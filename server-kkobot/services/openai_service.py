import json
import httpx
import asyncio
from config.loader import load_config
from core.globals import http_client, fallback_llms
from core.logger import logger

CONFIG = load_config()
API_KEY = CONFIG['APIs']['OPENAI']['KEY']


async def call_openai(model, data, retry=0, timeout=None):
    """
    OpenAI API 호출 함수 (model을 별도 인자로 받음)

    Args:
        model (str): 사용할 모델명 (예: gpt-4o, gpt-3.5-turbo)
        data (dict): messages, temperature, max_tokens 포함된 요청 본문
        retry (int): 재시도 횟수
        timeout (int): 타임아웃 시간

    Returns:
        str: OpenAI 응답 텍스트 또는 오류 메시지
    """
    openai_config = next((x for x in fallback_llms if x['name'] == 'openai'), {})
    timeout = timeout or openai_config.get('timeout', 15)

    # ✅ model을 외부에서 받음
    if not model:
        logger.error("[OPENAI] 모델명이 지정되지 않았습니다.")
        return "[ERROR] 모델명이 누락되었습니다."

    # ✅ 기본값 설정
    if "temperature" not in data:
        data["temperature"] = 0.7
    if "max_tokens" not in data:
        data["max_tokens"] = 800

    data["model"] = model

    url = "https://api.openai.com/v1/chat/completions"
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }

    logger.debug(f"[OPENAI] 요청 URL: {url}")
    logger.debug(f"[OPENAI] 요청 데이터: {json.dumps(data, ensure_ascii=False)}")

    retry_count = 0
    max_retries = retry

    while retry_count <= max_retries:
        try:
            res = await http_client.post(url, headers=headers, json=data, timeout=timeout)
            logger.debug(f"[OPENAI] 응답 상태코드: {res.status_code}")
            res.raise_for_status()

            result_json = res.json()
            logger.debug(f"[OPENAI] 응답 원문: {json.dumps(result_json, ensure_ascii=False)}")

            result = result_json["choices"][0]["message"]["content"].strip()
            logger.info(f"[OPENAI] 응답 요약: {result[:50]}...")
            return result

        except httpx.ReadTimeout:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[OPENAI] 타임아웃 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries})")
                await asyncio.sleep(wait_time)
            else:
                logger.error(f"[OPENAI] 최대 재시도 횟수 초과 ({max_retries})")
                return "⚠️ OpenAI 응답 시간이 초과되었어요. 잠시 후 다시 시도해 주세요."

        except httpx.HTTPStatusError as http_err:
            retry_count += 1
            if retry_count <= max_retries and (
                    http_err.response.status_code >= 500 or http_err.response.status_code == 429
            ):
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(
                    f"[OPENAI] HTTP 오류({http_err.response.status_code}). {wait_time}초 후 재시도 ({retry_count}/{max_retries})")
                await asyncio.sleep(wait_time)
            else:
                logger.error(f"[OPENAI] HTTP 오류: 상태코드: {http_err.response.status_code} / 내용: {http_err.response.text}")
                return "⚠️ 요청 처리 중 문제가 발생했어요. 잠시 후 다시 시도해 주세요."

        except Exception as e:
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.warning(f"[OPENAI] 예외 발생. {wait_time}초 후 재시도 ({retry_count}/{max_retries}): {e}")
                await asyncio.sleep(wait_time)
            else:
                logger.exception(f"[OPENAI ERROR] 예외 발생: {e}")
                return "⚠️ 응답을 받는 데 문제가 발생했어요. 다시 시도해 주세요."
