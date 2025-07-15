"""
타임아웃 및 재시도 처리 표준 라이브러리

시스템 전반에서 일관된 타임아웃 처리를 위한 표준 클래스 제공
"""
import asyncio
from typing import Optional, Callable, Any
from core.logger import logger


class TimeoutRetryHandler:
    """
    타임아웃 재시도 핸들러
    
    일관된 타임아웃 처리와 재시도 로직을 제공합니다.
    """
    
    def __init__(self, max_retries: int = 3, initial_delay: float = 1.0, 
                 backoff_factor: float = 2.0, max_delay: float = 60.0):
        """
        Args:
            max_retries: 최대 재시도 횟수
            initial_delay: 초기 대기 시간 (초)
            backoff_factor: 대기 시간 증가 배수
            max_delay: 최대 대기 시간 (초)
        """
        self.max_retries = max_retries
        self.retry_count = 0
        self.initial_delay = initial_delay
        self.backoff_factor = backoff_factor
        self.max_delay = max_delay
        self.current_delay = initial_delay
    
    def on_timeout(self) -> bool:
        """
        타임아웃 발생 시 호출
        
        Returns:
            bool: 재시도 가능 여부 (True: 재시도, False: 중단)
        """
        self.retry_count += 1
        return self.retry_count < self.max_retries
    
    def get_delay(self) -> float:
        """
        현재 대기 시간 반환
        
        Returns:
            float: 대기 시간 (초)
        """
        delay = self.current_delay
        # 다음 대기 시간 계산
        self.current_delay = min(
            self.current_delay * self.backoff_factor,
            self.max_delay
        )
        return delay
    
    def reset(self):
        """카운터 및 대기 시간 초기화"""
        self.retry_count = 0
        self.current_delay = self.initial_delay
    
    @property
    def attempts_left(self) -> int:
        """남은 시도 횟수"""
        return max(0, self.max_retries - self.retry_count)
    
    def log_timeout(self, context: str):
        """타임아웃 로그 기록"""
        logger.warning(
            f"[TIMEOUT] {context} - 시도 {self.retry_count}/{self.max_retries}, "
            f"다음 대기: {self.current_delay:.1f}초"
        )


class ErrorCountHandler:
    """
    연속 오류 카운트 핸들러
    
    무한 루프 방지를 위한 연속 오류 카운트 관리
    """
    
    def __init__(self, max_consecutive_errors: int = 10):
        """
        Args:
            max_consecutive_errors: 최대 연속 오류 허용 횟수
        """
        self.max_consecutive_errors = max_consecutive_errors
        self.error_count = 0
    
    def on_error(self) -> bool:
        """
        오류 발생 시 호출
        
        Returns:
            bool: 계속 진행 가능 여부 (True: 계속, False: 중단)
        """
        self.error_count += 1
        return self.error_count < self.max_consecutive_errors
    
    def on_success(self):
        """성공 시 호출 - 오류 카운트 리셋"""
        self.error_count = 0
    
    def log_error(self, context: str, error: Exception):
        """오류 로그 기록"""
        logger.error(
            f"[ERROR] {context} - 연속 오류 {self.error_count}/{self.max_consecutive_errors}: {error}"
        )
        
        if self.error_count >= self.max_consecutive_errors:
            logger.critical(
                f"[CRITICAL] {context} - 최대 연속 오류 횟수 초과, 작업 중단"
            )


async def with_timeout_retry(
    func: Callable,
    timeout: float,
    handler: Optional[TimeoutRetryHandler] = None,
    context: str = "",
    *args,
    **kwargs
) -> Optional[Any]:
    """
    타임아웃과 재시도를 적용하여 함수 실행
    
    Args:
        func: 실행할 비동기 함수
        timeout: 타임아웃 시간 (초)
        handler: 타임아웃 핸들러 (없으면 기본값 사용)
        context: 로깅용 컨텍스트 정보
        *args, **kwargs: func에 전달할 인자
    
    Returns:
        함수 실행 결과 또는 None (실패 시)
    """
    if handler is None:
        handler = TimeoutRetryHandler()
    
    while True:
        try:
            async with asyncio.timeout(timeout):
                result = await func(*args, **kwargs)
                handler.reset()  # 성공 시 리셋
                return result
                
        except asyncio.TimeoutError:
            if not handler.on_timeout():
                handler.log_timeout(f"{context} - 최대 재시도 초과")
                return None
            
            handler.log_timeout(context)
            delay = handler.get_delay()
            await asyncio.sleep(delay)
            
        except Exception as e:
            logger.error(f"[ERROR] {context} - 예외 발생: {e}")
            return None


class ConnectionHealthChecker:
    """
    연결 상태 검증 헬퍼
    
    TCP 연결의 건강 상태를 주기적으로 확인
    """
    
    def __init__(self, check_interval: float = 30.0):
        """
        Args:
            check_interval: 상태 확인 주기 (초)
        """
        self.check_interval = check_interval
        self.last_check = 0
    
    async def is_healthy(self, reader: asyncio.StreamReader, 
                        writer: asyncio.StreamWriter) -> bool:
        """
        연결 상태 확인
        
        Args:
            reader: StreamReader 객체
            writer: StreamWriter 객체
            
        Returns:
            bool: 연결 정상 여부
        """
        try:
            # Reader 상태 확인
            if reader.at_eof():
                return False
            
            # Writer 상태 확인
            if writer.is_closing():
                return False
            
            # 전송 버퍼 확인 (선택적)
            # writer.transport.get_write_buffer_size() == 0
            
            return True
            
        except Exception as e:
            logger.debug(f"[HEALTH_CHECK] 연결 상태 확인 실패: {e}")
            return False