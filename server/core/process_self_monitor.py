"""
프로세스 자체 모니터링 모듈
"""
import os
import psutil
import asyncio
import logging
from datetime import datetime
from typing import Optional, Dict, Any

logger = logging.getLogger(__name__)


class ProcessSelfMonitor:
    """자신의 프로세스만 모니터링하는 클래스"""
    
    def __init__(self, process_name: str, db=None):
        self.process_name = process_name
        self.db = db
        self.pid = os.getpid()
        self.process = psutil.Process(self.pid)
        self._cpu_percent = 0.0
        self._memory_mb = 0.0
        self._last_cpu_time = None
        self._running = True
        
        # CPU 측정을 위한 초기값 설정
        self.process.cpu_percent(interval=None)
        
    async def start(self):
        """모니터링 시작"""
        try:
            # 프로세스 시작 시 DB 업데이트
            if self.db:
                await self.db.execute("""
                    UPDATE kb_server_processes 
                    SET pid = %s, 
                        status = 'running',
                        last_heartbeat = NOW()
                    WHERE process_name = %s
                """, (self.pid, self.process_name))
            
            logger.info(f"Process monitoring started: {self.process_name} (PID: {self.pid})")
            
            # 모니터링 루프 시작
            asyncio.create_task(self._monitor_loop())
            
        except Exception as e:
            logger.error(f"Failed to start monitoring: {e}", exc_info=True)
    
    async def _monitor_loop(self):
        """주기적으로 자신의 리소스 사용량 측정 (5초마다)"""
        while self._running:
            try:
                # CPU 사용률 측정 (비블로킹)
                self._cpu_percent = self.process.cpu_percent(interval=None)
                
                # 메모리 사용량 (MB 단위)
                memory_info = self.process.memory_info()
                self._memory_mb = memory_info.rss / 1024 / 1024
                
                # kb_server_processes 테이블 업데이트
                if self.db:
                    await self.db.execute("""
                        UPDATE kb_server_processes 
                        SET cpu_usage = %s,
                            memory_usage = %s,
                            last_heartbeat = NOW()
                        WHERE process_name = %s
                    """, (self._cpu_percent, self._memory_mb, self.process_name))
                
                logger.debug(
                    f"Process stats - PID: {self.pid}, "
                    f"CPU: {self._cpu_percent:.1f}%, "
                    f"Memory: {self._memory_mb:.1f}MB"
                )
                
            except psutil.NoSuchProcess:
                logger.error(f"Process {self.pid} no longer exists")
                break
            except Exception as e:
                logger.error(f"Monitoring error: {e}", exc_info=True)
            
            # 5초마다 측정 (ping은 30초마다이므로 더 자주 측정)
            await asyncio.sleep(5)
    
    def get_current_stats(self) -> Dict[str, Any]:
        """현재 프로세스 상태 반환 (ping 이벤트용)"""
        return {
            'process_name': self.process_name,
            'server_cpu_usage': round(self._cpu_percent, 2),
            'server_memory_usage': round(self._memory_mb, 2)
        }
    
    async def update_heartbeat(self):
        """heartbeat 업데이트 (ping 이벤트 시 호출)"""
        if self.db:
            try:
                await self.db.execute("""
                    UPDATE kb_server_processes 
                    SET last_heartbeat = NOW()
                    WHERE process_name = %s
                """, (self.process_name,))
            except Exception as e:
                logger.error(f"Failed to update heartbeat: {e}")
    
    async def stop(self):
        """모니터링 중지"""
        self._running = False
        
        try:
            if self.db:
                await self.db.execute("""
                    UPDATE kb_server_processes 
                    SET pid = NULL,
                        status = 'stopped',
                        cpu_usage = 0.00,
                        memory_usage = 0.00
                    WHERE process_name = %s
                """, (self.process_name,))
            
            logger.info(f"Process monitoring stopped: {self.process_name}")
            
        except Exception as e:
            logger.error(f"Failed to update stop status: {e}")