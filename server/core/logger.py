"""
로거 설정 모듈
"""
import logging
import os
from datetime import datetime
from pathlib import Path


def init_logger(level: str = 'INFO') -> logging.Logger:
    """로거 초기화"""
    
    # 로그 디렉토리 생성
    log_dir = Path(__file__).parent.parent / "logs"
    log_dir.mkdir(exist_ok=True)
    
    # 로그 파일 경로
    log_file = log_dir / "server.log"
    
    # 로거 설정
    logger = logging.getLogger('kkobot_lite')
    logger.setLevel(getattr(logging, level.upper()))
    
    # 기존 핸들러 제거
    for handler in logger.handlers[:]:
        logger.removeHandler(handler)
    
    # 포맷터 설정
    formatter = logging.Formatter(
        '[%(asctime)s][%(levelname)s][%(filename)s:%(funcName)s:%(lineno)d] %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # 파일 핸들러
    file_handler = logging.FileHandler(log_file, encoding='utf-8')
    file_handler.setLevel(logging.DEBUG)
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)
    
    # 콘솔 핸들러 (컬러 지원)
    console_handler = logging.StreamHandler()
    console_handler.setLevel(getattr(logging, level.upper()))
    
    # 컬러 포맷터
    color_formatter = ColoredFormatter(
        '\033[32m[%(asctime)s][%(levelname)s][%(filename)s:%(funcName)s:%(lineno)d] %(message)s\033[0m',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    console_handler.setFormatter(color_formatter)
    logger.addHandler(console_handler)
    
    return logger


class ColoredFormatter(logging.Formatter):
    """컬러 포맷터"""
    
    COLORS = {
        'DEBUG': '\033[36m',     # 청록색
        'INFO': '\033[32m',      # 녹색
        'WARNING': '\033[95m',   # 자주색
        'ERROR': '\033[31;1m',   # 빨간색 볼드
        'CRITICAL': '\033[41m',  # 빨간색 배경
    }
    
    def format(self, record):
        color = self.COLORS.get(record.levelname, '\033[0m')
        record.levelname = f"{color}{record.levelname}\033[0m"
        return super().format(record)


# 기본 로거 인스턴스
logger = init_logger('DEBUG')