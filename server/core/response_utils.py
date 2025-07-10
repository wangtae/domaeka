"""
응답 유틸리티 모듈 (순환 import 방지)
"""
import json
import asyncio
from typing import Dict, Any
from core.logger import logger


async def send_message_response(writer: asyncio.StreamWriter, room: str, text: str, channel_id: str = ""):
    """
    메시지 응답 전송
    
    Args:
        writer: 스트림 라이터
        room: 방 이름
        text: 응답 텍스트
        channel_id: 채널 ID
    """
    response = {
        "event": "messageResponse",
        "data": {
            "room": room,
            "text": text,
            "channel_id": channel_id
        }
    }
    
    await send_json_response(writer, response)
    logger.info(f"[SEND] 방:{room} 응답:{text}")


async def send_json_response(writer: asyncio.StreamWriter, response: Dict[str, Any]):
    """
    JSON 응답 전송
    
    Args:
        writer: 스트림 라이터
        response: 응답 데이터
    """
    try:
        json_str = json.dumps(response, ensure_ascii=False) + "\n"
        writer.write(json_str.encode('utf-8'))
        await writer.drain()
    except Exception as e:
        logger.error(f"[SEND] 응답 전송 실패: {e}")