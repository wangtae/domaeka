# WSL Docker 빠른 시작 가이드

## 전제 조건
- Windows 11 또는 Windows 10 (버전 2004 이상)
- WSL2 설치됨
- Docker Desktop 설치됨

## 5분 안에 시작하기

### 1. Docker Desktop 설정 확인
Windows에서 Docker Desktop 실행 후:
- Settings → General → "Use the WSL 2 based engine" ✓
- Settings → Resources → WSL Integration → Ubuntu (또는 사용 중인 배포판) ✓

### 2. WSL 터미널에서 실행

```bash
# 1. 프로젝트 폴더 생성
mkdir -p ~/projects/domaeka
cd ~/projects/domaeka

# 2. 프로젝트 클론 (Git 사용 시)
git clone [프로젝트 URL] domaeka.dev

# 3. 개발용 docker-compose.yml 생성
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  web:
    build:
      context: ./domaeka.dev/web
      dockerfile: Dockerfile
    ports:
      - "8080:80"
    volumes:
      - ./domaeka.dev/web:/var/www/html
    networks:
      - dev-network

  mariadb:
    image: mariadb:10.3.7
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root123
      MYSQL_DATABASE: domaeka
    volumes:
      - ./mysql-data:/var/lib/mysql
    networks:
      - dev-network

  python-bot:
    build:
      context: ./domaeka.dev/server
      dockerfile: Dockerfile
    ports:
      - "1490:1490"
    volumes:
      - ./domaeka.dev/server:/app
    networks:
      - dev-network

networks:
  dev-network:
    driver: bridge
EOF

# 4. 실행
docker-compose up -d --build

# 5. 상태 확인
docker-compose ps
```

### 3. 접속
- 웹: http://localhost:8080
- DB: localhost:3306 (root/root123)
- Bot: localhost:1490

## Tailscale로 외부 접속 (선택사항)

### 1. Tailscale 설치
```bash
# WSL에서 실행
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

### 2. 접속 URL 확인
```bash
# Tailscale 네트워크 이름 확인
tailscale ip -4
# 예: 100.64.0.1

# 또는 Tailscale 도메인 확인
tailscale status | grep $(hostname)
# 예: mypc.tail1234.ts.net
```

이제 팀원들이 다음으로 접속 가능:
- http://[your-tailscale-name]:8080 (웹)
- [your-tailscale-name]:1490 (봇)

## 일반적인 명령어

```bash
# 중지
docker-compose down

# 재시작
docker-compose restart

# 로그 보기
docker-compose logs -f

# 특정 서비스만 재시작
docker-compose restart web
```

## 팁

1. **VSCode 사용 시**
   - Docker 확장 설치
   - Remote-WSL 확장 설치
   - WSL 내에서 `code .` 실행

2. **성능 최적화**
   - 프로젝트 폴더를 WSL 파일시스템에 위치 (`~/projects/`)
   - Windows 경로 (`/mnt/c/...`) 사용 피하기

3. **DB 클라이언트**
   - Windows에서 HeidiSQL, DBeaver 등 사용
   - 접속: localhost:3306