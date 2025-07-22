# 모니터링 리팩토링 가이드

## 현재 상황
- `system_monitor.py`: 전체 시스템 모니터링 (제거/비활성화 권장)
- `kb_server_processes`: 프로세스별 모니터링 필드 준비 완료

## 권장 변경사항

### 1. system_monitor.py 비활성화
```python
# main.py에서 제거
# from database.system_monitor import SystemMonitor  # 주석 처리
# system_monitor = SystemMonitor(db)                 # 주석 처리
# await system_monitor.start()                       # 주석 처리
```

### 2. 프로세스 자체 모니터링 추가
```python
# main.py에 추가
from core.process_self_monitor import ProcessSelfMonitor

# 프로세스 모니터 시작
if args.name:  # --name 옵션이 있을 때만
    process_monitor = ProcessSelfMonitor(args.name, db)
    await process_monitor.start()
```

### 3. requirements.txt 확인
```txt
psutil>=5.9.0  # 프로세스 모니터링용
```

## 모니터링 도구 분리

### Python 서버 (자체 모니터링)
- **역할**: 자신의 프로세스 리소스만 측정
- **저장**: `kb_server_processes` 테이블
- **항목**: PID, CPU 사용률, 메모리 사용량

### 외부 모니터링 도구 (시스템 전체)
- **Docker Stats**: `docker stats` 명령으로 컨테이너별 사용량 확인
- **Prometheus + Grafana**: 시스템 전체 모니터링
- **Datadog/New Relic**: 상용 APM 도구

## 장점
1. **책임 분리**: 각 프로세스는 자신만 관리
2. **정확성**: 프로세스별 정확한 측정
3. **확장성**: 프로세스 추가 시 자동으로 모니터링
4. **간소화**: 불필요한 시스템 모니터링 제거

## 웹 관리자에서 활용

```php
// 프로세스별 리소스 사용량 표시
$processes = sql_query("
    SELECT 
        process_name,
        port,
        status,
        cpu_usage,
        memory_usage,
        last_heartbeat
    FROM kb_server_processes
    WHERE last_heartbeat > NOW() - INTERVAL 5 MINUTE
    ORDER BY process_name
");

// 비정상 프로세스 알림
$high_cpu = sql_query("
    SELECT process_name, cpu_usage 
    FROM kb_server_processes 
    WHERE cpu_usage > 80.0 
    AND status = 'running'
");
```

## Docker에서 시스템 모니터링

```bash
# 실시간 모니터링
docker stats

# 특정 컨테이너만
docker stats domaeka-server-test-01

# JSON 형식으로 한 번만
docker stats --no-stream --format "json" domaeka-server-test-01
```

이렇게 분리하면 각 도구가 자신의 역할에만 집중할 수 있습니다.