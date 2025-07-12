import logging
import sys
import os
import time
import inspect
import asyncio
from functools import wraps
from logging.handlers import TimedRotatingFileHandler, QueueHandler, QueueListener
import queue

# 로그 포맷 정의
from datetime import datetime
import pytz

KST = pytz.timezone('Asia/Seoul')  # 필요 시 시간대 설정 (옵션)

# ANSI 색상 코드
RESET = "\x1b[0m"
COLORS = {
    "DEBUG": "\x1b[90m",  # 회색
    "INFO": "\x1b[32m",  # 초록
    "WARNING": "\x1b[95m",  # 밝은 마젠타 (노랑 대체)
    "ERROR": "\x1b[31;1m",  # 빨강
    "CRITICAL": "\x1b[41;1m",  # 빨강 배경
}


class CustomFormatter(logging.Formatter):
    def formatTime(self, record, datefmt=None):
        dt = datetime.fromtimestamp(record.created, tz=KST)
        millis = int(dt.microsecond / 100000)
        return f"{dt.strftime('%Y-%m-%d %H:%M:%S')}.{millis}"

    def format(self, record):
        log_color = COLORS.get(record.levelname, RESET)

        record.asctime = self.formatTime(record)
        log_fmt = f"{log_color}[{{asctime}}][{{levelname}}][{{filename}}:{{funcName}}:{{lineno}}] {{message}}{RESET}"

        formatter = logging.Formatter(log_fmt, style='{')
        return formatter.format(record)


class ErrorNotificationHandler(logging.Handler):
    """에러 발생 시 알림을 보내는 핸들러"""

    def __init__(self):
        super().__init__()
        # ERROR 레벨 이상의 로그만 처리
        self.setLevel(logging.ERROR)

    def emit(self, record):
        try:
            # 비동기 함수를 로깅 핸들러에서 호출하기 위한 트릭
            from core.error_notifier import notify_error

            error_type = record.levelname
            error_message = self.format(record)

            # exc_info가 있으면 포함
            exc_info = record.exc_info[1] if record.exc_info else None

            # 컨텍스트 정보 구성
            context = {
                "module": record.module,
                "function": record.funcName,
                "line": record.lineno
            }

            # 비동기 이벤트 루프 접근
            try:
                loop = asyncio.get_event_loop()
                asyncio.run_coroutine_threadsafe(
                    notify_error(error_message, error_type, context, exc_info),
                    loop
                )
            except Exception:
                # 로깅 핸들러 내에서 오류가 발생하면 무시
                pass
        except Exception:
            # 에러 알림 모듈 로드 실패 등의 오류는 무시
            pass


def init_logger(level='DEBUG'):
    logger = logging.getLogger("kakao-server")

    if logger.hasHandlers():
        logger.handlers.clear()

    logger.setLevel(getattr(logging, level.upper(), logging.DEBUG))

    # 기존 핸들러 생성 (콘솔, 파일, 에러 알림)
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(CustomFormatter())

    LOG_DIR = os.getenv("LOG_DIR", "logs")
    os.makedirs(LOG_DIR, exist_ok=True)

    file_handler = TimedRotatingFileHandler(
        filename=os.path.join(LOG_DIR, "server.log"),
        when="midnight",
        backupCount=30,
        encoding="utf-8"
    )
    file_handler.setFormatter(CustomFormatter())

    error_notifier = ErrorNotificationHandler()
    error_notifier.setFormatter(CustomFormatter())

    # 1. 로그 큐 생성
    log_queue = queue.Queue(-1)

    # 2. QueueHandler를 logger에 추가
    queue_handler = QueueHandler(log_queue)
    logger.addHandler(queue_handler)

    # 3. QueueListener를 별도 스레드에서 실행 (핸들러는 기존 것 사용)
    listener = QueueListener(log_queue, console_handler, file_handler, error_notifier)
    listener.daemon = True  # 서버 종료시 자동 종료
    listener.start()

    # listener를 logger 객체에 보관(필요시 종료용)
    logger._queue_listener = listener

    return logger


logger = init_logger()  # ✅ 전역 선언으로 import 가능하게!
