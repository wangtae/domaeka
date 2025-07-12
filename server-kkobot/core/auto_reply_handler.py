import re
from core.utils.send_message import send_message_response
from core.logger import logger
from core.globals import auto_replies
from core.utils.prefix_utils import parse_prefix
from services.command_dispatcher import process_command
import core.globals as g


def substitute_placeholders(text, username, time_str=None, date_str=None):
    """
    자동응답 템플릿 내 플레이스홀더 치환 처리
    """
    if not text:
        return ""
    text = text.replace("{{USERNAME}}", username)
    if time_str:
        text = text.replace("{{TIME}}", time_str)
    if date_str:
        text = text.replace("{{DATE}}", date_str)
    return text


async def handle_auto_reply(receivedMessage: dict):
    """
    receivedMessage 기반 자동응답 핸들러
    """
    bot_name = receivedMessage.get("bot_name")
    channel_id = str(receivedMessage.get("channel_id"))
    room = receivedMessage.get("room")
    text = receivedMessage.get("text")
    sender = receivedMessage.get("sender")
    client_key = receivedMessage.get("client_key")
    user_hash = receivedMessage.get("user_hash")

    # 필수 정보 누락 시 중단 (client_key는 제외)
    required_fields = {
        "bot_name": bot_name,
        "channel_id": channel_id,
        "room": room,
        "sender": sender,
        "user_hash": user_hash,
    }
    missing = [k for k, v in required_fields.items() if not v]
    if missing:
        logger.warning(f"[AUTO_REPLY_SKIP] 필수 정보 누락: {missing} / 값: {required_fields}")
        return False

    try:
        auto_reply_data = g.auto_replies.get(bot_name, {}).get(channel_id)
        if not auto_reply_data:
            return False

        for item in auto_reply_data.get("auto_replies", []):
            trigger = item.get("trigger")
            reply = item.get("reply")
            command = item.get("command")

            if not trigger:
                continue

            if re.match(trigger, text, re.IGNORECASE):
                # ✅ 대기 메시지 처리를 위해 명령어 설정 확인
                command_config = g.PREFIX_MAP.get(command, {})
                use_waiting_message = command_config.get('use_waiting_message', False)

                # ✅ reply 먼저 전송 (대기 메시지 여부 확인)
                if reply and use_waiting_message:
                    response_text = substitute_placeholders(reply, sender)
                    await send_message_response(receivedMessage, response_text)

                # ✅ command 실행 (prefix → process_command()로 전달)
                if command:
                    command_result = await process_command({
                        "prefix": command,
                        "prompt": "",
                        "channel_id": channel_id,
                        "bot_name": bot_name,
                        "room": room,
                        "user_hash": user_hash,
                        "sender": sender,
                        "client_key": client_key,
                        "writer": receivedMessage.get("writer")  # writer 추가
                    })
                    if command_result:
                        await send_message_response(receivedMessage, command_result)

                return True  # 첫 trigger만 처리하고 종료

    except Exception as e:
        logger.exception(f"[AUTO_REPLY_ERROR] 자동응답 처리 중 오류 → {e}")

    return False
