"""
ping 관리 모듈
메시지 카운터 기반으로 연결된 클라이언트들에게 ping을 전송
"""
import time
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_json_response
import core.globals as g


class PingManager:
    def __init__(self):
        """ping 관리자 초기화"""
        self.initialized = True
        
    async def start(self):
        """ping 관리자 시작"""
        logger.info(f"[PING_MANAGER] ping 관리자 시작 - 메시지 {g.PING_MESSAGE_INTERVAL}개마다 전송")
        
    async def stop(self):
        """ping 관리자 중지"""
        logger.info("[PING_MANAGER] ping 관리자 중지")
        
    async def check_and_send_ping(self):
        """메시지 카운터를 확인하고 필요시 ping 전송"""
        g.ping_message_counter += 1
        
        if g.ping_message_counter >= g.PING_MESSAGE_INTERVAL:
            await self._send_ping_to_all_clients()
            g.ping_message_counter = 0  # 카운터 리셋
                
    async def _send_ping_to_all_clients(self):
        """모든 연결된 클라이언트에게 ping 전송"""
        if not g.clients:
            return
            
        current_time = int(time.time() * 1000)  # 밀리초
        ping_count = 0
        
        logger.info(f"[PING_MANAGER] 메시지 {g.PING_MESSAGE_INTERVAL}개 도달 - ping 전송 시작")
        
        # 연결된 모든 클라이언트에게 ping 전송
        for client_key, writer in list(g.clients.items()):
            try:
                if writer.is_closing():
                    logger.warning(f"[PING_MANAGER] 닫힌 연결 제거: {client_key}")
                    del g.clients[client_key]
                    continue
                    
                # client_key는 (bot_name, device_id) 튜플
                bot_name, device_id = client_key
                
                # 주소 찾기 (역방향 조회)
                client_addr = None
                for addr, key in g.clients_by_addr.items():
                    if key == client_key:
                        client_addr = addr
                        break
                
                from core.client_status import client_status_manager
                client_info = client_status_manager.get_client_info(str(client_addr)) if client_addr else None

                ping_data = {
                    "event": "ping",
                    "data": {
                        "bot_name": bot_name,  # client_key에서 가져온 값 사용
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
                
                await send_json_response(writer, ping_data)
                ping_count += 1
                logger.info(f"[PING_MANAGER] ping 전송: {client_key}")
                
            except Exception as e:
                logger.error(f"[PING_MANAGER] ping 전송 실패 {client_key}: {e}")
                # 전송 실패한 클라이언트는 연결 목록에서 제거
                try:
                    del g.clients[client_key]
                except KeyError:
                    pass
                    
        if ping_count > 0:
            logger.info(f"[PING_MANAGER] ping 전송 완료: {ping_count}개 클라이언트")


# 전역 ping 관리자 인스턴스
ping_manager = PingManager()