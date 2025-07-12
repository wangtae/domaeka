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


async def check_room_approval(room_name: str, channel_id: str, bot_name: str) -> bool:
    """
    방 승인 상태를 확인하고, 새로운 방이면 pending 상태로 등록
    
    Args:
        room_name: 채팅방 이름
        channel_id: 채널 ID
        bot_name: 봇 이름
        
    Returns:
        bool: 승인된 방이면 True, 아니면 False
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 방 승인 확인 건너뜀")
        return True  # DB 연결이 없으면 기본적으로 허용
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # room_id는 channel_id와 bot_name을 조합하여 생성
                room_id = f"{channel_id}_{bot_name}"
                
                # 기존 방 조회
                sql = "SELECT status FROM kb_rooms WHERE room_id = %s"
                await cursor.execute(sql, (room_id,))
                result = await cursor.fetchone()
                
                if result:
                    # 기존 방이 있으면 승인 상태 확인
                    status = result[0]
                    is_approved = status == 'approved'
                    logger.debug(f"[DB] 방 승인 상태 확인: {room_name} -> {status} (승인: {is_approved})")
                    return is_approved
                else:
                    # 새로운 방이면 pending 상태로 등록
                    await register_room_if_new(room_id, room_name, channel_id, bot_name)
                    logger.info(f"[DB] 새로운 방 등록: {room_name} -> pending 상태")
                    return False  # 새로 등록된 방은 승인 대기 상태
                    
    except Exception as e:
        logger.error(f"[DB] 방 승인 확인 실패: {e}")
        return False  # 오류 발생 시 안전하게 False 반환


async def register_room_if_new(room_id: str, room_name: str, channel_id: str, bot_name: str):
    """
    새로운 방을 kb_rooms 테이블에 pending 상태로 등록
    
    Args:
        room_id: 방 ID (channel_id + bot_name 조합)
        room_name: 채팅방 이름
        channel_id: 채널 ID
        bot_name: 봇 이름
    """
    if not g.db_pool:
        logger.debug("[DB] DB 풀이 없어 방 등록 건너뜀")
        return
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # 방 정보 삽입 (실제 테이블 스키마에 맞게 수정)
                sql = """
                INSERT INTO kb_rooms 
                (room_id, bot_name, room_name, room_concurrency, status, descryption, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                values = (
                    room_id,
                    bot_name,
                    room_name,
                    2,  # room_concurrency 기본값
                    'pending',
                    f"자동 등록된 방 - 채널ID: {channel_id}",  # descryption
                    datetime.now(),
                    datetime.now()
                )
                
                await cursor.execute(sql, values)
                await conn.commit()
                logger.info(f"[DB] 새로운 방 등록 성공: {room_name} (room_id: {room_id})")
                
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