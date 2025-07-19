# Docker 설정 안내

## 파일 구조 확인사항

### 1. 운영 환경 (domaeka.live)
- ✅ **웹 서버**: `/domaeka.live/web/Dockerfile` 
- ✅ **Python 서버**: `/domaeka.live/server/Dockerfile`

### 2. 테스트 환경 (domaeka.test)
- ⚠️ **웹 서버**: `/domaeka.test/web/Dockerfile` 필요

테스트 환경의 Dockerfile이 없는 경우, 다음 중 하나를 선택하세요:

#### 옵션 1: 운영 환경 Dockerfile 복사
```bash
cp domaeka.live/web/Dockerfile domaeka.test/web/
```

#### 옵션 2: 심볼릭 링크 생성
```bash
cd domaeka.test/web
ln -s ../../domaeka.live/web/Dockerfile Dockerfile
```

#### 옵션 3: docker-compose.yml 수정
```yaml
domaeka-dev-web:
  build:
    context: ./domaeka.live/web  # 운영 환경 Dockerfile 사용
    dockerfile: Dockerfile
```

## docker-compose.yml 사용법

### 1. 파일 복사
```bash
cd /data/projects/domaeka
cp domaeka.live/.cfg/docker-compose.yml .
```

### 2. 이미지 빌드 및 실행
```bash
# 모든 서비스 빌드 및 시작
docker-compose up -d --build

# 특정 서비스만 빌드
docker-compose build domaeka-web
docker-compose build domaeka-bot-server

# 로그 확인
docker-compose logs -f
```

### 3. Python 봇 서버 설정 확인
Python 봇 서버는 supervisord.conf에 정의된 여러 프로세스를 실행합니다:
- domaeka-test-01 (포트 1490)
- domaeka-live-01 (포트 1491)

## 주요 변경사항

### 기존 방식
```yaml
image: nginx-php8  # 사전 빌드된 이미지 사용
```

### 새로운 방식
```yaml
build:
  context: ./domaeka.live/web
  dockerfile: Dockerfile  # Dockerfile에서 빌드
```

## 문제 해결

### nginx-php8 이미지가 없다는 오류
더 이상 사전 빌드된 이미지를 사용하지 않습니다. 
`docker-compose build`로 이미지를 생성하세요.

### 포트 충돌
다른 서비스가 같은 포트를 사용 중인 경우:
```yaml
ports:
  - "8089:80"  # 왼쪽 숫자를 다른 포트로 변경
```

### 로그 디렉토리 권한
```bash
mkdir -p logs/{nginx,nginx_dev,php,php_dev,python,mysql}
chmod 755 logs/*
```