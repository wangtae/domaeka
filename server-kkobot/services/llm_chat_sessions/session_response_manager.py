"""
세션 응답 관리 모듈
- 세션 내 메시지 처리 및 응답 생성 관리
- 개인/그룹 채팅 별 응답 전략
"""
import time
from core.logger import logger
from core.utils.send_message import send_message_response
import core.globals as g


class SessionResponseManager:
    def __init__(self):
        self.personal_chats = {}  # 개인 세션 상태 관리
        self.group_chats = {}  # 그룹 세션 상태 관리

    async def handle_message(self, context, active_session):
        """메시지를 처리하고 응답 여부 결정"""
        session_type = active_session["type"]
        channel_id = context.get("channel_id")
        user_hash = context.get("user_hash")
        text = context.get("text", "")
        sender = context.get("sender", "")

        # 메시지 히스토리에 추가
        self._add_message_to_history(active_session["session"], sender, text)

        # 세션 타입에 따라 다른 처리
        if session_type == "private":
            # 개인 채팅은 모든 메시지에 응답
            return await self._handle_personal_message(context, active_session)
        else:
            # 그룹 채팅은 상황에 따라 결정
            return await self._handle_group_message(context, active_session)

    def _add_message_to_history(self, session, sender, text):
        """메시지를 세션 히스토리에 추가하고 메트릭 업데이트"""
        if "message_history" not in session:
            session["message_history"] = []

        # 메시지 추가
        session["message_history"].append({
            "role": "user",
            "name": sender,
            "content": text,
            "timestamp": time.time()
        })

        # 메트릭 업데이트
        self._update_metrics(session, is_bot=False)
        logger.debug(f"[세션응답] 사용자 메시지 추가 → 발신자: {sender}, 메트릭: {session.get('metrics', {})}")

    async def _handle_personal_message(self, context, active_session):
        """개인 채팅 메시지 처리 - 항상 응답"""
        logger.info(f"[세션응답] 개인채팅 메시지 처리 → 채널: {context.get('channel_id')}, 발신자: {context.get('sender')}")

        # 메시지 처리 및 응답 생성
        response = await self._generate_response(context, "private", active_session["session"])

        if response:
            # 응답 히스토리 추가
            self._add_response_to_history(active_session["session"], response)

            # 응답 전송
            await send_message_response(context, response)
            logger.info(f"[세션응답] 개인채팅 응답 전송 → 길이: {len(response)}")
            return True
        return False

    async def _handle_group_message(self, context, active_session):
        """그룹 채팅 메시지 처리 - 상황에 따라 결정"""
        channel_id = context.get("channel_id")
        sender = context.get("sender", "")

        # 이 채널의 상태 정보
        chat_state = self.group_chats.setdefault(channel_id, {
            "last_response_time": 0,
            "pending_messages": [],
            "responding": False
        })

        # 메시지 우선순위 평가
        priority = self._evaluate_message_priority(context, active_session)
        logger.debug(f"[세션응답] 그룹채팅 메시지 우선순위 → 채널: {channel_id}, 발신자: {sender}, 우선순위: {priority}/10")

        # 응답 결정
        should_respond = False
        current_time = time.time()

        # 고우선순위 메시지는 항상 응답
        if priority >= 8:  # 높은 우선순위 (질문, 멘션 등)
            should_respond = True
            logger.info(f"[세션응답] 고우선순위 메시지 → 응답 결정")
        # 중간 우선순위는 일정 시간 지났으면 응답
        elif priority >= 5 and current_time - chat_state["last_response_time"] > 30:
            should_respond = True
            logger.info(
                f"[세션응답] 중간우선순위 + 시간경과 → 응답 결정 (마지막응답후 {int(current_time - chat_state['last_response_time'])}초)")
        # 낮은 우선순위는 더 긴 시간 후에만 응답
        elif priority >= 2 and current_time - chat_state["last_response_time"] > 120:
            should_respond = True
            logger.info(
                f"[세션응답] 저우선순위 + 긴시간경과 → 응답 결정 (마지막응답후 {int(current_time - chat_state['last_response_time'])}초)")

        if should_respond:
            # 응답 생성 및 전송
            response = await self._generate_response(context, "group", active_session["session"])
            if response:
                # 응답 히스토리 추가
                self._add_response_to_history(active_session["session"], response)

                # 상태 업데이트
                chat_state["last_response_time"] = current_time
                chat_state["pending_messages"] = []

                # 응답 전송
                await send_message_response(context, response)
                logger.info(f"[세션응답] 그룹채팅 응답 전송 → 길이: {len(response)}")
                return True
        else:
            logger.debug(
                f"[세션응답] 응답하지 않음 → 우선순위: {priority}, 마지막응답: {int(current_time - chat_state['last_response_time'])}초 전")

        # 응답하지 않는 경우에도 메시지는 기록
        chat_state["pending_messages"].append(context)
        return False

    def _add_response_to_history(self, session, response):
        """봇 응답을 세션 히스토리에 추가하고 메트릭 업데이트"""
        if "message_history" not in session:
            session["message_history"] = []

        # 응답 추가
        session["message_history"].append({
            "role": "assistant",
            "content": response,
            "timestamp": time.time()
        })

        # 메트릭 업데이트
        self._update_metrics(session, is_bot=True)
        logger.debug(f"[세션응답] 봇 응답 추가 → 메트릭: {session.get('metrics', {})}")

    def _evaluate_message_priority(self, context, active_session):
        """메시지 우선순위 평가 (1-10 척도)"""
        text = context.get("text", "").lower()
        is_mention = context.get("is_mention", False)

        priority = 5  # 기본 우선순위

        # 멘션이 있으면 최우선
        if is_mention:
            priority = 10

        # 질문 형태면 높은 우선순위
        if "?" in text or any(kw in text for kw in ["어떻게", "왜", "무엇", "언제", "어디", "누구"]):
            priority += 3

        # 감정 표현이 강하면 우선순위 상승
        if any(em in text for em in ["감사", "고마", "화나", "슬프", "기쁘", "사랑"]):
            priority += 1

        # 봇 이름이 언급되면 우선순위 상승
        bot_name = active_session["session"].get("bot_name", "")
        if "봇" in text or bot_name.lower() in text:
            priority += 2

        return min(priority, 10)  # 최대 10점

    def _update_metrics(self, session, is_bot=False):
        """세션 메트릭 업데이트"""
        if "metrics" not in session:
            session["metrics"] = {"total_messages": 0, "user_messages": 0, "bot_messages": 0}

        session["metrics"]["total_messages"] = session["metrics"].get("total_messages", 0) + 1

        if is_bot:
            session["metrics"]["bot_messages"] = session["metrics"].get("bot_messages", 0) + 1
        else:
            session["metrics"]["user_messages"] = session["metrics"].get("user_messages", 0) + 1

    async def _generate_response(self, context, session_type, session):
        """세션 응답 생성 (순환 참조 방지용)"""
        # 순환 참조 문제를 피하기 위해 session_processor.py의 함수 직접 호출
        from services.llm_chat_sessions.session_processor import generate_session_response
        return await generate_session_response(context, session_type, session)


# 전역 인스턴스 생성
response_manager = SessionResponseManager()
