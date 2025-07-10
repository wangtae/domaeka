"""
메시지 처리 모듈
"""
import json
import asyncio
from typing import Dict, Any, Optional
from core.logger import logger
from core.response_utils import send_message_response, send_json_response
from services.echo_service import handle_echo_command
from database.db_utils import save_chat_to_db
import core.globals as g


async def process_message(raw_message: str, writer: asyncio.StreamWriter):
    """
    메시지 처리 및 응답
    
    Args:
        raw_message: 클라이언트로부터 받은 원시 메시지
        writer: 응답을 보낼 스트림 라이터
    """
    try:
        # JSON 파싱
        data = json.loads(raw_message)
        event = data.get('event', '')
        message_data = data.get('data', {})
        
        logger.info(f"[MSG] 이벤트: {event}")
        
        if event == 'analyze':
            # 메시지 분석 및 명령어 처리
            await handle_analyze_event(message_data, writer)
        elif event == 'ping':
            # 핑 응답
            await handle_ping_event(message_data, writer)
        else:
            logger.warning(f"[MSG] 알 수 없는 이벤트: {event}")
            
    except json.JSONDecodeError as e:
        logger.error(f"[MSG] JSON 파싱 실패: {e}")
    except Exception as e:
        logger.error(f"[MSG] 메시지 처리 오류: {e}")


async def handle_analyze_event(data: Dict[str, Any], writer: asyncio.StreamWriter):
    """
    analyze 이벤트 처리 (메시지 분석)
    
    Args:
        data: 메시지 데이터
        writer: 응답 스트림 라이터
    """
    text = data.get('text', '')
    room = data.get('room', '')
    sender = data.get('sender', '')
    bot_name = data.get('botName', '')
    channel_id = data.get('channelId', '')
    user_hash = data.get('userHash', '')
    
    logger.info(f"[ANALYZE] 방:{room} 발신자:{sender} 메시지:{text}")
    
    # Context 객체 생성
    context = {
        "bot_name": bot_name,
        "channel_id": str(channel_id) if channel_id else "",
        "room": room,
        "user_hash": user_hash,
        "sender": sender,
        "is_group_chat": data.get('isGroupChat', False),
        "is_mention": data.get('isMention', False),
        "message": text,
        "timestamp": data.get('timestamp', ''),
        "log_id": data.get('logId', ''),
        "writer": writer
    }
    
    # 데이터베이스에 채팅 로그 저장
    if g.db_pool:
        await save_chat_to_db(context)
    
    # echo 명령어 체크
    if text.startswith('# echo '):
        await handle_echo_command(context, text)
    elif text.strip() == '# echo':
        await send_message_response(writer, room, "사용법: # echo {내용}", channel_id)


async def handle_ping_event(data: Dict[str, Any], writer: asyncio.StreamWriter):
    """
    ping 이벤트 처리
    
    Args:
        data: 핑 데이터
        writer: 응답 스트림 라이터
    """
    logger.info("[PING] 핑 수신")
    
    # 핑 응답
    response = {
        "event": "ping",
        "data": {
            "bot_name": data.get("bot_name", ""),
            "channel_id": data.get("channel_id", ""),
            "room": data.get("room", ""),
            "user_hash": data.get("user_hash", ""),
            "server_timestamp": data.get("server_timestamp", ""),
            "client_status": {
                "cpu": None,
                "ram": None,
                "temp": None
            },
            "is_manual": False
        }
    }
    
    await send_json_response(writer, response)


