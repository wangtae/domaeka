"""
전역 변수 및 설정 모듈
"""
import asyncio
from collections import defaultdict

# 버전 정보
VERSION = "v1.0.0-lite"

# 데이터베이스 설정
DB_NAME = "kkobot_test"  # 기본값

# 전역 변수
db_pool = None  # 데이터베이스 커넥션 풀
clients = {}  # {(ip, port): writer}
server = None  # TCP 서버 인스턴스
shutdown_event = asyncio.Event()  # 종료 이벤트

# 클라이언트 관리
client_connections = defaultdict(dict)  # {bot_name: {(ip, port): connection_info}}

# 로그 레벨
LOG_LEVEL = 'INFO'