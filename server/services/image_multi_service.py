"""
멀티 이미지 생성 및 전송 서비스 모듈 - kkobot 호환 버전
- 각 단어별로 이미지를 생성하여 base64로 인코딩 후 리스트로 반환
"""
import io
import base64
from PIL import Image, ImageDraw, ImageFont
from core.logger import logger
from core.response_utils import send_message_response
import re
import os
from typing import Dict, Any

# 한글 폰트 경로들 (kkobot과 동일하게 적용)
KOREAN_FONT_PATHS = [
    "/usr/share/fonts/truetype/nanum/NanumGothic.ttf",
    "/usr/share/fonts/truetype/nanum/NanumBarunGothic.ttf",
    "/usr/share/fonts/truetype/nanum/NanumGothicBold.ttf",
    "/usr/share/fonts/truetype/nanum/NanumMyeongjo.ttf",
    "/home/wangt/projects/client/domaeka/domaeka.dev/server/config/fonts/NanumGothic.ttf"  # 프로젝트 폰트 경로
]

# 명령어 alias 리스트
COMMAND_ALIASES = [
    "IMGEXT", "멀티이미지", "멀티텍스트이미지"
]

def find_korean_font(font_size=40):
    """
    사용 가능한 한글 폰트를 찾아 반환
    """
    for font_path in KOREAN_FONT_PATHS:
        try:
            if os.path.exists(font_path):
                return ImageFont.truetype(font_path, font_size)
        except (OSError, IOError):
            continue
    logger.warning("한글 폰트를 찾을 수 없습니다. 기본 폰트 사용.")
    return ImageFont.load_default()

async def handle_imgext_command(context: Dict[str, Any], text: str):
    """
    IMGEXT 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        text: 명령어 텍스트
    """
    try:
        writer = context.get("writer")
        if not writer:
            logger.error("[IMGEXT] Writer not found in context")
            return
            
        
        # 프롬프트 추출
        prompt = text.replace("# IMGEXT", "").strip()
        if not prompt:
            await send_message_response(context, "사용법: # IMGEXT {텍스트}")
            return
        
        # 멀티 이미지 생성
        response = await handle_multiple_image_command(prompt)
        
        # 응답 전송
        await send_message_response(context, response)
        
        logger.info(f"[IMGEXT] 명령어 처리 완료: {prompt}")
        
    except Exception as e:
        logger.error(f"[IMGEXT] 명령어 처리 오류: {e}")
        await send_message_response(context, f"이미지 생성 중 오류가 발생했습니다: {str(e)}")

async def handle_multiple_image_command(prompt: str):
    """
    입력된 프롬프트를 공백 기준으로 분할하여 각 단어별 이미지를 생성하고,
    base64로 인코딩된 이미지 리스트를 반환합니다.
    클라이언트에는 IMAGE_BASE64:base64a|||base64b|||base64c 형태로 전송해야 합니다.
    """
    try:
        if not prompt or not prompt.strip():
            return ["입력된 텍스트가 없습니다."]
        
        # 명령어 부분 제거 (alias 리스트 기반)
        prompt_strip = prompt.strip()
        for alias in COMMAND_ALIASES:
            if prompt_strip.startswith(f"# {alias} "):
                prompt_strip = prompt_strip[len(f"# {alias} "):]
                break
            if prompt_strip.startswith(f"#{alias} "):
                prompt_strip = prompt_strip[len(f"#{alias} "):]
                break
        
        logger.debug(f"[IMGEXT] 명령어 제거 후 prompt: {prompt_strip}")
        
        # 공백 기준 split, 빈 문자열 제거
        words = [w for w in prompt_strip.split(" ") if w]
        logger.debug(f"[IMGEXT] split 결과 words: {words}")
        
        base64_images = []
        font_size = 48
        padding = 20
        bg_color = (255, 255, 255)
        text_color = (0, 0, 0)
        font = find_korean_font(font_size)
        
        for word in words:
            # 텍스트 크기 계산 (bbox 사용)
            temp_img = Image.new('RGB', (1, 1), color=bg_color)
            temp_draw = ImageDraw.Draw(temp_img)
            bbox = temp_draw.textbbox((0, 0), word, font=font)
            w, h = bbox[2] - bbox[0], bbox[3] - bbox[1]
            
            img_width = w + padding * 2
            img_height = h + padding * 2
            
            img = Image.new('RGB', (img_width, img_height), color=bg_color)
            draw = ImageDraw.Draw(img)
            draw.text((padding, padding), word, fill=text_color, font=font)
            
            buf = io.BytesIO()
            img.save(buf, format='PNG')
            base64_str = base64.b64encode(buf.getvalue()).decode('utf-8')
            base64_images.append(base64_str)
        
        logger.info(f"[IMGEXT] {len(base64_images)}장 이미지 생성 완료: {words}")
        logger.debug(f"[IMGEXT] base64_images full: {base64_images}")
        
        # 클라이언트 프로토콜에 맞게 IMAGE_BASE64:... 형태로 반환
        return [f"IMAGE_BASE64:{'|||'.join(base64_images)}"]
        
    except Exception as e:
        logger.error(f"[IMGEXT] 이미지 생성 오류: {e}")
        return ["이미지 생성 중 오류가 발생했습니다."]