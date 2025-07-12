from core.logger import logger
from games.omok.handlers.omok_globals import omok_sessions, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.utils.user_utils import get_user_id


async def handle_stop_command(prompt, context):
    """
    진행 중인 오목 게임을 중단합니다.
    게임은 무효 처리되며 DB에 저장되지 않습니다.
    
    Args:
        prompt (str): 명령어 프롬프트
        context (dict): 메시지 컨텍스트
    """
    channel_id = context["channel_id"]
    try:
        user_id = get_user_id(context)
    except ValueError as e:
        await send_message_response(context, str(e))
        return []
    clear_omok_timeout(context)

    session = omok_sessions.get(channel_id)
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []

    # 게임 참여자인지 확인
    if session.player1["user_id"] != user_id and (session.player2 and session.player2["user_id"] != user_id):
        await send_message_response(context, "게임 참여자만 게임을 중단할 수 있습니다.")
        return []

    # 세션 삭제
    del omok_sessions[channel_id]

    # 중단 메시지 전송
    await send_message_response(
        context,
        f"🚫 게임이 중단되었습니다.\n게임 결과는 기록되지 않습니다."
    )
    return []  # 빈 리스트 반환 