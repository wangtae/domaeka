"""
시스템 모니터링 모듈
서버의 CPU, 메모리, 디스크 사용률 등을 주기적으로 DB에 저장
"""
import json
import psutil
from datetime import datetime
from core.logger import logger
import core.globals as g


async def save_system_status():
    """
    시스템 상태를 kb_system_monitor 테이블에 저장
    """
    try:
        if not g.db_pool:
            logger.error("[SYSTEM_MONITOR] DB 풀이 초기화되지 않았습니다.")
            return False
            
        # 시스템 정보 수집
        cpu_percent = psutil.cpu_percent(interval=1)
        memory = psutil.virtual_memory()
        disk = psutil.disk_usage('/')
        
        # 활성 연결 수 계산
        active_connections = len(g.clients)
        
        # 메시지 큐 크기 (워커 큐 사용 시)
        message_queue_size = 0
        if hasattr(g, 'message_queue') and g.message_queue:
            message_queue_size = g.message_queue.qsize()
        
        # 서버 이름 설정
        server_name = getattr(g, 'process_name', 'domaeka-server')
        if not server_name:
            server_name = f"domaeka-{g.DB_NAME}"
        
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                sql = """
                INSERT INTO kb_system_monitor (
                    server_name, cpu_percent, memory_total, memory_used,
                    memory_percent, disk_usage_percent, active_connections,
                    message_queue_size
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                await cur.execute(sql, (
                    server_name,
                    cpu_percent,
                    memory.total,
                    memory.used,
                    memory.percent,
                    disk.percent,
                    active_connections,
                    message_queue_size
                ))
                await conn.commit()
                
        logger.info(
            f"[SYSTEM_MONITOR] 시스템 상태 저장 완료 - "
            f"CPU: {cpu_percent:.1f}%, "
            f"Memory: {memory.percent:.1f}%, "
            f"Disk: {disk.percent:.1f}%, "
            f"Connections: {active_connections}, "
            f"Queue: {message_queue_size}"
        )
        return True
        
    except Exception as e:
        logger.error(f"[SYSTEM_MONITOR] 시스템 상태 저장 실패: {e}")
        return False


async def system_monitor_task(interval=300):
    """
    시스템 모니터링 태스크
    
    Args:
        interval: 모니터링 주기 (초), 기본값 5분
    """
    logger.info(f"[SYSTEM_MONITOR] 시스템 모니터링 시작 (주기: {interval}초)")
    
    # 첫 번째 저장은 즉시 실행
    try:
        await save_system_status()
    except Exception as e:
        logger.error(f"[SYSTEM_MONITOR] 초기 모니터링 오류: {e}")
    
    while not g.shutdown_event.is_set():
        # 다음 실행까지 대기
        try:
            await asyncio.sleep(interval)
        except asyncio.CancelledError:
            logger.info("[SYSTEM_MONITOR] 시스템 모니터링 중단")
            break
            
        # 시스템 상태 저장
        try:
            await save_system_status()
        except Exception as e:
            logger.error(f"[SYSTEM_MONITOR] 모니터링 오류: {e}")
    
    logger.info("[SYSTEM_MONITOR] 시스템 모니터링 종료")


# 비동기 import를 위한 asyncio 추가
import asyncio