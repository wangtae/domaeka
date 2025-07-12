from core.logger import logger
from services.openai_service import call_openai
from services.gemini_service import call_gemini
from services.deepseek_service import call_deepseek
from services.perplexity_service import call_perplexity
from services.grok_service import call_grok
import asyncio
import core.globals as g
import random
from core.utils.send_message import send_message_from_context, send_message_response
from core.utils.auth_utils import is_admin_ext
from core.globals import LLMs_modules

# LLM 함수 매핑
LLM_HANDLERS = {
    'grok': call_grok,
    'openai': call_openai,
    'gemini': call_gemini,
    'deepseek': call_deepseek,
    'perplexity': call_perplexity
}


# ✅ 통합 LLM 호출 함수
async def call_llm_with_fallback(context, prompt, providers=None, retry=None):
    """
    여러 LLM을 인라인으로 순차 시도하는 fallback 함수

    Args:
        context (dict): 메시지 컨텍스트 (대기 메시지 발송용)
        prompt (str): 사용자 질문
        providers (list, optional): 시도할 LLM 제공자 목록 (name, model, timeout, retry, system_prompt 포함)
        retry (int, optional): 각 LLM에 대한 재시도 횟수, None인 경우 LLM별 설정 사용

    Returns:
        str: 최종 LLM 응답 또는 오류 메시지
    """
    # fallback_llms 사용
    fallback_settings = g.fallback_llms

    # --llm-model 파라미터 처리 (관리자 전용)
    llm_model = None
    llm_model_name = None
    if context and "_prompt_parameters" in context:
        llm_model = context["_prompt_parameters"].get("llm-model")
    if llm_model:
        # 관리자 권한 체크
        logger.debug(f"[ADMIN_CHECK] _original_channel_id={context.get('_original_channel_id')}, channel_id={context.get('channel_id')}, user_hash={context.get('user_hash')}, ADMIN_USERS={getattr(g, 'ADMIN_USERS', None)}")
        if not is_admin_ext(context):
            logger.warning(f"[ADMIN_ONLY] --llm-model은 관리자만 사용할 수 있습니다. (user_hash={context.get('user_hash')}, _original_channel_id={context.get('_original_channel_id')}, channel_id={context.get('channel_id')}, requested_model={llm_model})")
            return None
        # providers 리스트에서 해당 모델이 있으면 최상단으로, 없으면 새로 추가
        if not providers:
            providers = fallback_settings
        matched = False
        new_providers = []
        # 1. 정확히 일치하는 provider가 있으면 최상단
        for p in providers:
            if p.get("model") == llm_model:
                new_providers.append(p)
                matched = True
                break
        # 2. LLMs_modules에서 name(제공자명) 추출
        provider_name = None
        if not matched:
            for name, models in LLMs_modules.items():
                if llm_model in models:
                    provider_name = name
                    break
        # 3. name이 추출되면 해당 name의 provider를 찾아서 추가, 없으면 openai로 fallback
        if not matched:
            if provider_name:
                # 기존 providers에서 해당 name의 provider를 찾아서 복사 후 model만 교체
                found = False
                for p in providers:
                    if p.get("name") == provider_name:
                        new_p = p.copy()
                        new_p["model"] = llm_model
                        new_providers.append(new_p)
                        found = True
                        break
                if not found:
                    # name만 맞춰서 새로 추가
                    new_providers.append({"name": provider_name, "model": llm_model, "timeout": 30, "retry": 0, "system_prompt": providers[0].get("system_prompt") if providers else ""})
            else:
                # name 추정 실패시 openai로 fallback
                new_providers.append({"name": "openai", "model": llm_model, "timeout": 30, "retry": 0, "system_prompt": providers[0].get("system_prompt") if providers else ""})
        # 기존 providers에서 중복 제거
        for p in providers:
            if p.get("model") != llm_model:
                new_providers.append(p)
        providers = new_providers
        llm_model_name = llm_model

    if not providers:
        providers = fallback_settings

    # name → fallback 세부 정보 맵
    fallback_map = {llm["name"]: llm for llm in fallback_settings}

    final_response = None
    used_model_name = None

    for i, provider in enumerate(providers):
        provider_name = provider["name"]
        model = provider.get("model")
        timeout = provider.get("timeout", 20)
        system_prompt = provider.get("system_prompt")

        # fallback_llms에 정의된 system_prompt가 기본
        if not system_prompt:
            system_prompt = fallback_map.get(provider_name, {}).get("system_prompt")

        # 재시도 횟수 설정
        provider_retry = retry if retry is not None else provider.get("retry", 0)

        if i > 0:
            await send_thinking_message(context)

        logger.info(f"[LLM Fallback] 시도 {i + 1}/{len(providers)}: {provider_name} (model: {model})")

        handler = LLM_HANDLERS.get(provider_name)
        if not handler:
            logger.warning(f"[LLM Fallback] 지원하지 않는 provider: {provider_name}")
            continue

        # 프롬프트 + 요청 데이터 구성
        data = {
            "messages": [
                {"role": "system", "content": system_prompt or ""},
                {"role": "user", "content": prompt}
            ],
            "temperature": 0.7,
            "max_tokens": 5000
        }

        try:
            response = await asyncio.wait_for(
                handler(model, data, retry=provider_retry, timeout=timeout),
                timeout=timeout * (provider_retry + 1) + 5
            )

            if response and not (
                    response.startswith("⚠️") or response.startswith("⏳") or response.startswith("[ERROR]")):
                logger.info(f"[LLM Fallback] {provider_name} 응답 성공")
                final_response = response
                used_model_name = model  # 실제 사용된 모델명 저장
                break
            else:
                logger.warning(f"[LLM Fallback] {provider_name} 오류 응답: {response[:50]}...")
        except asyncio.TimeoutError:
            logger.warning(f"[LLM Fallback] {provider_name} 타임아웃 발생")
        except Exception as e:
            logger.exception(f"[LLM Fallback] {provider_name} 예외 발생: {e}")

    if final_response:
        # 마크다운 제거
        for md in ["**", "##"]:
            final_response = final_response.replace(md, "")
        # --show-llm-model=true가 명시적으로 전달된 경우에만 실제 사용된 모델명을 응답 마지막에 표시
        show_llm_model = False
        if context and "_prompt_parameters" in context:
            show_llm_model = str(context["_prompt_parameters"].get("show-llm-model", "")).lower() == "true"
        if show_llm_model and used_model_name:
            # 사람이 읽기 쉬운 LLM 이름으로 변환
            human_llm_name = None
            for provider, models in LLMs_modules.items():
                if used_model_name in models:
                    human_llm_name = models[used_model_name]
                    break
            if not human_llm_name:
                human_llm_name = used_model_name
            final_response = f"{final_response}\n\n사용된 LLM MODEL: {human_llm_name}"
        return final_response
    else:
        return "❌ 모든 AI 모델 시도에 실패했어요. 잠시 후 다시 시도해 주세요."


async def send_thinking_message(context: dict = None):
    if context:
        thinking_msg = random.choice(g.THINKING_MESSAGES)
        await send_message_response(context, thinking_msg)
    else:
        logger.warning("[THINKING_MESSAGE] context 인자가 없어 메시지를 전송할 수 없습니다.")
