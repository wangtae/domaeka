import json
import httpx
import logging
import asyncio
import re
from config.loader import load_config
from core.globals import http_client, fallback_llms
from core.logger import logger

CONFIG = load_config()
API_KEY = CONFIG['APIs']['PERPLEXITY']['KEY']


def remove_markdown_emphasis(text):
    """마크다운 강조 표시(**) 제거 함수"""
    return re.sub(r'\*\*(.*?)\*\*', r'\1', text)


async def call_perplexity(model, data, retry=0, timeout=None):
    """
    Perplexity API 호출 함수 (모델 외부 분리형)

    Args:
        model (str): 사용할 모델명 (예: sonar-pro, mixtral-8x7b-instruct)
        data (dict): OpenAI-style 메시지 형식 데이터
        retry (int): 재시도 횟수
        timeout (int): 타임아웃(초)

    Returns:
        str: 응답 텍스트 또는 오류 메시지
    """
    perplexity_config = next((x for x in fallback_llms if x['name'] == 'perplexity'), {})
    timeout = timeout or perplexity_config.get('timeout', 15)

    if timeout > 30:
        logger.warning(f"[PERPLEXITY] 타임아웃 값({timeout}초)이 너무 큽니다. 20초로 조정합니다.")
        timeout = 20

    connect_timeout = min(5.0, timeout / 2)

    # 모델 기본값 및 모델 목록 설정
    default_model = "sonar-pro"
    models = ["sonar-pro", "mixtral-8x7b-instruct"]
    model_index = models.index(model) if model in models else 0

    # temperature, max_tokens 기본값 설정
    if "temperature" not in data:
        data["temperature"] = 0.7
    if "max_tokens" not in data:
        data["max_tokens"] = 1500

    url = "https://api.perplexity.ai/chat/completions"
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }

    custom_timeout = httpx.Timeout(timeout, connect=connect_timeout)

    logger.debug(f"[PERPLEXITY] 요청 URL: {url}")
    logger.info(f"[PERPLEXITY] 타임아웃 설정: {timeout}초 (연결: {connect_timeout}초), 최대 재시도: {retry}회")

    retry_count = 0
    max_retries = retry

    while retry_count <= max_retries:
        current_model = models[model_index % len(models)]
        data["model"] = current_model

        logger.info(f"[PERPLEXITY] 시도 {retry_count + 1}/{max_retries + 1}: 모델={current_model}")
        logger.debug(f"[PERPLEXITY] 요청 데이터: {json.dumps(data, ensure_ascii=False)}")

        try:
            res = await http_client.post(url, headers=headers, json=data, timeout=custom_timeout)
            logger.debug(f"[PERPLEXITY] 응답 상태코드: {res.status_code}")

            if res.status_code == 200:
                result_json = res.json()
                result = result_json["choices"][0]["message"]["content"].strip()
                result = remove_markdown_emphasis(result)
                logger.info(f"[PERPLEXITY] 응답 요약 (시도 {retry_count + 1}): {result[:50]}...")
                return result

            elif res.status_code == 400 and "Invalid model" in res.text:
                logger.warning(f"[PERPLEXITY] 모델 오류 발생: {current_model}")
                model_index = (model_index + 1) % len(models)
                continue  # 모델 변경 후 재시도 (retry_count 증가 안 함)

            else:
                logger.error(f"[PERPLEXITY] HTTP 오류 (시도 {retry_count + 1}): {res.status_code} - {res.text}")
                retry_count += 1
                if retry_count <= max_retries:
                    wait_time = 0.5 * (2 ** (retry_count - 1))
                    logger.info(f"[PERPLEXITY] {wait_time}초 후 재시도합니다...")
                    await asyncio.sleep(wait_time)
                else:
                    return "⚠️ Perplexity API에서 오류 응답이 반환되었습니다. 다른 AI 명령어(>, >>, >>>)를 이용해 보세요."

        except (httpx.ReadTimeout, httpx.ConnectTimeout, asyncio.TimeoutError) as timeout_err:
            logger.warning(f"[PERPLEXITY] 타임아웃 발생 (시도 {retry_count + 1}): {str(timeout_err)}")
            retry_count += 1
            if retry_count <= max_retries:
                timeout = min(timeout * 1.2, 30)
                custom_timeout = httpx.Timeout(timeout, connect=connect_timeout)
                model_index = (model_index + 1) % len(models)
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.info(f"[PERPLEXITY] {wait_time}초 후 재시도합니다...")
                await asyncio.sleep(wait_time)
            else:
                return "⚠️ Perplexity API 응답 시간이 계속 초과됩니다. 다른 AI 명령어(>, >>, >>>)를 이용해 보세요."

        except Exception as e:
            logger.error(f"[PERPLEXITY] 오류 발생 (시도 {retry_count + 1}): {str(e)}")
            retry_count += 1
            if retry_count <= max_retries:
                wait_time = 0.5 * (2 ** (retry_count - 1))
                logger.info(f"[PERPLEXITY] {wait_time}초 후 재시도합니다...")
                await asyncio.sleep(wait_time)
            else:
                return "⚠️ 예기치 않은 오류가 발생했습니다. 다시 시도해 주세요."
