# MessengerBotR Bridge v3.2.1 업데이트 가이드

## 변경사항

### v3.2.1 (2024-01-23)
- **서버 지정 미디어 전송 대기시간 지원** 추가
  - 서버에서 `media_wait_time` 파라미터를 통해 미디어 전송 후 대기시간을 지정할 수 있습니다.
  - 중요한 미디어 전송 시 안정성을 위해 대기시간을 늘릴 수 있습니다.

## 서버 개발자를 위한 가이드

### 서버 지정 대기시간 사용법

기존 미디어 전송 응답에 `media_wait_time` 필드를 추가하면 됩니다.

#### 예시 1: 단일 이미지 전송 (기본 대기시간)
```json
{
  "event": "messageResponse",
  "data": {
    "room": "테스트방",
    "text": "MEDIA_URL:https://example.com/image.jpg",
    "channel_id": "123456789"
  }
}
```

#### 예시 2: 단일 이미지 전송 (서버 지정 대기시간 8초)
```json
{
  "event": "messageResponse",
  "data": {
    "room": "테스트방",
    "text": "MEDIA_URL:https://example.com/image.jpg",
    "channel_id": "123456789",
    "media_wait_time": 8000
  }
}
```

#### 예시 3: 멀티 이미지 전송 (서버 지정 대기시간 12초)
```json
{
  "event": "messageResponse",
  "data": {
    "room": "테스트방",
    "text": "MEDIA_URL:https://example.com/image1.jpg|||https://example.com/image2.jpg|||https://example.com/image3.jpg",
    "channel_id": "123456789",
    "media_wait_time": 12000
  }
}
```

### 대기시간 가이드라인

| 상황 | 권장 대기시간 | 설명 |
|------|---------------|------|
| 작은 이미지 (< 1MB) | 4000-6000ms | 클라이언트 기본값 사용 |
| 큰 이미지 (> 5MB) | 8000-10000ms | 서버에서 지정 권장 |
| 여러 파일 (3개 이상) | 10000-15000ms | 파일 수에 따라 조정 |
| 중요한 공지사항 이미지 | 10000-15000ms | 안정성을 위해 넉넉하게 |
| 동영상 파일 | 15000-20000ms | 크기에 따라 조정 |

### 하위 호환성

- `media_wait_time`이 없으면 클라이언트의 기본 로직이 작동합니다.
- 기존 서버 코드는 수정 없이 그대로 사용 가능합니다.
- v3.2.0 이하 버전의 클라이언트는 이 필드를 무시합니다.

### 주의사항

1. **대기시간 단위**: 밀리초(ms) 단위로 지정합니다.
2. **유효성 검사**: 숫자형이며 0보다 큰 값만 적용됩니다.
3. **과도한 대기시간**: 너무 긴 대기시간(30초 이상)은 사용자 경험을 해칠 수 있습니다.

### Python 서버 예시

```python
# 일반 이미지 전송
def send_image(room_name, image_url, channel_id):
    return {
        "event": "messageResponse",
        "data": {
            "room": room_name,
            "text": f"MEDIA_URL:{image_url}",
            "channel_id": channel_id
        }
    }

# 중요한 이미지 전송 (대기시간 지정)
def send_important_image(room_name, image_url, channel_id):
    return {
        "event": "messageResponse",
        "data": {
            "room": room_name,
            "text": f"MEDIA_URL:{image_url}",
            "channel_id": channel_id,
            "media_wait_time": 10000  # 10초 대기
        }
    }

# 대용량 파일 전송
def send_large_file(room_name, file_url, channel_id, file_size_mb):
    # 파일 크기에 따라 대기시간 계산
    wait_time = 5000 + (file_size_mb * 2000)  # 기본 5초 + MB당 2초
    wait_time = min(wait_time, 20000)  # 최대 20초로 제한
    
    return {
        "event": "messageResponse",
        "data": {
            "room": room_name,
            "text": f"MEDIA_URL:{file_url}",
            "channel_id": channel_id,
            "media_wait_time": int(wait_time)
        }
    }
```

## 클라이언트 로그 확인

서버 지정 대기시간이 적용되면 다음과 같은 로그가 출력됩니다:

```
[MEDIA] 미디어 전송 시작: 1개 (서버 지정 대기시간: 8000ms)
[MEDIA] 서버 지정 대기시간 사용: 8000ms
```

클라이언트 기본 대기시간이 사용되면:

```
[MEDIA] 미디어 전송 시작: 1개
[MEDIA] 단일 파일 전송 대기 (클라이언트 계산): 4500ms
```