"""
응답 전송 유틸리티 모듈 - v3.3.0 프로토콜 지원
"""
import asyncio
import json
import time
import base64
from datetime import datetime, timezone
from typing import Dict, Any, Optional, List, Union
from core.logger import logger
import core.globals as g


def sanitize_surrogates(text: str) -> str:
    """
    이모티콘을 포함한 모든 유니코드 문자를 안전하게 처리
    """
    # 대부분의 경우 원본 그대로 반환 (Python3는 기본적으로 UTF-8 지원)
    return text


def create_v330_packet(event: str, data: dict, raw_content: str = None) -> str:
    """
    v3.3.0 프로토콜 패킷 생성 (JSON + Raw 데이터)
    
    Args:
        event: 이벤트 타입
        data: 패킷 데이터
        raw_content: Raw 데이터 (없으면 기존 JSON 방식)
        
    Returns:
        str: 완성된 패킷 문자열
    """
    # 기본 패킷 구조
    packet = {
        'event': event,
        'data': data.copy()
    }
    
    # UTC 타임스탬프 추가
    packet['data']['timestamp'] = datetime.now(timezone.utc).isoformat()
    packet['data']['timezone'] = 'Asia/Seoul'
    
    if raw_content is not None:
        # v3.3.0: JSON + Raw 데이터 구조
        message_type = data.get('message_type', 'text')
        
        if message_type == 'text':
            # 텍스트는 Base64 인코딩
            encoded_content = base64.b64encode(raw_content.encode('utf-8')).decode('ascii')
            packet['data']['content_encoding'] = 'base64'
            packet['data']['message_positions'] = [0, len(encoded_content)]
            
            json_str = json.dumps(packet, ensure_ascii=False)
            return json_str + encoded_content + '\n'
            
        elif message_type in ['image', 'audio', 'video', 'document']:
            # 미디어 데이터 처리
            if isinstance(raw_content, list):
                # 멀티 미디어: message_positions 계산
                positions = [0]
                current_pos = 0
                content_parts = []
                
                for content in raw_content:
                    if isinstance(content, str):
                        content_bytes = content.encode('utf-8')
                    else:
                        content_bytes = content
                    content_parts.append(content_bytes)
                    current_pos += len(content_bytes)
                    positions.append(current_pos)
                
                packet['data']['message_positions'] = positions
                combined_content = b''.join(content_parts).decode('utf-8')
                
                json_str = json.dumps(packet, ensure_ascii=False)
                return json_str + combined_content + '\n'
            else:
                # 단일 미디어
                if isinstance(raw_content, str):
                    content_size = len(raw_content.encode('utf-8'))
                else:
                    content_size = len(raw_content)
                    raw_content = raw_content.decode('utf-8') if isinstance(raw_content, bytes) else str(raw_content)
                
                packet['data']['message_positions'] = [0, content_size]
                
                json_str = json.dumps(packet, ensure_ascii=False)
                return json_str + raw_content + '\n'
    
    # Raw 데이터가 없으면 기존 JSON 방식 (ping, handshake 등)
    return json.dumps(packet, ensure_ascii=False) + '\n'


async def send_message(writer, packet):
    """
    writer를 통해 패킷 전송. kkobot과 동일한 구조.
    """
    if isinstance(packet.get('data', {}).get('text'), list):
        text_list = packet['data']['text']
        for i, text_item in enumerate(text_list):
            is_last_message = (i == len(text_list) - 1)
            single_packet = packet.copy()
            single_packet['data'] = packet['data'].copy()
            single_packet['data']['text'] = text_item
            await send_message(writer, single_packet)
            if not is_last_message:
                await asyncio.sleep(1)
        return

    # 단일 메시지(문자열)는 그대로 전송
    if writer is None:
        logger.error("[SEND_MESSAGE] Writer가 None입니다")
        return

    if writer.is_closing():
        logger.warning(f"[SEND_MESSAGE] writer가 이미 닫혀있어 전송할 수 없음 → writer: {writer}")
        return

    try:
        # 서버→클라이언트 전송은 길이 제한 없음 (미디어 파일 전송을 위해)
        # 응답 메시지 크기 체크 제거
        
        message = json.dumps(packet, ensure_ascii=False) + '\n'
        # sanitize_surrogates 제거 - JSON 직렬화 후 추가 처리 불필요
        encoded_message = message.encode('utf-8')
        
        # 서버→클라이언트는 크기 제한 없음
        logger.debug(f"[SEND_MESSAGE] 메시지 크기: {len(encoded_message)} 바이트")

        channel_id = packet.get('data', {}).get('channel_id')
        room = packet.get('data', {}).get('room')
        target = channel_id or room or 'unknown'

        writer.write(encoded_message)
        await writer.drain()

        logger.debug(f"[SEND_MESSAGE] 전송 완료 → 타겟: {target}, 메시지: {message.strip()[:100]}...")

    except asyncio.TimeoutError:
        logger.error(f"[SEND_MESSAGE] writer.drain() 타임아웃 발생 → 타겟: {target}, writer: {writer}")
    except Exception as e:
        logger.error(f"[SEND_MESSAGE] 메시지 전송 실패 → 타겟: {target}, writer: {writer}, 오류: {e}")


async def send_v330_message(writer: asyncio.StreamWriter, event: str, data: dict, raw_content: str = None):
    """
    v3.3.0 프로토콜로 메시지 전송
    
    Args:
        writer: 스트림 라이터
        event: 이벤트 타입
        data: 메시지 데이터
        raw_content: Raw 데이터 (텍스트 또는 미디어)
    """
    try:
        if writer.is_closing():
            logger.warning("[V330_MESSAGE] Writer가 이미 닫혀있음")
            return
            
        # v3.3.0 패킷 생성
        packet_str = create_v330_packet(event, data, raw_content)
        encoded_message = packet_str.encode('utf-8')
        
        # 서버→클라이언트는 크기 제한 없음
        logger.debug(f"[V330_MESSAGE] 메시지 크기: {len(encoded_message)} 바이트")

        writer.write(encoded_message)
        await writer.drain()
        
        target = data.get('channel_id') or data.get('room') or 'unknown'
        logger.debug(f"[V330_MESSAGE] 전송 완료 → 타겟: {target}, 이벤트: {event}")
        
    except Exception as e:
        logger.error(f"[V330_MESSAGE] 전송 실패: {e}")


async def send_message_response(context: Union[Dict[str, Any], asyncio.StreamWriter], message: Union[str, List[str]], room: str = None, channel_id: Optional[str] = None, media_wait_time: Optional[int] = None):
    """
    메시지 응답 전송 - v3.3.0 프로토콜 지원
    
    Args:
        context: 컨텍스트 딕셔너리 또는 writer (하위 호환성)
        message: 전송할 메시지 또는 메시지 리스트
        room: 채팅방 이름 (하위 호환성)
        channel_id: 채널 ID (하위 호환성)
        media_wait_time: 미디어 전송 대기시간 (밀리초) - v3.2.1 클라이언트 지원
    """
    # 하위 호환성: 이전 방식 지원
    if isinstance(context, asyncio.StreamWriter):
        writer = context
        room_name = room
        channel_id_val = channel_id
        bot_name = ""
    else:
        # client_key로 writer 가져오기
        client_key = context.get('client_key')
        writer = None
        if client_key and client_key in g.clients:
            writer = g.clients[client_key]
        
        room_name = context.get('room', '')
        channel_id_val = context.get('channel_id', '')
        bot_name = context.get('bot_name', '')

    if not writer or not room_name:
        logger.warning(f"[MESSAGE_RESPONSE] 필수 정보 누락 → writer: {bool(writer)}, room: {room_name}")
        return False

    # '@no-reply' 메시지는 클라이언트로 전송하지 않음
    if message == '@no-reply':
        logger.debug(f"[MESSAGE_RESPONSE] no-reply 응답 → room: {room_name}")
        return True

    # v3.3.0 프로토콜 감지 (클라이언트 버전 확인)
    use_v330 = True  # 기본적으로 v3.3.0 사용
    
    # 리스트 형태의 메시지 처리
    if isinstance(message, list):
        success = True
        for i, msg in enumerate(message):
            # 리스트 내의 '@no-reply' 메시지는 건너뜀
            if msg == '@no-reply':
                continue

            # v3.3.0 프로토콜 사용
            if use_v330:
                data = {
                    'room': room_name,
                    'channel_id': channel_id_val,
                    'bot_name': bot_name,
                    'message_type': 'text'
                }
                
                if media_wait_time and media_wait_time > 0:
                    data['media_wait_time'] = media_wait_time
                
                await send_v330_message(writer, 'messageResponse', data, msg)
            else:
                # 레거시 방식
                packet = {
                    'event': 'messageResponse',
                    'data': {
                        'room': room_name,
                        'channel_id': channel_id_val,
                        'bot_name': bot_name,
                        'text': msg
                    }
                }
                
                if media_wait_time and media_wait_time > 0:
                    packet['data']['media_wait_time'] = media_wait_time
                
                await send_message(writer, packet)

            # 마지막 메시지가 아니면 1초 대기
            if i < len(message) - 1:
                await asyncio.sleep(1)

        if success:
            logger.debug(f"[MESSAGE_RESPONSE] 리스트 메시지 전송 성공 → room: {room_name}, 메시지 개수: {len(message)}")
        return success

    # 단일 메시지 처리
    try:
        # 미디어 메시지 감지
        if isinstance(message, str) and (message.startswith('IMAGE_BASE64:') or message.startswith('MEDIA_URL:')):
            # 미디어 메시지 처리
            if message.startswith('IMAGE_BASE64:'):
                media_data = message[13:]  # "IMAGE_BASE64:" 제거
                message_type = 'image'
                message_format = 'jpg'
            elif message.startswith('MEDIA_URL:'):
                media_data = message[10:]  # "MEDIA_URL:" 제거
                message_type = 'image'  # 기본값
                message_format = 'jpg'
            
            # 멀티 미디어 확인 (|||로 구분)
            if '|||' in media_data:
                media_list = media_data.split('|||')
                
                if use_v330:
                    # v3.3.0: 멀티 미디어
                    data = {
                        'room': room_name,
                        'channel_id': channel_id_val,
                        'bot_name': bot_name,
                        'message_type': message_type,
                        'message_format': message_format
                    }
                    
                    if media_wait_time and media_wait_time > 0:
                        data['media_wait_time'] = media_wait_time
                    
                    await send_v330_message(writer, 'messageResponse', data, media_list)
                    logger.info(f"[MESSAGE_RESPONSE] v3.3.0 멀티 {message_type} 전송 성공 → room: {room_name}, {len(media_list)}개")
                else:
                    # 레거시 방식
                    packet = {
                        'event': 'messageResponse',
                        'data': {
                            'room': room_name,
                            'channel_id': channel_id_val,
                            'bot_name': bot_name,
                            'text': message
                        }
                    }
                    
                    if media_wait_time and media_wait_time > 0:
                        packet['data']['media_wait_time'] = media_wait_time
                    
                    await send_message(writer, packet)
                    logger.info(f"[MESSAGE_RESPONSE] 레거시 멀티 {message_type} 전송 성공 → room: {room_name}, {len(media_list)}개")
            else:
                # 단일 미디어
                if use_v330:
                    # v3.3.0: 단일 미디어
                    data = {
                        'room': room_name,
                        'channel_id': channel_id_val,
                        'bot_name': bot_name,
                        'message_type': message_type,
                        'message_format': message_format
                    }
                    
                    if media_wait_time and media_wait_time > 0:
                        data['media_wait_time'] = media_wait_time
                    
                    await send_v330_message(writer, 'messageResponse', data, media_data)
                    logger.info(f"[MESSAGE_RESPONSE] v3.3.0 단일 {message_type} 전송 성공 → room: {room_name}")
                else:
                    # 레거시 방식
                    packet = {
                        'event': 'messageResponse',
                        'data': {
                            'room': room_name,
                            'channel_id': channel_id_val,
                            'bot_name': bot_name,
                            'text': message
                        }
                    }
                    
                    if media_wait_time and media_wait_time > 0:
                        packet['data']['media_wait_time'] = media_wait_time
                    
                    await send_message(writer, packet)
                    logger.info(f"[MESSAGE_RESPONSE] 레거시 단일 {message_type} 전송 성공 → room: {room_name}")
        else:
            # 텍스트 메시지
            if use_v330:
                # v3.3.0: 텍스트 메시지
                data = {
                    'room': room_name,
                    'channel_id': channel_id_val,
                    'bot_name': bot_name,
                    'message_type': 'text'
                }
                
                if media_wait_time and media_wait_time > 0:
                    data['media_wait_time'] = media_wait_time
                
                await send_v330_message(writer, 'messageResponse', data, message)
                logger.debug(f"[MESSAGE_RESPONSE] v3.3.0 텍스트 메시지 전송 성공 → room: {room_name}")
            else:
                # 레거시 방식
                packet = {
                    'event': 'messageResponse',
                    'data': {
                        'room': room_name,
                        'channel_id': channel_id_val,
                        'bot_name': bot_name,
                        'text': message
                    }
                }
                
                if media_wait_time and media_wait_time > 0:
                    packet['data']['media_wait_time'] = media_wait_time
                
                await send_message(writer, packet)
                logger.debug(f"[MESSAGE_RESPONSE] 레거시 텍스트 메시지 전송 성공 → room: {room_name}")
        
        return True
    except Exception as e:
        logger.error(f"[MESSAGE_RESPONSE] 단일 메시지 전송 실패: {e}")
        return False


async def send_json_response(writer: asyncio.StreamWriter, response: Dict[str, Any]):
    """
    JSON 응답 전송 - v3.3.0 프로토콜 통합 지원 (ping, handshake 등)
    
    Args:
        writer: 스트림 라이터
        response: 응답 딕셔너리
    """
    try:
        if writer.is_closing():
            logger.warning("[JSON_RESPONSE] Writer가 이미 닫혀있음")
            return
        
        # v3.3.0: 모든 응답을 JSON+Raw 구조로 통일
        event = response.get('event', '')
        data = response.get('data', {})
        
        # 공통 필드 자동 추가
        data['timestamp'] = datetime.now(timezone.utc).isoformat()
        data['timezone'] = 'Asia/Seoul'
        data['message_positions'] = [0, 0]  # Raw 데이터 없음 표시
        
        # 업데이트된 응답 생성
        updated_response = {
            'event': event,
            'data': data
        }
        
        message = json.dumps(updated_response, ensure_ascii=False) + '\n'
        encoded_message = message.encode('utf-8')
        
        # 인코딩된 메시지 크기 체크
        if len(encoded_message) > g.MAX_MESSAGE_SIZE:
            logger.error(f"[JSON_RESPONSE] 인코딩된 메시지 크기 초과: {len(encoded_message)} 바이트")
            return
            
        writer.write(encoded_message)
        await writer.drain()
        
        # ping 응답은 LOG_CONFIG에 따라 로그 출력 제어
        if response.get('event') == 'ping':
            ping_config = g.LOG_CONFIG.get('ping', {})
            if ping_config.get('enabled', True) and ping_config.get('detailed', False):
                logger.debug(f"[JSON_RESPONSE] v3.3.0 전송 완료: {message.strip()[:100]}...")
        else:
            logger.debug(f"[JSON_RESPONSE] v3.3.0 전송 완료: {message.strip()[:100]}...")
        
    except Exception as e:
        logger.error(f"[JSON_RESPONSE] 전송 실패: {e}")


async def send_ping_event_to_client(context):
    """
    ping 이벤트를 클라이언트로 전송하는 함수 - kkobot 호환
    """
    from datetime import datetime
    import pytz
    
    room = context.get('room')
    bot_name = context.get('bot_name')
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')
    
    # client_key로 writer 가져오기
    client_key = context.get('client_key')
    writer = None
    if client_key and client_key in g.clients:
        writer = g.clients[client_key]

    if not writer or not room:
        logger.warning(f"[PING_EVENT] 필수 정보 누락 → writer: {bool(writer)}, room: {room}, bot_name: {bot_name}")
        return False

    packet = {
        'event': 'ping',
        'data': {
            'bot_name': bot_name,
            'channel_id': channel_id,
            'room': room,
            'user_hash': user_hash,
            'server_timestamp': datetime.now(pytz.timezone('Asia/Seoul')).strftime("%Y-%m-%d %H:%M:%S.%f")[:-3],
            'text': ''
        }
    }
    try:
        await send_message(writer, packet)
        logger.info(f"[PING_EVENT] ping 이벤트 전송 완료 → {bot_name} / {room}")
        return True
    except Exception as e:
        logger.error(f"[PING_EVENT] 전송 실패 → {e}")
        return False