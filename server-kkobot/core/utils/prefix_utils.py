# ✅ core/utils/prefix_utils.py (수정된 버전: aliases에 정규표현식 지원)
import re
import core.globals as g
from core.logger import logger

PREFIX_MAP = g.PREFIX_MAP

# 정규식 컴파일 결과 캐싱
_pattern_cache = {}

def _make_hashable(obj):
    if isinstance(obj, dict):
        return frozenset({k: _make_hashable(v) for k, v in obj.items()}.items())
    elif isinstance(obj, list):
        return tuple(_make_hashable(elem) for elem in obj)
    else:
        return obj

# Helper function to check if a string is likely a regex pattern
def is_regex_pattern(s: str) -> bool:
    # A simple heuristic: if it starts with '^' or contains common regex metacharacters
    # This might need refinement for more complex cases, but should work for common usage.
    return s.startswith('^') or re.search(r'[\.\+\*\?\(\)\[\]\{\}\$]', s) is not None


# Helper function to extract the prompt after a matched part
# For literal matches, `matched_part` is the literal prefix/alias
# For regex matches, `matched_part` is the actual string matched by the regex
def extract_prompt(message: str, matched_part: str) -> str:
    if message.startswith(matched_part): # matched_part가 message의 시작 부분에 정확히 일치하는지 확인
        return message[len(matched_part):].strip()
    return "" # If matched_part isn't at the beginning, something went wrong, return empty.

def generate_keyword_pattern(prefix: str, command_info: dict) -> str:
    """
    핵심 키워드 기반으로 유연한 정규표현식 패턴 생성
    """
    keywords = command_info.get("keywords", [])
    keyword_aliases = command_info.get("keyword_aliases", {})
    optional_words = command_info.get("optional_words", [])
    
    # 접두어 추출 (e.g., "> 나의 mbti는?" → ">")
    prefix_part = prefix.split()[0] if " " in prefix else prefix
    escaped_prefix = re.escape(prefix_part)
    
    # 키워드 패턴 생성 (동의어 포함)
    keyword_patterns = []
    for keyword in keywords:
        aliases = keyword_aliases.get(keyword, [keyword])
        if any(word.isalpha() for word in aliases): # 영문 포함 시 대소문자 무관
            alias_pattern = "|".join([re.escape(alias) for alias in aliases])
            keyword_patterns.append(f"(?i:{alias_pattern})")
        else:
            alias_pattern = "|".join([re.escape(alias) for alias in aliases])
            keyword_patterns.append(f"({alias_pattern})")
    
    # 모든 키워드가 순서 무관하게 포함되어야 함 (Lookaheads)
    keyword_lookaheads = [f"(?=.*{pattern})" for pattern in keyword_patterns]
    
    # 허용된 문자 패턴 (한글, 영문, 숫자, 선택적 단어, 공백)
    # 기존 제안에 숫자가 없었지만, 실제 유연성을 위해 추가함.
    if optional_words:
        escaped_optional = [re.escape(word) for word in optional_words]
        allowed_chars = f"[{''.join(escaped_optional)}\s가-힣a-zA-Z0-9]*"
    else:
        allowed_chars = "[\s가-힣a-zA-Z0-9]*"
    
    # 최종 패턴: 접두어 + 모든 키워드 lookahead + 허용된 문자들
    lookaheads = "".join(keyword_lookaheads)
    return f"^{escaped_prefix}\s*{lookaheads}{allowed_chars}$"

def get_compiled_pattern(prefix: str, command_info: dict) -> re.Pattern:
    # command_info를 해시 가능한 형태로 변환
    hashable_command_info = _make_hashable(command_info)
    cache_key = f"{prefix}_{hash(hashable_command_info)}"
    if cache_key not in _pattern_cache:
        pattern_str = generate_keyword_pattern(prefix, command_info)
        _pattern_cache[cache_key] = re.compile(pattern_str)
    return _pattern_cache[cache_key]

# ✅ parse_prefix 개선 (fallback 방지)
def parse_prefix(message: str, bot_name=None):
    message = message.strip()

    # Sort all prefixes in PREFIX_MAP by length (descending) for consistent prioritization
    # This applies to all matching steps to prioritize longer, more specific matches.
    # The sorting key is the main prefix string.
    sorted_prefixes_and_info = sorted(
        g.PREFIX_MAP.items(),
        key=lambda item: len(item[0]),
        reverse=True
    )

    # 1. 수동 정규식 별칭 매칭 (최우선) - docs 설계 기반
    for main_prefix, info in sorted_prefixes_and_info:
        manual_aliases = info.get("aliases", [])
        for alias_pattern in manual_aliases:
            if is_regex_pattern(alias_pattern): # 별칭이 정규식 패턴인지 확인
                try:
                    match_obj = re.match(alias_pattern, message)
                    if match_obj:
                        # 정규식 매치된 실제 문자열 부분을 사용하여 프롬프트 추출
                        matched_string = match_obj.group(0)
                        logger.debug(f"[PREFIX_MATCH_REGEX_ALIAS] alias match: {alias_pattern} -> {main_prefix} (Matched: {matched_string})")
                        return main_prefix, extract_prompt(message, matched_string)
                except re.error as e:
                    logger.warning(f"[REGEX_ALIAS_ERROR] alias 정규식 오류: {alias_pattern} -> {e}")
                    continue

    # 2. 키워드 기반 유연 매칭 (핵심 기능) - docs 설계 기반
    for main_prefix, info in sorted_prefixes_and_info:
        if info.get("keywords"): # 'keywords' 속성이 있고 리스트가 비어있지 않은 명령어만 처리
            compiled_pattern = get_compiled_pattern(main_prefix, info)
            match_obj = compiled_pattern.match(message)
            if match_obj:
                matched_string = match_obj.group(0)
                logger.debug(f"[PREFIX_MATCH_KEYWORD_FLEXIBLE] keyword flexible match: {compiled_pattern.pattern} -> {main_prefix} (Matched: {matched_string})")
                return main_prefix, extract_prompt(message, matched_string)

    # 3. 정확한 텍스트 별칭 매칭 (docs의 3단계) - 정규식이 아닌 텍스트 별칭 처리
    for main_prefix, info in sorted_prefixes_and_info:
        text_aliases = info.get("aliases", [])
        for alias_text in text_aliases:
            if not is_regex_pattern(alias_text): # 별칭이 정규식이 아닌 경우에만 처리
                # 정확히 별칭과 일치하거나, 별칭 뒤에 공백이 붙는 형태로 시작하는 경우
                if message == alias_text: # 정확히 별칭과 일치하는 경우
                    logger.debug(f"[PREFIX_MATCH_EXACT_ALIAS] exact alias match: {alias_text} -> {main_prefix}")
                    return main_prefix, "" # 정확히 일치하면 프롬프트는 없음
                elif message.startswith(alias_text + " "): # 별칭 뒤에 공백이 붙는 형태로 시작하는 경우
                    logger.debug(f"[PREFIX_MATCH_TEXT_ALIAS_WITH_PROMPT] text alias match: {alias_text} -> {main_prefix}")
                    return main_prefix, message[len(alias_text + " "):].strip()

    # 4. 메인 접두어 매칭 (docs의 4단계) - 가장 기본적인 접두어 매칭
    for main_prefix, info in sorted_prefixes_and_info:
        if message.startswith(main_prefix): # 메시지가 메인 접두어로 시작하는지 확인
            logger.debug(f"[PREFIX_MATCH_MAIN] main prefix match: {main_prefix}")
            return main_prefix, message[len(main_prefix):].strip()

    return None, message


def parse_mention_analysis_request(prompt: str):
    """
    '@요한', '@요한 #{channel_id}' 등의 형식과 함께
    '성격', '성향', '어떤 사람', '누구', '분석', 'mbti', '애니어그램' 등의 키워드가 포함되었는지 확인.
    반환: (user_name, channel_id, analysis_type) 또는 (None, None, None)
    """
    # '@이름' 추출
    mention_match = re.search(r"@(?P<name>\S+)", prompt)
    if not mention_match:
        return None, None, None

    user_name = mention_match.group("name")

    # '#채널ID' 추출 (예: #12345678901234567)
    channel_id_match = re.search(r"#(?P<channel_id>\d+)", prompt)
    channel_id = channel_id_match.group("channel_id") if channel_id_match else None

    lower_prompt = prompt.lower()

    # 분석 유형별 키워드 매핑
    profile_keywords = ["성격", "성향", "어떤 사람", "누구", "분석"]
    mbti_keywords = ["mbti", "MBTI"]
    enneagram_keywords = ["애니어그램", "에니어그램"]

    if any(k in lower_prompt for k in profile_keywords):
        analysis_type = "profile"
    elif any(k in lower_prompt for k in mbti_keywords):
        analysis_type = "mbti"
    elif any(k in lower_prompt for k in enneagram_keywords):
        analysis_type = "enneagram"
    else:
        return None, None, None

    return user_name, channel_id, analysis_type
