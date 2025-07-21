# MessengerBotR Bridge v3.3.0 - 핵심 스펙

## 개요

v3.3.0은 JSON + Raw 데이터 구조를 도입하여 대용량 미디어 처리 성능을 극대화하는 새로운 프로토콜입니다.

**핵심 개선사항**:
- 99.9% JSON 파싱 부하 감소 (15MB → 200bytes)
- 통일된 메시지 구조 (텍스트/이미지/오디오/비디오/문서)
- Base64 인코딩을 통한 보안 강화

## 프로토콜 적용 범위

### 새 프로토콜 적용 이벤트 (JSON + Raw)
- **message** (클라이언트 → 서버): 사용자 메시지
- **messageResponse** (서버 → 클라이언트): 봇 응답
- **scheduleMessage** (서버 → 클라이언트): 예약 메시지
- **broadcastMessage** (서버 → 클라이언트): 방송 메시지

### 기존 JSON 유지 이벤트
- **handshake**: 연결 인증 (v3.2.1+ 스펙 유지)
- **ping**: 상태 모니터링 (v3.2.1+ 스펙 유지)
- **error**: 오류 처리
- **status**: 봇 상태

## 패킷 구조

### 기본 구조
```
[JSON 헤더] + [Raw 데이터]
{"event":"messageResponse",...}Base64EncodedContent
```

### JSON 헤더 필드
```json
{
    "event": "messageResponse",
    "data": {
        "room": "채팅방명",
        "channel_id": "12345",
        "message_type": "text|image|audio|video|document",
        "message_format": "jpg|mp3|mp4|pdf",  // 미디어만
        "content_encoding": "base64",  // 텍스트 권장
        "message_positions": [0, 100],  // [시작, 끝] 또는 [시작1, 시작2, ..., 끝]
        "media_wait_time": 3000,  // 미디어 전송 대기시간(ms)
        "timestamp": "2025-07-21T12:12:11Z",  // UTC
        "timezone": "Asia/Seoul"
    }
}
```

### 메시지 타입
```javascript
MESSAGE_TYPES = {
    TEXT: "text",
    IMAGE: "image", 
    AUDIO: "audio",
    VIDEO: "video",
    DOCUMENT: "document"
}
```

## 패킷 예시

### 텍스트 메시지 (서버 → 클라이언트)
```
[JSON]
{
    "event": "messageResponse",
    "data": {
        "room": "테스트방",
        "channel_id": "12345",
        "message_type": "text",
        "content_encoding": "base64",
        "message_positions": [0, 60],
        "timestamp": "2025-07-21T12:12:11Z",
        "timezone": "Asia/Seoul"
    }
}
[Base64 인코딩된 텍스트]
7JWI64WV7ZWY7IS47JqULiDsmKTripgg7KCA64WBIOuplOuJtOuKlCDrrK3qsIDsmpQ/
```

### 단일 이미지 (서버 → 클라이언트)
```
[JSON]
{
    "event": "messageResponse",
    "data": {
        "room": "사진방",
        "channel_id": "67890", 
        "message_type": "image",
        "message_format": "jpg",
        "message_positions": [0, 1024000],
        "media_wait_time": 3000,
        "timestamp": "2025-07-21T12:12:11Z",
        "timezone": "Asia/Seoul"
    }
}
[Base64 이미지 데이터]
iVBORw0KGgoAAAANS...
```

### 멀티 이미지 (3개)
```
[JSON]
{
    "event": "messageResponse",
    "data": {
        "room": "사진방",
        "message_type": "image", 
        "message_format": "jpg",
        "message_positions": [0, 1024000, 2048000, 3072000],
        "media_wait_time": 9000
    }
}
[연속된 Base64 데이터 - 구분자 없음]
이미지1데이터이미지2데이터이미지3데이터
```

### 사용자 메시지 (클라이언트 → 서버)
```
[JSON]
{
    "event": "message",
    "data": {
        "room": "테스트방",
        "channel_id": "12345",
        "message_type": "text",
        "content_encoding": "base64",
        "message_positions": [0, 0],  // 클라이언트는 정확한 길이 계산 어려움
        "timestamp": "2025-07-21T12:12:11Z",
        "timezone": "Asia/Seoul",
        
        // 기존 호환성 필드
        "sender": "사용자명",
        "is_group_chat": true,
        "log_id": "9876543210",
        "user_hash": "abc123",
        "is_mention": false,
        "bot_name": "LOA.i",
        "client_type": "MessengerBotR",
        "auth": { /* 인증 데이터 */ }
    }
}
[Base64 인코딩된 메시지]
7JWI64WV7ZWY7IS47JqU...
```

## 핵심 구현 로직

### 클라이언트: 메시지 수신 처리
```javascript
function handleServerResponse(rawMsg) {
    // JSON 끝 위치 찾기
    var jsonEndIndex = rawMsg.lastIndexOf('}');
    var jsonPart = rawMsg.substring(0, jsonEndIndex + 1);
    var packet = JSON.parse(jsonPart);
    
    // 새 프로토콜 이벤트 확인
    var newProtocolEvents = ["messageResponse", "scheduleMessage", "broadcastMessage"];
    
    if (packet.data && packet.data.message_positions && 
        newProtocolEvents.indexOf(packet.event) !== -1) {
        
        var baseOffset = jsonEndIndex + 1;
        var positions = packet.data.message_positions;
        
        if (positions.length === 2) {
            // 단일 메시지: 끝 위치 무시하고 전체 사용
            var messageData = rawMsg.substring(baseOffset);
            if (messageData.endsWith('\n')) {
                messageData = messageData.substring(0, messageData.length - 1);
            }
            handleSingleMessage(packet.data, messageData);
        } else {
            // 멀티 메시지: 위치 배열로 분할
            handleMultiMessage(packet.data, rawMsg, baseOffset);
        }
    } else {
        // 기존 이벤트 (handshake, ping 등)
        handleOtherEvents(packet);
    }
}

function handleSingleMessage(data, content) {
    switch(data.message_type) {
        case "text":
            if (data.content_encoding === "base64") {
                content = Base64.decode(content);
            }
            bot.send(data.room, content);
            break;
        case "image":
            MediaHandler.processImage(data, content);
            break;
    }
}
```

### 클라이언트: 메시지 전송
```javascript
function sendMessage(event, data, messageContent) {
    if (messageContent) {
        data.message_positions = [0, messageContent.length];
        var packet = JSON.stringify({event: event, data: data});
        var fullPacket = packet + messageContent + "\n";
        outputStream.write(fullPacket);
    } else {
        var jsonStr = JSON.stringify({event: event, data: data}) + "\n";
        outputStream.write(jsonStr);
    }
    outputStream.flush();
}

// 사용자 메시지 전송
function onMessage(msg) {
    var messageData = {
        room: msg.room,
        channel_id: msg.channelId ? msg.channelId.toString() : null,
        message_type: "text",
        content_encoding: "base64",
        message_positions: [0, 0],
        timestamp: Utils.formatTimestamp(new Date()),
        timezone: "Asia/Seoul",
        
        sender: msg.author.name,
        is_group_chat: msg.isGroupChat,
        // ... 기타 필드
    };
    
    var encodedContent = Base64.encode(msg.content);
    sendMessage("message", messageData, encodedContent);
}
```

### 서버: 패킷 생성 (Python)
```python
def create_message_packet(room_name, channel_id, message_type, content, **kwargs):
    packet = {
        "event": "messageResponse",
        "data": {
            "room": room_name,
            "channel_id": channel_id,
            "message_type": message_type,
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "timezone": "Asia/Seoul"
        }
    }
    
    # 텍스트는 Base64 인코딩 권장
    if message_type == "text":
        content_bytes = base64.b64encode(content.encode('utf-8'))
        packet["data"]["content_encoding"] = "base64"
    else:
        content_bytes = content.encode('utf-8')
    
    packet["data"]["message_positions"] = [0, len(content_bytes)]
    
    # 추가 메타데이터
    for key, value in kwargs.items():
        packet["data"][key] = value
    
    json_str = json.dumps(packet, ensure_ascii=False)
    return json_str.encode('utf-8') + content_bytes + b"\n"
```

## 마이그레이션 체크리스트

### Breaking Changes (v3.2.x → v3.3.0)
- [ ] **analyze 이벤트 → message 이벤트 변경**
- [ ] **IMAGE_BASE64: 프리픽스 방식 완전 제거**
- [ ] **모든 텍스트 메시지 Base64 인코딩 필수**
- [ ] **UTC 타임스탬프 + timezone 필드 추가**
- [ ] **새로운 JSON + Raw 데이터 구조 적용**

### 클라이언트 수정사항
```javascript
// 제거할 코드
if (text.startsWith("IMAGE_BASE64:")) { ... }  // ❌

// 새로운 코드  
if (data.message_type === "image") { ... }     // ✅
```

### 서버 수정사항
- [ ] analyze 핸들러를 message 핸들러로 변경
- [ ] 모든 응답을 새 패킷 구조로 생성
- [ ] Base64 인코딩/디코딩 로직 추가

## 보안 고려사항

### JSON 파싱 위험성 해결
```javascript
// 위험한 텍스트 예시
사용자메시지: function(){return{}}}}}

// lastIndexOf('}')가 잘못된 위치를 찾을 수 있음!
```

**해결책**: 모든 텍스트 메시지 Base64 인코딩 필수
- JSON 특수문자 완전 제거
- 유니코드 완벽 지원
- 크로스 플랫폼 호환성

## 성능 향상

| 항목 | 기존 (v3.2.x) | 개선 (v3.3.0) | 향상률 |
|------|----------------|----------------|--------|
| JSON 파싱 | 15MB 전체 | 200bytes만 | 99.9% 감소 |
| 처리 시간 | 3-5초 | 0.01초 미만 | 300-500배 |
| 메모리 사용 | 2배 (JSON+원본) | 분리 처리 | 50% 감소 |
| 데이터 추출 | O(n) 순회 | O(1) 직접 | 크기 무관 |

## 로깅 가이드

### 텍스트 메시지
```javascript
// Base64 디코딩 후 최대 1000바이트 출력
var decodedText = Base64.decode(content);
if (decodedText.length > 1000) {
    decodedText = decodedText.substring(0, 1000) + "... (truncated)";
}
Log.i("[MESSAGE] 텍스트: " + decodedText);
```

### 미디어 메시지
```javascript
// 개수와 크기 정보만 출력 (실제 데이터 제외)
var imageCount = positions.length - 1;
var totalSize = Math.round(positions[positions.length - 1] / 1024);
Log.i("[MESSAGE] 이미지: " + imageCount + "개, " + totalSize + "KB");
```

## 호환성 참고

- **Handshake**: v3.2.1+ 스펙 그대로 유지
- **Ping**: v3.2.1+ 스펙 그대로 유지  
- **Error**: 기존 JSON 방식 유지
- **메시지 통신**: v3.3.0 새 프로토콜만 사용

---

**버전**: v3.3.0  
**상태**: 개발 중 (Breaking Changes 포함)  
**관련 문서**: bridge-v3.2.1+.md (handshake/ping 스펙) 