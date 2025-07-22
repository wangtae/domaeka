# 인프라 빠른 설치 가이드

5분 안에 모든 인프라를 설치하는 스크립트입니다.

## 자동 설치 스크립트

```bash
#!/bin/bash
# quick-setup.sh

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== 인프라 자동 설치 시작 ===${NC}"

# 1. 기본 디렉토리 생성
echo -e "${YELLOW}1. 디렉토리 생성${NC}"
mkdir -p ~/infrastructure/{nginx-proxy-manager,portainer,monitoring-simple}

# 2. 네트워크 생성
echo -e "${YELLOW}2. Docker 네트워크 생성${NC}"
docker network create docker-network 2>/dev/null || true

# 3. NPM 설치
echo -e "${YELLOW}3. Nginx Proxy Manager 설치${NC}"
cd ~/infrastructure/nginx-proxy-manager
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  npm:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'
      - '443:443'
      - '81:81'
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
    networks:
      - docker-network
networks:
  docker-network:
    external: true
EOF
docker compose up -d

# 4. Portainer 설치
echo -e "${YELLOW}4. Portainer 설치${NC}"
cd ~/infrastructure/portainer
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  portainer:
    image: portainer/portainer-ce:latest
    container_name: portainer
    restart: unless-stopped
    ports:
      - '9000:9000'
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./data:/data
    networks:
      - docker-network
networks:
  docker-network:
    external: true
EOF
docker compose up -d

# 5. 모니터링 설치
echo -e "${YELLOW}5. 모니터링 도구 설치${NC}"
cd ~/infrastructure/monitoring-simple
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  dozzle:
    image: amir20/dozzle:latest
    container_name: dozzle
    restart: unless-stopped
    ports:
      - "8888:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    networks:
      - npm-network
  glances:
    image: nicolargo/glances:alpine-latest-full
    container_name: glances
    restart: unless-stopped
    ports:
      - "61208:61208"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    environment:
      - GLANCES_OPT=-w
    networks:
      - docker-network
networks:
  docker-network:
    external: true
EOF
docker compose up -d

# 6. 관리 스크립트 생성
cd ~/infrastructure
cat > manage-infra.sh << 'EOF'
#!/bin/bash
case $1 in
  start)
    for dir in */; do
      [ -f "$dir/docker-compose.yml" ] && (cd "$dir" && docker compose up -d)
    done
    ;;
  stop)
    for dir in */; do
      [ -f "$dir/docker-compose.yml" ] && (cd "$dir" && docker compose down)
    done
    ;;
  status)
    docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    ;;
  *)
    echo "Usage: $0 {start|stop|status}"
    ;;
esac
EOF
chmod +x manage-infra.sh

# 완료
echo -e "${GREEN}=== 설치 완료 ===${NC}"
echo ""
echo "접속 정보:"
echo "- NPM 관리자: http://localhost:81 (admin@example.com / changeme)"
echo "- Portainer: http://localhost:9000"
echo "- 로그 뷰어: http://localhost:8888"
echo "- 시스템 모니터: http://localhost:61208"
echo ""
echo "상태 확인: cd ~/infrastructure && ./manage-infra.sh status"
```

## 실행 방법

### 1. 스크립트 다운로드 및 실행
```bash
# 스크립트 생성
cat > ~/quick-infra-setup.sh << 'SCRIPT'
[위의 전체 스크립트 내용 붙여넣기]
SCRIPT

# 실행 권한 부여
chmod +x ~/quick-infra-setup.sh

# 실행
~/quick-infra-setup.sh
```

### 2. 설치 확인
```bash
# 상태 확인
cd ~/infrastructure
./manage-infra.sh status
```

## 접속 및 초기 설정

### NPM (필수)
1. http://localhost:81 접속
2. 초기 로그인: `admin@example.com` / `changeme`
3. **즉시 비밀번호 변경!**

### Portainer
1. http://localhost:9000 접속
2. Admin 계정 생성
3. Local Docker 선택

### 모니터링
- 로그: http://localhost:8888
- 시스템: http://localhost:61208

## hosts 파일 설정 (선택사항)

Windows PowerShell (관리자):
```powershell
Add-Content C:\Windows\System32\drivers\etc\hosts "`n127.0.0.1  domaeka.local"
Add-Content C:\Windows\System32\drivers\etc\hosts "127.0.0.1  portainer.local"
```

Linux/WSL:
```bash
echo "127.0.0.1  domaeka.local" | sudo tee -a /etc/hosts
echo "127.0.0.1  portainer.local" | sudo tee -a /etc/hosts
```

## 제거 방법

모든 인프라 제거:
```bash
cd ~/infrastructure
./manage-infra.sh stop
docker network rm docker-network
rm -rf ~/infrastructure
```

이제 5분 만에 완전한 개발 인프라가 준비되었습니다!