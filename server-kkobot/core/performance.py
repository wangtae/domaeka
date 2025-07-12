import time
import asyncio
from functools import wraps
from core.logger import logger

# ✅ 블록 타이밍 측정용 Timer 클래스
class Timer:
    def __init__(self, tag="TASK", level="info", warn_threshold=None):
        """
        :param tag: 로그 태그
        :param level: 로그 레벨 ('info', 'debug', 'warning', 'error')
        :param warn_threshold: 경과 시간 경고 임계값 (초)
        """
        self.tag = tag
        self.level = level.lower()
        self.warn_threshold = warn_threshold

    def __enter__(self):
        self.start = time.perf_counter()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        elapsed = time.perf_counter() - self.start
        msg = f"[PERF] {self.tag} → {elapsed:.4f}초"

        if self.warn_threshold and elapsed > self.warn_threshold:
            logger.warning(f"[PERF][SLOW] {msg} (기준 {self.warn_threshold:.2f}초 초과)")
        else:
            getattr(logger, self.level, logger.info)(msg)

# ✅ 함수 실행 시간 측정용 데코레이터
def measure_performance(arg=None):
    """
    사용법:
    @measure_performance
    @measure_performance("라벨명")
    """
    if callable(arg):  # 인자 없이 사용했을 경우
        func = arg
        label = None
        return _decorate(func, label)
    else:
        label = arg
        def decorator(func):
            return _decorate(func, label)
        return decorator

def _decorate(func, label=None):
    if asyncio.iscoroutinefunction(func):
        @wraps(func)
        async def async_wrapper(*args, **kwargs):
            start_time = time.perf_counter()
            try:
                result = await func(*args, **kwargs)
                return result
            finally:
                end_time = time.perf_counter()
                elapsed_ms = (end_time - start_time) * 1000
                logger.info(f"[PERF][async] {label or func.__name__} 실행 시간: {elapsed_ms:.2f}ms")
        return async_wrapper
    else:
        @wraps(func)
        def sync_wrapper(*args, **kwargs):
            start_time = time.perf_counter()
            try:
                return func(*args, **kwargs)
            finally:
                end_time = time.perf_counter()
                elapsed_ms = (end_time - start_time) * 1000
                logger.info(f"[PERF][sync] {label or func.__name__} 실행 시간: {elapsed_ms:.2f}ms")
        return sync_wrapper
