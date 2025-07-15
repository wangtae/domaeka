"""
워커 큐 처리 모듈
"""
import asyncio
import json
from typing import Dict, Any
from core.logger import logger
from core.message_processor import process_message
from core.timeout_handler import with_timeout_retry, TimeoutRetryHandler
import core.globals as g


async def get_room_semaphore(channel_id: str, concurrency: int = None):
    """
    채널별 세마포어 가져오기 (없으면 생성)
    
    Args:
        channel_id: 채널 ID
        concurrency: 동시 처리 제한 수 (기본값: ROOM_CONCURRENCY)
        
    Returns:
        asyncio.Semaphore: 채널별 세마포어
    """
    if concurrency is None:
        concurrency = g.ROOM_CONCURRENCY
        
    async with g.room_semaphore_lock:
        if channel_id not in g.room_semaphores:
            logger.info(f"[SEMAPHORE] 세마포어 생성: channel_id={channel_id}, concurrency={concurrency}")
            g.room_semaphores[channel_id] = asyncio.Semaphore(concurrency)
        return g.room_semaphores[channel_id]


async def process_message_with_limit(message: Dict[str, Any]):
    """
    세마포어를 사용하여 동시성을 제한하면서 메시지 처리
    
    Args:
        message: 처리할 메시지
        
    Returns:
        bool: 처리 성공 여부
    """
    bot_name = message.get('bot_name')
    data = message.get('data', {})
    channel_id = data.get('channel_id') or data.get('channelId', '')
    sender = data.get('sender')
    text = data.get('text')
    
    if not channel_id or channel_id == 'None':
        logger.error(f"[SEMAPHORE] channel_id 누락: {message}")
        await process_message(message)  # 세마포어 없이 처리
        return True
    
    # 봇별 세마포어 획득
    bot_semaphore = g.bot_semaphores[bot_name]
    
    # 방별 세마포어 획득
    room_semaphore = await get_room_semaphore(str(channel_id))
    
    logger.debug(f"[SEMAPHORE] 진입 대기: bot={bot_name}, channel_id={channel_id}")
    
    # 봇 세마포어와 방 세마포어 모두 획득
    async with bot_semaphore:
        async with room_semaphore:
            logger.debug(f"[SEMAPHORE] 진입: bot={bot_name}, channel_id={channel_id}, sender={sender}")
            try:
                await process_message(message)
                return True  # 성공 시 True 반환
            finally:
                logger.debug(f"[SEMAPHORE] 반환: bot={bot_name}, channel_id={channel_id}")


async def message_worker(worker_id: int):
    """
    메시지 큐에서 메시지를 가져와 처리하는 워커
    
    Args:
        worker_id: 워커 식별자
    """
    logger.info(f"[WORKER {worker_id}] 시작됨")
    
    while not g.shutdown_event.is_set():
        try:
            # 큐에서 메시지 가져오기 (타임아웃 1초)
            message = await asyncio.wait_for(g.message_queue.get(), timeout=1.0)
            
            logger.debug(f"[WORKER {worker_id}] 메시지 처리 시작")
            
            try:
                # 5분 타임아웃으로 메시지 처리
                result = await with_timeout_retry(
                    process_message_with_limit,
                    300,  # 300초 = 5분
                    TimeoutRetryHandler(max_retries=1),  # 메시지 처리는 1회만 재시도
                    f"WORKER {worker_id} 메시지 처리",
                    message
                )
                
                if result is not None:
                    logger.debug(f"[WORKER {worker_id}] 메시지 처리 완료")
                else:
                    logger.error(f"[WORKER {worker_id}] 메시지 처리 실패 (타임아웃 또는 오류)")
            except Exception as e:
                logger.error(f"[WORKER {worker_id}] 메시지 처리 실패: {e}", exc_info=True)
            finally:
                # 작업 완료 표시
                g.message_queue.task_done()
                
        except asyncio.TimeoutError:
            # 타임아웃은 정상 동작 (종료 체크를 위함)
            continue
        except Exception as e:
            logger.error(f"[WORKER {worker_id}] 워커 오류: {e}", exc_info=True)
            await asyncio.sleep(1)  # 오류 발생 시 잠시 대기
    
    logger.info(f"[WORKER {worker_id}] 종료됨")


async def start_workers():
    """
    워커 풀 시작
    """
    logger.info(f"[WORKER POOL] {g.MAX_CONCURRENT_WORKERS}개 워커 시작")
    
    # 메시지 큐 초기화
    g.message_queue = asyncio.Queue(maxsize=10000)
    
    # 워커 태스크 생성
    for i in range(g.MAX_CONCURRENT_WORKERS):
        worker_task = asyncio.create_task(message_worker(i))
        g.workers.append(worker_task)
    
    logger.info(f"[WORKER POOL] 모든 워커 시작 완료")


async def stop_workers():
    """
    워커 풀 종료
    """
    logger.info("[WORKER POOL] 워커 종료 시작")
    
    # shutdown_event 설정은 이미 되어있음
    
    # 모든 워커 태스크 완료 대기
    if g.workers:
        await asyncio.gather(*g.workers, return_exceptions=True)
    
    logger.info("[WORKER POOL] 모든 워커 종료 완료")