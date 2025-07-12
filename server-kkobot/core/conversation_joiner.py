"""
대화 참여 모듈: 채팅방에서 특정 조건이 만족될 때 봇이 대화에 참여하도록 함
"""
import asyncio
import time
import logging
from collections import defaultdict, deque
from datetime import datetime
from dateutil.parser import parse as parse_datetime

import core.globals as g
from core.logger import logger
from services.command_dispatcher import call_llm_with_fallback
from core.utils.send_message import send_direct_message, send_message_response

from core.utils.prefix_utils import parse_prefix
from core.db_utils import is_meaningful_message
from core.globals import PREFIX_MAP
# conversation_joiner.py
from core.globals import LLM_DEFAULT_SYSTEM_PROMPT
from core.sessions.session_manager import get_active_session

# 채널별 메시지 히스토리 저장
message_history = defaultdict(lambda: deque(maxlen=100))

# 채널별 마지막 대화 참여 시간
last_join_time = {}

# 채널별 참여 시도 카운트
join_attempt_count = defaultdict(int)


def to_timestamp(ts):
    if isinstance(ts, (int, float)):
        return float(ts)
    elif isinstance(ts, str):
        try:
            return parse_datetime(ts).timestamp()
        except Exception:
            return 0
    return 0


async def conversation_join_monitor():
    # logger.info("[대화참여] 모니터링 시작")
    while True:
        try:
            schedule_data = g.schedule_rooms
            for bot_name, channels in schedule_data.items():
                for channel_id, data in channels.items():
                    if "conversation_join" in data and data["conversation_join"].get("enabled", False):
                        await check_channel_for_conversation_join(bot_name, channel_id, data)
            await asyncio.sleep(10)
        except Exception as e:
            logger.error(f"[대화참여] 모니터링 오류: {str(e)}")
            await asyncio.sleep(30)


async def check_channel_for_conversation_join(bot_name, channel_id, data):
    # ✅ 참여 금지 시간 확인
    block_until = g.conversation_block_until.get(channel_id, 0)
    if time.time() < block_until:
        logger.info(f"[대화참여] 차단된 채널입니다 → {channel_id}, {datetime.fromtimestamp(block_until).strftime('%H:%M:%S')}까지 차단")
        return

    try:
        settings = data["conversation_join"]
        time_window = settings.get("time_window", 10)
        message_threshold = settings.get("message_threshold", 5)
        cooldown = settings.get("cooldown", 30)
        join_every_n = settings.get("join_every_n", 1)

        # logger.debug(f"[대화참여] 체크 시작 → {channel_id} / 봇: {bot_name}")
        # logger.debug(
        #    f"[대화참여] 설정값 → time_window={time_window}, threshold={message_threshold}, cooldown={cooldown}, every_n={join_every_n}")

        # 채널에 활성화된 세션이 있는지 확인
        active_session = get_active_session(None, channel_id)
        if active_session:
            # logger.debug(f"[대화참여] 채널에 활성 세션 있음 → {channel_id}, 유형: {active_session['type']}")
            return  # 활성 세션이 있으면 자동 참여 건너뜀

        if channel_id not in message_history:
            # logger.debug(f"[대화참여] 메시지 없음 → 히스토리 미존재: {channel_id}")
            return

        current_time = time.time()

        # 메시지 필터링 (전역 설정값 적용)
        window_limit = g.CONVERSATION_JOIN_HISTORY_SECONDS
        recent_messages = [
            msg for msg in message_history[channel_id]
            if to_timestamp(msg.get("timestamp", 0)) >= current_time - window_limit
        ]

        message_history[channel_id] = deque(recent_messages, maxlen=100)

        if len([m for m in recent_messages if not m.get("is_bot")]) < message_threshold:
            # logger.debug(f"[대화참여] 사용자 메시지 부족 → {len(recent_messages)} < {message_threshold}")
            return

        # 새 메시지 여부 확인 - 키가 없으면 기본값 0으로 설정
        last_check_key = f"last_check_time_{channel_id}"
        last_check_time = getattr(g, last_check_key, 0)
        new_messages = [
            msg for msg in recent_messages
            if to_timestamp(msg.get("timestamp", 0)) > last_check_time
        ]

        # 현재 체크 시간 기록
        setattr(g, last_check_key, current_time)

        # 새 메시지가 없으면 처리 중단
        if not new_messages:
            # logger.debug(f"[대화참여] 새 메시지 없음 → 마지막 체크 이후 새 메시지 없음")
            return

        if cooldown > 0 and channel_id in last_join_time:
            elapsed = current_time - last_join_time[channel_id]
            # logger.debug(f"[대화참여] 마지막 참여로부터 경과: {elapsed:.1f}초")
            if elapsed < cooldown * 60:
                logger.debug(f"[대화참여] 쿨다운 중 → 필요: {cooldown * 60:.1f}, 현재: {elapsed:.1f}")
                return

        # ✅ cooldown이 0인 경우에도 마지막 메시지 이후 새 메시지 체크
        if channel_id in last_join_time:
            last_msg_time = max(to_timestamp(msg.get("timestamp")) for msg in recent_messages)
            if last_msg_time <= last_join_time[channel_id]:
                # logger.debug(f"[대화참여] 새 메시지 없음 → 마지막 참여 이후 메시지 없음")
                return

        # ✅ 마지막 메시지가 챗봇 응답이면 참여 생략
        last_msg = recent_messages[-1]
        if last_msg.get("sender") == bot_name and last_msg.get("is_bot"):
            # logger.debug(f"[대화참여] 마지막 메시지가 챗봇 응답 → 참여 생략")
            return

        # ✅ 새 사용자 메시지만 카운트 증가
        new_user_messages = [msg for msg in new_messages if not msg.get("is_bot", False)]
        if new_user_messages:
            join_attempt_count[channel_id] += len(new_user_messages)
            # logger.debug(f"[대화참여] 카운트 증가 → 현재: {join_attempt_count[channel_id]}, 새 사용자 메시지 {len(new_user_messages)}개")

        # ✅ join_every_n 기능 개선 - 카운트에 따라 참여 결정
        if join_every_n > 1:
            if join_attempt_count[channel_id] % join_every_n != 0:
                # logger.debug(f"[대화참여] 참여 건너뜀 (join_every_n={join_every_n}, 현재={join_attempt_count[channel_id]})")
                return
            else:
                logger.debug(f"[대화참여] {join_every_n}번째 참여 → 현재 카운트: {join_attempt_count[channel_id]}")

        logger.info(f"[대화참여] 조건 충족됨 → {channel_id}, 메시지 수: {len(recent_messages)}")

        room_name = data.get("room_name", "알 수 없는 방")
        
        # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
        context = {
            'bot_name': bot_name,
            'channel_id': channel_id,
            'room': room_name
        }

        join_message = await generate_conversation_join_message(recent_messages, context)

        if join_message:
            await send_message_response(context, join_message)
            logger.info(f"[대화참여] 메시지 전송 성공 → {bot_name} / {room_name}")
            last_join_time[channel_id] = current_time
            add_message_to_history(channel_id, {
                "text": join_message,
                "sender": bot_name,
                "is_bot": True,
                "timestamp": current_time
            })
        else:
            logger.warning(f"[대화참여] ⚠️ 메시지 생성 실패 → {channel_id}")

    except Exception as e:
        logger.exception(f"[대화참여] 채널 확인 중 오류 ({channel_id}): {str(e)}")


async def generate_conversation_join_message(messages, context):
    try:
        conversation_text = ""
        participants = set()

        for msg in messages:
            sender = msg.get("sender", "알 수 없는 사용자")
            text = msg.get("text", "")
            timestamp = msg.get("timestamp")
            is_bot = msg.get("is_bot", False)
            label = f"{sender} (챗봇)" if is_bot else sender
            participants.add(sender)
            timestamp = to_timestamp(msg.get("timestamp"))
            formatted_time = datetime.fromtimestamp(timestamp).strftime("%H:%M:%S") if timestamp else ""
            conversation_text += f"[{formatted_time}] {label}: {text}\n"

        # ✅ 봇의 애칭 (bot_nickname)을 room 설정에서 가져오기
        bot_name = context["bot_name"]
        channel_id = context["channel_id"]
        room_data = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
        nickname = room_data.get("bot_nickname", bot_name)

        system_prompt = f"""
        당신은 친구들의 대화에 자연스럽게 참여하는 인간적이고 친근한 사람이며 이름은 '{nickname}'입니다.
        
        대화 참여 지침:
        1. 대화 맥락을 파악하고 가장 최근의 주제나 흐름에 맞춰 응답하세요.
        2. 참여자들의 이름을 자연스럽게 언급하여 친근함을 표현하세요.
        3. 실제 대화 참여자처럼 자연스럽게 의견을 제시하거나 공감을 표현하세요.
        
        중요한 점:
        - 모든 이전 메시지는 맥락 이해를 위한 참고용입니다. 모든 메시지에 답변할 필요는 없습니다.
        - 가장 최근의 메시지들에 대한 응답을 위주로 해야 합니다. 
        - 가장 최근의 메시지/질문이 독립적이라면 그것에만 집중해도 좋습니다.
        - 모든 메시지를 요약하려 하지 마세요. 대신 현재 흐름에 자연스럽게 참여하세요.
        - 질문을 받았다면 직접 답변을 제공하고, 고민에는 공감과 위로를, 일반 주제에는 도움이 될 만한 의견을 제시하세요.
        - 당신은 대화를 주도하는 것이 아니라 참여하는 것임을 명심하세요.
        - 이전에 당신이 언급한 내용을 반복하지 마세요.
        - 사람들이 나누는 일상 대화에 참여하는 느낌으로 자연스럽고 친근하게 응답하세요.
        - 도움을 요청하지도 않았는데 굳이 '무엇을 도와드릴까요?', '제가 도울 수 있는 부분이 있으면 말씀해주세요!' 같은 말은 안해도 됩니다.
        
        참여자의 감정이나 태도를 파악하여 적절한 톤으로 대응하세요. 유머, 진지함, 공감 등을 상황에 맞게 활용하여 실제 사람처럼 대화에 참여하는 것이 목표입니다.
        """

        # ✅ 여기에 추가하세요
        user_prompt = f"""
        다음은 대화 참여자들의 최근 대화입니다:
        
        {conversation_text}
        
        이 대화에 자연스럽게 참여할 수 있는 1-3문장 정도의 짧은 응답을 작성해주세요. 
        질문에 대한 답변은 필요한 만큼 상세히 해도 좋습니다.
        당신도 대화 참여자인 것처럼 보여지면 좋아요.
        """

        providers = [
            {
                "name": "grok",
                "model": "grok-3-mini",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "gemini",
                "model": "gemini-1.5-flash",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "grok-flash",
                "model": "grok-3-flash",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "gemini-flash",
                "model": "gemini-2.0-flash",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "deepseek",
                "timeout": 30,
                "model": "deepseek-chat",
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            }
        ]

        response = await call_llm_with_fallback(context, user_prompt, providers)

        if response:
            return response.strip()
        else:
            logger.error("[대화참여] 메시지 생성 실패: LLM 응답 오류")
            participant_names = ", ".join(list(participants)[:3])
            if len(participants) > 3:
                participant_names += f" 외 {len(participants) - 3}명"
            return f"흥미로운 대화네요, {participant_names}님! 저도 참여해도 될까요? 👋"

    except Exception as e:
        logger.error(f"[대화참여] 메시지 생성 오류: {str(e)}")
        try:
            participant_names = ", ".join(list(set([msg.get("sender", "친구") for msg in messages]))[:3])
            return f"재미있는 대화 같아요, {participant_names}님! 👋"
        except:
            return "흥미로운 대화네요! 저도 참여해도 될까요? 👋"


def add_message_to_history(channel_id, message_context: dict):
    try:
        message_context.setdefault("timestamp", time.time())

        # ✅ 의미 없는 메시지는 제외
        message_text = message_context.get("text", "")
        bot_name = message_context.get("bot_name", "")
        prefix, _ = parse_prefix(message_text, bot_name=bot_name)
        if not is_meaningful_message(message_text, prefix, "pass"):
            logger.debug(f"[대화참여] 의미 없는 메시지로 판단되어 히스토리에 추가되지 않음 → {message_text}")
            return

        message_history[channel_id].append(message_context)
    except Exception as e:
        logger.error(f"[대화참여] 메시지 히스토리 추가 오류: {str(e)}")
