"""
세션 상태 저장소 모듈
- 세션 데이터 저장 및 관리
- 활성 세션 및 사용량 저장
"""
from collections import defaultdict, deque
import time
import hashlib
from core.logger import logger

# 활성 세션 저장소
active_sessions = {
    "private": {},  # user_hash -> 세션 정보
    "group": {}  # channel_id -> 세션 정보
}

# 사용자별 일일 사용량 추적
daily_usage = defaultdict(lambda: {
    "private": 0,
    "group": 0,
    "last_reset_day": 0  # 마지막 리셋 날짜 (일 단위)
})

# 채널별 일일 사용량 추적
channel_daily_usage = defaultdict(lambda: {
    "group": 0,
    "last_reset_day": 0  # 마지막 리셋 날짜 (일 단위)
})


# 세션 ID 생성기
def generate_session_id():
    """고유한 세션 ID 생성"""
    now = time.time()
    random_part = hashlib.md5(f"{now}_{hash(str(now))}".encode()).hexdigest()
    return f"session_{int(now)}_{random_part[:8]}"


# 사용량 리셋 체크 (자정 기준)
def check_and_reset_daily_limits():
    """일일 사용량 리셋 체크 (자정 기준)"""
    current_day = int(time.time() / 86400)  # Unix timestamp 기준 일수

    # 사용자별 사용량 리셋
    for user_hash, usage in daily_usage.items():
        if usage["last_reset_day"] < current_day:
            usage["private"] = 0
            usage["group"] = 0
            usage["last_reset_day"] = current_day

    # 채널별 사용량 리셋
    for channel_id, usage in channel_daily_usage.items():
        if usage["last_reset_day"] < current_day:
            usage["group"] = 0
            usage["last_reset_day"] = current_day

    logger.debug(f"[세션저장소] 일일 사용량 리셋 체크 → 현재일: {current_day}")


# 세션 정보 생성 도우미 함수
def create_private_session_info(user_hash, channel_id, sender_name, room_name, timeout_minutes=10, offer_extension=True,
                                max_extensions=2):
    """개인 세션 정보 생성"""
    session_id = generate_session_id()

    return {
        "session_id": session_id,
        "channel_id": channel_id,
        "start_time": time.time(),
        "end_time": time.time() + (timeout_minutes * 60),
        "user_hash": user_hash,
        "user_name": sender_name,
        "room_name": room_name,
        "message_history": deque(maxlen=20),
        "extension_offered": False,
        "extensions_used": 0,
        "offer_extension": offer_extension,
        "max_extensions": max_extensions,
        "metrics": {
            "total_messages": 0,
            "user_messages": 0,
            "bot_messages": 0
        }
    }


def create_group_session_info(channel_id, initiator_hash, initiator_name, room_name, timeout_minutes=15,
                              offer_extension=True, max_extensions=2):
    """그룹 세션 정보 생성"""
    session_id = generate_session_id()

    return {
        "session_id": session_id,
        "channel_id": channel_id,
        "start_time": time.time(),
        "end_time": time.time() + (timeout_minutes * 60),
        "initiator_hash": initiator_hash,
        "initiator_name": initiator_name,
        "room_name": room_name,
        "participants": {initiator_hash: initiator_name},
        "message_history": deque(maxlen=30),
        "extension_offered": False,
        "extensions_used": 0,
        "offer_extension": offer_extension,
        "max_extensions": max_extensions,
        "metrics": {
            "total_messages": 0,
            "user_messages": 0,
            "bot_messages": 0
        }
    }
