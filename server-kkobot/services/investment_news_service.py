import asyncio
import json
from datetime import datetime, timedelta
from core import globals as g
from core.logger import logger
from zoneinfo import ZoneInfo
from core.utils.send_message import send_message, send_message_response
import aiomysql
from core.utils.yourls_client import shorten_url_with_yourls

KST = ZoneInfo("Asia/Seoul")

def humanize_time_diff(created_at: datetime):
    """
    주어진 시간과 현재 시간의 차이를 사람이 읽기 쉬운 형식으로 변환
    """
    try:
        if created_at.tzinfo is None:
            created_at = created_at.replace(tzinfo=KST)

        now = datetime.now(KST)
        delta = now - created_at

        days = delta.days
        hours, remainder = divmod(delta.seconds, 3600)
        minutes = remainder // 60

        if days >= 1:
            return f"{days}일 {hours}시간 전"
        elif hours >= 1:
            return f"{hours}시간 {minutes}분 전"
        else:
            return f"{minutes}분 전"
    except Exception:
        return "시간 정보 없음"

async def get_undelivered_news(source=None, categories=None, limit=30):
    """
    최근 NEWS_DELIVERY_LOOKBACK_MINUTES 분 내의 미발송 뉴스 조회
    """
    async with g.db_pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            # globals.py에서 설정값 가져오기
            lookback_minutes = getattr(g, 'NEWS_DELIVERY_LOOKBACK_MINUTES', 180)  # 기본값 3시간(180분)

            # 설정된 시간만큼 이전 시점 계산
            lookback_time = datetime.now(KST) - timedelta(minutes=lookback_minutes)
            lookback_time_str = lookback_time.strftime("%Y-%m-%d %H:%M:%S")

            logger.info(f"[뉴스 조회] {lookback_minutes}분({lookback_minutes/60:.1f}시간) 이내 미발송 뉴스 조회")

            query = """
                SELECT *, CONVERT_TZ(created_at, '+00:00', '+09:00') AS created_at
                FROM kb_market_news
                WHERE delivered_rooms = 0
                AND created_at >= %s
            """
            params = [lookback_time_str]

            if source:
                query += " AND source = %s"
                params.append(source)

            if categories and len(categories) > 0:
                placeholders = ', '.join(['%s'] * len(categories))
                query += f" AND category IN ({placeholders})"
                params.extend(categories)

            query += " ORDER BY created_at DESC LIMIT %s"
            params.append(limit)

            await cur.execute(query, params)
            results = await cur.fetchall()

            # 결과 로깅
            logger.info(f"[뉴스 조회] 미발송 뉴스 {len(results)}건 조회됨 (source={source}, categories={categories})")

            return results

def remove_duplicate_news(news_list):
    """
    ✅ 개선: 제목이나 URL이 동일한 뉴스 중복 제거

    Args:
        news_list (list): 원본 뉴스 리스트

    Returns:
        list: 중복이 제거된 뉴스 리스트
    """
    if not news_list:
        return []

    unique_news = []
    seen_titles = set()
    seen_urls = set()

    for news in news_list:
        title = news['title'].strip()
        url = news.get('original_url', '') or news['url']

        # 제목과 URL 모두 확인하여 중복 체크
        if title not in seen_titles and url not in seen_urls:
            seen_titles.add(title)
            seen_urls.add(url)
            unique_news.append(news)
        else:
            logger.debug(f"[뉴스 중복 제거] 제외됨 → {title}")

    logger.info(f"[뉴스 중복 제거] 총 {len(news_list)}건 중 {len(unique_news)}건 남김 (중복 {len(news_list) - len(unique_news)}건 제거)")
    return unique_news

def build_news_message(news_list: list[dict], source=None) -> str:
    """
    뉴스 리스트를 포맷팅된 메시지로 변환 - 시간 순서로 정렬
    """
    if not news_list:
        return ""

    # 중복 제거
    unique_news_list = remove_duplicate_news(news_list)

    if not unique_news_list:
        return ""

    # 시간 순서로 정렬 (최신순)
    sorted_news = sorted(unique_news_list, key=lambda x: x['created_at'], reverse=True)

    source_name = source or "TradingView"
    lines = [f"📰 오늘의 뉴스 - {source_name}\n"]

    for i, news in enumerate(sorted_news, start=1):
        title = news['title'].strip()
        url = news['url'].strip()
        short_url = shorten_url_with_yourls(url).replace("https://", "")
        created_at = news['created_at']
        time_diff = humanize_time_diff(created_at)

        # 카테고리 정보 추가
        category = news.get('category', '')
        category_display = f"[{category}]" if category else ""

        # 수정된 형식: 제목(시간) 줄바꿈 카테고리 URL
        lines.append(f"{i}. {title} ({time_diff})\n{category_display} {short_url}\n")

    lines.append(f"총 {len(sorted_news)}건의 뉴스를 전달해드렸습니다.")
    lines.append("\u200b" * 500 + f"\n⚠️ 이 정보는 {source_name} 뉴스 페이지에서 제공된 내용을 기반으로 구성되었습니다.")

    return "\n".join(lines)

async def mark_news_as_delivered(news_ids):
    """
    발송된 뉴스의 상태를 업데이트
    """
    if not news_ids:
        logger.info("[뉴스 발송] 발송 성공한 뉴스가 없어 상태 업데이트 생략")
        return

    async with g.db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            placeholders = ', '.join(['%s'] * len(news_ids))
            query = f"""
                UPDATE kb_market_news
                SET delivered_rooms = 1
                WHERE news_id IN ({placeholders})
            """
            await cur.execute(query, news_ids)
            rows_affected = cur.rowcount
            await conn.commit()
            logger.info(f"[뉴스 발송] {rows_affected}개 뉴스 delivered_rooms = 1 처리 완료")

async def deliver_news_to_all_rooms():

    """
    미발송된 뉴스를 모든 해당 채널에 전송하는 함수
    """
    # 이 부분을 추가하세요 - 함수 시작 부분
    if g.NEWS_DELIVERY_INTERVAL_MINUTES <= 0:
        logger.info("[뉴스 발송 건너뛰기] NEWS_DELIVERY_INTERVAL_MINUTES가 0 이하로 설정되어 뉴스 발송을 건너뜁니다.")
        return

    # 모든 등록된 클라이언트 확인
    while not g.clients:
        logger.info("[뉴스 발송] 클라이언트 연결 대기 중...")
        await asyncio.sleep(5)  # 5초마다 확인

    logger.info(f"[뉴스 발송] 클라이언트 연결 확인 → {list(g.clients.keys())}")

    # 기존 뉴스 발송 로직 (그대로 유지)
    delivered_news_ids_by_room = {}
    all_delivered_news_ids = set()

    for bot_name, channels in g.schedule_rooms.items():
        # 해당 봇의 클라이언트가 연결되어 있는지 확인
        if bot_name not in g.clients:
            logger.info(f"[뉴스 발송] 클라이언트 미연결 → {bot_name}")
            continue

        # 클라이언트 세션 확인 및 로깅
        client_sessions = g.clients.get(bot_name, {})
        if not client_sessions:
            logger.warning(f"[뉴스 발송] 클라이언트 세션 없음 → {bot_name}")
            continue

        logger.info(f"[뉴스 발송] 클라이언트 세션 확인 → {bot_name}: {list(client_sessions.keys())}")

        for channel_id_raw, conf in channels.items():
            channel_id = str(channel_id_raw)
            news_config = conf.get("enable_investment_news", False)
            room_name = conf.get("room_name", "알 수 없는 방")  # room_name 변수 정의

            # 뉴스 수신 설정 확인
            if news_config is False:
                logger.info(f"[뉴스 발송] 뉴스 수신 기능 비활성화 → {bot_name} / {room_name} ({channel_id})")
                continue

            if isinstance(news_config, list) and len(news_config) == 0:
                logger.info(f"[뉴스 발송] 구독 뉴스 카테고리 없음 → {bot_name} / {room_name} ({channel_id})")
                continue

            message = None
            news_list = []

            if news_config is True:
                news_list = await get_undelivered_news()
                if news_list:
                    message = build_news_message(news_list)
            elif isinstance(news_config, dict):
                for source, categories in news_config.items():
                    if source == "TradingView":
                        # ✅ 개선: 카테고리별 뉴스 수집 후 중복 제거
                        all_category_news = []
                        for category in categories:
                            category_news = await get_undelivered_news(source=source, categories=[category])
                            all_category_news.extend(category_news)

                        if all_category_news:
                            # 여러 카테고리에서 가져온 뉴스를 build_news_message 함수가 중복 제거
                            message = build_news_message(all_category_news, source)
                            news_list = all_category_news
                            break
                    else:
                        logger.warning(f"[뉴스 발송] 지원하지 않는 뉴스 소스 → {source}")

            # 메시지 검증
            if not message:
                logger.info(f"[뉴스 발송] 발송 대상 메시지 없음 → {bot_name} / {room_name} ({channel_id})")
                continue

            # 뉴스 ID 추출
            news_ids = [news['news_id'] for news in news_list]

            # 뉴스 발송
            try:
                # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
                context = {
                    'bot_name': bot_name,
                    'channel_id': channel_id,
                    'room': room_name
                }
                await send_message_response(context, message)
                logger.info(f"[뉴스 발송] 성공 → {bot_name} / {room_name}, {len(news_ids)}건")

                # 발송된 뉴스 ID 기록
                delivered_news_ids_by_room[f"{bot_name}/{channel_id}"] = news_ids
                all_delivered_news_ids.update(news_ids)

            except Exception as e:
                logger.error(f"[뉴스 발송] 실패 → {bot_name} / {room_name}: {e}")

    # 발송 결과 요약
    delivered_room_count = len(delivered_news_ids_by_room)
    logger.info(f"[뉴스 발송] 총 {delivered_room_count}개 방에 뉴스 전달 완료")

    # 최소 하나 이상의 방에 발송 성공한 뉴스만 delivered_rooms = 1로 업데이트
    if all_delivered_news_ids:
        await mark_news_as_delivered(list(all_delivered_news_ids))
    else:
        logger.warning("[뉴스 발송] 발송할 뉴스가 없어 상태 업데이트를 건너뜁니다.")