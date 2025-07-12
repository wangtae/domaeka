"""
클라이언트 연결 처리 모듈
"""
import asyncio
import json
from typing import Dict, Any
from core.logger import logger
from core.message_processor import process_message
from core.client_status import client_status_manager
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
    
    # 핸드셰이크 처리
    handshake_completed = False
    
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
                
                # 핸드셰이크 처리 (첫 번째 메시지)
                if not handshake_completed:
                    handshake_completed = await handle_handshake(message, client_addr)
                    if not handshake_completed:
                        continue
                
                # 일반 메시지 처리
                await process_message(message, writer, client_addr)
                
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
        client_status_manager.remove_client(str(client_addr))
        writer.close()
        await writer.wait_closed()
        logger.info(f"[CLIENT] 클라이언트 연결 해제: {client_addr}")


async def handle_handshake(message: str, client_addr) -> bool:
    """
    클라이언트 핸드셰이크 처리
    
    Args:
        message: 핸드셰이크 메시지
        client_addr: 클라이언트 주소
        
    Returns:
        bool: 핸드셰이크 성공 여부
    """
    try:
        # 핸드셰이크 메시지는 JSON 형태여야 함
        handshake_data = json.loads(message)
        
        # 필수 필드 확인
        bot_name = handshake_data.get('botName', '')
        version = handshake_data.get('version', '')
        device_id = handshake_data.get('deviceId', '')  # 새로 추가된 필드
        
        if not bot_name:
            logger.error(f"[HANDSHAKE] botName 누락: {client_addr}")
            return False
        
        # 클라이언트 상태 관리자에 등록
        client_info = client_status_manager.register_client(str(client_addr), handshake_data)
        
        logger.info(f"[HANDSHAKE] 성공: {client_addr} - {bot_name} v{version}")
        if device_id:
            logger.info(f"[HANDSHAKE] Device ID: {device_id}")
        
        return True
        
    except json.JSONDecodeError:
        logger.error(f"[HANDSHAKE] JSON 파싱 실패: {client_addr}")
        return False
    except Exception as e:
        logger.error(f"[HANDSHAKE] 처리 오류: {client_addr} -> {e}")
        return False