"""
Ping 모니터링 모듈 - kb_ping_monitor 테이블 연동
"""
from typing import Dict, Any
from datetime import datetime
from core.logger import logger
import core.globals as g


async def save_ping_result(ping_data: Dict[str, Any]) -> bool:
    """
    ping 결과를 kb_ping_monitor 테이블에 저장
    
    Args:
        ping_data: ping 응답 데이터
            - bot_name: 봇 이름
            - device_id: 디바이스 ID (또는 auth.deviceID)
            - auth.ipAddress: 클라이언트 IP
            - auth.timestamp: 클라이언트 타임스탬프
            - server_timestamp: 서버 타임스탬프
            - monitoring.total_memory: 전체 메모리 (MB)
            - monitoring.memory_usage: 메모리 사용량 (MB)
            - monitoring.memory_percent: 메모리 사용률 (%)
            - monitoring.message_queue_size: 메시지 큐 크기
            - monitoring.active_rooms: 활성 채팅방 수
            - process_name: 서버 프로세스명 (추가)
            - server_cpu_usage: 서버 CPU 사용률 (추가)
            - server_memory_usage: 서버 메모리 사용량 (추가)
            
    Returns:
        bool: 저장 성공 여부
    """
    if not g.db_pool:
        logger.debug("[PING_MONITOR] DB 풀이 없어 ping 결과 저장 건너뜀")
        return False
    
    # bot_name 추출
    bot_name = ping_data.get('bot_name')
    
    # device_id 추출 (auth 객체에서도 확인)
    device_id = ping_data.get('device_id')
    if not device_id and 'auth' in ping_data:
        device_id = ping_data['auth'].get('deviceID')
    
    if not bot_name or not device_id:
        logger.warning(f"[PING_MONITOR] bot_name 또는 device_id 누락 - bot_name: {bot_name}, device_id: {device_id}")
        return False
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # ping 결과 저장
                insert_sql = """
                INSERT INTO kb_ping_monitor 
                (bot_name, device_id, client_ip, ping_time_ms, client_timestamp, server_timestamp,
                 total_memory, memory_usage, memory_percent, message_queue_size, active_rooms,
                 process_name, server_cpu_usage, server_memory_usage, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                # 기본값 설정
                server_timestamp = ping_data.get('server_timestamp')
                
                # auth에서 client_timestamp 추출
                auth = ping_data.get('auth', {})
                client_timestamp = auth.get('timestamp', ping_data.get('client_timestamp'))
                
                # ping 응답 시간 계산 (현재 시간 - 서버가 ping 보낸 시간)
                import time
                current_time_ms = int(time.time() * 1000)
                if server_timestamp:
                    # server_timestamp가 초 단위인 경우 밀리초로 변환
                    if server_timestamp < 1000000000:
                        server_timestamp = server_timestamp * 1000
                    ping_time_ms = current_time_ms - server_timestamp
                else:
                    ping_time_ms = ping_data.get('ping_time_ms', 0)
                
                # monitoring 데이터 추출
                monitoring = ping_data.get('monitoring', {})
                total_memory = int(monitoring.get('total_memory', 0))
                memory_usage = int(monitoring.get('memory_usage', 0))
                memory_percent = float(monitoring.get('memory_percent', 0))
                message_queue_size = int(monitoring.get('message_queue_size', 0))
                active_rooms = int(monitoring.get('active_rooms', 0))
                
                # auth에서 client_ip 추출
                client_ip = auth.get('ipAddress', '')
                
                # timestamp 형식 변환 (밀리초 -> datetime)
                from datetime import datetime
                if client_timestamp:
                    client_datetime = datetime.fromtimestamp(client_timestamp / 1000)
                else:
                    client_datetime = None
                    
                if server_timestamp:
                    server_datetime = datetime.fromtimestamp(server_timestamp / 1000)
                else:
                    server_datetime = None
                
                # 서버 프로세스 정보 추출
                process_name = ping_data.get('process_name', g.process_name if hasattr(g, 'process_name') else None)
                server_cpu_usage = ping_data.get('server_cpu_usage', 0.0)
                server_memory_usage = ping_data.get('server_memory_usage', 0.0)
                
                await cursor.execute(insert_sql, (
                    bot_name, device_id, client_ip, ping_time_ms, client_datetime, server_datetime,
                    total_memory, memory_usage, memory_percent, message_queue_size, active_rooms,
                    process_name, server_cpu_usage, server_memory_usage,
                    datetime.now()
                ))
                
                # ping 로그는 LOG_CONFIG에 따라 출력 제어
                ping_config = g.LOG_CONFIG.get('ping', {})
                if ping_config.get('enabled', True) and ping_config.get('detailed', False):
                    logger.debug(f"[PING_MONITOR] ping 결과 저장 완료: {bot_name}@{device_id} - {ping_time_ms}ms")
                return True
                
    except Exception as e:
        logger.error(f"[PING_MONITOR] ping 결과 저장 실패: {e}")
        return False


async def get_recent_pings(bot_name: str, device_id: str = None, limit: int = 100):
    """
    최근 ping 기록 조회
    
    Args:
        bot_name: 봇 이름
        device_id: 디바이스 ID (선택)
        limit: 조회할 레코드 수
        
    Returns:
        List[Dict]: ping 기록 목록
    """
    if not g.db_pool:
        return []
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                if device_id:
                    sql = """
                    SELECT * FROM kb_ping_monitor 
                    WHERE bot_name = %s AND device_id = %s
                    ORDER BY created_at DESC
                    LIMIT %s
                    """
                    await cursor.execute(sql, (bot_name, device_id, limit))
                else:
                    sql = """
                    SELECT * FROM kb_ping_monitor 
                    WHERE bot_name = %s
                    ORDER BY created_at DESC
                    LIMIT %s
                    """
                    await cursor.execute(sql, (bot_name, limit))
                
                results = await cursor.fetchall()
                
                # 결과를 딕셔너리 리스트로 변환
                pings = []
                columns = ['id', 'bot_name', 'device_id', 'client_ip', 'total_memory', 
                          'memory_usage', 'memory_percent', 'message_queue_size', 
                          'active_rooms', 'client_status', 'server_status', 'is_manual',
                          'client_timestamp', 'server_timestamp', 'ping_time_ms', 'created_at']
                
                for row in results:
                    ping_dict = {}
                    for i, col in enumerate(columns):
                        if i < len(row):
                            ping_dict[col] = row[i]
                    pings.append(ping_dict)
                
                return pings
                
    except Exception as e:
        logger.error(f"[PING_MONITOR] ping 기록 조회 실패: {e}")
        return []


async def cleanup_old_pings(days_to_keep: int = 7) -> int:
    """
    오래된 ping 기록 정리
    
    Args:
        days_to_keep: 보관할 일수
        
    Returns:
        int: 삭제된 레코드 수
    """
    if not g.db_pool:
        return 0
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                DELETE FROM kb_ping_monitor 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %s DAY)
                """
                await cursor.execute(sql, (days_to_keep,))
                deleted_count = cursor.rowcount
                
                if deleted_count > 0:
                    logger.info(f"[PING_MONITOR] {deleted_count}개의 오래된 ping 기록 삭제")
                
                return deleted_count
                
    except Exception as e:
        logger.error(f"[PING_MONITOR] 오래된 기록 정리 실패: {e}")
        return 0