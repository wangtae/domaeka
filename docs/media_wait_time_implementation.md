# 서버 지정 미디어 대기시간 구현 완료

## 개요
MessengerBotR 클라이언트 v3.2.1에 서버에서 미디어 전송 후 대기시간을 지정할 수 있는 기능이 구현되었습니다.

## 구현 내용

### 1. 클라이언트 변경사항 (bridge-v3.2.1.js)

#### MediaHandler.handleMediaResponse 수정
```javascript
function handleMediaResponse(data) {
    var messageText = data.text;
    var roomName = data.room;
    var channelId = data.channel_id;
    var sources = [];
    // 서버에서 지정한 대기 시간 (옵션)
    var serverWaitTime = data.media_wait_time || null;
    // ... 기존 코드 ...
    send(channelId, sources, serverWaitTime);
}
```

#### MediaHandler.send 수정
```javascript
function send(channelId, sources, serverWaitTime) {
    // ... 파일 처리 코드 ...
    
    var waitTime;
    
    // 서버에서 지정한 대기 시간이 있으면 우선 사용
    if (serverWaitTime && typeof serverWaitTime === 'number' && serverWaitTime > 0) {
        waitTime = serverWaitTime;
        if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
            Log.i("[MEDIA] 서버 지정 대기시간 사용: " + waitTime + "ms");
        }
    } else {
        // 서버 지정 대기시간이 없으면 클라이언트 기본 로직 사용
        if (isMultiple) {
            waitTime = _calculateMultiFileWaitTime(processedFiles);
            if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                Log.i("[MEDIA] 멀티 파일 전송 대기 (클라이언트 계산): " + processedFiles.length + "개 파일, " + waitTime + "ms");
            }
        } else {
            waitTime = _calculateWaitTime(processedFiles[0].path);
            if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                Log.i("[MEDIA] 단일 파일 전송 대기 (클라이언트 계산): " + waitTime + "ms");
            }
        }
    }
    
    java.lang.Thread.sleep(waitTime);
    // ... 나머지 코드 ...
}
```

### 2. 서버 사용 예시

#### 기본 사용법
```python
# 클라이언트 기본 대기시간 사용
response = {
    "event": "messageResponse",
    "data": {
        "room": "채팅방",
        "text": "MEDIA_URL:https://example.com/image.jpg",
        "channel_id": "123456"
    }
}

# 서버 지정 대기시간 사용 (10초)
response = {
    "event": "messageResponse",
    "data": {
        "room": "채팅방",
        "text": "MEDIA_URL:https://example.com/image.jpg",
        "channel_id": "123456",
        "media_wait_time": 10000  # 10초 (밀리초)
    }
}
```

### 3. 주요 특징

1. **하위 호환성**: media_wait_time이 없으면 기존 클라이언트 로직 사용
2. **우선순위**: 서버 지정 값이 클라이언트 계산값보다 우선
3. **유효성 검사**: 숫자형이며 0보다 큰 값만 적용
4. **로깅 지원**: 서버/클라이언트 대기시간 구분하여 로그 출력

### 4. 권장 대기시간

| 상황 | 권장 대기시간 | 설명 |
|------|---------------|------|
| 작은 이미지 (< 1MB) | 4000-6000ms | 클라이언트 기본값 사용 |
| 큰 이미지 (> 5MB) | 8000-10000ms | 서버에서 지정 권장 |
| 여러 파일 (3개 이상) | 10000-15000ms | 파일 수에 따라 조정 |
| 중요한 공지사항 | 10000-15000ms | 안정성을 위해 넉넉하게 |
| 동영상 파일 | 15000-20000ms | 크기에 따라 조정 |

### 5. 테스트 서비스

`server/services/media_test_service.py`에 다양한 사용 예시가 구현되어 있습니다:
- 기본 이미지 전송
- 서버 지정 대기시간 테스트
- 멀티 이미지 전송
- 중요 공지사항 전송
- 대용량 파일 전송 (크기 기반 계산)

## 버전 정보
- 클라이언트 버전: v3.2.1
- 구현일: 2024-01-23
- 변경 파일:
  - `/client/messengerbotR/bridge-v3.2.1.js`
  - `/client/messengerbotR/README_v3.2.1.md`
  - `/server/services/media_test_service.py` (테스트용)