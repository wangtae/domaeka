import asyncio
import socket
from core.client_handler import handle_client
from config.loader import load_config
from core.logger import logger
from core.performance import measure_performance

CONFIG = load_config()

@measure_performance
async def start_server(port: int):
    """
    TCP 서버를 시작하고 클라이언트 연결을 처리합니다.
    """
    TCP_IP = CONFIG['SERVER']['server_ip']
    TCP_PORT = port # 파라미터로 받은 포트 사용

    # ✅ 자동응답 구조 초기화 (중요)
    from core.globals import load_auto_replies
    await load_auto_replies()

    try:
        logger.info(f"[SERVER INIT] TCP 서버 시작 시도 → {TCP_IP}:{TCP_PORT}")

        server = await asyncio.start_server(
            handle_client,
            TCP_IP,
            TCP_PORT
        )

        # ✅ TCP_NODELAY 활성화
        for sock in server.sockets:
            try:
                sock.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
                logger.info(f"[SOCKET CONFIG] TCP_NODELAY 활성화 완료 → {sock.getsockname()}")
            except Exception as e:
                logger.warning(f"[SOCKET CONFIG] TCP_NODELAY 설정 실패 → {e}", exc_info=True)

        addr = server.sockets[0].getsockname()
        logger.info(f"[SERVER STARTED] TCP 서버 실행 중 → {addr} (TCP_NODELAY 적용됨)")

        # 서버가 serve_forever() 동안 실행됨
        async with server:
            await server.serve_forever()

    except Exception as e:
        logger.error(f"[SERVER ERROR] TCP 서버 실행 실패 → {e}", exc_info=True)
        # 필요시 서버 재시작이나 알림 로직 추가 가능
