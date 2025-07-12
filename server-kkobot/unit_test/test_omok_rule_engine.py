from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board
from games.omok.engine.rule_engine import (
    is_double_three, is_double_four, is_overline,
    print_board_log, get_double_three_forbidden_points
)

def visualize_board(board, highlight_pos=None):
    """보드를 시각화하여 출력"""
    result = []
    result.append("    A B C D E F G H I J K L M N O")
    result.append("   --------------------------------")
    
    for y in range(15):
        row_str = f"{y+1:2d} |"
        for x in range(15):
            cell = board[y][x]
            if (x, y) == highlight_pos:
                cell_str = '[O]' if cell is None else f'[{cell}]'
            else:
                cell_str = ' . ' if cell is None else f' {cell} '
            row_str += cell_str
        result.append(row_str)
    return '\n'.join(result)

def test_double_three():
    """삼삼 테스트"""
    print("=" * 50)
    print("삼삼(Double Three) 테스트")
    print("=" * 50)
    
    # 테스트 1: 기본 삼삼 (가로 + 세로)
    print("\n[테스트 1] 기본 삼삼 (가로 + 세로)")
    board1 = [[None]*15 for _ in range(15)]
    board1[6][6] = 'B'  # 중심에서 가로
    board1[6][8] = 'B'
    board1[7][7] = 'B'  # 중심에서 세로
    board1[8][7] = 'B'
    
    print(visualize_board(board1, highlight_pos=(7, 7)))
    is_33 = is_double_three(board1, 7, 7, 'B')
    print(f"H8에 흑 착수 시 삼삼? {is_33}")
    
    # 테스트 2: 띄어진 삼삼
    print("\n[테스트 2] 띄어진 삼삼")
    board2 = [[None]*15 for _ in range(15)]
    board2[6][8] = 'B'  # I7
    board2[6][10] = 'B'  # K7
    board2[7][7] = 'B'  # H8
    board2[7][10] = 'B'  # K8
    
    print(visualize_board(board2, highlight_pos=(10, 4)))
    is_33 = is_double_three(board2, 10, 4, 'B')  # K5
    print(f"K5에 흑 착수 시 삼삼? {is_33}")
    
    # 테스트 3: 삼삼이 아닌 경우 (막힌 3)
    print("\n[테스트 3] 삼삼이 아닌 경우 (막힌 3)")
    board3 = [[None]*15 for _ in range(15)]
    board3[6][5] = 'W'  # 백돌로 막음
    board3[6][6] = 'B'
    board3[6][8] = 'B'
    board3[7][7] = 'B'
    board3[8][7] = 'B'
    
    print(visualize_board(board3, highlight_pos=(7, 7)))
    is_33 = is_double_three(board3, 7, 7, 'B')
    print(f"H8에 흑 착수 시 삼삼? {is_33}")

def test_double_four():
    """사사 테스트"""
    print("\n" + "=" * 50)
    print("사사(Double Four) 테스트")
    print("=" * 50)
    
    # 테스트 1: 기본 사사
    print("\n[테스트 1] 기본 사사 (가로 + 세로)")
    board1 = [[None]*15 for _ in range(15)]
    # 가로 4
    board1[6][5] = 'W'
    board1[6][6] = 'W'
    board1[6][7] = 'W'
    # 세로 4
    board1[5][7] = 'W'
    board1[7][7] = 'W'
    board1[8][7] = 'W'
    
    print(visualize_board(board1, highlight_pos=(4, 6)))
    is_44 = is_double_four(board1, 4, 6, 'W')  # E7
    print(f"E7에 백 착수 시 사사? {is_44}")
    
    # 테스트 2: 삼사가 아닌 경우 (막힌 4)
    print("\n[테스트 2] 사사가 아닌 경우 (막힌 4)")
    board2 = [[None]*15 for _ in range(15)]
    board2[6][4] = 'B'  # 흑돌로 막음
    board2[6][5] = 'W'
    board2[6][6] = 'W'
    board2[6][7] = 'W'
    board2[5][7] = 'W'
    board2[7][7] = 'W'
    board2[8][7] = 'W'
    
    print(visualize_board(board2, highlight_pos=(4, 6)))
    is_44 = is_double_four(board2, 4, 6, 'W')
    print(f"E7에 백 착수 시 사사? {is_44}")

def test_overline():
    """장목 테스트"""
    print("\n" + "=" * 50)
    print("장목(Overline) 테스트")
    print("=" * 50)
    
    # 테스트 1: 장목 (6목)
    print("\n[테스트 1] 장목 (6목)")
    board1 = [[None]*15 for _ in range(15)]
    board1[7][5] = 'B'
    board1[7][6] = 'B'
    board1[7][7] = 'B'
    board1[7][8] = 'B'
    board1[7][9] = 'B'
    
    print(visualize_board(board1, highlight_pos=(10, 7)))
    is_ol = is_overline(board1, 10, 7, 'B')  # K8
    print(f"K8에 흑 착수 시 장목? {is_ol}")
    
    # 테스트 2: 장목이 아닌 경우 (5목)
    print("\n[테스트 2] 장목이 아닌 경우 (정확히 5목)")
    board2 = [[None]*15 for _ in range(15)]
    board2[7][5] = 'B'
    board2[7][6] = 'B'
    board2[7][7] = 'B'
    board2[7][8] = 'B'
    
    print(visualize_board(board2, highlight_pos=(9, 7)))
    is_ol = is_overline(board2, 9, 7, 'B')  # J8
    print(f"J8에 흑 착수 시 장목? {is_ol}")

def test_specific_case():
    """특정 케이스 테스트"""
    print("\n" + "=" * 50)
    print("특정 케이스 테스트")
    print("=" * 50)
    
    # 문제가 되었던 케이스 재현
    print("\n[문제 케이스] 흑 K5 삼삼 금수")
    board = [[None]*15 for _ in range(15)]
    board[3][3] = 'W'  # D4
    board[3][4] = 'W'  # E4
    board[6][8] = 'B'  # I7
    board[7][7] = 'B'  # H8
    
    print(visualize_board(board, highlight_pos=(10, 4)))
    print_board_log(board, prefix="테스트 보드:")
    
    is_33 = is_double_three(board, 10, 4, 'B')  # K5
    print(f"K5에 흑 착수 시 삼삼? {is_33}")
    
    # 더 자세한 분석
    print("\n[중간 단계 확인]")
    board[10][4] = 'B'  # K5에 착수
    print(visualize_board(board))
    
    print("\n- I7-J7-K7 패턴 확인")
    print(f"  I7: {board[6][8]}, J7: {board[6][9]}, K7: {board[6][10]}")
    
    print("\n- K5-K6-K7 패턴 확인") 
    print(f"  K5: {board[4][10]}, K6: {board[5][10]}, K7: {board[6][10]}")

def test_game_scenario():
    """실제 게임 시나리오 테스트"""
    print("\n" + "=" * 50)
    print("실제 게임 시나리오 테스트")
    print("=" * 50)
    
    # 게임 보드 상태 재현
    board = [[None]*15 for _ in range(15)]
    board[3][3] = 'W'  # D4
    board[3][4] = 'W'  # E4
    board[6][8] = 'B'  # I7
    board[7][7] = 'B'  # H8
    
    print("\n현재 보드 상태:")
    print(visualize_board(board))
    
    # F10 금수 체크
    print("\n[F10(5,9) 금수 체크]")
    is_33_f10 = is_double_three(board, 5, 9, 'B')
    print(f"F10에 흑 착수 시 삼삼? {is_33_f10}")
    
    # K5 금수 체크
    print("\n[K5(10,4) 금수 체크]")
    is_33_k5 = is_double_three(board, 10, 4, 'B')
    print(f"K5에 흑 착수 시 삼삼? {is_33_k5}")
    
    # 모든 빈 칸에 대해 삼삼 체크
    print("\n[모든 빈 칸 삼삼 체크]")
    forbidden_positions = []
    for y in range(15):
        for x in range(15):
            if board[y][x] is None:
                if is_double_three(board, x, y, 'B'):
                    forbidden_positions.append((x, y))
    
    print(f"삼삼 금수 위치들: {forbidden_positions}")
    for pos in forbidden_positions:
        x, y = pos
        col = chr(ord('A') + x)
        print(f"  {col}{y+1} ({x},{y})")

def test_false_positive_double_three():
    """
    K5, F10 위치에 흑이 착수할 때 금수로 잘못 판정되는지 확인
    """
    board = [[None]*15 for _ in range(15)]
    # 예시 상황: 실제 로그와 동일하게 판을 구성
    # ... 필요한 위치에 돌을 놓음 ...
    # 예: board[y][x] = 'B' 또는 'W'
    board[3][3] = 'W'  # D4
    board[3][4] = 'W'  # E4
    board[6][8] = 'B'  # I7
    board[7][7] = 'B'  # H8
    # K5 = (10,4), F10 = (5,9)
    assert not is_double_three(board, 10, 4, 'B'), "K5는 금수가 아니어야 함"
    assert not is_double_three(board, 5, 9, 'B'), "F10은 금수가 아니어야 함"

def test_various_double_three_patterns():
    """다양한 삼삼(이중삼, 한 칸 띄운 삼삼, 단일 삼, 금수 아님 등) 상황 자동 검증"""
    from games.omok.engine.rule_engine import is_double_three
    size = 15
    # 1. 이중삼(정상 금수) - K5만 금수
    board1 = [[None]*size for _ in range(size)]
    board1[3][3] = 'W'
    board1[3][4] = 'W'
    board1[5][5] = 'W'
    board1[6][5] = 'W'
    board1[6][8] = 'B'
    board1[6][9] = 'B'
    board1[7][7] = 'B'
    board1[7][10] = 'B'
    forbidden1 = [(x, y) for y in range(size) for x in range(size) if is_double_three(board1, x, y, 'B')]
    print("[이중삼] 금수 위치:", forbidden1)
    assert forbidden1 == [(10, 4)], f"K5(10,4)만 금수여야 함, 실제: {forbidden1}"

    # 2. 한 칸 띄운 삼삼(정상 금수) - 중앙만 금수
    board2 = [[None]*size for _ in range(size)]
    board2[7][6] = 'B'
    board2[7][8] = 'B'
    board2[6][7] = 'B'
    board2[8][7] = 'B'
    forbidden2 = [(x, y) for y in range(size) for x in range(size) if is_double_three(board2, x, y, 'B')]
    print("[한 칸 띄운 삼삼] 금수 위치:", forbidden2)
    assert (7, 7) in forbidden2, f"중앙(7,7)만 금수여야 함, 실제: {forbidden2}"

    # 3. 단일 삼(금수 아님)
    board3 = [[None]*size for _ in range(size)]
    board3[7][6] = 'B'
    board3[7][7] = 'B'
    forbidden3 = [(x, y) for y in range(size) for x in range(size) if is_double_three(board3, x, y, 'B')]
    print("[단일 삼] 금수 위치:", forbidden3)
    assert forbidden3 == [], f"단일 삼은 금수 없어야 함, 실제: {forbidden3}"

    # 4. 막힌 삼(금수 아님)
    board4 = [[None]*size for _ in range(size)]
    board4[7][6] = 'B'
    board4[7][7] = 'B'
    board4[7][5] = 'W'  # 막힘
    forbidden4 = [(x, y) for y in range(size) for x in range(size) if is_double_three(board4, x, y, 'B')]
    print("[막힌 삼] 금수 위치:", forbidden4)
    assert forbidden4 == [], f"막힌 삼은 금수 없어야 함, 실제: {forbidden4}"

    # 5. 금수 아님(빈 보드)
    board5 = [[None]*size for _ in range(size)]
    forbidden5 = [(x, y) for y in range(size) for x in range(size) if is_double_three(board5, x, y, 'B')]
    print("[빈 보드] 금수 위치:", forbidden5)
    assert forbidden5 == [], f"빈 보드는 금수 없어야 함, 실제: {forbidden5}"

def test_get_double_three_forbidden_points():
    """get_double_three_forbidden_points 함수의 다양한 삼삼 금수 케이스 자동 검증"""
    from games.omok.engine.rule_engine import get_double_three_forbidden_points
    size = 15
    # 1. 이중삼(정상 금수) - K5만 금수
    board1 = [[None]*size for _ in range(size)]
    board1[3][3] = 'W'
    board1[3][4] = 'W'
    board1[5][5] = 'W'
    board1[6][5] = 'W'
    board1[6][8] = 'B'
    board1[6][9] = 'B'
    board1[7][7] = 'B'
    board1[7][10] = 'B'
    forbidden1 = get_double_three_forbidden_points(board1, 'B')
    print("[get_double_three_forbidden_points] 금수 위치:", sorted(forbidden1))
    assert (10, 4) in forbidden1 and len(forbidden1) == 1, f"K5(10,4)만 금수여야 함, 실제: {forbidden1}"

    # 2. 한 칸 띄운 삼삼(정상 금수) - 중앙만 금수
    board2 = [[None]*size for _ in range(size)]
    board2[7][6] = 'B'
    board2[7][8] = 'B'
    board2[6][7] = 'B'
    board2[8][7] = 'B'
    forbidden2 = get_double_three_forbidden_points(board2, 'B')
    print("[get_double_three_forbidden_points] 금수 위치(한 칸 띄운):", sorted(forbidden2))
    assert (7, 7) in forbidden2 and len(forbidden2) == 1, f"중앙(7,7)만 금수여야 함, 실제: {forbidden2}"

    # 3. 단일 삼(금수 아님)
    board3 = [[None]*size for _ in range(size)]
    board3[7][6] = 'B'
    board3[7][8] = 'B'
    forbidden3 = get_double_three_forbidden_points(board3, 'B')
    print("[get_double_three_forbidden_points] 금수 위치(단일삼):", sorted(forbidden3))
    assert forbidden3 == [] or forbidden3 is None, f"단일삼은 금수 없어야 함, 실제: {forbidden3}"

    print("get_double_three_forbidden_points 테스트 통과!")

# main에 추가
if __name__ == "__main__":
    # 기존 테스트들...
    
    # 실제 게임 시나리오 테스트
    test_game_scenario()
    test_various_double_three_patterns()
    test_get_double_three_forbidden_points()