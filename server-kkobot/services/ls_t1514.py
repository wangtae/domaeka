import httpx
import asyncio
from datetime import datetime
from config.loader import load_config
from core.logger import logger

# ✅ 환경설정 로드
CONFIG = load_config()
APP_KEY = CONFIG['APIs']['LS']['API_KEY']
APP_SECRET = CONFIG['APIs']['LS']['SECRET_KEY']

API_URL = "https://openapi.ls-sec.co.kr:8080"
TOKEN_URL = f"{API_URL}/oauth2/token"
T1514_URL = f"{API_URL}/indtp/market-data"


def get_access_token():
    headers = {"Content-Type": "application/x-www-form-urlencoded"}
    data = {
        "grant_type": "client_credentials",
        "appkey": APP_KEY,
        "appsecretkey": APP_SECRET,
        "scope": "oob"
    }

    response = httpx.post(TOKEN_URL, headers=headers, data=data)
    response.raise_for_status()
    token_data = response.json()
    return token_data['access_token']


def call_t1514(token: str, upcode: str, base_date: str = None):
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {token}",
        "tr_cd": "t1514",
        "tr_cont": "N"
    }

    if base_date is None:
        base_date = datetime.now().strftime('%Y%m%d')

    payload = {
        "t1514InBlock": {
            "upcode": upcode,
            "gubun1": " ",
            "gubun2": "1",
            "cts_date": base_date,
            "cnt": 3,
            "rate_gbn": "2"
        }
    }

    response = httpx.post(T1514_URL, headers=headers, json=payload)
    logger.debug(f"[REQUEST] upcode={upcode}, payload={payload}")
    response.raise_for_status()
    data = response.json()
    return data


def print_result(data: dict, upcode: str):
    rows = data.get("t1514OutBlock1", [])
    print(f"\n[업코드 {upcode}]")
    for row in rows:
        print(f"{row['date']} | 상승: {row['up']} | 하락: {row['down']} | 보합: {row['unchg']}")


async def main():
    try:
        logger.info("[LS T1514 START]")
        token = get_access_token()
        logger.info("[TOKEN ACQUIRED]")

        for upcode in ["001", "301"]:
            try:
                result = call_t1514(token, upcode)
                print_result(result, upcode)
            except httpx.HTTPStatusError as e:
                logger.error(f"[HTTP ERROR] {e.response.status_code} - {e.response.text}")
            except Exception as e:
                logger.exception(f"[ERROR] {e}")

        logger.info("[LS T1514 DONE]")

    except Exception as e:
        logger.exception(f"[FATAL ERROR] {e}")


if __name__ == "__main__":
    asyncio.run(main())
