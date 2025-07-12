# core/utils/bot_response_saver.py

import uuid
from datetime import datetime


async def save_bot_response(
        pool,
        channel_id: str,
        room_name: str,
        bot_name: str,
        message: str,
        directive: str = None,
        message_type: str = "normal",
        is_meaningful: int = 1,
        is_mention: int = 0,
        is_group_chat: int = 1,
        user_hash: str = "(bot)",  # ✅ 기본값
):
    """
    우리 봇이 생성한 응답 메시지를 kb_chat_logs_v2 테이블에 저장하는 함수.

    Args:
        pool: aiomysql 연결 풀
        channel_id (str): 채널 ID
        room_name (str): 방 이름
        bot_name (str): 봇 이름
        message (str): 저장할 메시지 텍스트
        directive (str, optional): 사용된 명령어 (ex: #로또추천). Defaults to None.
        message_type (str, optional): 메시지 타입 (ex: normal, lotto, bible_search, openai 등). Defaults to "normal".
        is_meaningful (int, optional): 의미 있는 메시지 여부 (0 또는 1). Defaults to 1.
        is_mention (int, optional): 멘션 여부 (0 또는 1). Defaults to 0.
        is_group_chat (int, optional): 그룹 채팅 여부 (0 또는 1). Defaults to 1.
    """

    # 저장하지 않아야 할 메시지들
    forbidden_keywords = ["[WAITING_MESSAGE]", "[THINKING_MESSAGE]", "[ERROR]"]

    if not message or any(keyword in message for keyword in forbidden_keywords) or message.strip() == "@no-reply":
        return  # 대기, 생각, 에러, @no-reply 메시지는 저장하지 않음

    log_id = str(uuid.uuid4())
    now = datetime.utcnow()

    query = """
        INSERT INTO kb_chat_logs (
            channel_id, user_hash, room_name, sender, message,
            directive, message_type, is_meaningful, bot_name,
            is_mention, is_group_chat, log_id, client_timestamp,
            is_bot, is_our_bot_response
        ) VALUES (
            %s, %s, %s, %s, %s,
            %s, %s, %s, %s,
            %s, %s, %s, %s,
            %s, %s
        )
    """

    params = (
        channel_id,
        user_hash,  # user_hash → 우리 봇은 사람이 아님
        room_name,
        bot_name,  # sender
        message,
        directive,
        message_type,
        is_meaningful,
        bot_name,
        is_mention,
        is_group_chat,
        log_id,
        now,
        1,  # is_bot = 1
        1  # is_our_bot_response = 1
    )

    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute(query, params)
            await conn.commit()
