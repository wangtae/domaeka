from .omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.constants import PIECES
from games.omok.engine.rule_engine import place_stone, is_move_allowed, get_forbidden_points
from games.omok.engine.ai_engine import choose_ai_move, format_move_history
from games.omok.utils.send_image_service import send_omok_board_image
from games.omok.utils.board_size import get_omok_input_guide
from .coord_utils import parse_move_coord
import re
from datetime import datetime
from core.logger import logger
from .swap_handler import log_board_state
from games.omok.utils.user_utils import get_user_id
from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board

async def handle_omok_move(command, context):
    """
    일반/AI 착수, 금수, 승패, 착수 제한 등 처리
    """
    logger.info(f"[OMOK][DEBUG][move_handler] handle_omok_move 진입: command={command}, context={context}")
    session = omok_sessions.get(context["channel_id"])
    log_board_state(session, context, tag="move_handler 진입")
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
    # 1. 본게임 상태가 아니면 착수 불가
    logger.debug(f"[OMOK][DEBUG] session.state={session.state}")
    if session.state not in (
        "playing", "opening", "swap_opening", "swap1", "swap2", "swap1_opening", "swap2_opening", "swap", "swap_stage"
    ):
        await send_message_response(context, "아직 게임이 시작되지 않았습니다.")
        return []
    move_timeout = session.parameters.get('move_timeout_seconds', 30)
    # H8 또는 8H 형식 매칭 (# 오목 착수 접두어 허용)
    coord_match = re.match(r"(?:# ?오목\s*착수\s*)?([A-Oa-o][1-9][0-5]?|[1-9][0-5]?[A-Oa-o])", command.strip())
    if not coord_match:
        board_size = session.board_size if hasattr(session, 'board_size') else 15
        await send_message_response(context, get_omok_input_guide(board_size))
        return []
    coord = coord_match.group(1)
    # user_id는 반드시 context["userHash"] 또는 context["user_hash"]만 사용
    try:
        user_id = get_user_id(context)
    except ValueError as e:
        await send_message_response(context, str(e))
        return []
    try:
        x, y = parse_move_coord(coord, session.board_size if hasattr(session, 'board_size') else 15)
        logger.info(f"[OMOK][DEBUG][move_handler] 좌표 파싱 결과: x={x}, y={y}")
    except ValueError as e:
        await send_message_response(context, f"⚠️ {str(e)}")
        return []
    except IndexError:
        await send_message_response(context, f"오목판 범위를 벗어난 좌표입니다. 올바른 범위 내에서 착수해 주세요.")
        return []

    # 유저 대전: 차례 유저만 착수 가능하도록 검증
    try:
        # 초반 착수 제한 검사
        restrict_list = session.rule_options.get("first_move_restrict", [])
        board_size = session.board_size if hasattr(session, 'board_size') else 15
        move_number = len(session.move_history) + 1 if hasattr(session, 'move_history') else 1
        restrict_type = None
        # 1수: center_only
        if move_number == 1 and "center_only" in restrict_list:
            restrict_type = "allowed"
        # 3수(흑): area5x5/area7x7
        elif move_number == 3:
            if "area5x5" in restrict_list:
                restrict_type = "forbidden"
            elif "area7x7" in restrict_list:
                restrict_type = "forbidden"
        if restrict_type:
            if not is_move_allowed(x, y, move_number, restrict_list, board_size, restrict_type=restrict_type):
                await send_message_response(context, "착수가 불가한 영역입니다. (초반 착수 제한)")
                return []
        # 정상 착수 처리 (모든 분기 DRY)
        log_board_state(session, context, tag="move_handler 착수 직전")
        try:
            logger.info(f"[OMOK][DEBUG][move_handler] make_move 호출 직전: user_id={user_id}, session.turn={session.turn}")
            await session.make_move(x, y, user_id, context)
            logger.info(f"[OMOK][DEBUG][move_handler] make_move 호출 완료")
            log_board_state(session, context, tag="move_handler 착수 후")
            logger.info(f"[OMOK][DEBUG] make_move 완료, turn={session.turn}, is_ai_turn={session.is_ai_turn()}, ai_level={session.ai_level}")
            # 디버그 모드일 때 착수 히스토리 메시지 전송
            if session.parameters.get('debug', False):
                history_text = format_move_history(session.move_history)
                await send_message_response(context, f"[디버그] 현재까지의 착수 현황:\n{history_text}")
            if session.is_ai_turn():
                logger.info(f"[OMOK][DEBUG] AI 턴 진입, ai_auto_move 호출")
                import asyncio
                asyncio.create_task(session.ai_auto_move(context))
            return
        except IndexError:
            await send_message_response(context, f"오목판 범위를 벗어난 좌표입니다. 올바른 범위 내에서 착수해 주세요.")
            return []
    except Exception as e:
        logger.error(f"착수 중 오류 발생: {e}")
        if 'list index out of range' in str(e):
            await send_message_response(context, f"오목판 범위를 벗어난 좌표입니다. 올바른 범위 내에서 착수해 주세요.")
        else:
            await send_message_response(context, f"착수 중 오류: {e}")
        return []

async def ai_auto_move(session, context):
    """
    AI 자동 착수 처리
    """
    pass

def check_forbidden_move(x, y, session):
    """
    금수(삼삼, 사사 등) 체크
    """
    pass

def place_stone_and_check_result(x, y, session):
    """
    돌을 놓고 승패/금수/착수 가능 여부 판정
    """
    pass 