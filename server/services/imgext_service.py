"""
이미지 추출 테스트 서비스
"""
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response


async def handle_imgext_command(context: Dict[str, Any], prompt: str, params: Dict[str, Any]):
    """
    이미지 추출 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        prompt: 명령어 뒤의 텍스트
        params: 파싱된 파라미터 (count 등)
    """
    try:
        # 파라미터 처리
        image_count = params.get('count', 2)
        
        # 테스트용 이미지 데이터 (예시)
        test_images = [
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",  # 1x1 픽셀 이미지
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==",  # 또 다른 1x1 픽셀
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",  # 세 번째
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",  # 네 번째
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=="   # 다섯 번째
        ]
        
        # 요청된 개수만큼 이미지 선택
        selected_images = test_images[:min(image_count, len(test_images))]
        
        # IMAGE_BASE64 형식으로 반환
        response = f"IMAGE_BASE64:{'|||'.join(selected_images)}"
        
        await send_message_response(context, response)
        logger.info(f"[IMGEXT] 이미지 추출 테스트 완료 (개수: {len(selected_images)}개)")
        
    except Exception as e:
        logger.error(f"[IMGEXT] 이미지 추출 오류: {e}")
        await send_message_response(context, f"❌ 이미지 추출 실패: {str(e)}")