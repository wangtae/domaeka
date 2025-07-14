"""
TCP 서버 모듈
"""
import asyncio
import socket
import json
from typing import Dict, Any
from core.logger import logger
from core.client_handler import handle_client
from core.worker import start_workers, stop_workers
import core.globals as g


async def start_server(port: int):
    """
    TCP 서버를 시작하고 클라이언트 연결을 처리
    
    Args:
        port: 서버 포트
    """
    TCP_IP = "0.0.0.0"  # 고정값
    TCP_PORT = port

    try:
        logger.info(f"[SERVER INIT] TCP 서버 시작 시도 → {TCP_IP}:{TCP_PORT}")
        
        # 전체 연결 수 제한 세마포어 초기화
        g.connection_semaphore = asyncio.Semaphore(g.MAX_CONCURRENT_CONNECTIONS)
        logger.info(f"[SERVER INIT] 최대 동시 연결 수 제한: {g.MAX_CONCURRENT_CONNECTIONS}")
        
        # 워커 풀 시작
        await start_workers()

        # StreamReader의 기본 limit 설정 (메시지 크기 제한과 동일하게)
        server = await asyncio.start_server(
            handle_client,
            TCP_IP,
            TCP_PORT,
            limit=g.MAX_MESSAGE_SIZE  # StreamReader 버퍼 크기 제한
        )

        # TCP_NODELAY 활성화
        for sock in server.sockets:
            try:
                sock.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
                logger.info(f"[SOCKET CONFIG] TCP_NODELAY 활성화 완료 → {sock.getsockname()}")
            except Exception as e:
                logger.warning(f"[SOCKET CONFIG] TCP_NODELAY 설정 실패 → {e}")

        addr = server.sockets[0].getsockname()
        logger.info(f"[SERVER STARTED] TCP 서버 실행 중 → {addr}")

        # 전역 변수에 저장
        g.server = server

        # 서버가 종료 이벤트까지 실행됨
        async with server:
            # 종료 이벤트를 기다림
            await g.shutdown_event.wait()
            logger.info("[SERVER] 종료 이벤트 수신, 서버 종료 중...")
            
            # 워커 풀 종료
            await stop_workers()

    except Exception as e:
        logger.error(f"[SERVER ERROR] TCP 서버 실행 실패 → {e}", exc_info=True)
        raise