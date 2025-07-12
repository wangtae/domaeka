from PIL import Image, ImageDraw, ImageFont
import os
import base64
import io

FONT_PATH = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"  # 환경에 따라 경로 수정

CELL_SIZE = 30
MARGIN = 45
GRID_SIZE = 30
IMG_SIZE = MARGIN * 2 + GRID_SIZE * (CELL_SIZE - 1)


def draw_board(board, last_move=None, style_name=None, channel_id=None):
    """
    오목판 상태를 이미지로 그리고 base64로 인코딩하여 반환합니다.
    
    Args:
        board: 2D 배열 [15][15], 각 셀은 'B'(흑돌)/'W'(백돌)/None(빈칸)
        last_move: 마지막 착수 위치 (x, y) 튜플
        style_name: 스타일 이름
        channel_id: 채널 ID
        
    Returns:
        str: Base64로 인코딩된 이미지 데이터
    """
    board_size = len(board)
    img_size = CELL_SIZE * board_size + MARGIN * 2

    # 이미지 생성
    img = Image.new("RGB", (img_size, img_size), color=(240, 217, 181))  # 바둑판 색상으로 변경
    draw = ImageDraw.Draw(img)

    try:
        font = ImageFont.truetype(FONT_PATH, 26)  # 폰트 크기를 14로 변경
    except:
        font = ImageFont.load_default()

    # 좌표 표시
    for i in range(board_size):
        # 알파벳 (A-O) - 하단에 표시
        draw.text((MARGIN + i * GRID_SIZE, img_size - MARGIN), chr(ord('A') + i), font=font, fill="black", anchor="mm")
        # 숫자 (1-15) - 오른쪽에 표시
        num_text = str(i + 1)
        draw.text((img_size - MARGIN, MARGIN + i * GRID_SIZE), num_text, font=font, fill="black", anchor="mm")

    # 격자 그리기 (1부터 15까지)
    for i in range(board_size):
        start = MARGIN + i * GRID_SIZE
        # 가로선 (길이 조정)
        draw.line([(MARGIN, start), (MARGIN + (board_size - 1) * GRID_SIZE, start)], fill="black", width=1)
        # 세로선 (길이 조정)
        draw.line([(start, MARGIN), (start, MARGIN + (board_size - 1) * GRID_SIZE)], fill="black", width=1)

    # 화점 그리기 (3-3, 3-11, 11-3, 11-11, 7-7)
    star_points = [(3, 3), (3, 11), (11, 3), (11, 11), (7, 7)]
    for x, y in star_points:
        cx = MARGIN + x * GRID_SIZE
        cy = MARGIN + y * GRID_SIZE
        draw.ellipse([(cx - 5, cy - 5), (cx + 5, cy + 5)], fill="black")

    # 바둑돌 그리기
    for y in range(board_size):
        for x in range(board_size):
            piece = board[y][x]
            if piece:
                cx = MARGIN + x * GRID_SIZE
                cy = MARGIN + y * GRID_SIZE
                radius = CELL_SIZE // 2 - 4
                color = "black" if piece == "흑" else "white"
                # 바둑돌 그리기 (테두리 포함)
                draw.ellipse([(cx - radius, cy - radius), (cx + radius, cy + radius)],
                           fill=color, outline="black")

    # 마지막 착수 위치 표시
    if last_move:
        lx, ly = last_move
        cx = MARGIN + lx * GRID_SIZE
        cy = MARGIN + ly * GRID_SIZE
        piece = board[ly][lx]
        mark_color = "white" if piece == "흑" else "red"
        draw.ellipse([(cx - 4, cy - 4), (cx + 4, cy + 4)], fill=mark_color)

    # 이미지를 base64로 인코딩
    byte_arr = io.BytesIO()
    img.save(byte_arr, format='PNG')
    base64_data = base64.b64encode(byte_arr.getvalue()).decode('utf-8')
    
    return base64_data


def render_board(board, last_move=None):
    """
    오목판을 렌더링하고 base64로 인코딩된 이미지를 반환합니다.
    
    Args:
        board: 2D 배열 [15][15], 'B'/'W'/None
        last_move: (x, y) 좌표
        
    Returns:
        str: Base64로 인코딩된 이미지 데이터
    """
    img = Image.new('RGB', (IMG_SIZE, IMG_SIZE), (240, 217, 181))
    draw = ImageDraw.Draw(img)
    
    try:
        font = ImageFont.truetype(FONT_PATH, 16)
    except:
        font = ImageFont.load_default()

    # 그리드 선
    for i in range(CELL_SIZE):
        x = MARGIN + i * GRID_SIZE
        draw.line([(x, MARGIN), (x, IMG_SIZE - MARGIN)], fill="black", width=1)
        draw.line([(MARGIN, x), (IMG_SIZE - MARGIN, x)], fill="black", width=1)
        draw.text((x - 5, 5), chr(ord('A') + i), font=font, fill="black")  # A~O
        draw.text((5, x - 5), str(i + 1), font=font, fill="black")

    # 바둑돌
    for y in range(CELL_SIZE):
        for x in range(CELL_SIZE):
            piece = board[y][x]
            if piece:
                cx = MARGIN + x * GRID_SIZE
                cy = MARGIN + y * GRID_SIZE
                radius = 15
                color = "black" if piece == 'B' else "white"
                draw.ellipse((cx - radius, cy - radius, cx + radius, cy + radius), fill=color, outline="black")
                # 마지막 착수 표시
                if last_move and (x, y) == last_move:
                    draw.ellipse((cx - 5, cy - 5, cx + 5, cy + 5), fill="red")

    # 이미지를 base64로 인코딩
    byte_arr = io.BytesIO()
    img.save(byte_arr, format='PNG')
    base64_data = base64.b64encode(byte_arr.getvalue()).decode('utf-8')
    
    return base64_data
