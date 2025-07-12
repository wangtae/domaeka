import random
import core.globals as g
from core.logger import logger
from core.utils.prefix_utils import parse_mention_analysis_request
from core.db_utils import get_user_hash_by_name, save_archived_message_to_db
from core.utils.cache_service import get_cached_response, cache_response
from core.utils.template_variables import process_template_variables_async
from core.sessions.session_manager import get_active_session
from services.llm_chat_sessions.session_processor import process_session_message
from services.llm_chat_sessions.session_commands import handle_session_command
from core.utils.command_parser import parse_full_input

# LLM 서비스 모듈 import'
from services.openai_service import call_openai
from services.gemini_service import call_gemini
from services.deepseek_service import call_deepseek
from services.perplexity_service import call_perplexity
from services.grok_service import call_grok

# 기타 서비스 모듈 import
from services.local_service import call_local_llm
from services.bible_service import bible_query
from services.recommend_lunch_service import recommend_lunch_menu
from services.lotto_service import generate_lotto_numbers
from services.profile_service import analyze_profile
from services.reload_all_json import reload_all_json_files
from services.chat_rank_service import get_chat_rank
from services.llm_fallback_service import call_llm_with_fallback
from services.weather_service import weather_service
from services.naver_weather_service import handle_weather_command
from services.proverb_service import get_random_investment_proverb, handle_today_proverb
from services.image_service import handle_image_command
from services.image_url_service import handle_image_url_command
from services.today_bible_service import handle_today_bible_command
from services.bloomberg_service import process_bloomberg_command
from services.exchange_rate_service import handle_exchange_rate_command
from services.conversation_summary_service import (
    handle_today_conversation_summary,
    handle_recent_conversation_summary,
    handle_user_conversation_summary
)
from services.youtube_service import handle_youtube_summary
from services.webpage_service import handle_webpage_summary
from services.profile_mbti_service import analyze_mbti
from services.profile_enneagram_service import analyze_enneagram
from services.tts_url_service import handle_tts_command, handle_tts_ko_command, handle_tts_en_command
from services.image_multi_service import handle_multiple_image_command
from services.ross_ai_service import handle_ross_ai_command
from services.imagen_service import handle_imagen_command
from services.config_generator_service import generate_bot_settings_from_db

# 오목 핸들러 import
from games.omok.handlers.start_game_handler import handle_start_command
from games.omok.handlers.join_game_handler import handle_join_game
from games.omok.handlers.stop_game_handler import handle_stop_command
from games.omok.handlers.omok_command_handler import handle_omok_command, handle_status_command

# 동적 명령어 시스템 사용
from core.command_manager import command_manager

# 기존 전역 변수는 fallback으로 보존
PREFIX_MAP = g.PREFIX_MAP
ENABLED_PREFIXES = g.ENABLED_PREFIXES


def is_bot_mention(target_name: str, bot_name: str):
    return target_name.strip().lower() == f"@{bot_name.lower()}"


async def handle_todo_list():
    todo_text = "📋 [할 일 목록]\n\n"
    todo_text += "\n".join([f"{idx + 1}. {item}" for idx, item in enumerate(g.TODO_ITEMS)])
    logger.info("[TODO_LIST] 할 일 목록 출력 완료")
    return todo_text


async def generate_tts_message(text_result, tts_config, bot_name=None, room_name=None):
    """
    메시지에 대한 TTS 생성을 담당하는 함수
    TTS 메시지 문자열을 반환합니다.
    """
    try:
        if not tts_config or not tts_config.get("enabled", False):
            return None

        from services.tts_url_service import handle_tts_command

        # 기본값 복사 + override 적용
        config = g.TTS_DEFAULT_CONFIG.copy()
        custom_config = tts_config.get("config", {})
        if custom_config and isinstance(custom_config, dict):
            for key, value in custom_config.items():
                if key in config:
                    config[key] = value

        logger.info(f"[TTS] TTS 생성 시작 → {bot_name} / {room_name}")

        # TTS 생성
        tts_result = await handle_tts_command(text_result, config)

        # TTS 결과 처리
        tts_message = None
        if isinstance(tts_result, list) and tts_result:
            tts_message = tts_result[0]
        elif isinstance(tts_result, str):
            tts_message = tts_result

        if not tts_message:
            logger.warning(f"[TTS] TTS 생성 결과 없음 → {bot_name} / {room_name}")
            return None

        logger.info(f"[TTS] TTS 메시지 생성 성공 → {bot_name} / {room_name}")
        return tts_message

    except Exception as e:
        logger.error(f"[TTS] TTS 처리 중 오류 발생 → {e}", exc_info=True)
        return None


async def process_command(context: dict):
    prefix = context.get("prefix")

    original_prompt = context.get("prompt") or ""

    # 명령어 정의 가져오기
    command_info = PREFIX_MAP.get(prefix)
    command_type = command_info['type'] if command_info else None

    # 파라미터/프롬프트 분리
    command_type, parameters, prompt = parse_full_input(
        input_text=context.get("prompt"),
        prefix_map=g.PREFIX_MAP,
        user_role=context.get("user_role", "user")
    )

    # 파싱된 parameters를 context에 저장
    context["_prompt_parameters"] = parameters

    # [설명]
    # parameters는 기존대로 서비스 함수에 인자로 전달합니다.
    # 그러나 새로운 방식에서는 모든 prompt 파라미터가 context["_prompt_parameters"]에 저장되어
    # 서비스 함수 내부 또는 LLM 호출부 등에서 context["_prompt_parameters"]로도 접근할 수 있습니다.
    # (함수 시그니처를 변경하지 않고, context 기반의 범용 파라미터 활용을 지원)

    # --channel-id 파라미터가 있으면 channel_id 교체 (관리자만 허용)
    if parameters and "channel-id" in parameters:
        from core.utils.auth_utils import is_admin
        context["_original_channel_id"] = context.get("channel_id")
        if not is_admin(context["_original_channel_id"], context.get("user_hash")):
            logger.warning(f"[ADMIN_ONLY] --channel-id는 관리자만 사용할 수 있습니다. (user_hash={context.get('user_hash')}, _original_channel_id={context['_original_channel_id']}, requested_channel_id={parameters['channel-id']})")
            return "@no-reply"
        context["channel_id"] = parameters["channel-id"]

    # 디버깅
    logger.debug(f"[PARSED_PARAMETERS] {parameters}")
    logger.debug(f"[PARSED_PROMPT] {prompt}")

    bot_name = context.get("bot_name")
    channel_id = context.get("channel_id")
    user_hash = context.get("user_hash")
    room_name = context.get("room")
    sender = context.get("sender")
    client_key = context.get('client_key')
    # 스케줄링된 메시지인지 여부 확인 (scheduler.py에서 설정)
    is_scheduled = context.get('is_scheduled', False)

    room_data = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
    bot_nickname = room_data.get("bot_nickname", bot_name)

    logger.debug(f"[PROCESS_COMMAND] prefix={prefix}, prompt={prompt}, is_scheduled={is_scheduled}")

    if prefix == ">" and prompt.startswith("@"):
        target_name, target_channel_id, analysis_type = parse_mention_analysis_request(prompt)
        if target_name and analysis_type:
            logger.debug(f"[MENTION_ANALYZE] 자동 분석 요청 감지됨 → {target_name}, type: {analysis_type}")
            target_channel_id = target_channel_id or channel_id
            target_user_hash = await get_user_hash_by_name(g.db_pool, target_channel_id, target_name)

            if not target_user_hash:
                return f"❌ 해당 방에서 '{target_name}' 사용자를 찾을 수 없어요."

            if analysis_type == "profile":
                return await analyze_profile(
                    bot_name=bot_name,
                    channel_id=target_channel_id,
                    user_hash=target_user_hash,
                    prompt=prompt,
                    parameters=parameters,
                    sender=sender,
                    is_self=False
                )
            elif analysis_type == "mbti":
                return await analyze_mbti(
                    bot_name=bot_name,
                    channel_id=target_channel_id,
                    user_hash=target_user_hash,
                    sender=sender,
                    room_name=room_name
                )
            elif analysis_type == "enneagram":
                return await analyze_enneagram(
                    bot_name=bot_name,
                    channel_id=target_channel_id,
                    user_hash=target_user_hash,
                    sender=sender,
                    room_name=room_name
                )

    # prefix가 None인 경우 처리하지 않음
    if prefix is None:
        if not context.get("disable_command_logs", False):
            logger.debug(f"[NO_PREFIX] prefix가 없는 메시지 → 명령어 처리 건너뜀")
        return None

    # 동적 명령어 시스템 사용: 봇별/방별 명령어 확인
    bot_enabled_prefixes = command_manager.get_bot_enabled_prefixes(bot_name, channel_id)
    if bot_enabled_prefixes and prefix not in bot_enabled_prefixes:
        # 봇별 명령어가 로드되어 있고, 해당 접두어가 비활성화된 경우
        if not context.get("disable_command_logs", False):
            logger.warning(f"[DISABLED] 봇 '{bot_name}'에서 비활성화된 명령어입니다. (prefix: {prefix})")
            return None
    elif not bot_enabled_prefixes and prefix not in ENABLED_PREFIXES:
        # 봇별 명령어가 없고, 글로벌 명령어에서도 비활성화된 경우
        if not context.get("disable_command_logs", False):
            logger.warning(f"[DISABLED] 비활성화된 명령어입니다. (prefix: {prefix})")
            return None

    # 동적 명령어 시스템에서 명령어 정보 가져오기
    command_info = command_manager.get_bot_command_info(bot_name, channel_id, prefix)
    if not command_info:
        # 봇별 명령어에 없으면 글로벌 fallback 사용
        command_info = PREFIX_MAP.get(prefix)
        logger.error(f"[ERROR] 봇별 명령어에 없어 글로벌 fallback 사용합니다. (있어서는 안되는 상황이지만 검증 전까진 하위 호환성을 위해 남겨둠)")
        if not command_info:
            if not context.get("disable_command_logs", False):
                logger.warning(f"[UNKNOWN PREFIX] 지원되지 않는 접두어입니다. ({prefix})")
                return "[ERROR] 지원되지 않는 접두어입니다."

    command_type = command_info['type']
    if not context.get("disable_command_logs", False):
        logger.debug(f"[COMMAND_TYPE] {command_type}")
        logger.debug(f"g.json_command_data에서 해당 명령어 설정: {g.json_command_data.get(command_type, {})}")

    # 캐시 확인 루틴은 외부로 이동 (message_processor.py에서 처리)
    # 대신 캐시 확인 플래그 설정
    if context.get('skip_cache_check', False):
        # message_processor.py에서 이미 캐시 확인을 했으므로 건너뜀
        logger.debug("[CACHE_CHECK] 캐시 확인 건너뜀 (message_processor.py에서 이미 처리됨)")
    else:
        # 과거 코드와의 호환성을 위해 남겨둠 (외부에서 직접 호출하는 경우)
        cached_response = await get_cached_response(command_type, prompt)
        if cached_response:
            logger.info(f"[CACHE_HIT] {command_type} - {prompt[:30]}...")
            return cached_response

    try:
        result = None

        LLM_system_prompt = g.LLM_DEFAULT_SYSTEM_PROMPT

        if command_type == 'help':
            result = g.generate_help_message(bot_name=bot_name, channel_id=channel_id)

        elif command_type == 'reload_env':
            # generate_bot_settings_from_db 함수가 success와 메시지를 튜플로 반환
            success, message = await generate_bot_settings_from_db()
            if success:
                result = f"✅ 봇 설정 파일 생성 완료: {message}"
            else:
                result = f"❌ 봇 설정 파일 생성 중 오류가 발생했습니다: {message}"

        elif command_type == 'reload_bot_settings':
            # 특정 봇의 설정만 재생성 (프롬프트에서 봇 이름 추출)
            if prompt and prompt.strip():
                target_bot_name = prompt.strip()
                success, message = await generate_bot_settings_from_db(target_bot_name)
                if success:
                    result = f"✅ '{target_bot_name}' 봇 설정 파일 생성 완료: {message}"
                else:
                    result = f"❌ '{target_bot_name}' 봇 설정 파일 생성 중 오류가 발생했습니다: {message}"
            else:
                # 프롬프트가 없으면 현재 봇의 설정 재생성
                success, message = await generate_bot_settings_from_db(bot_name)
                if success:
                    result = f"✅ '{bot_name}' 봇 설정 파일 생성 완료: {message}"
                else:
                    result = f"❌ '{bot_name}' 봇 설정 파일 생성 중 오류가 발생했습니다: {message}"

        elif command_type == 'profile_analyze':
            target_name, target_channel_id, _ = parse_mention_analysis_request(prompt or "")
            if target_name:
                target_channel_id = target_channel_id or channel_id
                target_user_hash = await get_user_hash_by_name(g.db_pool, target_channel_id, target_name)

                if not target_user_hash:
                    result = f"❌ 해당 방에서 '{target_name}' 사용자를 찾을 수 없어요."
                else:
                    result = await analyze_profile(
                        bot_name=bot_name,
                        channel_id=target_channel_id,
                        user_hash=target_user_hash,
                        prompt=prompt,
                        parameters=parameters,
                        sender=target_name,
                        is_self=False
                    )
            else:
                if not user_hash:
                    logger.warning("[PROFILE_ANALYZE] user_hash가 누락됨 → 요청 불가")
                    result = random.choice(g.LLM_ERROR_MESSAGES)
                else:
                    result = await analyze_profile(
                        bot_name=bot_name,
                        channel_id=channel_id,
                        user_hash=user_hash,
                        prompt=prompt,
                        parameters=parameters,
                        sender=sender,
                        is_self=True
                    )

        elif command_type == 'mbti_analyze':
            if not user_hash:
                logger.warning("[MBTI_ANALYZE] user_hash가 누락됨 → 요청 불가")
                result = random.choice(g.LLM_ERROR_MESSAGES)
            else:
                result = await analyze_mbti(
                    bot_name=bot_name,
                    channel_id=channel_id,
                    user_hash=user_hash,
                    sender=sender,
                    room_name=room_name
                )

        elif command_type == 'enneagram_analyze':
            if not user_hash:
                logger.warning("[ENNEAGRAM_ANALYZE] user_hash가 누락됨 → 요청 불가")
                result = random.choice(g.LLM_ERROR_MESSAGES)
            else:
                result = await analyze_enneagram(
                    bot_name=bot_name,
                    channel_id=channel_id,
                    user_hash=user_hash,
                    sender=sender,
                    room_name=room_name
                )

        elif command_type == 'reload-all-json':
            logger.debug("[COMMAND] 🔁 JSON 리로드 명령 실행됨")
            detail_msg = await reload_all_json_files()
            logger.debug(f"[COMMAND] 🔁 리로드 결과 detail_msg=\n{detail_msg}")
            result = detail_msg

        # LLM_DEFAULT_SYSTEM_PROMPT 변수를 globals.py에서 가져오기
        elif command_type == 'mention':
            if not prompt.startswith("@"):
                result = "[ERROR] @대상이 필요합니다. 예: [나를 멘션] @LOA.i 질문"
            else:
                # ✅ 사용자 분석 키워드 포함 여부 판단
                target_name, target_channel_id, analysis_type = parse_mention_analysis_request(prompt)
                if target_name and analysis_type:
                    # 채널 ID가 없으면 현재 채널 ID 사용
                    target_channel_id = target_channel_id or channel_id
                    target_user_hash = await get_user_hash_by_name(g.db_pool, target_channel_id, target_name)

                    if not target_user_hash:
                        result = f"❌ 해당 방에서 '{target_name}' 사용자를 찾을 수 없어요."
                    else:
                        if analysis_type == "profile":
                            result = await analyze_profile(
                                bot_name=bot_name,
                                channel_id=target_channel_id,
                                user_hash=target_user_hash,
                                prompt=prompt,
                                parameters=parameters,
                                sender=target_name,
                                is_self=False
                            )
                        elif analysis_type == "mbti":
                            result = await analyze_mbti(
                                bot_name=bot_name,
                                channel_id=target_channel_id,
                                user_hash=target_user_hash,
                                sender=target_name,
                                room_name=room_name
                            )
                        elif analysis_type == "enneagram":
                            result = await analyze_enneagram(
                                bot_name=bot_name,
                                channel_id=target_channel_id,
                                user_hash=target_user_hash,
                                sender=target_name,
                                room_name=room_name
                            )
                else:
                    # 기존 @봇 호출 처리
                    target, *rest = prompt.split(maxsplit=1)
                    question = rest[0] if rest else ""

                    if is_bot_mention(target, bot_name):
                        if not question or question.strip() == "":
                            result = "@no-reply"
                        else:
                            system_prompt = g.LLM_DEFAULT_SYSTEM_PROMPT
                            providers = [
                                {"name": "grok", "model": "grok-3-latest", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                                {"name": "gemini", "model": "gemini-1.5-pro", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                                {"name": "openai", "model": "gpt-4o", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                                {"name": "gemini-flash", "model": "gemini-1.5-flash", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                                {"name": "deepseek", "model": "deepseek-chat", "timeout": 30, "retry": 0, "system_prompt": system_prompt}
                            ]
                            received_message_context = {
                                "bot_name": bot_name,
                                "channel_id": channel_id,
                                "user_hash": user_hash,
                                "sender": sender,
                                "room": context.get("room")
                            }
                            result = await call_llm_with_fallback(received_message_context, question, providers)
                    else:
                        result = f"🧑‍🏫 @{target[1:]} 님을 대상으로 한 메시지를 분석하는 기능은 준비 중입니다."

        elif command_type == 'echo':
            result = prompt if prompt else None

        elif command_type == 'openai':
            # 새로운 API 방식으로 호출
            model = "gpt-4o"  # 또는 "gpt-3.5-turbo", "gpt-4", 등
            LLM_system_prompt += "5. 설명이 잘리지 않게 가능한 한 30초 이내로 작성해 주세요."
            data = {
                "messages": [
                    {"role": "system", "content": LLM_system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "temperature": 0.7,
                "max_tokens": 1500
            }

            result = await call_openai(model, data)

        elif command_type == 'grok':
            # 새로운 API 방식으로 호출
            model = "grok-3-latest"
            LLM_system_prompt += "5. 설명이 잘리지 않게 가능한 한 30초 이내로 작성해 주세요."
            data = {
                "messages": [
                    {"role": "system", "content": LLM_system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "temperature": 0.7,
                "max_tokens": 1500
            }

            result = await call_grok(model, data)

        elif command_type == 'gemini':
            # 새로운 API 방식으로 호출
            model = "gemini-1.5-pro"
            LLM_system_prompt += "5. 설명이 잘리지 않게 가능한 한 30초 이내로 작성해 주세요."
            data = {
                "messages": [
                    {"role": "system", "content": LLM_system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "temperature": 0.7,
                "max_tokens": 1500
            }
            result = await call_gemini(model, data)

        elif command_type == 'deepseek':
            # 새로운 API 방식으로 호출
            model = "deepseek-chat"
            LLM_system_prompt += "5. 설명이 잘리지 않게 가능한 한 30초 이내로 작성해 주세요."
            data = {
                "messages": [
                    {"role": "system", "content": LLM_system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "temperature": 0.7,
                "max_tokens": 1500
            }
            result = await call_deepseek(model, data)

        elif command_type == 'perplexity':
            # 새로운 API 방식으로 호출
            model = "sonar-pro"
            LLM_system_prompt += "5. 설명이 잘리지 않게 가능한 한 30초 이내로 작성해 주세요."
            data = {
                "messages": [
                    {"role": "system", "content": LLM_system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "temperature": 0.7,
                "max_tokens": 1500
            }
            result = await call_perplexity(model, data)

        elif command_type == 'llm_fallback':
            # llm_fallback_service는 기존 방식 유지 (내부에서 새 API 사용)
            result = await call_llm_with_fallback(context, prompt)

        elif command_type == 'local_llm':
            result = await call_local_llm(prompt)

        elif command_type == "bot_auto_reply":
            # 최근 대화 조회
            from core.db_utils import fetch_recent_messages
            history = await fetch_recent_messages(g.db_pool, channel_id, None, limit=100, minutes=30)
            history = [{"role": "user", "content": msg} for msg in reversed(history)]
            history.append({"role": "user", "content": prompt})

            system_prompt = f"당신은 친절하고 사랑스러운 사람이며 이름은 '{bot_nickname}'입니다. 답변을 줄 때 다음 규칙을 지켜주세요: 1. 공손하고 친절한 말투로 답변해 주세요. 2. 대화 참여자인것 처럼 응답해 주세요. 3. 정보에 대한 질문에는 정확한 정보를 제공하고, 확실하지 않은 경우에는 솔직하게 알려주세요."

            providers = [
                {"name": "grok", "model": "grok-3-latest", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                {"name": "gemini", "model": "gemini-1.5-pro", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                {"name": "openai", "model": "gpt-4o", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                {"name": "gemini-flash", "model": "gemini-1.5-flash", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
                {"name": "deepseek", "model": "deepseek-chat", "timeout": 30, "retry": 0, "system_prompt": system_prompt}
            ]

            user_prompt = f"""
            다음은 최근 대화방의 대화 내용입니다:
            
            {history}
            
            가장 마지막 대화가 응답해야할 메시지이며 나머지 대화들은 자연스러운 대화가 작성되기 위한 참고용 메시지들입니다.
            
            이 대화에 자연스럽게 참여할 수 있는 1-3문장 정도의 짧은 응답을 작성해주세요. 
            질문에 대한 답변은 필요한 만큼 상세히 해도 좋습니다.
            당신도 대화 참여자인 것처럼 보여지면 좋아요.
            """

            result = await call_llm_with_fallback(
                {
                    "bot_name": bot_name,
                    "channel_id": channel_id,
                    "room": room_name,
                    "user_hash": user_hash,
                    "sender": sender
                },
                user_prompt,
                providers=providers
            )

        elif command_type in ['bible', 'bible_random']:
            result = await bible_query(command_type, prompt, channel_id=channel_id, user_hash=user_hash)

        elif command_type in ['bible_search_all', 'bible_search_old', 'bible_search_new']:
            result = await bible_query(command_type, prompt, channel_id=channel_id, user_hash=user_hash)

        elif command_type == 'lotto':
            result = await generate_lotto_numbers()

        elif command_type == 'weather':
            result = await weather_service()
            logger.debug(f"[WEATHER_RESULT] {result}")

        elif command_type == 'naver_weather':
            result = await handle_weather_command(prompt)

        elif command_type == 'recomment_lunch_menu':
            result = await recommend_lunch_menu()

        elif command_type == 'today_proverb':
            result = await handle_today_proverb(prompt)

        elif command_type == "chat_rank_week":
            result = await get_chat_rank(channel_id, room_name, only_meaningful=True, days=7)

        elif command_type == "chat_rank_week_all":
            result = await get_chat_rank(channel_id, room_name, only_meaningful=False, days=7)

        elif command_type == "chat_rank":
            result = await get_chat_rank(channel_id, room_name, only_meaningful=True)

        elif command_type == "chat_rank_all":
            result = await get_chat_rank(channel_id, room_name, only_meaningful=False)

        elif command_type == 'image_generator':
            result = await handle_image_command(prompt)

        elif command_type == 'image_url_generator':
            result = await handle_image_url_command(prompt)

        elif command_type == 'today_bible':
            # 매일성경 묵상 명령어 추가
            result = await handle_today_bible_command()

        elif command_type == 'today_bible2':
            # 매일성경 묵상 명령어 추가

            template_context = {
                "channel_id": context.get("channel_id"),
                "username": context.get("sender"),
                "room": context.get("room"),
                "date_hash_modulo": 66
            }

            prompt_for_llm = await process_template_variables_async("""\
> 오늘의 성경 구절과 묵상 글을 작성해 주세요.

- 다음은 선택할 성경의 장의 내용입니다.

{{RANDOM_BIBLE_CHAPTER_CONTENT}}

- 이 선택된 장에서 성경 묵상글을 작성할 대상이 되는 구절을 선택해 주세요.
- 묵상 글은 깊이 있는 묵상 글로 📖 오른쪽에 바로 이어서 작성해 주세요.
- 아래 [형식]을 반드시 지켜 주세요. 내용 안의 (굿)은 카카오톡 이모티콘이므로 출력할 때 포함하세요.
- 전체 내용을 한글 500자 미만으로 작성해 주세요.

[형식]

(굿) 오늘의 성경 말씀!

구절 내용 (성경 약식 이름 + 장 + 절)

📖""", template_context)
            result = await call_llm_with_fallback(context, prompt_for_llm)

        elif command_type == 'bloomberg_news':
            result = await process_bloomberg_command(context)

        elif command_type == 'exchange_rate':
            result = await handle_exchange_rate_command(prompt)

        # process_command 함수 내에 다음 코드 추가
        elif command_type == 'today_conversation_summary':
            result = await handle_today_conversation_summary(prompt, parameters, context) # parameters는 앞으로 사용 안함

        elif command_type == 'today_conversation_summary_meaningful':
            context['prefix'] = context.get('prefix', '') + '!'  # '!'가 있으면 의미 있는 메시지만 포함
            result = await handle_today_conversation_summary(prompt, parameters, context) # parameters는 앞으로 사용 안함

        elif command_type == 'recent_conversation_summary':
            result = await handle_recent_conversation_summary(prompt, parameters, context) # parameters는 앞으로 사용 안함

        elif command_type == 'recent_conversation_summary_meaningful':
            context['prefix'] = context.get('prefix', '') + '!'  # '!'가 있으면 의미 있는 메시지만 포함
            result = await handle_recent_conversation_summary(prompt, parameters, context) # parameters는 앞으로 사용 안함

        elif command_type == 'user_conversation_summary':
            result = await handle_user_conversation_summary(prompt, parameters, context)

        elif command_type == 'user_conversation_summary_meaningful':
            context['prefix'] = context.get('prefix', '') + '!'  # '!'가 있으면 의미 있는 메시지만 포함
            result = await handle_user_conversation_summary(prompt, parameters, context)

        # 다른 elif 구문들 사이에 추가
        elif command_type in ['start_private_chat', 'start_group_chat', 'extend_chat', 'end_chat']:
            # 세션 명령어 처리
            await handle_session_command(context, command_type)
            return None

        elif command_type == 'youtube_summary':
            logger.debug(f"[DEBUG_CONTEXT_YOUTUBE] context before youtube_summary: {context}")
            result = await handle_youtube_summary(prompt, context)

        elif command_type == 'webpage_summary':
            logger.debug(f"[DEBUG_CONTEXT_WEBPAGE] context before webpage_summary: {context}")
            result = await handle_webpage_summary(prompt, context)

        elif command_type == "korea_market_briefing":
            from services.LS_t1514_service import fetch_briefing
            return await fetch_briefing()

        elif command_type == "version_history":
            from services.version_service import get_version_history_message
            return get_version_history_message()

        elif command_type == "block_conversation_join":
            from core.message_processor import handle_conversation_block_command
            return await handle_conversation_block_command(context)

        elif command_type == 'tts':
            # 기본 TTS 명령어 (언어 자동 감지)
            tts_config = {
                "language": "auto",
                "gender": "F",
                "voice": "auto",
                "intro": [
                    "🧏 음성으로 녹음해 보았어요!",
                    "🔔 내용을 녹음해 보았어요.",
                    "🎙️ 직접 읽어봤어요!",
                    "🎧 이 내용을 들려드릴게요.",
                    "📋 음성으로 정리해 드릴게요!",
                    "🎤 제가 읽어볼게요~",
                    "🎙 짧게 녹음해봤어요!️",
                    "👂 들어보는 게 더 편하죠?",
                    "🗣️ 말로 전해드릴게요!"
                ]
            }
            return await handle_tts_command(prompt, tts_config)

        elif command_type == 'tts_ko':
            # 한국어 TTS 명령어
            tts_config = {
                "language": "ko-KR",
                "gender": "F",
                "voice": "auto"
            }
            return await handle_tts_ko_command(prompt)

        elif command_type == 'tts_en':
            # 영어 TTS 명령어
            tts_config = {
                "language": "en-US",
                "gender": "F",
                "voice": "auto"
            }
            return await handle_tts_en_command(prompt)

        # 아래처럼 command_type 분기에 추가
        elif command_type == "omok_start":
            return await handle_start_command(prompt, parameters, context) # parameters는 앞으로 사용 안함

        elif command_type == "omok_join":
            return await handle_join_game(prompt, context)

        elif command_type == "omok_stop":
            return await handle_stop_command(prompt, context)

        elif command_type == 'todo_list':
            result = await handle_todo_list()

        elif command_type == 'multi_image_generator':
            result = await handle_multiple_image_command(prompt)

        elif command_type == 'show_llm_models':
            from services.show_llm_models import show_llm_models
            result = await show_llm_models(context)

        elif command_type == 'ross_ai':
            result = await handle_ross_ai_command(context, prompt)

        elif command_type == 'imagen_generate':
            result = await handle_imagen_command(context, prompt)

        else:
            logger.error(f"[UNKNOWN_COMMAND_TYPE] {command_type}")
            result = "[ERROR] 알 수 없는 명령입니다."

        # 결과 반환 전 처리
        if result is not None:
            # 스케줄링된 메시지이고 TTS 설정이 있는 경우 TTS 추가
            tts_config = context.get("tts_config")
            if tts_config and tts_config.get("enabled", False) and context.get("is_scheduled", False):
                # 텍스트 결과 추출
                text_result = result[0] if isinstance(result, list) and result else result

                # TTS 생성
                tts_message = await generate_tts_message(
                    text_result,
                    tts_config,
                    context.get("bot_name"),
                    context.get("room")
                )

                # TTS 결과가 있으면 원본 결과에 추가
                if tts_message:
                    if isinstance(result, list):
                        result.append(tts_message)
                    else:
                        result = [result, tts_message]

            # ✅ 캐시 저장 로직 추가
            # cache_response는 메시지 리스트를 기대하므로, result를 항상 리스트로 변환
            messages_to_cache = result if isinstance(result, list) else [result]
            
            # cache_response 내부에서 cache_enabled 여부를 확인하므로 별도 체크 필요 없음
            await cache_response(command_type, prompt, messages_to_cache)
            logger.info(f"[CACHE_SAVE] 캐시 저장 요청: {command_type} - {prompt[:30]}...")

            # 결과가 string이 아니거나 @no-reply인 경우 아카이브하지 않음
            if command_info and command_info.get("enable-archiving", False) and result and result != "@no-reply":
                try:
                    # context에 command_name과 command_type 추가
                    context["command_name"] = prefix
                    context["command_type"] = command_type
                    await save_archived_message_to_db(g.db_pool, context, result)
                except Exception as e:
                    logger.error(f"[ARCHIVE_SAVE_ERROR] 명령어 결과 아카이브 저장 실패 (command: {prefix}) → {e}", exc_info=True)

            return result # 최종 결과 반환 (str 또는 List[str])
        
        # result가 None인 경우 (예: @no-reply 또는 내부적으로 None 반환)
        return result 

    except Exception as e:
        logger.exception(f"[PROCESS_COMMAND_ERROR] {e}")
        # 스케줄링된 메시지의 경우 오류 메시지를 반환하지 않음
        if is_scheduled: # is_scheduled 변수는 함수 상단에서 이미 context에서 가져옴
            logger.error(f"[SCHEDULED_COMMAND_ERROR] 스케줄 명령 실행 실패: {e}")
            return None  # 오류 발생 시 None 반환하여 메시지 전송 안함
        return "[ERROR] 명령 실행 실패"
