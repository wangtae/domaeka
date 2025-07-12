# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 프로젝트 개요

**Domaeka 공동구매 시스템**은 카카오톡 봇을 활용한 공동구매 플랫폼입니다. 세 가지 주요 컴포넌트로 구성되어 있습니다:

1. **웹 시스템** (`web/`): 그누보드5/영카트5 기반의 계층형 관리자 시스템
2. **카카오봇 서버** (`server/`): Python 기반의 경량 카카오톡 봇 서버
3. **카카오봇 클라이언트** (`client/`): MessengerBotR 기반의 안드로이드 클라이언트

## 주요 개발 명령어

### 웹 시스템 (web/)
```bash
# Playwright 테스트 실행
npx playwright test

# 특정 테스트 파일 실행
npx playwright test headquarters.spec.ts

# 테스트 결과 HTML 리포트 보기
npx playwright show-report

# 파일 권한 설정
chmod +x perms.sh && ./perms.sh

# 데이터베이스 마이그레이션
mysql -u root -p domaeka < dmk/sql/001_create_dmk_tables.sql
mysql -u root -p domaeka < dmk/sql/002_alter_existing_tables.sql
mysql -u root -p domaeka < dmk/sql/005_insert_test_data.sql
php dmk/sql/004_update_admin_passwords.php
```

### 카카오봇 서버 (server/)
```bash
# 가상환경 활성화 및 의존성 설치
cd server
python -m venv venv
source venv/bin/activate  # Linux/Mac
# 또는 venv\Scripts\activate  # Windows
pip install -r requirements.txt

# 서버 실행 (기존 방식)
python main.py --port=1490 --mode=test    # 테스트 모드
python main.py --port=1491 --mode=prod    # 운영 모드

# 서버 실행 (신규 프로세스 방식 - 구현 예정)
python main.py --process-name=server-test-01    # 프로세스명 기반 실행
```

### 카카오봇 클라이언트 (client/)
```bash
# bridge.js 파일을 MessengerBotR 앱에 등록하여 사용
# 클라이언트는 안드로이드 앱이므로 별도 빌드 명령 없음
```

## 아키텍처 및 핵심 구조

### 전체 시스템 아키텍처

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   웹 시스템     │    │   카카오봇      │    │   카카오봇      │
│   (web/)        │    │   서버          │    │   클라이언트    │
│                 │    │   (server/)     │    │   (client/)     │
│ • 관리자 페이지 │    │                 │    │                 │
│ • 상품/주문관리 │    │ • TCP 서버      │◄───┤ • MessengerBotR │
│ • 계층형 권한   │    │ • 메시지 처리   │    │ • bridge.js     │
│ • 그누보드5기반 │    │ • DB 연동       │    │ • 안드로이드    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │
         └───────────────────────┘
              공통 MySQL DB
```

### 웹 시스템 (web/) - 계층형 관리자 시스템

**기반 기술**: 그누보드5 + 영카트5 + 도매까 확장

**핵심 구조**:
- `dmk/`: 도매까 전용 확장 기능
- `adm/`: 기존 관리자 시스템 (그누보드5)
- `shop/`: 쇼핑몰 기능 (영카트5)
- `bbs/`: 게시판 시스템

**계층형 권한 시스템**:
- **본사(super)**: 모든 권한 보유
- **총판(distributor)**: 총판 아래 대리점/지점 관리
- **대리점(agency)**: 대리점 아래 지점 관리
- **지점(branch)**: 제한된 상품/주문 관리

### 카카오봇 서버 (server/) - Python 비동기 TCP 서버

**기반 기술**: Python 3.7+ + asyncio + aiomysql

**핵심 구조**:
```
server/
├── core/
│   ├── server.py           # TCP 서버 구현
│   ├── client_handler.py   # 클라이언트 연결 처리
│   ├── message_processor.py # 메시지 처리 및 명령어 라우팅
│   └── globals.py          # 전역 설정
├── services/
│   ├── echo_service.py     # 에코 명령어
│   └── client_info_service.py # 클라이언트 정보 조회
├── database/
│   └── connection.py       # MySQL 연결 관리
└── main.py                 # 서버 진입점
```

**프로토콜**: JSON 기반 TCP 소켓 통신
- `analyze` 이벤트: 카카오톡 메시지 → 서버
- `messageResponse` 이벤트: 서버 → 카카오톡 메시지
- `ping` 이벤트: 연결 상태 확인 및 모니터링

### 카카오봇 클라이언트 (client/) - MessengerBotR 기반

**기반 기술**: MessengerBotR + Rhino JavaScript Engine

**핵심 구조**:
- `bridge.js`: 메인 클라이언트 코드 (3.1.4 버전)
- 모듈별 분리: Auth, Utils, MediaHandler, BotCore

**주요 기능**:
- 다중 서버 로테이션 및 무한 재연결
- HMAC 기반 인증 및 Android ID 식별
- TTL 기반 메시지 큐잉
- 동적 파일 전송 대기시간 계산
- 상세 로깅 및 리소스 모니터링

## 데이터베이스 구조

### 공통 테이블 (web/server 공유)

**기본 접두사**: `g5_` (그누보드5), `g5_dmk_` (도매까 확장), `kb_` (카카오봇)

**핵심 테이블**:
- `kb_chat_logs`: 카카오톡 채팅 로그 저장
- `kb_ping_monitor`: 클라이언트 ping 응답 및 모니터링 정보 저장
- `kb_bot_devices`: 봇 디바이스 인증 및 승인 관리
- `kb_rooms`: 채팅방별 봇 승인 및 설정 관리
- `kb_servers`: 서버 정보 및 상태 관리
- `kb_server_processes`: 서버 프로세스 관리 및 모니터링
- `g5_dmk_agencies`: 대리점 정보
- `g5_dmk_branches`: 지점 정보
- `g5_shop_item`: 상품 정보 (dmk_owner_type/dmk_owner_id 확장)

## 프로토콜 및 통신

### 카카오봇 클라이언트 ↔ 서버 통신

**연결 과정**:
1. 핸드셰이크: `{"botName": "LOA.i", "version": "3.1.4", "deviceID": "Android ID"}`
2. 인증: HMAC-SHA256 서명 검증
3. 메시지 교환: JSON 기반 이벤트 시스템

**주요 이벤트**:
```json
// 메시지 분석 요청 (클라이언트 → 서버)
{
  "event": "analyze",
  "data": {
    "room": "채팅방 이름",
    "text": "메시지 내용",
    "sender": "발신자",
    "channelId": "채널 ID",
    "auth": { /* HMAC 인증 정보 */ }
  }
}

// 메시지 응답 (서버 → 클라이언트)
{
  "event": "messageResponse", 
  "data": {
    "room": "채팅방 이름",
    "text": "응답 메시지",
    "channel_id": "채널 ID"
  }
}
```

## 개발 가이드라인

### 웹 시스템 개발
- 기존 그누보드5/영카트5 파일 수정 시 `dmk/docs/g5_modifications_log.md`에 기록
- 새로운 기능은 `dmk/` 폴더에 구현하여 기존 시스템 최소 수정
- 권한 체크: `dmk_get_admin_auth()` 및 `dmk_is_menu_allowed()` 사용

### 카카오봇 서버 개발
- 새로운 명령어는 `services/` 디렉토리에 서비스 모듈 생성
- `message_processor.py`에서 명령어 라우팅 추가
- 비동기 패턴 준수: `async/await` 사용
- ping 이벤트 처리 시 `kb_ping_monitor` 테이블에 모니터링 데이터 저장
- **봇 승인 시스템**: `kb_bot_devices`와 `kb_rooms` 모두 approved 상태여야 메시지 응답
- **프로세스 관리**: `--process-name` 옵션으로 DB 기반 프로세스 설정 관리 (구현 예정)

### 카카오봇 클라이언트 개발
- Rhino 엔진 제약: `const`, `let` 키워드 사용 금지
- 모듈 패턴 사용: 전역 네임스페이스 오염 방지
- 설정 변경은 `BOT_CONFIG` 객체에서 수행

## 환경 설정

### 개발 환경 요구사항
- **웹**: PHP 7.4+, MySQL 5.7+, Apache/Nginx
- **서버**: Python 3.7+, MySQL 연결
- **클라이언트**: Android 기기, MessengerBotR 앱

### 설정 파일
- `web/data/dbconfig.php`: 데이터베이스 연결 (Git 제외)
- `server/config/`: 암호화된 설정 파일
- `client/bridge.js`: BOT_CONFIG 객체에서 서버 정보 설정

## 보안 고려사항

- 설정 파일 암호화: Fernet 암호화 사용
- HMAC 인증: 클라이언트-서버 간 안전한 통신
- IP 제한: 개발자 IP 설정으로 접근 제한
- 파일 권한: 업로드 디렉토리 적절한 권한 설정
- **봇 승인 시스템**: 
  - 새로운 디바이스는 기본적으로 `pending` 상태로 등록
  - 새로운 채팅방은 기본적으로 `pending` 상태로 등록
  - 관리자 승인 후에만 봇 기능 활성화되어 보안성 강화

## 참고 문서

- `web/README.md`: 웹 시스템 상세 가이드
- `server/README.md`: 카카오봇 서버 API 문서
- `client/messengerbotR/README.md`: 클라이언트 설정 가이드
- `web/dmk/docs/`: 프로젝트 진행 상황 및 기술 문서