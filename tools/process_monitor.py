#!/usr/bin/env python3
"""
프로세스 모니터링 및 자동 재시작 도구

사용법:
    python process_monitor.py --name "domaeka-prod-01" --cpu-limit 80 --mem-limit 2048
"""
import psutil
import subprocess
import time
import argparse
import logging
from datetime import datetime
import signal
import sys

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class ProcessMonitor:
    def __init__(self, process_name, cpu_limit=80, mem_limit=2048, check_interval=10):
        self.process_name = process_name
        self.cpu_limit = cpu_limit
        self.mem_limit = mem_limit  # MB
        self.check_interval = check_interval
        self.restart_count = 0
        self.max_restarts = 10
        self.restart_cooldown = 60  # 초
        self.last_restart = None
        self.running = True
        
    def find_process(self):
        """프로세스 찾기"""
        for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
            try:
                cmdline = ' '.join(proc.info['cmdline'] or [])
                if self.process_name in cmdline:
                    return proc
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
        return None
        
    def check_process_health(self, proc):
        """프로세스 상태 체크"""
        try:
            # CPU 사용률 (5초 평균)
            cpu_percent = proc.cpu_percent(interval=5)
            
            # 메모리 사용량
            memory_info = proc.memory_info()
            memory_mb = memory_info.rss / 1024 / 1024
            
            logger.info(f"프로세스 상태 - PID: {proc.pid}, CPU: {cpu_percent:.1f}%, "
                       f"메모리: {memory_mb:.1f}MB")
            
            # 임계값 체크
            if cpu_percent > self.cpu_limit:
                logger.warning(f"CPU 사용률 초과: {cpu_percent:.1f}% > {self.cpu_limit}%")
                return False, f"CPU 사용률 초과 ({cpu_percent:.1f}%)"
                
            if memory_mb > self.mem_limit:
                logger.warning(f"메모리 사용량 초과: {memory_mb:.1f}MB > {self.mem_limit}MB")
                return False, f"메모리 사용량 초과 ({memory_mb:.1f}MB)"
                
            return True, "정상"
            
        except (psutil.NoSuchProcess, psutil.AccessDenied) as e:
            return False, f"프로세스 접근 오류: {e}"
            
    def restart_process(self, proc, reason):
        """프로세스 재시작"""
        # 재시작 쿨다운 체크
        if self.last_restart:
            elapsed = (datetime.now() - self.last_restart).total_seconds()
            if elapsed < self.restart_cooldown:
                logger.info(f"재시작 쿨다운 중... {self.restart_cooldown - elapsed:.0f}초 남음")
                return False
                
        # 재시작 횟수 체크
        if self.restart_count >= self.max_restarts:
            logger.error(f"최대 재시작 횟수 초과 ({self.max_restarts}회)")
            return False
            
        try:
            # 프로세스 종료
            logger.info(f"프로세스 종료 중... (이유: {reason})")
            proc.terminate()
            
            # 정상 종료 대기
            try:
                proc.wait(timeout=10)
            except psutil.TimeoutExpired:
                logger.warning("정상 종료 실패, 강제 종료...")
                proc.kill()
                proc.wait(timeout=5)
                
            # 잠시 대기
            time.sleep(5)
            
            # 프로세스 재시작
            logger.info("프로세스 재시작 중...")
            
            # systemd 사용하는 경우
            subprocess.run(['systemctl', 'restart', 'domaeka-server'], check=True)
            
            # 또는 직접 실행
            # subprocess.Popen([
            #     '/home/wangt/projects/client/domaeka/domaeka.dev/server/venv/bin/python',
            #     'main.py',
            #     '--name=domaeka-prod-01'
            # ], cwd='/home/wangt/projects/client/domaeka/domaeka.dev/server')
            
            self.restart_count += 1
            self.last_restart = datetime.now()
            
            logger.info(f"프로세스 재시작 완료 (재시작 횟수: {self.restart_count})")
            return True
            
        except Exception as e:
            logger.error(f"프로세스 재시작 실패: {e}")
            return False
            
    def run(self):
        """모니터링 루프"""
        logger.info(f"프로세스 모니터링 시작: {self.process_name}")
        logger.info(f"제한: CPU {self.cpu_limit}%, 메모리 {self.mem_limit}MB")
        
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
        while self.running:
            try:
                proc = self.find_process()
                
                if not proc:
                    logger.warning(f"프로세스를 찾을 수 없음: {self.process_name}")
                    # 프로세스가 없으면 시작 시도
                    # self.start_process()
                else:
                    # 프로세스 상태 체크
                    healthy, reason = self.check_process_health(proc)
                    
                    if not healthy:
                        logger.warning(f"프로세스 상태 이상: {reason}")
                        self.restart_process(proc, reason)
                        
                # 대기
                time.sleep(self.check_interval)
                
            except KeyboardInterrupt:
                break
            except Exception as e:
                logger.error(f"모니터링 오류: {e}")
                time.sleep(self.check_interval)
                
    def signal_handler(self, signum, frame):
        """시그널 핸들러"""
        logger.info("모니터링 종료 신호 수신")
        self.running = False
        sys.exit(0)


def main():
    parser = argparse.ArgumentParser(description='프로세스 모니터링 도구')
    parser.add_argument('--name', required=True, help='감시할 프로세스 이름')
    parser.add_argument('--cpu-limit', type=int, default=80, help='CPU 사용률 제한 (%)')
    parser.add_argument('--mem-limit', type=int, default=2048, help='메모리 사용량 제한 (MB)')
    parser.add_argument('--interval', type=int, default=10, help='체크 간격 (초)')
    
    args = parser.parse_args()
    
    monitor = ProcessMonitor(
        process_name=args.name,
        cpu_limit=args.cpu_limit,
        mem_limit=args.mem_limit,
        check_interval=args.interval
    )
    
    monitor.run()


if __name__ == "__main__":
    main()