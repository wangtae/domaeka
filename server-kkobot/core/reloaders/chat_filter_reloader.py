import asyncio
from core import globals as g
from core.logger import logger
from core.performance import Timer

async def reload_chat_filters(interval=60):
    url = "https://loa.best/projects/py/kakao-bot/message-filters.php"

    logger.info(f"[FILTER_RELOADER] 필터 재로더 시작 → 주기 {interval}초 / URL: {url}")

    while not g.shutdown_event.is_set():
        try:
            # ✅ 성능 측정
            with Timer("chat_filter_reload"):
                response = await g.http_client.get(url)
                response.raise_for_status()

                data = response.json()

                if isinstance(data, dict):
                    g.chat_filter_config = data
                    room_count = len(g.chat_filter_config.get('rooms', {}))
                    logger.info(f"[FILTER_RELOADER] 필터 로드 성공 → 룸 개수: {room_count}")
                else:
                    logger.warning(f"[FILTER_RELOADER] 필터 로드 실패 → JSON 형식 오류 / 수신 데이터: {data}")

        except Exception as e:
            logger.error(f"[FILTER_RELOADER] 필터 로드 오류 → {e}", exc_info=True)

        await asyncio.wait([g.shutdown_event.wait()], timeout=interval)

    logger.info("[FILTER_RELOADER] 필터 리로더 정상 종료됨")
