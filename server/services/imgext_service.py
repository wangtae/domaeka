"""
멀티이미지 전송 테스트 서비스
"""
import base64
import io
from typing import Dict, Any, List
from core.logger import logger
from core.response_utils import send_message_response

# PIL 라이브러리 임포트 시도
try:
    from PIL import Image, ImageDraw, ImageFont
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False
    logger.warning("[IMGEXT] PIL (Pillow) 라이브러리가 설치되어 있지 않습니다. 기본 이미지를 사용합니다.")


# 사전 정의된 색상별 1x1 픽셀 이미지 (PIL이 없을 때 사용)
PREDEFINED_IMAGES = {
    'red': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",      # 빨강
    'green': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",    # 초록
    'blue': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPj/HwABhAGAd3VKJwAAAABJRU5ErkJggg==",     # 파랑
    'yellow': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==",   # 노랑
    'magenta': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BwHwAFhQGAk7mlAgAAAABJRU5ErkJggg==",  # 마젠타
    'cyan': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+P//PwAFPQIDqL1kIgAAAABJRU5ErkJggg==",     # 시안
    'orange': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==",   # 주황
    'purple': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",   # 보라
    'gray': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mO8e+9+PQAG7AKjxl6YXwAAAABJRU5ErkJggg==",     # 회색
    'white': "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8////fwAKAwMBvJBRKAAAAABJRU5ErkJggg=="     # 흰색
}


def create_test_image(text: str, color: tuple) -> str:
    """
    텍스트와 배경색으로 테스트 이미지 생성
    
    Args:
        text: 이미지에 표시할 텍스트
        color: RGB 배경색 튜플
        
    Returns:
        Base64 인코딩된 이미지 문자열
    """
    if not PIL_AVAILABLE:
        # PIL이 없으면 색상에 맞는 사전 정의된 이미지 반환
        color_names = ['red', 'green', 'blue', 'yellow', 'magenta', 'cyan', 'orange', 'purple', 'gray', 'white']
        color_index = hash(str(color)) % len(color_names)
        return PREDEFINED_IMAGES[color_names[color_index]]
    
    # PIL이 있으면 실제 이미지 생성
    # 100x100 이미지 생성
    img = Image.new('RGB', (100, 100), color=color)
    draw = ImageDraw.Draw(img)
    
    # 텍스트 그리기 (중앙 정렬)
    text_color = (255, 255, 255) if sum(color) < 384 else (0, 0, 0)  # 배경색에 따라 텍스트 색상 결정
    try:
        # 기본 폰트 사용 (시스템 폰트가 없을 경우를 대비)
        font = ImageFont.load_default()
    except:
        font = None
    
    # 텍스트 위치 계산
    if font:
        bbox = draw.textbbox((0, 0), text, font=font)
        text_width = bbox[2] - bbox[0]
        text_height = bbox[3] - bbox[1]
    else:
        text_width = len(text) * 6  # 대략적인 추정
        text_height = 11
    
    x = (100 - text_width) // 2
    y = (100 - text_height) // 2
    
    draw.text((x, y), text, fill=text_color, font=font)
    
    # Base64로 인코딩
    buffer = io.BytesIO()
    img.save(buffer, format='PNG')
    img_data = buffer.getvalue()
    base64_str = base64.b64encode(img_data).decode('utf-8')
    
    return base64_str


async def handle_imgext_command(context: Dict[str, Any], prompt: str, params: Dict[str, Any]):
    """
    멀티이미지 전송 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        prompt: 명령어 뒤의 텍스트 (공백으로 구분된 값들)
        params: 파싱된 파라미터 (media-wait-time 등)
    """
    try:
        # 파라미터 처리
        media_wait_time = params.get('media-wait-time')  # None이면 클라이언트 기본값 사용
        
        # prompt를 공백으로 분리하여 각 값 추출
        values = prompt.strip().split() if prompt.strip() else []
        
        if not values:
            await send_message_response(context, "사용법: # IMGEXT 1 2 3 (공백으로 구분된 값들)")
            return
        
        # 배경색 정의 (다양한 색상)
        colors = [
            (255, 0, 0),      # 빨강
            (0, 255, 0),      # 초록
            (0, 0, 255),      # 파랑
            (255, 255, 0),    # 노랑
            (255, 0, 255),    # 마젠타
            (0, 255, 255),    # 시안
            (255, 128, 0),    # 주황
            (128, 0, 255),    # 보라
            (0, 128, 255),    # 하늘색
            (255, 0, 128),    # 분홍
        ]
        
        # 각 값에 대해 이미지 생성
        images = []
        for i, value in enumerate(values):
            color = colors[i % len(colors)]  # 색상 순환
            try:
                img_base64 = create_test_image(value, color)
                images.append(img_base64)
            except Exception as e:
                logger.error(f"[IMGEXT] 이미지 생성 실패 ({value}): {e}")
                # 실패한 경우 기본 이미지 사용
                images.append("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==")
        
        # IMAGE_BASE64 형식으로 반환
        response = f"IMAGE_BASE64:{'|||'.join(images)}"
        
        # media_wait_time 파라미터와 함께 메시지 전송
        await send_message_response(context, response, media_wait_time=media_wait_time)
        
        log_msg = f"[IMGEXT] 멀티이미지 전송 완료 (개수: {len(images)}개, 값: {values}"
        if media_wait_time:
            log_msg += f", 대기시간: {media_wait_time}ms"
        log_msg += ")"
        logger.info(log_msg)
        
    except Exception as e:
        logger.error(f"[IMGEXT] 멀티이미지 전송 오류: {e}")
        await send_message_response(context, f"❌ 멀티이미지 전송 실패: {str(e)}")