"""
ping 모니터링 결과를 DB에 저장하는 유틸리티
"""
import json
from core.logger import logger
import core.globals as g
from datetime import datetime
import pytz

async def save_ping_result(data: dict):
    """
    클라이언트에서 수신한 ping 결과를 kb_ping_monitor 테이블에 저장
    Args:
        data (dict): ping 결과 데이터
    """
    try:
        pool = g.db_pool
        if pool is None:
            logger.error("[PING_MONITOR] DB 풀이 초기화되지 않았습니다.")
            return False

        # client_timestamp: 서버가 최초로 보낸 시각 (클라이언트가 echo한 값)
        client_timestamp_str = data.get('server_timestamp')
        client_timestamp = None
        if client_timestamp_str:
            try:
                client_timestamp = datetime.strptime(client_timestamp_str, "%Y-%m-%d %H:%M:%S.%f")
                client_timestamp = pytz.timezone('Asia/Seoul').localize(client_timestamp)
            except Exception as e:
                logger.warning(f"[PING_MONITOR] client_timestamp 파싱 실패: {e}")
                client_timestamp = None

        # server_timestamp: 서버가 응답을 받은 시각(=지금)
        server_now = datetime.now(pytz.timezone('Asia/Seoul'))

        # rtt_ms 계산
        rtt_ms = None
        if client_timestamp:
            rtt_ms = int((server_now - client_timestamp).total_seconds() * 1000)

        async with pool.acquire() as conn:
            async with conn.cursor() as cur:
                sql = """
                INSERT INTO kb_ping_monitor (
                    bot_name, channel_id, room_name, user_hash,
                    client_timestamp, server_timestamp, rtt_ms,
                    client_status, server_status, is_manual
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                await cur.execute(sql, (
                    data.get('bot_name'),
                    data.get('channel_id'),
                    data.get('room'),
                    data.get('user_hash'),
                    client_timestamp.strftime("%Y-%m-%d %H:%M:%S.%f")[:-3] if client_timestamp else None,
                    server_now.strftime("%Y-%m-%d %H:%M:%S.%f")[:-3],
                    rtt_ms,
                    json.dumps(data.get('client_status')) if data.get('client_status') else None,
                    None,
                    data.get('is_manual', 0)
                ))
                await conn.commit()
        logger.info(f"[PING_MONITOR] ping 결과 저장 완료 → {data.get('bot_name')} / {data.get('room')}, rtt_ms={rtt_ms}")
        return True
    except Exception as e:
        logger.error(f"[PING_MONITOR] ping 결과 저장 실패: {e}")
        return False 