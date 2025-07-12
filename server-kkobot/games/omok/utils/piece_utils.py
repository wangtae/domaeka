def to_internal_piece(piece):
    """입력값을 내부 표준('B', 'W', None)으로 변환"""
    if piece in ('B', 'W', None):
        return piece
    if piece in ('흑', 'black', 1):
        return 'B'
    if piece in ('백', 'white', 2):
        return 'W'
    return None

def to_display_piece(piece):
    """내부 표준을 사용자 표기로 변환 ('흑', '백', '.')"""
    if piece == 'B':
        return '흑'
    if piece == 'W':
        return '백'
    return '.'

def normalize_color(color):
    """색상을 내부 표준('B', 'W')으로 변환"""
    return to_internal_piece(color)

def normalize_board(board):
    """보드 전체를 내부 표준(None, 'B', 'W')로 변환"""
    def norm(v):
        return to_internal_piece(v)
    return [[norm(cell) for cell in row] for row in board] 