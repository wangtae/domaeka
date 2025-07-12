"""
세션 관리 모듈 
- 세션 생성, 연장, 종료 관리
- 세션 상태 조회 및 관리
"""
import time
import asyncio
from collections import defaultdict, deque
import hashlib
from datetime import datetime
from core.logger import logger
import core.globals as g
from core.sessions.session_store import (
    active_sessions, daily_usage, channel_daily_usage,
    generate_session_id, check_and_reset_daily_limits,
    create_private_session_info, create_group_session_info
)


async def create_private_session(user_hash, channel_id, sender_name, room_name, settings):
    """개인 채팅 세션 생성"""
    check_and_reset_daily_limits()

    # 필수 값 확인
    if not user_hash or not channel_id or not sender_name:
        logger.error(f"[세션관리] 개인 세션 생성 실패 - 필수값 누락 → user_hash={user_hash}, channel_id={channel_id}")
        return {
            "success": False,
            "message": "세션 생성에 필요한 정보가 부족합니다."
        }

    # 이미 세션이 있는지 확인
    if user_hash in active_sessions["private"]:
        logger.warning(f"[세션관리] 개인 세션 중복 생성 시도 → 사용자: {sender_name}")
        return {
            "success": False,
            "message": "이미 진행 중인 개인 채팅 세션이 있습니다."
        }

    # 일일 제한 확인
    daily_limit = settings.get("daily_limit_per_user", 1)
    if daily_limit > 0 and daily_usage[user_hash]["private"] >= daily_limit:
        logger.warning(f"[세션관리] 개인 세션 일일 한도 초과 → 사용자: {sender_name}, 한도: {daily_limit}")
        return {
            "success": False,
            "message": f"오늘 개인 채팅 세션 사용 한도({daily_limit}회)를 모두 사용했습니다."
        }

    # 새 세션 생성
    timeout_minutes = settings.get("session_timeout_minutes", 10)
    offer_extension = settings.get("offer_extension", True)
    max_extensions = settings.get("max_extensions", 2)

    session_info = create_private_session_info(
        user_hash, channel_id, sender_name, room_name,
        timeout_minutes=timeout_minutes, offer_extension=offer_extension, max_extensions=max_extensions
    )
    active_sessions["private"][user_hash] = session_info

    # 사용량 증가
    daily_usage[user_hash]["private"] += 1

    logger.info(f"[세션관리] 개인 채팅 세션 생성 → ID: {session_info['session_id']}, 사용자: {sender_name}, 종료예정: {timeout_minutes}분 후")

    return {
        "success": True,
        "session_id": session_info["session_id"],
        "end_time": session_info["end_time"],
        "message": f"개인 채팅 세션이 시작되었습니다. {timeout_minutes}분 동안 대화할 수 있습니다."
    }


async def create_group_session(channel_id, initiator_hash, initiator_name, room_name, settings):
    """그룹 채팅 세션 생성"""
    check_and_reset_daily_limits()

    # 필수 값 확인
    if not channel_id or not initiator_hash or not initiator_name:
        logger.error(f"[세션관리] 그룹 세션 생성 실패 - 필수값 누락 → channel_id={channel_id}")
        return {
            "success": False,
            "message": "세션 생성에 필요한 정보가 부족합니다."
        }

    # 이미 세션이 있는지 확인
    if channel_id in active_sessions["group"]:
        logger.warning(f"[세션관리] 그룹 세션 중복 생성 시도 → 채널: {room_name}, 요청자: {initiator_name}")
        return {
            "success": False,
            "message": "이미 진행 중인 그룹 채팅 세션이 있습니다."
        }

    # 일일 제한 확인 - 채널 기준
    daily_limit = settings.get("daily_limit_per_room", 1)
    if daily_limit > 0 and channel_daily_usage[channel_id]["group"] >= daily_limit:
        logger.warning(f"[세션관리] 그룹 세션 일일 한도 초과 → 채널: {room_name}, 한도: {daily_limit}")
        return {
            "success": False,
            "message": f"오늘 이 채팅방의 그룹 채팅 세션 사용 한도({daily_limit}회)를 모두 사용했습니다."
        }

    # 새 세션 생성
    timeout_minutes = settings.get("session_timeout_minutes", 15)
    offer_extension = settings.get("offer_extension", True)
    max_extensions = settings.get("max_extensions", 2)

    session_info = create_group_session_info(
        channel_id, initiator_hash, initiator_name, room_name,
        timeout_minutes=timeout_minutes, offer_extension=offer_extension, max_extensions=max_extensions
    )
    active_sessions["group"][channel_id] = session_info

    # 사용량 증가
    channel_daily_usage[channel_id]["group"] += 1

    logger.info(
        f"[세션관리] 그룹 채팅 세션 생성 → ID: {session_info['session_id']}, 채널: {room_name}, 시작자: {initiator_name}, 종료예정: {timeout_minutes}분 후")

    return {
        "success": True,
        "session_id": session_info["session_id"],
        "end_time": session_info["end_time"],
        "message": f"그룹 채팅 세션이 시작되었습니다. {timeout_minutes}분 동안 모두 함께 대화할 수 있습니다."
    }


async def extend_session(session_type, session_key, minutes=5):
    """세션 시간 연장"""
    if session_type not in ["private", "group"]:
        logger.error(f"[세션관리] 잘못된 세션 유형 → {session_type}")
        return {"success": False, "message": "잘못된 세션 유형입니다."}

    if session_key not in active_sessions[session_type]:
        logger.warning(f"[세션관리] 연장 실패 - 세션 없음 → 유형: {session_type}, 키: {session_key}")
        return {"success": False, "message": "활성 세션이 없습니다."}

    session = active_sessions[session_type][session_key]

    # 이미 최대 연장 횟수를 사용했는지 확인
    max_extensions = session.get("max_extensions", 2)
    if session["extensions_used"] >= max_extensions:
        logger.warning(
            f"[세션관리] 연장 실패 - 최대 연장 횟수 초과 → ID: {session['session_id']}, 사용: {session['extensions_used']}, 최대: {max_extensions}")
        return {
            "success": False,
            "message": f"최대 연장 횟수({max_extensions}회)를 모두 사용했습니다."
        }

    # 세션 연장
    session["end_time"] += minutes * 60
    session["extensions_used"] += 1
    session["extension_offered"] = False

    # 현재 메트릭 정보도 로깅
    metrics = session.get("metrics", {})
    logger.info(
        f"[세션관리] 세션 연장 → 유형: {session_type}, ID: {session['session_id']}, 추가 시간: {minutes}분, "
        f"사용 연장: {session['extensions_used']}/{max_extensions}, "
        f"현재 메시지 수: {metrics.get('total_messages', 0)} (사용자: {metrics.get('user_messages', 0)}, 봇: {metrics.get('bot_messages', 0)})")

    return {
        "success": True,
        "new_end_time": session["end_time"],
        "message": f"채팅 세션이 {minutes}분 연장되었습니다. (연장 {session['extensions']}/{max_extensions}회 사용)"
    }


async def end_session(session_type, session_key, reason="사용자 요청"):
    """세션 종료"""
    if session_type not in ["private", "group"] or session_key not in active_sessions[session_type]:
        logger.warning(f"[세션관리] 종료 실패 - 세션 없음 → 유형: {session_type}, 키: {session_key}")
        return {"success": False, "message": "활성 세션이 없습니다."}

    session = active_sessions[session_type][session_key]
    channel_id = session["channel_id"]

    # 세션 요약 정보
    total_seconds = int(time.time() - session["start_time"])
    calculated_minutes = total_seconds // 60
    calculated_seconds = total_seconds % 60

    metrics = session["metrics"]
    session_id = session["session_id"]

    # 채널별 대화참여 히스토리 초기화
    if channel_id in g.message_history:
        del g.message_history[channel_id]
        logger.info(f"[세션관리] 채널 {channel_id} 대화 히스토리 초기화 완료")

    if channel_id in g.last_join_time:
        del g.last_join_time[channel_id]
        logger.info(f"[세션관리] 채널 {channel_id} 마지막 대화참여 시간 초기화 완료")

    # 세션 삭제
    del active_sessions[session_type][session_key]
    logger.info(
        f"[세션관리] 세션 종료 → 유형: {session_type}, ID: {session_id}, 원인: {reason}, "
        f"총 대화: {metrics['total_messages']}개, "
        f"지속 시간: {calculated_minutes}분 {calculated_seconds}초")

    return {
        "success": True,
        "duration_minutes": calculated_minutes,
        "metrics": metrics,
        "message": "세션이 종료되었습니다."
    }


def get_active_session(user_hash=None, channel_id=None):
    """활성 세션 조회 (개인/그룹 통합) - 동시 활성화 불가 규칙 적용"""
    # 개인 세션 검색
    if user_hash and user_hash in active_sessions["private"]:
        session = active_sessions["private"][user_hash]
        # 개인 세션인데 그룹 채팅에서 호출된 경우 방지
        if channel_id and session["channel_id"] != channel_id:
            logger.warning(f"[세션관리] 개인 세션이 다른 채널에서 조회 시도됨 → user_hash={user_hash}, 요청 채널={channel_id}")
            return None
        return {"type": "private", "key": user_hash, "session": session}

    # 그룹 세션 검색
    if channel_id and channel_id in active_sessions["group"]:
        session = active_sessions["group"][channel_id]
        # 그룹 세션인데 개인 채팅에서 호출된 경우 방지
        if user_hash and session.get("initiator_hash") != user_hash and user_hash not in session.get("participants", {}):
            logger.warning(f"[세션관리] 그룹 세션이 관련 없는 사용자에 의해 조회 시도됨 → channel_id={channel_id}, 요청 사용자={user_hash}")
            return None
        return {"type": "group", "key": channel_id, "session": session}

    return None


def get_all_active_sessions():
    """모든 활성 세션 반환"""
    all_sessions = {"private": [], "group": []}
    for user_hash, session in active_sessions["private"].items():
        all_sessions["private"].append({"key": user_hash, "session": session})
    for channel_id, session in active_sessions["group"].items():
        all_sessions["group"].append({"key": channel_id, "session": session})
    return all_sessions


def get_session_stats():
    """현재 세션 통계 반환"""
    private_count = len(active_sessions["private"])
    group_count = len(active_sessions["group"])
    return {"private_sessions": private_count, "group_sessions": group_count}


def cleanup_expired_sessions():
    """만료된 세션 정리 (주기적으로 호출)"""
    current_time = time.time()
    expired_private = []
    for user_hash, session in list(active_sessions["private"].items()):
        if current_time >= session["end_time"]:
            expired_private.append(user_hash)
    for user_hash in expired_private:
        end_session("private", user_hash, reason="만료된 세션 자동 정리")

    expired_group = []
    for channel_id, session in list(active_sessions["group"].items()):
        if current_time >= session["end_time"]:
            expired_group.append(channel_id)
    for channel_id in expired_group:
        end_session("group", channel_id, reason="만료된 세션 자동 정리")

    logger.debug("[세션관리] 만료된 세션 정리 완료")


async def session_cleanup_task():
    """세션 정리 백그라운드 태스크"""
    logger.info("[세션관리] 세션 정리 태스크 시작")
    while True:
        try:
            cleanup_expired_sessions()
            await asyncio.sleep(g.SESSION_CLEANUP_INTERVAL)  # globals에 정의된 간격 사용
        except Exception as e:
            logger.error(f"[세션관리] 세션 정리 태스크 오류 발생: {str(e)}", exc_info=True)
            await asyncio.sleep(g.SESSION_CLEANUP_INTERVAL * 2)  # 오류 시 더 긴 간격으로 재시도
