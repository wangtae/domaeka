import re

MEANINGFUL_KEYWORDS = {
    '감사', '고마워', '추천', '질문', '설명', '기도', '생각',
    '성경', '말씀', '예수', '주님', '하나님', '찬양', '묵상',
    '고맙다', '알려줘', '알려주세요', '정리', '찾아줘', '분석',
    '힘들어', '기뻐', '좋다', '싫다', '화나', '재밌어', '어려워',
    '왜', '무엇', '어떻게', '시작', '멈춰', '다시', '리셋', '토론',
    '견혜', '의견', '네요', '아요', '해요', '네요', '니다',
    '어요', '버림', '에요', '습니다', '하죠', '있죠', '구요', '고요', '이죠',
    '까요', '가요', '렇죠', '때문', '이유', '목적', '근거', '논리', '게요', '군요'
}


def is_natural_korean_message(message: str, type="") -> bool:
    stripped = message.strip()

    if type == "pass":
        return message

    if len(stripped) < 3:
        return False

    if stripped in {'ㅋㅋ', 'ㅎㅎ', '응', '넹', '웅', 'ㅠㅠ', 'ㅜㅜ', '엥'}:
        return False

    if re.fullmatch(r'[ㅋㅎㅠㅜ!?~,.ㄱㄴㄷㅂㅈ🤣😂😅😭👍❤️💕💯🔥😱🥲]{2,}', stripped):
        return False

    total_len = len(stripped)
    korean_count = len(re.findall(r'[가-힣]', stripped))

    if total_len > 0 and korean_count / total_len < 0.3:
        return False

    # 보조 키워드 포함 시 의미 있다고 판단
    if any(keyword in stripped for keyword in MEANINGFUL_KEYWORDS):
        return True

    return korean_count >= 3
