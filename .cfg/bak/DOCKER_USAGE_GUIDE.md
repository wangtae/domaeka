# 도매까 Docker 사용 가이드

## 1. 사전 준비사항

### 1.1 디렉토리 구조 확인
```
/data/projects/domaeka/
├── docker-compose.yml         # 이 파일을 새로 만들거나 수정해야 함
├── domaeka.live/             # 운영 환경
│   ├── server/
│   │   └── Dockerfile        # Python 서버용 (확인 필요)
│   └── web/
│       └── Dockerfile        # PHP 웹 서버용 (확인 필요)
└── domaeka.test/             # 테스트 환경
    └── web/
        └── Dockerfile        # 없으면 생성 필요
```

### 1.2 Docker 네트워크 확인
```bash
# Docker 네트워크 목록 확인
docker network ls

# docker-network가 없으면 생성
docker network create docker-network
```

## 2. 설정 파일 준비

### 2.1 docker-compose.yml 복사
```bash
cd /data/projects/domaeka
cp domaeka.live/.cfg/docker-compose.yml .
```

### 2.2 테스트 환경 Dockerfile 준비
```bash
# 옵션 1: 운영 환경 Dockerfile 복사 (권장)
cp domaeka.live/web/Dockerfile domaeka.test/web/

# 옵션 2: 심볼릭 링크 생성
cd domaeka.test/web
ln -s ../../domaeka.live/web/Dockerfile Dockerfile
cd /data/projects/domaeka
```

### 2.3 로그 디렉토리 생성
```bash
mkdir -p logs/{nginx,nginx_dev,php,php_dev,python,mysql}
```

## 3. Docker 이미지 빌드 및 실행

### 3.1 기존 컨테이너 중지 (필요시)
```bash
# 현재 실행 중인 컨테이너 확인
docker-compose ps

# 기존 컨테이너 중지 및 제거
docker-compose down
```

### 3.2 이미지 빌드
```bash
# 모든 서비스 빌드
docker-compose build

# 또는 특정 서비스만 빌드
docker-compose build domaeka-web
docker-compose build domaeka-bot-server
```

### 3.3 컨테이너 실행
```bash
# 빌드와 실행을 한번에 (권장)
docker-compose up -d --build

# 또는 이미 빌드된 이미지로 실행
docker-compose up -d
```

## 4. 상태 확인

### 4.1 컨테이너 상태
```bash
# 실행 중인 컨테이너 확인
docker-compose ps

# 모든 컨테이너 확인 (중지된 것 포함)
docker-compose ps -a
```

### 4.2 로그 확인
```bash
# 모든 서비스 로그
docker-compose logs -f

# 특정 서비스 로그
docker-compose logs -f domaeka-web
docker-compose logs -f domaeka-bot-server

# 최근 100줄만 보기
docker-compose logs --tail=100 domaeka-bot-server
```

### 4.3 서비스 접속 확인
- **운영 웹**: http://서버IP:8089
- **테스트 웹**: http://서버IP:8090
- **Python 봇 테스트**: 서버IP:1490
- **Python 봇 운영**: 서버IP:1491
- **MariaDB**: 서버IP:3307

## 5. 일상적인 운영 명령어

### 5.1 서비스 재시작
```bash
# 특정 서비스만 재시작
docker-compose restart domaeka-web

# 모든 서비스 재시작
docker-compose restart
```

### 5.2 서비스 중지/시작
```bash
# 중지
docker-compose stop domaeka-bot-server

# 시작
docker-compose start domaeka-bot-server
```

### 5.3 컨테이너 접속
```bash
# 웹 서버 접속
docker-compose exec domaeka-web bash

# Python 서버 접속
docker-compose exec domaeka-bot-server bash

# MariaDB 접속
docker-compose exec domaeka-mariadb mysql -u root -p
```

## 6. 업데이트 절차

### 6.1 코드만 변경된 경우
볼륨으로 마운트되므로 재시작만 하면 됨:
```bash
docker-compose restart domaeka-web
```

### 6.2 Dockerfile 수정된 경우
재빌드 필요:
```bash
docker-compose up -d --build domaeka-web
```

### 6.3 새로운 패키지 설치 필요시
1. Dockerfile 수정
2. 재빌드 및 재시작
```bash
docker-compose build domaeka-web
docker-compose up -d domaeka-web
```

## 7. 문제 해결

### 7.1 포트 충돌
```
Error: bind: address already in use
```
해결:
1. 사용 중인 포트 확인: `netstat -tlnp | grep 8089`
2. docker-compose.yml에서 포트 변경

### 7.2 이미지 빌드 실패
```bash
# 캐시 없이 처음부터 빌드
docker-compose build --no-cache domaeka-web
```

### 7.3 디스크 공간 부족
```bash
# 사용하지 않는 이미지 정리
docker image prune -a

# 사용하지 않는 컨테이너, 네트워크, 볼륨 정리
docker system prune -a
```

### 7.4 Python 서버 DB 연결 실패
Python 서버는 Docker 네트워크 내에서 실행되므로:
- DB 호스트: `domaeka-mariadb` (localhost 아님)
- DB 포트: `3306` (내부 포트)

## 8. 백업 및 복원

### 8.1 데이터베이스 백업
```bash
docker-compose exec domaeka-mariadb mysqldump -u root -p domaeka > backup.sql
```

### 8.2 데이터베이스 복원
```bash
docker-compose exec -T domaeka-mariadb mysql -u root -p domaeka < backup.sql
```

## 9. 모니터링

### 9.1 리소스 사용량
```bash
# CPU, 메모리 사용량
docker stats

# 특정 컨테이너만
docker stats domaeka-bot-server
```

### 9.2 프로세스 확인
```bash
# Python 서버의 supervisor 프로세스 확인
docker-compose exec domaeka-bot-server supervisorctl status
```

## 10. 전체 재구축 (최후의 수단)

```bash
# 1. 모든 것 중지
docker-compose down

# 2. 이미지 삭제 (주의!)
docker-compose down --rmi all

# 3. 처음부터 다시 빌드 및 실행
docker-compose up -d --build
```

---

## 빠른 시작 (Quick Start)

```bash
# 1. 프로젝트 디렉토리로 이동
cd /data/projects/domaeka

# 2. docker-compose.yml 복사
cp domaeka.live/.cfg/docker-compose.yml .

# 3. 테스트 환경 Dockerfile 복사
cp domaeka.live/web/Dockerfile domaeka.test/web/

# 4. 빌드 및 실행
docker-compose up -d --build

# 5. 상태 확인
docker-compose ps

# 6. 로그 확인
docker-compose logs -f
```