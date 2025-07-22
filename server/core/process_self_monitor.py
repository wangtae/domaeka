"""
프로세스 자체 모니터링 모듈
"""
import os
import psutil
import asyncio
import logging
from datetime import datetime
from typing import Optional, Dict, Any, List
from collections import deque
import core.globals as g

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
        
        # 샘플 저장용 deque (최근 N개만 유지)
        max_samples = getattr(g, 'PROCESS_MONITOR_SAMPLES', 10)
        self._cpu_samples = deque(maxlen=max_samples)
        self._memory_samples = deque(maxlen=max_samples)
        
        # CPU 측정을 위한 초기값 설정
        self.process.cpu_percent(interval=None)
        
        # 초기값 즉시 측정
        self._update_stats()
        
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
            
            # 첫 CPU 측정을 위해 1초 대기 후 측정
            await asyncio.sleep(1)
            self.process.cpu_percent(interval=1)  # 1초 동안 측정
            self._update_stats()
            
            # 모니터링 루프 시작
            asyncio.create_task(self._monitor_loop())
            
        except Exception as e:
            logger.error(f"Failed to start monitoring: {e}", exc_info=True)
    
    def _update_stats(self):
        """CPU와 메모리 통계 업데이트"""
        try:
            # CPU 사용률 측정 (비블로킹)
            cpu = self.process.cpu_percent(interval=None)
            # 첫 번째 호출은 0을 반환할 수 있으므로 무시
            if cpu > 0:
                self._cpu_percent = cpu
                self._cpu_samples.append(cpu)
            
            # 메모리 사용량 (MB 단위)
            memory_info = self.process.memory_info()
            self._memory_mb = memory_info.rss / 1024 / 1024
            self._memory_samples.append(self._memory_mb)
        except Exception as e:
            logger.error(f"Failed to update stats: {e}")
    
    async def _monitor_loop(self):
        """주기적으로 자신의 리소스 사용량 측정"""
        interval = getattr(g, 'PROCESS_MONITOR_INTERVAL', 3)
        
        while self._running:
            try:
                # 통계 업데이트
                self._update_stats()
                
                # kb_server_processes 테이블 업데이트 (현재값으로)
                if self.db:
                    await self.db.execute("""
                        UPDATE kb_server_processes 
                        SET cpu_usage = %s,
                            memory_usage = %s,
                            last_heartbeat = NOW()
                        WHERE process_name = %s
                    """, (self._cpu_percent, self._memory_mb, self.process_name))
                
                logger.info(
                    f"Process stats - PID: {self.pid}, "
                    f"CPU: {self._cpu_percent:.1f}%, "
                    f"Memory: {self._memory_mb:.1f}MB, "
                    f"Samples: CPU={len(self._cpu_samples)}, MEM={len(self._memory_samples)}"
                )
                
            except psutil.NoSuchProcess:
                logger.error(f"Process {self.pid} no longer exists")
                break
            except Exception as e:
                logger.error(f"Monitoring error: {e}", exc_info=True)
            
            # 설정된 주기마다 측정
            await asyncio.sleep(interval)
    
    def get_current_stats(self) -> Dict[str, Any]:
        """현재 프로세스 상태 반환 (ping 이벤트용) - 평균값과 최대값 포함"""
        # CPU 통계 계산
        cpu_avg = 0.0
        cpu_max = 0.0
        if self._cpu_samples:
            cpu_avg = sum(self._cpu_samples) / len(self._cpu_samples)
            cpu_max = max(self._cpu_samples)
        
        # 메모리 통계 계산
        mem_avg = 0.0
        mem_max = 0.0
        if self._memory_samples:
            mem_avg = sum(self._memory_samples) / len(self._memory_samples)
            mem_max = max(self._memory_samples)
        
        return {
            'process_name': self.process_name,
            'server_cpu_usage': round(cpu_avg, 2),      # 평균값
            'server_cpu_max': round(cpu_max, 2),        # 최대값
            'server_memory_usage': round(mem_avg, 2),   # 평균값
            'server_memory_max': round(mem_max, 2)      # 최대값
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