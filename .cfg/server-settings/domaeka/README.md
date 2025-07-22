# Domaeka 서버 설정 가이드

이 디렉토리는 실제 서버에 배포할 설정 파일들의 템플릿입니다.

## 디렉토리 구조

```
.cfg/server-settings/domaeka/
├── docker-compose.yml          # Docker Compose 설정
├── .env.example               # 환경 변수 예시
├── domaeka.live/
│   ├── Dockerfile.python-server  # Python 서버용 Docker 이미지
│   └── supervisord.conf         # Supervisor 설정
└── README.md                   # 이 파일
```

## 서버 배포 방법

### 1. 파일 복사

```bash
# 서버에서 실행
cd /data/projects/domaeka

# docker-compose.yml 복사
cp /path/to/.cfg/server-settings/domaeka/docker-compose.yml .

# Dockerfile 및 supervisor 설정 복사
cp /path/to/.cfg/server-settings/domaeka/domaeka.live/Dockerfile.python-server ./domaeka.live/
cp /path/to/.cfg/server-settings/domaeka/domaeka.live/supervisord.conf ./domaeka.live/

# 환경 변수 파일 생성
cp /path/to/.cfg/server-settings/domaeka/.env.example .env
vim .env  # 필요에 따라 수정
```

### 2. Docker 이미지 빌드

```bash
# Python 서버 이미지 빌드
docker-compose build domaeka-server-test-01
```

### 3. 서비스 실행

```bash
# 모든 서비스 시작
docker-compose up -d

# 특정 서비스만 시작
docker-compose up -d domaeka-server-test-01 domaeka-server-test-02

# 로그 확인
docker-compose logs -f domaeka-server-test-01
```

## 서비스 구성

### 웹 서비스
- **domaeka-web**: 메인 웹 서비스 (포트 8089)
- **domaeka-dev-web**: 개발 웹 서비스 (포트 8090)

### 데이터베이스
- **domaeka-mariadb**: MariaDB 10.3.7 (포트 3307)

### Python 서버
- **TEST 서버**: 
  - domaeka-server-test-01 (포트 1481, Supervisor UI 9101)
  - domaeka-server-test-02 (포트 1482, Supervisor UI 9102)
  - domaeka-server-test-03 (포트 1483, Supervisor UI 9103)
- **LIVE 서버**:
  - domaeka-server-live-01 (포트 1491, Supervisor UI 9111)
  - domaeka-server-live-02 (포트 1492, Supervisor UI 9112)
  - domaeka-server-live-03 (포트 1493, Supervisor UI 9113)

## Supervisor 웹 UI 접속

각 Python 서버는 Supervisor 웹 UI를 제공합니다:
- http://서버IP:9101-9103 (TEST 서버)
- http://서버IP:9111-9113 (LIVE 서버)
- 인증: admin / domaeka123

## 주의사항

1. `docker-network`는 미리 생성되어 있어야 합니다:
   ```bash
   docker network create docker-network
   ```

2. Python 서버의 `requirements.txt`는 `domaeka.live/server/` 디렉토리에 있어야 합니다.

3. 로그는 다음 위치에 저장됩니다:
   - 웹 로그: `./logs/nginx/`, `./logs/php/`
   - DB 로그: `./logs/mysql/`
   - Python 서버 로그: `./logs/server/[서버명]/`