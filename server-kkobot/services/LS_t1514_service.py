import httpx
import asyncio
import time
from datetime import datetime, timedelta
from config.loader import load_config
from core.logger import logger

CONFIG = load_config()
APP_KEY = CONFIG['APIs']['LS']['API_KEY']
APP_SECRET = CONFIG['APIs']['LS']['SECRET_KEY']
MAC_ADDRESS = CONFIG['APIs']['LS'].get('MAC', '000000000000')

API_URL = "https://openapi.ls-sec.co.kr:8080"
TOKEN_URL = f"{API_URL}/oauth2/token"
T1514_URL = f"{API_URL}/indtp/market-data"

UPCODE_NAME_MAP = {
    "001": "코스피",
    "301": "코스닥",
    "101": "코스피200"
}


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


def call_t1514(token: str, upcode: str, base_date: str):
    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "Authorization": f"Bearer {token}",
        "tr_cd": "t1514",
        "tr_cont": "N",
        "tr_cont_key": "",
        "mac_address": MAC_ADDRESS
    }

    payload = {
        "t1514InBlock": {
            "upcode": upcode,
            "gubun1": " ",
            "gubun2": "1",
            "cts_date": base_date,
            "cnt": 1,
            "rate_gbn": "2"
        }
    }

    logger.debug(f"[REQUEST] upcode={upcode}, payload={payload}")
    response = httpx.post(T1514_URL, headers=headers, json=payload)
    response.raise_for_status()
    return response.json()


def format_market_status(row: dict, name: str) -> str:
    try:
        jisu = row.get("jisu", "?")
        rate = row.get("diff", "?")
        up = row.get("high", "?")
        up_limit = row.get("up", "?")
        unchg = row.get("unchg", "?")
        down = row.get("low", "?")
        dn_limit = row.get("down", "?")

        return (
            f"\n· {name}: {jisu} (+{rate}％)\n"
            f"--------------------------\n"
            f"🔴 상승: {up}종목 (📈 상한: {up_limit})\n"
            f"➖ 보합: {unchg}종목\n"
            f"🔵 하락: {down}종목 (📉 하한: {dn_limit})"
        )
    except Exception as e:
        logger.warning(f"[FORMAT ERROR] {name}: {e}")
        return f"\n📌 {name}: 데이터 파싱 오류"


def get_base_date():
    now = datetime.now()
    if now.weekday() >= 5 or (now.hour < 15 or (now.hour == 15 and now.minute < 45)):
        base = now
        while base.weekday() >= 5:
            base -= timedelta(days=1)
        if now.weekday() >= 5 or now.hour < 15:
            base -= timedelta(days=1)
            while base.weekday() >= 5:
                base -= timedelta(days=1)
        return base.strftime("%Y%m%d")
    return now.strftime("%Y%m%d")


async def fetch_briefing() -> str:
    try:
        logger.info("[LS T1514 SERVICE] 시장 브리핑 시작")
        token = get_access_token()
        base_date = get_base_date()
        result_lines = [f"📊 한국증시 장마감 현황 ({base_date[:4]}-{base_date[4:6]}-{base_date[6:]})"]

        for upcode in ["001", "301", "101"]:
            try:
                await asyncio.sleep(1)
                data = call_t1514(token, upcode, base_date)
                row = data.get("t1514OutBlock1", [{}])[0]
                if row:
                    result_lines.append(format_market_status(row, UPCODE_NAME_MAP[upcode]))
                else:
                    result_lines.append(f"\n📌 {UPCODE_NAME_MAP[upcode]}: 데이터 수신 실패")
            except Exception as e:
                logger.error(f"[LS T1514 ERROR] {upcode}: {e}")
                result_lines.append(f"\n📌 {UPCODE_NAME_MAP[upcode]}: 데이터 수신 실패 (서버 오류)")

        result_lines.append("\n🏁 #한국증시 장마감 # 장마감")
        return "\n".join(result_lines)

    except Exception as e:
        logger.exception("[LS T1514 SERVICE] 전체 실패")
        return "⚠️ 시장 지수 정보를 불러오지 못했습니다."


if __name__ == "__main__":
    print(asyncio.run(fetch_briefing()))
