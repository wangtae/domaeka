import base64
from io import BytesIO
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ImageFilter
import os
import numpy as np
from core.logger import logger
from games.omok.constants import BOARD_STYLES, DEFAULT_BOARD_STYLE
from games.omok.utils.board_size import get_star_points
from games.omok.utils.piece_utils import to_display_piece

# 기본 설정
CELL_SIZE = 30
MARGIN = 45
STONE_SIZE = 28

# 폰트 경로
FONT_PATH = os.path.join(os.path.dirname(__file__), "..", "..", "..", "config", "fonts", "NanumGothic.ttf")
# 나무 텍스처 이미지 경로
WOOD_TEXTURE_PATH = os.path.join(os.path.dirname(__file__), "..", "..", "..", "assets", "messengerbotR", "wood_texture.jpg")

# 전역 캐시 딕셔너리
_wood_texture_cache = {}

def create_wood_texture(width, height, style, channel_id=None):
    """나무 텍스처 생성 또는 로드 (채널ID 기반 seed 및 캐싱 지원)"""
    cache_key = (channel_id, width, height, style.get("name"))
    if style.get("wood_texture", False):
        if cache_key in _wood_texture_cache:
            return _wood_texture_cache[cache_key].copy()
    try:
        if style.get("wood_texture", False):
            # seed 고정
            if channel_id is not None:
                np.random.seed(hash(channel_id) % (2**32))
            # 나무 텍스처 이미지 로드 시도
            wood_img = Image.open(WOOD_TEXTURE_PATH)
            wood_img = wood_img.resize((width, height), Image.Resampling.LANCZOS)
            enhancer = ImageEnhance.Contrast(wood_img)
            wood_img = enhancer.enhance(1.2)
            enhancer = ImageEnhance.Color(wood_img)
            wood_img = enhancer.enhance(0.8)
            wood_img = wood_img.filter(ImageFilter.GaussianBlur(radius=0.5))
            if style.get("wood_texture", False):
                _wood_texture_cache[cache_key] = wood_img.copy()
            return wood_img
    except Exception as e:
        logger.warning(f"나무 텍스처 로드 실패: {e}")
    if not style.get("wood_texture", False):
        base_color = style["background_color"]
        return Image.new('RGB', (width, height), base_color)
    # seed 고정
    if channel_id is not None:
        np.random.seed(hash(channel_id) % (2**32))
    base_color = style["background_color"]
    img = Image.new('RGB', (width, height), base_color)
    draw = ImageDraw.Draw(img)
    for i in range(0, width, 4):
        color_variation = np.random.randint(-10, 10)
        line_color = tuple(max(0, min(255, c + color_variation)) for c in base_color)
        draw.line([(i, 0), (i, height)], fill=line_color, width=2)
    for _ in range(100):
        x = np.random.randint(0, width)
        y = np.random.randint(0, height)
        size = np.random.randint(5, 20)
        color_variation = np.random.randint(-15, 15)
        knot_color = tuple(max(0, min(255, c + color_variation)) for c in base_color)
        draw.ellipse([(x-size//2, y-size//2), (x+size//2, y+size//2)], fill=knot_color)
    img = img.filter(ImageFilter.GaussianBlur(radius=1))
    if style.get("wood_texture", False):
        _wood_texture_cache[cache_key] = img.copy()
    return img

def draw_flat_stone(draw, x, y, color, size):
    """플랫(단순 원형) 바둑돌을 그립니다."""
    radius = size // 2
    if color == "black":
        draw.ellipse(
            (x - radius, y - radius, x + radius, y + radius),
            fill=(40, 40, 40),
            outline=(0, 0, 0)
        )
    else:
        draw.ellipse(
            (x - radius, y - radius, x + radius, y + radius),
            fill=(255, 255, 255),
            outline=(200, 200, 200)
        )

def draw_3d_stone(draw, x, y, color, size, style):
    """실제 바둑돌과 유사한 3D 효과를 그립니다."""
    radius = size // 2
    if color == "black":
        # 검은 돌 그라데이션 효과
        for r in range(radius, -1, -1):
            # 곡면 효과를 위한 비선형 그라데이션
            ratio = r / radius
            brightness = int(25 + (1 - ratio ** 2.5) * 35)  # 더 부드러운 곡면 효과
            draw.ellipse(
                (x - r, y - r, x + r, y + r),
                fill=(brightness, brightness, brightness),
                outline=None
            )
        
        # 광택 효과
        if style.get("stone_shadow", True):
            # 메인 하이라이트 (타원형)
            highlight_size_x = int(radius * 0.7)
            highlight_size_y = int(radius * 0.5)
            offset_x = int(radius * 0.2)
            offset_y = int(radius * 0.2)
            
            # 타원형 하이라이트
            draw.ellipse(
                (x - highlight_size_x + offset_x, 
                 y - highlight_size_y + offset_y,
                 x + highlight_size_x + offset_x, 
                 y + highlight_size_y + offset_y),
                fill=(90, 90, 90),
                outline=None
            )
            
            # 작은 보조 하이라이트
            small_size = int(radius * 0.3)
            draw.ellipse(
                (x - small_size + offset_x + 2,
                 y - small_size + offset_y + 2,
                 x + small_size + offset_x - 2,
                 y + small_size + offset_y - 2),
                fill=(120, 120, 120),
                outline=None
            )
    else:
        # 백돌: 중앙은 완전 흰색, 바깥은 연한 회색으로 부드럽게 그라데이션
        for r in range(radius, 0, -1):
            ratio = r / radius
            brightness = int(245 + 10 * ratio)  # 245~255 사이 밝은 톤
            draw.ellipse(
                (x - r, y - r, x + r, y + r),
                fill=(brightness, brightness, brightness),
                outline=None
            )
        # 중앙 완전 흰색
        draw.ellipse(
            (x - radius * 0.5, y - radius * 0.5, x + radius * 0.5, y + radius * 0.5),
            fill=(255, 255, 255),
            outline=None
        )
        # 넓고 강한 하이라이트(광택)
        if style.get("stone_shadow", True):
            highlight_size_x = int(radius * 0.9)
            highlight_size_y = int(radius * 0.6)
            offset_x = int(radius * 0.1)
            offset_y = int(radius * 0.1)
            draw.ellipse(
                (x - highlight_size_x + offset_x, 
                 y - highlight_size_y + offset_y,
                 x + highlight_size_x + offset_x, 
                 y + highlight_size_y + offset_y),
                fill=(255, 255, 255),
                outline=None
            )
        # 테두리는 아주 연하게
        for i in range(2):
            draw.ellipse(
                (x - radius + i, y - radius + i, 
                 x + radius - i, y + radius - i),
                fill=None,
                outline=(240 + i * 2, 240 + i * 2, 240 + i * 2)
            )

def get_bbox(area_coords):
    xs = [x for x, y in area_coords]
    ys = [y for x, y in area_coords]
    return min(xs), min(ys), max(xs), max(ys)

async def generate_board_image(board, last_move=None, style_name=None, channel_id=None, forbidden_points=None, restrict_areas=None, restrict_type=None):
    """
    오목판 상태를 이미지로 생성합니다.
    
    Args:
        board: NxN 2차원 배열의 오목판 상태 (None: 빈칸, '흑': 흑돌, '백': 백돌)
        last_move: 마지막 착수 위치 (x, y) 튜플
        style_name: 바둑판 스타일 이름
        channel_id: 채널 ID
        forbidden_points: 금수 좌표 리스트 [(x, y), ...] (옵션)
        restrict_areas: 제한 영역 좌표 리스트 (옵션)
        restrict_type: 'forbidden'(파란), 'allowed'(빨간) 등
    
    Returns:
        str: Base64로 인코딩된 이미지 데이터
    """
    try:
        style = BOARD_STYLES.get(style_name, BOARD_STYLES[DEFAULT_BOARD_STYLE])
        board_size = len(board)
        logger.info(f"[OMOK][이미지생성][DEBUG] generate_board_image 진입, board_size={board_size}")
        logger.info("[OMOK][이미지생성][DEBUG] generate_board_image 진입, board 전체:")
        for y, row in enumerate(board):
            logger.info(f"  board[{y}] = {row}")
        width = height = 2 * MARGIN + CELL_SIZE * (board_size - 1)
        image = create_wood_texture(width, height, style, channel_id=channel_id)
        draw = ImageDraw.Draw(image)
        
        # 격자 그리기
        grid_color = style["grid_color"] + (style["grid_alpha"],) if len(style["grid_color"]) == 3 else style["grid_color"]
        for i in range(board_size):
            # 가로선
            draw.line([(MARGIN, MARGIN + i * CELL_SIZE),
                      (width - MARGIN, MARGIN + i * CELL_SIZE)],
                     fill=grid_color, width=1)
            # 세로선
            draw.line([(MARGIN + i * CELL_SIZE, MARGIN),
                      (MARGIN + i * CELL_SIZE, height - MARGIN)],
                     fill=grid_color, width=1)
        
        # 화점 그리기
        star_points = get_star_points(board_size)
        for x, y in star_points:
            draw.ellipse([(MARGIN + x * CELL_SIZE - 3, MARGIN + y * CELL_SIZE - 3),
                         (MARGIN + x * CELL_SIZE + 3, MARGIN + y * CELL_SIZE + 3)],
                        fill=grid_color)
        
        # 좌표 표시
        try:
            font = ImageFont.truetype(FONT_PATH, 17)
        except Exception:
            font = ImageFont.load_default()
            logger.warning("기본 폰트 사용 (NanumGothic.ttf 로드 실패)")
        
        # 가로 좌표 (A-O)
        for i in range(board_size):
            x = MARGIN + i * CELL_SIZE
            # 상단 알파벳
            draw.text((x, MARGIN - 25), chr(65 + i), fill=grid_color[:3], font=font, anchor="mm")
            # 하단 알파벳
            draw.text((x, height - MARGIN + 25), chr(65 + i), fill=grid_color[:3], font=font, anchor="mm")
        
        # 세로 좌표 (1-15)
        for i in range(board_size):
            y = MARGIN + i * CELL_SIZE
            text = str(i + 1)
            # 왼쪽 숫자
            draw.text((MARGIN - 25, y), text, fill=grid_color[:3], font=font, anchor="mm")
            # 오른쪽 숫자
            draw.text((width - MARGIN + 25, y), text, fill=grid_color[:3], font=font, anchor="mm")
        
        # 돌 그리기 직전 board 배열 상태 로그 출력
        board_state_log = []
        for y in range(board_size):
            row = []
            for x in range(board_size):
                v = board[y][x]
                if v is None:
                    row.append('.')
                elif v == '흑':
                    row.append('B')
                elif v == '백':
                    row.append('W')
                else:
                    row.append(str(v))
            board_state_log.append(''.join(row))
        logger.info("[OMOK][이미지생성] board 상태:\n" + '\n'.join(board_state_log))

        # 돌 그리기
        stone_drawn = False
        for y in range(board_size):
            for x in range(board_size):
                if board[y][x] is not None:
                    stone_drawn = True
                    display_piece = to_display_piece(board[y][x])
                    logger.info(f"[OMOK][이미지생성][DEBUG] ({x},{y}) board={board[y][x]}, display_piece={display_piece}")
                    stone_x = MARGIN + x * CELL_SIZE
                    stone_y = MARGIN + y * CELL_SIZE
                    stone_color = "black" if display_piece == "흑" else "white"
                    if style.get("flat_stone", False):
                        draw_flat_stone(draw, stone_x, stone_y, stone_color, STONE_SIZE)
                    else:
                        draw_3d_stone(draw, stone_x, stone_y, stone_color, STONE_SIZE, style)
        if not stone_drawn:
            logger.warning("[OMOK][이미지생성][DEBUG] 돌 그리기 루프가 한 번도 실행되지 않음 (모든 칸이 None)")
        
        # 마지막 착수 위치 표시
        if last_move:
            x, y = last_move
            mark_x = MARGIN + x * CELL_SIZE
            mark_y = MARGIN + y * CELL_SIZE
            display_piece = to_display_piece(board[y][x])
            # 흑돌이면 흰 네모, 백돌이면 빨간 네모 (display_piece가 '백', 'W', 'white' 등 모두 포함)
            if display_piece in ("흑", "B", "black"):
                mark_color = "white"
            else:
                mark_color = "red"
            draw.rectangle([(mark_x - 5, mark_y - 5),
                          (mark_x + 5, mark_y + 5)],
                         fill=mark_color)
        
        # 금수(착수 불가) 영역 시각화
        if forbidden_points:
            for x, y in forbidden_points: 
                fx = MARGIN + x * CELL_SIZE
                fy = MARGIN + y * CELL_SIZE
                r = STONE_SIZE // 2 - 2
                draw.ellipse([(fx - r, fy - r), (fx + r, fy + r)], outline="red", width=3)
        
        # 제한 영역 시각화 (파란/빨간 네모)
        if restrict_areas:
            left, top, right, bottom = get_bbox(restrict_areas)
            rx1 = MARGIN + left * CELL_SIZE - STONE_SIZE // 2
            ry1 = MARGIN + top * CELL_SIZE - STONE_SIZE // 2
            rx2 = MARGIN + right * CELL_SIZE + STONE_SIZE // 2
            ry2 = MARGIN + bottom * CELL_SIZE + STONE_SIZE // 2
            color = "blue" if restrict_type == "allowed" else "red"
            draw.rectangle([(rx1, ry1), (rx2, ry2)], outline=color, width=4)
        
        # 이미지를 Base64로 인코딩
        buffer = BytesIO()
        image.save(buffer, format='PNG')
        image_data = base64.b64encode(buffer.getvalue()).decode('utf-8')
        
        return image_data
        
    except Exception as e:
        logger.error(f"이미지 생성 오류: {e}")
        raise 