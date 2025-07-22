# 명령어 시스템 및 웹 기반 API 저작 도구 설계

## 📋 개요

카카오봇 서버가 다양한 명령어를 지원하기 위해 확장 가능한 REST API 기반 명령어 시스템과 고객이 직접 기능을 개발할 수 있는 웹 기반 저작 도구를 설계합니다.

## 🎯 목표

1. **확장성**: 서버 코어 수정 없이 새로운 명령어 추가 가능
2. **분산성**: 명령어별 독립적인 API 서버로 기능 분리
3. **접근성**: 개발 경험이 적은 고객도 쉽게 기능 개발
4. **보안성**: 시스템 보안을 유지하면서 고객에게 개발 환경 제공

## 🏗️ 시스템 아키텍처

### 1. 사용자별 24시간 API 서버 구조

```mermaid
graph TD
    subgraph "사용자 A의 API 서버"
        A1[FastAPI 서버<br/>Port: 8001] --> A2[날씨 명령어]
        A1 --> A3[주문 명령어]
        A1 --> A4[재고 명령어]
    end
    
    subgraph "사용자 B의 API 서버"
        B1[FastAPI 서버<br/>Port: 8002] --> B2[환율 명령어]
        B1 --> B3[뉴스 명령어]
    end
    
    C[카카오톡] --> D[봇 서버]
    D --> E{라우팅}
    E -->|사용자 A| A1
    E -->|사용자 B| B1
```

### 2. 사용자 API 서버 템플릿

```python
# user_api_template.py
from fastapi import FastAPI, HTTPException
from datetime import datetime
import asyncio

app = FastAPI()

# 사용자가 정의한 전역 변수들
cache = {}
scheduled_tasks = []

@app.post("/command")
async def handle_command(data: dict):
    """모든 명령어를 하나의 엔드포인트에서 처리"""
    command = data.get('command')
    params = data.get('parameters', [])
    
    # 사용자가 정의한 명령어 라우팅
    if command == "날씨":
        return await weather_command(params)
    elif command == "주문":
        return await order_command(params)
    elif command == "재고":
        return await stock_command(params)
    else:
        raise HTTPException(404, f"명령어 '{command}'를 찾을 수 없습니다")

# 사용자가 구현하는 명령어 함수들
async def weather_command(params):
    location = params[0] if params else "서울"
    # 날씨 조회 로직
    return {"text": f"🌤️ {location} 날씨: 맑음, 15°C"}

async def order_command(params):
    # 주문 조회 로직
    return {"text": "📦 주문 내역: ..."}

async def stock_command(params):
    # 재고 확인 로직
    return {"text": "📊 재고 현황: ..."}

# 백그라운드 작업 (옵션)
@app.on_event("startup")
async def startup_event():
    # 주기적 작업, 스케줄링 등
    asyncio.create_task(background_task())

async def background_task():
    while True:
        # 30분마다 캐시 업데이트
        await asyncio.sleep(1800)
        # 업데이트 로직...

if __name__ == "__main__":
    import uvicorn
    # 각 사용자마다 다른 포트 할당
    uvicorn.run(app, host="0.0.0.0", port=8001)
```

### 2. 24시간 실행이 필요한 경우의 아키텍처

```python
# 사용자별 독립 API 서버 예시
from fastapi import FastAPI
import asyncio

app = FastAPI()

# 전역 상태 유지 가능
cache = {}
scheduled_tasks = []

@app.post("/command/weather")
async def weather_command(params: dict):
    location = params['parameters'][0]
    # 캐시 확인
    if location in cache:
        return cache[location]
    
    # 날씨 조회 로직
    result = await fetch_weather(location)
    cache[location] = result
    return result

@app.on_event("startup")
async def startup_event():
    # 주기적 작업 실행
    asyncio.create_task(periodic_update())

async def periodic_update():
    """30분마다 캐시 업데이트"""
    while True:
        await update_all_caches()
        await asyncio.sleep(1800)  # 30분

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
```

### 2. 명령어 라우팅 프로세스

```mermaid
sequenceDiagram
    participant U as 사용자
    participant B as 봇 클라이언트
    participant S as 봇 서버
    participant DB as 명령어 DB
    participant API as 외부 API 서버
    
    U->>B: "날씨 화도읍"
    B->>S: analyze 이벤트
    S->>DB: 명령어 조회
    DB->>S: API 서버 정보
    S->>API: REST API 요청
    API->>API: 날씨 크롤링
    API->>S: 포맷된 응답
    S->>B: messageResponse
    B->>U: 날씨 정보 표시
```

## 📊 데이터베이스 설계

### kb_commands 테이블

```sql
CREATE TABLE kb_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command VARCHAR(50) NOT NULL COMMENT '명령어 (예: 날씨)',
    pattern VARCHAR(255) COMMENT '정규식 패턴',
    api_server_url VARCHAR(500) NOT NULL COMMENT 'REST API URL',
    api_method VARCHAR(10) DEFAULT 'POST' COMMENT 'HTTP 메소드',
    api_headers TEXT COMMENT 'API 헤더 (JSON)',
    timeout INT DEFAULT 5000 COMMENT '타임아웃 (ms)',
    owner_id VARCHAR(50) COMMENT '소유자 ID',
    is_active TINYINT DEFAULT 1 COMMENT '활성화 여부',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_command (command),
    INDEX idx_owner (owner_id)
) COMMENT '명령어 라우팅 테이블';
```

### kb_api_logs 테이블

```sql
CREATE TABLE kb_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command_id INT,
    request_data TEXT COMMENT '요청 데이터',
    response_data TEXT COMMENT '응답 데이터',
    status_code INT COMMENT 'HTTP 상태 코드',
    response_time INT COMMENT '응답 시간 (ms)',
    error_message TEXT COMMENT '에러 메시지',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (command_id) REFERENCES kb_commands(id),
    INDEX idx_created (created_at)
) COMMENT 'API 호출 로그';
```

## 🛠️ REST API 표준

### 요청 형식

```json
{
    "command": "날씨",
    "parameters": ["화도읍"],
    "raw_text": "날씨 화도읍",
    "sender": "홍길동",
    "room": "테스트방",
    "channel_id": "12345",
    "metadata": {
        "timestamp": "2025-01-22T10:30:00Z",
        "device_id": "android_xxx"
    }
}
```

### 응답 형식

```json
{
    "success": true,
    "response": {
        "text": "🌤️ 화도읍 날씨\n현재: 5°C\n최고: 10°C / 최저: -2°C\n미세먼지: 보통",
        "type": "text",
        "buttons": [
            {
                "label": "자세히 보기",
                "url": "https://weather.naver.com/..."
            }
        ]
    },
    "error": null,
    "processing_time": 234
}
```

## 💻 웹 기반 API 저작 도구

### 1. Python 기반 도커 컨테이너 격리 아키텍처

#### 컨테이너 할당 방식

```mermaid
graph TD
    A[웹 에디터<br/>Monaco Editor] --> B[API Gateway]
    B --> C[Container Manager]
    C --> D{컨테이너 풀}
    D --> E[사용자 A<br/>전용 컨테이너]
    D --> F[사용자 B<br/>전용 컨테이너]
    D --> G[임시 실행용<br/>컨테이너]
    E --> H[코드 실행]
    F --> I[코드 실행]
    G --> J[코드 실행]
```

#### 도커 컨테이너 격리 전략

1. **사용자별 전용 컨테이너** (프리미엄 사용자)
   - 사용자당 1개의 지속적인 컨테이너 할당
   - 파일 저장 및 상태 유지 가능
   - 리소스 제한: CPU 1 core, Memory 512MB

2. **임시 실행 컨테이너** (일반 사용자)
   - 코드 실행시마다 새 컨테이너 생성
   - 실행 완료 후 즉시 삭제
   - 리소스 제한: CPU 0.5 core, Memory 256MB

3. **컨테이너 풀링**
   - 미리 생성된 컨테이너 풀 관리
   - 빠른 할당과 회수
   - 자동 스케일링

### 2. Python 전용 실행 환경

```dockerfile
# Python API 실행 환경 Dockerfile
FROM python:3.9-slim

# 보안을 위한 비권한 사용자 생성
RUN useradd -m -u 1000 sandbox

# 필수 라이브러리만 설치
RUN pip install --no-cache-dir \
    requests==2.31.0 \
    beautifulsoup4==4.12.2 \
    lxml==4.9.3 \
    python-dateutil==2.8.2

# 보안 설정
RUN echo "import sys; sys.modules['os'] = None" > /etc/python_startup.py
ENV PYTHONSTARTUP=/etc/python_startup.py

# 작업 디렉토리 설정
WORKDIR /sandbox
USER sandbox

# 읽기 전용 파일시스템
CMD ["python", "-m", "RestrictedPython"]
```

### 3. 컨테이너 관리 시스템

```python
# container_manager.py
import docker
import uuid
import asyncio
from datetime import datetime, timedelta

class ContainerManager:
    def __init__(self):
        self.client = docker.from_env()
        self.container_pool = []
        self.user_containers = {}  # 사용자별 전용 컨테이너
        
    async def get_container_for_user(self, user_id, is_premium=False):
        """사용자를 위한 컨테이너 할당"""
        if is_premium and user_id in self.user_containers:
            # 프리미엄 사용자는 전용 컨테이너 사용
            return self.user_containers[user_id]
        
        if is_premium:
            # 프리미엄 사용자용 새 컨테이너 생성
            container = await self.create_user_container(user_id)
            self.user_containers[user_id] = container
            return container
        else:
            # 일반 사용자는 임시 컨테이너 사용
            return await self.get_temp_container()
    
    async def create_user_container(self, user_id):
        """사용자 전용 컨테이너 생성"""
        container = self.client.containers.run(
            'domaeka-python-sandbox:latest',
            detach=True,
            name=f'sandbox-{user_id}',
            mem_limit='512m',
            cpus=1.0,
            network_mode='bridge',
            read_only=True,
            tmpfs={'/tmp': 'size=100M'},
            environment={
                'USER_ID': user_id,
                'TIMEOUT': '30'
            }
        )
        return container
    
    async def get_temp_container(self):
        """임시 실행용 컨테이너 할당"""
        if self.container_pool:
            return self.container_pool.pop()
        
        # 풀이 비어있으면 새로 생성
        return await self.create_temp_container()
    
    async def create_temp_container(self):
        """임시 컨테이너 생성"""
        container_id = str(uuid.uuid4())[:8]
        container = self.client.containers.run(
            'domaeka-python-sandbox:latest',
            detach=True,
            name=f'temp-{container_id}',
            mem_limit='256m',
            cpus=0.5,
            auto_remove=True,  # 종료시 자동 삭제
            network_mode='none',  # 네트워크 격리
            read_only=True,
            tmpfs={'/tmp': 'size=50M'}
        )
        return container
```

### 4. 웹 에디터 UI 설계 (Python 전용)

```
┌─────────────────────────────────────────────────────┐
│ 🐍 도매까 Python API 저작 도구                       │
├─────────────────────────────────────────────────────┤
│ 명령어: [날씨    ] 패턴: [날씨\s+(.+)]             │
│ [▶️ 실행] [💾 저장] [📋 템플릿] [⚙️ 설정]           │
├─────────────────────────────────────────────────────┤
│ 1  import requests                                  │
│ 2  from bs4 import BeautifulSoup                   │
│ 3  from datetime import datetime                    │
│ 4                                                   │
│ 5  def handle_command(params):                     │
│ 6      """                                         │
│ 7      날씨 정보를 조회하는 API                      │
│ 8      params: {                                   │
│ 9          'parameters': ['지역명'],               │
│ 10         'sender': '발신자',                     │
│ 11         'room': '채팅방'                        │
│ 12     }                                           │
│ 13     """                                         │
│ 14     location = params['parameters'][0]          │
│ 15     # 네이버 날씨 크롤링                         │
│ 16     url = f"https://search.naver.com/..."      │
│ 17     response = requests.get(url)                │
│ 18     soup = BeautifulSoup(response.text, 'html')│
│ 19                                                 │
│ 20     # 날씨 정보 파싱                             │
│ 21     temp = soup.select_one('.temperature').text │
│ 22     weather = soup.select_one('.weather').text  │
│ 23                                                 │
│ 24     return {                                    │
│ 25         "text": f"🌤️ {location} 날씨\n"        │
│ 26                 f"현재: {temp}\n"              │
│ 27                 f"날씨: {weather}"              │
│ 28     }                                           │
├─────────────────────────────────────────────────────┤
│ 📤 테스트 입력:                                      │
│ { "parameters": ["화도읍"], "sender": "테스터" }    │
├─────────────────────────────────────────────────────┤
│ 📥 실행 결과:                                        │
│ ✅ 실행 성공 (234ms)                                │
│ {                                                   │
│   "text": "🌤️ 화도읍 날씨\n현재: 5°C\n날씨: 맑음"  │
│ }                                                   │
│ 📊 리소스 사용: CPU 2%, Memory 15MB                 │
└─────────────────────────────────────────────────────┘
```

## 🔒 보안 고려사항

### 1. 도커 컨테이너 보안 설정

#### 컨테이너 실행 보안 옵션
```python
# secure_container_config.py
CONTAINER_SECURITY_CONFIG = {
    # 기본 보안 설정
    'security_opt': ['no-new-privileges'],  # 권한 상승 방지
    'cap_drop': ['ALL'],  # 모든 Linux capabilities 제거
    'cap_add': [],  # 필요한 최소 권한만 추가
    
    # 파일시스템 보안
    'read_only': True,  # 읽기 전용 루트 파일시스템
    'tmpfs': {
        '/tmp': 'size=50M,mode=1777',  # 임시 파일용 메모리 파일시스템
        '/var/tmp': 'size=10M,mode=1777'
    },
    
    # 네트워크 격리
    'network_mode': 'none',  # 완전한 네트워크 격리 (일반 사용자)
    # 프리미엄 사용자는 제한된 네트워크 접근 허용
    'dns': ['8.8.8.8'],  # 신뢰할 수 있는 DNS만 사용
    
    # 리소스 제한
    'mem_limit': '256m',
    'memswap_limit': '256m',  # 스왑 사용 금지
    'cpu_quota': 50000,  # CPU 50% 제한
    'cpu_period': 100000,
    'pids_limit': 50,  # 프로세스 수 제한
    
    # 사용자 권한
    'user': '1000:1000',  # 비권한 사용자로 실행
}
```

#### Python 코드 실행 제한
```python
# sandbox_executor.py
import ast
import sys
import timeout_decorator

class SafeCodeExecutor:
    # 금지된 모듈 및 함수
    FORBIDDEN_IMPORTS = {
        'os', 'sys', 'subprocess', 'eval', 'exec', 
        'compile', '__import__', 'open', 'file',
        'input', 'raw_input', 'execfile', 'reload'
    }
    
    # 허용된 모듈만 임포트 가능
    ALLOWED_MODULES = {
        'requests': True,
        'beautifulsoup4': True,
        'json': True,
        'datetime': True,
        're': True,
        'math': True,
        'random': True,
        'urllib.parse': True
    }
    
    def validate_code(self, code):
        """코드 안전성 검증"""
        try:
            tree = ast.parse(code)
            for node in ast.walk(tree):
                # import 검증
                if isinstance(node, ast.Import):
                    for alias in node.names:
                        if alias.name.split('.')[0] not in self.ALLOWED_MODULES:
                            raise ValueError(f"Forbidden import: {alias.name}")
                
                # from import 검증
                elif isinstance(node, ast.ImportFrom):
                    if node.module and node.module.split('.')[0] not in self.ALLOWED_MODULES:
                        raise ValueError(f"Forbidden import: {node.module}")
                
                # 위험한 함수 호출 검증
                elif isinstance(node, ast.Name):
                    if node.id in self.FORBIDDEN_IMPORTS:
                        raise ValueError(f"Forbidden function: {node.id}")
            
            return True
        except Exception as e:
            return False, str(e)
    
    @timeout_decorator.timeout(5)  # 5초 실행 제한
    def execute_code(self, code, params):
        """안전한 환경에서 코드 실행"""
        # 제한된 전역 변수
        safe_globals = {
            '__builtins__': {
                'len': len,
                'range': range,
                'str': str,
                'int': int,
                'float': float,
                'bool': bool,
                'list': list,
                'dict': dict,
                'tuple': tuple,
                'set': set,
                'print': print,
            }
        }
        
        # 허용된 모듈 추가
        exec("import requests", safe_globals)
        exec("from bs4 import BeautifulSoup", safe_globals)
        exec("import json", safe_globals)
        exec("from datetime import datetime", safe_globals)
        exec("import re", safe_globals)
        
        # 사용자 코드 실행
        exec(code, safe_globals)
        
        # handle_command 함수 호출
        if 'handle_command' in safe_globals:
            return safe_globals['handle_command'](params)
        else:
            raise ValueError("handle_command function not found")
```

### 2. 네트워크 보안

```python
# network_whitelist.py
ALLOWED_DOMAINS = [
    # 날씨 정보
    'api.openweathermap.org',
    'search.naver.com',
    'weather.naver.com',
    
    # 공공 API
    'api.data.go.kr',
    'openapi.naver.com',
    
    # 기타 신뢰할 수 있는 도메인만 추가
]

# iptables 규칙 (Docker 컨테이너 내부)
IPTABLES_RULES = """
# 기본 정책: 모든 아웃바운드 차단
iptables -P OUTPUT DROP

# 로컬호스트 허용
iptables -A OUTPUT -o lo -j ACCEPT

# DNS 쿼리 허용 (제한된 DNS 서버만)
iptables -A OUTPUT -p udp --dport 53 -d 8.8.8.8 -j ACCEPT

# 화이트리스트 도메인만 허용
{whitelist_rules}

# 나머지 모두 차단
iptables -A OUTPUT -j DROP
"""
```

### 3. 리소스 모니터링 및 제한

```python
# resource_monitor.py
import psutil
import asyncio

class ResourceMonitor:
    def __init__(self, container):
        self.container = container
        self.limits = {
            'cpu_percent': 50,  # CPU 50% 초과시 종료
            'memory_mb': 256,   # 메모리 256MB 초과시 종료
            'disk_io_mb': 100,  # 디스크 I/O 100MB 초과시 종료
        }
    
    async def monitor(self):
        """컨테이너 리소스 모니터링"""
        while True:
            stats = self.container.stats(stream=False)
            
            # CPU 사용률 체크
            cpu_percent = self.calculate_cpu_percent(stats)
            if cpu_percent > self.limits['cpu_percent']:
                await self.kill_container("CPU limit exceeded")
            
            # 메모리 사용량 체크
            memory_mb = stats['memory_stats']['usage'] / 1024 / 1024
            if memory_mb > self.limits['memory_mb']:
                await self.kill_container("Memory limit exceeded")
            
            await asyncio.sleep(1)
    
    async def kill_container(self, reason):
        """리소스 제한 초과시 컨테이너 종료"""
        self.container.kill()
        logging.warning(f"Container killed: {reason}")
```

## 📈 확장 가능한 기능

### 1. Python 템플릿 라이브러리

```python
# templates/weather_template.py
"""
날씨 조회 템플릿
네이버 날씨를 크롤링하여 정보를 반환합니다.
"""
import requests
from bs4 import BeautifulSoup

def handle_command(params):
    location = params['parameters'][0]
    
    # 네이버 날씨 검색
    url = f"https://search.naver.com/search.naver?query={location}+날씨"
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    
    response = requests.get(url, headers=headers)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # 날씨 정보 추출
    temperature = soup.select_one('.temperature_text').text
    weather_status = soup.select_one('.weather_main').text
    
    return {
        "text": f"🌤️ {location} 날씨\n"
                f"현재 온도: {temperature}\n"
                f"날씨 상태: {weather_status}"
    }
```

### 2. 사용량 제한 및 과금 시스템

```python
# usage_limiter.py
class UsageLimiter:
    def __init__(self):
        self.limits = {
            'free': {
                'daily_executions': 100,
                'max_execution_time': 5,
                'memory_limit': 256,
                'network_access': False
            },
            'premium': {
                'daily_executions': 10000,
                'max_execution_time': 30,
                'memory_limit': 512,
                'network_access': True
            }
        }
    
    async def check_usage(self, user_id, tier='free'):
        """사용량 확인 및 제한"""
        usage = await self.get_user_usage(user_id)
        limits = self.limits[tier]
        
        if usage['daily_count'] >= limits['daily_executions']:
            raise UsageLimitExceeded("일일 실행 횟수 초과")
        
        return True
```

### 3. 실시간 모니터링 대시보드

```
┌─────────────────────────────────────────────────────┐
│ 📊 도매까 API 모니터링 대시보드                      │
├─────────────────────────────────────────────────────┤
│ 실시간 현황                                         │
│ • 활성 컨테이너: 12 / 50                           │
│ • API 호출/분: 234                                  │
│ • 평균 응답시간: 156ms                             │
│                                                     │
│ 인기 명령어 TOP 5                                   │
│ 1. 날씨 (45%)                                      │
│ 2. 주문조회 (23%)                                   │
│ 3. 재고확인 (15%)                                   │
│ 4. 배송추적 (10%)                                   │
│ 5. 환율조회 (7%)                                    │
│                                                     │
│ 시스템 리소스                                       │
│ CPU: ████████░░ 78%                                │
│ Memory: ██████░░░░ 62%                             │
│ Network: ███░░░░░░░ 34%                            │
└─────────────────────────────────────────────────────┘
```

## 🚀 구현 로드맵

### Phase 1: 기본 구조 (2주)
- [ ] 명령어 라우팅 시스템 구현
- [ ] 데이터베이스 스키마 생성
- [ ] Docker 기반 Python 실행 환경 구축
- [ ] 기본 REST API 서버 템플릿

### Phase 2: 웹 에디터 (2주)
- [ ] Monaco Editor 통합
- [ ] Python 코드 검증 시스템
- [ ] 컨테이너 관리 시스템
- [ ] 기본 템플릿 5개 제공

### Phase 3: 보안 강화 (2주)
- [ ] AST 기반 코드 검증
- [ ] 네트워크 화이트리스트
- [ ] 리소스 모니터링
- [ ] 사용자 인증/권한

### Phase 4: 운영 기능 (2주)
- [ ] 사용량 제한 시스템
- [ ] 모니터링 대시보드
- [ ] 로깅 및 분석
- [ ] 백업/복구 시스템

## 💡 기술적 고려사항

### 1. 도커 컨테이너 vs 다른 격리 방식 비교

| 방식 | 장점 | 단점 | 보안성 | 성능 |
|------|------|------|--------|------|
| **Docker 컨테이너** | 완전한 격리, 표준화된 환경 | 리소스 오버헤드 | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| RestrictedPython | 가벼움, 빠른 실행 | 제한된 기능 | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| PyPy Sandbox | Python 전용 최적화 | 설정 복잡 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| VM (가상머신) | 최고 수준 격리 | 매우 무거움 | ⭐⭐⭐⭐⭐ | ⭐⭐ |

### 2. 도커 기반 구현의 실제 예시

#### Jupyter Hub와 유사한 아키텍처
```yaml
# docker-compose.yml
version: '3.8'

services:
  hub:
    image: jupyterhub/jupyterhub
    environment:
      DOCKER_JUPYTER_IMAGE: domaeka-python-sandbox
      DOCKER_NETWORK_NAME: jupyter-network
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    
  sandbox-spawner:
    build: ./sandbox
    environment:
      - SPAWNER_CLASS=dockerspawner.DockerSpawner
      - CPU_LIMIT=0.5
      - MEM_LIMIT=256M
```

### 3. 실제 운영 사례 참고

- **Google Colab**: 도커 컨테이너 기반 Python 실행
- **Replit**: 컨테이너 + 가상 파일시스템
- **CodeSandbox**: 마이크로VM 기반 격리
- **Kaggle Kernels**: 도커 + GPU 지원

## 📝 결론 및 권장사항

### 도커 컨테이너 격리 방식의 장점

1. **완전한 격리**: 각 사용자의 코드가 독립된 환경에서 실행되어 서로 영향을 주지 않음
2. **표준화된 환경**: 모든 사용자가 동일한 Python 버전과 라이브러리를 사용
3. **리소스 제어**: CPU, 메모리, 네트워크를 세밀하게 제어 가능
4. **확장성**: Kubernetes를 활용한 대규모 확장 가능

### 구현 방식 권장사항

1. **초기 단계 (MVP)**
   - 임시 실행 컨테이너만 제공
   - 기본 Python 라이브러리만 지원
   - 5초 실행 제한, 네트워크 차단

2. **성장 단계**
   - 프리미엄 사용자용 전용 컨테이너
   - 화이트리스트 기반 네트워크 접근
   - 더 많은 Python 라이브러리 지원

3. **성숙 단계**
   - Kubernetes 기반 자동 스케일링
   - 사용자별 커스텀 라이브러리 설치
   - 협업 및 코드 공유 기능

### Oracle Cloud Free Tier 서버에서 24시간 API 서버 운영

#### 서버 스펙: ARM 4 vCPU, 24GB RAM, 200GB Storage

**사용자별 24시간 API 서버 운영 시**

```
리소스 할당 전략:
- OS 및 Docker: 3GB
- 모니터링/관리: 1GB
- 사용 가능 메모리: 20GB

컨테이너당 할당:
- 최소 사양: 200MB RAM, 0.05 CPU
- 권장 사양: 400MB RAM, 0.1 CPU
- 프리미엄: 800MB RAM, 0.2 CPU

운영 가능 사용자 수:
- 최소 사양 (200MB): 약 100명
- 권장 사양 (400MB): 약 50명
- 프리미엄 (800MB): 약 25명
```

#### 실제 운영 구조

```yaml
# docker-compose.yml (일부 사용자 예시)
version: '3.8'

services:
  user-001:
    image: domaeka-python-api:latest
    container_name: api-user-001
    ports:
      - "8001:8000"
    mem_limit: 400m
    cpus: 0.1
    restart: always
    environment:
      - USER_ID=001
      - API_KEY=xxx
    volumes:
      - ./users/001:/app/data
  
  user-002:
    image: domaeka-python-api:latest
    container_name: api-user-002
    ports:
      - "8002:8000"
    mem_limit: 400m
    cpus: 0.1
    restart: always
    environment:
      - USER_ID=002
      - API_KEY=yyy
    volumes:
      - ./users/002:/app/data

  # ... 최대 50개 정도
```

#### 포트 관리 및 라우팅

```nginx
# nginx 리버스 프록시 설정
server {
    listen 80;
    
    # 사용자별 라우팅
    location /api/user/001/ {
        proxy_pass http://localhost:8001/;
    }
    
    location /api/user/002/ {
        proxy_pass http://localhost:8002/;
    }
    
    # 동적 라우팅 (Lua 사용)
    location ~ /api/user/(\d+)/ {
        set $user_id $1;
        set $port "";
        
        access_by_lua_block {
            local user_id = ngx.var.user_id
            local port_map = {
                ["001"] = 8001,
                ["002"] = 8002,
                -- DB에서 동적 로드
            }
            ngx.var.port = port_map[user_id] or 0
        }
        
        proxy_pass http://localhost:$port/;
    }
}
```

**실제 운영 가능 수: 약 50명**
- 각 사용자가 자신만의 FastAPI 서버 운영
- 모든 명령어를 하나의 프로그램에서 처리
- 24시간 상시 대기 상태 유지

### 권장 접근 방식

1. **대부분의 경우**: 온디맨드 실행으로 충분
   - 날씨, 환율, 주문 조회 등은 요청 시 실행이 적합
   - 비용 효율적이고 관리가 간단

2. **24시간 실행이 필요한 경우**:
   - 정기적인 알림 발송
   - 실시간 모니터링
   - WebSocket 연결 유지
   - → 이런 경우는 별도의 "프리미엄 플랜"으로 제공

3. **하이브리드 방식**:
   - 기본: 온디맨드 실행
   - 프리미엄: 전용 컨테이너 + 24시간 실행
   - 엔터프라이즈: 전용 서버 할당

### 실제 운영 최적화 방안

Oracle Cloud Free Tier 서버(ARM 4core 24GB)를 활용한 최적화:

1. **컨테이너 풀 사전 생성**
   - 10-20개의 Python 컨테이너를 미리 생성해두고 재사용
   - 콜드 스타트 시간 제거 (3초 → 0.1초)

2. **메모리 최적화**
   - Alpine Linux 기반 경량 이미지 사용 (50MB)
   - Python 슬림 버전 + 필수 라이브러리만 포함

3. **CPU 효율화**
   - ARM 아키텍처 최적화된 Python 빌드 사용
   - 비동기 처리로 CPU 유휴 시간 최소화

4. **스토리지 활용**
   - 200GB로 로그, 캐시, 사용자 코드 저장 충분
   - 컨테이너 이미지 레이어 공유로 공간 절약

### 사용자별 전용 DB 제공 시 리소스 분석

#### DB 옵션별 리소스 사용량

| DB 종류 | 메모리 사용 | 디스크 사용 | 특징 |
|---------|------------|------------|------|
| SQLite | 0MB (파일기반) | 1-100MB/사용자 | 가장 가벼움, 동시성 제한 |
| PostgreSQL | 100-200MB/인스턴스 | 100MB-1GB/사용자 | 고급 기능, 높은 메모리 |
| MySQL | 150-300MB/인스턴스 | 100MB-1GB/사용자 | 범용적, 중간 메모리 |

#### 1. SQLite 기반 구조 (권장)

```yaml
# 사용자별 구조
services:
  user-001:
    image: domaeka-python-api:latest
    mem_limit: 400m
    volumes:
      - ./users/001/code:/app/code
      - ./users/001/data.db:/app/data.db  # SQLite 파일
    environment:
      - DATABASE_URL=sqlite:///app/data.db
```

**SQLite 사용 시 운영 가능**:
- API 서버 + SQLite: 400MB/사용자
- 최대 사용자: **약 50명**
- 디스크: 200GB ÷ 4GB/사용자 = 50명

#### 2. PostgreSQL 공유 서버 방식 (권장)

```yaml
services:
  # PostgreSQL 마스터 (모든 사용자 공유)
  postgres-master:
    image: postgres:15-alpine
    mem_limit: 3g
    environment:
      - POSTGRES_MAX_CONNECTIONS=200
      - POSTGRES_SHARED_BUFFERS=1GB
    volumes:
      - ./postgres-data:/var/lib/postgresql/data
  
  # 사용자 001
  user-001-api:
    mem_limit: 300m
    environment:
      - DATABASE_URL=postgresql://user001:pass@postgres-master/user001_db
  
  # 사용자 002
  user-002-api:
    mem_limit: 300m
    environment:
      - DATABASE_URL=postgresql://user002:pass@postgres-master/user002_db
```

**PostgreSQL이 더 나은 이유**:

1. **리소스 효율성**
   - 하나의 PostgreSQL 인스턴스로 모든 사용자 서비스
   - 연결 풀링으로 메모리 효율 극대화
   - 공유 버퍼로 캐시 효율성 증대

2. **격리 및 보안**
   ```sql
   -- 사용자별 DB 생성
   CREATE DATABASE user001_db;
   CREATE USER user001 WITH PASSWORD 'secure_pass';
   GRANT ALL ON DATABASE user001_db TO user001;
   
   -- 스키마 격리도 가능
   CREATE SCHEMA user001;
   GRANT ALL ON SCHEMA user001 TO user001;
   ```

3. **고급 기능 활용**
   - JSON 데이터 타입 지원
   - 전문 검색 (Full Text Search)
   - 실시간 알림 (LISTEN/NOTIFY)
   - 스케줄링 (pg_cron)

4. **실제 구현 예시**
   ```python
   import asyncpg
   from fastapi import FastAPI
   
   app = FastAPI()
   
   @app.on_event("startup")
   async def startup():
       # 연결 풀 생성
       app.state.pool = await asyncpg.create_pool(
           "postgresql://user001:pass@postgres-master/user001_db",
           min_size=1,
           max_size=5
       )
   
   @app.post("/command")
   async def handle_command(data: dict):
       async with app.state.pool.acquire() as conn:
           # 트랜잭션 처리
           async with conn.transaction():
               # 로그 저장
               await conn.execute(
                   "INSERT INTO command_logs (command, params) VALUES ($1, $2)",
                   data['command'], data['parameters']
               )
               
               # 캐시 확인
               cache = await conn.fetchval(
                   "SELECT value FROM cache WHERE key = $1 AND expires_at > NOW()",
                   f"{data['command']}:{data['parameters'][0]}"
               )
               
               if cache:
                   return {"text": cache, "cached": True}
   ```

**PostgreSQL 최적화 구성**:
- PostgreSQL 서버: 3GB (모든 사용자 공유)
- API 서버: 300MB × 60명 = 18GB
- 여유 메모리: 3GB
- **최대 사용자: 60-70명**

#### 3. 하이브리드 접근법

```python
# 사용자 API 서버 템플릿 (DB 포함)
import sqlite3
from fastapi import FastAPI
from contextlib import asynccontextmanager

@asynccontextmanager
async def lifespan(app: FastAPI):
    # 시작 시 DB 초기화
    conn = sqlite3.connect('/data/user.db')
    conn.execute('''
        CREATE TABLE IF NOT EXISTS cache (
            key TEXT PRIMARY KEY,
            value TEXT,
            expires_at TIMESTAMP
        )
    ''')
    conn.execute('''
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            command TEXT,
            params TEXT,
            result TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    conn.close()
    yield

app = FastAPI(lifespan=lifespan)

@app.post("/command")
async def handle_command(data: dict):
    conn = sqlite3.connect('/data/user.db')
    
    # 명령어 로그 저장
    conn.execute(
        "INSERT INTO logs (command, params, result) VALUES (?, ?, ?)",
        (data['command'], str(data['parameters']), '')
    )
    
    # 캐시 확인
    cache = conn.execute(
        "SELECT value FROM cache WHERE key = ? AND expires_at > datetime('now')",
        (f"{data['command']}:{data['parameters'][0]}",)
    ).fetchone()
    
    if cache:
        return {"text": cache[0], "cached": True}
    
    # 실제 처리 로직...
    result = process_command(data)
    
    # 결과 캐싱
    conn.execute(
        "INSERT OR REPLACE INTO cache (key, value, expires_at) VALUES (?, ?, datetime('now', '+30 minutes'))",
        (f"{data['command']}:{data['parameters'][0]}", result['text'])
    )
    
    conn.commit()
    conn.close()
    
    return result
```

### 최종 운영 가능 사용자 수

| 구성 | API 서버 | DB 서버 | 실제 사용자당 | 최대 사용자 |
|------|----------|---------|--------------|------------|
| SQLite 개별 | 400MB | 0MB | 400MB | **50명** |
| **PostgreSQL 공유** | **300MB** | **3GB 공유** | **300MB** | **60-70명** ⭐ |
| MySQL 공유 | 300MB | 4GB 공유 | 300MB | **55명** |
| 혼합 (SQLite+Redis) | 350MB | 1GB 공유 | 350MB | **55명** |

### PostgreSQL이 최적인 이유

1. **메모리 효율성**
   - 하나의 DB 인스턴스를 모든 사용자가 공유
   - 사용자당 추가 DB 메모리 불필요
   - 연결 풀링으로 리소스 최적화

2. **기능적 우위**
   - JSONB로 NoSQL 기능도 활용
   - 비동기 알림 (LISTEN/NOTIFY)로 실시간 기능 구현
   - Window Functions, CTE 등 고급 SQL 지원

3. **운영 편의성**
   - 중앙 집중식 백업/복구
   - 하나의 DB로 모니터링 간소화
   - pg_stat_statements로 성능 분석

4. **확장성**
   - 읽기 전용 복제본 추가 가능
   - 파티셔닝으로 대용량 처리
   - Foreign Data Wrapper로 외부 연동

### 현실적인 제약사항

1. **관리 복잡도**
   - 50개 컨테이너 관리
   - 50개 DB 백업/복구
   - 로그 수집 및 모니터링

2. **성능 한계**
   - ARM 4코어로 50개 서비스 동시 처리 부담
   - I/O 병목 가능성

3. **확장성**
   - 수직 확장 불가 (Free Tier 제한)
   - 수평 확장 필요 (추가 서버)

### 최종 권장 아키텍처

```mermaid
graph TD
    subgraph "Oracle Cloud Free Tier Server"
        A[Nginx<br/>리버스 프록시] --> B[PostgreSQL 15<br/>3GB RAM]
        A --> C[API Container Pool<br/>60-70개]
        
        subgraph "PostgreSQL DB"
            D[user001_db]
            E[user002_db]
            F[user003_db]
            G[...]
        end
        
        subgraph "API Containers"
            H[user001-api<br/>:8001]
            I[user002-api<br/>:8002]
            J[user003-api<br/>:8003]
            K[...]
        end
    end
    
    L[카카오톡] --> M[봇 서버]
    M --> A
```

**결론**: 
- **PostgreSQL 공유 방식: 60-70명** (최적) ⭐
- SQLite 개별 방식: 50명
- 안정적 운영: **PostgreSQL로 50명** 권장

PostgreSQL이 메모리 효율성, 기능, 운영 편의성 모든 면에서 우수합니다.

## 🌐 유사 서비스 벤치마킹

### 1. 기존 Python API 호스팅 서비스

#### **Replit**
- **특징**: 웹 IDE + 24시간 실행 환경
- **가격**: 
  - Hacker Plan: $7/월 (0.5 vCPU, 0.5GB RAM, 10GB Storage)
  - Pro Plan: $20/월 (2 vCPU, 2GB RAM, 50GB Storage)
- **제한**: PostgreSQL은 별도 요금
- **URL**: https://replit.com

#### **PythonAnywhere**
- **특징**: Python 특화 호스팅
- **가격**:
  - Hacker: $5/월 (1 웹앱, 512MB RAM, 1GB Storage, MySQL 제공)
  - Web Dev: $12/월 (무제한 앱, 3GB RAM, 15GB Storage)
- **장점**: MySQL/PostgreSQL 기본 제공
- **URL**: https://www.pythonanywhere.com

#### **Render**
- **특징**: 자동 배포, 관리형 PostgreSQL
- **가격**:
  - Starter: $7/월 (0.5 CPU, 512MB RAM)
  - Standard: $25/월 (1 CPU, 2GB RAM)
  - PostgreSQL: $7/월부터
- **URL**: https://render.com

#### **Railway**
- **특징**: 간단한 배포, 사용량 기반 과금
- **가격**: 
  - Hobby: $5/월 크레딧 (이후 사용량 과금)
  - Pro: $20/월 시작
- **특이점**: PostgreSQL, Redis 원클릭 추가
- **URL**: https://railway.app

#### **Deta Space**
- **특징**: 개인 클라우드, 무료 시작
- **가격**:
  - Personal: 무료 (제한적)
  - Pro: $9/월 (더 많은 리소스)
- **제한**: 상업적 사용 제한
- **URL**: https://deta.space

### 2. 가격 비교표

| 서비스 | 최저가 | CPU | RAM | Storage | DB | 24시간 실행 |
|--------|--------|-----|-----|---------|-----|------------|
| **Replit** | $7/월 | 0.5 vCPU | 0.5GB | 10GB | 별도 | ✅ |
| **PythonAnywhere** | $5/월 | 공유 | 512MB | 1GB | MySQL 포함 | ✅ |
| **Render** | $7/월 | 0.5 CPU | 512MB | SSD | $7/월 추가 | ✅ |
| **Railway** | $5/월~ | 사용량 | 사용량 | 사용량 | 포함 | ✅ |
| **Heroku** | $7/월 | 1x | 512MB | 제한 | $9/월 추가 | ✅ |

### 3. 도매까 서비스와의 차별점

#### 도매까 API 플랫폼 장점
1. **카카오톡 봇 특화**
   - 카카오톡 메시지 처리에 최적화
   - 한국어 템플릿 제공
   - 도매까 시스템 연동

2. **한국 시장 맞춤**
   - 한국어 문서 및 지원
   - 네이버 API 등 한국 서비스 연동
   - 원화 결제

3. **가격 경쟁력**
   ```
   도매까 베이직: ₩5,000/월
   - 0.1 vCPU, 400MB RAM
   - SQLite DB 포함
   - 일 1,000회 실행
   
   도매까 프로: ₩15,000/월
   - 0.2 vCPU, 800MB RAM
   - PostgreSQL 전용 스키마
   - 일 10,000회 실행
   
   도매까 비즈니스: ₩50,000/월
   - 0.5 vCPU, 2GB RAM
   - PostgreSQL 전용 DB
   - 무제한 실행
   ```

### 4. 한국 서비스 현황

#### **Goorm IDE (구름IDE)**
- **특징**: 한국 토종 클라우드 IDE
- **가격**:
  - 무료: 컨테이너 1개, 1GB RAM
  - 프리미엄: ₩9,900/월 (컨테이너 5개, 2GB RAM)
  - 비즈니스: ₩29,700/월 (무제한)
- **제한**: 주로 개발 환경, 24시간 API 서버는 제한적
- **URL**: https://ide.goorm.io

#### **NCloud Functions (네이버)**
- **특징**: 서버리스 함수 실행
- **가격**: 
  - 100만 건/월 무료
  - 이후 ₩0.4/1,000건
- **제한**: 실행 시간 제한, 상태 유지 불가
- **URL**: https://www.ncloud.com/product/compute/cloudFunctions

#### **Toast Cloud (NHN)**
- **특징**: 기업용 클라우드 서비스
- **가격**: VM 인스턴스 기반 (비싸다)
- **대상**: 주로 기업 고객
- **URL**: https://www.toast.com

#### **Elice (엘리스)**
- **특징**: 교육용 코딩 플랫폼
- **가격**: 교육 과정별 상이
- **제한**: 교육 목적, 상업용 제한
- **URL**: https://elice.io

### 5. 한국 시장 분석

| 구분 | 해외 서비스 | 한국 서비스 | 도매까 API |
|------|------------|------------|-----------|
| **24시간 API 호스팅** | ✅ 다양함 | ❌ 거의 없음 | ✅ 제공 예정 |
| **Python + DB** | ✅ 통합 제공 | ❌ 별도 구매 | ✅ 통합 제공 |
| **가격** | $5-20/월 | 비싸거나 제한적 | ₩5,000-50,000/월 |
| **카카오봇 특화** | ❌ | ❌ | ✅ |
| **한국 API 연동** | ❌ | ⭕ 일부 | ✅ |

### 6. 시장 기회

```mermaid
graph TD
    A[한국 시장 갭] --> B[개인/소규모 사업자]
    B --> C[저렴한 API 호스팅 부재]
    B --> D[카카오봇 개발 수요]
    B --> E[한국 서비스 연동 필요]
    
    C --> F[도매까 API 플랫폼]
    D --> F
    E --> F
    
    style F fill:#34D399,stroke:#10B981,color:#fff
```

**한국 시장 현황**:
1. **구름IDE**: 개발 환경 중심, API 서버 운영은 비싸다
2. **네이버/NHN**: 기업 대상, 개인에게는 부담
3. **교육 플랫폼**: 상업적 사용 제한

**결론**: 
- 한국에는 **개인/소규모 사업자를 위한 저렴한 Python API 호스팅 서비스가 부재**
- 특히 **카카오봇 개발에 특화된 서비스는 전무**
- 월 5,000원~50,000원대 가격으로 충분한 시장 기회 존재

## 🔧 구현 난이도 분석

### 1. 기술적 난이도 평가

#### 쉬운 부분 (난이도: ⭐⭐)
1. **Docker 컨테이너 관리**
   - 성숙한 기술, 풍부한 문서
   - docker-py로 쉽게 자동화
   ```python
   import docker
   client = docker.from_env()
   container = client.containers.run(
       "python:3.9-slim",
       detach=True,
       mem_limit="400m"
   )
   ```

2. **웹 에디터 (Monaco Editor)**
   - Microsoft 제공 오픈소스
   - 바로 사용 가능한 컴포넌트

3. **PostgreSQL 사용자 관리**
   - 표준 SQL로 처리
   - 스키마 격리는 검증된 패턴

#### 중간 난이도 (⭐⭐⭐)
1. **리소스 모니터링**
   - cgroup 통계 수집
   - 실시간 메트릭 처리
   
2. **네트워크 격리**
   - iptables 규칙 관리
   - 도메인 화이트리스트

3. **자동 스케일링**
   - 컨테이너 풀 관리
   - 부하 분산

#### 어려운 부분 (⭐⭐⭐⭐⭐)
1. **보안 샌드박싱**
   - 악성 코드 방지
   - 리소스 남용 차단
   - 탈출 시도 방어

2. **멀티테넌트 격리**
   - 완벽한 사용자 격리
   - 성능 간섭 방지

3. **안정성 보장**
   - 24시간 무중단 운영
   - 자동 복구 시스템

### 2. 단계별 구현 전략

#### Phase 1: MVP (2-3개월)
```yaml
기능:
  - 기본 Docker 컨테이너 실행
  - 간단한 웹 에디터
  - SQLite DB 제공
  - 10명 사용자 제한

기술 스택:
  - FastAPI (관리 API)
  - Docker Python SDK
  - Monaco Editor
  - Nginx 리버스 프록시

예상 난이도: ⭐⭐⭐
```

#### Phase 2: 상용화 (3-4개월)
```yaml
추가 기능:
  - PostgreSQL 통합
  - 리소스 모니터링
  - 자동 백업
  - 50명 사용자 지원

추가 기술:
  - Prometheus + Grafana
  - PostgreSQL 레플리케이션
  - Redis 캐싱

예상 난이도: ⭐⭐⭐⭐
```

#### Phase 3: 확장 (6개월+)
```yaml
고급 기능:
  - Kubernetes 전환
  - 자동 스케일링
  - 고급 보안 기능
  - 500명+ 사용자

필요 기술:
  - Kubernetes 운영
  - Istio 서비스 메시
  - 분산 시스템 설계

예상 난이도: ⭐⭐⭐⭐⭐
```

### 3. 핵심 도전 과제와 해결책

| 도전 과제 | 난이도 | 해결 방안 |
|----------|--------|-----------|
| **악성 코드 실행** | ⭐⭐⭐⭐⭐ | seccomp, AppArmor, 코드 분석 |
| **리소스 독점** | ⭐⭐⭐⭐ | cgroup limits, 모니터링 |
| **데이터 유출** | ⭐⭐⭐⭐ | 네트워크 격리, 암호화 |
| **확장성** | ⭐⭐⭐ | 처음부터 수평 확장 설계 |
| **비용 관리** | ⭐⭐⭐ | 효율적 리소스 할당 |

### 4. 기존 오픈소스 활용

#### 활용 가능한 프로젝트
1. **Jupyter Hub** - 멀티유저 노트북 서버
2. **Coder** - 원격 개발 환경
3. **Eclipse Che** - 클라우드 IDE
4. **Gitpod** - 자동화된 개발 환경

#### 도매까 커스터마이징
```python
# JupyterHub 기반 커스터마이징 예시
from jupyterhub.spawner import DockerSpawner

class DomaekaSpawner(DockerSpawner):
    def start(self):
        # 도매까 전용 이미지 사용
        self.image = 'domaeka/python-api:latest'
        
        # 사용자별 리소스 제한
        self.mem_limit = '400m'
        self.cpu_limit = 0.1
        
        # 네트워크 격리
        self.network_mode = 'none'
        
        return super().start()
```

### 5. 현실적 평가

**구현 가능성: 70%**

✅ **가능한 이유**:
- Docker, PostgreSQL 등 성숙한 기술 스택
- 오픈소스 프로젝트 참고 가능
- MVP는 2-3개월 내 구현 가능

⚠️ **주의할 점**:
- 보안이 가장 큰 도전 과제
- 초기에는 제한된 기능으로 시작
- 점진적 개선 필요

**권장사항**:
1. MVP로 10-20명 베타 테스트
2. 피드백 받아 개선
3. 점진적 확장

**결론**: 어렵지만 충분히 구현 가능하며, 특히 카카오봇 특화로 범위를 좁히면 더 쉬워집니다.