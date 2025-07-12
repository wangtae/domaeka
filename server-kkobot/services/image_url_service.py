import asyncio
import base64
import io
import os
import uuid
from datetime import datetime
from PIL import Image, ImageDraw, ImageFont
from core import globals as g
from core.logger import logger
from core.utils.send_message import send_message
from pathlib import Path

# 현재 파일(image_url_service.py)의 절대 경로를 기준으로 프로젝트 루트 계산
current_file_path = Path(__file__).resolve()
# 프로젝트 루트: /home/wangt/cursor/projects/py/kakao-bot
PROJECT_ROOT = current_file_path.parent.parent.parent.parent

# 이미지 및 HTML 저장 디렉토리 및 URL 기본 설정
BASE_DIR = PROJECT_ROOT / 'web' / 'public' / 'kakao-images'
BASE_URL = 'https://loa.best/projects/py/kakao-images' # 이 URL은 실제 웹 서버 설정에 따라 변경될 수 있습니다.
IMAGE_DIR = BASE_DIR / 'img'
HTML_DIR = BASE_DIR

# 이미지 디렉토리 생성
os.makedirs(IMAGE_DIR, exist_ok=True)

# HTML 템플릿
HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>텍스트 이미지</title>
    <meta property="og:title" content="텍스트 이미지">
    <meta property="og:description" content="{text_preview}">
    <meta property="og:image" content="{image_url}">
    <meta property="og:image:width" content="{width}">
    <meta property="og:image:height" content="{height}">
    <style>
        body {{
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }}
        .container {{
            max-width: 100%;
            text-align: center;
            padding: 20px;
        }}
        img {{
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }}
        h1 {{
            color: #333;
            margin-bottom: 20px;
        }}
        .footer {{
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>텍스트 이미지</h1>
        <img src="{image_url}" alt="텍스트 이미지" width="{width}" height="{height}">
        <div class="footer">
            <p>LOA.i가 생성한 텍스트 이미지입니다.</p>
            <p>생성 시간: {timestamp}</p>
        </div>
    </div>
</body>
</html>
"""

# 이미지 생성 및 반환 함수
async def generate_text_image(text, font_size=40, padding=20, bg_color=(255, 255, 255), text_color=(0, 0, 0)):
    """
    텍스트를 이미지로 변환하여 HTML 페이지 URL 반환

    Args:
        text (str): 이미지에 표시할 텍스트
        font_size (int): 폰트 크기 (기본값: 40)
        padding (int): 여백 (기본값: 20)
        bg_color (tuple): 배경색 RGB (기본값: 흰색)
        text_color (tuple): 텍스트 색상 RGB (기본값: 검정색)

    Returns:
        str: HTML 페이지 URL
    """
    try:
        # 고유 ID 생성
        unique_id = uuid.uuid4().hex

        # 기본 폰트 사용 (실제 환경에서는 폰트 파일 경로를 지정할 수 있음)
        try:
            font = ImageFont.truetype("Arial.ttf", font_size)
        except:
            # 트루타입 폰트를 찾을 수 없는 경우 기본 폰트 사용
            font = ImageFont.load_default()

        # 텍스트 크기 계산 (폰트에 따라 달라짐)
        temp_img = Image.new('RGB', (1, 1), color=bg_color)
        temp_draw = ImageDraw.Draw(temp_img)

        # 텍스트를 여러 줄로 나눔
        lines = text.split('\n')

        # 각 줄의 너비 계산
        line_widths = []
        for line in lines:
            try:
                # Pillow 9.0.0 이상 버전
                line_widths.append(temp_draw.textlength(line, font=font))
            except AttributeError:
                # 이전 버전 Pillow용 대체 메서드
                line_widths.append(temp_draw.textsize(line, font=font)[0])

        max_width = max(line_widths) if line_widths else 300  # 기본 너비 설정

        # 이미지 크기 계산
        img_width = int(max_width) + (padding * 2)
        img_height = (font_size * len(lines)) + (padding * 2)

        # 이미지 생성
        img = Image.new('RGB', (img_width, img_height), color=bg_color)
        draw = ImageDraw.Draw(img)

        # 텍스트 그리기
        y_text = padding
        for i, line in enumerate(lines):
            try:
                # Pillow 9.0.0 이상
                draw.text((padding, y_text), line, font=font, fill=text_color)
            except:
                # 이전 버전 Pillow
                draw.text((padding, y_text), line, fill=text_color, font=font)

            y_text += font_size

        # 이미지 파일명 및 경로 설정
        image_filename = f"img_{unique_id}.png"
        image_filepath = os.path.join(IMAGE_DIR, image_filename)
        image_url = f"{BASE_URL}/img/{image_filename}"

        # HTML 파일명 및 경로 설정
        html_filename = f"text_{unique_id}.html"
        html_filepath = os.path.join(HTML_DIR, html_filename)
        html_url = f"{BASE_URL}/{html_filename}"

        # 이미지 저장
        img.save(image_filepath, format='PNG')

        # HTML 페이지 생성
        text_preview = text.replace('\n', ' ')[:100] + ('...' if len(text) > 100 else '')
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        html_content = HTML_TEMPLATE.format(
            image_url=image_url,
            width=img_width,
            height=img_height,
            text_preview=text_preview,
            timestamp=timestamp
        )

        with open(html_filepath, 'w', encoding='utf-8') as f:
            f.write(html_content)

        logger.info(f"[이미지URL 서비스] HTML 생성 완료: {html_filepath}, URL: {html_url}")

        return html_url

    except Exception as e:
        logger.error(f"[이미지URL 서비스] 이미지 생성 오류: {str(e)}")
        return None

async def handle_image_url_command(prompt):
    """
    IMGURL 명령어 처리 함수 (HTML 페이지 URL 기반)

    Args:
        prompt (str): 이미지로 변환할 텍스트

    Returns:
        str: 응답 메시지 (HTML 페이지 URL 포함)
    """
    if not prompt or prompt.strip() == "":
        return "텍스트를 입력해주세요. 예시: `# IMGURL 안녕하세요`"

    try:
        # HTML 페이지 URL 생성
        html_url = await generate_text_image(prompt)
        if not html_url:
            return "이미지 생성에 실패했습니다."

        # HTML 페이지 URL이 포함된 응답 메시지 반환
        return f"🖼️ 텍스트 이미지가 생성되었습니다:\n\n{html_url}"

    except Exception as e:
        logger.error(f"[이미지URL 명령어 오류] {str(e)}")
        return f"이미지 생성 중 오류가 발생했습니다: {str(e)}"