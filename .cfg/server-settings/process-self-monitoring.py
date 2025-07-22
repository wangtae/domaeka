# 프로세스 자체 모니터링 구현 예시
# server/core/process_self_monitor.py

import os
import psutil
import asyncio
import logging
from datetime import datetime

class ProcessSelfMonitor:
    """자신의 프로세스만 모니터링하는 클래스"""
    
    def __init__(self, process_name, db, interval=30):
        self.process_name = process_name
        self.db = db
        self.interval = interval  # 모니터링 주기 (초)
        self.pid = os.getpid()
        self.process = psutil.Process(self.pid)
        self._running = True
        
    async def start(self):
        """모니터링 시작 및 PID 등록"""
        try:
            # 프로세스 시작 시 DB 업데이트
            await self.db.execute("""
                UPDATE kb_server_processes 
                SET pid = %s, 
                    status = 'running',
                    last_heartbeat = NOW()
                WHERE process_name = %s
            """, (self.pid, self.process_name))
            
            logging.info(f"Process monitoring started: {self.process_name} (PID: {self.pid})")
            
            # 모니터링 루프 시작
            asyncio.create_task(self._monitor_loop())
            
        except Exception as e:
            logging.error(f"Failed to start monitoring: {e}")
            raise
    
    async def _monitor_loop(self):
        """주기적으로 자신의 리소스 사용량 측정"""
        while self._running:
            try:
                # CPU 사용률 측정 (이 프로세스만)
                # interval=0으로 하면 마지막 호출 이후의 평균값
                cpu_percent = self.process.cpu_percent(interval=0)
                
                # 메모리 사용량 (MB 단위)
                memory_info = self.process.memory_info()
                memory_mb = memory_info.rss / 1024 / 1024  # RSS (Resident Set Size)
                
                # 추가 정보 (옵션)
                num_threads = self.process.num_threads()
                num_connections = len(self.process.connections(kind='inet'))
                
                # DB 업데이트
                await self.db.execute("""
                    UPDATE kb_server_processes 
                    SET cpu_usage = %s,
                        memory_usage = %s,
                        last_heartbeat = NOW()
                    WHERE process_name = %s
                """, (cpu_percent, memory_mb, self.process_name))
                
                logging.debug(
                    f"Process stats - CPU: {cpu_percent:.1f}%, "
                    f"Memory: {memory_mb:.1f}MB, "
                    f"Threads: {num_threads}, "
                    f"Connections: {num_connections}"
                )
                
            except psutil.NoSuchProcess:
                logging.error(f"Process {self.pid} no longer exists")
                break
            except Exception as e:
                logging.error(f"Monitoring error: {e}", exc_info=True)
            
            await asyncio.sleep(self.interval)
    
    async def stop(self):
        """모니터링 중지 및 정리"""
        self._running = False
        
        try:
            await self.db.execute("""
                UPDATE kb_server_processes 
                SET pid = NULL,
                    status = 'stopped',
                    cpu_usage = 0.00,
                    memory_usage = 0.00
                WHERE process_name = %s
            """, (self.process_name,))
            
            logging.info(f"Process monitoring stopped: {self.process_name}")
            
        except Exception as e:
            logging.error(f"Failed to update stop status: {e}")


# main.py에 통합하는 예시
"""
# main.py 수정 예시

from core.process_self_monitor import ProcessSelfMonitor

async def main():
    # 기존 코드...
    
    # 프로세스 모니터링 시작
    process_monitor = ProcessSelfMonitor(
        process_name=args.name,
        db=db,
        interval=30  # 30초마다 측정
    )
    await process_monitor.start()
    
    # 종료 시그널 처리
    import signal
    
    async def shutdown_handler():
        await process_monitor.stop()
        # 기타 정리 작업...
        
    def signal_handler(signum, frame):
        asyncio.create_task(shutdown_handler())
        
    signal.signal(signal.SIGTERM, signal_handler)
    signal.signal(signal.SIGINT, signal_handler)
    
    try:
        # 서버 실행
        server = KakaoBotServer(port)
        await server.start()
    finally:
        await process_monitor.stop()
"""

# 참고: CPU 사용률 측정 방법
"""
1. interval=None (기본값)
   - process.cpu_percent() 첫 호출 시 0.0 반환
   - 이후 호출 시 마지막 호출 이후의 평균 사용률 반환

2. interval=0
   - 마지막 호출 이후의 평균 사용률 즉시 반환
   - 비블로킹

3. interval=1 (또는 다른 양수)
   - 지정된 시간(초) 동안 블로킹하며 측정
   - 더 정확하지만 블로킹됨

추천: interval=0 사용 (비블로킹 + 정확도 적절)
"""