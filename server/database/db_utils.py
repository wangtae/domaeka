"""
데이터베이스 유틸리티 모듈
"""
from typing import Dict, Any
from datetime import datetime
from core.logger import logger
import core.globals as g


async def save_chat_to_db(context: Dict[str, Any]):
    """
    채팅 로그를 데이터베이스에 저장
    
    Args:
        context: 메시지 컨텍스트
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 채팅 로그 저장 건너뜀")
        return
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # kb_chat_logs 테이블에 삽입 (실제 테이블 스키마 기준)
                sql = """
                INSERT INTO kb_chat_logs 
                (channel_id, user_hash, room_name, sender, message, directive, message_type, 
                 is_meaningful, bot_name, is_mention, is_group_chat, log_id, client_timestamp, 
                 is_bot, is_our_bot_response, is_scheduled)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                # 메시지 타입 결정
                text = context.get('text', '')
                message_type = 'command' if text.startswith('#') else 'text'
                directive = text.split()[0] if text.startswith('#') else None
                
                values = (
                    context.get('channel_id', ''),
                    context.get('user_hash', ''),
                    context.get('room', ''),
                    context.get('sender', ''),
                    text,  # 'message' 필드는 'text'를 사용
                    directive,
                    message_type,
                    1 if text.startswith('#') else 0,  # 명령어면 meaningful
                    context.get('bot_name', ''),
                    1 if context.get('is_mention') else 0,
                    1 if context.get('is_group_chat') else 0,
                    context.get('log_id', ''),
                    datetime.now(),  # client_timestamp
                    0,  # is_bot (일반 사용자 메시지)
                    0,  # is_our_bot_response
                    0   # is_scheduled
                )
                
                await cursor.execute(sql, values)
                logger.debug(f"[DB] 채팅 로그 저장: {context.get('room')} / {context.get('sender')}")
                
    except Exception as e:
        logger.error(f"[DB] 채팅 로그 저장 실패: {e}")


async def save_ping_to_db(ping_data: Dict[str, Any]):
    """
    ping 모니터링 정보를 kb_ping_monitor 테이블에 저장
    
    Args:
        ping_data: ping 이벤트 데이터
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 ping 저장 건너뜀")
        return
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                import json
                data = ping_data.get('data', {})
                auth_data = data.get('auth', {})
                monitoring_info = data.get('monitoring', {})
                client_addr = ping_data.get('client_addr', '')

                # RTT 계산
                client_timestamp_ms = data.get('server_timestamp', 0)
                server_timestamp_ms = int(datetime.now().timestamp() * 1000)
                rtt_ms = max(0, server_timestamp_ms - client_timestamp_ms) if client_timestamp_ms > 0 else None

                # kb_ping_monitor 테이블에 삽입
                sql = """
                INSERT INTO kb_ping_monitor 
                (bot_name, device_id, client_ip, total_memory, memory_usage, memory_percent, 
                 message_queue_size, active_rooms, client_status, server_status, is_manual, 
                 server_timestamp, client_timestamp, rtt_ms)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                values = (
                    data.get('bot_name', ''),
                    auth_data.get('deviceID', ''),
                    client_addr.split(':')[0] if client_addr else '',
                    monitoring_info.get('total_memory'),
                    monitoring_info.get('memory_usage'),
                    monitoring_info.get('memory_percent'),
                    monitoring_info.get('message_queue_size'),
                    monitoring_info.get('active_rooms'),
                    json.dumps(data.get('client_status', {}), ensure_ascii=False),
                    json.dumps(data.get('server_info', {}), ensure_ascii=False),
                    1 if data.get('is_manual') else 0,
                    datetime.now(),
                    datetime.fromtimestamp(client_timestamp_ms / 1000) if client_timestamp_ms > 0 else None,
                    rtt_ms
                )
                
                await cursor.execute(sql, values)
                logger.debug(f"[DB] ping 모니터링 저장: {data.get('bot_name')} (RTT: {rtt_ms}ms)")
                
    except Exception as e:
        logger.error(f"[DB] ping 모니터링 저장 실패: {e}")
        logger.error(f"[DB] ping_data: {ping_data}")


async def check_room_approval(room_name: str, channel_id: str, bot_name: str, device_id: str = None) -> bool:
    """
    방 승인 상태를 확인하고, 새로운 방이면 pending 상태로 등록
    
    Args:
        room_name: 채팅방 이름
        channel_id: 채널 ID
        bot_name: 봇 이름
        device_id: 디바이스 ID (선택사항)
        
    Returns:
        bool: 승인된 방이면 True, 아니면 False
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 방 승인 확인 건너뜀")
        return True  # DB 연결이 없으면 기본적으로 허용
    
    # device_id가 없으면 기본적으로 승인 상태로 처리
    if not device_id:
        logger.warning(f"[DB] device_id가 없어 방 승인 확인 건너뜀: {room_name}")
        return True
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # room_id는 이제 channel_id만 사용
                room_id = channel_id
                
                # 기존 방 조회 - bot_name과 device_id로 특정 봇의 방 조회
                sql = "SELECT status FROM kb_rooms WHERE room_id = %s AND bot_name = %s AND device_id = %s"
                await cursor.execute(sql, (room_id, bot_name, device_id))
                result = await cursor.fetchone()
                
                if result:
                    # 기존 방이 있으면 승인 상태 확인
                    status = result[0]
                    is_approved = status == 'approved'
                    logger.debug(f"[DB] 방 승인 상태 확인: {room_name} -> {status} (승인: {is_approved})")
                    return is_approved
                else:
                    # 새로운 방이면 pending 상태로 등록
                    await register_room_if_new(room_id, room_name, channel_id, bot_name, device_id)
                    logger.info(f"[DB] 새로운 방 등록: {room_name} -> pending 상태")
                    return False  # 새로 등록된 방은 승인 대기 상태
                    
    except Exception as e:
        logger.error(f"[DB] 방 승인 확인 실패: {e}")
        return False  # 오류 발생 시 안전하게 False 반환


async def register_room_if_new(room_id: str, room_name: str, channel_id: str, bot_name: str, device_id: str = None):
    """
    새로운 방을 kb_rooms 테이블에 pending 상태로 등록
    
    Args:
        room_id: 방 ID (channel_id와 동일)
        room_name: 채팅방 이름
        channel_id: 채널 ID
        bot_name: 봇 이름
        device_id: 디바이스 ID (필수)
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 방 등록 건너뜀")
        return
    
    if not device_id:
        logger.error(f"[DB] device_id가 없어 방 등록 불가: {room_name}")
        raise ValueError("device_id is required for room registration")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # 방 정보 삽입 (device_id 포함)
                sql = """
                INSERT INTO kb_rooms 
                (room_id, bot_name, device_id, room_name, room_concurrency, status, description, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                values = (
                    room_id,
                    bot_name,
                    device_id,  # device_id 필수
                    room_name,
                    2,  # room_concurrency 기본값
                    'pending',
                    f"자동 등록된 방 - 봇: {bot_name}, 디바이스: {device_id[:8]}...",  # description
                    datetime.now(),
                    datetime.now()
                )
                
                await cursor.execute(sql, values)
                await conn.commit()
                logger.info(f"[DB] 새로운 방 등록 성공: {room_name} (room_id: {room_id}, bot: {bot_name}, device: {device_id[:8]}...)")
                
    except Exception as e:
        logger.error(f"[DB] 방 등록 실패: {e}")
        raise


async def create_tables():
    """
    필요한 테이블 생성 (초기 설정용)
    """
    if not g.db_pool:
        logger.warning("[DB] DB 풀이 없어 테이블 생성 건너뜀")
        return
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # kb_chat_logs 테이블 생성
                create_chat_logs_sql = """
                CREATE TABLE IF NOT EXISTS kb_chat_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id VARCHAR(50) DEFAULT '',
                    user_hash VARCHAR(100) DEFAULT '',
                    room_name VARCHAR(255) DEFAULT '',
                    sender VARCHAR(100) DEFAULT '',
                    message TEXT,
                    directive VARCHAR(30) DEFAULT NULL,
                    message_type VARCHAR(20) NOT NULL DEFAULT 'text',
                    is_meaningful TINYINT(1) NOT NULL DEFAULT 0,
                    bot_name VARCHAR(30) DEFAULT '',
                    is_mention TINYINT(1) NOT NULL DEFAULT 0,
                    is_group_chat TINYINT(1) NOT NULL DEFAULT 0,
                    log_id VARCHAR(50) DEFAULT '',
                    client_timestamp DATETIME DEFAULT NULL,
                    server_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_bot TINYINT(1) DEFAULT 0,
                    is_our_bot_response TINYINT(1) NOT NULL DEFAULT 0,
                    is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_channel_id (channel_id),
                    INDEX idx_user_hash (user_hash),
                    INDEX idx_room_name (room_name),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                """
                
                await cursor.execute(create_chat_logs_sql)
                logger.info("[DB] kb_chat_logs 테이블 생성/확인 완료")
                
                # kb_rooms 테이블 생성
                create_rooms_sql = """
                CREATE TABLE IF NOT EXISTS kb_rooms (
                    room_id VARCHAR(50) PRIMARY KEY,
                    bot_name VARCHAR(30) NOT NULL,
                    room_name VARCHAR(255) NOT NULL,
                    room_concurrency INT DEFAULT 2,
                    room_owners LONGTEXT DEFAULT NULL,
                    log_settings LONGTEXT DEFAULT NULL,
                    status ENUM('pending', 'approved', 'denied', 'revoked', 'blocked') DEFAULT 'pending',
                    description TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_bot_name (bot_name),
                    INDEX idx_room_name (room_name),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                """
                
                await cursor.execute(create_rooms_sql)
                logger.info("[DB] kb_rooms 테이블 생성/확인 완료")
                
    except Exception as e:
        logger.error(f"[DB] 테이블 생성 실패: {e}")


async def get_server_process_config(process_name: str) -> Dict[str, Any]:
    """
    서버 프로세스 설정 조회
    
    Args:
        process_name: 프로세스 이름
        
    Returns:
        Dict: 서버 프로세스 설정 정보
        
    Raises:
        ValueError: 프로세스가 존재하지 않는 경우
    """
    if not g.db_pool:
        raise ValueError("데이터베이스 연결이 없습니다")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                SELECT process_id, server_id, process_name, process_type, 
                       port, type, status, created_at, updated_at
                FROM kb_server_processes 
                WHERE process_name = %s
                """
                
                await cursor.execute(sql, (process_name,))
                result = await cursor.fetchone()
                
                if not result:
                    raise ValueError(f"서버 프로세스 '{process_name}'을 찾을 수 없습니다")
                
                # 결과를 딕셔너리로 변환
                config = {
                    'process_id': result[0],
                    'server_id': result[1],
                    'process_name': result[2],
                    'process_type': result[3],
                    'port': result[4],
                    'type': result[5],
                    'status': result[6],
                    'created_at': result[7],
                    'updated_at': result[8]
                }
                
                logger.info(f"[DB] 서버 프로세스 설정 조회: {process_name} (포트: {config['port']}, 타입: {config['type']})")
                return config
                
    except Exception as e:
        logger.error(f"[DB] 서버 프로세스 설정 조회 실패: {e}")
        raise


async def update_server_process_status(process_name: str, status: str, pid: int = None):
    """
    서버 프로세스 상태 업데이트
    
    Args:
        process_name: 프로세스 이름
        status: 상태 (starting, running, stopping, stopped, error, crashed)
        pid: 프로세스 ID (선택사항)
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 프로세스 상태 업데이트 건너뜀")
        return
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                if pid is not None:
                    sql = """
                    UPDATE kb_server_processes 
                    SET status = %s, pid = %s, last_heartbeat = %s, updated_at = %s
                    WHERE process_name = %s
                    """
                    values = (status, pid, datetime.now(), datetime.now(), process_name)
                else:
                    sql = """
                    UPDATE kb_server_processes 
                    SET status = %s, last_heartbeat = %s, updated_at = %s
                    WHERE process_name = %s
                    """
                    values = (status, datetime.now(), datetime.now(), process_name)
                
                await cursor.execute(sql, values)
                await conn.commit()
                
                logger.info(f"[DB] 서버 프로세스 상태 업데이트: {process_name} -> {status}")
                
    except Exception as e:
        logger.error(f"[DB] 서버 프로세스 상태 업데이트 실패: {e}")
        raise


async def list_server_processes():
    """
    서버 프로세스 목록 조회
    
    Returns:
        List[Dict]: 서버 프로세스 목록
    """
    if not g.db_pool:
        raise ValueError("데이터베이스 연결이 없습니다")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                SELECT process_id, server_id, process_name, process_type, 
                       port, type, status, pid, last_heartbeat, 
                       cpu_usage, memory_usage, created_at, updated_at
                FROM kb_server_processes 
                ORDER BY process_name
                """
                
                await cursor.execute(sql)
                results = await cursor.fetchall()
                
                # 결과를 딕셔너리 리스트로 변환
                processes = []
                for row in results:
                    process = {
                        'process_id': row[0],
                        'server_id': row[1],
                        'process_name': row[2],
                        'process_type': row[3],
                        'port': row[4],
                        'type': row[5],
                        'status': row[6],
                        'pid': row[7],
                        'last_heartbeat': row[8],
                        'cpu_usage': row[9],
                        'memory_usage': row[10],
                        'created_at': row[11],
                        'updated_at': row[12]
                    }
                    processes.append(process)
                
                return processes
                
    except Exception as e:
        logger.error(f"[DB] 서버 프로세스 목록 조회 실패: {e}")
        raise