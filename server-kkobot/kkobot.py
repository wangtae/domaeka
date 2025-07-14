import core.globals as g
from database.connection import init_db_pool
import asyncio
import signal
import time
import psutil  # 시스템 모니터링 추가
from core.server import start_server
from core.scheduler import fetch_schedule_data, scheduled_sender
from core import globals as g  # ✅ globals import 추가
from core.reloaders.chat_filter_reloader import reload_chat_filters
from core.logger import logger
from core.performance import measure_performance
from services.news.tradingview_crawler import tradingview_news_scheduler
from core.sessions.session_scheduler import session_scheduler_task
from core.sessions.session_manager import session_cleanup_task
from core.client_handler import start_message_workers  # [추가] 메시지 워커 임포트
from services.config_generator_service import generate_schedule_rooms_from_db_json, generate_bot_settings_from_db # 새로 추가
import argparse # 추가
from core.command_manager import command_manager

shutdown_event = asyncio.Event()  # ✅ 종료 이벤트 객체 추가


# ✅ 시스템 상태 모니터링 (DB 저장 포함)
async def system_monitor(interval=300):
    """시스템 모니터링 + DB 저장"""
    from core.utils.system_monitor import save_system_status
    
    while not shutdown_event.is_set():
        try:
            # 시스템 상태 DB 저장
            await save_system_status()
            
            # 로그 출력 (디버그용)
            cpu = psutil.cpu_percent()
            memory = psutil.virtual_memory().percent
            logger.debug(f"[SYSTEM] CPU 사용률: {cpu}% / 메모리 사용률: {memory}%\n\n")
            
        except Exception as e:
            logger.error(f"[SYSTEM_MONITOR] 모니터링 오류: {e}")
            
        await asyncio.sleep(interval)


# ✅ 에러 알림 큐 처리 태스크
async def notification_queue_processor():
    """에러 알림 큐 처리 태스크"""
    while not shutdown_event.is_set():
        try:
            from core.error_notifier import process_pending_notifications
            await process_pending_notifications()
        except Exception as e:
            logger.error(f"[ERROR_NOTIFIER] 알림 큐 처리 오류: {e}")
        await asyncio.sleep(30)  # 30초마다 확인


# ✅ 종료 처리
async def shutdown():
    logger.warning("[SHUTDOWN] 서버 종료 감지, 모든 작업 중지 중...")

    shutdown_event.set()

    tasks = [task for task in asyncio.all_tasks() if task is not asyncio.current_task()]
    logger.info(f"[SHUTDOWN] {len(tasks)}개의 태스크 취소 시도 중...")

    for task in tasks:
        task.cancel()

    await asyncio.gather(*tasks, return_exceptions=True)

    # ✅ DB 커넥션 풀 정리
    if g.db_pool:
        logger.info("[SHUTDOWN] DB 풀 닫는 중...")
        g.db_pool.close()
        await g.db_pool.wait_closed()
        logger.info("[SHUTDOWN] DB 풀 닫힘 완료!")

    # ✅ HTTP 클라이언트 정리
    if g.http_client:
        await g.http_client.aclose()
        logger.info("[SHUTDOWN] HTTP 클라이언트 종료 완료")

    logger.info("[SHUTDOWN] 모든 리소스 정리 완료!")


@measure_performance  # ✅ 실행 시간 로깅
async def main():
    # ✅ 명령줄 인자 파싱
    parser = argparse.ArgumentParser(description="KakaoBot 서버 실행 옵션.")
    parser.add_argument("--port", type=int, default=37888, help="서버가 사용할 포트 번호.")
    parser.add_argument("--mode", type=str, default="test", choices=["live", "test"], 
                       help="실행 모드 (live: kkobot_live, test: kkobot_test). 기본값: test")
    args = parser.parse_args()
    
    # ✅ 실행 모드에 따른 데이터베이스 이름 설정
    if args.mode == "live":
        g.DB_NAME = "kkobot_live"
    else:
        g.DB_NAME = "kkobot_test"
    
    logger.info(f"\n🚀 서버를 시작합니다 - {g.VERSION} (포트: {args.port}, 모드: {args.mode}, DB: {g.DB_NAME})\n") # 로깅 메시지 수정
    start_time = time.time()

    # ✅ 필터 리로드 태스크 시작
    asyncio.create_task(reload_chat_filters(interval=60))
    logger.info("[STARTUP] 필터 리로더 태스크 시작 완료")

    # ✅ DB 풀 초기화
    g.db_pool = await init_db_pool()

    if g.db_pool:
        logger.info("[DB] DB 풀 초기화 성공")
        # await generate_schedule_rooms_from_db_json() # 새로 추가: DB에서 config JSON 생성
        # logger.info("[INIT] schedule-rooms-from-db.json 생성 완료. \"config/envs/schedule-rooms-from-db.json\" 파인.")
        # await generate_bot_settings_from_db() # 새로 추가: DB에서 봇 설정 JSON 생성
        # logger.info("[INIT] 봇 설정 파일 생성 완료.")
    else:
        logger.error("[DB ERROR] DB 풀 초기화 실패 → 서버 종료")
        await shutdown()
        return

    # 에러 알림 초기화 시점 조정을 위한 플래그 설정
    g.error_notifier_initialized = False

    # 서버 시작 시 알림 초기화는 일단 보류 (첫 메시지 수신 후 초기화하도록 변경)
    # 대신 로그만 남김
    logger.info("[ERROR_NOTIFIER] 에러 알림 시스템 초기화 준비 완료 (첫 메시지 수신 후 활성화)")

    # 캐시 테이블 초기화 추가
    from core.utils.cache_service import init_cache_table
    await init_cache_table()

    # 동적 명령어 시스템 초기화
    # from core.globals import init_command_system
    await command_manager.load_all_bot_commands()
    logger.info("[STARTUP] 동적 명령어 시스템 초기화 완료")

    # [추가] 메시지 워커 실행
    start_message_workers()

    loop = asyncio.get_running_loop()

    # ✅ 종료 신호 핸들링 등록
    for sig in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(sig, lambda: asyncio.create_task(shutdown()))

    # ✅ 스케줄러 및 서버 실행
    asyncio.create_task(fetch_schedule_data())
    asyncio.create_task(scheduled_sender())

    # ✅ 시스템 모니터링 (CPU/메모리 + DB 저장)
    asyncio.create_task(system_monitor(interval=g.SYSTEM_MONITOR_INTERVAL))

    # ✅ 에러 알림 큐 처리 태스크 추가
    asyncio.create_task(notification_queue_processor())
    logger.info("[STARTUP] 에러 알림 큐 처리 태스크 시작 완료")

    # ✅ 뉴스 크롤링
    #if g.NEWS_DELIVERY_INTERVAL_MINUTES > 0:
    #    asyncio.create_task(tradingview_news_scheduler())

    # ✅ 대화 참여 모니터링 태스크 추가
    from core.conversation_joiner import conversation_join_monitor
    asyncio.create_task(conversation_join_monitor())
    logger.info("[STARTUP] 대화 참여 모니터링 태스크 시작 완료")

    # 세션 관련 태스크 추가
    asyncio.create_task(session_scheduler_task())
    asyncio.create_task(session_cleanup_task())

    # ✅ 타임아웃 매니저 실행
    from core.timeout_manager import timeout_manager
    asyncio.create_task(timeout_manager.run())

    # ✅ 서버 실행 (포트 인자 전달)
    await start_server(args.port)

    elapsed = round(time.time() - start_time, 2)
    logger.info(f"[STARTUP] 서버 전체 초기화 완료 → {elapsed}초 소요")

    # ✅ shutdown_event 감지 후 종료 절차 시작
    await shutdown_event.wait()

    logger.info("[SHUTDOWN] 서버 종료 완료")


if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.warning("[강제 종료] 서버 종료 완료")
