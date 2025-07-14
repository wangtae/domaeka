# 확장 가능한 명령어 파라미터 시스템 구현

## 개요
server-kkobot의 명령어 정의 구조를 참고하여 확장 가능한 명령어 파라미터 시스템을 구현했습니다.

## 구현된 기능

### 1. 명령어 정의 시스템 (`core/command_definitions.py`)

#### 주요 특징
- 각 명령어별로 파라미터를 정의할 수 있는 구조
- 파라미터 타입, 필수 여부, 최소/최대값 등 상세 설정 가능
- 자동 파라미터 파싱 및 유효성 검증

#### 명령어 정의 예시
```python
COMMAND_DEFINITIONS = {
    "# IMGEXT": {
        "type": "multi_image_generator",
        "desc": "텍스트를 단어별로 이미지로 변환하여 멀티 이미지 전송",
        "parameters": {
            "media-wait-time": {
                "description": "미디어 전송 후 대기시간 (밀리초)",
                "required": False,
                "type": "int",
                "min": 1000,
                "max": 30000,
                "default": None
            }
        }
    }
}
```

### 2. IMGEXT 명령어 확장

#### 기본 사용법
```
# IMGEXT 1 2 3 4 5
```

#### 서버 지정 대기시간 사용법
```
# IMGEXT --media-wait-time=8000 1 2 3 4 5
```

#### 구현 세부사항
- `image_multi_service.py`에서 새로운 파라미터 시스템 사용
- `parse_command_parameters()` 함수로 자동 파싱
- `validate_command_parameters()` 함수로 유효성 검증
- 1000ms ~ 30000ms 범위 내에서 대기시간 설정 가능

### 3. 응답 시스템 확장 (`core/response_utils.py`)

#### send_message_response 함수 확장
```python
async def send_message_response(
    context, 
    message, 
    room=None, 
    channel_id=None, 
    media_wait_time=None  # 새로 추가된 파라미터
):
```

#### 패킷 구조
```json
{
  "event": "messageResponse",
  "data": {
    "room": "테스트방",
    "text": "IMAGE_BASE64:base64data1|||base64data2",
    "channel_id": "123456",
    "media_wait_time": 8000  // 조건부 포함
  }
}
```

### 4. 클라이언트 호환성 (bridge-v3.2.1.js)

#### 서버 지정 대기시간 처리
```javascript
// 서버에서 지정한 대기 시간이 있으면 우선 사용
if (serverWaitTime && typeof serverWaitTime === 'number' && serverWaitTime > 0) {
    waitTime = serverWaitTime;
    Log.i("[MEDIA] 서버 지정 대기시간 사용: " + waitTime + "ms");
} else {
    // 클라이언트 기본 로직 사용
    waitTime = _calculateWaitTime(processedFiles[0].path);
    Log.i("[MEDIA] 단일 파일 전송 대기 (클라이언트 계산): " + waitTime + "ms");
}
```

## 시스템 확장 방법

### 새로운 명령어 추가

1. **명령어 정의 추가** (`command_definitions.py`)
```python
COMMAND_DEFINITIONS["# NEWCMD"] = {
    "type": "new_command_type",
    "desc": "새로운 명령어 설명",
    "parameters": {
        "param1": {
            "description": "파라미터 1 설명",
            "required": True,
            "type": "string"
        },
        "param2": {
            "description": "파라미터 2 설명", 
            "required": False,
            "type": "int",
            "min": 1,
            "max": 100,
            "default": 10
        }
    }
}
```

2. **서비스 모듈 작성** (`services/new_command_service.py`)
```python
from core.command_definitions import parse_command_parameters, validate_command_parameters

async def handle_new_command(context, text):
    # 파라미터 파싱
    params, remaining_text = parse_command_parameters("# NEWCMD", text)
    
    # 유효성 검증
    is_valid, error_msg = validate_command_parameters("# NEWCMD", params)
    if not is_valid:
        await send_message_response(context, f"오류: {error_msg}")
        return
    
    # 파라미터 사용
    param1 = params.get("param1")
    param2 = params.get("param2", 10)  # 기본값 사용
    
    # 명령어 처리 로직...
```

### 지원하는 파라미터 타입
- `string`: 문자열
- `int`: 정수 (min/max 설정 가능)
- `bool`: 불린값 (향후 확장 예정)

### 파라미터 규칙
- 파라미터명은 하이픈(`-`) 사용: `--media-wait-time`
- 파싱 후 언더스코어(`_`)로 변환: `media_wait_time`
- 필수 파라미터 누락 시 오류 메시지 반환
- 타입 불일치 시 해당 파라미터 무시

## 사용 예시

### 기본 사용 (클라이언트 계산 대기시간)
```
# IMGEXT Hello World Test
```

### 서버 지정 대기시간 (8초)
```
# IMGEXT --media-wait-time=8000 Hello World Test
```

### 여러 파라미터 조합 (향후 확장)
```
# IMGEXT --media-wait-time=10000 --font-size=48 Hello World
```

## 로그 출력 예시

### 서버 로그
```
[IMGEXT] media_wait_time 옵션 감지: 8000ms
[IMGEXT] 명령어 처리 완료: Hello World Test (대기시간: 8000ms)
```

### 클라이언트 로그
```
[MEDIA] 미디어 전송 시작: 3개 (서버 지정 대기시간: 8000ms)
[MEDIA] 서버 지정 대기시간 사용: 8000ms
```

## 향후 확장 가능성

1. **더 많은 파라미터 타입 지원**
   - `float`: 실수값
   - `enum`: 열거형 값
   - `list`: 배열 값

2. **고급 유효성 검증**
   - 정규식 패턴 매칭
   - 조건부 필수 파라미터
   - 파라미터 간 의존성 검증

3. **자동 도움말 생성**
   - 명령어 정의 기반 도움말 자동 생성
   - 파라미터 설명 포함

이 시스템을 통해 앞으로 새로운 명령어를 추가할 때 일관된 파라미터 처리 방식을 사용할 수 있습니다.