from core.logger import logger
from games.omok.constants import RULE_ELEMENTS, RULE_DISPLAY_NAMES, RULE_VALUE_DISPLAY_FORMATS
import copy
from games.omok.utils.restrict_area import get_restricted_area
from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board
from copy import deepcopy

def check_five_in_a_row(board, x, y, player_piece):
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    board_size = len(board)

    for dx, dy in directions:
        count = 1
        for dir in [1, -1]:
            nx, ny = x, y
            while True:
                nx += dx * dir
                ny += dy * dir
                if 0 <= nx < board_size and 0 <= ny < board_size and board[ny][nx] == player_piece:
                    count += 1
                else:
                    break
        if count >= 5:
            return True
    return False

def match_pattern(window, pattern):
    if len(window) != len(pattern):
        return False
    for i in range(len(pattern)):
        if pattern[i] is not None and window[i] != pattern[i]:
            return False
    return True

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

def is_open_four_in_window(window, color, move_idx=None):
    """
    6칸 윈도우에서 사(4목) 패턴 확인 (양쪽이 열렸든 막혔든, 다음 수로 5목 완성 가능)
    move_idx: 착수 위치 인덱스(없으면 모든 위치 검사)
    """
    if len(window) != 6:
        return False
    # 연속된 4개: [*, B, B, B, B, *] (여기서 *은 None, 상대돌, EDGE 모두 허용)
    for i in range(3):
        segment = window[i:i+4]
        if all(stone == color for stone in segment):
            left = window[i-1] if i-1 >= 0 else 'EDGE'
            right = window[i+4] if i+4 < 6 else 'EDGE'
            # 양끝 중 하나라도 빈칸이면(착수 가능)
            if left is None or right is None:
                # 착수 위치가 4목의 일부인 경우만 인정
                if move_idx is None or (i <= move_idx < i+4):
                    return True
    # 한 칸 띄운 4목(비연속, 다음 수로 5목 완성 가능)
    for i in range(2):
        segment = window[i:i+5]
        if segment.count(color) == 4 and segment.count(None) == 1:
            idxs = [j for j in range(5) if segment[j] is None]
            for idx in idxs:
                test = segment[:]
                test[idx] = color
                if all(stone == color for stone in test):
                    # 착수 위치가 4목의 일부(원래 color)인 경우만 인정
                    if move_idx is not None and (i <= move_idx < i+5) and segment[move_idx-i] == color:
                        return True
    return False

def matches_pattern(window, pattern, color):
    """
    윈도우가 패턴과 일치하는지 확인
    """
    for i in range(len(window)):
        if pattern[i] == color:
            if window[i] != color:
                return False
        elif pattern[i] == None:
            if window[i] is not None:
                return False
    return True

def check_open_three_pattern(pattern, color):
    """
    열린 3 패턴인지 확인
    - 연속된 3개의 돌
    - 한 칸 띄고 2개 + 1개 (B_BB, BB_B 등)
    """
    if len(pattern) != 3:
        return False
    
    color_count = pattern.count(color)
    none_count = pattern.count(None)
    
    # 케이스 1: 연속된 3개 (BBB)
    if color_count == 3 and none_count == 0:
        return True
    
    # 케이스 2: 한 칸 띄고 (B_B나 다른 형태는 이미 양쪽이 열려있음이 보장됨)
    # 이 경우는 실제로는 5칸을 봐야 정확하므로 다른 방식으로 접근 필요
    
    return False

def check_open_four_pattern(pattern, color):
    """
    열린 4 패턴인지 확인
    """
    if len(pattern) != 4:
        return False
    
    color_count = pattern.count(color)
    none_count = pattern.count(None)
    
    # 연속된 4개
    if color_count == 4 and none_count == 0:
        return True
    
    return False

def is_continuous_pattern(pattern, color, length):
    """
    패턴이 연속된 length개의 color를 포함하는지 확인
    """
    continuous_count = 0
    for value in pattern:
        if value == color:
            continuous_count += 1
            if continuous_count == length:
                return True
        else:
            continuous_count = 0
    return False

def count_open_threes(board, x, y, color):
    """
    (x, y)에 착수했을 때, 해당 위치가 열린삼 패턴의 일부(중심)인 경우만 카운트
    """
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    count = 0
    for dx, dy in directions:
        for window_size in [5, 6]:
            for start_offset in range(-(window_size - 1), 1):
                positions = [(x + (start_offset + i) * dx, y + (start_offset + i) * dy) for i in range(window_size)]
                if not all(0 <= px < board_size and 0 <= py < board_size for px, py in positions):
                    continue
                if (x, y) not in positions:
                    continue
                window = [board[py][px] for px, py in positions]
                move_idx = positions.index((x, y))
                if is_open_three_in_direction(board, x, y, color, dx, dy):
                    count += 1
    return count

def count_open_threes_all(board, color):
    """
    보드 전체에서 열린삼 패턴 개수(착수 위치 불문, 중복 포함)
    """
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    count = 0
    for y in range(board_size):
        for x in range(board_size):
            if board[y][x] != color:
                continue
            for dx, dy in directions:
                for window_size in [5, 6]:
                    for start_offset in range(-(window_size - 1), 1):
                        positions = [(x + (start_offset + i) * dx, y + (start_offset + i) * dy) for i in range(window_size)]
                        if not all(0 <= px < board_size and 0 <= py < board_size for px, py in positions):
                            continue
                        window = [board[py][px] for px, py in positions]
                        move_idx = positions.index((x, y))
                        if is_open_three_in_direction(board, x, y, color, dx, dy):
                            count += 1
    return count

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
    open_four_count = 0
    for dx, dy in directions:
        is_four = is_four_in_direction(temp_board, x, y, color, dx, dy)
        if is_four:
            open_four_count += 1
        else:
            is_three = is_open_three_in_direction(temp_board, x, y, color, dx, dy)
            if is_three:
                open_three_count += 1
    # 삼삼 2개 이상만 True, 삼삼 1개 + 사사 1개(삼사)는 False
    return open_three_count >= 2

def is_five_in_a_row(board, x, y, color):
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

def is_four_in_direction(board, x, y, color, dx, dy):
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

def is_double_four(board, x, y, color):
    if board[y][x] is not None:
        return False
    temp_board = [row[:] for row in board]
    temp_board[y][x] = color
    if is_five_in_a_row(temp_board, x, y, color):
        return False
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    open_four_count = 0
    open_three_count = 0
    for dx, dy in directions:
        is_four = is_four_in_direction(temp_board, x, y, color, dx, dy)
        if is_four:
            open_four_count += 1
        else:
            is_three = is_open_three_in_direction(temp_board, x, y, color, dx, dy)
            if is_three:
                open_three_count += 1
    # 사사 2개 이상만 True, 삼삼 1개 + 사사 1개(삼사)는 False
    return open_four_count >= 2

def is_overline(board, x, y, color, as_prompt=False):
    """
    장목(Overline) 판별 함수
    """
    prompt = (
        "장목(Overline)이란, 한 줄에 6목 이상을 만드는 경우를 말합니다. "
        "일부 룰에서는 흑이 장목을 만들면 금수로 간주합니다."
    )
    if as_prompt:
        return prompt
    
    norm_board = normalize_board(board)
    temp_board = copy.deepcopy(norm_board)
    
    if temp_board[y][x] is not None:
        return False
    
    normalized_color = normalize_color(color)
    temp_board[y][x] = normalized_color
    return has_n_or_more_in_a_row(temp_board, x, y, normalized_color, 6)

def has_n_or_more_in_a_row(board, x, y, color, n):
    """
    (x, y)에 착수했을 때 4방향 중 한 방향이라도 n개 이상 연속 돌이 있는지 판별
    """
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    max_count = 0
    
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
        if count > max_count:
            max_count = count
    
    return max_count >= n

def count_open_fours(board, x, y, color):
    """
    열린4 패턴 개수 카운트 (개선된 버전)
    - 기존 인터페이스 유지
    """
    norm_board = normalize_board(board)
    temp_board = copy.deepcopy(norm_board)
    
    if temp_board[y][x] is not None:  # is not으로 비교
        return 0
    
    normalized_color = normalize_color(color)
    temp_board[y][x] = normalized_color
    return _count_open_patterns_improved(temp_board, x, y, normalized_color, 4)

def is_forbidden_move(board, x, y, color, ruleset):
    """
    금수 판별 - 인터페이스 그대로 유지
    """
    color_key = "black" if color in ("흑", "black", 1, "B") else "white"
    stone = to_internal_piece(color)
    # 삼삼 금수
    if ruleset["double_three"].get(color_key, False) and is_double_three(board, x, y, stone):
        return True, "삼삼 금수"
    # 사사 금수
    if ruleset["double_four"].get(color_key, False) and is_double_four(board, x, y, stone):
        return True, "사사 금수"
    # 장목 금수/승리/무효 처리
    overline_action = ruleset["overline"].get(color_key, "invalid")
    if is_overline(board, x, y, stone):
        if overline_action == "forbidden":
            return True, "장목 금수"
    return False, ""

def get_rule_guide(ruleset):
    """룰셋 가이드 - 기존 코드 유지"""
    guide_parts = []
    for key, meta in RULE_ELEMENTS.items():
        display_name = RULE_DISPLAY_NAMES.get(key, key)
        value = ruleset.get(key, meta.get("default"))
        
        if meta["type"].startswith("per_color"):
            if isinstance(value, dict):
                b, w = value.get("black"), value.get("white")
                b_disp = RULE_VALUE_DISPLAY_FORMATS[key].get(b, str(b))
                w_disp = RULE_VALUE_DISPLAY_FORMATS[key].get(w, str(w))
                if b_disp == w_disp:
                    guide_parts.append(f"{display_name} {b_disp}")
                else:
                    guide_parts.append(f"{display_name}(흑 {b_disp}, 백 {w_disp})")
            else:
                disp = RULE_VALUE_DISPLAY_FORMATS[key].get(value, str(value))
                guide_parts.append(f"{display_name} {disp}")
        elif meta["type"] == "action":
            disp = RULE_VALUE_DISPLAY_FORMATS[key].get(value, str(value))
            guide_parts.append(f"{display_name}: {disp}")
        elif meta["type"] == "enum":
            disp = RULE_VALUE_DISPLAY_FORMATS[key].get(value, str(value))
            guide_parts.append(f"{display_name}: {disp}")
        elif meta["type"] == "list":
            disp = ", ".join([RULE_VALUE_DISPLAY_FORMATS[key].get(v, str(v)) for v in value])
            guide_parts.append(f"{display_name}: {disp}")
        elif meta["type"] == "bool":
            disp = RULE_VALUE_DISPLAY_FORMATS[key].get(value, str(value))
            guide_parts.append(f"{display_name}: {disp}")
    
    return ", ".join(guide_parts)

def count_in_a_row(board, x, y, color):
    """연속 돌 개수 반환 - 기존 코드 유지"""
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    max_count = 0
    
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
        if count > max_count:
            max_count = count
    
    return max_count

def get_all_in_a_row_counts(board, x, y, color):
    """4방향 연속 돌 개수 리스트 반환 - 기존 코드 유지"""
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    counts = []
    
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
        counts.append(count)
    
    return counts

def place_stone(board, x, y, color, ruleset):
    """착수 처리 - 기존 코드 유지"""
    # 이미 돌이 놓인 자리인지 확인
    if board[y][x] is not None:
        return {"result": "invalid", "reason": "이미 돌이 놓인 자리입니다."}

    # 금수 판별
    is_forbidden, reason = is_forbidden_move(board, x, y, color, ruleset)
    forbidden_action = ruleset.get("forbidden_action", "block")
    
    if is_forbidden:
        if forbidden_action == "lose":
            return {"result": "lose", "reason": f"금수({reason})에 착수하여 즉시 패배 처리됩니다.", 
                    "winner": "white" if color == "black" else "black"}
        elif forbidden_action == "block":
            return {"result": "forbidden", "reason": f"해당 자리는 금수({reason})로 착수할 수 없습니다."}
        else:
            return {"result": "forbidden", "reason": f"금수({reason})로 착수 불가"}

    # 정상 착수 처리
    board[y][x] = to_internal_piece(color)
    color_key = "black" if color in ("흑", "black", 1, "B") else "white"
    overline_action = ruleset["overline"].get(color_key, "invalid")
    color_stone = to_internal_piece(color)
    counts = get_all_in_a_row_counts(board, x, y, color_stone)
    
    if any(c >= 6 for c in counts):
        logger.debug(f"[OMOK][DEBUG] place_stone: counts={counts}, overline_action={overline_action} → 장목(6목 이상) 분기")
        if overline_action == "win":
            logger.debug(f"[OMOK][DEBUG] place_stone: 장목 승리 처리")
            winner = to_internal_piece(color)
            return {"result": "win", "winner": winner, "reason": "장목(6목 이상) 승리"}
        elif overline_action == "invalid":
            logger.debug(f"[OMOK][DEBUG] place_stone: 장목 무효 처리")
            return {"result": "ok"}
    elif any(c == 5 for c in counts):
        logger.debug(f"[OMOK][DEBUG] place_stone: counts={counts}, overline_action={overline_action} → 5목 승리 분기")
        winner = to_internal_piece(color)
        return {"result": "win", "winner": winner, "reason": "5목 달성"}
    
    return {"result": "ok"}

def get_forbidden_points(board, ruleset, color):
    """금수 좌표 리스트 반환 - 기존 코드 유지"""
    logger.debug(f"[OMOK][RULE_ENGINE] 금수 좌표 계산에 사용된 룰셋: {ruleset.get('name', 'unknown')} (옵션: {ruleset}), color={color}")
    forbidden_points = []
    board_size = len(board)
    
    for y in range(board_size):
        for x in range(board_size):
            if board[y][x] is None or board[y][x] == 0:  # None과 0 모두 체크
                is_forbidden, _ = is_forbidden_move(board, x, y, color, ruleset)
                if is_forbidden:
                    forbidden_points.append((x, y))
    
    return forbidden_points

def is_move_allowed(x, y, move_number, restrict_list, board_size, restrict_type=None):
    """착수 가능 여부 판정 - 기존 코드 유지"""
    # 1수: center_only 적용
    if move_number == 1 and "center_only" in restrict_list:
        allowed = set(get_restricted_area(board_size, "center_only"))
        if restrict_type == "allowed":
            return (x, y) in allowed
        elif restrict_type == "forbidden":
            return (x, y) not in allowed
        return (x, y) in allowed
    
    # 3수(흑): area5x5/area7x7 적용
    if move_number == 3:
        if "area5x5" in restrict_list:
            area = set(get_restricted_area(board_size, "area5x5"))
            if restrict_type == "allowed":
                return (x, y) in area
            elif restrict_type == "forbidden":
                return (x, y) not in area
            return (x, y) not in area
        if "area7x7" in restrict_list:
            area = set(get_restricted_area(board_size, "area7x7"))
            if restrict_type == "allowed":
                return (x, y) in area
            elif restrict_type == "forbidden":
                return (x, y) not in area
            return (x, y) not in area
    
    # 그 외: 제한 없음
    return True

# 디버깅용 함수 추가
def print_board_log(board, prefix="[OMOK][DEBUG] 가상 보드 현황:"):
    """보드 출력 - 기존 코드 유지"""
    board_str = "\n".join(
        "".join('.' if cell is None or cell == 0 else str(cell) for cell in row)
        for row in board
    )
    logger.debug(f"{prefix}\n{board_str}")

# 기존 _count_open_patterns 함수 유지 (호환성을 위해)
def _count_open_patterns(board, x, y, color, length):
    """기존 함수 - 호환성 유지"""
    return _count_open_patterns_improved(board, x, y, color, length)

def _count_open_patterns_improved(board, x, y, color, length):
    """
    (x, y)에 돌을 놓은 후, 모든 방향의 윈도우에서 열린삼/열린사 패턴을 카운트
    """
    temp_board = copy.deepcopy(board)
    temp_board[y][x] = color
    board_size = len(board)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    open_count = 0
    # length==3: 5칸, 6칸 윈도우 모두 검사
    # length==4: 6칸 윈도우만 검사
    window_sizes = [5, 6] if length == 3 else [6]
    for dx, dy in directions:
        for window_size in window_sizes:
            for start_offset in range(-(window_size - 1), 1):
                positions = [(x + (start_offset + i) * dx, y + (start_offset + i) * dy) for i in range(window_size)]
                if not all(0 <= px < board_size and 0 <= py < board_size for px, py in positions):
                    continue
                if (x, y) not in positions:
                    continue
                window = [temp_board[py][px] for px, py in positions]
                move_idx = positions.index((x, y))
                if length == 3:
                    if is_open_three_in_direction(board, x, y, color, dx, dy):
                        open_count += 1
                        logger.debug(f"[OMOK][열린3] 방향: {(dx,dy)}, 위치: {positions}, 패턴: {window}")
                elif length == 4:
                    if is_open_four_in_window(window, color, move_idx):
                        open_count += 1
                        logger.debug(f"[OMOK][열린4] 방향: {(dx,dy)}, 위치: {positions}, 패턴: {window}")
    return open_count

def get_double_three_forbidden_points(board, color):
    """
    삼삼 금수 좌표 리스트 반환 (최적화: 4방향 2칸 이내 후보만 검사, 중복 제거)
    """
    board_size = len(board)
    directions = [(1,0), (0,1), (1,1), (1,-1)]
    checked = set()
    forbidden_points = set()

    # 1. 후보 좌표 추출 (4방향 2칸 이내)
    for y in range(board_size):
        for x in range(board_size):
            if board[y][x] is not None:
                continue
            for dx, dy in directions:
                for d in [-2, -1, 0, 1, 2]:
                    nx, ny = x + dx*d, y + dy*d
                    if 0 <= nx < board_size and 0 <= ny < board_size:
                        checked.add((nx, ny))

    # 2. 각 후보 좌표에 대해 삼삼 금수 판정
    for (x, y) in checked:
        if board[y][x] is not None:
            continue
        temp_board = deepcopy(board)
        temp_board[y][x] = color
        open_three_dirs = 0
        for dx, dy in directions:
            found = False
            for window_size in [5, 6]:
                for start_offset in range(-(window_size - 1), 1):
                    positions = [(x + (start_offset + i) * dx, y + (start_offset + i) * dy) for i in range(window_size)]
                    if not all(0 <= px < board_size and 0 <= py < board_size for px, py in positions):
                        continue
                    if (x, y) not in positions:
                        continue
                    window = [temp_board[py][px] for px, py in positions]
                    move_idx = positions.index((x, y))
                    if is_open_three_in_direction(board, x, y, color, dx, dy):
                        found = True
                        break
                if found:
                    open_three_dirs += 1
        if open_three_dirs >= 2:
            forbidden_points.add((x, y))
    return list(forbidden_points)