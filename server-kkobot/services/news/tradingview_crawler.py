import requests
from bs4 import BeautifulSoup
from datetime import datetime
import hashlib
import asyncio
import json
import pytz
import warnings
import re

from core import globals as g
from core.logger import logger
from services.investment_news_service import deliver_news_to_all_rooms

# MySQL 경고 필터링
warnings.filterwarnings('ignore', message='Duplicate entry .* for key')

TRADINGVIEW_NEWS = {
    "주식": "https://kr.tradingview.com/news/markets/stocks/",
    "ETFs": "https://kr.tradingview.com/news/markets/etfs/",
    "크립토": "https://kr.tradingview.com/news/markets/crypto/",
    "외환": "https://kr.tradingview.com/news/markets/forex/",
    "지수": "https://kr.tradingview.com/news/markets/indices/",
    "선물": "https://kr.tradingview.com/news/markets/futures/",
    "채권": "https://kr.tradingview.com/news/markets/bonds/",
    "경제": "https://kr.tradingview.com/news/markets/economy/",
    "글로벌뉴스": "https://kr.tradingview.com/news-flow/?market_country=entire_world&market=index,stock,etf,crypto,forex,futures,bond,economic&area=WLD,AME,EUR,ASI,OCN,AFR"
}

SOURCE = "TradingView"
KST = pytz.timezone("Asia/Seoul")  # ✅ 한국 시간대

def extract_news_id_from_url(url: str, category: str) -> str:
    """
    URL과 카테고리를 기반으로 고유 뉴스 ID 생성

    Args:
        url (str): 뉴스 URL
        category (str): 뉴스 카테고리

    Returns:
        str: 카테고리와 URL 기반의 고유 ID
    """
    # 카테고리를 포함하여 해시 생성
    hash_content = f"{category}-{url}"
    hash_id = hashlib.md5(hash_content.encode("utf-8")).hexdigest()[:12]
    return f"tv-{category}-{hash_id}"

def fetch_tradingview_news_html(url: str) -> str:
    headers = {"User-Agent": "Mozilla/5.0"}
    try:
        response = requests.get(url, headers=headers)
        response.raise_for_status()
        logger.debug(f"[HTML 수신 성공] {url} - {len(response.text)} bytes")
        return response.text
    except Exception as e:
        logger.error(f"[HTML 수신 실패] {url} - {str(e)}")
        return ""

def save_html_to_file(html, filename):
    """
    디버깅을 위해 HTML을 파일로 저장
    """
    try:
        with open(filename, 'w', encoding='utf-8') as f:
            f.write(html)
        logger.debug(f"[HTML 저장 성공] {filename}")
    except Exception as e:
        logger.error(f"[HTML 저장 실패] {filename} - {str(e)}")

def deep_find_items(data):
    if isinstance(data, dict):
        if "news" in data and isinstance(data["news"], dict):
            try:
                return data["news"]["data"]["items"]
            except KeyError:
                pass

        # 글로벌뉴스 페이지용 구조 확인
        if "feed" in data and isinstance(data["feed"], dict):
            try:
                return data["feed"]["data"]["items"]
            except KeyError:
                pass

        for value in data.values():
            found = deep_find_items(value)
            if found:
                return found
    elif isinstance(data, list):
        for item in data:
            found = deep_find_items(item)
            if found:
                return found
    return None

def parse_news_from_html(html: str, category: str) -> list[dict]:
    if not html:
        logger.warning(f"[HTML 없음] 카테고리: {category}")
        return []

    soup = BeautifulSoup(html, "html.parser")

    # 디버깅: 스크립트 태그 개수 확인
    script_tags = soup.find_all("script", {"type": "application/prs.init-data+json"})
    logger.debug(f"[카테고리 {category}] application/prs.init-data+json 스크립트 태그 개수: {len(script_tags)}")

    # 글로벌뉴스 페이지의 경우 다른 스크립트 태그 패턴을 사용할 수 있음
    if category == "글로벌뉴스" and len(script_tags) == 0:
        # 다른 스크립트 태그 패턴 시도
        script_tags = soup.find_all("script")
        for script_tag in script_tags:
            if script_tag.string and 'window.__INITIAL_STATE__' in script_tag.string:
                logger.info(f"[글로벌뉴스] __INITIAL_STATE__ 스크립트 태그 발견")
                try:
                    # __INITIAL_STATE__ = {...} 형식에서 JSON 추출
                    match = re.search(r'window\.__INITIAL_STATE__\s*=\s*(\{.*\});', script_tag.string, re.DOTALL)
                    if match:
                        raw_json = json.loads(match.group(1))
                        items = extract_global_news_items(raw_json)
                        if items:
                            news_list = []
                            for item in items:
                                # 글로벌뉴스 항목 구조에 맞게 파싱
                                story_path = item.get('link') or f"/news/{item.get('id', '')}"
                                url = f"https://kr.tradingview.com{story_path}"
                                news_id = extract_news_id_from_url(url, category)

                                news_list.append({
                                    "news_id": news_id,
                                    "title": item.get("title", ""),
                                    "url": url,
                                    "published": item.get("timestamp"),
                                    "category": category,
                                    "original_url": story_path
                                })
                            logger.info(f"[글로벌뉴스] {len(news_list)}개 뉴스 항목 파싱 성공")
                            return news_list
                except Exception as e:
                    logger.error(f"[글로벌뉴스 파싱 오류] {str(e)}")

    # 기존 카테고리용 파싱 로직
    for script_tag in script_tags:
        try:
            raw_json = json.loads(script_tag.string)
            items = deep_find_items(raw_json)
            if items:
                news_list = []
                for item in items:
                    url = f"https://kr.tradingview.com{item['storyPath']}"
                    news_id = extract_news_id_from_url(url, category)

                    news_list.append({
                        "news_id": news_id,
                        "title": item["title"],
                        "url": url,
                        "published": item.get("published"),
                        "category": category,
                        "original_url": item["storyPath"]
                    })
                logger.info(f"[카테고리 {category}] {len(news_list)}개 뉴스 항목 파싱 성공")
                return news_list
        except Exception as e:
            logger.debug(f"[JSON 파싱 실패] 카테고리: {category} - {e}")

    logger.warning(f"[뉴스 항목 없음] 카테고리: {category} - 적합한 뉴스 항목을 찾을 수 없음")
    return []

def extract_global_news_items(data):
    """
    글로벌뉴스 페이지의 특수 구조에서 뉴스 항목 추출
    """
    if isinstance(data, dict):
        # 가능한 경로들 시도
        if "newsFlow" in data:
            newsflow = data["newsFlow"]
            if "stories" in newsflow and isinstance(newsflow["stories"], list):
                return newsflow["stories"]
            if "data" in newsflow and "stories" in newsflow["data"]:
                return newsflow["data"]["stories"]

        # feed 구조 확인
        if "feed" in data:
            feed = data["feed"]
            if "items" in feed:
                return feed["items"]
            if "data" in feed and "items" in feed["data"]:
                return feed["data"]["items"]

        # 재귀적으로 모든 딕셔너리 검색
        for key, value in data.items():
            if isinstance(value, (dict, list)):
                result = extract_global_news_items(value)
                if result:
                    return result

    elif isinstance(data, list):
        # 리스트 내 첫 번째 항목에 title, link 등이 있는지 확인
        if len(data) > 0 and isinstance(data[0], dict):
            if "title" in data[0] and ("link" in data[0] or "id" in data[0]):
                return data

        # 재귀적으로 모든 리스트 항목 검색
        for item in data:
            if isinstance(item, (dict, list)):
                result = extract_global_news_items(item)
                if result:
                    return result

    return None

async def save_news_to_db(news_list):
    pool = g.db_pool
    if pool is None:
        logger.error("[DB 오류] DB 풀이 초기화되지 않았습니다.")
        return

    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            new_count = 0
            skipped_count = 0

            # 경고 메시지 없는 쿼리 실행을 위한 설정
            await cur.execute("SET SESSION sql_notes = 0")

            for news in news_list:
                # 1. 원본 URL로 중복 확인
                if news.get("original_url"):
                    await cur.execute("""
                        SELECT news_id, category FROM kb_market_news 
                        WHERE original_url = %s LIMIT 1
                    """, (news.get("original_url"),))
                    existing_news = await cur.fetchone()

                    if existing_news:
                        logger.debug(f"[URL 중복] 건너뜀 → {news['title']} (기존: {existing_news[0]}, 카테고리: {existing_news[1]} / 신규 카테고리: {news.get('category')})")
                        skipped_count += 1
                        continue

                # 2. 같은 카테고리 내에서 제목으로 중복 확인 (추가된 부분)
                if news.get("title") and news.get("category"):
                    await cur.execute("""
                        SELECT news_id FROM kb_market_news 
                        WHERE title = %s AND category = %s LIMIT 1
                    """, (news.get("title"), news.get("category")))
                    existing_by_title = await cur.fetchone()

                    if existing_by_title:
                        logger.debug(f"[제목 중복] 건너뜀 → {news['title']} (기존 ID: {existing_by_title[0]}, 카테고리: {news.get('category')})")
                        skipped_count += 1
                        continue

                # ✅ published → 한국시간
                if news.get("published"):
                    dt_obj = datetime.fromtimestamp(news["published"], pytz.utc).astimezone(KST)
                else:
                    dt_obj = datetime.now(KST)

                created_at = dt_obj.strftime("%Y-%m-%d %H:%M:%S")
                now_kst = datetime.now(KST).strftime("%Y-%m-%d %H:%M:%S")

                try:
                    # 현재 뉴스 로깅 (추가 디버깅)
                    logger.debug(f"[저장 시도] ID: {news['news_id']}, 카테고리: {news.get('category')}, 제목: {news['title'][:30]}...")

                    await cur.execute("""
                        INSERT IGNORE INTO kb_market_news 
                        (news_id, title, summary, url, original_url, timestamp, source, category, created_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """, (
                        news['news_id'],
                        news['title'],
                        news.get('summary', ''),
                        news['url'],
                        news.get('original_url', ''),
                        now_kst,
                        SOURCE,
                        news.get("category", ""),
                        created_at
                    ))

                    if cur.rowcount > 0:
                        new_count += 1
                        logger.info(f"[신규 뉴스] 저장됨 → {news['title']} ({news['news_id']})")
                    else:
                        logger.debug(f"[저장 실패] 영향 받은 행 없음 → {news['title']} ({news['news_id']})")

                except Exception as e:
                    # 중복 오류 무시, 다른 오류는 로깅
                    if "Duplicate entry" not in str(e):
                        logger.error(f"[DB 오류] 뉴스 저장 실패: {e}")
                    else:
                        logger.debug(f"[중복 키] {news['news_id']} - {news['title']}")

            # 원래 설정으로 복원
            await cur.execute("SET SESSION sql_notes = 1")

            await conn.commit()
            logger.info(f"[{datetime.now(KST)}] 총 {len(news_list)}건 중 {new_count}건 저장됨, {skipped_count}건 중복 건너뜀")

async def parse_and_save_news_from_requests():
    try:
        all_news = []
        for category, url in TRADINGVIEW_NEWS.items():
            try:
                logger.info(f"[카테고리 수집 시작] {category} - {url}")
                html = fetch_tradingview_news_html(url)

                # 디버깅: 글로벌뉴스 HTML 저장
                #if category == "글로벌뉴스" and html:
                #    save_html_to_file(html, f"debug_global_news_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html")

                news_items = parse_news_from_html(html, category)
                if news_items:
                    all_news.extend(news_items)
                    logger.info(f"[카테고리 수집 완료] {category}: {len(news_items)}건")
                else:
                    logger.warning(f"[카테고리 수집 실패] {category}: 뉴스 항목 없음")
            except Exception as e:
                logger.error(f"[카테고리 오류] {category} 뉴스 수집 실패: {str(e)}")
                continue

        if not all_news:
            logger.warning("[뉴스 없음] 저장할 뉴스 없음")
            return

        await save_news_to_db(all_news)
        await deliver_news_to_all_rooms()
    except Exception as e:
        logger.exception(f"[에러] TradingView 뉴스 수집 실패: {e}")

async def tradingview_news_scheduler():
    logger.info("[뉴스 스케줄러 시작] TradingView 뉴스 수집 시작됨")
    while True:
        try:
            await parse_and_save_news_from_requests()
        except Exception as e:
            logger.exception(f"[스케줄러 오류] 예외 발생: {e}")
        await asyncio.sleep(60 * g.NEWS_DELIVERY_INTERVAL_MINUTES)