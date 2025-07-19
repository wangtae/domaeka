# WSL 개발 환경에서 Docker 설정 가이드

## 1. WSL에서 Docker 설치

### 1.1 Docker Desktop 설치 (권장)
1. Windows에서 Docker Desktop 설치
2. 설정에서 "Use the WSL 2 based engine" 활성화
3. Resources → WSL Integration에서 사용할 WSL 배포판 선택

### 1.2 WSL에서 확인
```bash
# Docker 설치 확인
docker --version
docker-compose --version
```

## 2. 개발 환경용 Docker 구성

### 2.1 프로젝트 구조
```
~/projects/domaeka/
├── docker-compose.dev.yml    # 개발용 설정
├── nginx-proxy-manager/      # NPM 설정
│   └── docker-compose.yml
└── domaeka.dev/             # 프로젝트 코드
    ├── server/
    ├── web/
    └── .cfg/
```

### 2.2 개발용 docker-compose.yml 생성
`~/projects/domaeka/docker-compose.dev.yml`:

```yaml
version: '3.8'

services:
  domaeka-web-dev:
    build:
      context: ./domaeka.dev/web
      dockerfile: Dockerfile
    container_name: domaeka-web-dev
    networks:
      - domaeka-dev-net
      - npm-network  # NPM 네트워크에 연결
    volumes:
      - ./domaeka.dev/web:/var/www/html
      - ./conf/nginx:/etc/nginx
      - ./conf/php:/usr/local/etc/php
      - ./logs/nginx:/var/log/nginx
      - ./logs/php:/var/log/php
    environment:
      - VIRTUAL_HOST=domaeka.local,domaeka.${TAILSCALE_NAME}.ts.net
      - VIRTUAL_PORT=80
    restart: unless-stopped

  domaeka-bot-dev:
    build: 
      context: ./domaeka.dev/server
      dockerfile: Dockerfile
    container_name: domaeka-bot-dev
    networks:
      - domaeka-dev-net
    ports:
      - "1490:1490"
      - "1491:1491"
    volumes:
      - ./domaeka.dev/server:/app
      - ./domaeka.dev/web/data:/web/data:ro
      - ./logs/python:/app/logs
    environment:
      - PYTHONUNBUFFERED=1
      - TZ=Asia/Seoul
    restart: unless-stopped
    depends_on:
      - domaeka-mariadb-dev

  domaeka-mariadb-dev:
    image: mariadb:10.3.7
    container_name: domaeka-mariadb-dev
    networks:
      - domaeka-dev-net
    ports:
      - "3307:3306"
    environment:
      MYSQL_ROOT_PASSWORD: devpassword
      MYSQL_DATABASE: domaeka
      MYSQL_USER: domaeka
      MYSQL_PASSWORD: domaeka123
    volumes:
      - ./mysql-data-dev:/var/lib/mysql
      - ./initdb:/docker-entrypoint-initdb.d
    restart: unless-stopped

networks:
  domaeka-dev-net:
    driver: bridge
  npm-network:
    external: true  # NPM 네트워크 사용
```

## 3. Nginx Proxy Manager 설정

### 3.1 NPM Docker 설정
`~/projects/nginx-proxy-manager/docker-compose.yml`:

```yaml
version: '3.8'

services:
  npm:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'      # HTTP
      - '443:443'    # HTTPS
      - '81:81'      # 관리자 패널
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
    networks:
      - npm-network

networks:
  npm-network:
    driver: bridge
    name: npm-network
```

### 3.2 NPM 실행
```bash
cd ~/projects/nginx-proxy-manager
docker-compose up -d

# 초기 로그인
# URL: http://localhost:81
# Email: admin@example.com
# Password: changeme
```

## 4. Tailscale 통합

### 4.1 Tailscale 설치 (WSL 내부)
```bash
# Tailscale 설치
curl -fsSL https://tailscale.com/install.sh | sh

# Tailscale 시작
sudo tailscale up

# Tailscale 상태 확인
tailscale status
```

### 4.2 Tailscale 네트워크 이름 확인
```bash
# 현재 머신의 Tailscale 이름 확인
tailscale status | grep $(hostname)
# 예: mycomputer.tail1234.ts.net
```

### 4.3 환경 변수 설정
`.env` 파일 생성:
```bash
TAILSCALE_NAME=mycomputer.tail1234
```

## 5. 통합 실행

### 5.1 전체 시작 스크립트
`~/projects/domaeka/start-dev.sh`:

```bash
#!/bin/bash

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}개발 환경 시작${NC}"

# NPM 네트워크 생성
docker network create npm-network 2>/dev/null || true

# NPM 시작
echo -e "${YELLOW}1. Nginx Proxy Manager 시작${NC}"
cd ~/projects/nginx-proxy-manager
docker-compose up -d

# 도매까 프로젝트 시작
echo -e "${YELLOW}2. 도매까 프로젝트 시작${NC}"
cd ~/projects/domaeka
docker-compose -f docker-compose.dev.yml up -d --build

# 상태 확인
echo -e "${YELLOW}3. 서비스 상태${NC}"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 접속 정보
echo -e "${GREEN}접속 정보:${NC}"
echo "- NPM 관리자: http://localhost:81"
echo "- 로컬 웹: http://localhost:8089"
echo "- Tailscale: http://$(tailscale status | grep $(hostname) | awk '{print $1}')"
echo "- 봇 서버: localhost:1490 (테스트), localhost:1491 (운영)"
```

### 5.2 실행 권한 부여 및 실행
```bash
chmod +x ~/projects/domaeka/start-dev.sh
~/projects/domaeka/start-dev.sh
```

## 6. NPM에서 프록시 설정

### 6.1 NPM 관리자 패널 접속
1. http://localhost:81 접속
2. 로그인 (초기 비밀번호 변경)

### 6.2 프록시 호스트 추가
1. Proxy Hosts → Add Proxy Host
2. 설정:
   - Domain Names: `domaeka.local`, `domaeka.[tailscale-name].ts.net`
   - Scheme: `http`
   - Forward Hostname: `domaeka-web-dev`
   - Forward Port: `80`
   - Websockets Support: 활성화

### 6.3 SSL 설정 (Tailscale용)
1. SSL 탭에서 "Request a new SSL Certificate" 선택
2. Let's Encrypt 사용 (Tailscale 도메인은 자동 인증됨)

## 7. hosts 파일 설정 (로컬 개발용)

### Windows hosts 파일 수정
```powershell
# PowerShell 관리자 권한으로 실행
notepad C:\Windows\System32\drivers\etc\hosts
```

추가:
```
127.0.0.1  domaeka.local
```

## 8. 접속 방법

### 로컬 접속
- 웹: http://domaeka.local
- NPM: http://localhost:81
- DB: localhost:3307

### Tailscale 네트워크 접속
- 웹: https://domaeka.[your-tailscale-name].ts.net
- 봇: [your-tailscale-name].ts.net:1490

### 직접 포트 접속
- 웹: http://localhost:8089
- 봇: localhost:1490, localhost:1491

## 9. 개발 워크플로우

### 코드 수정 시
```bash
# 코드는 볼륨 마운트되어 있어 자동 반영
# PHP 재시작만 필요한 경우
docker-compose -f docker-compose.dev.yml restart domaeka-web-dev
```

### Dockerfile 수정 시
```bash
docker-compose -f docker-compose.dev.yml up -d --build domaeka-web-dev
```

### 로그 확인
```bash
# 실시간 로그
docker-compose -f docker-compose.dev.yml logs -f

# 특정 서비스 로그
docker-compose -f docker-compose.dev.yml logs -f domaeka-bot-dev
```

## 10. 장점

1. **실제 운영 환경과 동일**: 리모트 서버와 같은 환경
2. **HTTPS 지원**: NPM으로 SSL 자동 관리
3. **팀 협업**: Tailscale로 팀원들이 개발 서버 접속 가능
4. **격리된 환경**: 로컬 시스템 오염 없음
5. **쉬운 초기화**: 컨테이너 삭제로 깨끗한 환경

## 문제 해결

### WSL2 메모리 사용량 제한
`~/.wslconfig` (Windows 사용자 폴더):
```ini
[wsl2]
memory=4GB
processors=2
```

### Docker Desktop 느린 경우
Settings → Resources → WSL Integration → Enable integration 확인

### 네트워크 문제
```bash
# Docker 네트워크 재생성
docker network prune
docker network create npm-network
```