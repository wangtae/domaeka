"""
프로세스 자체 모니터링 모듈
"""
import os
import psutil
import asyncio
from datetime import datetime
from typing import Optional, Dict, Any, List
from collections import deque
import core.globals as g

# 로거 직접 가져오기
import logging
logger = logging.getLogger('kkobot_lite')


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
        self._sample_count = 0  # 샘플 카운트 추가
        
        # psutil 테스트
        try:
            test_cpu = self.process.cpu_percent(interval=0.1)
            test_mem = self.process.memory_info()
            logger.info(f"[PROCESS_MONITOR] psutil 테스트 - CPU: {test_cpu}%, Memory: {test_mem.rss/1024/1024:.1f}MB")
            print(f"[PROCESS_MONITOR] psutil 테스트 - CPU: {test_cpu}%, Memory: {test_mem.rss/1024/1024:.1f}MB")  # 콘솔 직접 출력
        except Exception as e:
            logger.error(f"[PROCESS_MONITOR] psutil 테스트 실패: {e}")
            print(f"[PROCESS_MONITOR] psutil 테스트 실패: {e}")  # 콘솔 직접 출력
        
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
        logger.info(f"[PROCESS_MONITOR] start() 메서드 호출됨 - Process: {self.process_name}, PID: {self.pid}")
        try:
            # 프로세스 시작 시 DB 업데이트
            if self.db:
                async with self.db.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute("""
                            UPDATE kb_server_processes 
                            SET pid = %s, 
                                status = 'running',
                                last_heartbeat = NOW()
                            WHERE process_name = %s
                        """, (self.pid, self.process_name))
                        await conn.commit()
            
            logger.info(f"Process monitoring started: {self.process_name} (PID: {self.pid})")
            
            # 첫 CPU 측정을 위해 1초 대기 후 측정
            await asyncio.sleep(1)
            self.process.cpu_percent(interval=1)  # 1초 동안 측정
            self._update_stats()
            
            # 모니터링 루프 시작
            self._monitor_task = asyncio.create_task(self._monitor_loop())
            logger.info(f"[PROCESS_MONITOR] 모니터링 태스크 생성 완료 - PID: {self.pid}")
            
        except Exception as e:
            logger.error(f"Failed to start monitoring: {e}", exc_info=True)
    
    def _update_stats(self):
        """CPU와 메모리 통계 업데이트"""
        try:
            # CPU 사용률 측정 (비블로킹)
            cpu = self.process.cpu_percent(interval=None)
            logger.debug(f"[PROCESS_MONITOR] psutil.cpu_percent() 반환값: {cpu}")
            
            # 첫 번째 호출은 0을 반환할 수 있으므로 무시
            # 또한 비정상적으로 높은 값(100% 초과)도 필터링
            if cpu > 0 and cpu <= 100:
                self._cpu_percent = cpu
                self._cpu_samples.append(cpu)
                logger.debug(f"[PROCESS_MONITOR] CPU 샘플 추가: {cpu}%")
            elif cpu > 100:
                logger.warning(f"[PROCESS_MONITOR] 비정상적인 CPU 값 무시: {cpu}%")
            else:
                logger.debug(f"[PROCESS_MONITOR] CPU 값이 0이므로 무시")
            
            # 메모리 사용량 (MB 단위)
            memory_info = self.process.memory_info()
            self._memory_mb = memory_info.rss / 1024 / 1024
            self._memory_samples.append(self._memory_mb)
            logger.debug(f"[PROCESS_MONITOR] 메모리 측정: RSS={memory_info.rss}, MB={self._memory_mb:.1f}")
        except Exception as e:
            logger.error(f"Failed to update stats: {e}", exc_info=True)
    
    async def _monitor_loop(self):
        """주기적으로 자신의 리소스 사용량 측정"""
        interval = getattr(g, 'PROCESS_MONITOR_INTERVAL', 3)
        logger.info(f"[PROCESS_MONITOR] 모니터링 루프 시작 - 측정 주기: {interval}초, PID: {self.pid}, Process: {self.process_name}")
        
        loop_count = 0
        while self._running:
            try:
                loop_count += 1
                self._sample_count += 1
                # 통계 업데이트
                self._update_stats()
                
                # 실시간 측정값 로그 출력 (3초마다)
                monitor_msg = (f"[PROCESS_MONITOR] #{loop_count} CPU: {self._cpu_percent:.1f}% (샘플: {len(self._cpu_samples)}개, "
                           f"평균: {sum(self._cpu_samples)/len(self._cpu_samples) if self._cpu_samples else 0:.1f}%, "
                           f"최대: {max(self._cpu_samples) if self._cpu_samples else 0:.1f}%) | "
                           f"MEM: {self._memory_mb:.1f}MB (샘플: {len(self._memory_samples)}개, "
                           f"평균: {sum(self._memory_samples)/len(self._memory_samples) if self._memory_samples else 0:.1f}MB, "
                           f"최대: {max(self._memory_samples) if self._memory_samples else 0:.1f}MB)")
                logger.info(monitor_msg)
                print(monitor_msg)  # 콘솔 직접 출력
                
                # kb_server_processes 테이블 업데이트 (평균값으로)
                # 초기 3개 샘플은 무시하고 DB 업데이트 (안정화 대기)
                if self.db and self._sample_count > 3:
                    # 평균값 계산
                    cpu_avg = sum(self._cpu_samples) / len(self._cpu_samples) if self._cpu_samples else 0
                    mem_avg = sum(self._memory_samples) / len(self._memory_samples) if self._memory_samples else 0
                    
                    logger.info(f"[PROCESS_MONITOR] DB 업데이트 시도 - process_name: {self.process_name}, CPU avg: {round(cpu_avg, 2)}%, MEM avg: {round(mem_avg, 2)}MB")
                    
                    try:
                        async with self.db.acquire() as conn:
                            async with conn.cursor() as cursor:
                                await cursor.execute("""
                                    UPDATE kb_server_processes 
                                    SET cpu_usage = %s,
                                        memory_usage = %s,
                                        last_heartbeat = NOW()
                                    WHERE process_name = %s
                                """, (round(cpu_avg, 2), round(mem_avg, 2), self.process_name))
                                
                                affected_rows = cursor.rowcount
                                await conn.commit()
                                
                                logger.info(f"[PROCESS_MONITOR] DB 업데이트 완료 - 영향받은 행: {affected_rows}")
                                
                                # 업데이트 확인
                                await cursor.execute("""
                                    SELECT cpu_usage, memory_usage, last_heartbeat 
                                    FROM kb_server_processes 
                                    WHERE process_name = %s
                                """, (self.process_name,))
                                result = await cursor.fetchone()
                                if result:
                                    logger.info(f"[PROCESS_MONITOR] DB 확인 - CPU: {result[0]}%, MEM: {result[1]}MB, last_heartbeat: {result[2]}")
                    except Exception as e:
                        logger.error(f"[PROCESS_MONITOR] DB 업데이트 실패: {e}", exc_info=True)
                else:
                    logger.warning(f"[PROCESS_MONITOR] DB 연결이 없습니다 - self.db: {self.db}")
                
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
    
    def reset_samples(self):
        """샘플 초기화 (비정상 값 제거용)"""
        self._cpu_samples.clear()
        self._memory_samples.clear()
        self._sample_count = 0
        logger.info("[PROCESS_MONITOR] 샘플 데이터 초기화 완료")
    
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
                async with self.db.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute("""
                            UPDATE kb_server_processes 
                            SET last_heartbeat = NOW()
                            WHERE process_name = %s
                        """, (self.process_name,))
                        await conn.commit()
            except Exception as e:
                logger.error(f"Failed to update heartbeat: {e}")
    
    async def stop(self):
        """모니터링 중지"""
        self._running = False
        
        try:
            if self.db:
                async with self.db.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute("""
                            UPDATE kb_server_processes 
                            SET pid = NULL,
                                status = 'stopped',
                                cpu_usage = 0.00,
                                memory_usage = 0.00
                            WHERE process_name = %s
                        """, (self.process_name,))
                        await conn.commit()
            
            logger.info(f"Process monitoring stopped: {self.process_name}")
            
        except Exception as e:
            logger.error(f"Failed to update stop status: {e}")