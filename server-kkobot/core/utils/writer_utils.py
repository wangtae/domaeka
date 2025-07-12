"""
Writer 검색 유틸리티 모듈
g.clients에서 유효한 writer를 찾는 공통 함수들을 제공합니다.
"""

from core.logger import logger
import core.globals as g


def get_valid_writer(bot_name: str):
    """
    지정된 봇의 유효한 writer를 반환합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        writer: 유효한 writer 객체 또는 None
    """
    if not hasattr(g, 'clients') or bot_name not in g.clients:
        return None
    
    client_sessions = g.clients[bot_name]
    
    # 유효한 writer 찾기
    for session_addr, potential_writer in list(client_sessions.items()):
        if potential_writer and not potential_writer.is_closing():
            logger.debug(f"[WRITER_UTILS] 유효한 writer 찾음 → {bot_name} / {session_addr}")
            return potential_writer
        else:
            logger.warning(f"[WRITER_UTILS] 유효하지 않은 writer 제거 → {bot_name} / {session_addr}")
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
    
    for bot_name, client_sessions in g.clients.items():
        writer = get_valid_writer(bot_name)
        if writer:
            return bot_name, writer
    
    return None, None


def cleanup_invalid_writers():
    """
    모든 봇의 유효하지 않은 writer들을 정리합니다.
    """
    if not hasattr(g, 'clients'):
        return
    
    cleaned_count = 0
    
    for bot_name, client_sessions in g.clients.items():
        for session_addr, writer in list(client_sessions.items()):
            if not writer or writer.is_closing():
                del client_sessions[session_addr]
                cleaned_count += 1
                logger.debug(f"[WRITER_UTILS] 정리된 writer → {bot_name} / {session_addr}")
    
    if cleaned_count > 0:
        logger.info(f"[WRITER_UTILS] 총 {cleaned_count}개의 유효하지 않은 writer 정리 완료") 