# Infrastructure 구성 가이드

개발 환경에서 사용할 인프라 서비스들을 Docker로 구성하는 가이드입니다.

## 전체 구조

```
~/
├── infrastructure/                # 인프라 서비스
│   ├── nginx-proxy-manager/      # 리버스 프록시 & SSL
│   ├── portainer/                # Docker 관리 UI
│   ├── monitoring/               # 모니터링 도구
│   └── manage-infra.sh           # 통합 관리 스크립트
│
└── projects/                     # 실제 프로젝트
    └── domaeka/
```

## 1. 기본 설정

### 1.1 인프라 디렉토리 생성
```bash
mkdir -p ~/infrastructure
cd ~/infrastructure
```

### 1.2 공용 네트워크 생성
```bash
# 모든 서비스가 통신할 네트워크
docker network create docker-network
```

## 2. Nginx Proxy Manager (NPM)

### 2.1 설치
```bash
mkdir -p ~/infrastructure/nginx-proxy-manager
cd ~/infrastructure/nginx-proxy-manager

cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  npm:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'        # HTTP
      - '443:443'      # HTTPS
      - '81:81'        # 관리자 패널
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
    networks:
      - docker-network
    environment:
      - DISABLE_IPV6=true

networks:
  docker-network:
    external: true
EOF

# 시작
docker compose up -d
```

### 2.2 초기 설정
1. http://localhost:81 접속
2. 초기 로그인:
   - Email: `admin@example.com`
   - Password: `changeme`
3. 즉시 비밀번호 변경!

### 2.3 프록시 호스트 추가
1. **Proxy Hosts** → **Add Proxy Host**
2. 도매까 프로젝트 설정:
   ```
   Domain Names: domaeka.local
   Scheme: http
   Forward Hostname: domaeka-web-dev
   Forward Port: 80
   
   ✓ Block Common Exploits
   ✓ Websockets Support
   ```

### 2.4 Tailscale 도메인 설정 (선택사항)
```
Domain Names: domaeka.[your-name].tail1234.ts.net
```
SSL 탭에서 Let's Encrypt 인증서 자동 발급

## 3. Portainer

### 3.1 설치
```bash
mkdir -p ~/infrastructure/portainer
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
      - '9443:9443'  # HTTPS (선택사항)
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./data:/data
    networks:
      - docker-network

networks:
  docker-network:
    external: true
EOF

# 시작
docker compose up -d
```

### 3.2 초기 설정
1. http://localhost:9000 접속
2. Admin 계정 생성
3. Docker 환경 선택 → **Local** 선택

### 3.3 NPM으로 접속 설정 (선택사항)
NPM에서 프록시 호스트 추가:
```
Domain Names: portainer.local
Forward Hostname: portainer
Forward Port: 9000
```

## 4. 모니터링 도구

### 4.1 간단한 모니터링 (초보자 추천)
```bash
mkdir -p ~/infrastructure/monitoring-simple
cd ~/infrastructure/monitoring-simple

cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  # 실시간 로그 뷰어
  dozzle:
    image: amir20/dozzle:latest
    container_name: dozzle
    restart: unless-stopped
    ports:
      - "8888:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    networks:
      - docker-network
    environment:
      - DOZZLE_ENABLE_ACTIONS=true

  # 시스템 모니터
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
    pid: host

networks:
  docker-network:
    external: true
EOF

# 시작
docker compose up -d
```

### 4.2 전체 모니터링 (고급)
```bash
mkdir -p ~/infrastructure/monitoring-full
cd ~/infrastructure/monitoring-full

cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    container_name: prometheus
    restart: unless-stopped
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - ./prometheus-data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.enable-lifecycle'
    networks:
      - docker-network

  grafana:
    image: grafana/grafana:latest
    container_name: grafana
    restart: unless-stopped
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_USER=admin
      - GF_SECURITY_ADMIN_PASSWORD=admin123
      - GF_USERS_ALLOW_SIGN_UP=false
    volumes:
      - ./grafana-data:/var/lib/grafana
      - ./grafana-provisioning:/etc/grafana/provisioning
    networks:
      - docker-network

  node-exporter:
    image: prom/node-exporter:latest
    container_name: node-exporter
    restart: unless-stopped
    ports:
      - "9100:9100"
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.rootfs=/rootfs'
      - '--path.sysfs=/host/sys'
      - '--collector.filesystem.mount-points-exclude=^/(sys|proc|dev|host|etc)($$|/)'
    networks:
      - docker-network

  cadvisor:
    image: gcr.io/cadvisor/cadvisor:latest
    container_name: cadvisor
    restart: unless-stopped
    ports:
      - "8080:8080"
    volumes:
      - /:/rootfs:ro
      - /var/run:/var/run:ro
      - /sys:/sys:ro
      - /var/lib/docker/:/var/lib/docker:ro
      - /dev/disk/:/dev/disk:ro
    privileged: true
    devices:
      - /dev/kmsg
    networks:
      - docker-network

networks:
  docker-network:
    external: true
EOF

# Prometheus 설정
cat > prometheus.yml << 'EOF'
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'node'
    static_configs:
      - targets: ['node-exporter:9100']

  - job_name: 'cadvisor'
    static_configs:
      - targets: ['cadvisor:8080']

  - job_name: 'grafana'
    static_configs:
      - targets: ['grafana:3000']
EOF

# 시작
docker compose up -d
```

## 5. 통합 관리 스크립트

### 5.1 관리 스크립트 생성
```bash
cd ~/infrastructure

cat > manage-infra.sh << 'EOF'
#!/bin/bash

ACTION=$1
SERVICE=$2

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# 서비스 목록
declare -A SERVICES=(
    ["npm"]="nginx-proxy-manager"
    ["portainer"]="portainer"
    ["monitoring"]="monitoring-simple"
    ["monitoring-full"]="monitoring-full"
)

function start_all() {
    echo -e "${GREEN}모든 인프라 서비스 시작${NC}"
    for dir in */; do
        if [ -f "$dir/docker-compose.yml" ]; then
            echo -e "${YELLOW}Starting $dir${NC}"
            (cd "$dir" && docker compose up -d)
        fi
    done
}

function stop_all() {
    echo -e "${RED}모든 인프라 서비스 중지${NC}"
    for dir in */; do
        if [ -f "$dir/docker-compose.yml" ]; then
            echo -e "${YELLOW}Stopping $dir${NC}"
            (cd "$dir" && docker compose down)
        fi
    done
}

function status() {
    echo -e "${GREEN}인프라 서비스 상태${NC}"
    echo ""
    docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | grep -E "(portainer|npm|dozzle|glances|prometheus|grafana|NAMES)"
}

function logs() {
    if [ -z "$SERVICE" ]; then
        echo "Usage: $0 logs <service>"
        echo "Services: npm, portainer, monitoring"
        exit 1
    fi
    
    SERVICE_DIR=${SERVICES[$SERVICE]}
    if [ -z "$SERVICE_DIR" ]; then
        echo "Unknown service: $SERVICE"
        exit 1
    fi
    
    (cd "$SERVICE_DIR" && docker compose logs -f)
}

function urls() {
    echo -e "${BLUE}=== 서비스 접속 URL ===${NC}"
    echo ""
    echo -e "${GREEN}Nginx Proxy Manager${NC}"
    echo "  관리자: http://localhost:81"
    echo ""
    echo -e "${GREEN}Portainer${NC}"
    echo "  Docker 관리: http://localhost:9000"
    echo ""
    echo -e "${GREEN}모니터링${NC}"
    echo "  로그 뷰어 (Dozzle): http://localhost:8888"
    echo "  시스템 모니터 (Glances): http://localhost:61208"
    echo "  메트릭 (Prometheus): http://localhost:9090"
    echo "  대시보드 (Grafana): http://localhost:3000"
    echo ""
    echo -e "${GREEN}로컬 도메인 (hosts 파일 설정 필요)${NC}"
    echo "  웹: http://domaeka.local"
    echo "  Portainer: http://portainer.local"
}

case $ACTION in
    start)
        start_all
        ;;
    stop)
        stop_all
        ;;
    restart)
        stop_all
        sleep 2
        start_all
        ;;
    status)
        status
        ;;
    logs)
        logs
        ;;
    urls)
        urls
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|urls}"
        echo ""
        echo "Commands:"
        echo "  start    - 모든 인프라 서비스 시작"
        echo "  stop     - 모든 인프라 서비스 중지"
        echo "  restart  - 모든 서비스 재시작"
        echo "  status   - 서비스 상태 확인"
        echo "  logs     - 특정 서비스 로그 보기"
        echo "  urls     - 접속 URL 확인"
        exit 1
        ;;
esac
EOF

chmod +x manage-infra.sh
```

## 6. Windows hosts 파일 설정

PowerShell을 관리자 권한으로 실행:
```powershell
notepad C:\Windows\System32\drivers\etc\hosts
```

추가:
```
127.0.0.1  domaeka.local
127.0.0.1  portainer.local
127.0.0.1  grafana.local
127.0.0.1  prometheus.local
```

## 7. 설치 순서 (권장)

```bash
# 1. 인프라 디렉토리로 이동
cd ~/infrastructure

# 2. NPM 설치 (필수)
cd nginx-proxy-manager
docker compose up -d
cd ..

# 3. Portainer 설치 (권장)
cd portainer
docker compose up -d
cd ..

# 4. 간단한 모니터링 설치 (선택)
cd monitoring-simple
docker compose up -d
cd ..

# 5. 전체 상태 확인
./manage-infra.sh status

# 6. 접속 URL 확인
./manage-infra.sh urls
```

## 8. 문제 해결

### 포트 충돌
```bash
# 사용 중인 포트 확인
netstat -tlnp | grep :80
```

### 네트워크 문제
```bash
# 네트워크 재생성
docker network rm docker-network
docker network create docker-network
```

### 권한 문제 (WSL)
```bash
# Docker 소켓 권한
sudo chmod 666 /var/run/docker.sock
```

## 9. 다음 단계

인프라가 준비되면:
1. `~/projects/domaeka`에서 프로젝트 실행
2. NPM에서 프록시 호스트 설정
3. Portainer에서 컨테이너 관리
4. 모니터링 도구로 상태 확인

이제 완전한 개발 인프라가 준비되었습니다!