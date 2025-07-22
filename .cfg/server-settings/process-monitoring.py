# 프로세스 모니터링 코드 예시
# server/core/process_monitor.py 에 추가할 코드

import os
import psutil
import asyncio
import logging
from datetime import datetime

class ProcessMonitor:
    """프로세스 모니터링 및 DB 업데이트 클래스"""
    
    def __init__(self, process_name, db):
        self.process_name = process_name
        self.db = db
        self.pid = os.getpid()
        self.process = psutil.Process(self.pid)
        self.monitoring_interval = 30  # 30초마다 모니터링
        
    async def register_process(self):
        """프로세스 시작 시 PID 등록"""
        try:
            # PID 및 상태 업데이트
            await self.db.execute("""
                UPDATE kb_server_processes 
                SET pid = %s, 
                    status = 'running', 
                    last_heartbeat = NOW()
                WHERE process_name = %s
            """, (self.pid, self.process_name))
            
            logging.info(f"Process registered: {self.process_name} (PID: {self.pid})")
            
            # 모니터링 태스크 시작
            asyncio.create_task(self.monitor_loop())
            
        except Exception as e:
            logging.error(f"Failed to register process: {e}")
            raise
    
    async def monitor_loop(self):
        """주기적으로 CPU/메모리 사용량 모니터링"""
        while True:
            try:
                # CPU 사용률 (프로세스별)
                cpu_percent = self.process.cpu_percent(interval=1)
                
                # 메모리 사용량 (MB)
                memory_info = self.process.memory_info()
                memory_mb = memory_info.rss / 1024 / 1024
                
                # DB 업데이트
                await self.db.execute("""
                    UPDATE kb_server_processes 
                    SET cpu_usage = %s,
                        memory_usage = %s,
                        last_heartbeat = NOW()
                    WHERE process_name = %s
                """, (cpu_percent, memory_mb, self.process_name))
                
                logging.debug(f"Monitor update: CPU={cpu_percent:.1f}%, Memory={memory_mb:.1f}MB")
                
            except psutil.NoSuchProcess:
                logging.error(f"Process {self.pid} no longer exists")
                break
            except Exception as e:
                logging.error(f"Monitoring error: {e}")
            
            await asyncio.sleep(self.monitoring_interval)
    
    async def shutdown(self):
        """프로세스 종료 시 정리"""
        try:
            await self.db.execute("""
                UPDATE kb_server_processes 
                SET pid = NULL,
                    status = 'stopped',
                    cpu_usage = 0,
                    memory_usage = 0
                WHERE process_name = %s
            """, (self.process_name,))
            
            logging.info(f"Process shutdown: {self.process_name}")
            
        except Exception as e:
            logging.error(f"Failed to update shutdown status: {e}")


# main.py에 추가할 코드 예시
async def main():
    # 기존 코드...
    
    # 프로세스 모니터 초기화
    monitor = ProcessMonitor(args.name, db)
    await monitor.register_process()
    
    # 종료 시그널 핸들러
    import signal
    
    def signal_handler(signum, frame):
        asyncio.create_task(monitor.shutdown())
        # 기존 종료 로직...
    
    signal.signal(signal.SIGTERM, signal_handler)
    signal.signal(signal.SIGINT, signal_handler)
    
    # 서버 시작...


# requirements.txt에 추가
# psutil==5.9.8  # CPU/메모리 모니터링용