# MessengerBotR Bridge v3.5.0 - 프로토콜 재설계

## 메시지 패킷 구조 정의 (bridge-v3.2.1+.js 기준)

### 1. **Handshake 패킷** (클라이언트 → 서버)
연결 초기화 시 클라이언트가 서버로 전송하는 첫 번째 메시지 (강화된 핸드셰이크 시스템)

```json
{
  "clientType": "MessengerBotR",     // 클라이언트 타입 (v3.2.0 추가)
  "botName": "LOA.i",                // 봇 이름
  "version": "3.2.1",                // 클라이언트 버전  
  "deviceID": "ccbd8eee1012327e",    // 안드로이드 디바이스 ID
  "deviceIP": "192.168.1.100",       // 클라이언트 IP 주소 (소켓 로컬 주소)
  "deviceInfo": "samsung SM-G991N (Android 13, API 33)" // 디바이스 정보
}
```
**참고**: 
- JSON 문자열 + "\n"으로 전송
- deviceInfo: "{brand} {model} (Android {version}, API {sdk})" 형식
- v3.2.0부터 kb_bot_devices 테이블 연동을 위한 강화된 인증

### 2. **Analyze 이벤트** (클라이언트 → 서버)
카카오톡 메시지를 서버로 전달하여 분석 요청

```json
{
  "event": "analyze",
  "data": {
    "room": "채팅방 이름",
    "text": "메시지 내용",
    "sender": "발신자 이름",
    "isGroupChat": true,
    "channelId": "1234567890",    // 채팅방 ID (문자열)
    "logId": "9876543210",        // 메시지 로그 ID (문자열)
    "userHash": "abc123def456",   // 사용자 해시
    "isMention": false,           // 멘션 여부
    "timestamp": "2025-01-20 15:30:45",
    "botName": "LOA.i",
    "clientType": "MessengerBotR",
    "auth": {                     // 인증 정보 (Auth.createAuthData())
      "clientType": "MessengerBotR",
      "botName": "LOA.i",
      "deviceUUID": "uuid-string",
      "deviceID": "ccbd8eee1012327e",  // Android ID (DeviceInfo.getAndroidId())
      "macAddress": "AA:BB:CC:DD:EE:FF",
      "ipAddress": "192.168.1.100",
      "timestamp": 1234567890000,
      "version": "3.2.1",
      "signature": "HMAC-SHA256 서명값"
    }
  }
}
```

### 3. **Message Response 이벤트** (서버 → 클라이언트)
서버가 클라이언트로 메시지 전송 요청

```json
{
  "event": "messageResponse",
  "data": {
    "room": "채팅방 이름",
    "text": "응답 메시지",
    "channel_id": "1234567890",
    "type": "text"               // text, image, file 등
  }
}
```

### 4. **Ping 이벤트** (양방향)

#### 4-1. 서버 → 클라이언트 (Ping 요청)
```json
{
  "event": "ping",
  "data": {
    "bot_name": "LOA.i",
    "server_timestamp": 1234567890000
  }
}
```

#### 4-2. 클라이언트 → 서버 (Ping 응답)
```json
{
  "event": "ping",
  "data": {
    "bot_name": "LOA.i",
    "server_timestamp": 1234567890000,
    "monitoring": {              // 모니터링 데이터 (MONITORING_ENABLED=true일 때)
      "total_memory": 2048.0,    // MB 단위 (maxMemory)
      "memory_usage": 512.5,     // MB 단위 (usedMemory)
      "memory_percent": 25.0,    // 메모리 사용률 %
      "message_queue_size": 5,   // 메시지 큐 크기
      "active_rooms": 10         // 활성 채팅방 수
    },
    "auth": {                    // 인증 정보 (Auth.createAuthData())
      "clientType": "MessengerBotR",
      "botName": "LOA.i",
      "deviceUUID": "uuid-string",
      "deviceID": "ccbd8eee1012327e",
      "macAddress": "AA:BB:CC:DD:EE:FF",
      "ipAddress": "192.168.1.100",
      "timestamp": 1234567890000,
      "version": "3.2.1",
      "signature": "HMAC-SHA256 서명값"
    }
  }
}
```

### 5. **Schedule 이벤트** (서버 → 클라이언트)
예약된 메시지 전송 (messageResponse와 동일한 구조)

```json
{
  "event": "messageResponse",
  "data": {
    "room": "채팅방 이름",
    "text": "예약 메시지 내용",
    "channel_id": "1234567890"
  }
}
```
**참고**: 스케줄된 메시지도 일반 messageResponse로 전송됨

### 6. **Media 관련 이벤트**

미디어 전송은 messageResponse 이벤트의 특수한 형태로 처리됩니다.

#### 6-1. URL 기반 미디어 전송 (서버 → 클라이언트)
```json
{
  "event": "messageResponse",
  "data": {
    "room": "채팅방 이름",
    "text": "MEDIA_URL:https://example.com/image1.jpg|||https://example.com/image2.jpg",
    "channel_id": "1234567890",
    "media_wait_time": 8000  // 선택적, v3.2.1 추가 - 서버 지정 대기시간 (ms)
  }
}
```

#### 6-2. Base64 기반 이미지 전송 (서버 → 클라이언트)
```json
{
  "event": "messageResponse",
  "data": {
    "room": "채팅방 이름",
    "text": "IMAGE_BASE64:data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "channel_id": "1234567890",
    "media_wait_time": 8000  // 선택적, v3.2.1 추가 - 서버 지정 대기시간 (ms)
  }
}
```

**참고**: 
- `MEDIA_URL:` 접두사: URL 기반 미디어 전송
- `IMAGE_BASE64:` 접두사: Base64 인코딩된 이미지 전송
- 여러 파일은 `|||`로 구분
- MediaHandler 모듈이 자동으로 처리
- `media_wait_time` (v3.2.1): 서버가 지정한 미디어 전송 대기시간 (선택적)

### 7. **Error 이벤트** (서버 → 클라이언트)
오류 발생 시 전송

```json
{
  "event": "error",
  "data": {
    "code": "AUTH_FAILED",
    "message": "인증 실패: 서명이 올바르지 않습니다",
    "timestamp": 1234567890000
  }
}
```

## 인증 구조 (auth)

모든 클라이언트 → 서버 메시지에 포함되는 인증 정보 (Auth.createAuthData() 함수로 생성):

```json
{
  "clientType": "MessengerBotR",   // 클라이언트 타입
  "botName": "LOA.i",              // 봇 이름
  "deviceUUID": "uuid-string",     // 디바이스 UUID
  "deviceID": "ccbd8eee1012327e",  // Android ID
  "macAddress": "AA:BB:CC:DD:EE:FF", // MAC 주소
  "ipAddress": "192.168.1.100",   // 로컬 IP 주소
  "timestamp": 1234567890000,      // 현재 시간 (밀리초)
  "version": "3.2.1",              // 클라이언트 버전
  "signature": "a1b2c3d4..."       // HMAC-SHA256 서명
}
```

**서명 생성 방식** (v3.2.0부터 clientType 추가):
```javascript
var signString = [clientType, botName, deviceUUID, macAddress, ipAddress, timestamp, BOT_SPECIFIC_SALT].join('|');
signature = HMAC-SHA256(signString, SECRET_KEY);
```

## 프로토콜 특징

1. **JSON 기반**: 모든 메시지는 JSON 형식으로 인코딩
2. **줄바꿈 구분**: 각 메시지는 `\n`으로 구분
3. **UTF-8 인코딩**: 모든 텍스트는 UTF-8로 인코딩
4. **이벤트 기반**: 모든 메시지는 `event`와 `data` 필드를 포함
5. **인증 필수**: 클라이언트 → 서버 메시지는 반드시 인증 정보 포함

## 연결 흐름

```
1. 클라이언트 → 서버: TCP 연결
2. 클라이언트 → 서버: Handshake 패킷
3. 서버 → 클라이언트: Ping 요청 (주기적)
4. 클라이언트 → 서버: Ping 응답
5. 클라이언트 → 서버: Analyze 이벤트 (메시지 수신 시)
6. 서버 → 클라이언트: Message Response (응답 필요 시)
```

## 주요 버전별 변경사항

### v3.2.1 (2025-01)
- 서버 지정 미디어 전송 대기시간 지원 (`media_wait_time`)
- messageResponse에 선택적 media_wait_time 필드 추가

### v3.2.0 (2025-01)
- 강화된 핸드셰이크 인증 시스템
- handshake에 clientType, deviceIP, deviceInfo 필드 추가
- kb_bot_devices 테이블 연동
- auth 서명에 clientType 추가

### v3.1.4 (이전)
- 기본 핸드셰이크 (botName, version, deviceID)
- 기본 인증 구조

---
*작성일: 2025-01-20*
*최종 수정일: 2025-01-20*
*기준 버전: bridge-v3.2.1+.js*