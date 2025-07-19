# Tailscale + Docker 개발 환경 팁

## Tailscale이란?
- VPN 서비스로 모든 디바이스를 안전하게 연결
- 복잡한 설정 없이 자동으로 네트워크 구성
- 각 디바이스에 고유한 IP와 도메인 부여

## Tailscale의 장점

### 1. 고정 IP처럼 사용 가능
```bash
# Tailscale IP는 변하지 않음
tailscale ip -4
# 100.64.x.x 형태의 고정 IP

# 도메인도 고정
# mycomputer.tail1234.ts.net
```

### 2. 팀 협업에 최적
- 팀원들이 개발 서버에 직접 접속 가능
- 포트 포워딩, 공유기 설정 불필요
- HTTPS 자동 지원

### 3. 보안
- 모든 트래픽 암호화
- 인증된 사용자만 접속

## Docker + Tailscale 활용법

### 1. Docker 컨테이너 직접 노출
```yaml
services:
  web:
    ports:
      - "0.0.0.0:8080:80"  # 모든 인터페이스에 바인딩
```

### 2. Tailscale Subnet Router (고급)
WSL을 Tailscale 서브넷 라우터로 설정:
```bash
# Docker 네트워크 대역 광고
sudo tailscale up --advertise-routes=172.16.0.0/12
```

이제 팀원들이 Docker 내부 네트워크에 직접 접속 가능!

### 3. 개발 서버 URL 공유
```bash
# 팀원에게 공유할 URL 생성 스크립트
cat > share-urls.sh << 'EOF'
#!/bin/bash
TAILSCALE_NAME=$(tailscale status | grep $(hostname) | awk '{print $2}')
echo "=== 개발 서버 접속 정보 ==="
echo "웹: http://${TAILSCALE_NAME}:8080"
echo "API: http://${TAILSCALE_NAME}:1490"
echo "DB: ${TAILSCALE_NAME}:3306"
echo "========================"
EOF

chmod +x share-urls.sh
./share-urls.sh
```

## 실제 사용 시나리오

### 시나리오 1: 모바일 앱 개발
```yaml
# API 서버를 Tailscale로 노출
services:
  api:
    ports:
      - "0.0.0.0:3000:3000"
```
모바일 개발자가 실제 디바이스에서 `http://your-pc.tail1234.ts.net:3000` 접속

### 시나리오 2: 클라이언트 데모
1. 개발 서버 실행
2. Tailscale URL 공유
3. 클라이언트가 브라우저로 접속
4. 실시간 피드백 받으며 개발

### 시나리오 3: DB 공유
```yaml
mariadb:
  ports:
    - "0.0.0.0:3306:3306"  # 주의: 보안 고려
```
팀원이 DB 클라이언트로 직접 연결 가능

## 보안 고려사항

### 1. 서비스별 접근 제어
```yaml
# 특정 서비스만 Tailscale에 노출
services:
  web:
    ports:
      - "0.0.0.0:80:80"    # Tailscale 노출
  
  mariadb:
    ports:
      - "127.0.0.1:3306:3306"  # 로컬만
```

### 2. Tailscale ACL 설정
Tailscale 관리 콘솔에서 접근 제어:
```json
{
  "acls": [
    {
      "action": "accept",
      "users": ["team@example.com"],
      "ports": ["*:8080", "*:1490"]
    }
  ]
}
```

## 문제 해결

### Tailscale이 WSL에서 작동하지 않을 때
```bash
# systemd 활성화 필요 (WSL2)
# /etc/wsl.conf 생성
sudo tee /etc/wsl.conf << EOF
[boot]
systemd=true
EOF

# WSL 재시작
wsl --shutdown
# Windows에서 다시 WSL 실행
```

### 네트워크 충돌
```bash
# Docker 네트워크 확인
docker network ls
docker network inspect bridge

# Tailscale과 충돌하지 않는 대역 사용
docker network create --subnet=172.20.0.0/16 custom-network
```

## 최적의 개발 환경 구성

1. **로컬 개발**: localhost 사용
2. **팀 공유**: Tailscale URL 사용  
3. **프로덕션 미러링**: Docker Compose로 동일 환경 구성
4. **자동화**: 스크립트로 URL 생성 및 공유

이렇게 하면 WSL + Docker + Tailscale로 완벽한 개발 환경 구축 가능!