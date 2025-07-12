"""
세션 내 메시지 처리 모듈
- 채팅 세션 내 메시지 처리 및 응답 생성
- 세션 메시지 기록 관리
"""
from collections import deque
import time
import json
from datetime import datetime
from core.logger import logger
from core.utils.send_message import send_message_response
import core.globals as g
from services.llm_fallback_service import call_llm_with_fallback

# session_manager.py에서 구현된 함수들 import
from core.sessions.session_manager import get_active_session

# 세션 응답 관리자 사용 여부 확인 (순환 참조 방지를 위한 지연 임포트 사용)
USE_RESPONSE_MANAGER = True


async def process_session_message(context):
    """세션 내 메시지 처리"""
    if USE_RESPONSE_MANAGER:
        # 순환 참조 방지를 위한 지연 임포트
        from services.llm_chat_sessions.session_response_manager import response_manager

    bot_name = context.get("bot_name", "")
    channel_id = context.get("channel_id")
    room = context.get("room")
    sender = context.get("sender")
    user_hash = context.get("user_hash")
    message_text = context.get("text", "")
    timestamp = context.get("timestamp", time.time())

    logger.info(f"[세션디버깅] process_session_message 호출됨 → 채널: {channel_id}, 사용자: {sender}, 메시지: {message_text[:30]}...")

    # 기본 컨텍스트 확인
    if not channel_id or not user_hash or not message_text:
        logger.error(f"[세션메시지] 컨텍스트 불완전 → channel_id={channel_id}, user_hash={user_hash}")
        return False

    # 로그 설정 확인
    room_config = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
    log_settings = room_config.get("log_settings", {})
    disable_chat_logs = log_settings.get("disable_chat_logs", False)

    if not disable_chat_logs:
        logger.info(f"[세션메시지] 처리 시작 → 채널: {channel_id}, 발신자: {sender}")

    # 활성 세션 확인
    active_session = get_active_session(user_hash, channel_id)

    # 개인 세션 없으면 그룹 세션 확인
    if not active_session and channel_id:
        active_session = get_active_session(None, channel_id)

    if not active_session:
        logger.warning(f"[세션메시지] 활성 세션 없음 → 채널: {channel_id}, 사용자: {sender}")
        return False

    session_type = active_session["type"]
    session = active_session["session"]

    # 개인 세션인 경우 해당 사용자 메시지만 처리
    if session_type == "private" and active_session["key"] != user_hash:
        logger.debug(f"[세션메시지] 다른 사용자의 개인 세션 무시 → 사용자: {sender}, 소유자: {session.get('user_name')}")
        return False

    # 메시지가 세션 명령어인 경우 예외 처리
    if message_text.startswith("# 채팅") or message_text.startswith("# 시간연장"):
        logger.debug(f"[세션메시지] 세션 명령어 무시 → {message_text[:15]}")
        return False

    if USE_RESPONSE_MANAGER:
        # 새로운 응답 관리자 사용 - 우선순위 기반 처리
        processed = await response_manager.handle_message(context, active_session)
        return processed
    else:
        # 기존 방식 사용 - 모든 메시지 즉시 응답
        # 메시지 기록에 추가
        session["message_history"].append({
            "role": "user",
            "name": sender,
            "content": message_text,
            "timestamp": timestamp
        })

        # 메트릭 초기화 확인 후 업데이트
        if "metrics" not in session:
            session["metrics"] = {"total_messages": 0, "user_messages": 0, "bot_messages": 0}
            logger.info(f"[세션디버깅] 메트릭 초기화 → {session['metrics']}")

        # 메트릭 업데이트 (안전하게)
        session["metrics"]["total_messages"] = session["metrics"].get("total_messages", 0) + 1
        session["metrics"]["user_messages"] = session["metrics"].get("user_messages", 0) + 1

        logger.info(
            f"[세션디버깅] 사용자 메시지 메트릭 업데이트 → 총: {session['metrics']['total_messages']}, 사용자: {session['metrics']['user_messages']}")

        # 그룹 세션인 경우 참여자 목록 업데이트
        if session_type == "group" and user_hash not in session.get("participants", {}):
            session["participants"][user_hash] = sender
            logger.info(f"[세션메시지] 새 참여자 추가 → {sender}")

        # 응답 생성
        response = await generate_session_response(context, session_type, session)

        if not response:
            logger.error(f"[세션메시지] 응답 생성 실패 → 채널: {channel_id}, 사용자: {sender}")
            await send_message_response(context, "세션 응답 생성 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.")
            return True

        # 응답 기록 추가
        session["message_history"].append({
            "role": "assistant",
            "content": response,
            "timestamp": time.time()
        })

        # 메트릭 업데이트 - 봇 메시지 카운트
        session["metrics"]["total_messages"] = session["metrics"].get("total_messages", 0) + 1
        session["metrics"]["bot_messages"] = session["metrics"].get("bot_messages", 0) + 1

        logger.info(
            f"[세션디버깅] 봇 응답 메트릭 업데이트 → 총: {session['metrics']['total_messages']}, 봇: {session['metrics']['bot_messages']}")

        # 응답 전송
        await send_message_response(context, response)

        logger.info(f"[세션메시지] 응답 전송 완료 → 세션유형: {session_type}, 응답길이: {len(response)}")
        return True


async def generate_session_response(context, session_type, session):
    """세션 내용에 따른 응답 생성"""
    try:
        # 현재 메트릭 상태 로깅
        metrics = session.get("metrics", {})
        logger.debug(
            f"[세션응답] 메트릭 상태 → 총: {metrics.get('total_messages', 0)}, 사용자: {metrics.get('user_messages', 0)}, 봇: {metrics.get('bot_messages', 0)}")

        # 시스템 프롬프트 구성
        if session_type == "private":
            # 개인 채팅용 시스템 프롬프트
            system_prompt = f"""
            당신은 사용자 {session.get('user_name', '사용자')}와 1:1 대화를 나누고 있습니다. 
            이전 대화 내용을 기억하고 일관된 응답을 제공하세요.
            대화는 자연스럽고 친근하게 진행하되, 질문에 대해 정확한 정보를 제공하세요.
            현재 시간: {time.strftime('%Y-%m-%d %H:%M:%S')}
            
            대화 특성:
            - 개인 채팅이므로 해당 사용자의 질문과 관심사에 집중하세요
            - 이전 대화 맥락을 고려하여 응답하세요
            - 질문이 불명확하면 관련 정보를 물어보세요
            - 답변은 간결하고 명확하게 제공하세요
            """
        else:  # group
            # 그룹 채팅용 시스템 프롬프트
            participants = list(session.get("participants", {}).values())
            participants_str = ", ".join(participants) if len(
                participants) <= 5 else f"{', '.join(participants[:5])} 외 {len(participants) - 5}명"

            system_prompt = f"""
            당신은 여러 사용자({participants_str})가 참여하는 그룹 채팅에 있습니다.
            대화에 자연스럽게 참여하고, 질문에 답변하며, 주제에 관한 정보를 제공하세요.
            모든 참여자를 존중하고 대화를 촉진하세요.
            현재 시간: {time.strftime('%Y-%m-%d %H:%M:%S')}
            
            대화 특성:
            - 그룹 채팅이므로 누구나 볼 수 있는 방식으로 응답하세요
            - 대화를 독점하지 않고 모든 참여자가 참여할 수 있도록 하세요
            - 다양한 의견을 존중하고 중립적인 정보를 제공하세요
            - 질문한 사용자의 이름을 언급하며 응답하면 좋습니다
            """

        # 메시지 히스토리 구성
        recent_messages = list(session.get("message_history", []))[-100:]  # 최근 100개 메시지만 사용

        logger.debug(f"[세션응답] 히스토리 사용 → {len(recent_messages)}개 메시지")

        messages_str = "\n\n".join([
            f"[{msg.get('name', '사용자') if 'name' in msg else '사용자' if msg['role'] == 'user' else '봇'}]: {msg.get('content', '')}"
            for msg in recent_messages
        ])

        # 사용자의 마지막 메시지 추출 (가장 최근 사용자 메시지)
        last_user_message = None
        last_user_name = '사용자'
        for msg in reversed(recent_messages):
            if msg.get('role') == 'user':
                last_user_message = msg.get('content', '')
                last_user_name = msg.get('name', '사용자')
                break

        # 현재 시간 포맷팅
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        # 사용자 프롬프트 구성
        user_prompt = f"""
        현재 시간: {current_time}
        
        대화 내역:
        {messages_str}
        
        위 대화 내용을 바탕으로 자연스럽게 응답해주세요. 특히 가장 최근 메시지 "{last_user_message}"에 중점을 두고 응답하되, 이전 대화 맥락도 고려하세요.
        """

        # LLM 모델 설정
        providers = [
            {
                "name": "grok",
                "model": "grok-3-mini",
                "timeout": 30,
                "retry": 0,
                "system_prompt": system_prompt
            },
            {
                "name": "gemini",
                "model": "gemini-1.5-flash",
                "timeout": 30,
                "retry": 1,
                "system_prompt": system_prompt
            },
            {
                "name": "deepseek",
                "timeout": 30,
                "retry": 1,
                "system_prompt": system_prompt
            },
            {
                "name": "openai",
                "model": "gpt-3.5-turbo",
                "timeout": 30,
                "retry": 0,
                "system_prompt": system_prompt
            }
        ]

        # LLM으로 응답 생성
        response = await call_llm_with_fallback(context, user_prompt, providers)

        if not response:
            logger.warning(f"[세션메시지] LLM 응답 없음 → fallback 응답 사용")
            return "죄송합니다, 응답을 생성하는 과정에서 오류가 발생했습니다. 다시 질문해 주세요."

        return response.strip()

    except Exception as e:
        logger.exception(f"[세션메시지] 응답 생성 중 오류: {str(e)}")
        return "메시지 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요."
