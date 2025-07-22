# Supervisor 설명

## Supervisor란?

Supervisor는 UNIX 계열 시스템에서 여러 프로세스를 모니터링하고 제어하는 프로세스 관리 시스템입니다.

## Docker에서 Supervisor가 필요한 이유

### 1. Docker의 기본 원칙
- Docker는 "한 컨테이너, 한 프로세스" 원칙을 권장
- 하지만 웹 애플리케이션은 여러 프로세스가 필요:
  - Nginx (웹 서버)
  - PHP-FPM (PHP 처리)
  - Cron (스케줄 작업)
  - 기타 백그라운드 서비스

### 2. Supervisor의 역할
```yaml
command: ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

이 명령은:
- 컨테이너가 시작될 때 Supervisor를 메인 프로세스로 실행
- Supervisor가 설정 파일에 정의된 모든 자식 프로세스를 시작하고 관리

### 3. 주요 기능
- **프로세스 시작**: 정의된 순서대로 프로세스 시작
- **자동 재시작**: 프로세스가 죽으면 자동으로 재시작
- **로그 관리**: 각 프로세스의 로그를 개별 관리
- **우선순위 관리**: 프로세스 시작 순서 제어

## 도매까 프로젝트에서의 활용

### 웹 컨테이너 (domaeka-web)
```
supervisord (PID 1)
├── nginx (웹 서버)
├── php-fpm (PHP 처리)
└── cron (예약 작업)
```

### Python 봇 서버
```
supervisord (PID 1)
├── domaeka-test-01 (테스트 봇)
└── domaeka-live-01 (운영 봇)
```

## 장점

1. **통합 관리**: 여러 프로세스를 하나의 컨테이너에서 관리
2. **안정성**: 프로세스 장애 시 자동 복구
3. **로깅**: 중앙 집중식 로그 관리
4. **모니터링**: supervisorctl로 실시간 상태 확인

## 관리 명령어

```bash
# 컨테이너 내부에서 상태 확인
docker exec domaeka-web supervisorctl status

# 특정 프로세스 재시작
docker exec domaeka-web supervisorctl restart nginx

# 로그 확인
docker exec domaeka-web tail -f /var/log/supervisor/supervisord.log
```

## 대안

1. **다중 컨테이너**: 각 프로세스를 별도 컨테이너로 분리
2. **Docker Compose**: depends_on으로 의존성 관리
3. **Kubernetes**: 프로덕션 환경에서 더 나은 오케스트레이션

하지만 간단한 웹 애플리케이션의 경우 Supervisor가 더 실용적입니다.