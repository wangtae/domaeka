"""
세션 스케줄러 모듈
- 세션 만료 확인 및 알림 처리
- 연장 알림 관리
"""
import asyncio
import time
from core.logger import logger
from core.utils.send_message import send_direct_message
import core.globals as g

# session_manager.py에서 구현된 함수들 import
from core.sessions import session_manager


async def check_expiring_sessions():
    """세션 만료 및 연장 알림 체크 (주기적으로 실행)"""
    logger.debug("[세션스케줄러] 만료 세션 확인 시작")

    current_time = time.time()

    # 개인 세션 확인
    for user_hash, session in list(session_manager.active_sessions["private"].items()):
        await check_session_expiry("private", user_hash, session, current_time)

    # 그룹 세션 확인
    for channel_id, session in list(session_manager.active_sessions["group"].items()):
        await check_session_expiry("group", channel_id, session, current_time)


async def check_session_expiry(session_type, session_key, session, current_time):
    """개별 세션 만료 확인 및 처리"""
    try:
        # 세션이 만료되었는지 확인
        if current_time >= session["end_time"]:
            channel_id = session["channel_id"]
            result = await session_manager.end_session(session_type, session_key, reason="시간 만료")

            # 대화 참여 히스토리 초기화 (end_session 함수 내에서 처리됨)

            # 세션 종료 메시지 전송
            context = get_context_from_session(session)
            if session_type == "private":
                farewell_message = f"""📱 개인 채팅 세션이 종료되었습니다.
                
⏱️ 총 대화 시간: {result["duration_minutes"]}분
💬 주고받은 메시지: {result["metrics"]["total_messages"]}개

다시 대화하고 싶으시면 '# 채팅시작' 명령어를 입력해주세요.
즐거운 시간이었습니다! 👋"""
            else:  # group
                participants_count = len(session.get("participants", {}))
                farewell_message = f"""👨‍👩‍👧‍👦 그룹 채팅 세션이 종료되었습니다.
                
⏱️ 총 대화 시간: {result["duration_minutes"]}분
💬 주고받은 메시지: {result["metrics"]["total_messages"]}개
👥 참여한 사용자: {participants_count}명

다시 그룹 대화를 시작하려면 '# 그룹채팅시작' 명령어를 입력해주세요.
모두와 대화할 수 있어 즐거웠습니다! 👋"""
            
            success = await send_direct_message(context, farewell_message)
            if success:
                logger.info(f"[세션스케줄러] 세션 종료 메시지 전송 → 유형: {session_type}, ID: {session.get('session_id')}")
            else:
                logger.warning(f"[세션스케줄러] 세션 종료 메시지 전송 실패 → 유형: {session_type}, ID: {session.get('session_id')}")
            return

        # 연장 알림이 필요한지 확인 (만료 2분 전 & 연장 옵션 활성화 & 아직 알림 안함)
        time_left = session["end_time"] - current_time
        if time_left <= 120 and session["offer_extension"] and not session["extension_offered"]:
            # 연장 제안 메시지 전송
            context = get_context_from_session(session)
            extensions_used = session.get("extensions_used", 0)
            max_extensions = session.get("max_extensions", 2)

            if extensions_used < max_extensions:
                remaining_extensions = max_extensions - extensions_used
                message = f"""⏰ 채팅 세션이 2분 후에 종료됩니다.
                 
계속 대화하려면 '# 시간연장' 명령어를 입력해주세요. (남은 연장 기회: {remaining_extensions}회)"""
            else:
                message = "⏰ 채팅 세션이 2분 후에 종료됩니다. 더 이상 연장할 수 없습니다."

            success = await send_direct_message(context, message)
            if success:
                # 알림 플래그 설정
                session["extension_offered"] = True
                logger.info(f"[세션스케줄러] 연장 알림 전송 → 유형: {session_type}, ID: {session['session_id']}")
            else:
                logger.warning(f"[세션스케줄러] 연장 알림 전송 실패 → 유형: {session_type}, ID: {session['session_id']}")

    except Exception as e:
        logger.exception(f"[세션스케줄러] 세션 확인 중 오류: {str(e)}")


def get_context_from_session(session):
    """세션에서 메시지 전송에 필요한 컨텍스트 추출"""
    bot_name = session.get("bot_name", "")  # session에서 bot_name 가져오기, 없으면 빈 문자열
    channel_id = session["channel_id"]
    room_name = session.get("room_name", "알 수 없는 방")

    # context 구성 (writer는 send_direct_message에서 자동으로 찾음)
    context = {
        'bot_name': bot_name,
        'channel_id': channel_id,
        'room': room_name
    }

    return context


async def session_scheduler_task():
    """세션 스케줄러 주기적 실행 태스크"""
    logger.info("[세션스케줄러] 세션 스케줄러 태스크 시작")

    while True:
        try:
            await check_expiring_sessions()
            await asyncio.sleep(30)  # 30초마다 확인
        except Exception as e:
            logger.error(f"[세션스케줄러] 오류 발생: {str(e)}")
            await asyncio.sleep(60)  # 오류 발생 시 1분 후 재시도
