# ✅ games/omok/handlers/omok_command_handler.py
from .move_handler import handle_omok_move
from .swap_handler import handle_swap_action_choice, handle_swap_color_choice, handle_swap_move
from .status_handler import handle_status_command
from .omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout, handle_omok_timeout
from .coord_utils import parse_move_coord
from core.utils.send_message import send_message_response
from core.logger import logger
from games.omok.utils.user_utils import get_user_id
from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board

# 좌표 파싱 함수 등 공통 유틸만 유지
def parse_move_coord(coord, board_size=15):
    """
    좌표 문자열을 파싱하여 (x, y) 좌표로 변환합니다.
    H8 또는 8H 형식을 모두 지원하며, 영문자는 가로(A-?), 숫자는 세로(1-?) 좌표로 처리합니다.
    """
    coord = coord.upper().strip()
    if len(coord) < 2 or len(coord) > 3:
        raise ValueError(f"좌표는 2~3자리여야 합니다 (예: H8, 8H, {chr(ord('A')+board_size-1)}{board_size})")
    if coord[0].isalpha():
        x_str, y_str = coord[0], coord[1:]
    elif coord[-1].isalpha():
        x_str, y_str = coord[-1], coord[:-1]
    else:
        raise ValueError(f"좌표에는 반드시 하나의 알파벳(A-{chr(ord('A')+board_size-1)})이 포함되어야 합니다")
    if not ('A' <= x_str <= chr(ord('A')+board_size-1)):
        raise ValueError(f"가로 좌표는 A-{chr(ord('A')+board_size-1)} 사이여야 합니다")
    try:
        y = int(y_str)
        if not (1 <= y <= board_size):
            raise ValueError
    except ValueError:
        raise ValueError(f"세로 좌표는 1-{board_size} 사이여야 합니다")
    x = ord(x_str) - ord('A')
    y = y - 1  # 0-based index로 변환
    return x, y

# 명령어 처리 함수
async def handle_omok_command(command, context):
    """오목 관련 명령어를 처리합니다."""
    command = command.strip()
    logger.info(f"[OMOK][DEBUG][omok_command_handler] handle_omok_command 진입: command={command}, context={context}")
    # 착수 명령어 처리
    if command.startswith("# 오목 착수") or (len(command) <= 4 and any(c.isalpha() for c in command)):
        session = omok_sessions.get(context.get("channel_id"))
        # swap_opening 상태에서만 swap_handler로 위임
        if session and getattr(session, "state", None) == "swap_opening":
            # swap_stage가 choose_color면 색 선택 핸들러로
            if getattr(session, "swap_stage", None) == "choose_color":
                logger.info(f"[OMOK][DEBUG][omok_command_handler] swap_opening-choose_color 분기: command={command}")
                return await handle_swap_color_choice(command, context)
            # placing_1~3 등은 기존대로
            logger.info(f"[OMOK][DEBUG][omok_command_handler] swap_opening-placing 분기: command={command}")
            return await handle_swap_move(command, context)
        # 본게임(playing) 상태에서만 move_handler로 위임
        if session and getattr(session, "state", None) == "playing":
            logger.info(f"[OMOK][DEBUG][omok_command_handler] 본게임(playing) 분기: command={command}")
            try:
                user_id = get_user_id(context)
            except ValueError as e:
                await send_message_response(context, str(e))
                return
            context["user_id"] = user_id
            logger.info(f"[OMOK][DEBUG][omok_command_handler] move_handler 위임 직전: command={command}, user_id={user_id}")
            return await handle_omok_move(command, context)
        logger.info(f"[OMOK][DEBUG][omok_command_handler] 착수 명령 분기 실패: command={command}, session_state={getattr(session, 'state', None)}")
        return None  # 매칭되는 명령어가 없음

# 1. 모드 선택 안내 메시지 전용 함수
async def send_omok_mode_selection_message(context):
    await send_message_response(
        context,
        "🎮 오목  선택해주세요:\n\n1️⃣ AI 대전\n2️⃣ 유저 대전\n\n'1' 또는 '2'를 입력해 주세요."
    )

# 2. 게임 시작 메시지/오목판 이미지 출력 함수 (DRY)
async def send_omok_game_start_message(context, session, mode):
    from games.omok.engine.rule_engine import get_rule_guide
    from games.omok.utils.board_size import get_omok_input_guide
    from games.omok.utils.send_image_service import send_omok_board_image
    from .omok_command_handler import clear_omok_timeout, reset_omok_timeout

    rule_guide = get_rule_guide(session.rule_options)
    rule_display_name = session.rule_options.get('name', session.rule)
    move_timeout = session.parameters.get('move_timeout_seconds', 300)
    ai_level = session.ai_level if hasattr(session, 'ai_level') else 5
    ai_mode = session.ai_mode if hasattr(session, 'ai_mode') else "hybrid"
    mode_name = "고급" if ai_mode == "llm" else "기본"
    board_style = session.parameters.get('board_style', 'classic')
    debug_mode = session.parameters.get('debug', False)
    debug_mode_text = "\n• 🔧 디버그 모드: 활성화" if debug_mode else ""
    first_player = session.player1['user_name']
    mode_specific_text = "오목판이 표시될 때까지 잠시만 기다려주세요."
    
    # 흑/백 플레이어 이름 동적 매핑
    if hasattr(session, 'player1_color') and hasattr(session, 'player2_color'):
        if session.player1_color == "black":
            black_player = session.player1['user_name']
            white_player = ai_player_text
        else:
            black_player = ai_player_text
            white_player = session.player1['user_name']
    else:
        black_player = session.player1['user_name']
        white_player = ai_player_text

    # 착수 안내도 turn에 따라
    if session.turn == "black":
        first_player = black_player
    else:
        first_player = white_player

    # swap1/swap2 분기
    if session.swap_rule == "swap1":
        settings_msg = (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 대전 모드: AI 대전 ({mode_name})\n"
            f"• 룰셋: {rule_display_name}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"\n👥 플레이어\n\n"
            f"• 흑돌(●): {black_player}\n"
            f"• 백돌(○): {white_player}\n\n"
            f"📍 착수 방법\n\n"
            f"• 가로: A-O (알파벳)\n"
            f"• 세로: 1-15 (숫자)\n"
            f"• 예시: H8 또는 8H\n\n"
            f"{mode_specific_text}"
        )
        await send_message_response(context, settings_msg)
        reset_omok_timeout(context, move_timeout)
        return
    elif session.swap_rule == "swap2":
        settings_msg = (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 대전 모드: AI 대전 ({mode_name})\n"
            f"• 룰셋: {rule_display_name}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"\n👥 플레이어\n\n"
            f"• 흑돌(●): {black_player}\n"
            f"• 백돌(○): {white_player}\n\n"
            f"📍 착수 방법\n\n"
            f"• 가로: A-O (알파벳)\n"
            f"• 세로: 1-15 (숫자)\n"
            f"• 예시: H8 또는 8H\n\n"
            f"{mode_specific_text}"
        )
        await send_message_response(context, settings_msg)
        reset_omok_timeout(context, move_timeout)
        return
    # AI 대전
    if mode == 1:
        ai_player_text = f"AI (레벨 {ai_level})"
        mode_desc = f"AI 대전 ({mode_name})"
        settings_msg = (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"📋 게임 정보\n\n"
            f"• 대전 모드: {mode_desc}\n"
            f"• 룰셋: {rule_display_name}\n"
            f"• 한 수 제한 시간: {move_timeout}초\n\n"
            f"📖 룰 설명\n\n{rule_guide}\n\n"
            f"{debug_mode_text}\n"
            f"\n👥 플레이어\n\n"
            f"• 흑돌(●): {black_player}\n"
            f"• 백돌(○): {white_player}\n\n"
            f"{get_omok_input_guide(session.board_size)}\n"
            f"{mode_specific_text}\n\n"
            f"{first_player}님 먼저 착수해 주세요."
        )
        clear_omok_timeout(context)
        await send_message_response(context, settings_msg)
        reset_omok_timeout(context, move_timeout)
        return
    # 유저 대전
    else:
        ai_player_text = "참가자 대기 중"
        mode_desc = "유저 대전"
        mode_specific_text = "다른 플레이어의 참가를 기다립니다.\n\n'참여', 'join' 중 하나를 입력하여 게임에 참여할 수 있습니다."
        # 유저 대전도 흑/백 동적 매핑 적용
        if hasattr(session, 'player1_color') and hasattr(session, 'player2_color'):
            if session.player1_color == "black":
                black_player = session.player1['user_name']
                white_player = ai_player_text
            else:
                black_player = ai_player_text
                white_player = session.player1['user_name']
        else:
            black_player = session.player1['user_name']
            white_player = ai_player_text
        simple_msg = (
            f"🎮 오목 게임이 시작되었습니다!\n\n"
            f"👥 플레이어\n"
            f"• 흑돌(●): {black_player}\n"
            f"• 백돌(○): {white_player}\n\n"
            f"{mode_specific_text}"
        )
        await send_message_response(context, simple_msg)
        return

# 오목 시작 명령 처리 함수 예시 (실제 위치에 맞게 적용)
async def handle_omok_start_command(context):
    # 세션 생성 및 초기화 등 기존 로직 유지
    # ...
    # 모드 선택 안내만 출력
    await send_omok_mode_selection_message(context)
    # 세션 상태를 "waiting_for_mode_selection"으로 저장 (필요시)
    session = omok_sessions.get(context["channel_id"])
    if session:
        session.state = "waiting_for_mode_selection"
    return []

# handle_mode_selection 함수 내에서만 게임 시작 메시지/오목판 이미지 출력
async def handle_mode_selection(command, context):
    channel_id = context["channel_id"]
    session = omok_sessions.get(channel_id)
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
    try:
        mode = int(command)
        if mode not in [1, 2]:
            raise ValueError("잘못된 모드 선택")
        # swap_rule 처리 등 기존 로직 유지
        swap_rule = None
        if hasattr(session, 'parameters') and 'swap_rule' in session.parameters:
            swap_rule = session.parameters['swap_rule']
            if swap_rule in ("swap1", "swap2"):
                session.rule_options = dict(session.rule_options)
                session.rule_options["swap_rule"] = swap_rule
                session.swap_rule = swap_rule
                session.swap_stage = "placing_1"
                session.swap_moves = []
                session.swap_action = None
        # 실제 게임 시작 메시지/오목판 이미지는 여기서만 출력
        await send_omok_game_start_message(context, session, mode)
        # swap1/swap2는 state를 덮어쓰지 않고, 그 외만 in_progress로 전이
        if not (swap_rule in ("swap1", "swap2")):
            session.state = "in_progress"
        return []
    except ValueError:
        await send_message_response(context, "잘못된 모드입니다. '1' 또는 '2'를 입력해주세요.")
        return []
    except Exception as e:
        error_msg = f"게임 모드 설정 중 오류가 발생했습니다: {str(e)}"
        logger.error(f"[OMOK] {error_msg}")
        await send_message_response(context, error_msg)
        return []
