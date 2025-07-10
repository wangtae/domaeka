# Server-Lite - 카카오톡 봇 서버

MessengerBotR 클라이언트와 TCP 소켓 통신을 통해 카카오톡 봇 서비스를 제공하는 경량 Python 서버입니다.

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

## 🚀 주요 기능

### 1. TCP 서버 통신
- MessengerBotR 클라이언트와 TCP 소켓 통신
- 비동기 처리로 다중 클라이언트 연결 지원
- 자동 재연결 및 연결 상태 관리

### 2. 메시지 처리
- **analyze 이벤트**: 클라이언트로부터 받은 메시지 분석 및 명령어 처리
- **ping 이벤트**: 클라이언트 상태 확인 및 헬스체크
- JSON 기반 프로토콜로 구조화된 데이터 교환

### 3. 명령어 시스템
- **에코 명령어**: `# echo {내용}` 형태의 명령어 처리
- 확장 가능한 서비스 아키텍처로 새로운 명령어 쉽게 추가 가능

### 4. 데이터베이스 연동
- MySQL 데이터베이스 연결 (aiomysql 사용)
- 채팅 로그 자동 저장 (`kb_chat_logs` 테이블)
- 비동기 데이터베이스 처리

### 5. 보안 기능
- 암호화된 설정 파일 로드 (Fernet 암호화)
- 클라이언트 인증 및 권한 관리

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
    "userHash": "사용자 해시",
    "timestamp": "2024-01-01 12:00:00",
    "botName": "봇 이름",
    "clientType": "MessengerBotR",
    "auth": { /* 인증 정보 */ }
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
      "ram": null,
      "temp": null
    },
    "is_manual": false
  }
}
```

## 🗄️ 데이터베이스 구조

### kb_chat_logs 테이블
채팅 로그를 저장하는 테이블입니다.

| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| id | INT AUTO_INCREMENT | 기본키 |
| channel_id | VARCHAR(50) | 채널 ID |
| user_hash | VARCHAR(100) | 사용자 해시 |
| room_name | VARCHAR(255) | 채팅방 이름 |
| sender | VARCHAR(100) | 발신자 이름 |
| message | TEXT | 메시지 내용 |
| bot_name | VARCHAR(30) | 봇 이름 |
| log_id | VARCHAR(50) | 로그 ID |
| created_at | TIMESTAMP | 생성 시간 |

### 인덱스
- `idx_channel_id`: 채널 ID 인덱스
- `idx_user_hash`: 사용자 해시 인덱스
- `idx_room_name`: 채팅방 이름 인덱스
- `idx_created_at`: 생성 시간 인덱스

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

## 📞 지원 및 문의

이 서버는 kkobot.com의 MessengerBotR 봇 시스템과 연동되어 작동합니다.

- **버전**: v1.0.0-lite
- **호환성**: MessengerBotR v2.9.0c
- **라이선스**: 내부 프로젝트용



