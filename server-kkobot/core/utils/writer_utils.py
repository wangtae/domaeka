"""
Writer 검색 유틸리티 모듈
g.clients에서 유효한 writer를 찾는 공통 함수들을 제공합니다.
"""

from core.logger import logger
import core.globals as g


def get_valid_writer(bot_name: str, device_id: str = None):
    """
    지정된 봇의 유효한 writer를 반환합니다.
    
    Args:
        bot_name (str): 봇 이름
        device_id (str): 디바이스 ID (선택사항)
        
    Returns:
        writer: 유효한 writer 객체 또는 None
    """
    if not hasattr(g, 'clients'):
        return None
    
    # device_id가 제공되면 특정 클라이언트 키로 검색
    if device_id:
        client_key = (bot_name, device_id)
        if client_key in g.clients:
            client_sessions = g.clients[client_key]
            for session_addr, potential_writer in list(client_sessions.items()):
                if potential_writer and not potential_writer.is_closing():
                    logger.debug(f"[WRITER_UTILS] 유효한 writer 찾음 → {client_key} / {session_addr}")
                    return potential_writer
                else:
                    logger.warning(f"[WRITER_UTILS] 유효하지 않은 writer 제거 → {client_key} / {session_addr}")
                    del client_sessions[session_addr]
    else:
        # device_id가 없으면 bot_name으로 모든 클라이언트 검색
        for client_key, client_sessions in g.clients.items():
            if client_key[0] == bot_name:  # client_key[0]은 bot_name
                for session_addr, potential_writer in list(client_sessions.items()):
                    if potential_writer and not potential_writer.is_closing():
                        logger.debug(f"[WRITER_UTILS] 유효한 writer 찾음 → {client_key} / {session_addr}")
                        return potential_writer
                    else:
                        logger.warning(f"[WRITER_UTILS] 유효하지 않은 writer 제거 → {client_key} / {session_addr}")
                        del client_sessions[session_addr]
    
    return None


def get_any_valid_writer():
    """
    모든 봇 중에서 첫 번째로 찾은 유효한 writer를 반환합니다.
    
    Returns:
        tuple: (bot_name, writer) 또는 (None, None)
    """
    if not hasattr(g, 'clients'):
        return None, None
    
    for client_key, client_sessions in g.clients.items():
        bot_name, device_id = client_key
        for session_addr, writer in list(client_sessions.items()):
            if writer and not writer.is_closing():
                return bot_name, writer
    
    return None, None


def cleanup_invalid_writers():
    """
    모든 봇의 유효하지 않은 writer들을 정리합니다.
    """
    if not hasattr(g, 'clients'):
        return
    
    cleaned_count = 0
    
    for client_key, client_sessions in list(g.clients.items()):
        for session_addr, writer in list(client_sessions.items()):
            if not writer or writer.is_closing():
                del client_sessions[session_addr]
                cleaned_count += 1
                logger.debug(f"[WRITER_UTILS] 정리된 writer → {client_key} / {session_addr}")
        
        # 해당 클라이언트의 모든 연결이 종료되면 키 자체 제거
        if not client_sessions:
            del g.clients[client_key]
            logger.debug(f"[WRITER_UTILS] 빈 클라이언트 키 제거 → {client_key}")
    
    if cleaned_count > 0:
        logger.info(f"[WRITER_UTILS] 총 {cleaned_count}개의 유효하지 않은 writer 정리 완료") 