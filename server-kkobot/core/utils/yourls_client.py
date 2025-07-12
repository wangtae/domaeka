import requests
import hashlib
import re
from core.logger import logger
from config.loader import load_config

CONFIG = load_config()
YOURLS_SIGNATURE = CONFIG['APIs']['YOURLS']['SIGNATURE']
YOURLS_API_URL = "https://go.loa.best/yourls-api.php"


def generate_keyword(url: str, prefix: str = "k") -> str:
    reversed_url = url[::-1]
    cleaned = re.sub(r"[/:]", "", reversed_url)
    hash_id = hashlib.md5(cleaned.encode("utf-8")).hexdigest()
    return f"{prefix}{hash_id[:8]}"


def shorten_url_with_yourls(original_url: str, prefix: str = "kb", allow_keyword: bool = True) -> str:
    keyword = generate_keyword(original_url, prefix=prefix) if allow_keyword else None

    payload = {
        "signature": YOURLS_SIGNATURE,
        "action": "shorturl",
        "format": "json",
        "url": original_url
    }

    if keyword:
        payload["keyword"] = keyword

    try:
        response = requests.get(YOURLS_API_URL, params=payload, timeout=5)
        result = response.json()

        # ✅ 성공 또는 중복이라도 shorturl이 있으면 그대로 사용
        if "shorturl" in result:
            short = result["shorturl"]
            logger.info(f"[YOURLS] 단축 또는 중복 URL 사용 → {original_url} → {short}")
            return short

        logger.warning(f"[YOURLS] 단축 실패 → 전체 응답: {result} / 키워드: {keyword}")
        return original_url

    except Exception as e:
        logger.error(f"[YOURLS] 단축 요청 중 오류: {e}")
        return original_url
