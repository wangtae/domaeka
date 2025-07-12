import aiomysql
import json
from core import globals as g
from core.logger import logger
from core.globals import PREFIX_MAP, user_map  # 전역 캐시
from core.utils.prefix_utils import parse_prefix
from core.utils.message_filters import is_natural_korean_message

from datetime import datetime, timedelta
import uuid


def is_meaningful_message(message, prefix, type=""):
    command_info = PREFIX_MAP.get(prefix, {})

    if command_info.get('always_meaningful', False):
        return True
    if command_info.get('not_meaningful_message', False):
        return False

    # ✅ 일반 메시지에 대한 추가 의미 판별
    return is_natural_korean_message(message, type)


async def save_chat_to_db(
        pool,
        room_name,
        sender,
        message,
        bot_name,
        is_mention=False,
        client_timestamp=None,
        is_group_chat=False,
        channel_id=None,
        log_id=None,
        user_hash=None,
        disable_chat_logs=False,  # ✅ 추가됨
        is_bot=False,  # ✅ 봇 여부 추가
        directive=None,  # ✅ 지시어 추가
        message_type="normal",  # ✅ 메시지 타입 추가
        is_meaningful=None,  # ✅ 의미 있는 메시지 여부 추가
        is_scheduled=False  # ✅ 스케줄링된 메시지 여부 추가
):
    prefix, _ = parse_prefix(message, bot_name=bot_name)
    
    # is_meaningful이 None인 경우에만 계산
    if is_meaningful is None:
        is_meaningful = is_meaningful_message(message, prefix)

    # ✅ category가 존재하면 그 값을 message_type에 사용하고, 없으면 'normal'
    command_info = PREFIX_MAP.get(prefix, {})
    if message_type == "normal":
        message_type = command_info.get('category', 'normal')

    query = """
        INSERT INTO kb_chat_logs (
            channel_id, user_hash, room_name, sender, message,
            directive, message_type, is_meaningful, bot_name,
            is_mention, is_group_chat, log_id, client_timestamp, server_timestamp, is_bot, is_scheduled
        ) VALUES (
            %s, %s, %s, %s, %s,
            %s, %s, %s, %s,
            %s, %s, %s, %s, NOW(), %s, %s
        )
    """

    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                await cursor.execute(query, (
                    channel_id,
                    user_hash,
                    room_name,
                    sender,
                    message,
                    directive or prefix,  # directive가 있으면 사용, 없으면 prefix 사용
                    message_type,
                    int(is_meaningful),
                    bot_name,
                    int(is_mention),
                    int(is_group_chat),
                    log_id,
                    client_timestamp,
                    int(is_bot),
                    int(is_scheduled)
                ))

                if not disable_chat_logs:
                    logger.info(
                        f"[DB 저장 성공] channel_id={channel_id} / user_hash={user_hash} / type={message_type} / meaningful={is_meaningful} / is_bot={is_bot} / is_scheduled={is_scheduled}")

                if channel_id and user_hash and sender:
                    cached_name = user_map.get(channel_id, {}).get(user_hash)
                    if cached_name != sender:
                        await upsert_user_map(pool, channel_id, user_hash, sender)
                        user_map.setdefault(channel_id, {})[user_hash] = sender

    except Exception as e:
        logger.error(f"[DB 저장 실패] channel_id={channel_id} / user_hash={user_hash} / error={e}", exc_info=True)


async def save_archived_message_to_db(pool, context: dict, message_text: str):
    """
    명령어 실행 결과를 kb_archived_messages 테이블에 저장하는 함수.
    Context 객체에서 필요한 정보를 추출하여 저장합니다.
    """
    archive_id = str(uuid.uuid4())  # 고유 ID 생성
    bot_name = context.get("bot_name")
    channel_id = context.get("channel_id")
    room_name = context.get("room")
    user_hash = context.get("user_hash")
    sender = context.get("sender")
    command_name = context.get("command_name", "")  # PREFIX_MAP의 키값 (예: # 유저 대화 요약)
    command_type = context.get("command_type", "")  # PREFIX_MAP의 type 값 (예: user_conversation_summary)
    timestamp = datetime.now() # 서버 시간 기준
    is_group_chat = context.get("is_group_chat", False)
    is_mention = context.get("is_mention", False)
    bot_version = g.VERSION # globals.py에서 버전 가져옴
    server_status = context.get("server_status", "normal") # 서버 상태
    raw_context_json = json.dumps(context, ensure_ascii=False) # 전체 Context 저장

    query = """
        INSERT INTO kb_archived_messages (
            archive_id, bot_name, channel_id, room_name, user_hash,
            sender, command_name, command_type, message_text, timestamp,
            is_group_chat, is_mention, bot_version, server_status, raw_context_json
        ) VALUES (
            %s, %s, %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s, %s, %s, %s
        )
    """

    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                await cursor.execute(query, (
                    archive_id, bot_name, channel_id, room_name, user_hash,
                    sender, command_name, command_type, message_text, timestamp,
                    int(is_group_chat), int(is_mention), bot_version, server_status, raw_context_json
                ))
                logger.info(f"[아카이브 저장 성공] command={command_name} / channel_id={channel_id} / archive_id={archive_id}")
    except Exception as e:
        logger.error(f"[아카이브 저장 실패] command={command_name} / channel_id={channel_id} / error={e}", exc_info=True)


async def fetch_recent_messages(pool, channel_id, user_hash=None, limit=20, minutes=None, include_bot_response=False):
    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cur:

                base_query = """
                    SELECT message
                    FROM kb_chat_logs
                    WHERE channel_id = %s
                    AND is_meaningful = 1
                """
                params = [channel_id]

                # 기본: 우리 봇 응답 제외
                if not include_bot_response:
                    base_query += " AND is_our_bot_response = 0"

                if user_hash:
                    base_query += " AND user_hash = %s"
                    params.append(user_hash)

                if minutes is not None:
                    time_threshold = datetime.utcnow() - timedelta(minutes=minutes)
                    base_query += " AND server_timestamp >= %s"
                    params.append(time_threshold)

                base_query += " ORDER BY server_timestamp DESC LIMIT %s"
                params.append(limit)

                await cur.execute(base_query, tuple(params))
                rows = await cur.fetchall()
                logger.debug(f"[DB_QUERY_RESULT] {len(rows)}개 메시지 조회됨")
                return [row[0] for row in rows]

    except Exception as e:
        logger.exception(f"[DB_FETCH_ERROR] 최근 메시지 조회 실패 → {e}")
        return []


async def get_user_hash_by_name(pool, channel_id, user_name):
    query = """
        SELECT user_hash FROM kb_user_map
        WHERE channel_id = %s AND user_name = %s
    """
    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cur:
                await cur.execute(query, (channel_id, user_name))
                row = await cur.fetchone()
                return row[0] if row else None
    except Exception as e:
        logger.exception(f"[USER_MAP_ERROR] 유저 해시 조회 실패 → {e}")
        return None


async def upsert_user_map(pool, channel_id, user_hash, user_name):
    select_query = """
        SELECT user_name, name_history FROM kb_user_map
        WHERE channel_id = %s AND user_hash = %s
    """
    insert_query = """
        INSERT INTO kb_user_map (channel_id, user_hash, user_name, last_seen, name_history)
        VALUES (%s, %s, %s, NOW(), %s)
        ON DUPLICATE KEY UPDATE 
            user_name = VALUES(user_name),
            last_seen = NOW(),
            name_history = VALUES(name_history)
    """
    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cur:
                await cur.execute(select_query, (channel_id, user_hash))
                row = await cur.fetchone()

                now = datetime.now().isoformat()
                history = []

                if row:
                    existing_name, existing_history = row

                    if existing_name != user_name:
                        if existing_history:
                            history = json.loads(existing_history)
                        history.insert(0, {"time": now, "name": user_name})  # 최신이 위로
                    else:
                        return  # 변경 없으면 DB 갱신도 불필요

                else:
                    history = [{"time": now, "name": user_name}]

                await cur.execute(insert_query, (
                    channel_id, user_hash, user_name, json.dumps(history, ensure_ascii=False)
                ))

                logger.info(f"[NICKNAME_CHANGED] '{row[0] if row else 'None'}' → '{user_name}' / 채널: {channel_id}")

    except Exception as e:
        logger.warning(f"[USER_MAP_UPSERT_ERROR] 유저 맵 저장 실패 → {e}")


async def fetchone(query: str, params: tuple = ()) -> dict:
    async with g.db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(query, params)
            result = await cur.fetchone()
            return result


async def fetchall(query: str, params: tuple = ()) -> list:
    async with g.db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(query, params)
            results = await cur.fetchall()
            return results


async def fetch_today_conversation_for_summary(pool, channel_id, only_meaningful=False, exclude_bots=True):
    """
    오늘(0시부터 현재까지)의 대화를 가져오는 함수

    Args:
        pool: 데이터베이스 연결 풀
        channel_id (str): 채널 ID
        only_meaningful (bool): 의미 있는 메시지만 가져올지 여부
        exclude_bots (bool): 봇 메시지를 제외할지 여부

    Returns:
        list: 메시지 목록
    """
    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                query = """
                    SELECT sender, message, server_timestamp
                    FROM kb_chat_logs
                    WHERE channel_id = %s
                    AND DATE(server_timestamp) = CURDATE()
                """

                params = [channel_id]

                if only_meaningful:
                    query += " AND is_meaningful = 1"

                if exclude_bots:
                    query += " AND is_bot = 0"

                query += " ORDER BY server_timestamp ASC"

                await cur.execute(query, params)
                messages = await cur.fetchall()

                logger.info(f"[대화요약] 오늘의 대화 {len(messages)}개 메시지 조회됨 (채널: {channel_id})")
                return messages
    except Exception as e:
        logger.exception(f"[대화요약] DB 조회 오류: {e}")
        return []


async def fetch_recent_conversation_for_summary(pool, channel_id, minutes=60, only_meaningful=False, exclude_bots=True):
    """
    최근 N분간의 대화를 가져오는 함수

    Args:
        pool: 데이터베이스 연결 풀
        channel_id (str): 채널 ID
        minutes (int): 가져올 시간 범위(분)
        only_meaningful (bool): 의미 있는 메시지만 가져올지 여부
        exclude_bots (bool): 봇 메시지를 제외할지 여부

    Returns:
        list: 메시지 목록
    """
    try:
        async with pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                query = """
                    SELECT sender, message, server_timestamp
                    FROM kb_chat_logs
                    WHERE channel_id = %s
                    AND server_timestamp >= DATE_SUB(NOW(), INTERVAL %s MINUTE)
                """

                params = [channel_id, minutes]

                if only_meaningful:
                    query += " AND is_meaningful = 1"

                if exclude_bots:
                    query += " AND is_bot = 0"

                query += " ORDER BY server_timestamp ASC"

                await cur.execute(query, params)
                messages = await cur.fetchall()

                logger.info(f"[대화요약] 최근 {minutes}분 {len(messages)}개 메시지 조회됨 (채널: {channel_id})")
                return messages
    except Exception as e:
        logger.exception(f"[대화요약] DB 조회 오류: {e}")
        return []
