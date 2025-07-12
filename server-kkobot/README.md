# LOA.i - 메인 서버 (파이썬)

LOA.i의 메인 서버는 카카오톡 메시지를 처리하고, 다양한 핵심 기능 및 확장 서비스 연동을 담당하는 파이썬 기반의 비동기 서버입니다. 메신저봇R 클라이언트를 통해 카카오톡과 통신하며, 데이터베이스 및 확장 기능(Extensions)과 상호작용합니다.

## 목차

- [시스템 개요](#시스템-개요)
- [데이터 흐름](#데이터-흐름)
- [Context 객체](#context-객체)
- [폴더 구조](#폴더-구조)
- [핵심 컴포넌트 상세 설명](#핵심-컴포넌트-상세-설명)
- [주요 기능](#주요-기능)
- [설치 및 실행](#설치-및-실행)
- [설정 파일](#설정-파일)
- [운영 관련 사항](#운영-관련-사항)
- [개발 가이드](#개발-가이드)

## 시스템 개요

메인 서버는 시스템의 중심 허브 역할을 합니다. 클라이언트로부터 메시지를 수신하여 기본 처리(자동응답, 로깅 등)를 수행하고, 복잡하거나 독립적인 기능이 필요한 경우 확장 기능(Extensions)에 처리를 위임합니다. 데이터는 MySQL 데이터베이스에 저장 및 관리됩니다.

### 전체 아키텍처

메인 서버는 클라이언트, 확장 기능, 데이터베이스와 다음과 같이 상호작용합니다.

```
[사용자/카카오톡]
      │
      ▼
[클라이언트: 메신저봇R (JS)]
      │  (TCP 통신)
      ▼
[메인 서버: 파이썬 비동기 서버]
      │  ┬
      │  │ (API 통신)
      │  ▼
      │ [확장 기능: 별도 실행 모듈 (Python)]
      │
      │  (DB 연동)
      ▼
[MySQL 데이터베이스]
```

---

## 데이터 흐름

#### 1. 메시지 수신 및 처리 흐름 (메인 서버 관점)

1.  메신저봇R 클라이언트로부터 TCP 소켓을 통해 메시지 수신 (`handle_client` 함수)
2.  수신된 메시지 파싱 및 기본 검증 (`process_message`)
3.  자동 응답 조건 확인 및 처리 (`auto_reply_handler`)
4.  메시지 정보 기반 사용자/방 정보 업데이트 및 로깅
5.  명령어 접두어 분석 및 명령어 식별 (`prefix_utils`, `command_dispatcher`)
6.  **명령어 타입에 따라 처리 방식 결정**: 
    - **기본 명령어**: 서버 내 `services/` 디렉토리의 해당 서비스 함수 직접 호출
    - **확장 기능 명령어**: `extensions` 디렉토리의 해당 확장 기능 모듈에 API 통신을 통해 처리 요청 및 응답 대기
7.  처리 결과를 클라이언트로 전달하여 카카오톡에 응답 발송 (`send_message_from_context`)

#### 2. 스케줄 메시지 발송 흐름

- `scheduler.py` 모듈이 설정된 스케줄에 따라 메시지 생성 및 발송 (`send_message` 함수를 통해 클라이언트로 전송)

#### 3. 뉴스 크롤링 및 발송 흐름

- `services/news/tradingview_crawler.py` 모듈이 주기적으로 뉴스 크롤링 → DB 저장 → 발송 대상 채널 확인 → 메시지 생성 → 발송 (`send_message` 함수 사용)

---

## Context 객체

Context 객체는 LOA.i 서버에서 메시지 처리 과정 전반에 걸쳐 사용되는 핵심 데이터 구조입니다. 이 객체는 메시지 전송, 명령어 처리, 서비스 호출 등에 필요한 모든 컨텍스트 정보를 담고 있습니다.

### Context 필드 정의

#### 필수 필드 (메시지 전송용)
- **`bot_name`** (str): 봇의 이름 (예: "LOA.i")
- **`channel_id`** (str): 카카오톡 채널/방의 고유 ID
- **`room`** (str): 채팅방 이름

#### 선택적 필드 (상황에 따라 포함)
- **`bot_version`** (str): 봇의 버전 정보 (예: "v2.4.0")
- **`user_hash`** (str): 사용자의 고유 해시값
- **`sender`** (str): 메시지를 보낸 사용자의 닉네임
- **`is_group_chat`** (bool): 그룹 채팅 여부 (기본값: False)
- **`is_mention`** (bool): 멘션 메시지 여부 (기본값: False)
- **`server_status`** (str): 서버 상태 정보 (선택적)

### Context 사용 패턴

#### 1. 메시지 전송용 Context (최소 구성)
```python
context = {
    'bot_name': 'LOA.i',
    'channel_id': '12345678901234567',
    'room': '테스트 채팅방'
}
await send_message_response(context, "안녕하세요!")
```

#### 2. 완전한 Context (모든 정보 포함)
```python
context = {
    'bot_name': 'LOA.i',
    'bot_version': 'v2.4.0',
    'channel_id': '12345678901234567',
    'user_hash': 'abc123def456',
    'room': '테스트 채팅방',
    'sender': '사용자닉네임',
    'is_group_chat': True,
    'is_mention': False,
    'server_status': None
}
```

#### 3. 명령어 처리용 Context
명령어 처리 시에는 추가적인 정보가 포함될 수 있습니다:
```python
context = {
    # 기본 필드들...
    'prefix': '#',
    'prompt': '날씨',
    'is_scheduled': False,
    'schedule_index': None,
    'tts_config': {}
}
```

### Context 전달 흐름

1. **메시지 수신**: `message_processor.py`에서 초기 context 생성
2. **명령어 분석**: `command_dispatcher.py`에서 명령어 관련 정보 추가
3. **서비스 호출**: 각 서비스 함수에 context 전달
4. **메시지 전송**: `send_message_response()` 함수에서 context 사용

### 중요 사항

- **Writer 자동 검색**: `send_message_response()` 함수는 context의 `bot_name`을 사용하여 내부적으로 적절한 writer(소켓 연결)를 찾습니다.
- **필수 필드 검증**: 메시지 전송 시 `bot_name`, `channel_id`, `room` 필드가 반드시 필요합니다.
- **불변성**: Context 객체는 처리 과정에서 수정되지 않도록 주의해야 합니다.
- **타입 안정성**: 모든 필드는 명시된 타입을 준수해야 합니다.

### Context 생성 예시

#### 서비스에서 Context 생성
```python
async def some_service_function(bot_name, channel_id, room_name):
    context = {
        'bot_name': bot_name,
        'channel_id': channel_id,
        'room': room_name
    }
    
    # 메시지 전송
    await send_message_response(context, "처리 완료!")
```

#### 스케줄러에서 Context 생성
```python
context = {
    'bot_name': bot_name,
    'channel_id': channel_id,
    'room': room_name
}
await send_message_response(context, scheduled_message)
```

## 폴더 구조

```
server/
├── kkobot.py                      # 메인 실행 파일
├── core/                         # 핵심 로직 및 유틸리티
│   ├── globals.py                # 전역 변수 및 상태 관리
│   ├── server.py                 # TCP 서버 초기화
│   ├── client_handler.py         # 클라이언트 연결 처리
│   ├── message_processor.py      # 메시지 처리 파이프라인 (메인 파이프라인)
│   ├── db_utils.py               # 데이터베이스 유틸리티
│   ├── scheduler.py              # 스케줄 메시지 관리
│   ├── logger.py                 # 로깅 설정
│   ├── performance.py            # 성능 측정 유틸리티
│   ├── auto_reply_handler.py     # 자동 응답 처리
│   ├── reloaders/                # 설정 재로드 관련
│   └── utils/                    # 기타 유틸리티
├── services/                     # 메인 서버 내 기본 서비스 기능
│   ├── command_dispatcher.py     # 명령어 분배 (내부/확장 구분)
│   ├── news/                     # 뉴스 크롤링 관련
│   └── *.py                      # LLM, 성경, 프로필, 순위 등 기본 명령어 핸들러
├── database/                     # DB 연결 및 관리
├── config/                       # 각종 설정 파일 (JSON 등)
│   ├── commands/                 # 봇별/방별 명령어 정의 및 오버라이딩 JSON 파일
│   ├── envs/                     # 서버 환경 관련 JSON 설정 파일 (예: schedule-rooms-from-db.json)
│   ├── bots-settings/            # 봇별 특수 설정 (API 키 등)
│   └── *.json                    # 자동응답 등 일반 JSON 설정 파일
├── unit_test/                    # 단위 테스트 코드
├── docs/                         # 서버 관련 추가 문서
└── requirements.txt              # 서버 의존성 목록
```

- `games/omok/` 등 확장 기능으로 분리될 예정인 디렉토리는 향후 `extensions/` 디렉토리로 이동합니다.

---

## 핵심 컴포넌트 상세 설명

#### 1. 서버 초기화 및 실행 (kkobot.py)

- 서버 시작/종료, DB 연결 풀 초기화, 비동기 태스크(스케줄러, 뉴스 크롤러, 시스템 모니터링, 필터 리로더 등) 관리
- 메인 TCP 서버(`start_server()`) 실행

#### 2. TCP 서버 관리 (core/server.py, core/client_handler.py)

- TCP 서버 바인딩 및 설정
- 클라이언트 연결 관리, 핸드셰이크, 메시지 수신/파싱 (`handle_client`)
- `process_message()` 호출하여 메시지 처리 파이프라인 시작

#### 3. 메시지 처리 파이프라인 (core/message_processor.py)

- 수신 메시지의 초기 처리: 자동 응답, 필수 정보 검증, 사용자/방 정보 업데이트, 로깅
- 명령어 접두어 식별 후 `process_command()` 호출

#### 4. 명령어 분배 및 처리 (services/command_dispatcher.py)

- 접두어/명령어 타입을 기반으로 처리를 담당할 모듈/서비스 식별
- **기본 명령어**(`services/` 내 구현) 또는 **확장 기능 명령어**(`extensions/` 연동)로 분배
- 각 명령어 핸들러 호출 및 결과 취합

#### 5. 스케줄러 (core/scheduler.py)

- `config/schedules/schedule-rooms.json` 기반 스케줄 관리 및 메시지 자동 발송

#### 6. 뉴스 크롤러 (services/news/tradingview_crawler.py)

- 트레이딩뷰 뉴스 크롤링, DB 저장, 설정된 채널로 뉴스 발송

#### 7. 전역 상태 관리 (core/globals.py)

- 설정, 상수, DB 풀, HTTP 클라이언트, 룸/채널 매핑 등 시스템 전반의 공유 자원 관리
- 자동 응답 및 명령어 정의(`PREFIX_MAP`)를 관리하지만, 주요 명령어 정의는 `config/commands/{bot-name}.json` 파일로 이동되었습니다.

#### 8. 세션 관리 (core/sessions/)

`core/sessions/` 디렉토리는 봇과 사용자 간의 대화 세션을 관리하는 범용적인 기능을 제공합니다. LLM 채팅에 특화된 로직은 `services/llm_chat_sessions/`로 분리되었으며, `core/sessions/`에는 세션의 생명주기 관리, 데이터 저장, 만료 처리 등 핵심적인 인프라 기능만 남아 있습니다.

-   **`session_manager.py`**: 세션의 생성, 연장, 종료와 같은 고수준의 세션 생명주기를 관리합니다. 사용자 또는 채널별 세션 상태를 조작하는 인터페이스를 제공합니다.
-   **`session_store.py`**: 모든 활성 세션 데이터(`active_sessions`)와 사용자/채널별 일일 사용량(`daily_usage`, `channel_daily_usage`)을 저장하고 관리하는 단일 정보원(Single Source of Truth) 역할을 합니다. 세션 ID 생성, 일일 사용량 리셋 등 저수준 유틸리티 함수를 포함합니다.
-   **`session_scheduler.py`**: 백그라운드에서 주기적으로 세션의 만료 여부를 확인하고, 만료 임박 알림을 보내거나 만료된 세션을 자동으로 종료하는 역할을 합니다.

---

## 주요 기능

메인 서버 자체적으로 제공하는 주요 기능은 다음과 같습니다.
확장 기능(Extensions)을 통해 추가적인 기능(예: 게임)이 제공될 수 있습니다.

### AI 서비스 (일부 핵심 기능은 메인 서버에서 직접 처리, 복잡 기능은 확장 가능)
- **다중 AI 모델 연동**: OpenAI, Gemini 등 LLM API 호출 및 응답 처리
- **자연어 대화**: 기본적인 LLM 질의응답 중개
- **대화 요약, 코드 분석 등**: 간단한 LLM 활용 기능

### 정보 서비스
- **금융/투자**: 뉴스, 환율 정보 제공
- **날씨**: 날씨 정보 제공
- **미디어**: YouTube/웹페이지 정보 요약 등
- **기타**: 성경 검색, 명언/속담, 점심 메뉴 추천, 로또 번호 생성 등 유틸리티

### 사용자 관리
- 프로필, 채팅 순위, 크레딧 등 기본 사용자 데이터 관리 및 조회

---

## 설치 및 실행

### 필수 요구사항

- Python 3.9+ 이상
- MySQL 데이터베이스
- 외부 API 키 (OpenAI, Gemini 등)

### 서버 설치

1.  저장소 복제
2.  가상 환경 설정
3.  의존성 설치 (`requirements.txt`)
4.  설정 파일 (`config/`) 구성
5.  데이터베이스 설정 (스키마 적용)

### 서버 실행

- 개발 환경: `python kkobot.py`
- 운영 환경: `nohup python kkobot.py &` 등

---

## 설정 파일

LOA.i 서버는 다양한 설정 파일을 통해 동작 방식을 제어합니다. 설정 파일은 크게 두 가지 유형으로 관리됩니다:

### 1. 암호화된 핵심 설정 (`server/config/loader.py`)
- **파일**: `.kakao-bot.key`, `.kakao-bot.enc`  
- **로딩 방식**: `server/core/server.py`에서 `config.loader.load_config()`를 호출하여 서버 시작 시 1회 로드  
- **접근**: 복호화된 설정은 `CONFIG` 전역 변수에 저장되어 사용  

### 2. 일반 JSON 설정 (`server/core/globals.py`)
- **파일**: `config/settings/{bot-name}.json` (스케줄, 자동응답, 도움말 등 봇별 주요 설정), `config/commands/{bot-name}.json` (봇별/방별 명령어 정의 및 오버라이딩), `config/bots-settings/{bot-name}.json` (봇별 API 키 등 특수 설정), `history.json` 등  
- **로딩 방식**: `globals.py`가 애플리케이션 시작 시 `JSON_CONFIG_FILES`에 정의된 경로를 기반으로 일괄 로드, `JSON_DATA_VARS`에 매핑된 이름으로 `g` 객체 전역 변수에 자동 할당  
- `config/settings/{bot-name}.json`은 기존 `schedule-rooms.json`, `help-messages.json`, `auto-replies.json`, `profile-analysis.json`, `model-pricing.json` 등의 역할을 통합하여 봇별로 관리합니다.
- `config/commands/{bot-name}.json`은 동적 명령어 시스템의 핵심 파일로, 각 봇의 글로벌 명령어와 방별 오버라이딩 규칙을 정의합니다.
- **접근**: `from core import globals as g` 후 `g.<변수명>` 형식으로 참조  

#### 동적 리로드
일부 설정 파일(예: `config/settings/{bot-name}.json`, `config/commands/{bot-name}.json`)은 런타임에 변경될 수 있으며, 다음 명령으로 동적 리로드가 가능합니다:
```bash
curl -X POST http://localhost:8000/reload-config?file=settings
```
또는 채팅 명령어:
```text
!reload commands
!reload settings
```

#### 향후 계획
현재는 JSON 파일 기반 설정을 사용하고 있으나, **추후에는 중앙 DB 테이블로 이관**하여 API 기반으로 설정을 관리할 예정입니다. 이를 통해 운영 중 설정 변경 시 보다 안정적이고 일관된 관리 및 마이그레이션이 가능해집니다。

---

## 운영 관련 사항

- 로그 모니터링, 주요 유의사항, 성능 모니터링/최적화, 설정 리로드 방법 등

---

## 개발 가이드

- 새로운 기본 명령어 추가 (globals.py, command_dispatcher.py, services/)
- 새로운 자동 응답 패턴 추가 (auto-replies.json)
- **확장 기능 연동**: `extensions` 디렉토리의 확장 기능 모듈과의 API 통신 구현 및 `command_dispatcher.py`에서의 분배 로직 추가
- 시스템 확장 팁 (모듈화, 비동기, 오류 처리, 설정 기반 접근, 성능 고려)

### Context 객체 사용 가이드

#### 새로운 서비스 함수 작성 시
```python
async def my_service_function(bot_name, channel_id, room_name, user_data=None):
    # 1. Context 생성 (필수 필드만)
    context = {
        'bot_name': bot_name,
        'channel_id': channel_id,
        'room': room_name
    }
    
    # 2. 필요시 선택적 필드 추가
    if user_data:
        context.update({
            'user_hash': user_data.get('user_hash'),
            'sender': user_data.get('sender'),
            'is_group_chat': True
        })
    
    # 3. 메시지 전송
    await send_message_response(context, "처리 완료!")
```

#### 스케줄 메시지 작성 시
```python
# 스케줄러에서 자동으로 호출되는 함수
async def scheduled_function():
    for bot_name, channels in g.schedule_rooms.items():
        for channel_id, config in channels.items():
            context = {
                'bot_name': bot_name,
                'channel_id': channel_id,
                'room': config.get('room_name', '알 수 없는 방')
            }
            await send_message_response(context, "정기 메시지")
```

## Writer 관리

Writer는 서버와 클라이언트 간의 TCP 소켓 연결을 나타내는 `asyncio.StreamWriter` 객체입니다. LOA.i 서버에서는 안정적인 메시지 전송을 위해 자동화된 writer 관리 시스템을 사용합니다.

### Writer의 정체와 역할

- **정체**: `asyncio.StreamWriter` 객체로 TCP 소켓 연결을 나타냄
- **역할**: 서버에서 클라이언트로 메시지 패킷을 전송하는 통신 채널
- **구분**: 메시지 패킷 구성과는 무관하며, 순수하게 소켓 통신용
- **라우팅**: 클라이언트에서 `bot.send(roomName, messageText)`로 방을 구분

### Writer 저장 구조

#### ✅ 올바른 구조: `g.clients`
```python
g.clients = {
    "LOA.i": {
        ("192.168.1.100", 12345): writer1,
        ("192.168.1.101", 12346): writer2
    }
}
```
- **구조**: `{bot_name: {(ip, port): writer}}`
- **관리**: 연결/해제 시 자동으로 업데이트
- **용도**: 실제 소켓 연결 관리의 유일한 정보원(Single Source of Truth)

#### ❌ 레거시 구조: `g.room_to_writer`
```python
g.room_to_writer = {
    "LOA.i": {
        "channel_id": writer
    }
}
```
- **문제**: 실제로는 업데이트되지 않는 레거시 구조
- **사용 금지**: 새로운 코드에서는 사용하지 말 것

### 봇과 클라이언트의 관계

#### 기본 원칙
- **1개 봇 = 1개 논리적 클라이언트**: 사용자 관점에서 하나의 봇으로 인식
- **단일 활성 연결**: 여러 물리적 연결 중 하나만 활성화하여 사용
- **중복 방지**: 같은 메시지가 여러 번 전송되지 않도록 보장

#### 다중 연결의 목적
```python
# 고가용성(High Availability)을 위한 백업 연결
Primary:   LOA.i (메인 서버)     ← 주로 사용
Secondary: LOA.i (백업 서버)     ← 메인 장애 시 사용
Tertiary:  LOA.i (예비 서버)     ← 비상시 사용
```

### 좀비 Writer 이슈

#### 발생 원인
실제 운영 환경에서는 기술적 문제로 인해 여러 writer가 동시에 존재할 수 있습니다:

1. **메신저봇R 프로세스 문제**
   - 비정상 종료 시 TCP 연결이 즉시 정리되지 않음
   - 새로운 프로세스 시작 시 추가 연결 생성

2. **TCP 소켓 지연 정리**
   - TIME_WAIT 상태로 인한 지연된 정리
   - `is_closing() = False`이지만 실제로는 전송 불가능

#### 실제 상황 예시
```python
g.clients["LOA.i"] = {
    ("192.168.1.100", 12345): writer1,  # 좀비 연결 (실제로는 죽음)
    ("192.168.1.100", 12346): writer2   # 새로운 연결 (실제 활성)
}
```

#### 자동 해결 메커니즘
- **순차 확인**: 첫 번째 연결부터 순서대로 유효성 검사
- **자동 정리**: 유효하지 않은 연결은 자동으로 제거
- **Failover**: 첫 번째가 실패하면 자동으로 다음 연결 시도

### 올바른 사용법

#### DO (권장사항)
```python
# ✅ send_message_response 사용
context = {'bot_name': bot_name, 'channel_id': channel_id, 'room': room_name}
await send_message_response(context, message)

# ✅ 특수한 경우에만 writer_utils 사용
from core.utils.writer_utils import get_valid_writer
writer = get_valid_writer(bot_name)
if writer:
    # 직접 소켓 통신이 필요한 특수한 경우
    pass
```

#### DON'T (금지사항)
```python
# ❌ g.room_to_writer 사용 금지
writer = g.room_to_writer.get(bot_name, {}).get(channel_id)

# ❌ context에 writer 직접 포함 금지
context = {'bot_name': bot_name, 'channel_id': channel_id, 'room': room_name}
writer = get_valid_writer(bot_name)
context['writer'] = writer  # 이렇게 하지 마세요!

# ❌ 수동 writer 검색 후 context 생성 금지
writer = get_valid_writer(bot_name)
context = {'bot_name': bot_name, 'channel_id': channel_id, 'room': room_name, 'writer': writer}
```

### 오류 처리 가이드

#### Context 검증
```python
def validate_context(context):
    required_fields = ['bot_name', 'channel_id', 'room']
    for field in required_fields:
        if not context.get(field):
            raise ValueError(f"Context missing required field: {field}")
```

#### Writer 연결 오류 처리
```python
# send_message_response가 자동으로 처리하므로 별도 처리 불필요
# 단, 로그를 통해 연결 상태 모니터링 가능
try:
    success = await send_message_response(context, message)
    if not success:
        logger.warning("메시지 전송 실패 - writer 연결 문제 가능성")
except Exception as e:
    logger.error(f"메시지 전송 중 오류: {e}")
```

### 참고사항

#### 다중 Writer 표시 현상
실제 운영 중에는 `g.clients`에 같은 봇의 여러 writer가 표시될 수 있습니다:

```python
# 예시: 디버깅 시 관찰되는 상황
g.clients["LOA.i"] = {
    ("192.168.1.100", 12345): writer1,  # 좀비 연결
    ("192.168.1.100", 12346): writer2,  # 실제 활성 연결
}
```

**이는 정상적인 현상입니다:**
- 메신저봇R 재시작 과정에서 일시적으로 발생
- 시스템이 자동으로 유효한 연결만 선택하여 사용
- 좀비 연결은 점진적으로 정리됨
- 사용자에게는 여전히 하나의 봇으로만 인식됨

**오해하지 말아야 할 점:**
- 다중 연결 = 로드 밸런싱 (❌)
- 다중 연결 = 동시 메시지 전송 (❌)
- 다중 연결 = 여러 봇 인스턴스 (❌)

실제로는 **1개 봇 = 1개 논리적 클라이언트** 원칙이 유지되며, 여러 writer는 **고가용성과 기술적 문제 해결**을 위한 것입니다.