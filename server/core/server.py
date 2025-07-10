"""
TCP 서버 모듈
"""
import asyncio
import socket
import json
from typing import Dict, Any
from core.logger import logger
from core.client_handler import handle_client
from config.loader import load_config
import core.globals as g

CONFIG = load_config()


async def start_server(port: int):
    """
    TCP 서버를 시작하고 클라이언트 연결을 처리
    
    Args:
        port: 서버 포트
    """
    TCP_IP = CONFIG['SERVER']['server_ip']
    TCP_PORT = port

    try:
        logger.info(f"[SERVER INIT] TCP 서버 시작 시도 → {TCP_IP}:{TCP_PORT}")

        server = await asyncio.start_server(
            handle_client,
            TCP_IP,
            TCP_PORT
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

        # 서버가 serve_forever() 동안 실행됨
        async with server:
            await server.serve_forever()

    except Exception as e:
        logger.error(f"[SERVER ERROR] TCP 서버 실행 실패 → {e}", exc_info=True)
        raise