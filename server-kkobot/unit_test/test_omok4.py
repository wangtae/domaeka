from games.omok.engine.rule_engine import is_double_four

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

def is_four_anywhere_custom(board, x, y, color, dx, dy):
    board_size = len(board)
    found = False
    for window_size in [5, 6]:
        for offset in range(-(window_size - 1), 1):
            positions = [(x + (offset + i) * dx, y + (offset + i) * dy) for i in range(window_size)]
            if not all(0 <= px < board_size and 0 <= py < board_size for px, py in positions):
                continue
            if (x, y) not in positions:
                continue
            window = [board[py][px] for px, py in positions]
            move_idx = positions.index((x, y))
            # 5칸 윈도우: 연속 4개 + 빈칸 1개(위치 불문)
            if window_size == 5:
                if window.count(color) == 4 and window.count(None) == 1:
                    for idx in range(5):
                        if window[idx] is None and move_idx == idx:
                            test = window[:]
                            test[idx] = color
                            if test.count(color) == 5:
                                continue  # 5목 완성은 금수 아님
                            found = True
                # 한 칸 띄운 4목(비연속, 빈칸 2개, color 3개)
                if window.count(color) == 3 and window.count(None) == 2:
                    idxs = [i for i, v in enumerate(window) if v is None]
                    for idx in idxs:
                        if move_idx == idx:
                            test = window[:]
                            test[idx] = color
                            if test.count(color) == 4 and test.count(None) == 1:
                                found = True
            # 6칸 윈도우: 열린4(양끝 빈칸, 4개 연속)
            if window_size == 6:
                for i in range(3):
                    segment = window[i:i+4]
                    if all(stone == color for stone in segment):
                        left = window[i-1] if i-1 >= 0 else 'EDGE'
                        right = window[i+4] if i+4 < 6 else 'EDGE'
                        if (left is None or right is None) and (i <= move_idx < i+4):
                            found = True
                # 한 칸 띄운 4목(비연속, 빈칸 2개, color 3개)
                for i in range(3):
                    segment = window[i:i+4]
                    if segment.count(color) == 3 and segment.count(None) == 1:
                        idxs = [j for j in range(4) if segment[j] is None]
                        for idx in idxs:
                            if move_idx == i+idx:
                                test = segment[:]
                                test[idx] = color
                                if test.count(color) == 4:
                                    found = True
    return found

def extract_line(board, x, y, dx, dy):
    size = len(board)
    line = []
    px, py = x, y
    # (x, y)에서 반대 방향으로 이동
    while 0 <= px < size and 0 <= py < size:
        px -= dx
        py -= dy
    px += dx
    py += dy
    # (x, y)에서 정방향으로 이동하며 라인 추출
    while 0 <= px < size and 0 <= py < size:
        line.append(board[py][px])
        px += dx
        py += dy
    return line

def extract_line_with_center(board, x, y, dx, dy):
    line = extract_line(board, x, y, dx, dy)
    center = None
    for idx in range(len(line)):
        if (x, y) == get_coord_from_line(board, x, y, dx, dy, idx):
            center = idx
            break
    return line, center

def get_coord_from_line(board, x, y, dx, dy, idx):
    # (x, y)에서 반대 방향으로 idx만큼 이동한 좌표를 반환
    size = len(board)
    px, py = x, y
    # (x, y)에서 반대 방향으로 이동
    while 0 <= px < size and 0 <= py < size:
        px -= dx
        py -= dy
    px += dx
    py += dy
    px += dx * idx
    py += dy * idx
    return (px, py)

def is_open_four_pattern(window, move_idx, color):
    # 착수 후 연속4가 만들어지고, 양끝이 비어있으면 열린4로 간주
    test = window[:]
    test[move_idx] = color
    for i in range(len(test) - 3):
        segment = test[i:i+4]
        if all(stone == color for stone in segment):
            left = test[i-1] if i-1 >= 0 else 'EDGE'
            right = test[i+4] if i+4 < len(test) else 'EDGE'
            # 열린4: 양끝이 모두 None
            if left is None and right is None:
                return True
            # 5칸 윈도우에서는 한쪽만 열려도 인정
            if len(test) == 5 and (left is None or right is None):
                return True
    return False

def is_broken_four_pattern(window, move_idx, color):
    # 착수 후 한 칸 띄운 4목(비연속)이 만들어지는지 검사
    test = window[:]
    test[move_idx] = color
    for i in range(len(test) - 4):
        segment = test[i:i+5]
        # 4개 + 빈칸 1개(중간에)
        if segment.count(color) == 4 and segment.count(None) == 1:
            idxs = [j for j, v in enumerate(segment) if v is None]
            for idx in idxs:
                # 빈칸이 양끝이 아니고, 착수 위치가 4목에 포함
                if 0 < idx < 4 and (i <= move_idx < i+5):
                    return True
    return False

def count_fours(board, x, y, color, dx, dy):
    # 착수 후 라인
    line, center = extract_line_with_center(board, x, y, dx, dy)
    line = line[:]
    line[center] = color
    size = len(line)
    count = 0
    for i in range(center - 4, center + 1):
        if i < 0 or i + 5 > size:
            continue
        window = line[i:i+5]
        move_idx = center - i
        if window[move_idx] != color:
            continue
        if window.count(color) == 4 and window.count(None) == 1:
            # 5목이 되는 경우는 제외
            test = window[:]
            test[test.index(None)] = color
            if test.count(color) == 5:
                continue
            count += 1
    return count

def is_double_four_custom(board, x, y, color):
    return is_double_four(board, x, y, color)

def is_double_four(board, x, y, color, as_prompt=False):
    if as_prompt:
        return (
            "사사 금수란, 한 수로 4를 두 개 이상 동시에 만드는 경우를 말합니다. "
            "4는 다음 수에 5를 만들 수 있는 4목을 의미하며, 열린 4와 한쪽만 막힌 4를 모두 포함합니다."
        )
    if board[y][x] is not None:
        return False
    temp_board = [row[:] for row in board]
    temp_board[y][x] = color
    if check_five_in_a_row(temp_board, x, y, color):
        return False
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    four_count = 0
    debug_counts = []
    for dx, dy in directions:
        cnt = count_fours_in_direction(temp_board, x, y, color, dx, dy)
        debug_counts.append(cnt)
        four_count += cnt
    print(f"[DEBUG] (x={x}, y={y}, color={color}) 방향별 4목 개수: {debug_counts} → 합계: {four_count}")
    return four_count >= 2

def count_fours_in_direction(board, x, y, color, dx, dy):
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
    count = 0
    # 5칸 윈도우
    for start in range(len(line) - 4):
        window = line[start:start+5]
        move_idx = center - start
        if not (0 <= move_idx < 5):
            continue
        if window[move_idx] is not None:
            continue
        test = window[:]
        test[move_idx] = color
        # 연속4(착수 위치 포함)
        for i in range(2):
            if all(stone == color for stone in test[i:i+4]) and (i <= move_idx < i+4):
                # 5목이 되는 경우는 제외
                test2 = test[:]
                if None in test2:
                    test2[test2.index(None)] = color
                    if test2.count(color) == 5:
                        continue
                count += 1
        # 한 칸 띄운4(비연속, 착수 위치 포함)
        for i in range(2):
            seg = test[i:i+4]
            if seg.count(color) == 3 and seg.count(None) == 1:
                rel_idx = move_idx - i
                if 0 <= rel_idx < 4 and seg[rel_idx] is None:
                    test2 = seg[:]
                    test2[rel_idx] = color
                    if test2.count(color) == 4:
                        count += 1
    # 6칸 윈도우
    for start in range(len(line) - 5):
        window = line[start:start+6]
        move_idx = center - start
        if not (0 <= move_idx < 6):
            continue
        if window[move_idx] is not None:
            continue
        test = window[:]
        test[move_idx] = color
        # 연속4(착수 위치 포함)
        for i in range(3):
            if all(stone == color for stone in test[i:i+4]) and (i <= move_idx < i+4):
                # 5목이 되는 경우는 제외
                test2 = test[:]
                if None in test2:
                    test2[test2.index(None)] = color
                    if test2.count(color) == 5:
                        continue
                count += 1
        # 한 칸 띄운4(비연속, 착수 위치 포함)
        for i in range(3):
            seg = test[i:i+4]
            if seg.count(color) == 3 and seg.count(None) == 1:
                rel_idx = move_idx - i
                if 0 <= rel_idx < 4 and seg[rel_idx] is None:
                    test2 = seg[:]
                    test2[rel_idx] = color
                    if test2.count(color) == 4:
                        count += 1
    return count

def count_four_patterns(window, center_idx, color):
    cnt = 0
    color_count = window.count(color)
    for i in range(3):
        if window[i:i+4].count(color) == 4:
            if i <= center_idx < i + 4:
                left = window[i-1] if i > 0 else 'EDGE'
                right = window[i+4] if i+4 < len(window) else 'EDGE'
                if left is None or right is None:
                    cnt += 1
    if color_count == 4 and window.count(None) >= 1:
        for i in range(2):
            segment = window[i:i+5]
            if segment.count(color) == 4 and segment.count(None) == 1:
                if i <= center_idx < i + 5:
                    empty_idx = segment.index(None)
                    test_segment = segment[:]
                    test_segment[empty_idx] = color
                    if test_segment.count(color) == 5:
                        cnt += 1
    return cnt

def count_four_patterns_five_window(window, center_idx, color):
    cnt = 0
    color_count = window.count(color)
    if color_count == 4 and window.count(None) == 1:
        empty_idx = window.index(None)
        test_window = window[:]
        test_window[empty_idx] = color
        if test_window.count(color) == 5:
            cnt += 1
    return cnt

def check_five_in_a_row(board, x, y, color):
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

def visualize_board_simple(board, highlight=None):
    size = len(board)
    s = ''
    for y in range(size):
        for x in range(size):
            if highlight and (x, y) == highlight:
                s += '[X]'
            elif board[y][x] == 'B':
                s += ' B '
            elif board[y][x] == 'W':
                s += ' W '
            else:
                s += ' . '
        s += f' {y+1}\n'
    return s

def test_double_four_cases():
    print("\n[사사(이중사) 금수 판정 테스트]")
    size = 15
    test_cases = []
    # 1. 가로+세로 사사
    board1 = [[None]*size for _ in range(size)]
    board1[7][6] = 'B'; board1[7][8] = 'B'; board1[7][9] = 'B'; board1[6][7] = 'B'; board1[8][7] = 'B'; board1[9][7] = 'B'
    test_cases.append((board1, 7, 7, 'B', True, "가로+세로 사사"))
    # 2. 대각선+가로 사사
    board2 = [[None]*size for _ in range(size)]
    board2[5][5] = 'B'; board2[6][6] = 'B'; board2[8][8] = 'B'; board2[7][5] = 'B'; board2[7][6] = 'B'; board2[7][8] = 'B'
    test_cases.append((board2, 7, 7, 'B', True, "대각선+가로 사사"))
    # 3. 한쪽이 막힌 4 (금수 아님)
    board3 = [[None]*size for _ in range(size)]
    board3[7][5] = 'W'; board3[7][6] = 'B'; board3[7][7] = 'B'; board3[7][8] = 'B'
    test_cases.append((board3, 7, 7, 'B', False, "한쪽이 막힌 4"))
    # 4. 한 칸 띄운 사사
    board4 = [[None]*size for _ in range(size)]
    board4[7][5] = 'B'; board4[7][7] = 'B'; board4[7][9] = 'B'; board4[6][6] = 'B'; board4[8][8] = 'B'
    test_cases.append((board4, 7, 7, 'B', False, "한 칸 띄운 사사"))
    # 5. 5목이 되는 경우(금수 아님)
    board5 = [[None]*size for _ in range(size)]
    board5[7][5] = 'B'; board5[7][6] = 'B'; board5[7][7] = 'B'; board5[7][8] = 'B'
    test_cases.append((board5, 7, 9, 'B', False, "5목 완성"))
    # 6. 빈 보드(금수 없음)
    board6 = [[None]*size for _ in range(size)]
    for y in range(size):
        for x in range(size):
            test_cases.append((board6, x, y, 'B', False, f"빈 보드 ({x},{y})"))
    # 7. 사용자 제시 G7(6,6) 사사 금수 케이스
    board7 = [[None]*size for _ in range(size)]
    # ......... (0~5)
    # ......WW..... (6)
    board7[6][4] = 'W'; board7[6][5] = 'W'
    # .....BBBW.... (7)
    board7[7][5] = 'B'; board7[7][6] = 'B'; board7[7][7] = 'B'; board7[7][8] = 'W'
    # .....WBB....... (8)
    board7[8][5] = 'W'; board7[8][6] = 'B'; board7[8][7] = 'B'
    # .....BB.W...... (9)
    board7[9][5] = 'B'; board7[9][6] = 'B'; board7[9][8] = 'W'
    # ....WBBBWW..... (10)
    board7[10][4] = 'W'; board7[10][5] = 'B'; board7[10][6] = 'B'; board7[10][7] = 'B'; board7[10][8] = 'W'; board7[10][9] = 'W'
    # ....W.W........ (11)
    board7[11][4] = 'W'; board7[11][6] = 'W'
    test_cases.append((board7, 6, 6, 'B', True, "사용자 G7(6,6) 사사 금수"))
    # 실제 테스트
    for idx, (board, x, y, color, expected, desc) in enumerate(test_cases):
        custom = is_double_four_custom(board, x, y, color)
        engine = is_double_four(board, x, y, color)
        if desc == "사용자 G7(6,6) 사사 금수":
            print("[G7(6,6) 보드 시각화 및 커스텀 판정 결과]")
            print(visualize_board_simple(board, highlight=(6,6)))
            print(f"커스텀 함수 판정: {custom}")
        if desc == "한 칸 띄운 사사" and (x, y) == (7, 7):
            print("[디버그] 한 칸 띄운 사사 (7,7) 보드 상태 (착수 전):")
            print(visualize_board_simple(board, highlight=(7,7)))
            temp_board = [row[:] for row in board]
            temp_board[7][7] = 'B'
            print("[디버그] 한 칸 띄운 사사 (7,7) 보드 상태 (착수 후):")
            print(visualize_board_simple(temp_board, highlight=(7,7)))
            print(f"[디버그] four_count (7,7): {custom}")
        print(f"[디버그] custom={custom}, expected={expected}, desc={desc}, (x,y)=({x},{y})")
        assert custom == engine, f"[불일치] {desc} ({x},{y}) 커스텀={custom}, 이식={engine}"
        assert custom == expected, f"[기대값 불일치] {desc} ({x},{y}) 결과: {custom}, 기대: {expected}"
    print("[사사(이중사) 금수 판정 테스트] 통과!")

if __name__ == "__main__":
    test_double_four_cases() 