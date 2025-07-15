"""
에코 서비스 모듈
"""
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response


async def handle_echo_command(context: Dict[str, Any], prompt: str, params: Dict[str, Any]):
    """
    에코 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        prompt: 에코할 내용
        params: 파싱된 파라미터 (channel-id, repeat 등)
    """
    echo_content = prompt.strip()
    
    room = context.get('room', '')
    channel_id = context.get('channel_id', '')
    writer = context.get('writer')
    
    # 파라미터 처리
    target_channel_id = params.get('channel-id', channel_id)
    repeat_count = params.get('repeat', 1)
    
    # repeat 파라미터 제한
    if repeat_count > 5:
        repeat_count = 5
        await send_message_response(context, "⚠️ 반복 횟수는 최대 5회로 제한됩니다.")
    
    if echo_content:
        # channel-id 파라미터가 있으면 해당 채널로 전송
        if target_channel_id != channel_id:
            # 다른 채널로 전송하는 경우
            logger.info(f"[ECHO] 다른 채널로 에코: {target_channel_id}")
            # TODO: 다른 채널로 메시지 전송 구현
            await send_message_response(context, f"❌ 다른 채널로의 전송은 아직 구현되지 않았습니다.")
        else:
            # 반복 횟수만큼 전송
            for i in range(repeat_count):
                response_text = f"에코: {echo_content}"
                if repeat_count > 1:
                    response_text = f"[{i+1}/{repeat_count}] {response_text}"
                await send_message_response(context, response_text)
            logger.info(f"[ECHO] 처리 완료: {echo_content} (반복: {repeat_count}회)")
    else:
        await send_message_response(context, "에코할 내용을 입력하세요.")