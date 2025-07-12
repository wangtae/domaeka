import asyncio
import base64
import io
import os
from PIL import Image, ImageDraw, ImageFont
from core import globals as g
from core.logger import logger
from core.utils.send_message import send_message

# 한글 폰트 경로들 (다양한 경로 시도)
KOREAN_FONT_PATHS = [
    "/usr/share/fonts/truetype/nanum/NanumGothic.ttf",
    "/usr/share/fonts/truetype/nanum/NanumBarunGothic.ttf",
    "/usr/share/fonts/truetype/nanum/NanumGothicBold.ttf",
    "/usr/share/fonts/truetype/nanum/NanumMyeongjo.ttf",
    "/home/loa/projects/py/kakao-bot/server/config/fonts/NanumGothic.ttf"  # 기존 경로도 유지
]


def find_korean_font():
    """
    사용 가능한 한글 폰트를 찾아 반환
    """
    for font_path in KOREAN_FONT_PATHS:
        try:
            return ImageFont.truetype(font_path, 40)
        except (OSError, IOError):
            continue

    # 모든 폰트 로딩 실패 시 기본 폰트
    logger.warning("한글 폰트를 찾을 수 없습니다. 기본 폰트 사용.")
    return ImageFont.load_default()


# 이미지 생성 및 반환 함수
async def generate_text_image(text, font_size=40, padding=20, bg_color=(255, 255, 255), text_color=(0, 0, 0)):
    """
    텍스트를 이미지로 변환하여 Base64 인코딩 문자열로 반환

    Args:
        text (str): 이미지에 표시할 텍스트
        font_size (int): 폰트 크기 (기본값: 40)
        padding (int): 여백 (기본값: 20)
        bg_color (tuple): 배경색 RGB (기본값: 흰색)
        text_color (tuple): 텍스트 색상 RGB (기본값: 검정색)

    Returns:
        str: Base64로 인코딩된 이미지 데이터
    """
    try:
        # 한글 폰트 선택
        font = find_korean_font()

        # 텍스트 크기 계산 (폰트에 따라 달라짐)
        temp_img = Image.new('RGB', (1, 1), color=bg_color)
        temp_draw = ImageDraw.Draw(temp_img)

        # 텍스트를 여러 줄로 나눔
        lines = text.split('\n')
        line_widths = [temp_draw.textlength(line, font=font) for line in lines]
        max_width = max(line_widths)

        # 이미지 크기 계산
        img_width = int(max_width) + (padding * 2)
        img_height = (font_size * len(lines)) + (padding * 2)

        # 이미지 생성
        img = Image.new('RGB', (img_width, img_height), color=bg_color)
        draw = ImageDraw.Draw(img)

        # 텍스트 그리기
        y_text = padding
        for line in lines:
            draw.text((padding, y_text), line, font=font, fill=text_color)
            y_text += font_size

        # 이미지를 바이트 스트림으로 변환
        byte_arr = io.BytesIO()
        img.save(byte_arr, format='PNG')

        # Base64 인코딩
        base64_data = base64.b64encode(byte_arr.getvalue()).decode('utf-8')
        logger.info(f"[이미지 생성] 텍스트 '{text[:20]}...' 이미지 변환 성공")

        return base64_data

    except Exception as e:
        logger.error(f"[이미지 생성 오류] {str(e)}")
        return None


async def handle_image_command(prompt):
    """
    IMAGE 명령어 처리 함수

    Args:
        prompt (str): 이미지로 변환할 텍스트

    Returns:
        str: 응답 메시지
    """
    if not prompt or prompt.strip() == "":
        return "텍스트를 입력해주세요. 예시: `# IMAGE 안녕하세요`"

    try:
        # 이미지 생성
        base64_data = await generate_text_image(prompt)
        if not base64_data:
            return "이미지 생성에 실패했습니다."

        # 결과 반환
        return f"IMAGE_BASE64:{base64_data}"

    except Exception as e:
        logger.error(f"[IMAGE 명령어 오류] {str(e)}")
        return f"이미지 생성 중 오류가 발생했습니다: {str(e)}"


async def create_and_send_image(prompt, message_context):
    """
    프롬프트를 기반으로 이미지를 생성하고 전송합니다.

    Args:
        prompt (str): 이미지 생성 프롬프트
        message_context (dict): 메시지 컨텍스트
    """
    try:
        # 이미지 명령어 형식으로 변환
        image_command = f"# img {prompt}"

        # 기존 이미지 서비스 함수 호출하는 코드 (실제 구현에 맞게 수정 필요)
        from services.command_dispatcher import process_command

        # writer가 없을 때 경고 로그 남기기
        writer = message_context.get("writer")
        if not writer:
            logger.warning(f"[IMAGE_SERVICE] writer 정보가 없습니다. 이미지 전송에 문제가 있을 수 있습니다. bot_name: {message_context.get('bot_name')}, channel_id: {message_context.get('channel_id')}")

        # 명령어 처리를 위한 컨텍스트 구성
        command_context = {
            "prefix": "# img",
            "prompt": prompt,
            "channel_id": message_context.get("channel_id"),
            "bot_name": message_context.get("bot_name"),
            "user_hash": message_context.get("user_hash"),
            "room": message_context.get("room"),
            "sender": message_context.get("sender"),
            "writer": writer  # writer 추가 (None일 수 있음)
        }

        # 이미지 생성 및 전송
        await process_command(command_context)
        logger.info(f"[IMAGE_SERVICE] 이미지 생성 요청 성공: {prompt[:50]}...")

    except Exception as e:
        logger.error(f"[IMAGE_SERVICE] 이미지 생성 중 오류 발생: {e}")
