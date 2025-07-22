# 프로세스 모니터링 방법 비교

## 1. Python에서 직접 모니터링 (권장)

### 장점:
- **정확성**: 해당 프로세스의 실제 사용량 측정
- **유연성**: 원하는 메트릭 추가 가능
- **통합성**: DB 업데이트와 함께 처리

### 구현:
```python
import psutil
import os

# 현재 프로세스 객체
process = psutil.Process(os.getpid())

# CPU 사용률 (%)
cpu_percent = process.cpu_percent(interval=1)

# 메모리 사용량 (MB)
memory_mb = process.memory_info().rss / 1024 / 1024

# 추가 정보들
num_threads = process.num_threads()
num_fds = process.num_fds()  # 열린 파일 수 (Linux)
connections = len(process.connections())  # 네트워크 연결 수
```

## 2. Supervisor에서 모니터링

### Supervisor의 한계:
- Supervisor는 기본적으로 CPU/메모리 사용량을 직접 제공하지 않음
- XML-RPC API로는 프로세스 상태(running/stopped)만 확인 가능

### Supervisor에서 얻을 수 있는 정보:
```python
# XML-RPC를 통해 얻을 수 있는 정보
info = supervisor.getProcessInfo('python-server')
{
    'name': 'python-server',
    'state': 20,  # RUNNING
    'statename': 'RUNNING',
    'start': 1642851234,  # 시작 시간 (Unix timestamp)
    'now': 1642851300,    # 현재 시간
    'pid': 12345,         # PID
    'stdout_logfile': '/var/log/supervisor/python-server.out.log',
    'stderr_logfile': '/var/log/supervisor/python-server.err.log'
}
```

## 3. 하이브리드 접근법 (최적)

### 역할 분담:
1. **Supervisor**: 프로세스 생명주기 관리
   - 시작/중지/재시작
   - 자동 재시작
   - 로그 관리

2. **Python 프로세스**: 상세 모니터링
   - PID 등록
   - CPU/메모리 사용량
   - 하트비트

3. **웹 관리자**: 통합 제어
   - Supervisor API로 프로세스 제어
   - DB에서 모니터링 데이터 조회

### 구현 예시:

```python
# server/main.py 수정
import os
import psutil
import asyncio
from core.process_monitor import ProcessMonitor

async def main():
    # 프로세스 이름 가져오기
    process_name = args.name
    
    # 프로세스 모니터 시작
    monitor = ProcessMonitor(process_name, db)
    await monitor.register_process()
    
    # 기존 서버 로직...
    server = KakaoBotServer(port)
    await server.start()
```

## 4. 시스템 전체 모니터링 (선택사항)

컨테이너 레벨에서 추가 모니터링이 필요하면:

```yaml
# docker-compose.yml에 추가
services:
  prometheus:
    image: prom/prometheus
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    ports:
      - "9090:9090"
  
  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
```

## 권장사항

**Python 프로세스에서 직접 모니터링**하는 것을 권장합니다:
1. `psutil` 라이브러리 사용
2. 30초 간격으로 CPU/메모리 측정
3. DB에 즉시 업데이트
4. 비정상 종료 시에도 Supervisor가 재시작

이 방법이 가장 간단하고 효과적입니다.