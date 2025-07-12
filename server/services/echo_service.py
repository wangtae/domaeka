"""
에코 서비스 모듈
"""
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response


async def handle_echo_command(context: Dict[str, Any], text: str):
    """
    에코 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        text: 입력 텍스트
    """
    # '# echo ' 제거하여 에코할 내용 추출
    echo_content = text[7:].strip()  # '# echo ' 길이만큼 제거
    
    room = context.get('room', '')
    channel_id = context.get('channel_id', '')
    writer = context.get('writer')
    
    if echo_content:
        response_text = f"에코: {echo_content}"
        await send_message_response(context, response_text)
        logger.info(f"[ECHO] 처리 완료: {echo_content}")
    else:
        await send_message_response(context, "에코할 내용을 입력하세요.")