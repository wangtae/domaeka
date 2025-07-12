from datetime import datetime
from games.omok.constants import DEFAULT_RULE, DEFAULT_RULE_OPTIONS, PIECES, GAME_MODE_SELECTION, BOARD_STYLES, OMOK_USAGE_GUIDE
from games.omok.db.omok_stats_dao import save_game_result, get_user_stats, get_ai_stats, get_vs_stats
from core.utils.send_message import send_message_response
from core.logger import logger
from games.omok.engine.rule_engine import get_rule_guide
from games.omok.utils.board_size import get_omok_input_guide
from games.omok.handlers.omok_globals import clear_omok_timeout
from games.omok.utils.piece_utils import to_internal_piece


class OmokSession:
    def __init__(self, game_id, player1, player2=None, ai_level=None, rule=DEFAULT_RULE, rule_options=None, ai_mode="hybrid", parameters=None, player1_color="black", player2_color="white"):
        # player1, player2 모두 user_id=userHash(고유값), user_name=sender(닉네임)만 사용
        self.game_id = game_id
        self.player1 = player1  # {"user_id": userHash, "user_name": sender}
        self.ai_level = ai_level
        self.ai_mode = ai_mode  # "hybrid" 또는 "llm"
        self.parameters = parameters or {}
        self.rule = rule
        self.rule_options = rule_options or DEFAULT_RULE_OPTIONS.copy()
        self.player1_color = player1_color
        self.player2_color = player2_color
        # player2는 생성 시점에는 항상 인자로만 받음 (AI 대전 선택 시에만 AI 할당)
        self.player2 = player2  # AI 대전이면 AI dict, 유저 대전이면 None 또는 유저 dict
        self.turn = 'black'  # black always starts first, but who is black may differ
        self.board_size = int(self.parameters.get("board_size", 15))
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] OmokSession 생성자 진입, self.parameters: {self.parameters}")
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] OmokSession 생성자에서 self.board_size: {self.board_size}")
        self.board = [[None] * self.board_size for _ in range(self.board_size)]
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] 오목판 생성, 크기: {self.board_size}x{self.board_size}")
        self.move_history = []
        self.winner = None
        self.started_at = datetime.now()
        self.last_move_time = None  # 마지막 착수 시각
        # swap 관련 상태 변수
        self.swap_rule = self.rule_options.get("swap_rule", "none")
        if self.swap_rule in ("swap1", "swap2"):
            self.state = "swap_opening"
            self.swap_stage = "placing_1"
            # swap 오프닝 주체 및 색 선택자 지정
            if self.player1_color == "black":
                self.opening_player = self.player1  # 흑이 3수 착수
                self.color_chooser = self.player2 if self.player2 else {"user_id": "AI", "user_name": f"AI(Lv.{self.ai_level if self.ai_level else 5})"}
            else:
                self.opening_player = self.player2 if self.player2 else {"user_id": "AI", "user_name": f"AI(Lv.{self.ai_level if self.ai_level else 5})"}
                self.color_chooser = self.player1  # 백이 색 선택
        else:
            self.state = "playing"
            self.swap_stage = "normal"
        self.swap_moves = []  # swap 단계에서 놓인 돌 좌표 기록
        self.swap_action = None  # swap2에서 swap/add_moves 선택
        # 타임아웃 관리(선택)
        self.timeout_type = None  # "start_timeout", "move_timeout" 등

    def get_player_by_color(self, color):
        """
        현재 돌색(black/white)에 해당하는 플레이어 반환 (AI/유저/참가자 모두 지원)
        """
        if self.player1_color == color:
            return self.player1
        elif self.player2 and self.player2_color == color:
            return self.player2
        elif self.ai_level and self.player2_color == color:
            return {"user_id": "AI", "user_name": f"AI(Lv.{self.ai_level})"}
        else:
            return None

    def get_current_player_name(self):
        """
        현재 턴의 플레이어 이름 반환 (흑/백/AI/유저/참가자 모두 지원)
        """
        current_player = self.get_player_by_color(self.turn)
        return current_player["user_name"] if current_player else "알 수 없음"

    async def make_move(self, x, y, user_id, context):
        """
        착수 검증, 착수 처리, 착수 메시지 생성, 종료 판정, 예외 처리까지 DRY하게 통합
        """
        from core.utils.send_message import send_message_response
        from games.omok.engine.rule_engine import place_stone, get_forbidden_points
        from games.omok.handlers.omok_globals import clear_omok_timeout
        try:
            if not user_id:
                await send_message_response(context, "유저 정보가 올바르지 않습니다. (userHash 누락)")
                return
            current_player = self.get_player_by_color(self.turn)
            stone = PIECES[self.turn]
            if not current_player or user_id != current_player["user_id"]:
                raise ValueError(f"지금은 {self.get_current_player_name()}({stone})의 차례입니다.")
            if self.board[y][x] is not None:
                raise ValueError("이미 착수된 위치입니다.")
            # 금수/초반 제한 등은 핸들러에서 사전 검증
            from games.omok.utils.piece_utils import to_internal_piece
            result = place_stone(self.board, x, y, to_internal_piece(self.turn), self.rule_options)
            if result["result"] == "invalid":
                raise ValueError(result["reason"])
            elif result["result"] == "forbidden":
                self.move_history.append((x, y, to_internal_piece(self.turn)))
                raise ValueError(result["reason"])
            elif result["result"] == "lose":
                self.move_history.append((x, y, to_internal_piece(self.turn)))
                await send_message_response(context, result["reason"])
                await self.finish_and_cleanup(result["winner"], context)
                return
            elif result["result"] == "win":
                self.move_history.append((x, y, to_internal_piece(self.turn)))
                await send_message_response(context, f"{current_player['user_name']}님({stone})이 {chr(65+x)}{y+1}에 착수했습니다.")
                await self.finish_and_cleanup('black' if self.turn == 'black' else 'white', context)
                return
            # 정상 착수
            self.move_history.append((x, y, to_internal_piece(self.turn)))
            # 착수 메시지
            await send_message_response(context, f"{current_player['user_name']}님({stone})이 {chr(65+x)}{y+1}에 착수했습니다.")
            # AI 대전일 때만 착수 후 즉시 이미지 전송
            if self.ai_level:
                await self.send_board_image(context)
            if self.is_full():
                await self.finish_and_cleanup(None, context)
                return
            # 정상 착수 후 타임아웃 해제
            clear_omok_timeout(context)
            # 턴 변경
            self.turn = 'white' if self.turn == 'black' else 'black'
            await self.proceed_turn(context)
            logger.info(f"[OMOK][TURN][make_move] state={self.state}, turn={self.turn}, player1_id={self.player1.get('user_id')}, player1_name={self.player1.get('user_name')}, player2_id={self.player2.get('user_id') if self.player2 else None}, player2_name={self.player2.get('user_name') if self.player2 else None}, ai_level={self.ai_level}, move_history={self.move_history}")
        except Exception as e:
            logger.error(f"[OMOK] 착수 처리 오류: {e}")
            await send_message_response(context, f"착수 중 오류: {e}")

    def get_last_move(self):
        return self.move_history[-1][:2] if self.move_history else None

    def is_full(self):
        return all(cell is not None for row in self.board for cell in row)

    async def end_game(self, winner, context):
        """게임 종료 처리"""
        try:
            user1_id = self.player1["user_id"]
            user1_name = self.player1["user_name"]
            user2_id = self.player2["user_id"] if self.player2 else None
            user2_name = self.player2["user_name"] if self.player2 else None
            ai_name = f"AI(Lv.{self.ai_level})" if self.ai_level else None

            # 실제 승자 플레이어 동적 매핑
            winner_player = self.get_player_by_color(winner) if winner else None
            winner_name = winner_player["user_name"] if winner_player else None
            winner_id = winner_player["user_id"] if winner_player else None

            # 메시지/승자/패자 결정
            if winner in ("black", "white") and winner_player:
                end_message = (
                    "🎉 게임 종료: 승리!\n\n"
                    f"🏆 승자: {winner_name}\n"
                    f"🎮 사용한 돌: {PIECES[winner]}\n\n"
                )
            elif winner in ("black", "white") and self.ai_level and winner_player is None:
                # AI가 승리한 경우(플레이어가 아닌 AI)
                end_message = (
                    "😢 게임 종료: 패함!\n\n"
                    f"🏆 승자: {ai_name}\n"
                    f"🎮 사용한 돌: {PIECES[winner]}\n\n"
                )
                winner_id = None
            else:
                # 무승부
                end_message = (
                    "🤝 게임 종료: 무승부!\n\n"
                    "더 이상 둘 수 있는 자리가 없습니다.\n"
                    "양 플레이어 모두 수고하셨습니다!\n"
                )
                winner_id = None

            # 게임 결과 저장 (AI가 승리해도 user1_id로 저장)
            await save_game_result(self, winner_id if winner_id else user1_id)

            messages = []
            # 전적 조회 및 메시지 생성
            if self.ai_level:
                # AI 대전: 유저 전체 전적 + AI 상대 전적
                user_stats = await get_user_stats(user1_id)
                if user_stats:
                    messages.append(
                        f"📊 전체 전적\n"
                        f"• {user_stats['wins']}승 {user_stats['losses']}패 {user_stats['draws']}무\n"
                        f"• 승률: {user_stats['win_rate']}%\n"
                        f"• 레이팅: {user_stats['rating']}"
                    )
                ai_stats = await get_ai_stats(user1_id, self.ai_level)
                if ai_stats:
                    messages.append(
                        f"\n🤖 AI(Lv.{self.ai_level}) 상대 전적\n"
                        f"• {ai_stats['wins']}승 {ai_stats['losses']}패 {ai_stats['draws']}무\n"
                        f"• 승률: {ai_stats['win_rate']}%"
                    )
            elif self.player2:
                # 유저 대전: 양쪽 유저 전체 전적 + 상대 전적
                user1_stats = await get_user_stats(user1_id)
                if user1_stats:
                    messages.append(
                        f"📊 전체 전적 ({user1_name})\n"
                        f"• {user1_stats['wins']}승 {user1_stats['losses']}패 {user1_stats['draws']}무\n"
                        f"• 승률: {user1_stats['win_rate']}%\n"
                        f"• 레이팅: {user1_stats['rating']}"
                    )
                user2_stats = await get_user_stats(user2_id)
                if user2_stats:
                    messages.append(
                        f"\n📊 전체 전적 ({user2_name})\n"
                        f"• {user2_stats['wins']}승 {user2_stats['losses']}패 {user2_stats['draws']}무\n"
                        f"• 승률: {user2_stats['win_rate']}%\n"
                        f"• 레이팅: {user2_stats['rating']}"
                    )
                vs_stats = await get_vs_stats(user1_id, user2_id)
                if vs_stats:
                    messages.append(
                        f"\n⚔️ {user1_name} vs {user2_name}\n"
                        f"• {vs_stats['wins']}승 {vs_stats['losses']}패 {vs_stats['draws']}무\n"
                        f"• 승률: {vs_stats['win_rate']}%"
                    )
            else:
                # 무승부 또는 예외 상황: player1만 표시
                user_stats = await get_user_stats(user1_id)
                if user_stats:
                    messages.append(
                        f"📊 전체 전적\n"
                        f"• {user_stats['wins']}승 {user_stats['losses']}패 {user_stats['draws']}무\n"
                        f"• 승률: {user_stats['win_rate']}%\n"
                        f"• 레이팅: {user_stats['rating']}"
                    )

            await send_message_response(context, end_message + "\n".join(messages))

        except Exception as e:
            logger.error(f"[OMOK] 게임 결과 저장 중 오류 발생: {str(e)}")
            await send_message_response(context, f"게임 결과 저장 중 오류가 발생했습니다: {str(e)}")

    def get_opponent_color(self):
        return "white" if self.turn == "black" else "black"

    async def finish_and_cleanup(self, winner, context):
        self.state = "ended"
        # 마지막 오목판 이미지를 항상 전송
        await self.send_board_image(context)
        await self.end_game(winner, context)
        from games.omok.handlers.omok_globals import omok_sessions, clear_omok_timeout
        del omok_sessions[context["channel_id"]]
        clear_omok_timeout(context)

    def join_player2(self, user_id, user_name):
        logger.info(f"[OMOK][TURN][join_player2] state={self.state}, turn={self.turn}, player1_id={self.player1.get('user_id')}, player1_name={self.player1.get('user_name')}, player2_id(before)={self.player2.get('user_id') if self.player2 else None}, player2_name(before)={self.player2.get('user_name') if self.player2 else None}, ai_level={self.ai_level}, move_history={self.move_history}")
        if self.player2 is not None:
            raise ValueError("이미 게임이 시작되었습니다.")
        if self.player1["user_id"] == user_id:
            raise ValueError("자신의 게임에는 참여할 수 없습니다.")
        if self.state != "waiting_for_player2":
            raise ValueError(f"현재는 참여할 수 없는 상태입니다. (state: {self.state})")
        self.player2 = {"user_id": user_id, "user_name": user_name}
        self.state = "playing"
        logger.info(f"[OMOK][TURN][join_player2] player2_id(after)={self.player2.get('user_id')}, player2_name(after)={self.player2.get('user_name')}, state={self.state}")

    def is_omok_player(self, user_id):
        allowed_ids = [self.player1["user_id"]]
        if self.player2:
            allowed_ids.append(self.player2["user_id"])
        return user_id in allowed_ids

    def get_join_message(self):
        from games.omok.engine.rule_engine import get_rule_guide
        from games.omok.utils.board_size import get_omok_input_guide
        rule_guide = get_rule_guide(self.rule_options)
        board_style = self.parameters.get('board_style', 'classic')
        debug_mode_text = "• 🔍 디버그 모드: 활성화\n" if self.parameters.get('debug', False) else ""
        ban_spot_on = self.parameters.get('visualize_forbidden', False)
        ban_spot_text = f"💡 금수 표시: {'ON' if ban_spot_on else 'OFF'} (명령어: --ban-spot=true)\n\n"
        move_timeout = self.parameters.get('move_timeout_seconds', 300)
        player2_name = self.player2['user_name'] if self.player2 and 'user_name' in self.player2 else "대기 중"
        return (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 룰셋: {self.parameters.get('rule_str', '기본 룰')}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"{debug_mode_text}"
            f"👥 플레이어\n\n"
            f"• 흑돌(●): {self.player1['user_name']}\n"
            f"• 백돌(○): {player2_name}\n\n"
            f"🧱 바둑판 스타일: {board_style}\n\n"
            f"{ban_spot_text}"
            f"{get_omok_input_guide(self.board_size)}\n\n"
            f"게임을 시작합니다!\n\n"
            f"{self.player1['user_name']}님 먼저 착수해 주세요."
        )

    def get_mode_selection_message(self):
        style_info = BOARD_STYLES[self.parameters.get('board_style', 'classic')]
        style_desc = f"\n\n📋 : {style_info['name']}\n{style_info['description']}"
        mode_msg = f"{GAME_MODE_SELECTION['message']}{style_desc}\n\n{OMOK_USAGE_GUIDE}"
        return mode_msg

    def get_swap_opening_message(self, swap_type):
        rule_guide = get_rule_guide(self.rule_options)
        rule_display_name = self.rule_options.get('name', self.rule)
        move_timeout = self.parameters.get('move_timeout_seconds', 300)
        ai_level = self.ai_level if hasattr(self, 'ai_level') and self.ai_level else 5
        mode_name = "고급" if self.ai_mode == "llm" else "기본"
        ai_player_text = f"AI (레벨 {ai_level})"
        first_player = self.opening_player['user_name']
        mode_desc = f"AI 대전 ({mode_name})"
        mode_specific_text = "오목판이 표시될 때까지 잠시만 기다려주세요."
        settings_msg = (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 대전 모드: {mode_desc}\n"
            f"• 룰셋: {rule_display_name}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"👥 플레이어\n\n"
            f"• 흑돌(●): {self.player1['user_name']}\n"
            f"• 백돌(○): {ai_player_text}\n\n"
            f"{get_omok_input_guide(self.board_size)}"
            f"{mode_specific_text}"
        )
        board_msg = f"{swap_type} 룰입니다.\n\n{first_player}님 1번째 수를 착수해 주세요."
        forbidden_points = None
        if self.parameters.get("visualize_forbidden", False):
            from games.omok.engine.rule_engine import get_forbidden_points
            forbidden_points = get_forbidden_points(self.board, self.rule_options, self.turn)
        return settings_msg, board_msg, forbidden_points, move_timeout

    def get_ai_game_start_message(self):
        try:
            rule_guide = get_rule_guide(self.rule_options)
            move_timeout = self.parameters.get('move_timeout_seconds', 30)
            ban_spot_on = self.parameters.get('visualize_forbidden', False)
            ban_spot_text = f"💡 금수 표시: {'ON' if ban_spot_on else 'OFF'} (명령어: --ban-spot=true)\n\n"
            player1_name = self.player1['user_name'] if self.player1 and 'user_name' in self.player1 else '플레이어1'
            ai_level = self.ai_level if hasattr(self, 'ai_level') and self.ai_level else 5
            player2_name = self.player2['user_name'] if self.player2 and 'user_name' in self.player2 else f"AI (레벨 {ai_level})"
            return (
                f"🎮 오목 AI 대전이 시작되었습니다!\n\n"
                f"• 흑돌(●): {player1_name}\n"
                f"• 백돌(○): {player2_name}\n\n"
                f"• 한 수 제한 시간: {move_timeout}초\n"
                f"{ban_spot_text}"
                f"{rule_guide}\n\n"
                f"{player1_name}님 먼저 착수해 주세요."
            )
        except Exception as e:
            logger.error(f"[OMOK][ERROR] get_ai_game_start_message 오류: {e}")
            raise

    def get_user_game_wait_message(self):
        try:
            player1_name = self.player1['user_name'] if self.player1 and 'user_name' in self.player1 else '플레이어1'
            return (
                "👥 유저 대전이 선택되었습니다!\n\n"
                "다른 유저가 '참여', '참가', 'join' 명령으로 참가하면 게임이 시작됩니다.\n\n"
                "참여를 기다리는 중입니다..."
            )
        except Exception as e:
            logger.error(f"[OMOK][ERROR] get_user_game_wait_message 오류: {e}")
            raise

    def select_game_mode(self, mode, context):
        try:
            if mode == "ai":
                self.ai_level = int(self.parameters.get("ai_level", 5))
                self.player2 = {"user_id": "AI", "user_name": f"AI (레벨 {self.ai_level})"}
                self.state = "playing"
                return self.get_ai_game_start_message()
            elif mode == "user":
                self.state = "waiting_for_player2"
                return self.get_user_game_wait_message()
            else:
                raise ValueError("잘못된 모드 선택입니다. (ai/user 만 허용)")
        except Exception as e:
            logger.error(f"[OMOK][ERROR] select_game_mode 오류: {e}")
            raise

    def get_game_start_message(self):
        from games.omok.engine.rule_engine import get_rule_guide
        from games.omok.utils.board_size import get_omok_input_guide
        rule_guide = get_rule_guide(self.rule_options)
        rule_display_name = self.rule_options.get('name', self.rule)
        move_timeout = self.parameters.get('move_timeout_seconds', 60)
        board_style = self.parameters.get('board_style', 'classic')
        ban_spot_on = self.parameters.get('visualize_forbidden', False)
        ban_spot_text = f"💡 금수 표시: {'ON' if ban_spot_on else 'OFF'} (명령어: --ban-spot=true)\n"
        player1_name = self.player1['user_name'] if self.player1 and 'user_name' in self.player1 else '플레이어1'
        player2_name = self.player2['user_name'] if self.player2 and 'user_name' in self.player2 else "대기 중"
        # 흑/백 동적 매핑
        black_player = player1_name if self.player1_color == 'black' else player2_name
        white_player = player2_name if self.player2_color == 'white' else player1_name
        return (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 룰셋: {rule_display_name}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"👥 플레이어\n\n"
            f"• 흑돌(●): {black_player}\n"
            f"• 백돌(○): {white_player}\n\n"
            f"🧱 바둑판 스타일: {board_style}\n\n"
            f"{ban_spot_text}\n"
            f"{get_omok_input_guide(self.board_size)}\n\n"
            f"게임을 시작합니다.\n오목판이 표시될 때 까지 잠시만 기다려주세요."
        )

    async def proceed_turn(self, context):
        """
        모든 턴 진행 로직을 DRY하게 관리: AI/유저 분기, 자동 착수, 타임아웃, 안내 메시지/이미지 전송 등
        """
        logger.info(f"[OMOK][TURN][proceed_turn] state={self.state}, turn={self.turn}, player1_id={self.player1.get('user_id')}, player1_name={self.player1.get('user_name')}, player2_id={self.player2.get('user_id') if self.player2 else None}, player2_name={self.player2.get('user_name') if self.player2 else None}, ai_level={self.ai_level}, move_history={self.move_history}")
        from core.utils.send_message import send_message_response
        try:
            # 1. 게임 종료 상태면 무시
            if self.state == "ended":
                return

            current_player = self.get_player_by_color(self.turn)
            stone = PIECES[self.turn]

            # 1. 턴이 누구든 타임아웃 등록
            move_timeout = self.parameters.get('move_timeout_seconds', 0)
            logger.info(f"[OMOK][TURN] proceed_turn: state={self.state}, turn={self.turn}, ai_level={self.ai_level}, move_timeout={move_timeout}, context_channel_id={context.get('channel_id')}")
            if move_timeout > 0:
                logger.info(f"[OMOK][TURN] reset_omok_timeout 호출: move_timeout={move_timeout}, channel_id={context.get('channel_id')}")
                from games.omok.handlers.omok_globals import reset_omok_timeout
                reset_omok_timeout(context, move_timeout)

            # 2. AI 턴이면 '생각중' 메시지만 전송 후 종료 (ai_auto_move는 외부에서 호출)
            if self.is_ai_turn():
                await send_message_response(context, f"AI님({stone})이 착수할 곳을 생각중이에요.")
                return

            # 3. 유저 턴: 오목판 이미지+차례 안내
            await self.send_board_image(context)
            await send_message_response(context, f"{current_player['user_name']}님({stone})의 차례입니다.")
        except Exception as e:
            logger.error(f"[OMOK] proceed_turn 오류: {e}")
            await send_message_response(context, f"턴 진행 중 오류가 발생했습니다: {e}")

    def is_ai_turn(self):
        # AI 대전이면서 현재 턴이 AI의 색깔일 때
        return self.ai_level and self.turn == self.player2_color

    async def ai_auto_move(self, context):
        logger.info(f"[OMOK][TURN][ai_auto_move] state={self.state}, turn={self.turn}, player1_id={self.player1.get('user_id')}, player1_name={self.player1.get('user_name')}, player2_id={self.player2.get('user_id') if self.player2 else None}, player2_name={self.player2.get('user_name') if self.player2 else None}, ai_level={self.ai_level}, move_history={self.move_history}")
        from games.omok.engine.ai_engine import choose_ai_move
        from games.omok.engine.rule_engine import place_stone
        from games.omok.constants import PIECES
        from core.utils.send_message import send_message_response
        from games.omok.handlers.omok_globals import clear_omok_timeout
        try:
            # context에 omok_session을 명확히 추가 (AI 전략 함수에서 세션 정보 활용)
            context["omok_session"] = self
            (ax, ay), used_llm = await choose_ai_move(
                board=self.board,
                context=context,
                player_piece='B' if self.turn == 'black' else 'W',
                move_history=self.move_history
            )
            if ax is None or ay is None:
                raise ValueError("AI가 유효한 착수 위치를 찾지 못했습니다.")
            result_ai = place_stone(self.board, ax, ay, self.turn, self.rule_options)
            ai_player = self.get_player_by_color(self.turn)
            stone = PIECES[self.turn]
            coord_str = f"{chr(65+ax)}{ay+1}"
            if result_ai["result"] == "invalid":
                raise ValueError(result_ai["reason"])
            elif result_ai["result"] == "forbidden":
                raise ValueError(result_ai["reason"])
            elif result_ai["result"] == "lose":
                await send_message_response(context, f"AI님({stone})이 {coord_str}에 착수했습니다.")
                await self.send_board_image(context)
                await self.finish_and_cleanup('black' if self.turn == 'white' else 'white', context)
                return
            elif result_ai["result"] == "win":
                await send_message_response(context, f"AI님({stone})이 {coord_str}에 착수했습니다.")
                await self.send_board_image(context)
                await self.finish_and_cleanup('black' if self.turn == 'black' else 'white', context)
                return
            # 정상 착수
            self.move_history.append((ax, ay, to_internal_piece(self.turn)))
            await send_message_response(context, f"AI님({stone})이 {coord_str}에 착수했습니다.")
            # 정상 착수 후 타임아웃 해제
            clear_omok_timeout(context)
            # 턴 변경 및 proceed_turn (이미지는 proceed_turn에서만 전송)
            self.turn = 'white' if self.turn == 'black' else 'black'
            await self.proceed_turn(context)
        except Exception as e:
            logger.error(f"[OMOK][ERROR] ai_auto_move 오류: {e}")
            await send_message_response(context, f"AI 착수 중 오류가 발생했습니다: {e}")

    async def prompt_user_move(self, context):
        """
        오목판 이미지와 안내 메시지를 항상 전송 (금수/옵션/UX 통일)
        """
        await self.proceed_turn(context)

    async def send_board_image(self, context, message_text=None):
        """
        오목판 이미지만 별도 전송 (필요시)
        """
        from games.omok.utils.send_image_service import send_omok_board_image
        forbidden_points = None
        if self.parameters.get("visualize_forbidden", False):
            from games.omok.engine.rule_engine import get_forbidden_points
            forbidden_points = get_forbidden_points(self.board, self.rule_options, self.turn)
        await send_omok_board_image(
            board=self.board,
            context=context,
            last_move=self.get_last_move(),
            message_text=message_text,
            session=self,
            forbidden_points=forbidden_points
        )

    async def send_message(self, context, message):
        """
        안내 메시지 전송 (모든 안내 메시지 포맷 통일)
        """
        from core.utils.send_message import send_message_response
        await send_message_response(context, message)

    async def handle_timeout(self, context):
        """
        타임아웃 발생 시 처리 (종료, 안내 등)
        """
        await self.finish_and_cleanup(None, context)
        await self.send_message(context, "⏰ 제한 시간 초과로 게임이 종료되었습니다.")

