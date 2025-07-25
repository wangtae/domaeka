"""
메시지 처리 모듈 - v3.3.0 프로토콜 지원
"""
import json
import asyncio
import time
from datetime import datetime, timezone
import pytz
from typing import Dict, Any, Optional
from core.logger import logger
from core.response_utils import send_message_response, send_json_response
from core.auth_utils import validate_client_auth
from core.client_status import client_status_manager
from services.echo_service import handle_echo_command
from services.client_info_service import handle_client_info_command
from services.image_multi_service import handle_imgext_command
from database.db_utils import save_chat_to_db
from database.ping_monitor import save_ping_result
from core.ping_scheduler import ping_manager
import core.globals as g


def convert_utc_to_kst(utc_timestamp_str: str, timezone_str: str = 'Asia/Seoul') -> str:
    """
    UTC 타임스탬프를 한국시간으로 변환
    
    Args:
        utc_timestamp_str: UTC 타임스탬프 문자열 (ISO 8601 형식)
        timezone_str: 타임존 문자열
        
    Returns:
        str: 한국시간 타임스탬프 문자열 (YYYY-MM-DD HH:MM:SS 형식)
    """
    try:
        # UTC 타임스탬프 파싱
        if utc_timestamp_str.endswith('Z'):
            utc_dt = datetime.fromisoformat(utc_timestamp_str[:-1]).replace(tzinfo=timezone.utc)
        else:
            utc_dt = datetime.fromisoformat(utc_timestamp_str).replace(tzinfo=timezone.utc)
        
        # 지정된 타임존으로 변환 (기본: 한국시간)
        target_tz = pytz.timezone(timezone_str)
        local_dt = utc_dt.astimezone(target_tz)
        
        # YYYY-MM-DD HH:MM:SS 형식으로 반환
        return local_dt.strftime('%Y-%m-%d %H:%M:%S')
        
    except Exception as e:
        logger.warning(f"[TIMESTAMP] UTC 변환 실패: {e}, 원본: {utc_timestamp_str}")
        # 변환 실패 시 현재 시간 반환
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')


async def process_message(received_message: dict):
    """
    메시지 처리 및 응답 - v3.3.0 프로토콜 지원
    
    Args:
        received_message: 클라이언트로부터 받은 메시지 딕셔너리
    """
    try:
        # 이벤트별 처리 분기
        event = received_message.get('event', '')
        data = received_message.get('data', {})
        
        logger.info(f"[MSG] 이벤트: {event}")
        logger.debug(f"[MSG] 전체 메시지: {received_message}")  # 디버깅용
        
        # 빈 이벤트인 경우 메시지 내용 로깅
        if not event:
            logger.debug(f"[MSG] 빈 이벤트 메시지 내용: {received_message}")
        
        if event == 'analyze':
            # analyze 이벤트 처리
            context = {
                'room': data.get('room'),
                'sender': data.get('sender'),
                'text': data.get('text'),
                'is_group_chat': data.get('isGroupChat'),
                'channel_id': str(data.get('channelId', '')),
                'log_id': data.get('logId'),
                'user_hash': data.get('userHash'),
                'is_mention': data.get('isMention'),
                'timestamp': data.get('timestamp'),
                'bot_name': data.get('botName'),
                'auth': data.get('auth'),
                'client_key': received_message.get('client_key'),
                'client_addr': str(received_message.get('client_addr', '')),
            }
            
            # v3.3.0: UTC 타임스탬프 처리
            if context['timestamp']:
                timezone_info = data.get('timezone', 'Asia/Seoul')
                try:
                    # UTC 타임스탬프인지 확인 (ISO 8601 형식)
                    if 'T' in context['timestamp'] and ('Z' in context['timestamp'] or '+' in context['timestamp']):
                        # UTC → 로컬 시간 변환
                        context['timestamp'] = convert_utc_to_kst(context['timestamp'], timezone_info)
                        logger.debug(f"[MSG] UTC 타임스탬프 변환: {data.get('timestamp')} → {context['timestamp']}")
                except Exception as e:
                    logger.warning(f"[MSG] 타임스탬프 변환 실패: {e}")
            
            await handle_analyze_event(context)
            
            # analyze 메시지 처리 후 ping 카운터 체크 (비활성화 - 30초 주기 방식으로 변경)
            # await ping_manager.check_and_send_ping()
            
        elif event == 'ping':
            # ping 이벤트 처리 (서버 -> 클라이언트 방향의 초기 ping)
            ping_message = received_message.copy()
            ping_message['client_addr'] = str(received_message.get('client_addr', ''))
            await handle_ping_event(ping_message)
        elif event == 'pong':
            # pong 이벤트 처리 (클라이언트 -> 서버 방향의 응답)
            pong_message = received_message.copy()
            pong_message['client_addr'] = str(received_message.get('client_addr', ''))
            await handle_pong_event(pong_message)
        else:
            logger.warning(f"[MSG] 알 수 없는 이벤트: {event}")
            
    except Exception as e:
        logger.error(f"[MSG] 메시지 처리 오류: {e}")


async def handle_analyze_event(context: Dict[str, Any]):
    """
    analyze 이벤트 처리 (메시지 분석) - v3.3.0 지원
    
    Args:
        context: 메시지 컨텍스트 (kkobot 호환 구조)
    """
    text = context.get('text', '')
    room = context.get('room', '')
    sender = context.get('sender', '')
    bot_name = context.get('bot_name', '')
    channel_id = context.get('channel_id', '')
    user_hash = context.get('user_hash', '')
    client_key = context.get('client_key')  # (bot_name, device_id) 튜플
    client_addr = context.get('client_addr')
    
    if client_addr:
        client_status_manager.update_chat_context(client_addr, context)

    # 인증 정보 검증
    auth_data = context.get('auth', {})
    device_id = None
    is_device_approved = True  # 기본값
    
    if auth_data:
        is_valid, error_msg, client_info = validate_client_auth(auth_data)
        if not is_valid:
            logger.warning(f"[ANALYZE] 인증 실패: {error_msg}")
        else:
            # 클라이언트 정보 업데이트
            if client_addr:
                client_status_manager.update_auth_info(client_addr, auth_data)
            
            # 디바이스 승인 상태 확인 (캐시에서)
            device_id = auth_data.get('deviceID')
            if device_id and bot_name:
                # 캐시에서 승인 상태 확인
                client_key = (bot_name, device_id)
                is_device_approved = g.client_approval_status.get(client_key, True)
                
                if not is_device_approved:
                    logger.info(f"[ANALYZE] 승인되지 않은 디바이스: {bot_name}@{device_id} - 제한 모드")
    
    # 방 승인 상태 확인 (device_id 포함)
    from database.db_utils import check_room_approval
    is_room_approved = await check_room_approval(room, channel_id, bot_name, device_id)
    
    # 전체 승인 상태 확인 (디바이스 승인 AND 방 승인)
    is_fully_approved = is_device_approved and is_room_approved
    
    logger.info(f"[ANALYZE] 방:{room} 발신자:{sender} 메시지:{text} "
               f"(디바이스 승인: {'approved' if is_device_approved else 'pending'}, "
               f"방 승인: {'approved' if is_room_approved else 'pending'}, "
               f"전체 승인: {'approved' if is_fully_approved else 'pending'})")
    
    # 데이터베이스에 채팅 로그 저장 (승인 상태와 관계없이 항상 저장)
    if g.db_pool:
        await save_chat_to_db(context)
    
    # 승인되지 않은 디바이스 또는 방은 로깅만 하고 응답하지 않음
    if not is_fully_approved:
        if not is_device_approved:
            logger.info(f"[ANALYZE] 승인 대기 중인 디바이스 - 응답 없이 로깅만 수행: {bot_name}@{device_id}")
        if not is_room_approved:
            logger.info(f"[ANALYZE] 승인 대기 중인 방 - 응답 없이 로깅만 수행: {room}")
        return
    
    # 승인된 디바이스 및 방에서만 명령어 처리
    from services.command_dispatcher import parse_command, process_command
    
    command_prefix, prompt = parse_command(text)
    if command_prefix:
        result = await process_command(context, command_prefix, prompt)
        if result:
            # result가 문자열이면 그대로 전송, 리스트면 그대로 전송
            await send_message_response(context, result)


async def handle_ping_event(received_message: Dict[str, Any]):
    """
    ping 이벤트 처리 (클라이언트가 서버로 보내는 ping - 일반적으로 발생하지 않음)
    
    Args:
        received_message: 클라이언트로부터 받은 ping 메시지
    """
    data = received_message.get('data', {})
    client_key = received_message.get('client_key')
    client_addr = received_message.get('client_addr')
    
    # 클라이언트 발신 ping은 무시 (서버만 ping을 발신하도록 제한)
    logger.warning(f"[PING] 클라이언트 발신 ping 감지 - 무시함: {client_addr}")
    logger.warning(f"[PING] 클라이언트 데이터: bot_name={data.get('bot_name')}, has_server_timestamp={bool(data.get('server_timestamp'))}")


async def handle_pong_event(received_message: Dict[str, Any]):
    """
    pong 이벤트 처리 (서버가 보낸 ping에 대한 클라이언트의 응답)
    
    Args:
        received_message: 클라이언트로부터 받은 pong 메시지
    """
    data = received_message.get('data', {})
    client_key = received_message.get('client_key')
    client_addr = received_message.get('client_addr')
    
    logger.info(f"[PONG] 퐁 수신 - 클라이언트: {client_addr}")
    
    # pong 데이터 상세 로깅
    bot_name = data.get('bot_name', '')
    monitoring = data.get('monitoring', {})
    logger.info(f"[PONG] 데이터: bot={bot_name}, monitoring={bool(monitoring)}")
    if monitoring:
        logger.info(f"[PONG] 모니터링: memory={monitoring.get('memory_usage', 0):.1f}MB/"
                   f"{monitoring.get('total_memory', 0):.1f}MB ({monitoring.get('memory_percent', 0):.1f}%), "
                   f"queue={monitoring.get('message_queue_size', 0)}, rooms={monitoring.get('active_rooms', 0)}")
    
    # 클라이언트 상태 정보 처리
    client_status = data.get("client_status", {})
    monitoring_info = data.get("monitoring", {})
    auth_data = data.get("auth", {})
    
    # 클라이언트 정보 업데이트
    if client_addr:
        # 핑 시간 업데이트
        client_status_manager.update_ping_time(client_addr)
        
        # 상태 정보 업데이트
        if client_status:
            client_status_manager.update_client_status(client_addr, client_status)
        
        # 모니터링 정보 업데이트
        if monitoring_info:
            client_status_manager.update_monitoring_info(client_addr, monitoring_info)
            logger.info(f"[PONG] 모니터링 정보 업데이트: uptime={monitoring_info.get('uptime')}, "
                       f"messages={monitoring_info.get('messageCount')}")
        
        # 인증 정보 검증 및 업데이트
        if auth_data:
            is_valid, error_msg, client_info = validate_client_auth(auth_data)
            if not is_valid:
                logger.warning(f"[PONG] 인증 실패: {error_msg}")
            else:
                client_status_manager.update_auth_info(client_addr, auth_data)
    
    # pong 응답은 추가 응답하지 않고 데이터베이스에만 저장
    logger.info(f"[PONG] pong 응답 수신 - 저장만 수행: {client_addr}")
    # ping 모니터링 정보를 데이터베이스에 저장
    if g.db_pool:
        # 서버 프로세스 모니터 정보 추가
        if hasattr(g, 'process_monitor') and g.process_monitor:
            process_stats = g.process_monitor.get_current_stats()
            data.update(process_stats)
            logger.info(f"[PONG] 프로세스 모니터 정보 추가: {process_stats}")
            # heartbeat 업데이트
            await g.process_monitor.update_heartbeat()
        else:
            logger.warning(f"[PONG] 프로세스 모니터 없음 - hasattr: {hasattr(g, 'process_monitor')}, value: {getattr(g, 'process_monitor', None)}")
        
        # ping_monitor 모듈의 save_ping_result 사용
        await save_ping_result(data)
        logger.info(f"[PONG] 모니터링 정보 DB 저장 완료 - {auth_data.get('botName', '')}")


