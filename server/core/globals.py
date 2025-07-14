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
clients = {}  # {(bot_name, device_id): writer} - bot_name과 device_id로 클라이언트 추적
server = None  # TCP 서버 인스턴스
shutdown_event = asyncio.Event()  # 종료 이벤트

# 서버 프로세스 관리
process_name = None  # 현재 서버 프로세스 이름

# 클라이언트 관리 (추가 매핑)
clients_by_addr = {}  # {(ip, port): (bot_name, device_id)} - 주소로 클라이언트 찾기용

# 로그 레벨
LOG_LEVEL = 'DEBUG'

# 클라이언트 타임아웃 (초)
CLIENT_TIMEOUT = 300

# ping 관련 설정
PING_MESSAGE_INTERVAL = 10  # 몇 개 메시지마다 ping 전송할지 (기존 방식, 비활성화 예정)
ping_message_counter = 0    # 현재 메시지 카운터
PING_INTERVAL_SECONDS = 30  # 30초마다 ping 전송 (새로운 방식)

# 워커 큐 관련 설정
MAX_CONCURRENT_WORKERS = 31  # 전체 워커 수
message_queue = None  # asyncio.Queue 인스턴스 (서버 시작시 초기화)
workers = []  # 워커 태스크 리스트

# 동시성 제어 설정
BOT_CONCURRENCY = 30  # 봇별 동시 처리 제한
ROOM_CONCURRENCY = 3  # 방별 동시 처리 제한 (기본값)

# 시스템 모니터링 설정
SYSTEM_MONITOR_INTERVAL = 300  # 시스템 모니터링 주기 (초), 기본값: 5분

# 세마포어 관리
bot_semaphores = defaultdict(lambda: asyncio.Semaphore(BOT_CONCURRENCY))  # 봇별 세마포어
room_semaphores = {}  # {channel_id: asyncio.Semaphore} - 방별 세마포어
room_semaphore_lock = asyncio.Lock()  # 세마포어 생성/갱신 보호용