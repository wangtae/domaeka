import asyncio
import logging
import json
from pathlib import Path
import core.globals as g

# ✅ 로거 설정
logger = logging.getLogger(__name__)
if not logger.handlers:
    logging.basicConfig(
        level=logging.INFO,
        format='[%(asctime)s][%(levelname)s][%(module)s:%(lineno)d] %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

async def reload_auto_replies(interval=600):
    """
    auto-replies.json에서 자동응답 데이터를 주기적으로 로드하여
    전역 g.auto_replies_data에 저장합니다.

    :param interval: 리로드 주기(초) 기본값 600초 (10분)
    """
    logger.info(f"[AUTO_REPLY RELOADER] 자동응답 데이터 주기적 로더 시작 (interval={interval}s)")

    try:
        while not g.shutdown_event.is_set():
            try:
                logger.info("[AUTO_REPLY RELOADER] 자동응답 데이터 로드 시도...")

                # globals.py에 정의된 JSON 파일 경로 사용
                auto_replies_path = g.JSON_CONFIG_FILES["auto_replies"]
                
                if auto_replies_path.exists():
                    with auto_replies_path.open("r", encoding="utf-8") as f:
                        data = json.load(f)

                    if isinstance(data, dict):
                        g.auto_replies_data = data
                        logger.info(f"[AUTO_REPLY RELOADER] 자동응답 데이터 로드 성공! ({len(data)}개 봇 데이터)")
                    else:
                        logger.warning(f"[AUTO_REPLY RELOADER] 데이터 포맷 오류! → {type(data)}")
                else:
                    logger.error(f"[AUTO_REPLY RELOADER] 파일을 찾을 수 없음: {auto_replies_path}")

            except Exception as e:
                logger.exception(f"[AUTO_REPLY RELOADER] 예외 발생 → {e}")

            # ✅ shutdown 감시 포함 sleep (추천 방식)
            logger.info(f"[AUTO_REPLY RELOADER] {interval}초 후 다음 로드 시도 대기 중...")
            try:
                await asyncio.wait_for(g.shutdown_event.wait(), timeout=interval)
            except asyncio.TimeoutError:
                pass

    except asyncio.CancelledError:
        logger.info("[AUTO_REPLY RELOADER] 태스크 취소 감지 → 종료 준비 완료")
        raise
