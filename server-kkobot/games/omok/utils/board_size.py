from games.omok.constants import ALLOWED_BOARD_SIZES, BOARD_SIZE_DEFAULT

def parse_board_size_param(options: dict) -> int:
    """
    --board-size 파라미터를 파싱 및 검증하여 유효한 오목판 크기를 반환합니다.
    허용값: 7, 9, 11, 13, 15, 17, 19
    잘못된 값 입력 시 기본값(15) 반환
    """
    value = options.get("board-size")
    if value is None:
        return BOARD_SIZE_DEFAULT
    try:
        size = int(value)
        if size in ALLOWED_BOARD_SIZES:
            return size
    except Exception:
        pass
    return BOARD_SIZE_DEFAULT


def get_omok_input_guide(board_size: int) -> str:
    """
    오목판 크기에 맞는 착수 입력 안내 메시지 생성
    예: 가로 A-O, 세로 1-15, 중앙 예시 등
    """
    col_end = chr(ord('A') + board_size - 1)
    row_end = board_size
    center_col = chr(ord('A') + board_size // 2)
    center_row = board_size // 2 + 1
    return (
        f"📍 착수 방법\n\n"
        f"• 가로: A-{col_end} (알파벳)\n"
        f"• 세로: 1-{row_end} (숫자)\n"
        f"• 예시: {center_col}{center_row} 또는 {center_row}{center_col}\n"
    )


def get_star_points(board_size: int) -> list:
    """
    오목판 크기에 맞는 화점(스타포인트) 좌표 리스트 반환
    """
    if board_size == 15:
        return [(3,3), (3,11), (7,7), (11,3), (11,11)]
    elif board_size == 13:
        return [(3,3), (3,9), (6,6), (9,3), (9,9)]
    elif board_size == 19:
        return [(3,3), (3,9), (3,15), (9,3), (9,9), (9,15), (15,3), (15,9), (15,15)]
    else:
        center = board_size // 2
        points = [(center, center)]
        if board_size >= 9:
            margin = 2 if board_size <= 11 else 3
            points += [
                (margin, margin), (margin, board_size - 1 - margin),
                (board_size - 1 - margin, margin), (board_size - 1 - margin, board_size - 1 - margin)
            ]
        return points


# --- 아래 함수들은 engine/rule_engine.py로 이동됨 ---
# def is_double_three(board, x, y, color, as_prompt=False): ...
# def is_double_four(board, x, y, color, as_prompt=False): ...
# def is_overline(board, x, y, color, as_prompt=False): ...
# def count_open_threes(board, x, y, color): ...
# def count_open_fours(board, x, y, color): ...
# def has_n_or_more_in_a_row(board, x, y, color, n): ... 