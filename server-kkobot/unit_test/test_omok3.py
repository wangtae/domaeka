# omok_double_three_checker.py

from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board
from games.omok.engine import rule_engine

def get_double_three_forbidden_points(board, color):
    """
    삼삼 금수 위치를 효율적으로 찾는 함수
    
    Args:
        board: 15x15 오목판 (None: 빈칸, 'B': 흑, 'W': 백)
        color: 검사할 돌 색깔 ('B' 또는 'W')
    
    Returns:
        list: 삼삼 금수 위치 좌표 리스트 [(x, y), ...]
    """
    board_size = len(board)
    forbidden_points = []
    checked_points = set()  # 중복 검사 방지
    
    # 현재 돌이 있는 위치 찾기
    stone_positions = []
    for y in range(board_size):
        for x in range(board_size):
            if board[y][x] == color:
                stone_positions.append((x, y))
    
    # 각 돌 주변 2칸 범위 검사
    for px, py in stone_positions:
        for dy in range(-2, 3):
            for dx in range(-2, 3):
                nx, ny = px + dx, py + dy
                
                # 보드 범위 체크
                if 0 <= nx < board_size and 0 <= ny < board_size:
                    # 빈 칸이고 아직 검사하지 않은 위치
                    if board[ny][nx] is None and (nx, ny) not in checked_points:
                        checked_points.add((nx, ny))
                        
                        # 해당 위치에 착수했을 때 삼삼인지 검사
                        if is_double_three(board, nx, ny, color):
                            forbidden_points.append((nx, ny))
    
    return forbidden_points

def is_open_three_in_direction(board, x, y, color, dx, dy):
    board_size = len(board)
    line = []
    for i in range(-4, 5):
        nx = x + i * dx
        ny = y + i * dy
        if 0 <= nx < board_size and 0 <= ny < board_size:
            line.append(board[ny][nx])
        else:
            line.append('EDGE')
    center = 4
    found = False
    for start in range(len(line) - 4):
        if start <= center <= start + 4:
            window = line[start:start+5]
            patterns = [
                [None, color, color, color, None],
                [None, color, None, color, color],
                [None, color, color, None, color],
                [color, None, color, color, None],
                [color, color, None, color, None],
            ]
            for pattern in patterns:
                if match_pattern(window, pattern):
                    center_in_window = center - start
                    if window[center_in_window] == color:
                        if not found:
                            found = True
    return found

def is_double_three(board, x, y, color, as_prompt=False):
    if as_prompt:
        return (
            "삼삼 금수란, 한 수로 열린 3(양쪽이 막히지 않은 3목)을 두 개 이상 동시에 만드는 경우를 말합니다. "
            "이 룰에서는 흑 또는 백이 한 번의 착수로 열린 3을 두 개 이상 만들면 해당 수는 불법수(금수)입니다."
        )
    if board[y][x] is not None:
        return False
    temp_board = [row[:] for row in board]
    temp_board[y][x] = color
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    open_three_count = 0
    for dx, dy in directions:
        if is_open_three_in_direction(temp_board, x, y, color, dx, dy):
            open_three_count += 1
    return open_three_count >= 2

def match_pattern(window, pattern):
    """패턴 매칭 함수"""
    if len(window) != len(pattern):
        return False
    for i in range(len(pattern)):
        if pattern[i] is not None and window[i] != pattern[i]:
            return False
    return True

# 테스트 및 시각화 함수들
def visualize_board(board, forbidden_points=None, highlight_pos=None):
    """보드를 시각화하여 출력"""
    result = []
    result.append("    A B C D E F G H I J K L M N O")
    result.append("   --------------------------------")
    
    for y in range(15):
        row_str = f"{y+1:2d} |"
        for x in range(15):
            cell = board[y][x]
            if (x, y) == highlight_pos:
                cell_str = '[X]' if cell is None else f'[{cell}]'
            elif forbidden_points and (x, y) in forbidden_points:
                cell_str = ' * ' if cell is None else f' {cell} '
            else:
                cell_str = ' . ' if cell is None else f' {cell} '
            row_str += cell_str
        result.append(row_str)
    return '\n'.join(result)

def assert_double_three_equal(board, x, y, color):
    result_custom = is_double_three(board, x, y, color)
    result_engine = rule_engine.is_double_three(board, x, y, color)
    assert result_custom == result_engine, f"is_double_three 불일치: custom={result_custom}, engine={result_engine}, pos=({x},{y}), color={color}"
    return result_custom

def assert_double_three_points_equal(board, color):
    points_custom = set(get_double_three_forbidden_points(board, color))
    points_engine = set(rule_engine.get_double_three_forbidden_points(board, color))
    assert points_custom == points_engine, f"get_double_three_forbidden_points 불일치: custom={points_custom}, engine={points_engine}"
    return points_custom

def test_double_three_patterns():
    """다양한 삼삼 패턴 테스트"""
    print("=" * 50)
    print("삼삼(Double Three) 패턴 테스트")
    print("=" * 50)
    
    # 테스트 1: 기본 십자형 삼삼
    print("\n[테스트 1] 십자형 삼삼")
    board1 = [[None]*15 for _ in range(15)]
    board1[7][6] = 'B'  # G8
    board1[7][8] = 'B'  # I8
    board1[6][7] = 'B'  # H7
    board1[8][7] = 'B'  # H9
    
    print(visualize_board(board1, highlight_pos=(7,7)))
    print("H8(7,7)에 착수하면:")
    print("- 가로: G8-H8-I8 (열린 3)")
    print("- 세로: H7-H8-H9 (열린 3)")
    result = assert_double_three_equal(board1, 7, 7, 'B')
    print(f"삼삼인가? {result}")
    
    # 테스트 2: 대각선 삼삼
    print("\n[테스트 2] 대각선 삼삼")
    board2 = [[None]*15 for _ in range(15)]
    board2[5][5] = 'B'  # F6
    board2[7][7] = 'B'  # H8
    board2[7][5] = 'B'  # F8
    board2[5][7] = 'B'  # H6
    
    print(visualize_board(board2, highlight_pos=(6,6)))
    print("G7(6,6)에 착수하면:")
    print("- 대각선↘: F6-G7-H8 (열린 3)")
    print("- 대각선↗: F8-G7-H6 (열린 3)")
    result = assert_double_three_equal(board2, 6, 6, 'B')
    print(f"삼삼인가? {result}")
    
    # 테스트 3: 한 칸 띄어진 삼삼
    print("\n[테스트 3] 한 칸 띄어진 삼삼")
    board3 = [[None]*15 for _ in range(15)]
    board3[5][5] = 'B'  # F6
    board3[6][6] = 'B'  # G7
    board3[8][8] = 'B'  # I9
    board3[8][6] = 'B'  # G9
    board3[6][8] = 'B'  # I7
    
    print(visualize_board(board3, highlight_pos=(7,7)))
    print("H8(7,7)에 착수하면:")
    print("- 대각선↘: F6-G7-*-H8-I9 (한 칸 띄어진 열린 3)")
    print("- 대각선↗: G9-*-H8-I7 (한 칸 띄어진 열린 3)")
    result = assert_double_three_equal(board3, 7, 7, 'B')
    print(f"삼삼인가? {result}")
    
    # 테스트 4: 실제 게임 상황
    print("\n[테스트 4] 실제 게임 상황")
    board4 = [[None]*15 for _ in range(15)]
    board4[3][3] = 'W'  # D4
    board4[3][4] = 'W'  # E4
    board4[6][8] = 'B'  # I7
    board4[7][7] = 'B'  # H8
    
    forbidden = get_double_three_forbidden_points(board4, 'B')
    print(visualize_board(board4, forbidden))
    print(f"삼삼 금수 위치: {forbidden}")
    
    # K5 상세 분석
    print("\nK5(10,4) 상세 분석:")
    temp_board = [row[:] for row in board4]
    temp_board[4][10] = 'B'  # K5 착수
    
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    direction_names = ["가로", "세로", "대각선↘", "대각선↗"]
    open_three_count = 0
    
    for i, (dx, dy) in enumerate(directions):
        if is_open_three_in_direction(temp_board, 10, 4, 'B', dx, dy):
            open_three_count += 1
            print(f"  {direction_names[i]} 방향에서 열린 3 발견")
    
    print(f"총 열린 3 개수: {open_three_count}")
    print(f"삼삼인가? {open_three_count >= 2}")
    
    # F10 상세 분석
    print("\nF10(5,9) 상세 분석:")
    temp_board2 = [row[:] for row in board4]
    temp_board2[9][5] = 'B'  # F10 착수
    
    open_three_count = 0
    for i, (dx, dy) in enumerate(directions):
        if is_open_three_in_direction(temp_board2, 5, 9, 'B', dx, dy):
            open_three_count += 1
            print(f"  {direction_names[i]} 방향에서 열린 3 발견")
    
    print(f"총 열린 3 개수: {open_three_count}")
    print(f"삼삼인가? {open_three_count >= 2}")

def test_non_double_three():
    """삼삼이 아닌 경우 테스트"""
    print("\n" + "=" * 50)
    print("삼삼이 아닌 경우 테스트")
    print("=" * 50)
    
    # 테스트 1: 단일 열린 3
    print("\n[테스트 1] 단일 열린 3")
    board = [[None]*15 for _ in range(15)]
    board[7][6] = 'B'  # G8
    board[7][8] = 'B'  # I8
    
    print(visualize_board(board, highlight_pos=(7,7)))
    print("H8(7,7)에 착수하면:")
    print("- 가로: G8-H8-I8 (열린 3)")
    print(f"삼삼인가? {is_double_three(board, 7, 7, 'B')}")
    
    # 테스트 2: 막힌 3
    print("\n[테스트 2] 막힌 3")
    board2 = [[None]*15 for _ in range(15)]
    board2[7][5] = 'W'  # F8 (백돌로 막음)
    board2[7][6] = 'B'  # G8
    board2[7][8] = 'B'  # I8
    board2[6][7] = 'B'  # H7
    board2[8][7] = 'B'  # H9
    
    print(visualize_board(board2, highlight_pos=(7,7)))
    print("H8(7,7)에 착수하면:")
    print("- 가로: (W)G8-H8-I8 (막힌 3)")
    print("- 세로: H7-H8-H9 (열린 3)")
    print(f"삼삼인가? {is_double_three(board2, 7, 7, 'B')}")

def test_custom_case():
    """사용자 제시 보드 상황에서 삼삼 금수 위치 테스트"""
    print("\n" + "=" * 50)
    print("사용자 제시 보드 상황 삼삼 금수 테스트")
    print("=" * 50)
    board = [[None]*15 for _ in range(15)]
    # 백돌 위치
    board[3][3] = 'W'
    board[5][5] = 'W'
    board[6][5] = 'W'
    # 흑돌 위치
    board[6][8] = 'B'
    board[6][10] = 'B'
    board[7][7] = 'B'
    board[7][9] = 'B'
    board[5][8] = 'B'
    board[7][6] = 'B'

    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden))
    print(f"삼삼 금수 위치: {forbidden}")

def test_custom_case_2():
    """첫 번째 변형 보드 상황 테스트"""
    print("\n" + "=" * 50)
    print("첫 번째 변형 보드 상황 삼삼 금수 테스트")
    print("=" * 50)
    board = [[None]*15 for _ in range(15)]
    board[3][3] = 'W'
    board[5][5] = 'W'
    board[6][5] = 'W'
    board[6][8] = 'B'
    board[6][10] = 'B'
    board[7][7] = 'B'
    board[7][9] = 'B'
    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden))
    print(f"삼삼 금수 위치: {forbidden}")

def test_custom_case_3():
    """두 번째 변형 보드 상황 테스트"""
    print("\n" + "=" * 50)
    print("두 번째 변형 보드 상황 삼삼 금수 테스트")
    print("=" * 50)
    board = [[None]*15 for _ in range(15)]
    board[3][3] = 'W'
    board[5][5] = 'W'
    board[6][5] = 'W'
    board[5][9] = 'B'
    board[6][8] = 'B'
    board[6][10] = 'B'
    board[7][7] = 'B'
    board[7][9] = 'B'
    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden))
    print(f"삼삼 금수 위치: {forbidden}")

def test_various_double_four_patterns():
    """다양한 사사(이중사) 금수 케이스 자동 검증"""
    try:
        from games.omok.engine.rule_engine import is_double_four
    except ImportError:
        print("is_double_four 함수를 임포트할 수 없습니다. 경로를 확인하세요.")
        return
    size = 15

    # 1. 가로+세로 사사
    board1 = [[None]*size for _ in range(size)]
    board1[7][6] = 'B'
    board1[7][8] = 'B'
    board1[7][9] = 'B'
    board1[6][7] = 'B'
    board1[8][7] = 'B'
    board1[9][7] = 'B'
    assert is_double_four(board1, 7, 7, 'B'), "가로+세로 사사 금수여야 함"

    # 2. 대각선+가로 사사
    board2 = [[None]*size for _ in range(size)]
    board2[5][5] = 'B'
    board2[6][6] = 'B'
    board2[8][8] = 'B'
    board2[7][5] = 'B'
    board2[7][6] = 'B'
    board2[7][8] = 'B'
    assert is_double_four(board2, 7, 7, 'B'), "대각선+가로 사사 금수여야 함"

    # 3. 한쪽이 막힌 4 (금수 아님)
    board3 = [[None]*size for _ in range(size)]
    board3[7][5] = 'W'
    board3[7][6] = 'B'
    board3[7][7] = 'B'
    board3[7][8] = 'B'
    assert not is_double_four(board3, 7, 7, 'B'), "한쪽이 막힌 4는 금수 아님"

    # 4. 한 칸 띄운 사사
    board4 = [[None]*size for _ in range(size)]
    board4[7][5] = 'B'
    board4[7][7] = 'B'
    board4[7][9] = 'B'
    board4[6][6] = 'B'
    board4[8][8] = 'B'
    assert is_double_four(board4, 7, 7, 'B'), "한 칸 띄운 사사 금수여야 함"

    # 5. 5목이 되는 경우(금수 아님)
    board5 = [[None]*size for _ in range(size)]
    board5[7][5] = 'B'
    board5[7][6] = 'B'
    board5[7][7] = 'B'
    board5[7][8] = 'B'
    assert not is_double_four(board5, 7, 9, 'B'), "5목 완성은 금수 아님"

    # 6. 빈 보드(금수 없음)
    board6 = [[None]*size for _ in range(size)]
    for y in range(size):
        for x in range(size):
            assert not is_double_four(board6, x, y, 'B'), "빈 보드는 금수 없어야 함"

    print("사사(이중사) 금수 자동 테스트 통과!")

def is_five_in_a_row_custom(board, x, y, color):
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    for dx, dy in directions:
        count = 1
        for dir in [1, -1]:
            nx, ny = x, y
            while True:
                nx += dx * dir
                ny += dy * dir
                if 0 <= nx < board_size and 0 <= ny < board_size and board[ny][nx] == color:
                    count += 1
                else:
                    break
        if count >= 5:
            return True
    return False

def is_four_in_direction_custom(board, x, y, color, dx, dy):
    board_size = len(board)
    line = []
    for i in range(-5, 6):
        nx = x + i * dx
        ny = y + i * dy
        if 0 <= nx < board_size and 0 <= ny < board_size:
            line.append(board[ny][nx])
        else:
            line.append('EDGE')
    center = 5
    # 6칸 윈도우로 사(4목) 패턴 검사
    for start in range(len(line) - 5):
        window = line[start:start+6]
        move_idx = center - start
        # 연속 4개: [*, B, B, B, B, *] (여기서 *은 None, 상대돌, EDGE 모두 허용)
        for i in range(3):
            segment = window[i:i+4]
            if all(stone == color for stone in segment):
                left = window[i-1] if i-1 >= 0 else 'EDGE'
                right = window[i+5] if i+5 < 6 else 'EDGE'
                if left is None or right is None:
                    if i <= move_idx < i+4:
                        return True
        # 한 칸 띄운 4목(비연속, 빈칸에 착수 시 5목 완성)
        for i in range(2):
            segment = window[i:i+5]
            if segment.count(color) == 4 and segment.count(None) == 1:
                idxs = [j for j in range(5) if segment[j] is None]
                for idx in idxs:
                    test = segment[:]
                    test[idx] = color
                    if all(stone == color for stone in test):
                        if move_idx == i+idx:
                            return True
    return False

def is_double_four_custom(board, x, y, color):
    if board[y][x] is not None:
        return False
    temp_board = [row[:] for row in board]
    temp_board[y][x] = color
    if is_five_in_a_row_custom(temp_board, x, y, color):
        return False
    open_four_count = 0
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    for dx, dy in directions:
        if is_four_in_direction_custom(temp_board, x, y, color, dx, dy):
            open_four_count += 1
    return open_four_count >= 2

def get_double_four_forbidden_points_custom(board, color):
    board_size = len(board)
    forbidden_points = []
    checked_points = set()
    stone_positions = []
    for y in range(board_size):
        for x in range(board_size):
            if board[y][x] == color:
                stone_positions.append((x, y))
    for px, py in stone_positions:
        for dy in range(-2, 3):
            for dx in range(-2, 3):
                nx, ny = px + dx, py + dy
                if 0 <= nx < board_size and 0 <= ny < board_size:
                    if board[ny][nx] is None and (nx, ny) not in checked_points:
                        checked_points.add((nx, ny))
                        if is_double_four_custom(board, nx, ny, color):
                            forbidden_points.append((nx, ny))
    return forbidden_points

def test_various_double_four_patterns_custom():
    print("\n" + "=" * 50)
    print("사사(이중사) 금수 패턴 테스트 (커스텀)")
    print("=" * 50)
    size = 15
    # 1. 가로+세로 사사
    board1 = [[None]*size for _ in range(size)]
    board1[7][6] = 'B'
    board1[7][8] = 'B'
    board1[7][9] = 'B'
    board1[6][7] = 'B'
    board1[8][7] = 'B'
    board1[9][7] = 'B'
    assert is_double_four_custom(board1, 7, 7, 'B'), "가로+세로 사사 금수여야 함"
    # 2. 대각선+가로 사사
    board2 = [[None]*size for _ in range(size)]
    board2[5][5] = 'B'
    board2[6][6] = 'B'
    board2[8][8] = 'B'
    board2[7][5] = 'B'
    board2[7][6] = 'B'
    board2[7][8] = 'B'
    assert is_double_four_custom(board2, 7, 7, 'B'), "대각선+가로 사사 금수여야 함"
    # 3. 한쪽이 막힌 4 (금수 아님)
    board3 = [[None]*size for _ in range(size)]
    board3[7][5] = 'W'
    board3[7][6] = 'B'
    board3[7][7] = 'B'
    board3[7][8] = 'B'
    assert not is_double_four_custom(board3, 7, 7, 'B'), "한쪽이 막힌 4는 금수 아님"
    # 4. 한 칸 띄운 사사
    board4 = [[None]*size for _ in range(size)]
    board4[7][5] = 'B'
    board4[7][7] = 'B'
    board4[7][9] = 'B'
    board4[6][6] = 'B'
    board4[8][8] = 'B'
    assert is_double_four_custom(board4, 7, 7, 'B'), "한 칸 띄운 사사 금수여야 함"
    # 5. 5목이 되는 경우(금수 아님)
    board5 = [[None]*size for _ in range(size)]
    board5[7][5] = 'B'
    board5[7][6] = 'B'
    board5[7][7] = 'B'
    board5[7][8] = 'B'
    assert not is_double_four_custom(board5, 7, 9, 'B'), "5목 완성은 금수 아님"
    # 6. 빈 보드(금수 없음)
    board6 = [[None]*size for _ in range(size)]
    for y in range(size):
        for x in range(size):
            assert not is_double_four_custom(board6, x, y, 'B'), "빈 보드는 금수 없어야 함"
    print("사사(이중사) 금수 자동 테스트(커스텀) 통과!")

def test_specific_double_three_case():
    print("\n[삼삼 금수 후보 좌표 판정 테스트]")
    size = 15
    board = [[None]*size for _ in range(size)]
    # 백돌
    board[3][3] = 'W'  # d4
    board[3][4] = 'W'  # e4
    # 흑돌
    board[7][7] = 'B'  # H8
    board[6][8] = 'B'  # i7
    # 금수 후보 좌표
    candidates = [
        (4,10),  # E11
        (5,9),   # f10
        (6,8),   # g9
        (9,5),   # j6
        (10,4),  # k5
        (11,3),  # l4
    ]
    for x, y in candidates:
        result = assert_double_three_equal(board, x, y, 'B')
        print(f"좌표 ({x},{y}) → 금수: {result}")
    # 자동 검증: 모두 금수(True)여야 함
    for x, y in candidates:
        assert assert_double_three_equal(board, x, y, 'B'), f"{x},{y}는 삼삼 금수여야 함"
    print("[삼삼 금수 후보 좌표 판정 테스트] 통과!")

def test_user_case_h7():
    print("\n[사용자 제시 보드 상황: H7 금수 오탐 테스트]")
    size = 15
    board = [[None]*size for _ in range(size)]
    # 백돌
    board[3][4] = 'W'  # E4
    board[3][5] = 'W'  # F4
    board[4][2] = 'W'  # C5
    board[5][6] = 'W'  # G6
    board[6][6] = 'W'  # G7
    # 흑돌
    board[6][8] = 'B'  # I7
    board[6][10] = 'B' # K7
    board[7][7] = 'B'  # H8
    board[7][9] = 'B'  # J8
    board[9][7] = 'B'  # H10
    # 금수 판정 좌표
    h7 = (7,6)
    j8 = (9,7)
    k5 = (10,4)
    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden, highlight_pos=h7))
    print(f"금수 좌표: {forbidden}")
    # H7은 금수가 아니어야 함
    assert h7 not in forbidden, "H7(7,6)은 금수가 아니어야 함"
    # J8, K5는 금수여야 함
    assert j8 in forbidden, "J8(9,7)은 금수여야 함"
    assert k5 in forbidden, "K5(10,4)는 금수여야 함"
    print("[사용자 제시 보드 상황: H7 금수 오탐 테스트] 통과!")

def test_user_case_c6_g4():
    print("\n[사용자 제시 보드 상황: C6, G4 금수 판정 테스트]")
    size = 15
    board = [[None]*size for _ in range(size)]
    # 백돌
    board[3][2] = 'W'  # C4
    board[4][2] = 'W'  # C5
    board[3][4] = 'W'  # E4
    board[3][5] = 'W'  # F4
    board[5][6] = 'W'  # G6
    board[6][6] = 'W'  # G7
    # 금수 판정 좌표
    c6 = (2,5)
    g4 = (6,3)
    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden, highlight_pos=c6))
    print(f"금수 좌표: {forbidden}")
    # C6는 금수여야 함
    assert c6 in forbidden, "C6(2,5)는 삼삼 금수여야 함"
    # G4는 금수가 아니어야 함
    assert g4 not in forbidden, "G4(6,3)는 삼삼 금수가 아니어야 함"
    print("[사용자 제시 보드 상황: C6, G4 금수 판정 테스트] 통과!")

def test_compare_custom_vs_ruleengine_double_three():
    print("\n[커스텀 vs rule_engine 삼삼 금수 판정 비교]")
    size = 15
    # 다양한 보드 케이스
    test_cases = []
    # 1. 십자형 삼삼
    board1 = [[None]*size for _ in range(size)]
    board1[7][6] = 'B'; board1[7][8] = 'B'; board1[6][7] = 'B'; board1[8][7] = 'B'
    test_cases.append((board1, 7, 7, 'B', True))
    # 2. 대각선 삼삼
    board2 = [[None]*size for _ in range(size)]
    board2[5][5] = 'B'; board2[7][7] = 'B'; board2[7][5] = 'B'; board2[5][7] = 'B'
    test_cases.append((board2, 6, 6, 'B', True))
    # 3. 한 칸 띄운 삼삼
    board3 = [[None]*size for _ in range(size)]
    board3[5][5] = 'B'; board3[6][6] = 'B'; board3[8][8] = 'B'; board3[8][6] = 'B'; board3[6][8] = 'B'
    test_cases.append((board3, 7, 7, 'B', True))
    # 4. 실제 게임 상황(금수 아님)
    board4 = [[None]*size for _ in range(size)]
    board4[3][3] = 'W'; board4[3][4] = 'W'; board4[6][8] = 'B'; board4[7][7] = 'B'
    test_cases.append((board4, 10, 4, 'B', False))
    test_cases.append((board4, 5, 9, 'B', False))
    # 5. 사용자 제시 H7 오탐 상황(삼사)
    board5 = [[None]*size for _ in range(size)]
    board5[3][4] = 'W'; board5[3][5] = 'W'; board5[4][2] = 'W'; board5[5][6] = 'W'; board5[6][6] = 'W'
    board5[6][8] = 'B'; board5[6][10] = 'B'; board5[7][7] = 'B'; board5[7][9] = 'B'; board5[9][7] = 'B'
    test_cases.append((board5, 7, 6, 'B', False)) # H7
    test_cases.append((board5, 9, 7, 'B', True))  # J8
    test_cases.append((board5, 10, 4, 'B', True)) # K5
    # 6. 사용자 제시 C6, G4 상황
    board6 = [[None]*size for _ in range(size)]
    board6[3][2] = 'W'; board6[4][2] = 'W'; board6[3][4] = 'W'; board6[3][5] = 'W'; board6[5][6] = 'W'; board6[6][6] = 'W'
    test_cases.append((board6, 2, 5, 'B', True))  # C6
    test_cases.append((board6, 6, 3, 'B', False)) # G4
    # 실제 비교
    for idx, (board, x, y, color, expected) in enumerate(test_cases):
        custom = is_forbidden_move_custom(board, x, y, color)
        engine = is_forbidden_move_engine(board, x, y, color)
        print(f"테스트 {idx+1}: ({x},{y}) → 커스텀={custom}, rule_engine={engine}, 기대={expected}")
        if custom != engine:
            print("[디버그] 판정 불일치! 내부 패턴/윈도우/인덱스 비교:")
            directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
            for dx, dy in directions:
                print(f"  방향 {(dx,dy)}:")
                print("    [커스텀]", end=' ')
                _debug_open_three(board, x, y, color, dx, dy)
                print("    [엔진]", end=' ')
                from games.omok.engine.rule_engine import is_open_three_in_direction as engine_open_three
                _debug_open_three_engine(board, x, y, color, dx, dy, engine_open_three)
        assert custom == engine, f"불일치: ({x},{y}) 커스텀={custom}, rule_engine={engine}"
        assert custom == expected, f"기대값 불일치: ({x},{y}) {custom} vs {expected}"
    print("[커스텀 vs rule_engine 삼삼 금수 판정 비교] 통과!")

def _debug_open_three(board, x, y, color, dx, dy):
    board_size = len(board)
    line = []
    for i in range(-4, 5):
        nx = x + i * dx
        ny = y + i * dy
        if 0 <= nx < board_size and 0 <= ny < board_size:
            line.append(board[ny][nx])
        else:
            line.append('EDGE')
    center = 4
    for start in range(len(line) - 4):
        if start <= center <= start + 4:
            window = line[start:start+5]
            patterns = [
                [None, color, color, color, None],
                [None, color, None, color, color],
                [None, color, color, None, color],
                [color, None, color, color, None],
                [color, color, None, color, None],
            ]
            for pattern in patterns:
                if match_pattern(window, pattern):
                    center_in_window = center - start
                    if pattern == [None, color, color, color, None]:
                        if center_in_window in [1,2,3] and window[center_in_window] == color:
                            if window[0] is None and window[4] is None:
                                print(f"윈도우={window} 패턴={pattern} 중심={center_in_window} → True", end='; ')
                    else:
                        if window[center_in_window] == color and window[0] is None and window[4] is None:
                            print(f"윈도우={window} 패턴={pattern} 중심={center_in_window} → True", end='; ')
    print()

def _debug_open_three_engine(board, x, y, color, dx, dy, engine_func):
    result = engine_func(board, x, y, color, dx, dy)
    print(f"엔진 결과: {result}")

def test_user_case_k5():
    print("\n[사용자 제시 보드 상황: K5 금수 판정 비교]")
    size = 15
    board = [[None]*size for _ in range(size)]
    # 백돌
    board[2][3] = 'W'  # D3
    board[3][4] = 'W'  # E4
    board[3][5] = 'W'  # F4
    board[4][3] = 'W'  # D5
    board[4][4] = 'W'  # E5
    board[5][3] = 'W'  # D6
    # 흑돌
    board[6][8] = 'B'  # I7
    board[6][10] = 'B' # K7
    board[7][7] = 'B'  # H8
    board[7][9] = 'B'  # J8
    # 금수 판정 좌표
    k5 = (10,4)
    custom = assert_double_three_equal(board, k5[0], k5[1], 'B')
    engine = rule_engine.is_double_three(board, k5[0], k5[1], 'B')
    print(f"K5(10,4) → 커스텀={custom}, rule_engine={engine}")
    assert custom == engine, f"K5(10,4) 판정 불일치: 커스텀={custom}, rule_engine={engine}"
    assert custom, "K5(10,4)는 삼삼 금수여야 함"
    print("[사용자 제시 보드 상황: K5 금수 판정 비교] 통과!")

def is_forbidden_move_custom(board, x, y, color):
    if board[y][x] is not None:
        return False
    temp_board = [row[:] for row in board]
    temp_board[y][x] = color
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    open_three_count = 0
    open_four_count = 0
    for dx, dy in directions:
        is_four = is_four_in_direction_custom(temp_board, x, y, color, dx, dy)
        if is_four:
            open_four_count += 1
        else:
            is_three = is_open_three_in_direction(temp_board, x, y, color, dx, dy)
            if is_three:
                open_three_count += 1
    if open_three_count >= 2:
        return True
    if open_four_count >= 2:
        return True
    return False

def is_forbidden_move_engine(board, x, y, color):
    return rule_engine.is_double_three(board, x, y, color) or rule_engine.is_double_four(board, x, y, color)

def test_samsa_not_forbidden():
    print("\n[삼사(삼삼+사사) 금수 오탐 방지 테스트]")
    size = 15
    board = [[None]*size for _ in range(size)]
    # 삼삼 패턴(가로)
    board[7][6] = 'B'
    board[7][8] = 'B'
    # 사사 패턴(세로)
    board[6][7] = 'B'
    board[8][7] = 'B'
    board[9][7] = 'B'
    samsa_pos = (7, 7)
    result_custom = is_forbidden_move_custom(board, samsa_pos[0], samsa_pos[1], 'B')
    result_engine = is_forbidden_move_engine(board, samsa_pos[0], samsa_pos[1], 'B')
    print(f"H8(7,7) 삼사 상황 → 커스텀 금수 판정: {result_custom}, 엔진 금수 판정: {result_engine}")
    assert not result_custom, "삼사(삼삼+사사)는 금수로 판정되면 안 됩니다 (커스텀)"
    assert not result_engine, "삼사(삼삼+사사)는 금수로 판정되면 안 됩니다 (엔진)"
    print("[삼사(삼삼+사사) 금수 오탐 방지 테스트] 통과!")

if __name__ == "__main__":
    test_compare_custom_vs_ruleengine_double_three()
    test_double_three_patterns()
    test_non_double_three()
    test_custom_case()
    test_custom_case_2()
    test_custom_case_3()
    test_various_double_four_patterns()
    test_various_double_four_patterns_custom()
    test_specific_double_three_case()
    test_user_case_h7()
    test_user_case_c6_g4()
    test_user_case_k5()
    test_samsa_not_forbidden()
    
    # 전체 보드 금수 검사 예제
    print("\n" + "=" * 50)
    print("전체 보드 금수 검사")
    print("=" * 50)
    
    board = [[None]*15 for _ in range(15)]
    board[3][3] = 'W'  # D4
    board[3][4] = 'W'  # E4
    board[6][8] = 'B'  # I7
    board[7][7] = 'B'  # H8
    
    forbidden = get_double_three_forbidden_points(board, 'B')
    print(visualize_board(board, forbidden))
    print(f"삼삼 금수 위치: {forbidden}")
    
    for x, y in forbidden:
        print(f"  {chr(65+x)}{y+1} ({x},{y})")
        
        
        