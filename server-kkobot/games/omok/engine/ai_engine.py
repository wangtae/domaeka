import random
import core.globals as g
from services.llm_fallback_service import call_llm_with_fallback
from core.logger import logger
from games.omok.engine.rule_engine import is_forbidden_move, check_five_in_a_row, count_open_threes, count_open_fours
from games.omok.constants import RULESETS
import copy
from games.omok.utils.piece_utils import to_internal_piece
import time
from functools import lru_cache
from typing import Tuple, List, Optional, Set

# 캐싱 데코레이터
def memoize_position(func):
    cache = {}
    def wrapper(board, x, y, piece, ruleset=None):
        board_key = tuple(tuple(row) for row in board)
        cache_key = (board_key, x, y, piece, str(ruleset))
        if cache_key not in cache:
            cache[cache_key] = func(board, x, y, piece, ruleset)
        return cache[cache_key]
    return wrapper

# 패턴 정의
WINNING_PATTERNS = {
    'five': ['●●●●●'],
    'open_four': ['.●●●●.', '●.●●●.', '●●.●●.', '●●●.●.'],
    'blocked_four': ['X●●●●.', '.●●●●X', 'X●.●●●', '●●●.●X'],
    'open_three': ['.●●●..', '..●●●.', '.●.●●.', '.●●.●.'],
    'blocked_three': ['X●●●..', '..●●●X', 'X●.●●.', '.●●.●X'],
    'potential_four': ['.●●.●.', '.●.●●.', '●..●●.', '.●●..●']
}

# 위치 가중치 맵
POSITION_WEIGHTS = []
for i in range(15):
    row = []
    for j in range(15):
        distance = min(i, 14-i) + min(j, 14-j)
        weight = min(7, distance) + 1
        row.append(weight)
    POSITION_WEIGHTS.append(row)

OMOK_SYSTEM_PROMPT = """
당신은 오목 게임의 고수 AI 플레이어입니다. 주어진 바둑판 상태를 철저히 분석하고 최적의 수를 선택해야 합니다.

바둑판 표현:
- 15x15 크기의 바둑판
- None: 빈 칸
- 'B': 흑돌
- 'W': 백돌

좌표 체계:
- 가로: A-O (알파벳)
- 세로: 1-15 (숫자)

필수 응답 형식:
- 반드시 "알파벳+숫자" 형식으로만 응답 (예: "H8")
- 분석 내용이나 설명 금지

핵심 규칙:
1. 5개 연속 돌 → 승리
2. 빈 칸에만 착수 가능
3. 흑은 3-3 금수 적용
4. 6목 이상도 승리 인정

고급 전략 지침:
1. **동시에 열린 3목/4목(이중삼/이중사 등) 다중 위협을 최우선으로 탐지/공격/방어**
2. 자신의 4목 만들기 (승리 기회)
3. 열린 3목 만들기 (다중 위협)
4. 잠재적 3목 위치 선점
5. 상대의 4목 저지 (필수)
6. 상대의 열린 3목 차단
7. 상대의 잠재적 3목 방해
8. 중앙 및 중앙 근처 선점
9. 상대 돌 밀집 지역 공략
10. 자신의 돌과 연결성 유지
11. 수비와 공격의 균형
12. 장기적 발전 가능성

특수 전술:
- 흑돌: 3-3 금수 회피하며 공격
- 백돌: 흑의 3-3 금수 유도
- 양날 공격 구사 (이중/삼중 위협)
- 수비하며 역공 기회 엿보기

위치 평가 기준:
- 돌의 연결성과 확장성
- 공격/수비 다목적 가치
- 상대방 견제 효과
- 차후 전개 가능성

승리 패턴 인식:
- 5목 직접 달성
- 4-4 이중 공격
- 3-4 혼합 공격
- 3-3 회피하며 공격 (흑)
- **이중삼/이중사 등 다중 위협 패턴을 반드시 인식하고 활용**
"""

# AI 모드 매핑
AI_MODE_MAP = {
    "기본": "hybrid",
    "고급": "llm",
    "hybrid": "hybrid",
    "llm": "llm"
}

def get_internal_ai_mode(mode):
    """한글/영문 ai_mode를 내부 코드로 변환"""
    return AI_MODE_MAP.get(str(mode).strip().lower(), "hybrid")

def board_to_text(board):
    """바둑판 상태를 문자열로 변환"""
    rows = []
    for y in range(len(board)):
        row = []
        for x in range(len(board)):
            if board[y][x] is None:
                row.append('.')
            else:
                row.append(board[y][x])
        rows.append(''.join(row))
    return '\n'.join(rows)

def format_move_history(move_history):
    """착수 히스토리를 문자열로 변환"""
    if not move_history:
        return "아직 착수가 없습니다."
    
    moves = []
    for i, (x, y, color) in enumerate(move_history, 1):
        moves.append(f"{i}. {color}({chr(ord('A') + x)}{y + 1})")
    return "\n".join(moves)

def get_candidate_moves(board, max_distance=2) -> List[Tuple[int, int]]:
    """돌 주변의 유망한 위치만 반환"""
    candidates = set()
    size = len(board)
    
    # 빈 보드인 경우 중앙 주변만 반환
    has_stones = any(board[y][x] is not None for y in range(size) for x in range(size))
    if not has_stones:
        center = size // 2
        for dx in range(-1, 2):
            for dy in range(-1, 2):
                x, y = center + dx, center + dy
                if 0 <= x < size and 0 <= y < size:
                    candidates.add((x, y))
        return list(candidates)
    
    # 기존 돌 주변 확장
    for y in range(size):
        for x in range(size):
            if board[y][x] is not None:
                for dx in range(-max_distance, max_distance + 1):
                    for dy in range(-max_distance, max_distance + 1):
                        nx, ny = x + dx, y + dy
                        if (0 <= nx < size and 0 <= ny < size and 
                            board[ny][nx] is None):
                            candidates.add((nx, ny))
    
    return list(candidates)

def copy_board(board):
    return [row[:] for row in board]

def match_pattern_at_position(board, x, y, dx, dy, piece, pattern):
    """특정 위치에서 특정 방향으로 패턴 매칭"""
    internal_piece = to_internal_piece(piece)
    opponent_piece = to_internal_piece('B' if piece == 'W' else 'W')
    
    pattern_len = len(pattern)
    for start_offset in range(-pattern_len + 1, 1):
        match = True
        for i, p in enumerate(pattern):
            nx = x + (start_offset + i) * dx
            ny = y + (start_offset + i) * dy
            
            if not (0 <= nx < len(board) and 0 <= ny < len(board)):
                match = False
                break
            
            cell = board[ny][nx]
            if p == '●' and cell != internal_piece:
                match = False
                break
            elif p == '.' and cell is not None:
                match = False
                break
            elif p == 'X' and (cell is None or cell == internal_piece):
                match = False
                break
        
        if match:
            return True
    return False

def count_pattern_matches(board, x, y, piece, pattern_list):
    """특정 위치에서 패턴 매칭 수 계산"""
    if board[y][x] is not None:
        return 0
    
    board_copy = copy_board(board)
    board_copy[y][x] = to_internal_piece(piece)
    
    count = 0
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    
    for dx, dy in directions:
        for pattern in pattern_list:
            if match_pattern_at_position(board_copy, x, y, dx, dy, piece, pattern):
                count += 1
    
    return count

def count_connected_stones(board, x, y, piece):
    """연결된 돌의 개수 계산"""
    internal_piece = to_internal_piece(piece)
    count = 0
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    
    for dx, dy in directions:
        line_count = 0
        for direction in [-1, 1]:
            nx, ny = x + dx * direction, y + dy * direction
            while (0 <= nx < len(board) and 0 <= ny < len(board) and 
                   board[ny][nx] == internal_piece):
                line_count += 1
                nx += dx * direction
                ny += dy * direction
        count = max(count, line_count)
    
    return count

def is_valid_win(board, x, y, piece, ruleset):
    """해당 위치가 유효한 승리인지 확인 (장목 규칙 적용)"""
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    internal_piece = to_internal_piece(piece)
    
    for dx, dy in directions:
        count = 1
        # 양방향 확인
        for direction in [-1, 1]:
            nx, ny = x + dx * direction, y + dy * direction
            while 0 <= nx < len(board) and 0 <= ny < len(board) and board[ny][nx] == internal_piece:
                count += 1
                nx += dx * direction
                ny += dy * direction
        
        if count == 5:
            return True  # 정확히 5목
        elif count > 5:
            # 장목 처리
            long_line_rule = ruleset.get("long_line", {}).get(piece.lower(), "win")
            if long_line_rule == "win":
                return True
            elif long_line_rule == "forbidden":
                return False  # 금수로 처리
            elif long_line_rule == "invalid":
                continue  # 무효, 다른 방향 확인
    
    return False

def check_long_line_status(board, x, y, piece, ruleset):
    """장목 상태 확인 (forbidden/invalid/win)"""
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    internal_piece = to_internal_piece(piece)
    
    for dx, dy in directions:
        count = 1
        # 양방향 확인
        for direction in [-1, 1]:
            nx, ny = x + dx * direction, y + dy * direction
            while 0 <= nx < len(board) and 0 <= ny < len(board) and board[ny][nx] == internal_piece:
                count += 1
                nx += dx * direction
                ny += dy * direction
        
        if count > 5:
            long_line_rule = ruleset.get("long_line", {}).get(piece.lower(), "win")
            return long_line_rule
    
    return None  # 장목이 아님

@memoize_position
def evaluate_position_enhanced(board, x, y, piece, ruleset):
    """개선된 평가 함수 (룰셋과 장목 규칙 포함)"""
    if board[y][x] is not None:
        return -1
    
    score = 0
    opponent = 'B' if piece == 'W' else 'W'
    if ruleset is None:
        ruleset = RULESETS.get("standard")
    
    # 위치 가중치 적용
    score += POSITION_WEIGHTS[y][x] * 10
    
    # 패턴 기반 평가
    board_copy = copy_board(board)
    board_copy[y][x] = to_internal_piece(piece)
    
    # 승리 확인 (장목 규칙 적용)
    if is_valid_win(board_copy, x, y, piece, ruleset):
        return 100000
    
    # 상대방 승리 차단
    board_copy[y][x] = to_internal_piece(opponent)
    if is_valid_win(board_copy, x, y, opponent, ruleset):
        return 90000
    board_copy[y][x] = to_internal_piece(piece)
    
    # 장목 상태 확인
    long_line_status = check_long_line_status(board_copy, x, y, piece, ruleset)
    if long_line_status == "forbidden":
        return -100000  # 장목 금수인 경우 매우 낮은 점수
    elif long_line_status == "invalid":
        score -= 5000  # 장목 무효인 경우 페널티
    
    # 금수 체크 - 상대방을 금수 위치로 유도하는 전략 평가
    board_copy_opp = copy_board(board)
    board_copy_opp[y][x] = to_internal_piece(opponent)
    is_forbidden_for_opponent, forbidden_type = is_forbidden_move(board_copy_opp, x, y, opponent, ruleset)
    if is_forbidden_for_opponent and opponent == 'B':  # 흑에게만 금수가 적용되는 경우가 많음
        score += 3000  # 상대방을 금수 위치로 유도할 수 있으면 보너스
    
    # 패턴 매칭 점수
    open_fours = count_pattern_matches(board, x, y, piece, WINNING_PATTERNS['open_four'])
    blocked_fours = count_pattern_matches(board, x, y, piece, WINNING_PATTERNS['blocked_four'])
    open_threes = count_pattern_matches(board, x, y, piece, WINNING_PATTERNS['open_three'])
    blocked_threes = count_pattern_matches(board, x, y, piece, WINNING_PATTERNS['blocked_three'])
    potential_fours = count_pattern_matches(board, x, y, piece, WINNING_PATTERNS['potential_four'])
    
    # 공격 점수
    score += open_fours * 10000
    score += blocked_fours * 1000
    score += open_threes * 500
    score += blocked_threes * 100
    score += potential_fours * 200
    
    # 방어 점수 (상대방 패턴)
    opp_open_fours = count_pattern_matches(board, x, y, opponent, WINNING_PATTERNS['open_four'])
    opp_blocked_fours = count_pattern_matches(board, x, y, opponent, WINNING_PATTERNS['blocked_four'])
    opp_open_threes = count_pattern_matches(board, x, y, opponent, WINNING_PATTERNS['open_three'])
    
    score += opp_open_fours * 9000
    score += opp_blocked_fours * 900
    score += opp_open_threes * 400
    
    # 연결성 보너스
    connected_stones = count_connected_stones(board_copy, x, y, piece)
    score += connected_stones * 30
    
    return score

def find_winning_move_with_rules(board, piece, ruleset):
    """승리할 수 있는 착수점을 찾습니다 (룰셋 적용)"""
    candidates = get_candidate_moves(board)
    
    for x, y in candidates:
        is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
        if is_forbidden:
            continue
            
        board[y][x] = to_internal_piece(piece)
        
        # 장목 규칙을 고려한 승리 확인
        if is_valid_win(board, x, y, piece, ruleset):
            board[y][x] = None
            return x, y
            
        board[y][x] = None
    
    return None

def find_threats_with_rules(board, piece, ruleset, min_threat_level=2):
    """위협적인 위치 찾기 (룰셋 적용)"""
    threats = []
    candidates = get_candidate_moves(board)
    
    for x, y in candidates:
        is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
        if is_forbidden:
            continue
            
        board[y][x] = to_internal_piece(piece)
        
        # 열린 3목과 4목 계산
        open_threes = count_open_threes(board, x, y, piece)
        open_fours = count_open_fours(board, x, y, piece)
        
        threat_level = open_threes + open_fours * 2
        
        if threat_level >= min_threat_level:
            threats.append((x, y, threat_level))
        
        board[y][x] = None
    
    return sorted(threats, key=lambda x: x[2], reverse=True)

def minimax_alpha_beta_with_rules(board, piece, depth, alpha, beta, maximizing, time_limit, start_time, ruleset):
    """알파-베타 가지치기를 사용한 미니맥스 (룰셋 적용)"""
    if time.time() - start_time > time_limit:
        return None, evaluate_board_state(board, piece, ruleset)
    
    if depth == 0:
        return None, evaluate_board_state(board, piece, ruleset)
    
    candidates = get_candidate_moves(board, max_distance=1)
    if not candidates:
        return None, 0
    
    # 후보 수 정렬
    if depth > 1:
        scored_moves = []
        for x, y in candidates[:10]:
            is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
            if is_forbidden:
                continue
                
            # 장목 체크
            board[y][x] = to_internal_piece(piece)
            long_line_status = check_long_line_status(board, x, y, piece, ruleset)
            board[y][x] = None
            
            if long_line_status == "forbidden":
                continue  # 장목 금수는 제외
            
            score = evaluate_position_enhanced(board, x, y, piece, ruleset)
            scored_moves.append((score, (x, y)))
        
        candidates = [move for _, move in sorted(scored_moves, reverse=maximizing)]
    
    best_move = None
    opponent = 'B' if piece == 'W' else 'W'
    
    if maximizing:
        max_eval = float('-inf')
        for x, y in candidates:
            is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
            if is_forbidden:
                continue
                
            board[y][x] = to_internal_piece(piece)
            
            # 유효한 승리 체크 (장목 규칙 적용)
            if is_valid_win(board, x, y, piece, ruleset):
                board[y][x] = None
                return (x, y), 100000
            
            # 장목 무효인 경우 평가 감소
            long_line_status = check_long_line_status(board, x, y, piece, ruleset)
            eval_penalty = 0
            if long_line_status == "invalid":
                eval_penalty = -5000
            
            _, eval_score = minimax_alpha_beta_with_rules(board, opponent, depth-1, alpha, beta, False, 
                                                         time_limit, start_time, ruleset)
            eval_score += eval_penalty
            board[y][x] = None
            
            if eval_score > max_eval:
                max_eval = eval_score
                best_move = (x, y)
            
            alpha = max(alpha, eval_score)
            if beta <= alpha:
                break
        
        return best_move, max_eval
    else:
        min_eval = float('inf')
        for x, y in candidates:
            is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
            if is_forbidden:
                continue
                
            board[y][x] = to_internal_piece(piece)
            
            # 유효한 승리 체크
            if is_valid_win(board, x, y, piece, ruleset):
                board[y][x] = None
                return (x, y), -100000
            
            # 장목 무효인 경우 평가 감소
            long_line_status = check_long_line_status(board, x, y, piece, ruleset)
            eval_penalty = 0
            if long_line_status == "invalid":
                eval_penalty = 5000  # 상대방 관점에서는 보너스
            
            _, eval_score = minimax_alpha_beta_with_rules(board, opponent, depth-1, alpha, beta, True, 
                                                         time_limit, start_time, ruleset)
            eval_score += eval_penalty
            board[y][x] = None
            
            if eval_score < min_eval:
                min_eval = eval_score
                best_move = (x, y)
            
            beta = min(beta, eval_score)
            if beta <= alpha:
                break
        
        return best_move, min_eval

def evaluate_board_state(board, piece, ruleset):
    """전체 보드 상태 평가"""
    score = 0
    
    # 모든 위치에 대해 평가
    for y in range(len(board)):
        for x in range(len(board)):
            if board[y][x] is None:
                continue
            
            current_piece = 'B' if board[y][x] == to_internal_piece('B') else 'W'
            position_score = evaluate_position_enhanced(board, x, y, current_piece, ruleset)
            
            if current_piece == piece:
                score += position_score
            else:
                score -= position_score
    
    return score

def strategic_move_with_rules(board, piece, ruleset):
    """기본 전략적 위치 선택 (룰셋 적용)"""
    empty_cells = [(x, y) for y in range(len(board)) for x in range(len(board)) if board[y][x] is None]
    if not empty_cells:
        return None
    
    # 중앙 우선, 금수가 아닌 위치 찾기
    cx = cy = len(board) // 2
    candidates = sorted(empty_cells, key=lambda pos: abs(pos[0] - cx) + abs(pos[1] - cy))
    
    for x, y in candidates:
        is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
        if not is_forbidden:
            return (x, y)
    
    # 모든 위치가 금수인 경우 (극히 드물지만) None 반환
    logger.warning("[AI] 모든 위치가 금수입니다.")
    return None

def count_in_a_row(board, x, y, piece):
    """특정 위치에서 연속된 돌의 개수 계산 (최대값 반환)"""
    internal_piece = to_internal_piece(piece)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    max_count = 0
    
    for dx, dy in directions:
        count = 1  # 현재 위치 포함
        
        # 양방향 체크
        for direction in [-1, 1]:
            nx, ny = x + dx * direction, y + dy * direction
            while 0 <= nx < len(board) and 0 <= ny < len(board) and board[ny][nx] == internal_piece:
                count += 1
                nx += dx * direction
                ny += dy * direction
        
        max_count = max(max_count, count)
    
    return max_count

def find_best_move(board, piece, ruleset=None, use_minimax=True, time_limit=2.0):
    """개선된 최적 수 찾기"""
    logger.info("[DEBUG][AI] find_best_move 진입")
    if ruleset is None:
        ruleset = RULESETS.get("standard")
    
    opponent_piece = 'B' if piece == 'W' else 'W'
    
    # 1. 즉시 승리 확인
    winning_move = find_winning_move_with_rules(board, piece, ruleset)
    if winning_move:
        logger.info(f"[DEBUG][AI] 즉시 승리 발견: {winning_move}")
        return winning_move
    
    # 2. 상대 즉시 승리 차단
    opponent_winning = find_winning_move_with_rules(board, opponent_piece, ruleset)
    if opponent_winning:
        logger.info(f"[DEBUG][AI] 상대 승리 차단: {opponent_winning}")
        return opponent_winning
    
    # 3. 상대의 열린4 차단 (긴급)
    opponent_open_fours = find_open_fours_defense(board, opponent_piece, ruleset)
    if opponent_open_fours:
        logger.info(f"[DEBUG][AI] 상대 열린4 긴급 차단: {opponent_open_fours[0]}")
        return opponent_open_fours[0]
    
    # 4. 자신의 열린4 기회
    my_open_fours = find_open_fours_attack(board, piece, ruleset)
    if my_open_fours:
        logger.info(f"[DEBUG][AI] 열린4 공격: {my_open_fours[0]}")
        return my_open_fours[0]
    
    # 5. 상대의 열린3 차단 (중요! - 수정된 부분)
    opponent_open_threes = find_open_threes_defense(board, opponent_piece, ruleset)
    if opponent_open_threes:
        # 방어 위치 중에서 내가 동시에 공격할 수 있는 위치 우선 선택
        best_defense = None
        best_score = -float('inf')
        
        for x, y in opponent_open_threes:
            # 금수 체크
            is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
            if is_forbidden:
                continue
            
            # 이 위치에 두었을 때 나의 공격 가능성 평가
            board[y][x] = to_internal_piece(piece)
            
            # 내가 이 위치에 두면 4목이 되는지 확인
            my_four = count_in_a_row(board, x, y, piece)
            # 내가 이 위치에 두면 열린삼이 생기는지 확인
            my_open_threes = count_open_threes(board, x, y, piece)
            
            # 평가 점수 계산
            score = 0
            if my_four >= 4:
                score += 1000  # 4목 공격 보너스
            score += my_open_threes * 100  # 열린삼 공격 보너스
            score += evaluate_position_enhanced(board, x, y, piece, ruleset)
            
            board[y][x] = None
            
            if score > best_score:
                best_score = score
                best_defense = (x, y)
        
        if best_defense:
            logger.info(f"[DEBUG][AI] 상대 열린삼 방어: {best_defense}, 점수: {best_score}")
            return best_defense
    
    # 이하 기존 코드와 동일...
    # 6. 위협 기반 평가
    my_threats = find_threats_with_rules(board, piece, ruleset)
    opponent_threats = find_threats_with_rules(board, opponent_piece, ruleset)
    
    # 상대의 위협 차단
    if opponent_threats and opponent_threats[0][2] >= 2:
        logger.info(f"[DEBUG][AI] 상대 위협 차단: {opponent_threats[0][:2]}")
        return opponent_threats[0][:2]
    
    # 나의 위협 생성
    if my_threats and my_threats[0][2] >= 2:
        logger.info(f"[DEBUG][AI] 위협 생성: {my_threats[0][:2]}")
        return my_threats[0][:2]
    
    # 7. 미니맥스 사용
    if use_minimax:
        start_time = time.time()
        best_move, _ = minimax_alpha_beta_with_rules(board, piece, depth=3, alpha=float('-inf'), 
                                                    beta=float('inf'), maximizing=True, 
                                                    time_limit=time_limit, start_time=start_time,
                                                    ruleset=ruleset)
        if best_move:
            logger.info(f"[DEBUG][AI] 미니맥스 선택: {best_move}")
            return best_move
    
    # 8. 평가 함수 기반 선택
    candidates = get_candidate_moves(board)
    best_score = -float('inf')
    best_moves = []
    
    for x, y in candidates:
        is_forbidden, forbidden_type = is_forbidden_move(board, x, y, piece, ruleset)
        if is_forbidden:
            continue
        
        score = evaluate_position_enhanced(board, x, y, piece, ruleset)
        
        if score > best_score:
            best_score = score
            best_moves = [(x, y)]
        elif score == best_score:
            best_moves.append((x, y))
    
    if best_moves:
        move = random.choice(best_moves)
        logger.info(f"[DEBUG][AI] 평가 함수 선택: {move}, 점수: {best_score}")
        return move
    
    return strategic_move_with_rules(board, piece, ruleset)

def find_open_threes_defense(board, opponent_piece, ruleset):
    """상대의 열린3을 찾아 방어할 위치 반환 (개선된 버전)"""
    defense_moves = []
    urgent_defense = []  # 긴급 방어 위치
    
    # 1단계: 상대가 다음에 둘 수 있는 후보 위치들 확인
    candidates = get_candidate_moves(board)
    
    for x, y in candidates:
        if board[y][x] is None:
            # 상대가 이 위치에 두었을 때
            board[y][x] = to_internal_piece(opponent_piece)
            
            # 열린삼이 몇 개 생기는지 확인
            open_threes = count_open_threes(board, x, y, opponent_piece)
            
            if open_threes >= 2:
                # 이중삼 이상 - 매우 위험
                urgent_defense.append((x, y))
            elif open_threes == 1:
                # 열린삼 1개 - 위험
                defense_moves.append((x, y))
            
            # 추가로 이 위치에 두면 4목이 되는지도 확인
            if count_in_a_row(board, x, y, opponent_piece) >= 4:
                urgent_defense.append((x, y))
            
            board[y][x] = None
    
    # 2단계: 기존 열린삼 패턴을 막을 수 있는 위치 찾기
    for y in range(len(board)):
        for x in range(len(board)):
            if board[y][x] is None:
                # 내가 이 위치에 두면 상대의 열린삼이 막히는지 확인
                board[y][x] = to_internal_piece('B' if opponent_piece == 'W' else 'W')
                
                # 상대의 열린삼 개수 감소 확인
                blocked_threes = False
                for cy in range(len(board)):
                    for cx in range(len(board)):
                        if board[cy][cx] == to_internal_piece(opponent_piece):
                            # 이 위치를 두기 전후의 열린삼 개수 비교
                            board[y][x] = None
                            before = count_open_threes(board, cx, cy, opponent_piece)
                            board[y][x] = to_internal_piece('B' if opponent_piece == 'W' else 'W')
                            after = count_open_threes(board, cx, cy, opponent_piece)
                            
                            if before > after:
                                blocked_threes = True
                                break
                    if blocked_threes:
                        break
                
                if blocked_threes and (x, y) not in defense_moves and (x, y) not in urgent_defense:
                    defense_moves.append((x, y))
                
                board[y][x] = None
    
    # 긴급 방어가 있으면 우선 반환
    if urgent_defense:
        return urgent_defense
    
    return defense_moves

def find_open_fours_defense(board, opponent_piece, ruleset):
    """상대의 열린4를 찾아 방어할 위치 반환"""
    candidates = get_candidate_moves(board)
    defense_moves = []
    
    for x, y in candidates:
        if board[y][x] is None:
            board[y][x] = to_internal_piece(opponent_piece)
            open_fours = count_open_fours(board, x, y, opponent_piece)
            board[y][x] = None
            
            if open_fours > 0:
                # 열린4는 반드시 막아야 함
                return [(x, y)]
    
    return defense_moves

def find_open_fours_attack(board, piece, ruleset):
    """자신의 열린4를 만들 수 있는 위치 반환"""
    candidates = get_candidate_moves(board)
    attack_moves = []
    
    for x, y in candidates:
        is_forbidden, _ = is_forbidden_move(board, x, y, piece, ruleset)
        if is_forbidden:
            continue
        
        if board[y][x] is None:
            board[y][x] = to_internal_piece(piece)
            open_fours = count_open_fours(board, x, y, piece)
            board[y][x] = None
            
            if open_fours > 0:
                attack_moves.append((x, y))
    
    return attack_moves

def coord_to_xy(coord):
    """알파벳+숫자 형식의 좌표를 (x, y) 좌표로 변환합니다."""
    coord = coord.upper().strip()
    if len(coord) < 2 or len(coord) > 3:
        return None
        
    # 첫 글자가 알파벳인 경우 (H8 형식)
    if coord[0].isalpha():
        x_str, y_str = coord[0], coord[1:]
    # 마지막 글자가 알파벳인 경우 (8H 형식)
    elif coord[-1].isalpha():
        x_str, y_str = coord[-1], coord[:-1]
    else:
        return None
    
    # 가로 좌표(A-O) 검증
    if not ('A' <= x_str <= 'O'):
        return None
        
    # 세로 좌표(1-15) 검증
    try:
        y = int(y_str)
        if not (1 <= y <= 15):
            return None
    except ValueError:
        return None
    
    x = ord(x_str) - ord('A')
    y = y - 1  # 0-based index로 변환
    return x, y

def xy_to_coord(x, y):
    """(x, y) 좌표를 알파벳+숫자 형식으로 변환합니다."""
    return f"{chr(ord('A') + x)}{y + 1}"

async def choose_ai_move(board, context, player_piece='W', move_history=None):
   """AI의 다음 수를 선택합니다"""
   try:
       # 세션에서 설정 가져오기
       session = context.get("omok_session")
       if not session:
           logger.warning("[OMOK] 세션 정보를 찾을 수 없습니다.")
           return strategic_move(board), False

       # 방 설정 가져오기
       try:
           bot_name = context.get("bot_name")
           channel_id = str(context.get("channel_id"))
           room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
           omok_settings = room_config.get("omok_settings", {})
           logger.debug(f"[OMOK] 방 설정 로드 → {omok_settings}")
       except Exception as e:
           logger.warning(f"[OMOK] 방 설정 로드 실패 → {e}")
           omok_settings = {}

       # 룰셋 가져오기
       ruleset = omok_settings.get("ruleset", RULESETS.get("standard"))

       # 디버그 모드 확인
       debug_mode = context.get("parameters", {}).get("debug", False)
       if debug_mode and move_history:
           history_text = format_move_history(move_history)
           logger.info(f"[OMOK] 현재까지의 착수 현황:\n{history_text}")

       # AI 모드 결정
       if session and hasattr(session, 'ai_mode'):
           internal_mode = get_internal_ai_mode(session.ai_mode)
       else:
           mode_raw = omok_settings.get("ai_mode", "hybrid")
           internal_mode = get_internal_ai_mode(mode_raw)
       logger.info(f"[OMOK] AI 모드: {internal_mode}")
       
       # LLM 전용 모드인 경우
       if internal_mode == "llm":
           logger.info("[OMOK] LLM 모드로 동작합니다.")
           board_text = board_to_text(board)
           history_text = format_move_history(move_history or [])
           
           prompt = f"""현재 바둑판 상태:
{board_text}

착수 히스토리:
{history_text}

당신은 {player_piece} 돌을 사용합니다.
바둑판을 분석하고 최적의 착수 위치를 알파벳+숫자 형식으로 응답하세요."""

           providers = [
               {
                   "name": "openai",
                   "timeout": 30,
                   "model": "gpt-4",
                   "retry": 0,
                   "system_prompt": OMOK_SYSTEM_PROMPT
               },
               {
                   "name": "gemini",
                   "model": "gemini-1.5-pro",
                   "timeout": 30,
                   "retry": 0,
                   "system_prompt": OMOK_SYSTEM_PROMPT
               }
           ]

           response = await call_llm_with_fallback(context, prompt, providers=providers)
           logger.info(f"[OMOK] LLM 응답: {response}")
           
           # 응답에서 좌표 추출
           response = response.strip().strip('"\'')
           
           # 알파벳+숫자 형식의 좌표를 x,y로 변환
           xy = coord_to_xy(response)
           if xy:
               x, y = xy
               if board[y][x] is None:
                   # 금수 확인
                   is_forbidden, _ = is_forbidden_move(board, x, y, player_piece, ruleset)
                   if not is_forbidden:
                       logger.info(f"[OMOK] LLM 착수 위치 선택: {response} ({x}, {y})")
                       return (x, y), True
                   else:
                       logger.warning(f"[OMOK] LLM이 선택한 위치 {response} ({x}, {y})는 금수입니다.")
               else:
                   logger.warning(f"[OMOK] LLM이 선택한 위치 {response} ({x}, {y})는 이미 돌이 있습니다.")
           else:
               logger.warning(f"[OMOK] LLM 응답이 올바른 형식이 아닙니다: {response}")
           
           # LLM이 실패하면 기본 전략 사용
           logger.warning("[OMOK] LLM 응답 실패, 기본 전략으로 대체합니다.")
           return strategic_move_with_rules(board, player_piece, ruleset), False
           
       # 하이브리드 모드
       else:
           logger.info("[OMOK] 하이브리드 모드로 동작합니다.")
           # 개선된 기본 전략으로 최선의 수 찾기
           move = find_best_move(board, player_piece, ruleset, use_minimax=True, time_limit=2.0)
           if move:
               logger.info(f"[OMOK] 하이브리드 모드 선택: {move}")
               return move, False
               
           # 기본 전략이 실패하면 LLM 사용
           board_text = board_to_text(board)
           history_text = format_move_history(move_history or [])
           
           prompt = f"""현재 바둑판 상태:
{board_text}

착수 히스토리:
{history_text}

당신은 {player_piece} 돌을 사용합니다.
바둑판을 분석하고 최적의 착수 위치를 알파벳+숫자 형식으로 응답하세요."""

           providers = [
               {
                   "name": "openai",
                   "timeout": 30,
                   "model": "gpt-4",
                   "retry": 0,
                   "system_prompt": OMOK_SYSTEM_PROMPT
               },
               {
                   "name": "gemini",
                   "model": "gemini-1.5-pro",
                   "timeout": 30,
                   "retry": 0,
                   "system_prompt": OMOK_SYSTEM_PROMPT
               }
           ]

           response = await call_llm_with_fallback(context, prompt, providers=providers)
           
           # 응답에서 좌표 추출
           response = response.strip().strip('"\'')
           
           # 알파벳+숫자 형식의 좌표를 x,y로 변환
           xy = coord_to_xy(response)
           if xy:
               x, y = xy
               if board[y][x] is None:
                   # 금수 확인
                   is_forbidden, _ = is_forbidden_move(board, x, y, player_piece, ruleset)
                   if not is_forbidden:
                       return (x, y), True
           else:
               logger.warning(f"[OMOK] LLM 응답이 올바른 형식이 아닙니다: {response}")

   except Exception as e:
       logger.error(f"AI 착수 선택 중 오류 발생: {e}", exc_info=True)

   # 모든 방법이 실패하면 기본 전략 사용
   return strategic_move(board), False

def strategic_move(board):
   """기본 전략적 위치 선택 (룰셋 미적용, 폴백용)"""
   # 빈 칸 찾기
   empty_cells = [(x, y) for y in range(len(board)) for x in range(len(board)) if board[y][x] is None]
   if not empty_cells:
       return None
       
   # 중앙 우선 전략
   cx = cy = len(board) // 2
   candidates = sorted(empty_cells, key=lambda pos: abs(pos[0] - cx) + abs(pos[1] - cy))
   return candidates[0] if candidates else None

def choose_swap_color(board, move_history, session, context):
   """swap1/swap2에서 AI가 돌 색깔을 선택하는 전략 함수"""
   # 보드 상태 분석
   if len(move_history) >= 3:
       ruleset = RULESETS.get("standard")
       # 현재 보드 상태 평가
       black_advantage = evaluate_board_state(board, 'B', ruleset)
       white_advantage = evaluate_board_state(board, 'W', ruleset)
       
       # 유리한 색상 선택
       if black_advantage > white_advantage:
           return 'black'
       else:
           return 'white'
   
   # 정보가 부족한 경우 랜덤 선택
   return random.choice(['black', 'white'])

def choose_swap2_action(board, move_history, session, context):
   """swap2에서 AI가 swap(스왑) 또는 add_moves(추가착수) 중 하나를 선택하는 전략 함수"""
   if len(move_history) >= 3:
       ruleset = RULESETS.get("standard")
       # 현재 보드 평가
       current_evaluation = evaluate_board_state(board, 'B', ruleset)
       
       # 현재 위치가 매우 유리하면 스왑
       if abs(current_evaluation) > 500:
           return 'swap'
       # 균형잡힌 상태면 추가 착수
       else:
           return 'add_moves'
   
   return random.choice(['swap', 'add_moves'])

def choose_additional_moves(board, move_history, session, context):
   """swap2에서 추가 착수 단계에서 AI가 둘 좌표를 선택하는 전략 함수"""
   ruleset = RULESETS.get("standard")
   # 전략적으로 좋은 위치 찾기
   best_moves = []
   candidates = get_candidate_moves(board)
   
   for x, y in candidates:
       if board[y][x] is None:
           # 금수 확인
           is_forbidden, _ = is_forbidden_move(board, x, y, 'B', ruleset)
           if not is_forbidden:
               score = evaluate_position_enhanced(board, x, y, 'B', ruleset)  # 흑돌 기준으로 평가
               best_moves.append((score, (x, y)))
   
   # 상위 2개 선택
   best_moves.sort(reverse=True)
   if len(best_moves) >= 2:
       return best_moves[0][1], best_moves[1][1]
   elif len(best_moves) == 1:
       # 하나만 있으면 중앙 근처 빈 칸 추가
       return best_moves[0][1], strategic_move_with_rules(board, 'B', ruleset)
   else:
       return None, None

# 기존 함수들 (호환성을 위해 유지) - 최소화
def count_open_threes(board, x, y, piece, debug=False):
    """열린 3목 개수 계산 (더 정확한 버전)"""
    if board[y][x] is not None:
        return 0
    
    board_copy = copy_board(board)
    board_copy[y][x] = to_internal_piece(piece)
    
    count = 0
    size = len(board_copy)
    directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
    
    for dx, dy in directions:
        # 5칸 윈도우로 슬라이딩하며 체크 (최대 길이 5)
        for start in range(-4, 1):
            # 5개 위치의 좌표
            coords = [(x + (start + i) * dx, y + (start + i) * dy) for i in range(5)]
            
            # 범위 체크
            if not all(0 <= nx < size and 0 <= ny < size for nx, ny in coords):
                continue
            
            # 현재 위치가 5칸 안에 포함되는지 확인
            if (x, y) not in coords:
                continue
            
            # 5칸의 상태 가져오기
            stones = [board_copy[ny][nx] for nx, ny in coords]
            
            # 열린 3목 패턴 체크: .●●●. (양쪽이 비어있고 가운데 3개가 연속)
            if (stones.count(to_internal_piece(piece)) == 3 and 
                stones.count(None) == 2 and
                stones[0] is None and stones[4] is None):
                
                # 3개가 실제로 연속인지 확인
                piece_indices = [i for i, s in enumerate(stones) if s == to_internal_piece(piece)]
                if len(piece_indices) == 3 and max(piece_indices) - min(piece_indices) == 2:
                    count += 1
                    if debug:
                        logger.info(f"열린삼 발견: {coords}, 패턴: {stones}")
    
    return count

def check_open_three(board, x, y, piece, debug=False):
   """열린 3목 체크"""
   return count_open_threes(board, x, y, piece, debug) > 0

def check_open_four(board, x, y, piece):
   """열린 4목 체크"""
   if board[y][x] is not None:
       return False
   board_copy = copy_board(board)
   board_copy[y][x] = to_internal_piece(piece)
   directions = [(1, 0), (0, 1), (1, 1), (1, -1)]
   
   for dx, dy in directions:
       count = 1
       empty_ends = 0
       for direction in [-1, 1]:
           nx, ny = x + dx * direction, y + dy * direction
           empty_before = False
           for _ in range(4):
               if not (0 <= nx < len(board_copy) and 0 <= ny < len(board_copy)):
                   break
               if board_copy[ny][nx] == to_internal_piece(piece):
                   if empty_before:
                       break
                   count += 1
               elif board_copy[ny][nx] is None:
                   if count < 4:
                       empty_before = True
                   else:
                       empty_ends += 1
                   break
               else:
                   break
               nx += dx * direction
               ny += dy * direction
           if 0 <= nx < len(board_copy) and 0 <= ny < len(board_copy) and board_copy[ny][nx] is None:
               empty_ends += 1
       if count == 4 and empty_ends == 2:
           return True
   return False

def evaluate_position(board, x, y, piece):
   """기존 평가 함수 - 호환성 유지"""
   ruleset = RULESETS.get("standard")
   return evaluate_position_enhanced(board, x, y, piece, ruleset)
