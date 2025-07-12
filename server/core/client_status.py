"""
클라이언트 상태 정보 처리 모듈
"""
import time
from typing import Dict, Any, Optional
from dataclasses import dataclass, asdict
from core.logger import logger


@dataclass
class ClientStatus:
    """클라이언트 상태 정보"""
    cpu: Optional[float] = None
    ram_used: Optional[int] = None
    ram_max: Optional[int] = None
    ram_used_mb: Optional[int] = None
    ram_max_mb: Optional[int] = None
    temp: Optional[float] = None


@dataclass
class ClientMonitoring:
    """클라이언트 모니터링 정보"""
    uptime: Optional[int] = None
    message_count: Optional[int] = None
    last_activity: Optional[str] = None
    connection_count: Optional[int] = None
    error_count: Optional[int] = None


@dataclass
class ClientInfo:
    """통합 클라이언트 정보"""
    # 기본 정보
    client_type: str = ""
    bot_name: str = ""
    device_uuid: str = ""
    device_id: str = ""  # 새로 추가된 필드
    mac_address: str = ""
    ip_address: str = ""
    version: str = ""
    
    # 연결 정보
    connected_at: float = 0.0
    last_ping: float = 0.0
    
    # 상태 정보
    status: Optional[ClientStatus] = None
    monitoring: Optional[ClientMonitoring] = None
    
    def to_dict(self) -> Dict[str, Any]:
        """딕셔너리로 변환"""
        result = asdict(self)
        if self.status:
            result['status'] = asdict(self.status)
        if self.monitoring:
            result['monitoring'] = asdict(self.monitoring)
        return result


class ClientStatusManager:
    """클라이언트 상태 관리자"""
    
    def __init__(self):
        self.clients: Dict[str, ClientInfo] = {}
    
    def register_client(self, client_addr: str, handshake_data: Dict[str, Any]) -> ClientInfo:
        """
        클라이언트 등록
        
        Args:
            client_addr: 클라이언트 주소
            handshake_data: 핸드셰이크 데이터
            
        Returns:
            ClientInfo: 등록된 클라이언트 정보
        """
        client_info = ClientInfo(
            bot_name=handshake_data.get('botName', ''),
            device_id=handshake_data.get('deviceId', ''),  # 새로 추가
            version=handshake_data.get('version', ''),
            connected_at=time.time()
        )
        
        self.clients[client_addr] = client_info
        logger.info(f"[CLIENT_STATUS] 클라이언트 등록: {client_addr} - {client_info.bot_name}")
        
        return client_info
    
    def update_auth_info(self, client_addr: str, auth_data: Dict[str, Any]):
        """
        클라이언트 인증 정보 업데이트
        
        Args:
            client_addr: 클라이언트 주소
            auth_data: 인증 데이터
        """
        if client_addr not in self.clients:
            return
        
        client = self.clients[client_addr]
        client.client_type = auth_data.get('clientType', '')
        client.device_uuid = auth_data.get('deviceUUID', '')
        client.mac_address = auth_data.get('macAddress', '')
        client.ip_address = auth_data.get('ipAddress', '')
        client.version = auth_data.get('version', '')
    
    def update_client_status(self, client_addr: str, status_data: Dict[str, Any]):
        """
        클라이언트 상태 정보 업데이트
        
        Args:
            client_addr: 클라이언트 주소
            status_data: 상태 데이터
        """
        if client_addr not in self.clients:
            return
        
        client = self.clients[client_addr]
        
        # RAM 정보 처리
        ram_info = status_data.get('ram', {})
        if ram_info:
            client.status = ClientStatus(
                cpu=status_data.get('cpu'),
                ram_used=ram_info.get('used'),
                ram_max=ram_info.get('max'),
                ram_used_mb=ram_info.get('usedMB'),
                ram_max_mb=ram_info.get('maxMB'),
                temp=status_data.get('temp')
            )
        else:
            client.status = ClientStatus(
                cpu=status_data.get('cpu'),
                temp=status_data.get('temp')
            )
    
    def update_monitoring_info(self, client_addr: str, monitoring_data: Dict[str, Any]):
        """
        클라이언트 모니터링 정보 업데이트
        
        Args:
            client_addr: 클라이언트 주소
            monitoring_data: 모니터링 데이터
        """
        if client_addr not in self.clients:
            return
        
        client = self.clients[client_addr]
        client.monitoring = ClientMonitoring(
            uptime=monitoring_data.get('uptime'),
            message_count=monitoring_data.get('messageCount'),
            last_activity=monitoring_data.get('lastActivity'),
            connection_count=monitoring_data.get('connectionCount'),
            error_count=monitoring_data.get('errorCount')
        )
    
    def update_ping_time(self, client_addr: str):
        """
        클라이언트 마지막 핑 시간 업데이트
        
        Args:
            client_addr: 클라이언트 주소
        """
        if client_addr in self.clients:
            self.clients[client_addr].last_ping = time.time()
    
    def remove_client(self, client_addr: str):
        """
        클라이언트 제거
        
        Args:
            client_addr: 클라이언트 주소
        """
        if client_addr in self.clients:
            client_info = self.clients[client_addr]
            logger.info(f"[CLIENT_STATUS] 클라이언트 제거: {client_addr} - {client_info.bot_name}")
            del self.clients[client_addr]
    
    def get_client_info(self, client_addr: str) -> Optional[ClientInfo]:
        """
        클라이언트 정보 조회
        
        Args:
            client_addr: 클라이언트 주소
            
        Returns:
            Optional[ClientInfo]: 클라이언트 정보
        """
        return self.clients.get(client_addr)
    
    def get_all_clients(self) -> Dict[str, ClientInfo]:
        """
        모든 클라이언트 정보 조회
        
        Returns:
            Dict[str, ClientInfo]: 모든 클라이언트 정보
        """
        return self.clients.copy()
    
    def get_client_summary(self) -> Dict[str, Any]:
        """
        클라이언트 요약 정보
        
        Returns:
            Dict[str, Any]: 요약 정보
        """
        total_clients = len(self.clients)
        active_clients = sum(1 for client in self.clients.values() 
                           if time.time() - client.last_ping < 300)  # 5분 이내 활성
        
        return {
            'total_clients': total_clients,
            'active_clients': active_clients,
            'clients': [client.to_dict() for client in self.clients.values()]
        }


# 전역 클라이언트 상태 관리자
client_status_manager = ClientStatusManager()