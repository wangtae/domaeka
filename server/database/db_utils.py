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
                create_table_sql = """
                CREATE TABLE IF NOT EXISTS kb_chat_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id VARCHAR(50) DEFAULT '',
                    user_hash VARCHAR(100) DEFAULT '',
                    room_name VARCHAR(255) DEFAULT '',
                    sender VARCHAR(100) DEFAULT '',
                    message TEXT,
                    bot_name VARCHAR(30) DEFAULT '',
                    log_id VARCHAR(50) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_channel_id (channel_id),
                    INDEX idx_user_hash (user_hash),
                    INDEX idx_room_name (room_name),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                """
                
                await cursor.execute(create_table_sql)
                logger.info("[DB] kb_chat_logs 테이블 생성/확인 완료")
                
    except Exception as e:
        logger.error(f"[DB] 테이블 생성 실패: {e}")