#!/bin/bash

# 도매까 프로젝트 배포 스크립트
# 이 스크립트를 원격 서버의 /data/projects/domaeka/ 디렉토리에서 실행하세요

# 색상 정의
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== 도매까 프로젝트 Docker 배포 ===${NC}"
echo ""

# 현재 디렉토리 확인
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ docker-compose.yml 파일을 찾을 수 없습니다.${NC}"
    echo "   /data/projects/domaeka/ 디렉토리에서 실행하세요."
    exit 1
fi

# Docker 네트워크 확인
echo -e "${YELLOW}1. Docker 네트워크 확인${NC}"
if docker network ls | grep -q "docker-network"; then
    echo -e "${GREEN}✓ docker-network 존재${NC}"
else
    echo -e "${YELLOW}! docker-network 생성${NC}"
    docker network create docker-network
fi

# 로그 디렉토리 생성
echo -e "\n${YELLOW}2. 로그 디렉토리 생성${NC}"
mkdir -p logs/{nginx,nginx_dev,php,php_dev,mysql,python}
echo -e "${GREEN}✓ 로그 디렉토리 생성 완료${NC}"

# Docker Compose 실행
echo -e "\n${YELLOW}3. Docker 서비스 시작${NC}"
docker-compose down
docker-compose build
docker-compose up -d

# 상태 확인
echo -e "\n${YELLOW}4. 서비스 상태 확인${NC}"
docker-compose ps

# 헬스체크
echo -e "\n${YELLOW}5. 서비스 헬스체크${NC}"
sleep 5

# MariaDB 체크
if docker-compose exec -T domaeka-mariadb mysql -uroot -p\${MYSQL_ROOT_PASSWORD} -e "SELECT 1" >/dev/null 2>&1; then
    echo -e "${GREEN}✓ MariaDB 정상 작동${NC}"
else
    echo -e "${RED}✗ MariaDB 연결 실패${NC}"
fi

# Python 서버 체크
if docker-compose exec -T domaeka-bot-server python -c "print('Python OK')" >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Python 서버 정상 작동${NC}"
else
    echo -e "${RED}✗ Python 서버 실행 실패${NC}"
fi

echo -e "\n${GREEN}=== 배포 완료 ===${NC}"
echo ""
echo "서비스 접속 정보:"
echo "- 운영 웹: http://localhost:8089"
echo "- 개발 웹: http://localhost:8090"
echo "- 봇 서버 (테스트): localhost:1490"
echo "- 봇 서버 (운영): localhost:1491"
echo "- MariaDB: localhost:3307"
echo ""
echo "유용한 명령어:"
echo "- 로그 확인: docker-compose logs -f [서비스명]"
echo "- 재시작: docker-compose restart [서비스명]"
echo "- 중지: docker-compose down"