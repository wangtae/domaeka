# ✅ games/omok/handlers/join_game_handler.py
from games.omok.handlers.omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.utils.send_image_service import send_omok_board_image
from games.omok.engine.rule_engine import get_rule_guide, get_forbidden_points
from games.omok.utils.board_size import get_omok_input_guide
from games.omok.utils.user_utils import get_user_id, get_user_name
import logging


async def handle_join_game(command, context):
    """
    유저 대전 참여를 처리하는 핸들러
    
    Args:
        command (str): 명령어 프롬프트
        context (dict): 메시지 컨텍스트
    """
    session = omok_sessions.get(context["channel_id"])
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
    if session.player2 is not None:
        await send_message_response(context, "이미 두 명이 참가 중입니다.")
        return []
    try:
        user_id = get_user_id(context)
    except ValueError as e:
        await send_message_response(context, str(e))
        return []
    user_name = get_user_name(context)
    logger = logging.getLogger(__name__)
    logger.info(f"[OMOK_JOIN] 참여 요청 user_id: {user_id}, user_name: {user_name}, context: {context}")
    try:
        session.join_player2(user_id, user_name)
        logger.info(f"[OMOK_JOIN] 세션 참여 후 상태: player1={session.player1}, player2={session.player2}, state={session.state}")
        start_msg = session.get_game_start_message()
        await send_message_response(context, start_msg)
        await session.proceed_turn(context)
    except Exception as e:
        logger.error(f"[OMOK][ERROR] handle_join_game 오류: {e}")
        await send_message_response(context, f"오목 참여 중 오류가 발생했습니다: {e}")
    return []
