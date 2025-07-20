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
client_max_message_sizes = {}  # {(bot_name, device_id): max_message_size} - 클라이언트별 메시지 크기 제한
client_approval_status = {}  # {(bot_name, device_id): is_approved} - 클라이언트별 승인 상태 캐시

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

# 메시지 크기 제한
MAX_MESSAGE_SIZE = 1024 * 1024  # 1MB - 원시 TCP 데이터 최대 크기
MAX_KAKAOTALK_MESSAGE_LENGTH = 65000  # 카카오톡 최대 글자수

# 전체 연결 수 제한
MAX_CONCURRENT_CONNECTIONS = 100  # 최대 동시 TCP 연결 수
connection_semaphore = None  # asyncio.Semaphore 인스턴스 (서버 시작시 초기화)

# 세마포어 관리
bot_semaphores = defaultdict(lambda: asyncio.Semaphore(BOT_CONCURRENCY))  # 봇별 세마포어
room_semaphores = {}  # {channel_id: asyncio.Semaphore} - 방별 세마포어
room_semaphore_lock = asyncio.Lock()  # 세마포어 생성/갱신 보호용

# 명령어 정의
COMMAND_PREFIX_MAP = {
    "# echo": {
        "type": "echo",
        "desc": "에코 테스트 (# echo {내용})",
        "admin_only": False,
        "prompt_required": True,
        "parameters": [
            {
                "name": "channel-id",
                "type": "string",
                "desc": "에코를 발송할 채널 ID (기본값: 현재 채널)",
                "optional": True
            },
            {
                "name": "repeat",
                "type": "int",
                "desc": "반복 횟수 (기본값: 1, 최대: 5)",
                "optional": True,
                "default": 1
            }
        ]
    },
    "# client_info": {
        "type": "client_info",
        "desc": "클라이언트 정보 조회",
        "admin_only": True,
        "prompt_required": False,
        "parameters": [
            {
                "name": "bot-name",
                "type": "string",
                "desc": "특정 봇 정보만 조회",
                "optional": True
            }
        ]
    },
    "# IMGEXT": {
        "type": "imgext",
        "desc": "멀티이미지 전송 테스트 (# IMGEXT 1 2 3)",
        "admin_only": False,
        "prompt_required": True,
        "parameters": [
            {
                "name": "media-wait-time",
                "type": "int",
                "desc": "미디어 전송 대기시간 (밀리초, 기본값: 클라이언트 설정)",
                "optional": True
            }
        ]
    },
    "# reload bots-config": {
        "type": "reload_bots_config",
        "desc": "봇 승인 상태 및 설정을 DB에서 다시 로드",
        "admin_only": True,
        "prompt_required": False,
        "parameters": []
    }
}

# 활성화된 명령어 접두어 리스트
ENABLED_PREFIXES = list(COMMAND_PREFIX_MAP.keys())

# 로그 설정
LOG_CONFIG = {
    'ping': {
        'enabled': True,
        'level': 'INFO',  # DEBUG, INFO, WARNING, ERROR
        'detailed': False  # True: 상세 로그, False: 간소화 로그
    },
    'scheduler': {
        'enabled': True,
        'level': 'INFO',
        'detailed': True
    },
    'message': {
        'enabled': True,
        'level': 'DEBUG',
        'detailed': True
    },
    'database': {
        'enabled': True,
        'level': 'INFO',
        'detailed': False
    },
    'client': {
        'enabled': True,
        'level': 'INFO',
        'detailed': True
    }
}