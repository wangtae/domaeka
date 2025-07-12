import httpx
import json
import re
import asyncio
from config.loader import load_config
from core.logger import logger
from core.globals import fallback_llms

CONFIG = load_config()
API_KEY = CONFIG['APIs']['DEEPSEEK']['KEY']

HEADERS = {
    "Content-Type": "application/json",
    "Authorization": f"Bearer {API_KEY}"
}


def remove_markdown_emphasis(text):
    """
    텍스트에서 마크다운 강조 표시(**)를 제거
    """
    return re.sub(r'\*\*(.*?)\*\*', r'\1', text)


async def call_deepseek(model, data, retry=0, timeout=None):
    """
    OpenAI 포맷 기반 Deepseek API 호출 함수

    Args:
        model (str): 사용할 모델명 (예: "deepseek-chat")
        data (dict): OpenAI 스타일 요청 데이터 (messages, temperature, max_tokens 등)
        retry (int): 재시도 횟수
        timeout (int): 타임아웃 시간

    Returns:
        str: Deepseek 응답 또는 오류 메시지
    """
    logger.debug("[DEEPSEEK START]")

    config = next((x for x in fallback_llms if x['name'] == 'deepseek'), {})
    timeout = timeout or config.get('timeout', 20)

    if not model:
        logger.error("[DEEPSEEK] 모델명이 지정되지 않았습니다.")
        return "[ERROR] 모델명이 누락되었습니다."

    url = "https://api.deepseek.com/v1/chat/completions"

    # ✅ 요청 파라미터 설정 (OpenAI 스타일)
    if "temperature" not in data:
        data["temperature"] = 0.7
    if "max_tokens" not in data:
        data["max_tokens"] = 1500

    data["model"] = model

    retry_count = 0
    max_retries = retry

    while retry_count <= max_retries:
        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(timeout)) as client:
                logger.debug(f"[DEEPSEEK REQUEST] 모델: {model}, Payload: {json.dumps(data, ensure_ascii=False)}")
                response = await client.post(url, headers=HEADERS, json=data)
                logger.debug(f"[DEEPSEEK] 응답 상태코드: {response.status_code}")
                response.raise_for_status()

                result_json = response.json()
                reply = result_json["choices"][0]["message"]["content"].strip()
                reply = remove_markdown_emphasis(reply)

                logger.debug(f"[DEEPSEEK RESPONSE] {reply}")
                return reply

        except httpx.ReadTimeout:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[DEEPSEEK] 타임아웃 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries})")
                await asyncio.sleep(wait_time)
            else:
                logger.error(f"[DEEPSEEK] 최대 재시도 횟수 초과 ({max_retries})")
                return "⚠️ 응답이 지연되고 있어요. 잠시 후 다시 시도해 주세요."

        except httpx.HTTPStatusError as http_err:
            retry_count += 1
            if retry_count <= max_retries and (
                    http_err.response.status_code >= 500 or http_err.response.status_code == 429
            ):
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(
                    f"[DEEPSEEK] HTTP 오류({http_err.response.status_code}). {wait_time}초 후 재시도 ({retry_count}/{max_retries})")
                await asyncio.sleep(wait_time)
            else:
                logger.error(
                    f"[DEEPSEEK HTTP ERROR] 상태코드: {http_err.response.status_code} / 내용: {http_err.response.text}")
                return "⚠️ 서버에서 오류가 발생했어요. 잠시 후 다시 시도해 주세요."

        except Exception as e:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[DEEPSEEK] 예외 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries}): {e}")
                await asyncio.sleep(wait_time)
            else:
                logger.exception(f"[DEEPSEEK ERROR] 예외 발생: {e}")
                return "⚠️ 요청을 처리하는 중 문제가 발생했어요. 다시 시도해 주세요."
