"""
클라이언트 연결 처리 모듈
"""
import asyncio
import json
from typing import Dict, Any
from core.logger import logger
from core.message_processor import process_message
import core.globals as g


async def handle_client(reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
    """
    클라이언트 연결 처리
    
    Args:
        reader: 스트림 리더
        writer: 스트림 라이터
    """
    client_addr = writer.get_extra_info('peername')
    logger.info(f"[CLIENT] 새 클라이언트 연결: {client_addr}")
    
    # 클라이언트 등록
    g.clients[client_addr] = writer
    
    try:
        while not g.shutdown_event.is_set():
            # 클라이언트로부터 메시지 수신
            data = await reader.readline()
            if not data:
                logger.warning(f"[CLIENT] 클라이언트 연결 종료: {client_addr}")
                break
            
            # JSON 메시지 파싱 및 처리
            try:
                message = data.decode('utf-8').strip()
                logger.debug(f"[RECV] {client_addr}: {message}")
                
                # 메시지 처리
                await process_message(message, writer)
                
            except json.JSONDecodeError as e:
                logger.error(f"[CLIENT] JSON 파싱 실패: {e}")
            except Exception as e:
                logger.error(f"[CLIENT] 메시지 처리 실패: {e}")
                
    except asyncio.CancelledError:
        logger.info(f"[CLIENT] 연결 취소됨: {client_addr}")
    except Exception as e:
        logger.error(f"[CLIENT] 연결 오류: {client_addr} -> {e}")
    finally:
        # 클라이언트 정리
        if client_addr in g.clients:
            del g.clients[client_addr]
        writer.close()
        await writer.wait_closed()
        logger.info(f"[CLIENT] 클라이언트 연결 해제: {client_addr}")