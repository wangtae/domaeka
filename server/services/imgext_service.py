"""
이미지 추출 테스트 서비스
"""
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response


async def handle_imgext_command(context: Dict[str, Any], prompt: str):
    """
    이미지 추출 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        prompt: 명령어 뒤의 텍스트
    """
    try:
        # 테스트용 이미지 데이터 (예시)
        test_images = [
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",  # 1x1 픽셀 이미지
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=="   # 또 다른 1x1 픽셀
        ]
        
        # IMAGE_BASE64 형식으로 반환
        response = f"IMAGE_BASE64:{'|||'.join(test_images)}"
        
        await send_message_response(context, response)
        logger.info(f"[IMGEXT] 이미지 추출 테스트 완료")
        
    except Exception as e:
        logger.error(f"[IMGEXT] 이미지 추출 오류: {e}")
        await send_message_response(context, f"❌ 이미지 추출 실패: {str(e)}")