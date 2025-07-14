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
                    handshake_completed = await handle_handshake(message, client_addr, writer)
                    continue  # 핸드셰이크 메시지는 여기서 처리 종료
                
                # 일반 메시지 처리 (핸드셰이크 완료 후에만)
                json_message = json.loads(message)
                json_message['writer'] = writer
                json_message['client_addr'] = client_addr
                await process_message(json_message) 
                
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


async def handle_handshake(message: str, client_addr, writer) -> bool:
    """
    클라이언트 핸드셰이크 처리 및 kb_bot_devices 테이블 연동
    
    Args:
        message: 핸드셰이크 메시지
        client_addr: 클라이언트 주소
        writer: 스트림 라이터
        
    Returns:
        bool: 핸드셰이크 성공 여부
    """
    try:
        # 핸드셰이크 메시지는 JSON 형태여야 함
        handshake_data = json.loads(message)
        
        # 필수 필드 확인 (구 버전 호환성 지원)
        bot_name = handshake_data.get('botName', '')
        version = handshake_data.get('version', '')
        device_id = handshake_data.get('deviceID', '')
        
        # v3.2.0 이상의 확장 필드 (선택 사항)
        client_type = handshake_data.get('clientType', 'MessengerBotR')  # 기본값 설정
        device_ip = handshake_data.get('deviceIP', str(client_addr).split(':')[0] if ':' in str(client_addr) else 'unknown')
        device_info = handshake_data.get('deviceInfo', '')
        
        # 핵심 필드 검증 (구 버전 호환)
        required_fields = ['botName', 'version', 'deviceID']
        for field in required_fields:
            if not handshake_data.get(field):
                logger.error(f"[HANDSHAKE] {field} 필드 누락: {client_addr}")
                return False
        
        logger.info(f"[HANDSHAKE] 수신: {client_addr} - {client_type} {bot_name} v{version}")
        logger.info(f"[HANDSHAKE] Device: {device_id}, IP: {device_ip}, Info: {device_info}")
        
        # kb_bot_devices 테이블과 연동하여 승인 상태 확인
        from database.device_manager import validate_and_register_device
        is_approved, status_message = await validate_and_register_device(handshake_data, str(client_addr))
        
        if not is_approved:
            logger.warning(f"[HANDSHAKE] 승인되지 않은 디바이스: {client_addr} - {status_message}")
            # 승인되지 않았어도 연결은 허용하되, 제한 모드로 동작
        
        # 클라이언트 상태 관리자에 등록 (승인 상태 포함)
        handshake_data['approval_status'] = 'approved' if is_approved else 'pending'
        handshake_data['status_message'] = status_message
        client_info = client_status_manager.register_client(str(client_addr), handshake_data)
        
        logger.info(f"[HANDSHAKE] 성공: {client_addr} - {bot_name} v{version} (상태: {handshake_data['approval_status']})")
        
        # 핸드셰이크 성공 응답 전송
        from core.response_utils import send_json_response
        handshake_response = {
            'event': 'handshakeComplete',
            'data': {
                'success': True,
                'approved': is_approved,
                'message': status_message,
                'server_version': '1.0.0'
            }
        }
        await send_json_response(writer, handshake_response)
        logger.info(f"[HANDSHAKE] 응답 전송 완료: {client_addr}")
        
        return True
        
    except json.JSONDecodeError:
        logger.error(f"[HANDSHAKE] JSON 파싱 실패: {client_addr}")
        return False
    except Exception as e:
        logger.error(f"[HANDSHAKE] 처리 오류: {client_addr} -> {e}")
        return False