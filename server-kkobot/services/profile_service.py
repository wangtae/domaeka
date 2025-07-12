# ✅ services/profile_service.py
import json
import core.globals as g
from core.logger import logger
from services.llm_fallback_service import call_llm_with_fallback
from core.db_utils import fetch_recent_messages
from core.globals import find_provider_by_model

# ✅ 프로필 분석 설정 로드
try:
    with g.JSON_CONFIG_FILES["profile_analysis"].open(encoding="utf-8") as f:
        profile_config = json.load(f)
        logger.info("[CONFIG] 프로필 분석 설정 로드 완료")
except Exception as e:
    profile_config = {}
    logger.exception(f"[ERROR] profile_analysis.json 로드 실패 → {e}")


# ✅ 룸별 설정값 조회
def get_room_config(bot_name, channel_id):
    defaults = profile_config.get("defaults", {})
    return profile_config.get("rooms", {}).get(bot_name, {}).get(str(channel_id), {}) or defaults


# ✅ 기본 providers 생성
def get_default_providers(system_prompt):
    return [
        {
            "name": "openai",
            "timeout": 30,
            "model": "gpt-4o",
            "retry": 0,
            "system_prompt": system_prompt
        },
        {
            "name": "gemini",
            "model": "gemini-1.5-pro",
            "timeout": 30,
            "retry": 0,
            "system_prompt": system_prompt
        },
        {
            "name": "grok",
            "model": "grok-3-latest",
            "timeout": 30,
            "retry": 0,
            "system_prompt": system_prompt
        }
    ]


# ✅ 프로필 분석 함수
async def analyze_profile(bot_name, channel_id, user_hash, prompt, parameters=None, is_self=True, room_name=None,
                          sender=None):
    parameters = parameters or {}  # None 방지

    # 설정 가져오기
    room_config = get_room_config(bot_name, channel_id)
    defaults = profile_config.get("defaults", {})

    # 히스토리 설정
    history_limit = room_config.get("history_limit", defaults.get("history_limit", 500))
    min_required = room_config.get("self_min_history" if is_self else "other_min_history", 10)

    logger.debug(f"[PROFILE_ANALYSIS] 히스토리: {history_limit}, 최소 필요: {min_required}")
    logger.debug(f"[USER_HASH] {user_hash}, [ROOM_NAME] {channel_id}, [SENDER] {sender}")

    # 대화 로그 가져오기
    history = await fetch_recent_messages(
        pool=g.db_pool,
        channel_id=channel_id,
        user_hash=user_hash,
        limit=history_limit
    )

    logger.debug(f"[HISTORY_FETCHED] {len(history)}개 조회됨")
    if len(history) < min_required:
        return f"📉 분석을 위한 최소 대화 수는 {min_required}개입니다. 현재는 {len(history)}개밖에 없어요."

    # 시스템 프롬프트 구성
    messages_text = "\n".join([f"- {msg}" for msg in history])
    system_prompt = (
        f"다음은 '{sender}'님의 과거 대화 기록입니다. 이 기록을 분석하여 사용자의 성격과 성향을 정리해주세요.\n"
        f"다음 내용을 포함해 주세요:\n"
        f"1. 전반적인 성격 특성(외향/내향, 긍정/부정 등)\n"
        f"2. 의사소통 스타일과 관심사\n"
        f"3. 대화에서 드러나는 가치관이나 우선순위\n\n"
        f"직접적이고 친근한 톤으로 설명해 주세요. 너무 진지하거나 학술적인 분석은 피해주세요.\n\n"
        f"[대화 기록]\n{messages_text}"
    )

    logger.debug("[PROFILE_ANALYSIS] 시스템 프롬프트 구성 완료")

    # 🔥 model 파라미터로 provider 설정
    model_from_user = parameters.get("model")
    if model_from_user:
        provider_name = find_provider_by_model(model_from_user)
        if provider_name:
            providers = [{
                "name": provider_name,
                "model": model_from_user,
                "timeout": 30,
                "retry": 0,
                "system_prompt": system_prompt
            }]
            logger.debug(f"[PROFILE_ANALYSIS] 사용자 모델 적용 → {providers}")
        else:
            logger.warning(f"[PROFILE_ANALYSIS] 모델 '{model_from_user}'에 대한 provider를 찾을 수 없습니다. 기본 providers 사용")
            providers = get_default_providers(system_prompt)
    else:
        providers = get_default_providers(system_prompt)

    # 가상의 received_message 객체 생성
    received_message = {
        "bot_name": bot_name,
        "channel_id": channel_id,
        "user_hash": user_hash,
        "sender": sender,
        "room": room_name,
    }

    user_prompt = f"'{sender}'님의 성격과 성향을 분석해주세요."
    result = await call_llm_with_fallback(received_message, user_prompt, providers)

    if not result:
        return "성격 분석 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."

    final_result = f"🔍 {sender}님의 성격 분석 결과입니다!\n\n{result}"
    return final_result
