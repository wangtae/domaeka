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
- 서버 프로세스 관리 (kb_server_processes 테이블 기반)

사용법:
python main.py --name=domaeka-test-01  (권장)
python main.py --port=1490 --mode=test (기존 방식, 호환성 유지)
"""

import asyncio
import argparse
import signal
import os
from core.logger import logger
from core.server import start_server
from core.ping_scheduler_v2 import ping_scheduler
from database.connection import init_db_pool
from database.db_utils import create_tables, get_server_process_config, update_server_process_status, list_server_processes
import core.globals as g


async def shutdown():
    """서버 종료 처리"""
    logger.warning("[SHUTDOWN] 서버 종료 신호 수신")
    
    try:
        # 프로세스 상태 업데이트 (종료 중)
        if hasattr(g, 'process_name') and g.process_name:
            await update_server_process_status(g.process_name, 'stopping')
        
        # 종료 이벤트 설정
        g.shutdown_event.set()
        
        # ping 스케줄러 중지
        await ping_scheduler.stop()
        
        # 스케줄러 서비스 중지
        from services.scheduler_service import scheduler_service
        await scheduler_service.stop()
        
        # 프로세스 모니터 중지
        if hasattr(g, 'process_monitor') and g.process_monitor:
            await g.process_monitor.stop()
            logger.info("[SHUTDOWN] 프로세스 모니터 중지 완료")
        
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
                # 프로세스 상태 업데이트 (종료됨)
                if hasattr(g, 'process_name') and g.process_name:
                    await update_server_process_status(g.process_name, 'stopped')
                
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
        
        # 이벤트 루프 종료 강제 실행
        loop = asyncio.get_running_loop()
        loop.stop()
        
    except Exception as e:
        logger.error(f"[SHUTDOWN] 종료 처리 중 오류: {e}")
        # 강제 종료
        import os
        os._exit(0)


def signal_handler():
    """시그널 핸들러"""
    logger.info("[SIGNAL] 종료 신호 수신")
    # 종료 이벤트 설정
    g.shutdown_event.set()
    
    # 현재 실행 중인 태스크들을 즉시 취소
    loop = asyncio.get_running_loop()
    for task in asyncio.all_tasks(loop):
        if not task.done() and task != asyncio.current_task():
            task.cancel()
    
    # shutdown 태스크 생성
    loop.create_task(shutdown())


async def print_server_processes():
    """서버 프로세스 목록 출력"""
    try:
        # 데이터베이스 초기화
        db_pool = await init_db_pool()
        if not db_pool:
            print("❌ 데이터베이스 연결 실패")
            return
        
        # 서버 프로세스 목록 조회
        processes = await list_server_processes()
        
        if not processes:
            print("📋 등록된 서버 프로세스가 없습니다.")
            return
        
        print("📋 서버 프로세스 목록:")
        print("=" * 120)
        print(f"{'프로세스명':<20} {'타입':<8} {'포트':<6} {'상태':<10} {'PID':<8} {'마지막 하트비트':<20} {'생성일시':<20}")
        print("-" * 120)
        
        for process in processes:
            # 상태별 이모지
            status_emoji = {
                'running': '🟢',
                'stopped': '🔴',
                'starting': '🟡',
                'stopping': '🟠',
                'error': '❌',
                'crashed': '💥'
            }.get(process['status'], '⚪')
            
            # 날짜 포맷팅
            created_at = process['created_at'].strftime('%Y-%m-%d %H:%M:%S') if process['created_at'] else 'N/A'
            last_heartbeat = process['last_heartbeat'].strftime('%Y-%m-%d %H:%M:%S') if process['last_heartbeat'] else 'N/A'
            
            print(f"{process['process_name']:<20} {process['type']:<8} {process['port']:<6} "
                  f"{status_emoji} {process['status']:<8} {process['pid'] or 'N/A':<8} "
                  f"{last_heartbeat:<20} {created_at:<20}")
        
        print("-" * 120)
        print(f"총 {len(processes)}개의 서버 프로세스가 등록되어 있습니다.")
        
        # 데이터베이스 연결 종료
        db_pool.close()
        await db_pool.wait_closed()
        
    except Exception as e:
        print(f"❌ 서버 프로세스 목록 조회 실패: {e}")


async def main():
    """메인 함수"""
    # 명령줄 인자 파싱
    parser = argparse.ArgumentParser(description="kkobot.dev server-lite")
    
    # 새로운 방식: --name 옵션 (권장)
    parser.add_argument("--name", type=str, help="서버 프로세스 이름 (kb_server_processes 테이블에서 조회)")
    
    # 기존 방식: --port, --mode 옵션 (호환성 유지)
    parser.add_argument("--port", type=int, help="서버 포트 (기본값: 1490)")
    parser.add_argument("--mode", choices=["test", "prod"], help="실행 모드 (test/prod)")
    
    # 유틸리티 옵션
    parser.add_argument("--list", action="store_true", help="서버 프로세스 목록 출력")
    parser.add_argument("--test-db", action="store_true", help="데이터베이스 연결 테스트")
    
    args = parser.parse_args()
    
    # --list 옵션 처리
    if args.list:
        # 설정 파일 내용 출력
        from config.loader import load_config
        config = load_config()
        print("\n[현재 로드된 설정 파일 정보]")
        print("-" * 50)
        if 'DBs' in config:
            for db_name, db_config in config['DBs'].items():
                print(f"\n[{db_name} 데이터베이스 설정]")
                print(f"  HOST: {db_config.get('HOST', 'N/A')}")
                print(f"  PORT: {db_config.get('PORT', 'N/A')}")
                print(f"  USER: {db_config.get('USER', 'N/A')}")
                print(f"  NAME: {db_config.get('NAME', 'N/A')}")
                print(f"  PASS: {'*' * len(str(db_config.get('PASS', '')))}")
        print("-" * 50)
        
        await print_server_processes()
        return
    
    # --test-db 옵션 처리
    if args.test_db:
        # 설정 파일 내용 출력
        from config.loader import load_config
        config = load_config()
        print("\n[데이터베이스 연결 테스트]")
        print("-" * 50)
        
        # test와 live DB 모두 테스트
        for db_name in ['test', 'live']:
            if db_name not in config.get('DBs', {}):
                print(f"\n[{db_name}] 설정이 없습니다.")
                continue
                
            db_config = config['DBs'][db_name]
            print(f"\n[{db_name} 데이터베이스]")
            print(f"  HOST: {db_config.get('HOST')}")
            print(f"  PORT: {db_config.get('PORT')}")
            print(f"  USER: {db_config.get('USER')}")
            print(f"  NAME: {db_config.get('NAME')}")
            
            # 실제 연결 테스트
            try:
                g.DB_NAME = db_name
                import aiomysql
                import socket
                
                # DNS 해결 테스트
                host = db_config.get('HOST')
                print(f"  DNS 해결 시도: {host}")
                try:
                    ip = socket.gethostbyname(host)
                    print(f"  ✅ DNS 해결 성공: {host} -> {ip}")
                except socket.gaierror as e:
                    print(f"  ❌ DNS 해결 실패: {e}")
                    print(f"  💡 힌트: /etc/hosts 파일에 '{host}' 항목을 추가하거나 실제 IP 주소를 사용하세요")
                    continue
                
                print(f"  연결 시도 중...")
                conn = await aiomysql.connect(
                    host=db_config.get('HOST'),
                    port=db_config.get('PORT'),
                    user=db_config.get('USER'),
                    password=db_config.get('PASS'),
                    db=db_config.get('NAME'),
                    charset='utf8mb4'
                )
                
                # 간단한 쿼리 실행
                async with conn.cursor() as cursor:
                    await cursor.execute("SELECT VERSION()")
                    version = await cursor.fetchone()
                    print(f"  ✅ 연결 성공! MySQL 버전: {version[0]}")
                    
                    # kb_servers 테이블 확인
                    await cursor.execute("SHOW TABLES LIKE 'kb_servers'")
                    if await cursor.fetchone():
                        print(f"  ✅ kb_servers 테이블 존재 확인")
                    else:
                        print(f"  ❌ kb_servers 테이블이 없습니다")
                
                conn.close()
                
            except Exception as e:
                print(f"  ❌ 연결 실패: {e}")
        
        print("-" * 50)
        return
    
    # 서버 설정 결정
    if args.name:
        # 새로운 방식: kb_server_processes 테이블에서 설정 조회
        try:
            # 데이터베이스 초기화 (설정 조회를 위해 먼저 연결)
            db_pool = await init_db_pool()
            if not db_pool:
                logger.error("[STARTUP] 데이터베이스 연결 실패 - 서버 프로세스 설정을 조회할 수 없습니다")
                return
            
            # 서버 프로세스 설정 조회
            config = await get_server_process_config(args.name)
            
            # 전역 변수에 설정
            g.process_name = args.name
            port = config['port']
            mode = config['type']  # 'test' or 'live'
            
            logger.info(f"[STARTUP] 서버 프로세스 설정 로드: {args.name}")
            logger.info(f"[STARTUP] 포트: {port}, 모드: {mode}")
            
        except ValueError as e:
            logger.error(f"[STARTUP] {e}")
            logger.error(f"[STARTUP] 사용 가능한 서버 프로세스를 확인하세요")
            return
        except Exception as e:
            logger.error(f"[STARTUP] 서버 프로세스 설정 조회 실패: {e}")
            return
    
    elif args.port or args.mode:
        # 기존 방식: 명령줄 인자 사용 (호환성 유지)
        port = args.port or 1490
        mode = args.mode or "test"
        g.process_name = None
        
        logger.warning("[STARTUP] 기존 방식으로 실행 중 (--port, --mode)")
        logger.warning("[STARTUP] 권장: --name 옵션을 사용하세요")
        
        # 데이터베이스 초기화
        db_pool = await init_db_pool()
        if not db_pool:
            logger.warning("[STARTUP] 데이터베이스 연결 실패 - DB 없이 서버 실행")
    
    else:
        logger.error("[STARTUP] 서버 실행 옵션이 필요합니다")
        logger.error("[STARTUP] 사용법:")
        logger.error("[STARTUP]   python main.py --name=domaeka-test-01  (권장)")
        logger.error("[STARTUP]   python main.py --port=1490 --mode=test (기존 방식)")
        return
    
    # 데이터베이스 설정 키 설정 (설정 파일의 DBs.test/DBs.live와 일치)
    g.DB_NAME = "test" if mode == "test" else "live"
    
    logger.info(f"[STARTUP] Domaeka 카카오봇 서버 시작 - 버전: {g.VERSION}")
    logger.info(f"[STARTUP] 모드: {mode}, 포트: {port}")
    logger.info(f"[STARTUP] 데이터베이스: {g.DB_NAME}")
    if g.process_name:
        logger.info(f"[STARTUP] 프로세스: {g.process_name}")
    logger.info("[STARTUP] 지원 기능: Echo, 클라이언트 모니터링, HMAC 인증, 방 승인 시스템")
    
    # 시그널 핸들러 등록
    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(sig, signal_handler)
    
    try:
        # 프로세스 상태 업데이트 (시작 중)
        if g.process_name:
            await update_server_process_status(g.process_name, 'starting', os.getpid())
        
        # 테이블 생성/확인
        if g.db_pool:
            await create_tables()
            logger.info("[STARTUP] 데이터베이스 연결 및 테이블 확인 완료")
        
        # ping 스케줄러 시작
        await ping_scheduler.start()
        
        # 프로세스 자체 모니터링 시작
        if g.process_name:
            from core.process_self_monitor import ProcessSelfMonitor
            g.process_monitor = ProcessSelfMonitor(g.process_name, g.db_pool)
            await g.process_monitor.start()
            logger.info(f"[STARTUP] 프로세스 모니터링 시작: {g.process_name}")
        
        # 시스템 모니터링은 비활성화 (외부 도구 사용 권장)
        # from database.system_monitor import system_monitor_task
        # asyncio.create_task(system_monitor_task(interval=g.SYSTEM_MONITOR_INTERVAL))
        # logger.info(f"[STARTUP] 시스템 모니터링 태스크 시작 완료 (주기: {g.SYSTEM_MONITOR_INTERVAL}초)")
        
        # 메모리 관리자 시작
        from core.memory_manager import memory_manager
        await memory_manager.start()
        logger.info("[STARTUP] 메모리 관리자 시작 완료")
        
        # 스케줄러 서비스 시작
        from services.scheduler_service import scheduler_service
        scheduler_task = asyncio.create_task(scheduler_service.start())
        logger.info("[STARTUP] 스케줄러 서비스 시작 완료")
        
        # 프로세스 상태 업데이트 (실행 중)
        if g.process_name:
            await update_server_process_status(g.process_name, 'running', os.getpid())
        
        # TCP 서버 시작
        await start_server(port)
        
    except KeyboardInterrupt:
        logger.info("[MAIN] 키보드 인터럽트로 종료")
        await shutdown()
    except SystemExit:
        # 설정 파일 로드 실패나 DB 연결 실패 시 정상 종료
        logger.info("[MAIN] 시스템 종료")
    except Exception as e:
        logger.error(f"[MAIN] 서버 실행 오류: {e}")
        
        # 프로세스 상태 업데이트 (오류)
        if g.process_name:
            try:
                await update_server_process_status(g.process_name, 'error')
            except:
                pass
        
        await shutdown()
    finally:
        logger.info("[MAIN] 프로그램 종료")
        # 최종 강제 종료 보장
        os._exit(0)


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n[MAIN] 강제 종료")
    except Exception as e:
        print(f"[MAIN] 실행 오류: {e}")