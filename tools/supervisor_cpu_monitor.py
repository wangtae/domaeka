#!/usr/bin/env python3
"""
Supervisor용 CPU 모니터링 헬퍼
Supervisor의 XML-RPC API를 사용하여 프로세스를 관리합니다.
"""
import psutil
import time
import xmlrpc.client
import logging
import sys
from datetime import datetime

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class SupervisorCPUMonitor:
    def __init__(self, supervisor_url='http://localhost:9001/RPC2', 
                 program_name='domaeka-server',
                 cpu_limit=80.0,
                 check_interval=30,
                 check_duration=5):
        self.supervisor = xmlrpc.client.ServerProxy(supervisor_url)
        self.program_name = program_name
        self.cpu_limit = cpu_limit
        self.check_interval = check_interval
        self.check_duration = check_duration
        self.restart_count = 0
        self.last_restart = None
        self.restart_cooldown = 300  # 5분
        
    def get_process_info(self):
        """Supervisor에서 프로세스 정보 가져오기"""
        try:
            info = self.supervisor.supervisor.getProcessInfo(self.program_name)
            return info
        except Exception as e:
            logger.error(f"프로세스 정보 조회 실패: {e}")
            return None
            
    def find_process_by_pid(self, pid):
        """PID로 프로세스 찾기"""
        try:
            return psutil.Process(pid)
        except psutil.NoSuchProcess:
            return None
            
    def check_cpu_usage(self, process):
        """CPU 사용률 체크 (일정 시간 동안의 평균)"""
        try:
            # check_duration 동안의 평균 CPU 사용률
            cpu_percent = process.cpu_percent(interval=self.check_duration)
            
            # 메모리 정보도 함께 로깅
            memory_info = process.memory_info()
            memory_mb = memory_info.rss / 1024 / 1024
            
            logger.info(f"[{self.program_name}] PID: {process.pid}, "
                       f"CPU: {cpu_percent:.1f}%, 메모리: {memory_mb:.1f}MB")
            
            return cpu_percent
            
        except (psutil.NoSuchProcess, psutil.AccessDenied) as e:
            logger.error(f"프로세스 접근 오류: {e}")
            return 0
            
    def should_restart(self, cpu_percent):
        """재시작 여부 결정"""
        if cpu_percent <= self.cpu_limit:
            return False, None
            
        # 재시작 쿨다운 체크
        if self.last_restart:
            elapsed = (datetime.now() - self.last_restart).total_seconds()
            if elapsed < self.restart_cooldown:
                remaining = self.restart_cooldown - elapsed
                return False, f"쿨다운 중 ({remaining:.0f}초 남음)"
                
        return True, f"CPU 사용률 초과 ({cpu_percent:.1f}% > {self.cpu_limit}%)"
        
    def restart_process(self):
        """Supervisor를 통해 프로세스 재시작"""
        try:
            logger.warning(f"[{self.program_name}] 프로세스 재시작 중...")
            
            # Supervisor API로 재시작
            result = self.supervisor.supervisor.restartProcess(self.program_name)
            
            if result:
                self.restart_count += 1
                self.last_restart = datetime.now()
                logger.info(f"[{self.program_name}] 재시작 완료 "
                           f"(총 {self.restart_count}회)")
                return True
            else:
                logger.error(f"[{self.program_name}] 재시작 실패")
                return False
                
        except Exception as e:
            logger.error(f"재시작 오류: {e}")
            return False
            
    def run(self):
        """메인 모니터링 루프"""
        logger.info(f"CPU 모니터링 시작: {self.program_name} "
                   f"(제한: {self.cpu_limit}%)")
        
        while True:
            try:
                # Supervisor에서 프로세스 정보 가져오기
                proc_info = self.get_process_info()
                
                if not proc_info:
                    logger.error(f"프로세스 정보를 찾을 수 없음: {self.program_name}")
                    time.sleep(self.check_interval)
                    continue
                    
                # 프로세스 상태 확인
                state = proc_info['state']
                if state != 20:  # RUNNING = 20
                    logger.warning(f"프로세스가 실행 중이 아님: {state}")
                    time.sleep(self.check_interval)
                    continue
                    
                # PID로 실제 프로세스 찾기
                pid = proc_info['pid']
                process = self.find_process_by_pid(pid)
                
                if not process:
                    logger.error(f"PID {pid}에 해당하는 프로세스를 찾을 수 없음")
                    time.sleep(self.check_interval)
                    continue
                    
                # CPU 사용률 체크
                cpu_percent = self.check_cpu_usage(process)
                
                # 재시작 필요 여부 확인
                should_restart, reason = self.should_restart(cpu_percent)
                
                if should_restart:
                    logger.warning(f"재시작 조건 충족: {reason}")
                    self.restart_process()
                elif reason:
                    logger.info(f"재시작 보류: {reason}")
                    
                # 다음 체크까지 대기
                time.sleep(self.check_interval)
                
            except KeyboardInterrupt:
                logger.info("모니터링 종료")
                break
            except Exception as e:
                logger.error(f"모니터링 오류: {e}")
                time.sleep(self.check_interval)


if __name__ == "__main__":
    # 명령줄 인자 처리 (간단한 버전)
    program_name = sys.argv[1] if len(sys.argv) > 1 else 'domaeka-server'
    cpu_limit = float(sys.argv[2]) if len(sys.argv) > 2 else 80.0
    
    monitor = SupervisorCPUMonitor(
        program_name=program_name,
        cpu_limit=cpu_limit,
        check_interval=30,  # 30초마다 체크
        check_duration=5    # 5초간 평균 측정
    )
    
    monitor.run()