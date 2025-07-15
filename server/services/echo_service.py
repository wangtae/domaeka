"""
에코 서비스 모듈
"""
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response


async def handle_echo_command(context: Dict[str, Any], prompt: str):
    """
    에코 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        prompt: 에코할 내용
    """
    echo_content = prompt.strip()
    
    room = context.get('room', '')
    channel_id = context.get('channel_id', '')
    writer = context.get('writer')
    
    if echo_content:
        response_text = f"에코: {echo_content}"
        await send_message_response(context, response_text)
        logger.info(f"[ECHO] 처리 완료: {echo_content}")
    else:
        await send_message_response(context, "에코할 내용을 입력하세요.")