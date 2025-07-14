"""
클라이언트 연결 처리 모듈
"""
import asyncio
import json
from typing import Dict, Any
from core.logger import logger
from core.message_processor import process_message
from core.client_status import client_status_manager
from core.ping_scheduler_v2 import ping_scheduler
import core.globals as g


async def ping_client_periodically(writer: asyncio.StreamWriter, client_addr, bot_name: str):
    """
    클라이언트에게 주기적으로 ping 전송
    
    Args:
        writer: 스트림 라이터
        client_addr: 클라이언트 주소
        bot_name: 봇 이름
    """
    import random
    # 클라이언트별로 분산시키기 위해 0~5초 랜덤 대기
    initial_delay = random.uniform(0, 5)
    await asyncio.sleep(initial_delay)
    
    while not g.shutdown_event.is_set():
        try:
            if writer.is_closing():
                logger.debug(f"[PING] Writer가 닫혀있음, ping 중단: {client_addr}")
                break
                
            # ping 메시지 전송
            from core.response_utils import send_json_response
            ping_message = {
                'event': 'ping',
                'data': {
                    'bot_name': bot_name,
                    'server_timestamp': int(asyncio.get_event_loop().time() * 1000)
                }
            }
            await send_json_response(writer, ping_message)
            logger.debug(f"[PING] 전송 완료: {client_addr}")
            
            # 다음 ping까지 대기
            await asyncio.sleep(g.PING_INTERVAL_SECONDS)
            
        except Exception as e:
            logger.error(f"[PING] 전송 실패: {client_addr} - {e}")
            break


async def handle_client(reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
    """
    클라이언트 연결 처리
    
    Args:
        reader: 스트림 리더
        writer: 스트림 라이터
    """
    client_addr = writer.get_extra_info('peername')
    logger.info(f"[CLIENT] 새 클라이언트 연결: {client_addr}")
    
    # 핸드셰이크 처리
    handshake_completed = False
    bot_name = None
    device_id = None
    client_key = None  # (bot_name, device_id) 튜플
    
    try:
        # 핸드셰이크 타임아웃 처리 (10초)
        if not handshake_completed:
            try:
                async with asyncio.timeout(10):
                    data = await reader.readline()
                    if not data:
                        logger.error(f"[HANDSHAKE] 핸드셰이크 데이터 없음: {client_addr}")
                        writer.close()
                        await writer.wait_closed()
                        return
                    
                    # 핸드셰이크 처리
                    message = data.decode('utf-8').strip()
                    logger.debug(f"[RECV] {client_addr}: {message}")
                    
                    handshake_result = await handle_handshake(message, client_addr, writer)
                    if isinstance(handshake_result, tuple) and len(handshake_result) == 3:
                        handshake_completed, bot_name, device_id = handshake_result
                        if handshake_completed:
                            # 클라이언트 등록
                            client_key = (bot_name, device_id)
                            
                            # 기존 연결이 있으면 종료
                            if client_key in g.clients:
                                old_writer = g.clients[client_key]
                                if old_writer and not old_writer.is_closing():
                                    logger.warning(f"[CLIENT] 기존 연결 종료: {client_key}")
                                    old_writer.close()
                                    await old_writer.wait_closed()
                            
                            # 새 연결 등록
                            g.clients[client_key] = writer
                            g.clients_by_addr[client_addr] = client_key
                            logger.info(f"[CLIENT] 클라이언트 등록: {client_key} from {client_addr}")
                            
                            # 새로운 ping 스케줄러에 클라이언트 추가
                            await ping_scheduler.add_client(bot_name, device_id, writer)
                            logger.info(f"[CLIENT] Ping 스케줄러 등록: {client_key}")
                        else:
                            logger.error(f"[HANDSHAKE] 핸드셰이크 실패: {client_addr}")
                            writer.close()
                            await writer.wait_closed()
                            return
                    else:
                        logger.error(f"[HANDSHAKE] 핸드셰이크 실패: {client_addr}")
                        writer.close()
                        await writer.wait_closed()
                        return
                        
            except asyncio.TimeoutError:
                logger.error(f"[HANDSHAKE] 핸드셰이크 타임아웃: {client_addr}")
                writer.close()
                await writer.wait_closed()
                return
        
        # 일반 메시지 처리 루프
        while not g.shutdown_event.is_set():
            # 클라이언트로부터 메시지 수신 (30초 타임아웃)
            try:
                async with asyncio.timeout(30):
                    data = await reader.readline()
            except asyncio.TimeoutError:
                logger.warning(f"[TIMEOUT] 읽기 타임아웃: {client_addr}")
                continue
                
            if not data:
                logger.warning(f"[CLIENT] 클라이언트 연결 종료: {client_addr}")
                break
            
            # JSON 메시지 파싱 및 처리
            try:
                message = data.decode('utf-8').strip()
                logger.debug(f"[RECV] {client_addr}: {message}")
                
                # 일반 메시지 처리 (핸드셰이크 완료 후에만)
                json_message = json.loads(message)
                # 순환 참조 방지: writer 객체 대신 필요한 정보만 전달
                json_message['bot_name'] = bot_name
                json_message['device_id'] = device_id
                json_message['client_key'] = (bot_name, device_id)
                json_message['client_addr'] = client_addr
                
                # ping 이벤트 처리
                if json_message.get('event') == 'ping':
                    from database.ping_monitor import save_ping_result
                    ping_data = json_message.get('data', {})
                    # bot_name과 device_id 정보 추가
                    ping_data['bot_name'] = bot_name
                    ping_data['device_id'] = device_id
                    await save_ping_result(ping_data)
                    continue  # ping 패킷은 일반 메시지 처리로 넘기지 않음
                
                # 메시지를 큐에 추가 (워커가 처리)
                await g.message_queue.put(json_message)
                logger.debug(f"[CLIENT] 메시지를 큐에 추가: {client_addr}") 
                
            except json.JSONDecodeError as e:
                logger.error(f"[CLIENT] JSON 파싱 실패: {e}")
            except Exception as e:
                logger.error(f"[CLIENT] 메시지 처리 실패: {e}")
                
    except asyncio.CancelledError:
        logger.info(f"[CLIENT] 연결 취소됨: {client_addr}")
    except Exception as e:
        logger.error(f"[CLIENT] 연결 오류: {client_addr} -> {e}")
    finally:
        # ping 스케줄러에서 클라이언트 제거
        if bot_name and device_id:
            await ping_scheduler.remove_client(bot_name, device_id)
            logger.debug(f"[CLIENT] Ping 스케줄러에서 제거: ({bot_name}, {device_id})")
        
        # 클라이언트 정리
        if client_addr in g.clients_by_addr:
            client_key = g.clients_by_addr[client_addr]
            if client_key in g.clients:
                del g.clients[client_key]
            del g.clients_by_addr[client_addr]
            logger.info(f"[CLIENT] 클라이언트 제거: {client_key}")
        
        client_status_manager.remove_client(str(client_addr))
        writer.close()
        await writer.wait_closed()
        logger.info(f"[CLIENT] 클라이언트 연결 해제: {client_addr}")


async def handle_handshake(message: str, client_addr, writer):
    """
    클라이언트 핸드셰이크 처리 및 kb_bot_devices 테이블 연동
    
    Args:
        message: 핸드셰이크 메시지
        client_addr: 클라이언트 주소
        writer: 스트림 라이터
        
    Returns:
        tuple: (핸드셰이크 성공 여부, bot_name, device_id) 또는 bool
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
        
        return True, bot_name, device_id
        
    except json.JSONDecodeError:
        logger.error(f"[HANDSHAKE] JSON 파싱 실패: {client_addr}")
        return False
    except Exception as e:
        logger.error(f"[HANDSHAKE] 처리 오류: {client_addr} -> {e}")
        return False