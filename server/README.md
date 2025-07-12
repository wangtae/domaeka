# Domaeka 카카오봇 서버

Domaeka 공동구매 시스템을 위한 카카오톡 봇 서버입니다. MessengerBotR 클라이언트와 TCP 소켓 통신을 통해 공동구매 관련 기능을 제공합니다.

## 📋 프로젝트 개요

이 프로젝트는 **domaeka** 공동구매 시스템에 카카오봇을 적용하여 비즈니스 모델을 구축하는 것입니다. 기존 kkobot 프로젝트의 구조를 따르되, domaeka에 필요한 기능들로 새롭게 작성되고 있습니다.

### 주요 특징
- **kkobot 호환성**: 클라이언트-서버 연결 방식과 메시지 구조를 kkobot과 동일하게 유지
- **독립적 구현**: domaeka DB에 별도 테이블을 정의하여 봇과 방 관리
- **확장 가능**: 새로운 공동구매 관련 기능을 쉽게 추가할 수 있는 구조
- **시스템 호환성**: 언제든 kkobot 시스템과 상호 호환 가능

### 클라이언트 연동
- `@client/messengerbotR/bridge.js`는 kkobot 시스템을 기반으로 하되 domaeka 요구사항에 맞게 수정
- 클라이언트와 서버 간 연결 관리 방식과 메시지 구조 kkobot과 호환성 유지
- 기존 kkobot 서버 참조용으로 `@server-kkobot` 폴더에 소스 복사 보관

### 클라이언트 업데이트 사항 (v2.9.0c)
- **핸드셰이크 프로토콜 변경**: `deviceId` 필드 추가 (서버 대응 필요)
- **ping 이벤트 확장**: `monitoring` 필드 추가로 클라이언트 상태 모니터링 강화
- **인증 시스템 개선**: `deviceUUID`, `macAddress`, `ipAddress` 등 추가 인증 정보
- **미디어 전송 통합**: MEDIA_ 프로토콜과 IMAGE_BASE64 레거시 호환성

## 🏗️ 프로젝트 구조

```
server/
├── config/
│   ├── __init__.py
│   └── loader.py              # 설정 파일 로더 (암호화된 설정 파일 로드)
├── core/
│   ├── __init__.py
│   ├── client_handler.py      # 클라이언트 연결 처리
│   ├── globals.py             # 전역 변수 및 설정
│   ├── logger.py              # 로깅 모듈
│   ├── message_processor.py   # 메시지 처리 및 명령어 라우팅
│   ├── response_utils.py      # 응답 전송 유틸리티
│   └── server.py              # TCP 서버 구현
├── database/
│   ├── __init__.py
│   ├── connection.py          # 데이터베이스 연결 관리
│   └── db_utils.py            # 데이터베이스 유틸리티 (테이블 생성, 채팅 로그 저장)
├── logs/
│   └── server.log             # 서버 로그 파일
├── services/
│   ├── __init__.py
│   └── echo_service.py        # 에코 명령어 서비스
├── main.py                    # 메인 실행 파일
└── requirements.txt           # 의존성 패키지
```

## 🚀 현재 구현된 기능

### 1. TCP 서버 통신
- MessengerBotR 클라이언트와 TCP 소켓 통신
- 비동기 처리로 다중 클라이언트 연결 지원
- 자동 재연결 및 연결 상태 관리

### 2. 메시지 처리
- **analyze 이벤트**: 클라이언트로부터 받은 메시지 분석 및 명령어 처리
- **ping 이벤트**: 클라이언트 상태 확인 및 헬스체크
- JSON 기반 프로토콜로 구조화된 데이터 교환

### 3. 기본 명령어 시스템
- **에코 명령어**: `# echo {내용}` 형태의 명령어 처리 (현재 구현됨)
- 확장 가능한 서비스 아키텍처로 새로운 명령어 쉽게 추가 가능

### 4. 데이터베이스 연동
- MySQL 데이터베이스 연결 (aiomysql 사용)
- 채팅 로그 자동 저장 (`kb_chat_logs` 테이블)
- 비동기 데이터베이스 처리

### 5. 보안 기능
- 암호화된 설정 파일 로드 (Fernet 암호화)
- 클라이언트 인증 및 권한 관리

## 🎯 구현 예정 기능

### 1. 봇 및 방 관리 시스템
- DB 기반 봇 정의 및 설정 관리
- 채팅방별 권한 및 기능 설정
- 동적 봇 구성 변경

### 2. 공동구매 전용 기능
- 공동구매 상품 등록 및 관리
- 참여자 모집 및 관리
- 결제 및 배송 상태 관리
- 자동 알림 및 스케줄링

### 3. 스케줄링 발송 시스템
- domaeka 맞춤형 스케줄링 방식
- 공동구매 진행 상황별 자동 알림
- 시간 기반 메시지 발송

## 🔧 설치 및 실행

### 1. 의존성 설치
```bash
pip install -r requirements.txt
```

### 2. 설정 파일 준비
암호화된 설정 파일이 필요합니다:
- `.cfg/.kkobot.key`: 암호화 키 파일
- `.cfg/.kkobot.enc`: 암호화된 설정 파일

### 3. 서버 실행
```bash
# 개발 모드 (기본 포트 1490)
python main.py --mode=test

# 운영 모드 (포트 1491)
python main.py --mode=prod --port=1491

# 사용자 정의 포트
python main.py --port=1500
```

### 4. 실행 옵션
- `--port`: 서버 포트 번호 (기본값: 1490)
- `--mode`: 실행 모드 (`test` 또는 `prod`, 기본값: `test`)
  - `test`: 테스트 데이터베이스 사용 (`kkobot_test`)
  - `prod`: 운영 데이터베이스 사용 (`kkobot_prod`)

## 📡 클라이언트 통신 프로토콜

### 1. 메시지 형식
```json
{
  "event": "이벤트명",
  "data": {
    "필드1": "값1",
    "필드2": "값2"
  }
}
```

### 2. 지원 이벤트

#### analyze 이벤트 (클라이언트 → 서버)
카카오톡 메시지를 서버로 전송하여 분석 및 명령어 처리를 요청합니다.

```json
{
  "event": "analyze",
  "data": {
    "room": "채팅방 이름",
    "text": "메시지 내용",
    "sender": "발신자 이름",
    "isGroupChat": true,
    "channelId": "채널 ID",
    "logId": "메시지 로그 ID",
    "userHash": "사용자 해시",
    "isMention": false,
    "timestamp": "2024-01-01 12:00:00",
    "botName": "봇 이름",
    "clientType": "MessengerBotR",
    "auth": {
      "clientType": "MessengerBotR",
      "botName": "봇 이름",
      "deviceUUID": "디바이스 UUID",
      "macAddress": "MAC 주소",
      "ipAddress": "IP 주소",
      "timestamp": 1234567890123,
      "version": "2.9.0c",
      "signature": "HMAC 서명"
    }
  }
}
```

#### messageResponse 이벤트 (서버 → 클라이언트)
처리된 메시지를 클라이언트로 전송하여 카카오톡 채팅방에 메시지를 보내도록 합니다.

```json
{
  "event": "messageResponse",
  "data": {
    "room": "채팅방 이름",
    "text": "응답 메시지",
    "channel_id": "채널 ID"
  }
}
```

#### ping 이벤트 (양방향)
클라이언트와 서버 간 연결 상태를 확인합니다.

```json
{
  "event": "ping",
  "data": {
    "bot_name": "봇 이름",
    "channel_id": "채널 ID",
    "room": "채팅방 이름",
    "user_hash": "사용자 해시",
    "server_timestamp": "서버 타임스탬프",
    "client_status": {
      "cpu": null,
      "ram": {
        "used": 134217728,
        "max": 536870912,
        "usedMB": 128,
        "maxMB": 512
      },
      "temp": null
    },
    "monitoring": {
      "uptime": 3600000,
      "messageCount": 125,
      "lastActivity": "2024-01-01T12:00:00Z"
    },
    "is_manual": false,
    "auth": {
      "deviceUUID": "디바이스 UUID",
      "macAddress": "MAC 주소",
      "ipAddress": "IP 주소",
      "timestamp": 1234567890123,
      "signature": "HMAC 서명"
    }
  }
}
```

## 🗄️ 데이터베이스 구조

### 현재 구현된 테이블

#### kb_chat_logs 테이블
채팅 로그를 저장하는 테이블입니다. (kkobot과 동일한 구조)

| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| id | INT AUTO_INCREMENT | 기본키 |
| channel_id | VARCHAR(50) | 채널 ID |
| user_hash | VARCHAR(100) | 사용자 해시 |
| room_name | VARCHAR(255) | 채팅방 이름 |
| sender | VARCHAR(100) | 발신자 이름 |
| message | TEXT | 메시지 내용 |
| directive | VARCHAR(30) | 지시어(접두어) |
| message_type | VARCHAR(20) | 메시지 타입 (normal, command, auto_replay 등) |
| is_meaningful | TINYINT(1) | 의미 있는 메시지 여부 |
| bot_name | VARCHAR(30) | 봇 이름 |
| is_mention | TINYINT(1) | 멘션 여부 |
| is_group_chat | TINYINT(1) | 그룹채팅 여부 |
| log_id | VARCHAR(50) | 로그 ID |
| client_timestamp | DATETIME | 클라이언트 전송 시간 |
| server_timestamp | DATETIME | 서버 수신 시각 |
| is_bot | TINYINT(1) | sender가 bot인 경우 |
| is_our_bot_response | TINYINT(1) | 우리 봇이 생성한 응답 여부 |
| is_scheduled | TINYINT(1) | 스케줄링된 메시지 여부 |

### 구현 예정 테이블

#### 봇 정의 테이블 (kb_bots) - 추천안
| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| id | INT AUTO_INCREMENT | 기본키 |
| bot_name | VARCHAR(50) | 봇 이름 (고유값) |
| display_name | VARCHAR(100) | 표시용 봇 이름 |
| description | TEXT | 봇 설명 |
| is_active | TINYINT(1) | 활성화 상태 |
| created_at | TIMESTAMP | 생성 시간 |
| updated_at | TIMESTAMP | 수정 시간 |
| settings | JSON | 봇별 설정 (JSON 형태) |

#### 방 정의 테이블 (kb_rooms) - 추천안
| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| id | INT AUTO_INCREMENT | 기본키 |
| channel_id | VARCHAR(50) | 카카오톡 채널 ID (고유값) |
| room_name | VARCHAR(255) | 채팅방 이름 |
| bot_id | INT | 연결된 봇 ID (kb_bots.id) |
| is_group_chat | TINYINT(1) | 그룹채팅 여부 |
| is_active | TINYINT(1) | 봇 활성화 상태 |
| permissions | JSON | 방별 권한 설정 |
| created_at | TIMESTAMP | 등록 시간 |
| updated_at | TIMESTAMP | 수정 시간 |
| last_activity | TIMESTAMP | 마지막 활동 시간 |

## 🎯 명령어 시스템

### 에코 명령어
기본 제공되는 명령어로, 입력된 내용을 그대로 되돌려줍니다.

```
# echo 안녕하세요
→ 에코: 안녕하세요
```

### 새로운 명령어 추가
1. `services/` 디렉토리에 새로운 서비스 모듈 생성
2. `message_processor.py`에서 명령어 라우팅 로직 추가
3. 서비스 모듈에서 명령어 처리 로직 구현

## 🔍 로그 시스템

### 로그 레벨
- `DEBUG`: 상세한 디버깅 정보
- `INFO`: 일반적인 정보 메시지
- `WARNING`: 경고 메시지
- `ERROR`: 오류 메시지

### 로그 카테고리
- `[STARTUP]`: 서버 시작 관련
- `[SERVER]`: TCP 서버 관련
- `[CLIENT]`: 클라이언트 연결 관련
- `[MSG]`: 메시지 처리 관련
- `[DB]`: 데이터베이스 관련
- `[CONFIG]`: 설정 관련
- `[ECHO]`: 에코 명령어 관련
- `[PING]`: 핑 관련
- `[SHUTDOWN]`: 서버 종료 관련

## 🚨 오류 처리

### 연결 오류
- 클라이언트 연결 실패 시 자동 재시도
- 데이터베이스 연결 실패 시 안전한 종료
- 네트워크 오류 발생 시 로그 기록 및 복구

### 메시지 처리 오류
- JSON 파싱 실패 시 오류 로그 기록
- 알 수 없는 이벤트 수신 시 경고 메시지
- 데이터베이스 저장 실패 시 계속 진행

## 🔧 개발 정보

### 기술 스택
- **Python 3.7+**: 기본 런타임
- **asyncio**: 비동기 처리
- **aiomysql**: MySQL 비동기 드라이버
- **cryptography**: 설정 파일 암호화
- **PyMySQL**: MySQL 연결 지원

### 의존성
```
aiomysql==0.2.0
cryptography==45.0.3
PyMySQL>=1.0.2
```

### 버전 정보
- **현재 버전**: v1.0.0-lite
- **호환 클라이언트**: MessengerBotR v2.9.0c

## 🤝 클라이언트 연동

이 서버는 `client/messengerbotR/bridge.js`와 연동되어 작동합니다.

### 클라이언트 요구사항
- MessengerBotR v0.7.38a ~ v0.7.39a
- Rhino JavaScript Engine
- Android 카카오톡 앱

### 연결 흐름
1. **클라이언트 연결**: bridge.js가 TCP 소켓으로 서버에 연결
2. **핸드셰이크**: 봇 이름과 버전 정보 교환
3. **메시지 수신**: 카카오톡 메시지를 analyze 이벤트로 전송
4. **명령어 처리**: 서버에서 명령어 분석 및 처리
5. **응답 전송**: messageResponse 이벤트로 응답 메시지 전송
6. **메시지 출력**: 클라이언트가 카카오톡 채팅방에 메시지 출력

## 📝 개발 가이드

### 새로운 서비스 추가
1. `services/` 디렉토리에 새 모듈 생성
2. `message_processor.py`에서 명령어 조건 추가
3. 서비스 함수에서 `context` 객체 활용
4. `send_message_response`로 응답 전송

### 디버깅
- 로그 레벨을 `DEBUG`로 설정하여 상세 정보 확인
- `logs/server.log` 파일에서 실시간 로그 모니터링
- 클라이언트와 서버 간 JSON 메시지 교환 내역 확인

### 보안 고려사항
- 설정 파일은 반드시 암호화하여 관리
- 클라이언트 인증 정보 검증
- 민감한 정보는 로그에 기록하지 않음


### sql 

CREATE TABLE `kb_chat_logs` (
  `id` int(11) NOT NULL COMMENT '기본키',
  `channel_id` varchar(50) NOT NULL COMMENT '카카오톡 채널 ID (방 고유 ID)',
  `user_hash` varchar(100) NOT NULL COMMENT '해당 방 내 유저 고유 ID',
  `room_name` varchar(255) DEFAULT NULL COMMENT '카카오톡 방 이름 (변동 가능성 있음)',
  `sender` varchar(100) DEFAULT NULL COMMENT '보낸 사람 이름 (변동 가능성 있음)',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '메시지 내용',
  `directive` varchar(30) DEFAULT NULL COMMENT '지시어(접두어)',
  `message_type` varchar(20) NOT NULL COMMENT 'normal, command, auto_replay 등',
  `is_meaningful` tinyint(1) NOT NULL DEFAULT 0 COMMENT '의미 있는 메시지 여부',
  `bot_name` varchar(30) NOT NULL COMMENT '봇 이름',
  `is_mention` tinyint(1) NOT NULL DEFAULT 0 COMMENT '멘션 여부',
  `is_group_chat` tinyint(1) NOT NULL DEFAULT 0 COMMENT '그룹채팅 여부',
  `log_id` varchar(50) DEFAULT NULL COMMENT '메시지 고유 ID',
  `client_timestamp` datetime DEFAULT NULL COMMENT '클라이언트 전송 시간',
  `server_timestamp` datetime DEFAULT current_timestamp() COMMENT '서버 수신 시각',
  `is_bot` tinyint(1) DEFAULT 0 COMMENT 'sender가 bot인 경우',
  `is_our_bot_response` tinyint(1) NOT NULL DEFAULT 0 COMMENT '우리 봇이 생성한 응답 여부',
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '스케줄링된 메시지 여부'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='카카오봇 대화 로그 테이블';

--
-- 덤프된 테이블의 인덱스
--

--
-- 테이블의 인덱스 `kb_chat_logs`
--
ALTER TABLE `kb_chat_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_channel_user_time` (`channel_id`,`user_hash`,`server_timestamp`),
  ADD KEY `idx_meaningful` (`is_meaningful`);

--
-- 덤프된 테이블의 AUTO_INCREMENT
--

--
-- 테이블의 AUTO_INCREMENT `kb_chat_logs`
--
ALTER TABLE `kb_chat_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '기본키';
COMMIT;

---

## 📚 개발 참고사항

### kkobot 시스템과의 호환성
- 클라이언트-서버 연결 방식과 메시지 구조는 kkobot과 완전히 동일
- 언제든 kkobot 시스템과 상호 호환 가능하도록 설계
- 기존 kkobot 서버 코드는 `@server-kkobot` 폴더에서 참조 가능

### 다음 단계 작업
1. **클라이언트 프로토콜 업데이트 대응** - deviceId, monitoring 필드 처리
2. **봇 정의 테이블과 방 테이블 생성** - 환경 설정을 위한 기본 구조
3. **동적 봇 관리 시스템** - DB 기반 봇 설정 관리
4. **공동구매 전용 서비스** - domaeka 비즈니스 로직 구현
5. **스케줄링 시스템** - domaeka 맞춤형 자동 알림

### 클라이언트 변경사항 대응 필요
- **핸드셰이크**: `deviceId` 필드 처리를 위한 서버 코드 수정
- **ping 이벤트**: `monitoring` 필드와 강화된 `auth` 정보 처리
- **인증 시스템**: HMAC 서명 검증 및 추가 디바이스 정보 활용
- **에러 처리**: 새로운 필드들에 대한 검증 로직 추가

### 테이블 추천안
위에서 제시한 `kb_bots`와 `kb_rooms` 테이블 구조를 검토하여 domaeka 요구사항에 맞게 수정 후 구현 예정입니다.

## 📞 지원 및 문의

이 서버는 domaeka 공동구매 시스템을 위한 전용 카카오봇 서버입니다.

- **프로젝트**: domaeka 공동구매 시스템
- **기반 기술**: kkobot 시스템 호환
- **버전**: v1.0.0-domaeka
- **호환성**: MessengerBotR v2.9.0c



