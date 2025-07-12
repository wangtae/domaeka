"""
전역 변수 및 설정 모듈
"""
import asyncio
from collections import defaultdict

# 버전 정보
VERSION = "v1.0.0-lite"

# 데이터베이스 설정
DB_NAME = "test"  # 기본값 (설정 파일의 DBs.test와 일치)

# 전역 변수
db_pool = None  # 데이터베이스 커넥션 풀
clients = {}  # {(ip, port): writer}
server = None  # TCP 서버 인스턴스
shutdown_event = asyncio.Event()  # 종료 이벤트

# 클라이언트 관리
client_connections = defaultdict(dict)  # {bot_name: {(ip, port): connection_info}}

# 로그 레벨
LOG_LEVEL = 'DEBUG'

# 클라이언트 타임아웃 (초)
CLIENT_TIMEOUT = 300

# ping 관련 설정
PING_MESSAGE_INTERVAL = 10  # 몇 개 메시지마다 ping 전송할지
ping_message_counter = 0    # 현재 메시지 카운터