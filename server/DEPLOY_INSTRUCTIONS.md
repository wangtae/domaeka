# Python 봇 서버 Docker 배포 가이드

## 1. 서버 구조
```
/data/projects/domaeka/
├── docker-compose.yml          # 메인 도커 설정
├── domaeka.live/
│   ├── server/                 # Python 봇 서버
│   │   ├── main.py
│   │   ├── requirements.txt
│   │   ├── Dockerfile
│   │   └── supervisord.conf
│   └── web/                    # 웹 애플리케이션
│       └── data/               # 이미지 등 데이터
└── logs/
    └── python/                 # Python 서버 로그
```

## 2. docker-compose.yml에 추가할 내용

기존 `docker-compose.yml`의 `services:` 섹션에 다음 내용을 추가하세요:

```yaml
  domaeka-bot-server:
    build: 
      context: ./domaeka.live/server
      dockerfile: Dockerfile
    container_name: domaeka-bot-server
    networks:
      - docker-network
    ports:
      - "1490:1490"  # 테스트 서버 포트
      - "1491:1491"  # 라이브 서버 포트
    volumes:
      - ./domaeka.live/server:/app
      - ./domaeka.live/web/data:/web/data:ro  # 웹 데이터 읽기 전용
      - ./logs/python:/app/logs
    environment:
      - PYTHONUNBUFFERED=1
      - TZ=Asia/Seoul
    restart: always
    depends_on:
      - domaeka-mariadb
```

## 3. 배포 절차

### 3.1 로그 디렉토리 생성
```bash
cd /data/projects/domaeka
mkdir -p logs/python
```

### 3.2 Docker 이미지 빌드 및 실행
```bash
# 전체 서비스 중지 (필요시)
docker-compose down

# 새로운 이미지 빌드
docker-compose build domaeka-bot-server

# 전체 서비스 시작
docker-compose up -d

# 또는 Python 서버만 시작
docker-compose up -d domaeka-bot-server
```

### 3.3 상태 확인
```bash
# 컨테이너 상태 확인
docker-compose ps

# Python 서버 로그 확인
docker-compose logs -f domaeka-bot-server

# 또는 로그 파일 직접 확인
tail -f logs/python/*.log
```

## 4. 접속 방법

### 외부에서 접속
- 테스트 서버: `서버IP:1490`
- 라이브 서버: `서버IP:1491`

### 카카오봇 클라이언트 설정
카카오봇 클라이언트에서는 서버 IP와 포트로 직접 연결:
```javascript
// bridge.js 설정 예시
var BOT_CONFIG = {
    SERVERS: {
        PROD: [
            {url: "서버IP:1491", name: "Live Server"}
        ],
        TEST: [
            {url: "서버IP:1490", name: "Test Server"}
        ]
    }
};
```

## 5. 관리 명령어

### 서비스 재시작
```bash
docker-compose restart domaeka-bot-server
```

### 서비스 중지
```bash
docker-compose stop domaeka-bot-server
```

### 컨테이너 접속
```bash
docker-compose exec domaeka-bot-server bash
```

### 실시간 로그 모니터링
```bash
# Docker 로그
docker-compose logs -f domaeka-bot-server

# Supervisor 로그
docker-compose exec domaeka-bot-server tail -f /app/logs/supervisord.log

# 각 서버 프로세스 로그
docker-compose exec domaeka-bot-server tail -f /app/logs/domaeka-test-01.log
docker-compose exec domaeka-bot-server tail -f /app/logs/domaeka-live-01.log
```

## 6. 문제 해결

### DB 연결 확인
```bash
# 컨테이너 내부에서 DB 연결 테스트
docker-compose exec domaeka-bot-server python main.py --test-db
```

### 네트워크 확인
```bash
# Docker 네트워크 목록
docker network ls

# 네트워크 상세 정보
docker network inspect docker-network
```

### 프로세스 상태 확인
```bash
# Supervisor 상태 확인
docker-compose exec domaeka-bot-server supervisorctl status
```

## 7. 보안 고려사항

- 포트 1490, 1491은 실제 카카오봇만 접속할 수 있도록 방화벽 설정 권장
- NPM(Nginx Proxy Manager)를 통한 리버스 프록시 설정도 가능

## 8. 추가 서버 프로세스 실행

`supervisord.conf`를 수정하여 추가 서버 프로세스를 실행할 수 있습니다:

```ini
[program:domaeka-test-02]
command=python main.py --name domaeka-test-02
directory=/app
autostart=true
autorestart=true
stdout_logfile=/app/logs/domaeka-test-02.log
stderr_logfile=/app/logs/domaeka-test-02.error.log
```

수정 후 재시작:
```bash
docker-compose restart domaeka-bot-server
```