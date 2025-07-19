#!/bin/bash

# 색상 정의
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}도매까 봇 서버 배포 스크립트${NC}"
echo "================================"

# 도커 네트워크 확인
echo -e "\n${YELLOW}1. Docker 네트워크 확인${NC}"
if docker network ls | grep -q "domaeka_default"; then
    echo -e "${GREEN}✓ domaeka_default 네트워크 존재${NC}"
else
    echo -e "${RED}✗ domaeka_default 네트워크가 없습니다.${NC}"
    echo "  다음 명령으로 생성하세요:"
    echo "  docker network create domaeka_default"
    exit 1
fi

# 기존 컨테이너 중지
echo -e "\n${YELLOW}2. 기존 컨테이너 중지${NC}"
docker-compose -f docker-compose.prod.yml down

# 이미지 빌드
echo -e "\n${YELLOW}3. Docker 이미지 빌드${NC}"
docker-compose -f docker-compose.prod.yml build

# 컨테이너 시작
echo -e "\n${YELLOW}4. 컨테이너 시작${NC}"
docker-compose -f docker-compose.prod.yml up -d

# 상태 확인
echo -e "\n${YELLOW}5. 컨테이너 상태 확인${NC}"
docker-compose -f docker-compose.prod.yml ps

# 로그 확인
echo -e "\n${YELLOW}6. 초기 로그 확인${NC}"
docker-compose -f docker-compose.prod.yml logs --tail=20

echo -e "\n${GREEN}배포 완료!${NC}"
echo "로그 확인: docker-compose -f docker-compose.prod.yml logs -f"
echo "상태 확인: docker-compose -f docker-compose.prod.yml ps"
echo "중지: docker-compose -f docker-compose.prod.yml down"