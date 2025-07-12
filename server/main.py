#!/usr/bin/env python3
"""
kkobot.dev server-lite
MessengerBotR 클라이언트와 통신하는 경량 카카오톡 봇 서버

기능:
- TCP 서버로 클라이언트 연결 처리
- 데이터베이스 연결 (원본 server와 동일한 방식)
- '# echo {내용}' 명령어 처리
- '# client_info summary' 클라이언트 상태 조회
- 강화된 인증 시스템 (HMAC 서명 검증)
- 클라이언트 모니터링 정보 처리
- 채팅 로그 저장

사용법:
python main.py --port=1490 --mode=test
"""

import asyncio
import argparse
import signal
from core.logger import logger
from core.server import start_server
from database.connection import init_db_pool
from database.db_utils import create_tables
import core.globals as g


async def shutdown():
    """서버 종료 처리"""
    logger.warning("[SHUTDOWN] 서버 종료 신호 수신")
    
    # 종료 이벤트 설정
    g.shutdown_event.set()
    
    # 모든 클라이언트 연결 종료
    for addr, writer in list(g.clients.items()):
        try:
            writer.close()
            await writer.wait_closed()
            logger.info(f"[SHUTDOWN] 클라이언트 연결 종료: {addr}")
        except Exception as e:
            logger.error(f"[SHUTDOWN] 클라이언트 연결 종료 실패 {addr}: {e}")
    
    # 데이터베이스 연결 종료
    if g.db_pool:
        try:
            g.db_pool.close()
            await g.db_pool.wait_closed()
            logger.info("[SHUTDOWN] 데이터베이스 연결 종료 완료")
        except Exception as e:
            logger.error(f"[SHUTDOWN] 데이터베이스 종료 오류: {e}")
    
    # 서버 종료
    if g.server:
        try:
            g.server.close()
            await g.server.wait_closed()
            logger.info("[SHUTDOWN] TCP 서버 종료 완료")
        except Exception as e:
            logger.error(f"[SHUTDOWN] 서버 종료 오류: {e}")
    
    logger.info("[SHUTDOWN] 서버 종료 완료")


def signal_handler():
    """시그널 핸들러"""
    logger.info("[SIGNAL] 종료 신호 수신")
    asyncio.create_task(shutdown())


async def main():
    """메인 함수"""
    # 명령줄 인자 파싱
    parser = argparse.ArgumentParser(description="kkobot.dev server-lite")
    parser.add_argument("--port", type=int, default=1490, help="서버 포트 (기본값: 1490)")
    parser.add_argument("--mode", default="test", choices=["test", "prod"], 
                       help="실행 모드 (test/prod, 기본값: test)")
    args = parser.parse_args()
    
    # 데이터베이스 이름 설정
    g.DB_NAME = "kkobot_test" if args.mode == "test" else "kkobot_prod"
    
    logger.info(f"[STARTUP] Domaeka 카카오봇 서버 시작 - 버전: {g.VERSION}")
    logger.info(f"[STARTUP] 모드: {args.mode}, 포트: {args.port}")
    logger.info(f"[STARTUP] 데이터베이스: {g.DB_NAME}")
    logger.info("[STARTUP] 지원 기능: Echo, 클라이언트 모니터링, HMAC 인증")
    
    # 시그널 핸들러 등록
    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(sig, signal_handler)
    
    try:
        # 데이터베이스 초기화 (원본 server와 동일한 방식)
        db_pool = await init_db_pool()
        if db_pool:
            logger.info("[STARTUP] 데이터베이스 연결 성공")
            # 테이블 생성/확인
            await create_tables()
        else:
            logger.error("[STARTUP] 데이터베이스 연결 실패 - 프로그램을 종료합니다.")
            raise SystemExit("데이터베이스 연결 실패")
        
        # TCP 서버 시작
        await start_server(args.port)
        
    except KeyboardInterrupt:
        logger.info("[MAIN] 키보드 인터럽트로 종료")
    except SystemExit:
        # 설정 파일 로드 실패나 DB 연결 실패 시 정상 종료
        pass
    except Exception as e:
        logger.error(f"[MAIN] 서버 실행 오류: {e}")
        await shutdown()
    finally:
        logger.info("[MAIN] 프로그램 종료")


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n[MAIN] 강제 종료")
    except Exception as e:
        print(f"[MAIN] 실행 오류: {e}")