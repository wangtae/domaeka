"""
환율 정보 조회 서비스
LOA.i 카카오톡 봇 시스템을 위한 환율 데이터 파싱 및 응답 생성 모듈
"""

import aiohttp
import asyncio
import datetime
import logging
from bs4 import BeautifulSoup
from core.utils.send_message import send_message
import core.globals as g  # 옵션 1

logger = logging.getLogger(__name__)

# 나라 순서와 이모지 매핑
COUNTRY_ORDER = [
    {"code": "USD", "name": "미국", "emoji": "🇺🇸"},
    {"code": "EUR", "name": "유럽", "emoji": "🇪🇺"},
    {"code": "JPY", "name": "일본", "emoji": "🇯🇵"},
    {"code": "CNY", "name": "중국", "emoji": "🇨🇳"},
    {"code": "GBP", "name": "영국", "emoji": "🇬🇧"},
    {"code": "AUD", "name": "호주", "emoji": "🇦🇺"},
    {"code": "CAD", "name": "캐나다", "emoji": "🇨🇦"}
]

async def fetch_exchange_rate_data():
    """
    환율 정보를 크롤링하여 반환하는 함수
    
    Returns:
        dict: 환율 정보를 담은 딕셔너리 (통화코드를 키로 사용)
    """
    url = "https://www.kita.net/cmmrcInfo/ehgtGnrlzInfo/rltmEhgt.do"

    try:
        async with aiohttp.ClientSession() as session:
            async with session.get(url) as response:
                if response.status != 200:
                    logger.error(f"환율 정보 가져오기 실패: 상태 코드 {response.status}")
                    return None

                html_content = await response.text()

                # HTML 파싱
                soup = BeautifulSoup(html_content, 'html.parser')
                table = soup.select_one('table.table-bordered')

                if not table:
                    logger.error("환율 테이블을 찾을 수 없습니다.")
                    return None

                rows = table.select('tbody tr')

                exchange_data = {}

                for row in rows:
                    # 통화코드 추출
                    currency_link = row.select_one('th.bg-label a')
                    if not currency_link:
                        continue

                    currency_text = currency_link.text
                    currency_code = currency_text.split()[0]

                    # 환율 정보 추출
                    cells = row.select('td')
                    if len(cells) < 4:
                        continue

                    rate = cells[0].text.strip()
                    change = cells[1].text.strip()
                    change_rate = cells[2].text.strip()

                    # 상승/하락 화살표 구분
                    if "▼" in change:
                        change_arrow = "🔻"
                    elif "▲" in change:
                        change_arrow = "🔺"
                    else:
                        change_arrow = "-"

                    # 숫자만 추출
                    rate = rate.replace(',', '')
                    change_value = ''.join(filter(lambda x: x.isdigit() or x == '.', change))

                    exchange_data[currency_code] = {
                        "currency": currency_code,
                        "country": next((item["name"] for item in COUNTRY_ORDER if item["code"] == currency_code), "기타"),
                        "emoji": next((item["emoji"] for item in COUNTRY_ORDER if item["code"] == currency_code), "🌐"),
                        "rate": float(rate),
                        "change": float(change_value) if change_value else 0,
                        "change_dir": change_arrow,
                        "change_rate": float(change_rate) if change_rate.strip() else 0
                    }

                return exchange_data

    except Exception as e:
        logger.error(f"환율 정보 가져오기 중 오류 발생: {str(e)}")
        return None

def format_exchange_rate_message(exchange_data):
    """
    환율 정보를 메시지 형식으로 변환하는 함수
    
    Args:
        exchange_data (dict): 환율 정보 딕셔너리
        
    Returns:
        str: 형식화된 환율 정보 메시지
    """
    if not exchange_data:
        return "환율 정보를 가져오는 데 실패했습니다."

    today = datetime.datetime.now()
    date_str = today.strftime("%Y-%m-%d")

    # 요일 계산 (한국어)
    weekdays = ['월', '화', '수', '목', '금', '토', '일']
    weekday = weekdays[today.weekday()]

    message_lines = [f"💰 환율 정보 ({date_str}, {weekday})"]

    # 정해진 순서대로 통화 정보 추가
    for country_info in COUNTRY_ORDER:
        currency_code = country_info["code"]
        if currency_code in exchange_data:
            data = exchange_data[currency_code]

            # 상승/하락 이모티콘 설정
            change_icon = "🔺" if data['change_dir'] == "▽" else "🔻"

            # 환율 정보 라인 생성
            rate_line = f"{data['emoji']} {data['country']} {data['currency']}\n · {data['rate']:.2f} ({change_icon}{data['change']:.2f} {data['change_rate']:.2f}%)"

            message_lines.append(rate_line)

    return "\n\n".join(message_lines)

async def handle_exchange_rate_command(prompt=None):
    """
    환율 조회 명령어 처리 함수
    
    Args:
        prompt (str, optional): 명령어 프롬프트
        
    Returns:
        str: 환율 정보 메시지
    """
    exchange_data = await fetch_exchange_rate_data()
    return format_exchange_rate_message(exchange_data)