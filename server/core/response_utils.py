"""
응답 전송 유틸리티 모듈 - kkobot 호환 버전
"""
import asyncio
import json
import time
from typing import Dict, Any, Optional, List, Union
from core.logger import logger
import core.globals as g


def sanitize_surrogates(text: str) -> str:
    """
    UTF-16 surrogate 영역에 해당하는 비정상 문자 제거
    """
    return text.encode("utf-16", "surrogatepass").decode("utf-16", "replace")


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
        message = json.dumps(packet, ensure_ascii=False) + '\n'
        message = sanitize_surrogates(message)
        encoded_message = message.encode('utf-8')

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


async def send_message_response(context: Union[Dict[str, Any], asyncio.StreamWriter], message: Union[str, List[str]], room: str = None, channel_id: Optional[str] = None):
    """
    메시지 응답 전송 - kkobot 호환 버전
    
    Args:
        context: 컨텍스트 딕셔너리 또는 writer (하위 호환성)
        message: 전송할 메시지 또는 메시지 리스트
        room: 채팅방 이름 (하위 호환성)
        channel_id: 채널 ID (하위 호환성)
    """
    # 하위 호환성: 이전 방식 지원
    if isinstance(context, asyncio.StreamWriter):
        writer = context
        room_name = room
        channel_id_val = channel_id
        bot_name = ""
    else:
        writer = context.get('writer')
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

    base_packet = {
        'event': 'messageResponse',
        'data': {
            'room': room_name,
            'channel_id': channel_id_val,
            'bot_name': bot_name
        }
    }

    # 리스트 형태의 메시지 처리
    if isinstance(message, list):
        success = True
        for i, msg in enumerate(message):
            # 리스트 내의 '@no-reply' 메시지는 건너뜀
            if msg == '@no-reply':
                continue

            packet = base_packet.copy()
            packet['data'] = base_packet['data'].copy()
            packet['data']['text'] = msg

            try:
                await send_message(writer, packet)
            except Exception as e:
                logger.error(f"[MESSAGE_RESPONSE] 리스트 메시지 전송 실패: {e}")
                success = False
                break

            # 마지막 메시지가 아니면 1초 대기
            if i < len(message) - 1:
                await asyncio.sleep(1)

        if success:
            logger.debug(f"[MESSAGE_RESPONSE] 리스트 메시지 전송 성공 → room: {room_name}, 메시지 개수: {len(message)}")
        return success

    # 단일 메시지 처리
    packet = base_packet.copy()
    packet['data']['text'] = message

    try:
        await send_message(writer, packet)
        logger.debug(f"[MESSAGE_RESPONSE] 단일 메시지 전송 성공 → room: {room_name}, 메시지: {message[:50]}...")
        return True
    except Exception as e:
        logger.error(f"[MESSAGE_RESPONSE] 단일 메시지 전송 실패: {e}")
        return False


async def send_json_response(writer: asyncio.StreamWriter, response: Dict[str, Any]):
    """
    JSON 응답 전송
    
    Args:
        writer: 스트림 라이터
        response: 응답 딕셔너리
    """
    try:
        if writer.is_closing():
            logger.warning("[JSON_RESPONSE] Writer가 이미 닫혀있음")
            return
            
        message = json.dumps(response, ensure_ascii=False) + '\n'
        message = sanitize_surrogates(message)
        writer.write(message.encode('utf-8'))
        await writer.drain()
        
        logger.debug(f"[JSON_RESPONSE] 전송 완료: {message.strip()}")
        
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
    writer = context.get('writer')

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