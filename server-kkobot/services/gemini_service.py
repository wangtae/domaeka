import re
import httpx
import json
import asyncio
from config.loader import load_config
from core.logger import logger
from core.globals import fallback_llms

CONFIG = load_config()
API_KEY = CONFIG['APIs']['GEMINI']['KEY']

HEADERS = {
    "Content-Type": "application/json",
    "x-goog-api-key": API_KEY
}


def remove_markdown_emphasis(text):
    """텍스트에서 마크다운 강조 표시(**) 제거"""
    return re.sub(r'\*\*(.*?)\*\*', r'\1', text)


async def call_gemini(model, data, retry=0, timeout=None):
    """
    OpenAI 포맷 기반 Gemini API 호출 함수

    Args:
        model (str): 사용할 Gemini 모델명 (예: gemini-1.5-pro)
        data (dict): OpenAI-style 요청 데이터 (messages, temperature, max_tokens 등)
        retry (int): 재시도 횟수
        timeout (int): 타임아웃 시간

    Returns:
        str: Gemini 응답 텍스트 또는 오류 메시지
    """
    gemini_config = next((x for x in fallback_llms if x['name'] == 'gemini'), {})
    timeout = timeout or gemini_config.get('timeout', 15)

    if not model:
        logger.error("[GEMINI] 모델명이 지정되지 않았습니다.")
        return "[ERROR] 모델명이 누락되었습니다."

    url = f"https://generativelanguage.googleapis.com/v1/models/{model}:generateContent"

    # ✅ OpenAI 포맷 messages → Gemini contents 변환
    try:
        messages = data.get("messages", [])
        contents = []
        system_prompt = ""

        for msg in messages:
            role = msg.get("role")
            content = msg.get("content", "")
            if not role or not content:
                continue

            if role == "system":
                system_prompt += content.strip() + " 여기까지의 지침은 시스템 프롬프트이므로 작성한 내용에 포함되선 안됩니다.\n\n"
            else:
                full_text = system_prompt + content if system_prompt else content
                contents.append({
                    "role": role,
                    "parts": [{"text": full_text.strip()}]
                })
                system_prompt = ""

    except Exception as e:
        logger.exception("[GEMINI] 메시지 변환 중 오류 발생")
        return "[ERROR] 메시지 변환 중 오류 발생"

    # ✅ Gemini API payload 구성
    payload = {
        "contents": contents,
        "generationConfig": {
            "temperature": data.get("temperature", 0.7),
            "maxOutputTokens": data.get("max_tokens", 800)
        }
    }

    retry_count = 0
    max_retries = retry

    while retry_count <= max_retries:
        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(timeout)) as client:
                logger.debug(f"[GEMINI REQUEST] 모델: {model}, Payload: {json.dumps(payload, ensure_ascii=False)}")
                response = await client.post(url, headers=HEADERS, json=payload)
                logger.debug(f"[GEMINI] 응답 상태코드: {response.status_code}")
                response.raise_for_status()

                result_json = response.json()
                reply = result_json['candidates'][0]['content']['parts'][0]['text'].strip()
                reply = remove_markdown_emphasis(reply)
                logger.debug(f"[GEMINI RESPONSE] {reply}")
                return reply

        except httpx.ReadTimeout:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[GEMINI] 타임아웃 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries})")
                await asyncio.sleep(wait_time)
            else:
                logger.error(f"[GEMINI] 최대 재시도 횟수 초과 ({max_retries})")
                return "⏳ Gemini 응답이 지연되고 있어요. 잠시 후 다시 시도해 주세요."

        except httpx.HTTPStatusError as http_err:
            retry_count += 1
            if retry_count <= max_retries and (
                    http_err.response.status_code >= 500 or http_err.response.status_code == 429
            ):
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(
                    f"[GEMINI] HTTP 오류({http_err.response.status_code}). {wait_time}초 후 재시도 ({retry_count}/{max_retries})"
                )
                await asyncio.sleep(wait_time)
            else:
                logger.error(
                    f"[GEMINI HTTP ERROR] 상태코드: {http_err.response.status_code} / 내용: {http_err.response.text}"
                )
                return "⚠️ 서버에서 오류가 발생했어요. 잠시 후 다시 시도해 주세요."

        except Exception as e:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[GEMINI] 예외 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries}): {e}")
                await asyncio.sleep(wait_time)
            else:
                logger.exception(f"[GEMINI ERROR] 예외 발생: {e}")
                return "⚠️ 요청을 처리하는 중 문제가 발생했어요. 다시 시도해 주세요."
