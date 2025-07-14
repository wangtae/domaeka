import aiomysql
import json
from datetime import datetime
from core.logger import logger
import pytz

import core.globals as g
from core.utils.send_message import send_message, send_message_response


async def save_or_update_user(bot_name, channel_id, user_hash, new_name, client_key):
    db_pool = g.db_pool
    now = datetime.now().isoformat()

    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')  # 이 한 줄 추가!
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                "SELECT * FROM kb_user_map WHERE channel_id = %s AND user_hash = %s",
                (channel_id, user_hash)
            )
            user = await cur.fetchone()

            if user:
                old_name = user["user_name"]
                if old_name != new_name:
                    # 닉네임 변경 탐지!
                    history = json.loads(user.get("name_history") or "[]")
                    history.append({"time": now, "name": new_name})

                    await cur.execute(
                        "UPDATE kb_user_map SET user_name = %s, last_seen = NOW(), name_history = %s WHERE id = %s",
                        (new_name, json.dumps(history, ensure_ascii=False), user["id"])
                    )

                    # ✅ 닉네임 변경 알림 전송
                    if await is_nickname_alert_enabled(bot_name, channel_id):
                        await send_nickname_alert(bot_name, channel_id, old_name, new_name, client_key)


                else:
                    await cur.execute(
                        "UPDATE kb_user_map SET last_seen = NOW() WHERE id = %s", (user["id"],)
                    )
            else:
                history = [{"time": now, "name": new_name}]
                await cur.execute(
                    "INSERT INTO kb_user_map (channel_id, user_hash, user_name, last_seen, name_history) VALUES (%s, %s, %s, NOW(), %s)",
                    (channel_id, user_hash, new_name, json.dumps(history, ensure_ascii=False))
                )
        await conn.commit()

async def send_nickname_alert(bot_name, channel_id, old_name, new_name, client_key):
    db_pool = g.db_pool
    user_hash = None
    # user_hash를 찾기 위해 kb_user_map에서 최근 변경된 유저를 조회
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                "SELECT user_hash, name_history FROM kb_user_map WHERE channel_id = %s AND user_name = %s ORDER BY id DESC LIMIT 1",
                (channel_id, new_name)
            )
            user = await cur.fetchone()
            if user:
                user_hash = user["user_hash"]
                name_history_raw = user.get("name_history")
            else:
                name_history_raw = None

    # 닉네임 변경 이력 메시지 생성
    history_lines = []
    if name_history_raw:
        try:
            history = json.loads(name_history_raw)
            # 최신순 정렬 (time 내림차순)
            history_sorted = sorted(history, key=lambda x: x.get("time", ""), reverse=True)
            for item in history_sorted:
                # 시간 포맷 변환
                try:
                    dt = datetime.fromisoformat(item["time"])
                    dt = dt.astimezone(pytz.timezone('Asia/Seoul'))
                    time_str = dt.strftime('%Y-%m-%d %H:%M:%S')
                except Exception:
                    time_str = item["time"]
                history_lines.append(f"{time_str} - {item['name']}")
        except Exception as e:
            logger.warning(f"[NICKNAME_ALERT] name_history 파싱 실패: {e}")

    history_message = ""
    if history_lines:
        history_message = "\n\n닉네임 변경 이력:\n" + "\n".join(history_lines)

    message = (
        f"(꺄아) 누군가 이름이 변경되었어요"
        + ("\u200b" * 500)
        + f"\n\n\n'{old_name}' → '{new_name}' (으)로\n닉네임이 변경되었습니다."
        + history_message
    )

    # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
    context = {
        'bot_name': bot_name,
        'channel_id': channel_id,
        'room': g.channel_id_to_room.get(channel_id, "")
    }
    
    await send_message_response(context, message)
    logger.info(f"[NICKNAME_ALERT] 메시지 전송 성공 → {bot_name} / {g.channel_id_to_room.get(channel_id, '')}")

async def is_nickname_alert_enabled(bot_name, channel_id):
    try:
        if not g.schedule_rooms:
            logger.warning(f"[NICKNAME_ALERT_CHECK] g.schedule_rooms가 초기화되지 않았습니다.")
            return False

        channel_id_str = str(channel_id)
        settings = g.schedule_rooms.get(bot_name, {}).get(channel_id_str, {})
        enabled = settings.get("nickname_alert", False)

        logger.debug(f"[NICKNAME_ALERT_CHECK] bot={bot_name}, channel_id={channel_id_str}, enabled={enabled}")
        return enabled
    except Exception as e:
        logger.warning(f"[NICKNAME_ALERT_CHECK_ERROR] bot={bot_name}, channel_id={channel_id} → {e}")
        return False

async def save_or_update_bot_device(bot_name, device_id, ip_address, client_type, client_version, status_hint=None):
    db_pool = g.db_pool
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                """
                INSERT INTO kb_bot_devices
                    (bot_name, device_id, ip_address, status, client_type, client_version, client_info, descryption, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    ip_address=VALUES(ip_address),
                    client_type=VALUES(client_type),
                    client_version=VALUES(client_version),
                    client_info=VALUES(client_info),
                    descryption=VALUES(descryption),
                    updated_at=VALUES(updated_at)
                """,
                (bot_name, device_id, ip_address, status_hint or 'pending', client_type, client_version, 
                 json.dumps({"type": client_type, "version": client_version}, ensure_ascii=False), 
                 "", now, now)
            )
            logger.info(f"[BOT_DEVICE_UPSERT] 등록/갱신: bot={bot_name}, device_id={device_id}, ip={ip_address}, status={status_hint or 'pending'}")
        await conn.commit()

async def save_device_history(device_id, device_uuid, mac_address, ip_address, changed_at):
    """
    디바이스 정보 변경 이력 저장 (kb_bot_device_history)
    """
    db_pool = g.db_pool
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor() as cur:
            await cur.execute(
                "INSERT INTO kb_bot_device_history (device_id, device_uuid, mac_address, ip_address, changed_at) VALUES (%s, %s, %s, %s, %s)",
                (device_id, device_uuid, mac_address, ip_address, changed_at)
            )
            logger.info(f"[BOT_DEVICE_HISTORY] 이력 저장: device_id={device_id}, uuid={device_uuid}, mac={mac_address}, ip={ip_address}, changed_at={changed_at}")
        await conn.commit()

async def get_bot_device_status(bot_name):
    """
    봇의 디바이스 승인 상태를 확인 (새로운 디바이스 인증 시스템 연동)
    """
    db_pool = g.db_pool
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            # 해당 봇의 모든 디바이스를 확인하여 하나라도 approved면 승인
            await cur.execute(
                "SELECT status FROM kb_bot_devices WHERE bot_name = %s",
                (bot_name,)
            )
            rows = await cur.fetchall()
            
            if not rows:
                return None  # 등록된 디바이스 없음
            
            # 하나라도 approved 상태면 승인된 것으로 처리
            for row in rows:
                if row['status'] == 'approved':
                    return 'approved'
            
            # approved가 없으면 최신 디바이스의 상태 반환
            await cur.execute(
                "SELECT status FROM kb_bot_devices WHERE bot_name = %s ORDER BY id DESC LIMIT 1",
                (bot_name,)
            )
            latest_row = await cur.fetchone()
            return latest_row['status'] if latest_row else None

async def block_bot_device(bot_name):
    db_pool = g.db_pool
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                "UPDATE kb_bot_devices SET status='blocked', updated_at=NOW() WHERE bot_name=%s",
                (bot_name,)
            )
            logger.warning(f"[BOT_DEVICE_BLOCKED] 인증 실패로 자동 블락: bot={bot_name}")
        await conn.commit()



