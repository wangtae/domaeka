"""
대화 요약 서비스: 지정된 기간의 대화 내용을 요약하는 기능을 제공합니다.
방별 커스텀 LLM 모델 및 system_prompt 지원 기능 추가
"""
import re
import datetime
import aiomysql
# import uuid
from core import globals as g
from core.logger import logger
from services.llm_fallback_service import call_llm_with_fallback
from typing import List, Dict, Any, Optional, Union
# conversation_summary_service.py 상단
from core.globals import LLM_DEFAULT_SYSTEM_PROMPT
from core.globals import KAKAO_MSG_MORE_TRIGGER
from core.globals import apply_kakao_readmore
from core.utils.auth_utils import is_admin


async def fetch_today_conversation_for_summary(channel_id, only_meaningful=False):
    """
    오늘(0시부터 현재까지)의 대화를 가져오는 함수

    Args:
        channel_id (str): 채널 ID
        only_meaningful (bool): 의미 있는 메시지만 가져올지 여부

    Returns:
        list: 메시지 목록
    """
    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                query = """
                    SELECT sender, message, server_timestamp
                    FROM kb_chat_logs
                    WHERE channel_id = %s
                    AND DATE(server_timestamp) = CURDATE()
                """

                params = [channel_id]

                if only_meaningful:
                    query += " AND is_meaningful = 1"

                query += " ORDER BY server_timestamp ASC"

                await cur.execute(query, params)
                messages = await cur.fetchall()

                logger.info(f"[대화요약] 오늘의 대화 {len(messages)}개 메시지 조회됨 (채널: {channel_id})")
                return messages
    except Exception as e:
        logger.exception(f"[대화요약] DB 조회 오류: {e}")
        return []


async def fetch_recent_conversation_for_summary(channel_id, minutes=60, only_meaningful=False):
    """
    최근 N분간의 대화를 가져오는 함수

    Args:
        channel_id (str): 채널 ID
        minutes (int): 가져올 시간 범위(분)
        only_meaningful (bool): 의미 있는 메시지만 가져올지 여부

    Returns:
        list: 메시지 목록
    """
    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                query = """
                    SELECT sender, message, server_timestamp
                    FROM kb_chat_logs
                    WHERE channel_id = %s
                    AND server_timestamp >= DATE_SUB(NOW(), INTERVAL %s MINUTE)
                """

                params = [channel_id, minutes]

                if only_meaningful:
                    query += " AND is_meaningful = 1"

                query += " ORDER BY server_timestamp ASC"

                await cur.execute(query, params)
                messages = await cur.fetchall()

                logger.info(f"[대화요약] 최근 {minutes}분 {len(messages)}개 메시지 조회됨 (채널: {channel_id})")
                return messages
    except Exception as e:
        logger.exception(f"[대화요약] DB 조회 오류: {e}")
        return []


async def fetch_user_conversation_for_summary(channel_id, user_name, days=1, only_meaningful=False):
    """
    특정 유저의 최근 N일간의 대화를 가져오는 함수

    Args:
        channel_id (str): 채널 ID
        user_name (str): 유저 이름
        days (int): 가져올 일수 (1=오늘, 2=최근 2일)
        only_meaningful (bool): 의미 있는 메시지만 가져올지 여부

    Returns:
        list: 메시지 목록
    """
    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                if days == 1:
                    # 오늘만
                    query = """
                        SELECT sender, message, server_timestamp
                        FROM kb_chat_logs
                        WHERE channel_id = %s
                        AND sender = %s
                        AND DATE(server_timestamp) = CURDATE()
                    """
                else:
                    # 최근 N일
                    query = """
                        SELECT sender, message, server_timestamp
                        FROM kb_chat_logs
                        WHERE channel_id = %s
                        AND sender = %s
                        AND server_timestamp >= DATE_SUB(NOW(), INTERVAL %s DAY)
                    """

                params = [channel_id, user_name]
                if days != 1:
                    params.append(days)

                if only_meaningful:
                    query += " AND is_meaningful = 1"

                query += " ORDER BY server_timestamp ASC"

                await cur.execute(query, params)
                messages = await cur.fetchall()

                logger.info(f"[유저대화요약] {user_name}의 최근 {days}일 {len(messages)}개 메시지 조회됨 (채널: {channel_id})")
                return messages
    except Exception as e:
        logger.exception(f"[유저대화요약] DB 조회 오류: {e}")
        return []


def format_conversation_for_summary(messages: List[Dict[str, Any]]) -> str:
    """메시지 목록을 텍스트 형식으로 변환"""
    conversation_text = ""
    for msg in messages:
        sender = msg.get("sender", "알 수 없는 사용자")
        text = msg.get("message", "")
        timestamp = msg.get("server_timestamp", "")
        formatted_time = timestamp.strftime("%H:%M:%S") if hasattr(timestamp, "strftime") else ""
        conversation_text += f"[{formatted_time}] {sender}: {text}\n"
    return conversation_text


def parse_time_from_prompt(prompt: str) -> int:
    """프롬프트에서 시간 정보 추출"""
    minutes = 60  # 기본값: 60분
    if not prompt:
        return minutes

    try:
        time_match = re.search(r'(\d+)(?:분|시간|hours?|mins?)?', prompt)
        if time_match:
            extracted_time = int(time_match.group(1))
            if re.search(r'시간|hours?', prompt):
                minutes = extracted_time * 60
            else:
                minutes = extracted_time
    except Exception as e:
        logger.warning(f"[대화요약] 시간 파싱 오류: {e}")

    return minutes


def get_room_summary_config(bot_name, channel_id, summary_type="today_summary"):
    """
    채널별 요약 설정을 가져오는 함수

    Args:
        bot_name (str): 봇 이름
        channel_id (str): 채널 ID
        summary_type (str): 요약 유형 ('today_summary' 또는 'recent_summary')

    Returns:
        dict: 요약 설정
    """
    try:
        # schedule_rooms 구조에서 channel_id에 해당하는 설정 가져오기
        channel_config = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})

        # conversation_summary 섹션 확인
        if "conversation_summary" in channel_config and summary_type in channel_config["conversation_summary"]:
            summary_config = channel_config["conversation_summary"][summary_type]
            logger.debug(f"[대화요약] {bot_name}/{channel_id}의 {summary_type} 설정 찾음: {summary_config}")
            return summary_config

        logger.debug(f"[대화요약] {bot_name}/{channel_id}의 {summary_type} 설정이 없음")
        return {}
    except Exception as e:
        logger.warning(f"[대화요약] 설정 조회 오류: {e}")
        return {}


async def build_llm_providers(config=None, context=None):
    """
    LLM 프로바이더 목록 생성 (system_prompt 전처리 추가 버전)

    Args:
        config (dict, optional): LLM 설정 (ex: conversation_summary 설정)
        context (dict, optional): 템플릿 변수를 치환할 때 사용할 컨텍스트 (ex: received_message)

    Returns:
        list: LLM 프로바이더 목록
    """
    from core.utils.template_variables import process_template_variables_async

    # 기본 system_prompt
    default_system_prompt = """
    당신은 채팅 대화를 요약하는 전문가입니다. 주어진 대화를 분석하고 다음과 같은 형식으로 요약해주세요:

    1. 주요 주제 (2-3개)
    2. 핵심 내용 요약 (3-5문장)
    3. 주요 참여자와 그들의 주요 의견/기여

    대화의 흐름과 핵심 내용을 간결하게 캡처하되, 불필요한 세부사항은 생략하세요.
    중요한 정보나 결정사항이 있다면 반드시 포함시키세요.
    전체 요약은 300자 이내로 작성해주세요.
    """

    # 설정 기반으로 system_prompt 가져오기
    if config and "llm" in config and isinstance(config["llm"], dict):
        llm_config = config["llm"]
        provider = llm_config.get("provider", "gemini")
        model = llm_config.get("model", "gemini-1.5-pro")
        system_prompt = llm_config.get("system_prompt", default_system_prompt)

        # ✅ context가 있으면 system_prompt를 템플릿 치환
        # if context:
        system_prompt = await process_template_variables_async(system_prompt, context)

        providers = [
            {
                "name": provider,
                "model": model,
                "timeout": 180,
                "retry": 0,
                "system_prompt": system_prompt
            }
        ]

        # 백업 프로바이더 추가
        if provider != "gemini":
            providers.append({
                "name": "gemini",
                "model": "gemini-1.5-pro",
                "timeout": 180,
                "retry": 0,
                "system_prompt": system_prompt
            })

        if provider != "openai":
            providers.append({
                "name": "openai",
                "model": "gpt-4o",
                "timeout": 180,
                "retry": 0,
                "system_prompt": system_prompt
            })

        providers.extend([
            {
                "name": "grok",
                "model": "grok-3-latest",
                "timeout": 180,
                "retry": 0,
                "system_prompt": system_prompt
            },
            {
                "name": "deepseek",
                "model": "deepseek-chat",
                "timeout": 180,
                "retry": 0,
                "system_prompt": system_prompt
            }
        ])

        return providers

    # 설정이 없을 때 기본 프로바이더 목록
    if context:
        default_system_prompt = await process_template_variables_async(default_system_prompt, context)

    return [
        {
            "name": "gemini",
            "model": "gemini-1.5-pro",
            "timeout": 180,
            "retry": 0,
            "system_prompt": default_system_prompt
        },
        {
            "name": "openai",
            "model": "gpt-4o",
            "timeout": 180,
            "retry": 0,
            "system_prompt": default_system_prompt
        },
        {
            "name": "grok",
            "model": "grok-3-latest",
            "timeout": 180,
            "retry": 0,
            "system_prompt": default_system_prompt
        },
        {
            "name": "deepseek",
            "model": "deepseek-chat",
            "timeout": 180,
            "retry": 0,
            "system_prompt": default_system_prompt
        }
    ]


async def save_summary_to_db(
    bot_name: str,
    channel_id: str,
    summary_type: str,
    summary_content: str,
    start_timestamp: datetime.datetime,
    end_timestamp: datetime.datetime,
    is_meaningful_only: bool = False,
    summary_minutes: Optional[int] = None
):
    """
    생성된 대화 요약 내용을 kb_conversation_summaries 테이블에 저장합니다.
    """
    # try:
    #     summary_id = str(uuid.uuid4())
    #     query = """
    #         INSERT INTO kb_conversation_summaries (
    #             summary_id, bot_name, channel_id, summary_type, summary_content,
    #             start_timestamp, end_timestamp, is_meaningful_only, summary_minutes
    #         ) VALUES (
    #             %s, %s, %s, %s, %s,
    #             %s, %s, %s, %s
    #         )
    #     """
    #     async with g.db_pool.acquire() as conn:
    #         await conn.set_charset('utf8mb4')
    #         async with conn.cursor() as cursor:
    #             await cursor.execute(query, (
    #                 summary_id,
    #                 bot_name,
    #                 channel_id,
    #                 summary_type,
    #                 summary_content,
    #                 start_timestamp,
    #                 end_timestamp,
    #                 int(is_meaningful_only),
    #                 summary_minutes
    #             ))
    #     logger.info(f"[DB 저장 성공] 대화 요약 저장 → summary_id={summary_id}, channel_id={channel_id}, type={summary_type}")
    # except Exception as e:
    #     logger.error(f"[DB 저장 실패] 대화 요약 저장 중 오류 발생 → {e}", exc_info=True)


# 1. handle_today_conversation_summary 함수 수정
async def handle_today_conversation_summary(prompt, parameters, received_message):
    """오늘(0시부터 현재까지)의 대화를 요약"""
    # 명령 파라미터와 이벤트 채널ID 분리
    param_channel_id = None
    if parameters:
        param_channel_id = parameters.get("channel-id")
    event_channel_id = received_message.get("channel_id")
    real_channel_id = param_channel_id or event_channel_id
    # 명령 파라미터로 타방 요청 시에만 관리자 체크
    if param_channel_id and param_channel_id != event_channel_id:
        if not is_admin(event_channel_id, received_message.get("user_hash")):
            logger.warning(f"[ADMIN_ONLY] 관리자만 다른 채널의 대화 요약을 요청할 수 있습니다. (user_hash={received_message.get('user_hash')}, event_channel_id={event_channel_id}, param_channel_id={param_channel_id})")
            return "@no-reply"
    bot_name = received_message.get("bot_name", "")
    only_meaningful = "!" in received_message.get("prefix", "")
    logger.info(f"[대화요약] 오늘의 대화 요약 시작 → channel_id={real_channel_id}, 의미있는 메시지만={only_meaningful}")
    logger.info(f"[대화요약] param_channel_id={param_channel_id}, event_channel_id={event_channel_id}, real_channel_id={real_channel_id}")
    try:
        summary_config = get_room_summary_config(bot_name, real_channel_id, "today_summary")

        # 설정에 enabled가 명시적으로 False로 설정되어 있으면 기능 비활성화
        if summary_config.get("enabled") is False:
            return "이 채팅방에서는 대화 요약 기능이 비활성화되어 있습니다."

        # DB에서 오늘 메시지 가져오기
        messages = await fetch_today_conversation_for_summary(real_channel_id, only_meaningful)

        if not messages or len(messages) < 3:
            logger.info(f"[대화요약] 요약할 충분한 대화 내용이 없어 무응답 처리됩니다. (채널: {real_channel_id}, 메시지 수: {len(messages) if messages else 0})")
            return "@no-reply"

        # 대화 텍스트 구성
        conversation_text = format_conversation_for_summary(messages)

        # LLM 프로바이더 구성
        providers = await build_llm_providers(summary_config, received_message)

        # 요약 프롬프트 구성
        user_prompt = f"""
        다음은 오늘(0시부터 현재까지)의 대화 내용입니다:
        
        {conversation_text}
        
        위 대화 내용을 요약해주세요.
        """

        # LLM 호출
        summary = await call_llm_with_fallback(received_message, user_prompt, providers)

        if not summary or summary.startswith("[ERROR]"):
            return "대화 요약 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."
        

        now = datetime.datetime.now()
        date_str = now.strftime("%y/%m/%d")
        weekday_kr = "월화수목금토일"[now.weekday()]
        # 결과 포맷팅
        result = f"💬 하루 대화 요약 ({date_str}, {weekday_kr})\n"
        if only_meaningful:
            result += "(의미 있는 메시지만 포함)\n"
        result += f"\n{summary.strip()}"

        # kakao_readmore 적용
        kakao_readmore = summary_config.get("kakao_readmore", {})
        kakao_type = kakao_readmore.get("type", "lines")
        kakao_value = int(kakao_readmore.get("value", 1))
        result = apply_kakao_readmore(result, kakao_type, kakao_value)

        # 요약 내용을 DB에 저장
        # await save_summary_to_db(
        #     bot_name,
        #     real_channel_id,
        #     "today_summary",
        #     result,
        #     datetime.datetime.now(),
        #     datetime.datetime.now(),
        #     only_meaningful,
        #     None
        # )

        return result

    except Exception as e:
        logger.exception(f"[대화요약] 오류 발생: {e}")
        return f"대화 요약 중 오류가 발생했습니다: {str(e)}"


# 2. handle_recent_conversation_summary 함수 수정
async def handle_recent_conversation_summary(prompt, parameters, received_message):
    """최근 N분간의 대화를 요약"""
    param_channel_id = None
    if parameters:
        param_channel_id = parameters.get("channel-id")
    event_channel_id = received_message.get("channel_id")
    real_channel_id = param_channel_id or event_channel_id
    if param_channel_id and param_channel_id != event_channel_id:
        if not is_admin(event_channel_id, received_message.get("user_hash")):
            logger.warning(f"[ADMIN_ONLY] 관리자만 다른 채널의 대화 요약을 요청할 수 있습니다. (user_hash={received_message.get('user_hash')}, event_channel_id={event_channel_id}, param_channel_id={param_channel_id})")
            return "@no-reply"
    bot_name = received_message.get("bot_name", "")
    only_meaningful = "!" in received_message.get("prefix", "")
    summary_config = get_room_summary_config(bot_name, real_channel_id, "recent_summary")
    # 프롬프트에서 시간 추출 또는 방 설정에서 기본값 가져오기
    minutes = 60  # 기본값

    # 이 부분에 디버그 로그 추가
    logger.debug(f"[대화요약] 설정 확인: {summary_config}")

    # 먼저 prompt에서 시간 추출 시도
    minutes_from_prompt = parse_time_from_prompt(prompt)
    if minutes_from_prompt != 60:  # 기본값과 다르면 프롬프트에서 추출된 값 사용
        minutes = minutes_from_prompt
        logger.debug(f"[대화요약] 프롬프트에서 추출한 시간: {minutes}분")
    elif summary_config and "default_minutes" in summary_config:
        try:
            default_minutes = int(summary_config["default_minutes"])
            minutes = default_minutes
            logger.debug(f"[대화요약] 설정에서 가져온 기본 시간: {minutes}분")
        except (ValueError, TypeError) as e:
            logger.warning(f"[대화요약] default_minutes 변환 오류: {e}, 기본값 60분 사용")

    logger.info(f"[대화요약] 최근 대화 요약 시작 → channel_id={real_channel_id}, 시간={minutes}분, 의미있는 메시지만={only_meaningful}")
    logger.info(f"[대화요약] param_channel_id={param_channel_id}, event_channel_id={event_channel_id}, real_channel_id={real_channel_id}")

    try:
        # DB에서 최근 메시지 가져오기
        messages = await fetch_recent_conversation_for_summary(real_channel_id, minutes, only_meaningful)

        if not messages or len(messages) < 3:
            logger.info(f"요약할 충분한 대화 내용이 없습니다. 더 활발한 대화 후에 다시 시도해 주세요.")
            return "@no-reply"

        # 대화 텍스트 구성
        conversation_text = format_conversation_for_summary(messages)

        # LLM 프로바이더 구성
        # ⚠️ 수정: await 키워드 추가
        providers = await build_llm_providers(summary_config, received_message)

        # 요약 프롬프트 구성
        user_prompt = f"""
        다음은 최근 {minutes}분 동안의 대화 내용입니다:
        
        {conversation_text}
        
        위 대화 내용을 요약해주세요.
        """

        # LLM 호출
        summary = await call_llm_with_fallback(received_message, user_prompt, providers)

        if not summary or summary.startswith("[ERROR]"):
            return "대화 요약 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."

        # 결과 포맷팅
        now = datetime.datetime.now()
        date_time_str = now.strftime("%m/%d, %H:%M")
        result = f"💬 최근 {minutes}분 대화 요약 ({date_time_str})\n"
        if only_meaningful:
            result += "(의미 있는 메시지만 포함)\n"
        result += f"\n{summary.strip()}"

        # kakao_readmore 적용
        kakao_readmore = summary_config.get("kakao_readmore", {})
        kakao_type = kakao_readmore.get("type", "lines")
        kakao_value = int(kakao_readmore.get("value", 1))
        result = apply_kakao_readmore(result, kakao_type, kakao_value)

        # 요약 내용을 DB에 저장
        # await save_summary_to_db(
        #     bot_name,
        #     real_channel_id,
        #     "recent_summary",
        #     result,
        #     datetime.datetime.now(),
        #     datetime.datetime.now(),
        #     only_meaningful,
        #     minutes
        # )

        return result

    except Exception as e:
        logger.exception(f"[대화요약] 오류 발생: {e}")
        return f"대화 요약 중 오류가 발생했습니다: {str(e)}"


# 3. handle_user_conversation_summary 함수 추가
async def handle_user_conversation_summary(prompt, parameters, received_message):
    """특정 유저의 최근 N일간 대화를 요약"""
    # 필수 파라미터 확인 및 처리 (user-name)
    user_name = parameters.get("user-name")

    # 만약 --user-name이 없다면, prompt에서 @유저이름 패턴을 찾습니다.
    if not user_name and prompt:
        mention_match = re.search(r'@([\w가-힣]+)', prompt)
        if mention_match:
            user_name = mention_match.group(1)

    # ✅ --user-name이나 @유저이름이 없는 경우 메시지 sender를 사용
    if not user_name:
        user_name = received_message.get("sender")
        logger.info(f"[유저대화요약] --user-name 또는 @유저이름이 없어 메시지 보낸 유저 이름({user_name}) 사용.")

    if not user_name:
        return "필수 파라미터가 누락되었습니다. --user-name=유저이름 또는 @유저이름을 입력해주세요. (--recent-days=N 일간 대화 요약, 기본값 1일)"

    # recent-days 파라미터 초기값 설정 (config에서 가져올 수 있도록 None으로 시작)
    recent_days = None
    recent_days_param = parameters.get("recent-days")
    if recent_days_param is not None:
        try:
            recent_days = int(recent_days_param)
            if recent_days < 1 or recent_days > 30:
                return "recent-days는 1~30 사이의 값이어야 합니다."
        except (ValueError, TypeError):
            return "recent-days는 숫자여야 합니다."
    
    # 채널 ID 처리
    param_channel_id = parameters.get("channel-id")
    event_channel_id = received_message.get("channel_id")
    real_channel_id = param_channel_id or event_channel_id
    
    # 명령 파라미터로 타방 요청 시에만 관리자 체크
    if param_channel_id and param_channel_id != event_channel_id:
        if not is_admin(event_channel_id, received_message.get("user_hash")):
            logger.warning(f"[ADMIN_ONLY] 관리자만 다른 채널의 유저 대화 요약을 요청할 수 있습니다.")
            return "@no-reply"
    
    bot_name = received_message.get("bot_name", "")
    only_meaningful = "!" in received_message.get("prefix", "")
    
    logger.info(f"[유저대화요약] {user_name}의 최근 {recent_days if recent_days else '설정/기본'}일 대화 요약 시작 → channel_id={real_channel_id}, 의미있는 메시지만={only_meaningful}")
    
    try:
        summary_config = get_room_summary_config(bot_name, real_channel_id, "user_summary")
        
        # 설정에 enabled가 명시적으로 False로 설정되어 있으면 기능 비활성화
        if summary_config.get("enabled") is False:
            return "이 채팅방에서는 유저 대화 요약 기능이 비활성화되어 있습니다."
        
        # recent_days가 지정되지 않았으면 config에서 default_days 가져오기
        if recent_days is None:
            if "default_days" in summary_config:
                try:
                    recent_days = int(summary_config["default_days"])
                    if not (1 <= recent_days <= 30): # config 값도 유효성 검사
                        recent_days = 1 # 유효하지 않으면 기본값
                        logger.warning(f"[유저대화요약] user_summary 설정의 default_days ({summary_config['default_days']})가 유효하지 않아 기본값 1일 사용.")
                except (ValueError, TypeError):
                    recent_days = 1 # 숫자가 아니면 기본값
                    logger.warning(f"[유저대화요약] user_summary 설정의 default_days가 숫자가 아니어서 기본값 1일 사용.")
            else:
                recent_days = 1 # config에도 없으면 최종 기본값 1일
                logger.info(f"[유저대화요약] --recent-days 파라미터나 user_summary 설정에 default_days가 없어 기본값 1일 사용.")

        # 이제 recent_days는 반드시 유효한 정수 값을 가집니다.
        
        # DB에서 유저의 메시지 가져오기
        messages = await fetch_user_conversation_for_summary(real_channel_id, user_name, recent_days, only_meaningful)
        
        if not messages or len(messages) < 1:
            return f"{user_name}님의 최근 {recent_days}일간 대화 내용이 없습니다."
        
        # 대화 텍스트 구성
        conversation_text = format_conversation_for_summary(messages)
        
        # LLM 프로바이더 구성 시 system_prompt 템플릿 변수를 위한 context 추가
        llm_context_for_template = received_message.copy()
        llm_context_for_template['USER_NAME'] = user_name  # system_prompt의 {USER_NAME}에 매칭
        llm_context_for_template['N'] = recent_days        # system_prompt의 {N}에 매칭 (일수)

        providers = await build_llm_providers(summary_config, llm_context_for_template)
        
        # 요약 프롬프트 구성 (system_prompt가 지시사항을 대부분 포함하므로 대화 내용만 전달)
        user_prompt = f"""{conversation_text}"""
        
        # LLM 호출
        summary = await call_llm_with_fallback(llm_context_for_template, user_prompt, providers) # 업데이트된 context 전달
        
        if not summary or summary.startswith("[ERROR]"):
            return "대화 요약 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."
        
        # 결과 포맷팅
        now = datetime.datetime.now()
        if recent_days == 1:
            date_str = now.strftime("%y/%m/%d")
            weekday_kr = "월화수목금토일"[now.weekday()]
            result = f"👤 {user_name}님 오늘 대화 요약 ({date_str}, {weekday_kr})\n"
        else:
            result = f"👤 {user_name}님 최근 {recent_days}일 대화 요약\n"
        
        if only_meaningful:
            result += "(의미 있는 메시지만 포함)\n"
        result += f"\n{summary.strip()}"
        
        # kakao_readmore 적용
        kakao_readmore = summary_config.get("kakao_readmore", {})
        try:
            kakao_value = int(kakao_readmore.get("value", 1))
        except (ValueError, TypeError):
            logger.warning(f"[유저대화요약] kakao_readmore value ({kakao_readmore.get('value')})가 유효하지 않아 기본값 1 사용.")
            kakao_value = 1
        
        kakao_type = kakao_readmore.get("type", "lines")
        result = apply_kakao_readmore(result, kakao_type, kakao_value)
        
        # 요약 내용을 DB에 저장
        # await save_summary_to_db(
        #     bot_name,
        #     real_channel_id,
        #     "user_summary",
        #     result,
        #     datetime.datetime.now() - datetime.timedelta(days=recent_days),
        #     datetime.datetime.now(),
        #     only_meaningful,
        #     None  # summary_minutes는 일수 기반 요약에서는 사용 안함
        # )
        
        return result
        
    except Exception as e:
        logger.exception(f"[유저대화요약] 오류 발생: {e}")
        return f"유저 대화 요약 중 오류가 발생했습니다: {str(e)}"
