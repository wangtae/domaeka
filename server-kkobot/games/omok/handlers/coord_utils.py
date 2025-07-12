def parse_move_coord(coord, board_size=15):
    """
    좌표 문자열을 파싱하여 (x, y) 좌표로 변환합니다.
    board_size에 따라 가로/세로 범위 안내도 동적으로 처리합니다.
    """
    coord = coord.upper().strip()
    if len(coord) < 2 or len(coord) > 3:
        raise ValueError(f"좌표는 2~3자리여야 합니다 (예: H8, 8H, {chr(ord('A')+board_size-1)}{board_size})")
    if coord[0].isalpha():
        x_str, y_str = coord[0], coord[1:]
    elif coord[-1].isalpha():
        x_str, y_str = coord[-1], coord[:-1]
    else:
        raise ValueError(f"좌표에는 반드시 하나의 알파벳(A-{chr(ord('A')+board_size-1)})이 포함되어야 합니다")
    if not ('A' <= x_str <= chr(ord('A')+board_size-1)):
        raise ValueError(f"가로 좌표는 A-{chr(ord('A')+board_size-1)} 사이여야 합니다")
    try:
        y = int(y_str)
        if not (1 <= y <= board_size):
            raise ValueError
    except ValueError:
        raise ValueError(f"세로 좌표는 1-{board_size} 사이여야 합니다")
    x = ord(x_str) - ord('A')
    y = y - 1  # 0-based index로 변환
    return x, y

def is_valid_coord(x, y, board_size):
    """
    주어진 x, y가 board_size 범위 내에 있는지 검증
    """
    return 0 <= x < board_size and 0 <= y < board_size 