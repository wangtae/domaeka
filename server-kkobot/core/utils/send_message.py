"""
메시지 전송 유틸리티 모듈

이 모듈은 LOA.i 서버에서 클라이언트로 메시지를 전송하는 다양한 함수들을 제공합니다.
모든 함수는 context의 bot_name을 통해 자동으로 writer를 검색하므로,
context에 writer를 직접 포함시킬 필요가 없습니다.

주요 함수:
- send_message_response(): 표준 메시지 응답 전송 (권장)
- send_direct_message(): 직접 메시지 전송 (scheduledMessage 이벤트)
- send_ping_event_to_client(): ping 이벤트 전송

모든 함수는 writer 자동 검색, 유효성 검증, 오류 처리를 포함합니다.
"""

import json
import asyncio
from core.logger import logger
from core.performance import Timer
from core import globals as g
from core.utils.writer_utils import get_valid_writer


def sanitize_surrogates(text: str) -> str:
    """
    UTF-16 surrogate 영역에 해당하는 비정상 문자 제거
    """
    return text.encode("utf-16", "surrogatepass").decode("utf-16", "replace")


async def send_message(writer, packet):
    """
    writer를 통해 패킷 전송. writer가 None이면 글로벌 소켓 사용.
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
        try:
            message = json.dumps(packet, ensure_ascii=False) + '\n'
            message = sanitize_surrogates(message)
            encoded_message = message.encode('utf-8')

            channel_id = packet.get('data', {}).get('channelId')
            room = packet.get('data', {}).get('room')
            target = channel_id or room or 'unknown'

            if not hasattr(g, 'socket') or g.socket is None:
                logger.error(f"[SEND_MESSAGE][GLOBAL_SOCKET] 글로벌 소켓 연결 없음. 메시지 전송 불가 → 타겟: {target}")
                return

            g.socket.write(encoded_message)
            await g.socket.drain()
            logger.debug(f"[SEND_MESSAGE][GLOBAL_SOCKET] 글로벌 소켓 전송 완료 → {message.strip()[:100]}...") # 메시지 내용 일부 로깅

        except Exception as e:
            logger.error(f"[SEND_MESSAGE][GLOBAL_SOCKET] 글로벌 소켓 전송 실패 → 타겟: {target}, 오류: {e}", exc_info=True)
        return

    if writer.is_closing():
        logger.warning(f"[SEND_MESSAGE][WRITER_CLOSING] writer가 이미 닫혀있어 전송할 수 없음 → writer: {writer}")
        return

    try:
        message = json.dumps(packet, ensure_ascii=False) + '\n'
        message = sanitize_surrogates(message)
        encoded_message = message.encode('utf-8')

        channel_id = packet.get('data', {}).get('channelId')
        room = packet.get('data', {}).get('room')
        target = channel_id or room or 'unknown'

        with Timer(f"send_message to {target}"):
            writer.write(encoded_message)
            await writer.drain()

        logger.debug(f"[SEND_MESSAGE][WRITER] 전송 완료 → 타겟: {target}, 메시지: {message.strip()[:100]}...") # 메시지 내용 일부 로깅

    except asyncio.TimeoutError:
        logger.error(f"[SEND_MESSAGE][WRITER_TIMEOUT] writer.drain() 타임아웃 발생 → 타겟: {target}, writer: {writer}", exc_info=True)
    except Exception as e:
        logger.error(f"[SEND_MESSAGE][WRITER_FAIL] 메시지 전송 실패 → 타겟: {target}, writer: {writer}, 오류: {e}", exc_info=True)


async def send_message_from_context(context, text):
    # Deprecated: send_message_response를 사용하세요.
    await send_message_response(context, text)


async def send_direct_message(context: dict, message: str) -> bool:
    """
    직접 메시지 전송 함수 (scheduledMessage 이벤트 사용)
    
    Args:
        context (dict): 메시지 전송에 필요한 컨텍스트 정보
            - bot_name (str): 봇 이름 (writer 자동 검색용)
            - room (str): 채팅방 이름
            - channel_id (str): 채널 ID
            - 기타 선택적 필드들
        message (str): 전송할 메시지
        
    Returns:
        bool: 전송 성공 여부
        
    Note:
        - writer는 bot_name을 통해 자동으로 검색됩니다
        - context에 writer를 직접 포함시킬 필요가 없습니다
    """
    room = context.get('room')
    bot_name = context.get('bot_name')
    channel_id = context.get('channel_id')

    # bot_name으로 writer 자동 검색
    writer = get_valid_writer(bot_name) if bot_name else None

    if not writer or not room:
        logger.warning(f"[DIRECT_MESSAGE] 필수 정보 누락 → writer: {bool(writer)}, room: {room}, bot_name: {bot_name}")
        return False

    packet = {
        'event': 'scheduledMessage',
        'data': {
            'bot_name': bot_name,
            'bot_version': context.get('bot_version'),
            'channel_id': channel_id,
            'user_hash': context.get('user_hash', '(system)'),
            'room': room,
            'sender': context.get('sender', '(system)'),
            'is_group_chat': context.get('is_group_chat', False),
            'is_mention': context.get('is_mention', False),
            'server_status': None,
            'text': message
        }
    }

    try:
        await send_message(writer, packet)
        if isinstance(message, list):
            logger.debug(f"[DIRECT_MESSAGE] 리스트 메시지 전송 성공 → {bot_name} / {room} / 메시지 개수: {len(message)}")
        else:
            preview = message[:50] + "..." if isinstance(message, str) else f"(non-string: {type(message).__name__}) {message}"
            logger.debug(f"[DIRECT_MESSAGE] 전송 성공 → {bot_name} / {room} / {preview}")
        return True
    except Exception as e:
        logger.error(f"[DIRECT_MESSAGE] 전송 실패 → {e}", exc_info=True)
        return False


async def _send_message(writer, packet):
    """
    순수하게 메시지 전송만 담당하는 내부 함수
    레거시 코드 없이 단순히 패킷을 전송하는 역할만 수행
    """
    message = json.dumps(packet, ensure_ascii=False) + '\n'
    message = sanitize_surrogates(message)
    encoded_message = message.encode('utf-8')

    channel_id = packet.get('data', {}).get('channel_id')
    room = packet.get('data', {}).get('room')
    target = channel_id or room or 'unknown'

    # writer가 None인 경우 글로벌 소켓 사용
    if writer is None:
        try:
            if not hasattr(g, 'socket') or g.socket is None:
                logger.error(f"[SEND_MESSAGE_INTERNAL][GLOBAL_SOCKET] 글로벌 소켓 연결 없음. 메시지 전송 불가 → 타겟: {target}")
                return False

            g.socket.write(encoded_message)
            await g.socket.drain()
            logger.debug(f"[SEND_MESSAGE_INTERNAL][GLOBAL_SOCKET] 글로벌 소켓 전송 성공 → 타겟: {target}, 메시지: {message.strip()[:100]}...")
            return True

        except Exception as e:
            logger.error(f"[SEND_MESSAGE_INTERNAL][GLOBAL_SOCKET_FAIL] 글로벌 소켓 전송 실패 → 타겟: {target}, 오류: {e}", exc_info=True)
            return False

    # writer가 닫혀있는 경우
    if writer.is_closing():
        logger.warning(f"[SEND_MESSAGE_INTERNAL][WRITER_CLOSING] writer가 이미 닫혀있어 전송할 수 없음 → 타겟: {target}, writer: {writer}")
        return False

    try:
        writer.write(encoded_message)
        await writer.drain()
        logger.debug(f"[SEND_MESSAGE_INTERNAL][WRITER] 메시지 전송 성공 → 타겟: {target}, 메시지: {message.strip()[:100]}...")
        return True

    except asyncio.TimeoutError:
        logger.error(f"[SEND_MESSAGE_INTERNAL][WRITER_TIMEOUT] writer.drain() 타임아웃 발생 → 타겟: {target}, writer: {writer}", exc_info=True)
        return False
    except Exception as e:
        logger.error(f"[SEND_MESSAGE_INTERNAL][WRITER_FAIL] 메시지 전송 실패 → 타겟: {target}, writer: {writer}, 오류: {e}", exc_info=True)
        return False


def verify_writer_belongs_to_bot(bot_name: str, writer) -> bool:
    """
    주어진 writer가 실제로 해당 bot_name에 속하는지 검증
    
    Args:
        bot_name (str): 봇 이름
        writer: 검증할 writer 객체
        
    Returns:
        bool: writer가 해당 봇에 속하면 True, 아니면 False
    """
    if not hasattr(g, 'clients') or bot_name not in g.clients:
        return False
    
    client_sessions = g.clients[bot_name]
    
    # 해당 봇의 모든 writer 중에서 일치하는지 확인
    for session_addr, session_writer in client_sessions.items():
        if session_writer is writer:
            logger.debug(f"[WRITER_VERIFY] writer 검증 성공 → {bot_name} / {session_addr}")
            return True
    
    logger.warning(f"[WRITER_VERIFY] writer 불일치 → {bot_name}에 속하지 않는 writer 발견")
    return False


async def send_message_response(context: dict, message: str | list) -> bool:
    """
    새로운 messageResponse 방식의 메시지 전송 함수

    Args:
        context (dict): 메시지 전송에 필요한 컨텍스트 정보
        message (str | list): 전송할 메시지 내용
            - str: '@no-reply' 또는 단일 메시지
            - list: 여러 메시지를 순차적으로 전송

    Returns:
        bool: 전송 성공 여부
    """
    room = context.get('room')
    channel_id = context.get('channel_id')
    bot_name = context.get('bot_name')
    bot_version = context.get('bot_version')
    user_hash = context.get('user_hash')
    sender = context.get('sender')
    is_group_chat = context.get('is_group_chat', False)
    is_mention = context.get('is_mention', False)

    # writer 검증 및 선택
    context_writer = context.get('writer')
    writer = None
    
    if context_writer and not context_writer.is_closing():
        # context에 writer가 있으면 bot_name과 일치하는지 검증
        if bot_name and verify_writer_belongs_to_bot(bot_name, context_writer):
            writer = context_writer
            logger.debug(f"[MESSAGE_RESPONSE] context writer 사용 → {bot_name}")
        else:
            logger.error(f"[MESSAGE_RESPONSE] context writer 검증 실패 → bot_name: {bot_name}, writer 불일치")
            # 검증 실패 시 bot_name으로 다시 찾기
            writer = get_valid_writer(bot_name) if bot_name else None
    else:
        # context에 writer가 없거나 닫혀있으면 bot_name으로 찾기
        writer = get_valid_writer(bot_name) if bot_name else None

    if not writer or not room:
        logger.warning(f"[MESSAGE_RESPONSE] 필수 정보 누락 → writer: {bool(writer)}, room: {room}, bot_name: {bot_name}")
        return False

    # '@no-reply' 메시지는 클라이언트로 전송하지 않음
    if message == '@no-reply':
        logger.debug(f"[MESSAGE_RESPONSE] no-reply 응답 → room: {room}")
        return True

    base_packet = {
        'event': 'messageResponse',
        'data': {
            'bot_name': bot_name,
            'bot_version': bot_version,
            'channel_id': channel_id,
            'user_hash': user_hash,
            'room': room,
            'sender': sender,
            'is_group_chat': is_group_chat,
            'is_mention': is_mention,
            'server_status': None
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

            if not await _send_message(writer, packet):
                success = False
                break

            # 마지막 메시지가 아니면 1초 대기
            if i < len(message) - 1:
                await asyncio.sleep(1)

        if success:
            logger.debug(f"[MESSAGE_RESPONSE] 리스트 메시지 전송 성공 → room: {room}, 메시지 개수: {len(message)}")
        return success

    # 단일 메시지 처리
    packet = base_packet.copy()
    packet['data']['text'] = message

    success = await _send_message(writer, packet)
    if success:
        logger.debug(f"[MESSAGE_RESPONSE] 단일 메시지 전송 성공 → room: {room}, 메시지: {message[:50]}...")
    return success


async def send_ping_event_to_client(context):
    """
    ping 이벤트를 클라이언트로 전송하는 함수
    
    Args:
        context (dict): ping 이벤트 전송에 필요한 컨텍스트 정보
            - bot_name (str): 봇 이름 (writer 자동 검색용)
            - channel_id (str): 채널 ID
            - room (str): 채팅방 이름
            - user_hash (str): 사용자 해시
            
    Returns:
        bool: 전송 성공 여부
        
    Note:
        - writer는 bot_name을 통해 자동으로 검색됩니다
        - context에 writer를 직접 포함시킬 필요가 없습니다
    """
    from datetime import datetime
    import pytz
    
    room = context.get('room')
    bot_name = context.get('bot_name')
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')

    # bot_name으로 writer 자동 검색
    writer = get_valid_writer(bot_name) if bot_name else None

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
        await _send_message(writer, packet)
        logger.info(f"[PING_EVENT] ping 이벤트 전송 완료 → {bot_name} / {room}")
        return True
    except Exception as e:
        logger.error(f"[PING_EVENT] 전송 실패 → {e}")
        return False
