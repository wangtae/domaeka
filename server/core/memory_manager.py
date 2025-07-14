"""
메모리 관리 및 누수 방지 모듈
주기적인 가비지 컬렉션, 메모리 모니터링, 오래된 데이터 정리
"""
import gc
import asyncio
import time
import psutil
from typing import Dict, Any, Optional
from core.logger import logger
import core.globals as g


class MemoryManager:
    """메모리 관리자"""
    
    def __init__(self):
        self.last_gc_time = time.time()
        self.gc_interval = 600  # 10분
        self.memory_threshold = 80  # 80% 이상시 경고
        self.critical_threshold = 90  # 90% 이상시 강제 정리
        self.monitoring_enabled = True
        
    async def start(self):
        """메모리 관리자 시작"""
        logger.info("[MEMORY_MANAGER] 메모리 관리자 시작")
        # 가비지 컬렉션 태스크
        asyncio.create_task(self._periodic_gc())
        # 메모리 모니터링 태스크
        asyncio.create_task(self._memory_monitor())
        # 오래된 데이터 정리 태스크
        asyncio.create_task(self._cleanup_old_data())
        
    async def _periodic_gc(self):
        """주기적인 가비지 컬렉션"""
        while not g.shutdown_event.is_set():
            try:
                # GC 실행 전 메모리 상태
                before_mem = psutil.Process().memory_info().rss / 1024 / 1024  # MB
                
                # 가비지 컬렉션 실행
                collected = gc.collect()
                
                # GC 실행 후 메모리 상태
                after_mem = psutil.Process().memory_info().rss / 1024 / 1024  # MB
                freed = before_mem - after_mem
                
                if collected > 0 or freed > 0:
                    logger.info(
                        f"[MEMORY_MANAGER] GC 완료 - "
                        f"수집된 객체: {collected}개, "
                        f"해제된 메모리: {freed:.1f}MB"
                    )
                
                self.last_gc_time = time.time()
                
            except Exception as e:
                logger.error(f"[MEMORY_MANAGER] GC 오류: {e}")
                
            await asyncio.sleep(self.gc_interval)
            
    async def _memory_monitor(self):
        """메모리 사용량 모니터링"""
        while not g.shutdown_event.is_set():
            try:
                # 시스템 전체 메모리
                system_memory = psutil.virtual_memory()
                
                # 현재 프로세스 메모리
                process = psutil.Process()
                process_memory = process.memory_info()
                
                # 메모리 사용률 계산
                system_percent = system_memory.percent
                process_mb = process_memory.rss / 1024 / 1024
                
                # 임계값 체크
                if system_percent > self.critical_threshold:
                    logger.critical(
                        f"[MEMORY_MANAGER] 심각한 메모리 사용률: "
                        f"시스템 {system_percent:.1f}%, "
                        f"프로세스 {process_mb:.1f}MB"
                    )
                    await self._emergency_cleanup()
                    
                elif system_percent > self.memory_threshold:
                    logger.warning(
                        f"[MEMORY_MANAGER] 높은 메모리 사용률: "
                        f"시스템 {system_percent:.1f}%, "
                        f"프로세스 {process_mb:.1f}MB"
                    )
                    
                # 디버그 로깅 (5분마다)
                logger.debug(
                    f"[MEMORY_MANAGER] 메모리 상태 - "
                    f"시스템: {system_percent:.1f}%, "
                    f"프로세스: {process_mb:.1f}MB, "
                    f"활성 연결: {len(g.clients)}"
                )
                
            except Exception as e:
                logger.error(f"[MEMORY_MANAGER] 모니터링 오류: {e}")
                
            await asyncio.sleep(300)  # 5분마다 체크
            
    async def _cleanup_old_data(self):
        """오래된 데이터 정리"""
        while not g.shutdown_event.is_set():
            try:
                current_time = time.time()
                cleaned_count = 0
                
                # 1. 오래된 클라이언트 상태 정리 (1시간 이상)
                from core.client_status import client_status_manager
                for addr, client_info in list(client_status_manager.clients.items()):
                    if current_time - client_info.last_ping > 3600:  # 1시간
                        client_status_manager.remove_client(addr)
                        cleaned_count += 1
                        logger.info(f"[MEMORY_MANAGER] 오래된 클라이언트 정리: {addr}")
                
                # 2. 닫힌 writer 정리
                for client_key, writer in list(g.clients.items()):
                    if writer.is_closing():
                        del g.clients[client_key]
                        cleaned_count += 1
                        logger.info(f"[MEMORY_MANAGER] 닫힌 writer 정리: {client_key}")
                
                if cleaned_count > 0:
                    logger.info(f"[MEMORY_MANAGER] 총 {cleaned_count}개 항목 정리 완료")
                    
            except Exception as e:
                logger.error(f"[MEMORY_MANAGER] 데이터 정리 오류: {e}")
                
            await asyncio.sleep(1800)  # 30분마다
            
    async def _emergency_cleanup(self):
        """긴급 메모리 정리"""
        logger.warning("[MEMORY_MANAGER] 긴급 메모리 정리 시작")
        
        try:
            # 1. 강제 GC
            gc.collect(2)  # 최고 레벨 GC
            
            # 2. 메시지 큐 정리 (오래된 메시지 제거)
            if hasattr(g, 'message_queue') and g.message_queue:
                queue_size = g.message_queue.qsize()
                if queue_size > 5000:
                    logger.warning(f"[MEMORY_MANAGER] 메시지 큐 크기 초과: {queue_size}")
                    # 큐의 절반 정리
                    for _ in range(queue_size // 2):
                        try:
                            g.message_queue.get_nowait()
                        except asyncio.QueueEmpty:
                            break
                            
            # 3. 모든 클라이언트 상태 초기화
            from core.client_status import client_status_manager
            old_count = len(client_status_manager.clients)
            client_status_manager.clients.clear()
            logger.warning(f"[MEMORY_MANAGER] {old_count}개 클라이언트 상태 초기화")
            
        except Exception as e:
            logger.error(f"[MEMORY_MANAGER] 긴급 정리 오류: {e}")
            
    def get_memory_stats(self) -> Dict[str, Any]:
        """현재 메모리 통계 반환"""
        try:
            system_memory = psutil.virtual_memory()
            process = psutil.Process()
            process_memory = process.memory_info()
            
            return {
                'system_percent': system_memory.percent,
                'system_available_mb': system_memory.available / 1024 / 1024,
                'process_rss_mb': process_memory.rss / 1024 / 1024,
                'process_vms_mb': process_memory.vms / 1024 / 1024,
                'gc_stats': gc.get_stats(),
                'gc_count': gc.get_count(),
                'active_clients': len(g.clients),
                'queue_size': g.message_queue.qsize() if hasattr(g, 'message_queue') and g.message_queue else 0
            }
        except Exception as e:
            logger.error(f"[MEMORY_MANAGER] 통계 수집 오류: {e}")
            return {}


# 전역 메모리 관리자 인스턴스
memory_manager = MemoryManager()