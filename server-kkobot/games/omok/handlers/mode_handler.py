from .omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.engine.rule_engine import get_rule_guide
from games.omok.constants import PIECES
from games.omok.utils.send_image_service import send_omok_board_image
from games.omok.utils.board_size import get_omok_input_guide
import logging
import asyncio

logger = logging.getLogger(__name__)

async def handle_mode_selection(command, context):
    """
    게임 모드 선택을 처리합니다.
    """
    channel_id = context["channel_id"]
    session = omok_sessions.get(channel_id)
    
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
    
    try:
        mode = int(command)
        if mode not in [1, 2]:
            raise ValueError("잘못된 모드 선택")

        # swap_rule이 세션 파라미터에 있으면 rule_options에도 반영
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
                session.state = "swap_opening"
                # swap 오프닝 진입 시 보드/턴/기록도 항상 초기화
                session.board = [[None] * int(session.board_size) for _ in range(int(session.board_size))]
                session.turn = "black"
                session.move_history = []
        logger.info(f"[OMOK][DEBUG] handle_mode_selection 직전 swap_rule={session.swap_rule}, rule_options['swap_rule']={session.rule_options.get('swap_rule')}")
        if session.swap_rule == "swap1":
            settings_msg, board_msg, forbidden_points, move_timeout = session.get_swap_opening_message("swap1")
            await send_message_response(context, settings_msg)
            await send_omok_board_image(
                board=session.board,
                context=context,
                last_move=None,
                message_text=board_msg,
                session=session,
                forbidden_points=forbidden_points
            )
            reset_omok_timeout(context, move_timeout)
            return []
        elif session.swap_rule == "swap2":
            settings_msg, board_msg, forbidden_points, move_timeout = session.get_swap_opening_message("swap2")
            await send_message_response(context, settings_msg)
            await send_omok_board_image(
                board=session.board,
                context=context,
                last_move=None,
                message_text=board_msg,
                session=session,
                forbidden_points=forbidden_points
            )
            reset_omok_timeout(context, move_timeout)
            return []
        # 일반 모드 분기: OmokSession 메서드로 일원화
        mode_str = "ai" if mode == 1 else "user"
        try:
            _ = session.select_game_mode(mode_str, context)
            if mode == 2:
                # 유저 대전: 대기 메시지만 출력
                wait_msg = session.get_user_game_wait_message()
                await send_message_response(context, wait_msg)
                return []
            # AI 대전: 기존대로 게임 시작 메시지/오목판 이미지/착수 안내 출력
            game_start_message = session.get_game_start_message()
            await send_message_response(context, game_start_message)
            await session.proceed_turn(context)
            # AI 턴이면 proceed_turn 이후에 ai_auto_move를 별도 태스크로 호출
            if session.is_ai_turn():
                asyncio.create_task(session.ai_auto_move(context))
        except Exception as e:
            await send_message_response(context, f"게임 모드 설정 중 오류가 발생했습니다: {str(e)}")
        return []
    except ValueError:
        await send_message_response(context, "잘못된 모드입니다. '1' 또는 '2'를 입력해주세요.")
        return []
    except Exception as e:
        error_msg = f"게임 모드 설정 중 오류가 발생했습니다: {str(e)}"
        logger.error(f"[OMOK] {error_msg}")
        await send_message_response(context, error_msg)
        return [] 