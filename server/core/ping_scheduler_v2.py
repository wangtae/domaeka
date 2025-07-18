"""
클라이언트별 ping 타이머 관리 모듈
각 클라이언트마다 독립적인 ping 타이머를 관리하여 연결 상태를 모니터링
"""
import asyncio
import time
import random
from typing import Dict, Any, Optional
from core.logger import logger
from core.response_utils import send_json_response
import core.globals as g


class ClientPingScheduler:
    """클라이언트별 독립적인 ping 스케줄러"""
    
    def __init__(self):
        """ping 스케줄러 초기화"""
        self.ping_tasks: Dict[tuple, asyncio.Task] = {}  # {(bot_name, device_id): task}
        self.client_last_ping: Dict[tuple, float] = {}  # 마지막 ping 시간 추적
        
    async def start(self):
        """ping 스케줄러 시작"""
        logger.info(f"[PING_SCHEDULER] 클라이언트별 ping 스케줄러 시작 (주기: {g.PING_INTERVAL_SECONDS}초)")
        
    async def stop(self):
        """ping 스케줄러 중지"""
        logger.info("[PING_SCHEDULER] ping 스케줄러 중지 시작")
        
        # 모든 ping 태스크 취소
        for client_key, task in list(self.ping_tasks.items()):
            if not task.done():
                task.cancel()
                logger.debug(f"[PING_SCHEDULER] ping 태스크 취소: {client_key}")
                
        # 모든 태스크 완료 대기
        if self.ping_tasks:
            await asyncio.gather(*self.ping_tasks.values(), return_exceptions=True)
            
        self.ping_tasks.clear()
        self.client_last_ping.clear()
        logger.info("[PING_SCHEDULER] ping 스케줄러 중지 완료")
        
    async def add_client(self, bot_name: str, device_id: str, writer: Any):
        """새 클라이언트 추가 및 ping 태스크 시작
        
        Args:
            bot_name: 봇 이름
            device_id: 디바이스 ID
            writer: StreamWriter 객체
        """
        client_key = (bot_name, device_id)
        
        # 기존 태스크가 있으면 취소
        if client_key in self.ping_tasks:
            old_task = self.ping_tasks[client_key]
            if not old_task.done():
                old_task.cancel()
                logger.debug(f"[PING_SCHEDULER] 기존 ping 태스크 취소: {client_key}")
        
        # 새 ping 태스크 생성
        task = asyncio.create_task(self._client_ping_loop(bot_name, device_id))
        self.ping_tasks[client_key] = task
        self.client_last_ping[client_key] = time.time()
        
        logger.info(f"[PING_SCHEDULER] ping 태스크 시작: {client_key}")
        
    async def remove_client(self, bot_name: str, device_id: str):
        """클라이언트 제거 및 ping 태스크 중지
        
        Args:
            bot_name: 봇 이름
            device_id: 디바이스 ID
        """
        client_key = (bot_name, device_id)
        
        # ping 태스크 취소
        if client_key in self.ping_tasks:
            task = self.ping_tasks[client_key]
            if not task.done():
                task.cancel()
            del self.ping_tasks[client_key]
            
        # 마지막 ping 시간 제거
        if client_key in self.client_last_ping:
            del self.client_last_ping[client_key]
            
        logger.info(f"[PING_SCHEDULER] ping 태스크 제거: {client_key}")
        
    async def _client_ping_loop(self, bot_name: str, device_id: str):
        """개별 클라이언트의 ping 루프
        
        Args:
            bot_name: 봇 이름
            device_id: 디바이스 ID
        """
        client_key = (bot_name, device_id)
        
        # 초기 지연 (0-5초 랜덤) - 동시 시작 방지
        initial_delay = random.uniform(0, 5)
        await asyncio.sleep(initial_delay)
        
        logger.debug(f"[PING_SCHEDULER] ping 루프 시작: {client_key}, 초기 지연: {initial_delay:.1f}초")
        
        while not g.shutdown_event.is_set():
            try:
                # 클라이언트가 아직 연결되어 있는지 확인
                if client_key not in g.clients:
                    logger.debug(f"[PING_SCHEDULER] 클라이언트 연결 해제됨: {client_key}")
                    break
                    
                writer = g.clients[client_key]
                if writer.is_closing():
                    logger.debug(f"[PING_SCHEDULER] Writer 닫힘: {client_key}")
                    break
                
                # ping 전송
                await self._send_ping(bot_name, device_id, writer)
                self.client_last_ping[client_key] = time.time()
                
                # 다음 ping까지 대기
                await asyncio.sleep(g.PING_INTERVAL_SECONDS)
                
            except asyncio.CancelledError:
                logger.debug(f"[PING_SCHEDULER] ping 루프 취소됨: {client_key}")
                break
            except Exception as e:
                logger.error(f"[PING_SCHEDULER] ping 루프 오류 {client_key}: {e}")
                await asyncio.sleep(5)  # 오류 시 잠시 대기 후 재시도
                
        logger.debug(f"[PING_SCHEDULER] ping 루프 종료: {client_key}")
        
    async def _send_ping(self, bot_name: str, device_id: str, writer: Any):
        """개별 클라이언트에게 ping 전송
        
        Args:
            bot_name: 봇 이름
            device_id: 디바이스 ID
            writer: StreamWriter 객체
        """
        client_key = (bot_name, device_id)
        
        # 주소 찾기 (역방향 조회)
        client_addr = None
        for addr, key in g.clients_by_addr.items():
            if key == client_key:
                client_addr = addr
                break
                
        # 클라이언트 정보 가져오기
        from core.client_status import client_status_manager
        client_info = client_status_manager.get_client_info(str(client_addr)) if client_addr else None
        
        current_time = int(time.time() * 1000)  # 밀리초
        
        ping_data = {
            "event": "ping",
            "data": {
                "bot_name": bot_name,
                "device_id": device_id,
                "channel_id": client_info.channel_id if client_info else "",
                "room": client_info.room if client_info else "",
                "user_hash": client_info.user_hash if client_info else "",
                "server_timestamp": current_time,
                "is_manual": False,
                "server_info": {
                    "total_clients": len(g.clients),
                    "timestamp": current_time
                }
            }
        }
        
        try:
            await send_json_response(writer, ping_data)
            logger.debug(f"[PING_SCHEDULER] ping 전송 성공: {client_key}")
        except Exception as e:
            logger.error(f"[PING_SCHEDULER] ping 전송 실패 {client_key}: {e}")
            raise
            
    def get_client_last_ping(self, bot_name: str, device_id: str) -> Optional[float]:
        """클라이언트의 마지막 ping 시간 조회
        
        Args:
            bot_name: 봇 이름
            device_id: 디바이스 ID
            
        Returns:
            마지막 ping 시간 (Unix timestamp) 또는 None
        """
        return self.client_last_ping.get((bot_name, device_id))


# 전역 ping 스케줄러 인스턴스
ping_scheduler = ClientPingScheduler()