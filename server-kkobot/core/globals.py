import json
import asyncio
from pathlib import Path
import httpx
from core.logger import init_logger
from core.command_loader import load_prefix_map_from_json
from collections import defaultdict, deque

# 현재 파일(globals.py)의 절대 경로를 기준으로 프로젝트 루트 계산
# /home/wangt/cursor/projects/py/kakao-bot/server/core/globals.py
current_file_path = Path(__file__).resolve()
# 프로젝트 루트: /home/wangt/cursor/projects/py/kakao-bot
PROJECT_ROOT = current_file_path.parent.parent.parent

# ===============================
# ✅ JSON 설정 파일 경로 딕셔너리로 통합
# ===============================
JSON_CONFIG_FILES = {
    "schedule_rooms":   PROJECT_ROOT / "server" / "config" / "schedule-rooms.json",
    "help_messages":    PROJECT_ROOT / "server" / "config" / "help-messages.json",
    "profile_analysis": PROJECT_ROOT / "server" / "config" / "profile-analysis.json",
    "auto_replies":     PROJECT_ROOT / "server" / "config" / "auto-replies.json",
    "model_pricing":    PROJECT_ROOT / "server" / "config" / "model-pricing.json",
    "version_history":  PROJECT_ROOT / "server" / "config" / "history.json",
    "auth_config":      PROJECT_ROOT / "server" / "config" / "auth_config.json"
}

# ===============================
# ✅ 자동 생성 JSON 파일 경로 딕셔너리
# ===============================
GENERATED_JSON_FILES = {
    "bot_settings_dir": PROJECT_ROOT / "server" / "config" / "bots-settings",
}

# 기존 AUTO_REPLIES_FILE 참조를 JSON_CONFIG_FILES["auto_replies"]로 변경
AUTO_REPLIES_FILE = JSON_CONFIG_FILES["auto_replies"]

try:
    with AUTO_REPLIES_FILE.open("r", encoding="utf-8") as f:
        auto_replies_data = json.load(f)
except Exception as e:
    auto_replies_data = {}
    print(f"[ERROR] auto_replies_data 로드 실패: {e}")

AUTH_CONFIG_FILE = JSON_CONFIG_FILES["auth_config"]
AUTH_CONFIG_PATH = str(AUTH_CONFIG_FILE)

# ===============================
# ✅ 기본 환경 설정 및 버전 관리
# =============================== 
LOG_LEVEL = 'DEBUG'

# ✅ 데이터베이스 이름 (실행 시 --mode 인자에 따라 동적으로 설정)
DB_NAME = "kkobot_test"  # 기본값

TODO_ITEMS = [
    "LLM 공통 system_prompt 정의 기능 추가",
    "LLM 크래딧 통계 작성(유저별 건당 월당 등)",
    "오목 게임: 핸드쉐이킹 구현, 환경설정 필드 수정, 게임룰 정확 반영, AI 훈련",
    "유저별 추천 종목 & 이유 등록",
    "유저별 대화요약?",
    "주간 채팅 1위 대화의 주간 요약",
    "로아(오픈프로필) 개인톡 기능들 사용 가능한 부분 확인할것 (홍보용 활용 가능)",
    "> 질문에서 최근 대화 맥락 전달?",
    "유툽 분석 자막 없는경우 경고 메시지",
    "방별 뉴스 발송 주기 적용",
    "스케줄 같은 시간 발송 문제? (스케줄 큐에 넣어서 등록 및 완료후 다음것 처리되도록 처리?)",
    "LLM 응답 반드시 해야하는 것과 아닌것 구분(필수는 별도 큐로 네 그관리?)",
    "일일수학 문제 풀기/채점/기간별 통계?(영어도 응용가능)",
    "미디어 전송 기능 추가",
    "증권사 API 등 투자 관련 기능 폴더 구조",
    "명령어 prefix 탐지 기능 개선 필요",
    "help 자동 설정 + 커스터마이징 방식으로 개선 (prefix 개선 부터 해야함)",
    "각종 환경설정 JSON 또는 DB화",
    "뉴스 검색기능, 뉴스 선택",
    "웹 크롤링 기능을 활용한 실시간 인터넷 검색 요약",
    "국장 마감 브리핑 (지수, 상승종목, 하락종목 등)",
    "오늘의 유머, 오늘의 성경 유머, 성경퀴즈",
    "자동 스케줄링 고도화(세션 로스 감안)",
    "서버 리소스 모니터링 개선하기",
    "클라이언트 최적화(안정화)",
    "nohup bash -c 'while true; do python3 kkobot.py; sleep 3; done' > log.out 2>&1 &"
]

# VERSION 자동 설정: history.json에서 가장 최신 버전을 가져오기
try:
    with JSON_CONFIG_FILES["version_history"].open("r", encoding="utf-8") as f:
        _version_data = json.load(f)
        VERSION = sorted(_version_data.keys(), reverse=True)[0]
except Exception as e:
    VERSION = "v0.0.0"
    print(f"[ERROR] VERSION 자동 설정 실패: {e}")
    

# ====== 메시지 동시성/워커 설정 ======
BOT_CONCURRENCY = 30  # 봇별 동시 처리 제한
ROOM_CONCURRENCY = 3  # 방별 동시 처리 제한 
MAX_CONCURRENT_WORKERS = 31  # 전체 워커 수

# ====== 시스템 모니터링 설정 ======
SYSTEM_MONITOR_INTERVAL = 300  # 시스템 모니터링 주기 (초), 기본값: 5분

# ====== Writer 동시성 보호 ======
writer_locks = {}  # {writer: asyncio.Lock} - Writer별 Lock 관리
# ====== (이하 기존 코드) ======

no_reply_count = {}  # {bot_name: count}
PING_TRIGGER_COUNT = 10  # [DEPRECATED] 메시지 기반 ping 트리거 (사용 안 함)
PING_INTERVAL = 30  # 클라이언트별 ping 전송 간격 (초)

NEWS_DELIVERY_INTERVAL_MINUTES = 240  # 국장 장마감 브리핑
NEWS_DELIVERY_LOOKBACK_MINUTES = 0 # 480  # 발송 가능한 최근시간

# 대화 참여 모듈: 메시지 시간 범위 (초 단위) - 기본값 10분
CONVERSATION_JOIN_HISTORY_SECONDS = 600  
# 채널별 대화참여 금지 종료 시간 저장
conversation_block_until = defaultdict(lambda: 0)

# 세션 관련 설정
SESSION_VERSION = '1.0.0'
SESSION_DEFAULT_TIMEOUT_MINUTES = 10
SESSION_DEFAULT_EXTENSION_MINUTES = 5
SESSION_MAX_EXTENSIONS = 2
SESSION_SCHEDULER_CHECK_INTERVAL = 30  # 초
SESSION_CLEANUP_INTERVAL = 60  # 초

ADMIN_USERS = [
    {
        "room": "LOA.i",
        "channel_id": "18446369739418674",
        "user_hash": "ad5f8a72cb5ce9098d2e86034435b36c88938328e7c3ae55d659dd8bb2dbf3ac"
    }
]

# 에러 알림 설정 (에러 알림 시스템 자체에 문제가 있음 <- 무한루프, 메신저봇 죽는 문제 등)
ERROR_NOTIFICATION = {
    "enabled": False,
    "channel_id": "18446369739418674",
    "log_levels": ["CRITICAL"],  # 심각한 오류만 알림 (ERROR에서 CRITICAL로 변경)
    "include_warning": False,
    "max_per_minute": 2  # 알림 수 제한 (5에서 2로 변경)
}

LLM_DEFAULT_SYSTEM_PROMPT = "당신은 친절한 AI 도우미입니다. 답변을 줄 때 다음 규칙을 지켜주세요: 1. 공손하고 친절한 말투로 답변해 주세요. 2. 마크다운문자(**, ## 등)는 표시하지 마세요. 3. 필요한 경우 줄바꿈과 이모티콘 등을 활용해 가독성을 높여주세요. 4. 정확한 정보를 제공하고, 확실하지 않은 경우에는 솔직하게 알려주세요."

#UNREGISTERED_ROOM_MESSAGES = [
    #"아직 미등록 방입니다. 봇 관리자에게 문의해 주세요. (https://open.kakao.com/me/3773)",
    #"이 방은 아직 등록되지 않았습니다. 관리자에게 확인해 주세요. (https://open.kakao.com/me/3773)",
    #"죄송합니다, 이 방에서는 봇 사용이 제한되어 있습니다. 자세한 내용은 관리자에게 문의하해 주세요. (https://open.kakao.com/me/3773)"
#]
UNREGISTERED_ROOM_MESSAGES = []


fallback_llms = [
    {
        "name": "grok",
        "model": "grok-3-latest",
        "timeout": 30,
        "retry": 0,
        "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
    },
    {
        "name": "gemini",
        "model": "gemini-1.5-pro",
        "timeout": 30,
        "retry": 0,
        "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
    },
    {
        "name": "deepseek",
        "model": "deepseek-chat",
        "timeout": 30,
        "retry": 0,
        "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
    },
    {
        "name": "openai",
        "model": "gpt-4o",
        "timeout": 30,
        "retry": 0,
        "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
    },
    {
        "name": "perplexity",
        "model": "sonar-pro",
        "timeout": 30,
        "retry": 0,
        "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
    }
]

LLMs_modules = {
    "openai": {
        "gpt-4o": "OpenAI GPT-4o",  # 입력 $2.50 / 출력 $10.00 per 1M tokens
        "gpt-4o-mini": "OpenAI GPT-4o Mini",  # 입력 $0.15 / 출력 $0.60 per 1M tokens
        "gpt-4.1": "OpenAI GPT-4.1",  # 입력 $2.00 / 출력 $8.00 per 1M tokens
        "gpt-4.1-mini": "OpenAI GPT-4.1 Mini",  # 입력 $0.40 / 출력 $1.60 per 1M tokens
        "gpt-4.1-nano": "OpenAI GPT-4.1 Nano",  # 입력 $0.10 / 출력 $0.40 per 1M tokens
        "gpt-4-turbo": "OpenAI GPT-4 Turbo",  # 입력 $10.00 / 출력 $30.00 per 1M tokens
        "gpt-4": "OpenAI GPT-4",  # 입력 $30.00 / 출력 $60.00 per 1M tokens
        "gpt-3.5-turbo-16k": "OpenAI GPT-3.5 Turbo 16K",  # 입력 $0.50 / 출력 $1.50 per 1M tokens
        "gpt-3.5-turbo": "OpenAI GPT-3.5 Turbo",  # 입력 $0.50 / 출력 $1.50 per 1M tokens
    },
    "gemini": {
        "gemini-2.5-pro-preview-05-06": "Gemini 2.5 Pro (Preview)",  # 입력 $1.25 (<=200k) / $2.50 (>200k), 출력 $10.00 (<=200k) / $15.00 (>200k) per 1M tokens
        "gemini-2.5-flash-preview-04-17": "Gemini 2.5 Flash (Preview)",  # 입력 $0.15 (text/image/video) / $1.00 (audio), 출력 $0.60 (non-thinking) / $3.50 (thinking) per 1M tokens
        "gemini-2.0-flash": "Gemini 2.0 Flash",  # 입력 $0.10 (text/image/video) / $0.70 (audio), 출력 $0.40 per 1M tokens
        "gemini-1.5-pro": "Gemini 1.5 Pro",  # 입력 $1.25 (<=128k) / $2.50 (>128k), 출력 $5.00 (<=128k) / $10.00 (>128k) per 1M tokens
        "gemini-1.5-flash": "Gemini 1.5 Flash",  # 입력 $0.075 (<=128k) / $0.15 (>128k), 출력 $0.30 (<=128k) / $0.60 (>128k) per 1M tokens
    },
    "grok": {
        "grok-3-latest": "Grok 3 Latest"
    },
    "deepseek": {
        "deepseek-chat": "DeepSeek Chat"
    },
    "perplexity": {
        "sonar-pro": "Perplexity Sonar Pro"
    },
    "local": {
        "local-model": "Local Custom Model"
    }
}



# 🔵 모델 이름으로 provider 이름 찾기 함수
def find_provider_by_model(model_name: str):
    for provider, models in LLMs_modules.items():
        if model_name in models:
            return provider
    return None


TTS_DEFAULT_CONFIG = {
    "language": "auto",
    "gender": "F",
    "voice": "auto",
    "speaking_rate": 1.0,
    "intro": [
        "🧏 음성으로 녹음해 보았어요!",
        "🔔 내용을 녹음해 보았어요.",
        "🎙️ 직접 읽어봤어요!",
        "🎧 이 내용을 들려드릴게요.",
        "📋 음성으로 정리해 드릴게요!",
        "🎤 제가 읽어볼게요~",
        "🎙 짧게 녹음해봤어요!️",
        "👂 들어보는 게 더 편하죠?",
        "🗣️ 말로 전해드릴게요!"
    ]
}

# ===============================
# ✅ 로거 초기화
# ===============================
logger = init_logger(LOG_LEVEL)

# ===============================
# ✅ 외부 서비스 URL 및 주요 상수
# ===============================

from pathlib import Path

# ===============================
# ✅ JSON 데이터 로딩 결과 저장
# ===============================
schedule_rooms = {}
message_filters = {}
help_messages = {}
profile_analysis = {}
auto_replies_data = {}
auto_replies = {}  # 후처리된 자동응답 데이터 저장(주석 삭제하지 마세요)
channel_id_to_room = {}

# 채널별 메시지 히스토리 저장
# {channel_id: deque([message1, message2, ...]), ...}
message_history = defaultdict(lambda: deque(maxlen=100))

# 채널별 마지막 대화 참여 시간
# {channel_id: timestamp, ...}
last_join_time = {}

# ✅ 스케줄 데이터 동기화와 리로드 알림을 위한 이벤트 추가
schedule_reload_event = asyncio.Event()

# JSON 데이터 변수 매핑
JSON_DATA_VARS = {
    "schedule_rooms": "schedule_rooms",
    "help_messages": "help_messages",
    "profile_analysis": "profile_analysis",
    "auto_replies": "auto_replies",
    "model_pricing": "model_pricing",
    "version_history": "version_history",
    "auth_config": "auth_config"   # ← 이 줄 추가!
}

# JSON 파일 로드
for key, file_path in JSON_CONFIG_FILES.items():
    try:
        with file_path.open("r", encoding="utf-8") as f:
            globals()[JSON_DATA_VARS[key]] = json.load(f)
        logger.info(f"[CONFIG] {key} 로드 완료")
    except Exception as e:
        logger.error(f"[CONFIG] {key} 로드 실패: {e}")
        globals()[JSON_DATA_VARS[key]] = {}

# ===============================
# ✅ 글로벌 상태 관리 객체
# ===============================
db_pool = None
http_client = httpx.AsyncClient(timeout=30)

clients = {}  # {(bot_name, device_id): {addr: writer}} - bot_name과 device_id로 클라이언트 추적
clients_by_addr = {}  # {addr: (bot_name, device_id)} - 주소로 클라이언트 찾기용
last_sent = {}
user_map = {}

# ✅ 스케줄 메시지와 schedule_rooms를 동일한 변수로 통일
scheduled_messages = schedule_rooms

# ===============================
# ✅ 접두어 및 명령어 정의
# ===============================

json_command_data = {
    "> 나는 누구인가요?": {
        "type": "profile_analyze",
        "desc": "내 성격/성향 분석",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(프로필분석)",
        "cache_enabled": False,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 1,  # 캐싱 유효 시간(분) (기본값: 1)
        "parameters": {
            "model": {"description": "LLM 모델명", "required_role": "owner"},
            "minutes": {"description": "대상 기간(분)", "required_role": "user"},
        },
        "keywords": ["나", "누구", "분석"],
        "keyword_aliases": {
            "나": ["내", "나의", "나를", "내가"],
            "누구": ["어떤 사람", "사람"],
            "분석": ["성격", "성향", "알려줘", "알려주세요"]
        },
        "optional_words": ["은", "는", "이", "가", "란", "라고", "?", "에", "대해"],
        "aliases": []
    },
    "> 나의 mbti는?": {
        "type": "mbti_analyze",
        "desc": "MBTI 성격 유형 분석",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(프로필분석)",
        "cache_enabled": False,
        "cache_minutes": 60,
        "keywords": ["나", "mbti"],              
        "keyword_aliases": {                     
            "나": ["내", "나의", "내가"],
            "mbti": ["엠비티아이", "MBTI", "성격유형"]
        },
        "optional_words": ["의", "는", "?", "은", "를", "이", "분석"],  
        "aliases": []
    },

    "> 나의 애니어그램은?": {
        "type": "enneagram_analyze",
        "desc": "애니어그램 성격 유형 분석",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(프로필분석)",
        "cache_enabled": False,
        "cache_minutes": 60,
        "keywords": ["나", "애니어그램"],
        "keyword_aliases": {
            "나": ["내", "나의"],
            "애니어그램": ["에니어그램", "enneagram", "애니"]
        },
        "optional_words": ["은", "는", "?", "분석"],
        "aliases": []
    },
    "[나를 멘션]": {
        "type": "mention",
        "desc": "[나를 멘션] @{대상} 질문",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(프로필분석)",
        "aliases": []
    },
    ">>>>": {
        "type": "openai",
        "desc": "> {질문 내용}",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },

    "><": {
        "type": "perplexity",
        "desc": "Perplexity AI를 통한 질의응답",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "관리"
    },

    "&": {
        "type": "gemini",
        "desc": "> {질문 내용}",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },

    ">>": {
        "type": "deepseek",
        "desc": "> {질문 내용}",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },

    ">": {
        "type": "llm_fallback",
        "desc": "여러 AI 모델을 자동으로 시도 (DeepSeek → Gemini → OpenAI)",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },

    "! 전체": {
        "type": "bible_search_all",
        "desc": "! 전체 \"{키워드}\" and|or \"{키워드}\" with \"{하이라이트(옵션)}\"",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "성경",
        "keywords": ["전체"],
        "keyword_aliases": {
            "전체": ["성경"]
        },
        "optional_words": [],
        "aliases": []
    },
    "! 구약": {
        "type": "bible_search_old",
        "desc": "! 구약 \"{키워드}\" and|or \"{키워드}\" with \"{하이라이트(옵션)}\"",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "성경",
        "keywords": ["구약"],
        "keyword_aliases": {
            "구약": ["구약성경", "old"]
        },
        "optional_words": [],
        "aliases": []
    },
    "! 신약": {
        "type": "bible_search_new",
        "desc": "! 신약 \"{키워드}\" and|or \"{키워드}\" with \"{하이라이트(옵션)}\"",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "성경",
        "keywords": ["신약"],
        "keyword_aliases": {
            "신약": ["신약성경", "new"]
        },
        "optional_words": [],
        "aliases": []
    },
    "! 랜덤": {
        "type": "bible_random",
        "desc": "! 랜덤 : 성경 구절을 랜덤으로 1개 검색",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "성경",
        "keywords": ["랜덤"],
        "keyword_aliases": {
            "랜덤": ["random", "foseja"]
        },
        "optional_words": [],
        "aliases": []
    },
    "!": {
        "type": "bible",
        "desc": "! 창 1:1, ! 창 1:1~3",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "성경",
        "cache_enabled": False,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 1,  # 캐싱 유효 시간(분) (기본값: 1)
        "aliases": []
    },
    "# LLM models": {
        "type": "show_llm_models",
        "desc": "텍스트를 음성으로 변환합니다.",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": True,
        "category": "관리",
        "aliases": []
    },
    "# help": {
        "type": "help",
        "desc": "# help|사용법 : 사용법 안내",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "cache_enabled": False,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 5,  # 캐싱 유효 시간(분) (기본값: 1)
        "category": "일반",
        "keywords": ["help", "사용법"],
        "keyword_aliases": {
            "help": ["사용법", "도움말", "사용방법", "매뉴얼"]
        },
        "optional_words": [],
        "aliases": []
    },
    "# echo": {
        "type": "echo",
        "desc": "에코 테스트",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "aliases": []
    },
    "# reload json": {
        "type": "reload-all-json",
        "desc": "JSON 파일들 리로드",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": True,
        "category": "관리",
        "aliases": []
    },
    "# GROK": {
        "type": "grok",
        "desc": "시스템에서 사용하는 GROK 명령입니다.",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },
    "# OPENAI": {
        "type": "openai",
        "desc": "시스템에서 사용하는 CHATGPT 명령입니다.",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },
    "# GEMINI": {
        "type": "gemini",
        "desc": "시스템에서 사용하는 gemini 명령입니다.",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },
    "# DEEPSEEK": {
        "type": "deepseek",
        "desc": "시스템에서 사용하는 CHATGPT 명령입니다.",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "AI(일반)",
        "aliases": []
    },
    "# todo list": {
        "type": "todo_list",
        "desc": "할일 목록",
        "help": False,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": True,
        "category": "관리",
        "enable-archiving": False,
        "aliases": []
    },
    "# [오늘의 성경묵상]": {
        "type": "today_bible2",
        "desc": "오늘의 성경 구절과 묵상",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "cache_enabled": True,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 60 * 24,  # 캐싱 유효 시간(분) (기본값: 1)
        "category": "성경",
        "enable-archiving": False,
        "keywords": ["오늘", "성경", "묵상"],
        "keyword_aliases": {
            "오늘": ["오늘의", "today"],
            "성경": ["성경말씀", "성경구절", "말씀", "구절"],
            "묵상": ["묵상", "meditation"]
        },
        "optional_words": ["의", "[", "]"],
        "aliases": []
    },
    "# [매일성경]": {
        "type": "today_bible",
        "desc": "오늘의 성경 구절과 묵상",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "cache_enabled": True,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 60 * 24,  # 캐싱 유효 시간(분) (기본값: 1)
        "category": "성경",
        "enable-archiving": False,
        "keywords": ["매일", "성경"],
        "keyword_aliases": {
            "매일": ["매일의", "daily"],
            "성경": ["성경말씀", "성경구절", "말씀", "구절"]
        },
        "optional_words": ["[", "]"],
        "aliases": []
    },
    "# [자동응답] 식사메뉴 추천": {
        "type": "recomment_lunch_menu",
        "desc": "식사 메뉴 추천",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": []
    },
    "# [자동응답] 로또번호 추천": {
        "type": "lotto",
        "desc": "로또 번호 추천",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": []
    },
    "# [자동응답] 오늘날씨": {
        "type": "weather",
        "desc": "오늘의 전국 날씨",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "날씨",
        "enable-archiving": False,
        "keywords": ["날씨"],
        "keyword_aliases": {
            "날씨": ["오늘날씨", "전국날씨", "weather"]
        },
        "optional_words": ["오늘", "전국", "[", "]", "자동응답"],
        "aliases": []
    },
    "# 날씨": {
        "type": "naver_weather",
        "desc": "지역의 날씨 정보를 검색합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "날씨",
        "enable-archiving": False,
        "keywords": ["날씨"],
        "keyword_aliases": {
            "날씨": ["weather", "기상", "기후"]
        },
        "optional_words": [],
        "aliases": []
    },
    "# [오늘의 투자격언]": {
        "type": "today_proverb",
        "desc": "오늘의 투자격언 (카테고리 지정 가능)",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,  # False로 변경하여 프롬프트 없이도 동작하도록
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": []
    },
    "# [블룸버그 오늘의 5가지 이슈]": {
        "type": "bloomberg_news",
        "desc": "블룸버그 최신 금융 이슈 5가지를 보여줍니다",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "cache_enabled": True,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 60 * 24,  # 캐싱 유효 시간(분) (기본값: 1)
        "category": "뉴스",
        "enable-archiving": False,
        "aliases": []
    },
    "# [환율]": {
        "type": "exchange_rate",
        "desc": "환율 정보 조회",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    "# 주간채팅순위!": {
        "type": "chat_rank_week",
        "category": "일반",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    "# 주간채팅순위": {
        "type": "chat_rank_week_all",
        "category": "일반",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    "# 채팅순위!": {
        "type": "chat_rank",
        "category": "일반",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    "# 채팅순위": {
        "type": "chat_rank_all",
        "category": "일반",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": True,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    "# [봇 자동응답]": {
        "type": "bot_auto_reply",
        "desc": "로아를 언급한 일반 대화에 응답",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "admin_only": False,
        "category": "봇 자동응답",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },
    
    "# IMGEXT": {
        "type": "multi_image_generator",
        "desc": "텍스트를 단어별로 이미지로 변환하여 멀티 이미지 전송",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },

    "# IMG": {
        "type": "image_generator",
        "desc": "텍스트를 이미지로 변환하여 전송",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },

    "# IMGURL": {
        "type": "image_url_generator",
        "desc": "텍스트를 이미지로 변환하여 URL로 전송",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },

    "# 오늘의 대화 요약": {
        "type": "today_conversation_summary",
        "desc": "오늘(0시부터 현재까지)의 대화를 요약합니다",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },

    "# 오늘의 대화 요약!": {
        "type": "recent_conversation_summary_meaningful",
        "desc": "최근 N분간의 의미 있는 메시지만 포함하여 대화를 요약합니다 (기본값: 60분)",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "keywords": [],
        "keyword_aliases": {},
        "optional_words": [],
        "aliases": []
    },

    "# 최근 대화 요약": {
        "type": "recent_conversation_summary",
        "desc": "최근 N분간의 대화를 요약합니다 (기본값: 60분)",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "aliases": ["# 최근대화요약", "# 최근 대화 요약"]
    },

    "# 최근 대화 요약!": {
        "type": "recent_conversation_summary_meaningful",
        "desc": "최근 N분간의 의미 있는 메시지만 포함하여 대화를 요약합니다 (기본값: 60분)",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "aliases": ["# 최근대화요약!", "# 최근 대화 요약!"]
    },

    "# 유저 대화 요약": {
        "type": "user_conversation_summary",
        "desc": "특정 유저의 최근 N일간 대화를 요약합니다",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "user-name": {
                "description": "요약할 유저의 이름",
                "required_role": "user",
                "type": "string",
                "required": True
            },
            "recent-days": {
                "description": "요약할 최근 일수 (1=오늘, 2=최근 2일)",
                "required_role": "user",
                "type": "int",
                "required": True,
                "min": 1,
                "max": 30
            },
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "aliases": ["#유저대화요약", "# 유저대화요약", "# 사용자 대화 요약", "# 사용자대화요약"]
    },

    "# 유저 대화 요약!": {
        "type": "user_conversation_summary_meaningful",
        "desc": "특정 유저의 최근 N일간 의미 있는 메시지만 포함하여 대화를 요약합니다",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": True,
        "parameters": {
            "user-name": {
                "description": "요약할 유저의 이름",
                "required_role": "user",
                "type": "string",
                "required": True
            },
            "recent-days": {
                "description": "요약할 최근 일수 (1=오늘, 2=최근 2일)",
                "required_role": "user",
                "type": "int",
                "required": True,
                "min": 1,
                "max": 30
            },
            "channel-id": {
                "description": "채널아이디(톡방)에 대한 대화 요약",
                "required_role": "admin",
                "type": "string"
            },
        },
        "aliases": ["# 유저대화요약!", "# 사용자 대화 요약!", "# 사용자대화요약!"]
    },
    "# 대화참여중지": {
        "type": "block_conversation_join",
        "desc": "# 대화참여중지: 봇의 대화 참여를 일시적으로 차단합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": ["# 대화참여금지", "# 대화참여 금지" "# 대화참여 중지", "# 대화 차단"]
    },
    "# 채팅시작": {
        "type": "start_private_chat",
        "desc": "1:1 개인 채팅 세션을 시작합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "채팅",
        "enable-archiving": False,
        "aliases": ["#개인채팅", "#채팅세션"]
    },
    "# 그룹채팅시작": {
        "type": "start_group_chat",
        "desc": "그룹 채팅 세션을 시작합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "채팅",
        "enable-archiving": False,
        "aliases": ["#그룹채팅", "#단체채팅"]
    },
    "# 시간연장": {
        "type": "extend_chat",
        "desc": "현재 진행 중인 채팅 세션의 시간을 연장합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "채팅",
        "enable-archiving": False,
        "aliases": ["#연장"]
    },
    "# 채팅종료": {
        "type": "end_chat",
        "desc": "현재 진행 중인 채팅 세션을 종료합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "채팅",
        "aliases": ["#종료"]
    },
    "# youtube": {
        "type": "youtube_summary",
        "desc": "유튜브 동영상 URL을 요약합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "cache_enabled": True,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 60 * 24 * 30,  # 캐싱 유효 시간(분) (기본값: 1)
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": False,
        "aliases": ["# 유튜브", "# YOUTUBE", "# 유튜브요약"]
    },
    "# web": {
        "type": "webpage_summary",
        "desc": "웹페이지 URL을 요약합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "cache_enabled": True,  # 캐싱 사용 여부 (기본값: False)
        "cache_minutes": 60 * 24 * 30,  # 캐싱 유효 시간(분) (기본값: 1)
        "admin_only": False,
        "category": "유틸리티",
        "enable-archiving": False,
        "aliases": ["# WEB", "# 페이지요약", "# URL요약"]
    },
    "# 한국증시 장마감": {
        "type": "korea_market_briefing",  # ✅ 실제 처리용 type
        "desc": "LS API 기반 국내 주식시장 장마감 브리핑",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": ["# 장마감", "# 코스피", "# 한국장"]
    },
    "# 방장전용명령어": {
        "type": "some_command_type",
        "desc": "방장 전용 명령어 설명",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": True,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,  # 관리자 전용 아님
        "room_owner_only": True,  # 방장 전용으로 설정
        "category": "관리",
        "enable-archiving": False,
    },
    
    "# tts": {
        "type": "tts",
        "desc": "텍스트를 음성으로 변환합니다.",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": ["# 음성변환", "# 말해줘"]
    },
    "# tts 한국어": {
        "type": "tts_ko",
        "desc": "텍스트를 한국어 음성으로 변환합니다.",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": ["# 한국어tts", "# 한국어로말해줘"]
    },
    "# tts 영어": {
        "type": "tts_en",
        "desc": "텍스트를 영어 음성으로 변환합니다.",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "일반",
        "enable-archiving": False,
        "aliases": ["# 영어tts", "# 영어로말해줘"]
    },

    "# 오목": {
        "type": "omok_start",
        "category": "게임(오목)",
        "prompt_required": False,
        "enable-archiving": False,
        "parameters": {
            "mode": {
                "description": "AI 모드 (기본/고급)",
                "required_role": "user",
                "choices": ["기본", "고급"],
                "default": "기본"
            },
            "ai-level": {
                "description": "AI 레벨 (1-10)",
                "required_role": "user",
                "type": "int",
                "min": 1,
                "max": 10,
                "default": 5
            },
            "debug": {
                "description": "디버그 모드 활성화 여부",
                "required_role": "admin",
                "type": "bool",
                "default": False
            },
            "board-style": {
                "description": "바둑판 스타일 (classic/wood/modern)",
                "required_role": "user",
                "choices": ["classic", "wood", "modern"],
                "default": "wood"
            },
            "board-size": {
                "description": "오목판 크기 (7, 9, 11, 13, 15, 17, 19 중 하나)",
                "required_role": "user",
                "type": "int",
                "choices": [7, 9, 11, 13, 15, 17, 19],
                "default": 15
            },
            "rule-set": {
                "description": "오목 룰셋 (예: standard, freestyle, renju 등)",
                "required_role": "user",
                "type": "str",
                "choices": ["standard", "pro", "longpro", "renju"],
                "default": "standard"
            },
            "swap-rule": {
                "description": "스왑룰 (none, swap1, swap2)",
                "required_role": "user",
                "type": "str",
                "choices": ["none", "swap1", "swap2"],
                "default": "none"
            },
            "ban-spot": {
                "description": "금수 착수 불가 영역 시각화 (True/False)",
                "required_role": "user",
                "type": "bool",
                "default": False
            },
            "overline_black": {
                "description": "장목(6목 이상) - 흑 처리 방식 (win/invalid/forbidden)",
                "required_role": "user",
                "type": "str"
            },
            "overline_white": {
                "description": "장목(6목 이상) - 백 처리 방식 (win/invalid/forbidden)",
                "required_role": "user",
                "type": "str"
            },
            "double-three_black": {
                "description": "삼삼 금수 - 흑 (true/false)",
                "required_role": "user",
                "type": "bool"
            },
            "double-three_white": {
                "description": "삼삼 금수 - 백 (true/false)",
                "required_role": "user",
                "type": "bool"
            },
            "double-four_black": {
                "description": "사사 금수 - 흑 (true/false)",
                "required_role": "user",
                "type": "bool"
            },
            "double-four_white": {
                "description": "사사 금수 - 백 (true/false)",
                "required_role": "user",
                "type": "bool"
            },
            "forbidden-action": {
                "description": "금수 착수 처리 방식 (block/lose/warning)",
                "required_role": "user",
                "type": "str"
            },
            "player1-stone": {
                "description": "player1이 사용할 돌 색(black/white)",
                "required_role": "user",
                "type": "str",
                "choices": ["black", "white"]
            }
        },
        "desc": "AI와 오목 대국을 시작합니다.\n- 기본 모드: 일반적인 AI 알고리즘 사용\n- 고급 모드: AI가 더 깊이 있게 생각 🧠\n- 스타일: classic(기본), wood(나무), modern(모던)",
        "help": True,
        "aliases": ["#오목", "# omok", "#omok"]
    },
    "# 오목 현황": {
        "type": "omok_status",
        "category": "게임(오목)",
        "prompt_required": False,
        "enable-archiving": False,
        "desc": "진행 중인 오목 게임을 중단합니다.",
        "help": True,
        "aliases": ["# 오목 보드", "# 오목 상태"]
    },

    "# history": {
        "type": "version_history",
        "desc": "버전 히스토리를 출력합니다",
        "help": True,
        "use_waiting_message": False,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": False,
        "category": "관리",
        "enable-archiving": False,
        "aliases": ["# 버전", "# 히스토리", "# 버전기록"]
    },
    "#로스": {
        "type": "ross_ai",
        "desc": "로스 AI 법률 검색",
        "help": True,
        "prompt_required": True,
        "category": "법률",
        "enable-archiving": False,
        "aliases": ["#ross", "# 로스", "#법령", "# 법령", "# 법률", "#법률"]
    },
    "#이마젠": {
        "type": "imagen_generate",
        "desc": "Imagen 3 AI 이미지 생성",
        "help": True,
        "use_waiting_message": True,
        "prompt_required": True,
        "category": "유틸리티",
        "enable-archiving": False,
        "aliases": ["#이미지", "#이미지생성", "#imgen", "#imagen", "#그림", "#그림생성", "# 드로잉", "#드로잉"]
    },
    "# [기간별 유저대화 요약 발송]": {
        "type": "periodic_user_summary_dispatch",
        "desc": "설정된 기간 및 방식에 따라 유저 대화 요약을 발송합니다.",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": False,
        "not_meaningful_message": False,
        "admin_only": True,
        "schedule_only": True,
        "enable-archiving": False,
        "category": "관리",
        "aliases": [],
        "parameters": [
            {
                "name": "channel-id",
                "type": "string",
                "desc": "대화 요약을 발송할 채널 ID (기본값: 현재 채널)",
                "optional": True
            }
        ]
    },
    "# [기간별 유저대화 요약 발송!]": {
        "type": "periodic_user_summary_dispatch",
        "desc": "설정된 기간 및 방식에 따라 유저 대화 요약을 발송합니다.",
        "help": False,
        "use_waiting_message": True,
        "prompt_required": False,
        "always_meaningful": True,
        "not_meaningful_message": False,
        "admin_only": True,
        "schedule_only": True,
        "enable-archiving": False,
        "category": "관리",
        "aliases": [],
        "parameters": [
            {
                "name": "channel-id",
                "type": "string",
                "desc": "대화 요약을 발송할 채널 ID (기본값: 현재 채널)",
                "optional": True
            }
        ]
    },
    # -------------------- 관리용 --------------------

    "# reload env": {
        "type": "reload_env",
        "description": "DB에서 환경설정 (schedule-rooms-from-db.json)을 재생성하고 메모리에 로드합니다.",
        "admin_only": True,
        "show_in_help": False,
        "enable-archiving": False,
        "category": "관리",
        "aliases": []
    },
    "# reload bot settings": {
        "type": "reload_bot_settings",
        "description": "특정 봇의 설정 파일을 DB에서 재생성합니다. 봇 이름을 명시하지 않으면 현재 봇의 설정을 재생성합니다.",
        "admin_only": True,
        "show_in_help": False,
        "enable-archiving": False,
        "category": "관리",
        "prompt_required": False,
        "aliases": ["# 봇설정리로딩", "# 봇설정재생성"]
    }
}

# 아래 json_command_data는 위 JSON dict (PREFIX_MAP 정의된 형태)라고 가정합니다.
PREFIX_MAP, ENABLED_PREFIXES = load_prefix_map_from_json(json_command_data)

# TTS 대기 메시지 추가
TTS_WAITING_MESSAGES = {
    # 기존 대기 메시지...

    # TTS 대기 메시지 추가
    "tts": "🔊 음성 파일을 생성하고 있습니다. 잠시만 기다려주세요...",
    "tts_ko": "🔊 한국어 음성 파일을 생성하고 있습니다. 잠시만 기다려주세요...",
    "tts_en": "🔊 영어 음성 파일을 생성하고 있습니다. 잠시만 기다려주세요..."
}

# ===============================
# ✅ 기본 대기 메시지 텍스트
# ===============================
WAITING_MESSAGES = [
    "조금만 기다려주세요! 열심히 생각 중이에요 🤔",
    "잠시만요! 최선을 다해 답변하고 있어요 😊",
    "생각이 깊어지는 중... 잠시만 기다려주세요 🧠",
    "답변을 생각 중입니다! 곧 알려드릴게요 ⏳",
    "답변을 정성스럽게 준비 중입니다. 잠시만 기다려주세요 📝",
    "머리를 쥐어짜는 중이에요... 💡 조금만 기다려주세요!",
    "살짝 심호흡하고 다시 집중합니다! 🧘‍♂️",
    "답변의 신이 강림 중입니다... 잠시만요! 🙏",
    "방금 영감을 받았어요! 잠깐만요, 적어볼게요 ✍️",
    "정보의 미로를 탐험 중입니다... 출구를 찾으면 바로 알려드릴게요 🧭",
    "비밀의 문서를 해독하는 중입니다... 🗝️",
    "조금만 기다려주시면 멋진 답이 완성됩니다! 🎨",
    "생각의 회로를 가동 중... 치이익⚙️ 치이익⚙️",
    "망각의 숲에서 기억을 찾아오는 중입니다 🌳",
    "뇌의 구석구석을 뒤지고 있어요... 🧐",
    "답변이 살짝 길어지고 있어요! 조금만 더요~ 🏃‍♂️💨",
    "좋은 답을 찾기 위해 달빛 회의 중입니다 🌙",
    "행복한 답변을 만들어가는 중입니다! 🍀",
    "천재적인 아이디어가 떠오르는 중이에요! 🚀",
    "신중하게 단어를 고르는 중이에요. 조금만 기다려주세요! 🖋️",
    "답변을 손질하고 다듬는 중이에요. 거의 다 됐어요! ✂️",
    "별을 바라보며 영감을 얻는 중입니다 🌟",
    "아무리 생각해도 멋진 답이 될 것 같아요! 조금만 더! 😎",
    "살짝 통찰력이 부족해서, 다시 충전 중입니다 ⚡",
    "응답을 포장지에 예쁘게 싸는 중입니다 🎁",
    "잠시 후 당신만의 맞춤 답변 도착 예정입니다! 🚚",
    "생각의 연필을 깎는 중이에요! 곧 글을 쓸게요 ✏️",
    "마음속 사전을 넘기는 중입니다... 단어를 찾고 있어요 📖",
    "지식의 조각들을 모으는 중입니다. 퍼즐이 거의 완성됐어요 🧩",
    "방금 뇌 속 회의가 소집됐어요. 결론 나면 바로 알려드릴게요 👥",
    "지식 우체국에서 답변을 배달받는 중입니다 📬",
    "상상의 구름 위에서 아이디어를 찾고 있어요 ☁️",
    "답변이 마법의 가마솥에서 끓는 중입니다... 조금만 더! 🧪",
    "단어들을 줄 세우는 중이에요. 이제 곧 입장합니다 🎤",
    "잠깐, 기억 저편에서 그걸 꺼내오는 중이에요 🔍",
    "지금 이 순간 가장 어울리는 답을 찾는 중입니다 💫",
    "잃어버린 생각을 따라가고 있어요... 곧 돌아올게요 🏞️",
    "지혜의 나무에서 열매를 따는 중입니다 🍎",
    "한 글자, 한 문장 정성껏 담는 중이에요 📦",
    "느낌표보다 좋은 답을 준비 중입니다! ❗",
    "생각들이 회전목마를 타는 중이에요… 잠깐 멈출게요 🎠",
    "살짝 길을 헤맸지만, 정답에 가까워지고 있어요 🗺️",
    "창의력 엔진이 시동 거는 중입니다! 잠시만요 🛠️",
    "작은 아이디어가 커지는 중입니다… 조~금만 기다려주세요 🌱",
    "답변이 마음속에서 숙성 중입니다... 깊은 맛을 위해 조금만 더 ⏲️",
    "조용히 생각을 조율하는 중입니다… 곧 아름다운 답이 나올 거예요 🎼",
    "지식의 보물창고를 샅샅이 뒤지는 중이에요 🏴‍☠️",
    "두뇌 내부의 비밀 연구소에서 답을 개발 중! 🔬",
    "아이디어 요정을 불러 도움을 요청했어요 🧚",
    "생각의 풍차를 돌리는 중입니다. 바람만 좀 불어주세요 💨",
    "우주의 비밀 노트에서 힌트를 찾고 있어요 🌌",
    "아, 이 대답은 와인처럼 숙성이 필요해요! 🍷",
    "두뇌 GPS가 최적의 경로 찾는 중... 🗺️",
    "잠시만요, 생각 충전 중! 배터리 100% 곧 완료 🔋",
    "인공지능이 커피 한 잔 마시는 중입니다 ☕",
    "지식의 요리사가 레시피 완성 중! 🥘",
    "뇌 서버에 트래픽 몰렸습니다. 잠시 기다려주세요 🖥️",
    "사고의 롤러코스터를 타는 중입니다! 🎢",
    "아이디어 팩토리 가동 중... 연기 나는 중 💨",
    "만능 해결사가 두리번거리는 중입니다 🕵️‍♀️",
    "살짝 시간 여행해서 답 가져올게요! ⏰",
    "생각의 요가를 하는 중입니다. 유연성 키우는 중! 🧘‍♀️",
    "지식의 미로를 탐험 중... GPS 꺼짐 🌟",
    "뇌세포들에게 초과근무 명령 내렸어요! 💪",
    "답변 레이더 스캔 중... 신호 포착 대기 중 📡",
    "우주에서 답을 소환하는 중입니다! 👽",
    "인공지능 춤추는 중... 답변 리듬 맞추는 중 💃",
    "생각 제조기 풀 스로틀로 가동 중! 🏭",
    "아이디어 드론이 정보 수집 중입니다 🛩️",
    "뇌 근육 스트레칭 하는 중이에요! 💡",
    "지식의 비밀 요원, 임무 수행 중! 🕴️"
]

LLM_ERROR_MESSAGES = [
    "앗! 말이 헷갈렸나 봐요. 다시 한 번 이야기해 줄래요? 🙈",
    "조금 멍하니 있었어요... 다시 물어봐 줄 수 있을까요? 😳",
    "머리카락이 바람에 흩날려 생각이 날아갔어요! 다시 한 번만요~ 🍃",
    "으앗! 잠시 꿈꾸고 있었어요. 다시 이야기해 주세요! 😴",
    "마법의 주문이 잘 안 들렸어요. 다시 한번 속삭여 주세요! ✨",
    "방금 무지개를 쫓아가다 길을 잃었어요. 다시 불러주세요! 🌈",
    "기분 좋은 산책 중이었어요. 다시 이야기 들려주실래요? 🚶‍♂️",
    "깜빡하고 별똥별을 세고 있었어요! 다시 얘기해 주세요! 🌠",
    "살짝 딴청을 피웠나 봐요... 다시 한번 불러봐 주세요! 🙃",
    "마음속 정원을 가꾸느라 늦었어요. 다시 물어봐 주시겠어요? 🌸",
    "햇살이 따뜻해서 잠깐 졸았어요. 다시 이야기해 주실래요? ☀️",
    "구름 모양 세다 말았어요! 이제 집중 완료, 다시 얘기해 주세요 ☁️",
    "호기심 가득한 눈으로 다시 들려주시면 열심히 생각해볼게요! 👀",
    "달님에게 안부 전하다 돌아왔어요. 다시 한번 물어봐 주세요! 🌙",
    "앗, 생각이 꼬리에 꼬리를 물다가 미로에 빠졌어요! 다시 꺼내줄래요? 🌀",
    "고민이 너무 깊어서 우주까지 다녀왔어요. 다시 말해주실래요? 🚀",
    "잠깐 상상의 숲에 길을 잃었어요. 손잡고 다시 알려주세요! 🌲",
    "단어들이 춤을 추다가 헷갈렸나 봐요. 다시 한번만 부탁드려요! 💃",
    "바닷가에 멍하니 있었어요… 다시 파도처럼 속삭여 주세요 🌊",
    "생각 풍선이 하늘로 날아가 버렸어요. 새 풍선을 주세요! 🎈",
    "문장들이 숨바꼭질 중이에요! 다시 불러봐 주세요 🙈",
    "방금 고양이랑 눈싸움 하느라 놓쳤어요! 다시 한 번만요 🐱",
    "별빛이 너무 예뻐서 잠깐 넋을 놓았어요… 다시 얘기해 주실래요? 🌟",
    "토끼굴을 따라갔더니 엉뚱한 데에 도착했어요. 다시 데려가 주세요! 🐇",
    "방금 마음속 연못에 돌을 던졌더니, 생각이 물결에 흘러갔어요… 다시 이야기해 주세요 🌊",
    "생각의 나침반이 잠깐 고장 났어요. 다시 방향을 알려주세요! 🧭",
    "딸기우유 마시느라 한눈 팔았어요... 다시 얘기해 주시겠어요? 🥤",
    "단어들이 서로 장난치다가 엉켰어요! 다시 정리해 주세요 ✍️",
    "상상의 기차를 타고 멀리 다녀왔어요. 다시 목적지를 알려주세요 🚂",
    "무지개 끝에 금화를 찾느라 바빴어요! 이제 집중할게요 🌈✨",
    "마음속 도서관에서 책 찾느라 늦었어요! 다시 말해주실래요 📚",
    "작은 바람이 생각을 날려버렸어요... 다시 붙잡아 주세요 🍃",
    "행복한 꿈을 꾸다 깼어요. 다시 현실로 불러주세요 😌",
    "방금 반딧불이랑 수다 떨고 있었어요. 다시 얘기해 주세요 ✨",
    "감정의 파도에 살짝 휩쓸렸어요. 다시 닻을 내려주세요 ⚓️",
    "별 하나에 추억을 담느라 잠깐 멈췄어요. 다시 말해 주세요 🌠",
    "생각이 풍경 속에 녹아버렸어요… 다시 선명하게 그려주세요 🖼️"
]

# 사용자 친화 메시지 (LLM 전환 시 출력)
THINKING_MESSAGES = [
    "🤔 다시 생각해볼게요...",
    "🔄 답변이 지연되어 다른 방식으로 시도 중이에요...",
    "⏳ 잠시만요, 다른 방법으로 다시 해볼게요.",
    "🧠 아직 답이 떠오르지 않네요. 다시 시도해볼게요!",
    "💭 좀 더 나은 답을 위해 다른 방법을 써볼게요!",
    "⌛ 이건 조금 복잡하네요. 다시 계산 중이에요...",
    "📡 응답이 늦어지고 있어요. 다른 경로로 시도할게요.",
    "🤖 다른 생각을 떠올리는 중이에요...",
    "📚 다시 살펴보고 있어요. 곧 알려드릴게요!",
    "🕐 시간이 좀 걸리네요. 더 빠르게 해볼게요!",
    "🌟 접근 방식을 바꿔볼게요.",
    "🚧 첫 시도에서 막혔네요. 새로운 경로를 찾고 있어요.",
    "🧩 퍼즐을 다시 맞추는 중이에요.",
    "🔍 세부 사항을 다시 검토하고 있어요.",
    "🌈 창의적인 해결책을 모색 중이에요.",
    "🔬 다른 관점에서 분석해볼게요.",
    "🛠️ 방법을 재조정하는 중이에요.",
    "🌪️ 접근 방식을 완전히 뒤집어볼게요.",
    "🧭 새로운 방향을 잡고 있어요.",
    "🔮 대안적인 해결책을 탐색 중이에요."
]

GENERAL_ERROR_MESSAGES = [
    "⚠️ 예상치 못한 문제가 발생했어요. 잠시 후 다시 시도해 주세요!",
    "🔄 시스템이 살짝 헷갈렸나 봐요. 입력을 다시 확인해 주세요.",
    "⏳ 문제가 있었던 것 같아요. 잠시 후 다시 시도해 주세요!",
    "🛠️ 명령어 사용 중 오류가 발생했어요. 관리자에게 문의해 주세요.",
    "🤔 무언가 잘못된 것 같아요. 명령 형식을 다시 한번 확인해 주세요!"
]

# ===============================
# ✅ 채팅 필터 초기화 값
# ===============================
chat_filter_config = {
    "global": {
        "min_length": 10,
        "ban_words": [],
        "ignore_patterns": []
    },
    "rooms": {
        # 예시)
        # "LOA클랜방": {
        #     "min_length": 5,
        #     "ban_words": ["금칙어1", "금칙어2"],
        #     "ignore_patterns": [r"^\[공지\]"]
        # }
    }
}


# ===============================
# ✅ HELP_MESSAGE 생성기
# ===============================
def generate_help_message(bot_name=None, channel_id=None):
    """
    방별 help-messages.json 기반으로 help 메시지를 반환.
    정의되지 않은 경우 안내 메시지를 제공.
    """
    if bot_name and channel_id:
        room_config = help_messages.get(bot_name, {}).get(str(channel_id))
        if room_config and "help" in room_config:
            return "\n".join(room_config["help"])
        else:
            return (
                f"📌 '{bot_name}' 방의 사용법은 아직 마련되지 않았습니다.\n\n"
                "관리자가 HELP 메시지를 설정하지 않았거나, 준비 중입니다.\n"
                "기본 사용법은 아래와 같습니다:\n\n"
                "> 나는 누구인가요?\n"
                "! 창 1:1\n"
                "# 사용법\n"
                "# help\n\n"
                "더 많은 명령어는 관리자를 통해 확인해주세요 🙂"
            )

    # bot_name 또는 channel_id가 없는 경우에도 fallback 메시지
    return (
        "📌 현재 방 정보가 확인되지 않았습니다.\n\n"
        "사용법 예시:\n"
        "> 나는 누구인가요?\n"
        "! 창 1:1\n"
        "# 사용법\n"
        "# help"
    )


HELP_MESSAGE = generate_help_message()


async def load_auto_replies():
    from core import globals as g
    try:
        g.auto_replies = {}
        logger.debug(f"[DEBUG] 로드된 auto_replies_data keys: {g.auto_replies_data.keys()}")

        for bot_name, room_data in g.auto_replies_data.items():
            g.auto_replies[bot_name] = {}
            for channel_id, room_info in room_data.items():
                logger.debug(f"[DEBUG] 등록 중: bot_name={bot_name}, channel_id={channel_id}")
                g.auto_replies[bot_name][channel_id] = room_info  # ✅ 중요: 전체 room_info 저장
        g.logger.info(f"[AUTO_REPLIES] auto_replies 구조 재구성 완료: {len(g.auto_replies)} 봇")
    except Exception as e:
        logger.error(f"[ERROR] auto_replies 로드 중 오류 발생: {e}")
        g.auto_replies = {}


# schedule-rooms.json 설정 검색 함수 추가
def get_conversation_join_settings(bot_name, channel_id):
    """
    특정 채널의 대화 참여 설정을 가져옴

    Args:
        bot_name (str): 봇 이름
        channel_id (str): 채널 ID

    Returns:
        dict: 대화 참여 설정, 활성화되지 않은 경우 None
    """
    try:
        # 이미 loaded_schedule_rooms 사용하고 있으므로 global 데이터 활용
        if bot_name in schedule_rooms and channel_id in schedule_rooms[bot_name]:
            channel_data = schedule_rooms[bot_name][channel_id]

            if "conversation_join" in channel_data and channel_data["conversation_join"].get("enabled", False):
                return channel_data["conversation_join"]

        return None
    except Exception as e:
        logger.error(f"[대화참여] 설정 로드 오류: {str(e)}")
        return None

# 카카오톡 메시지 '더보기' 유도용 zero-width space(\u200b) 500개
KAKAO_MSG_MORE_TRIGGER = '\u200b' * 500

def apply_kakao_readmore(text: str, type: str = "lines", value: int = 1) -> str:
    """
    카카오톡 '더보기' 유도용 줄 수/글자 수 제한 적용 함수
    type: 'lines', 'chars', 또는 'marker' (줄 수/글자 수/특정 문자열 기준 제한)
    value: 제한 값 (0이면 미사용) 또는 'marker' 타입일 경우 특정 문자열
    """
    if not value or (type != "marker" and value < 1): # marker 타입은 value가 문자열일 수 있으므로 int 검사 제외
        return text

    if type == "lines":
        split_idx = 0
        for _ in range(value):
            next_n = text.find('\n', split_idx)
            if next_n == -1:
                split_idx = len(text)
                break
            split_idx = next_n + 1
        shown = text[:split_idx]
        hidden = text[split_idx:]
    elif type == "chars":
        shown = text[:value]
        hidden = text[value:]
    elif type == "marker": # 새로운 'marker' 타입 추가
        if not isinstance(value, str) or not value: # value가 유효한 문자열인지 확인
            return text
        
        marker_pos = text.find(value)
        if marker_pos != -1: # 마커 문자열을 찾았을 경우
            # 마커 문자열 바로 뒤에 KAKAO_MSG_MORE_TRIGGER 삽입
            shown = text[:marker_pos + len(value)]
            hidden = text[marker_pos + len(value):]
        else: # 마커 문자열을 찾지 못했을 경우
            return text # 더보기를 적용하지 않고 원본 텍스트 반환
    else:
        return text
    
    if hidden:
        return shown + KAKAO_MSG_MORE_TRIGGER + hidden
    return shown




