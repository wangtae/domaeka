#!/bin/bash

# MariaDB 컨테이너의 IP 주소 확인
echo "MariaDB 컨테이너 IP 주소 확인 중..."
MARIADB_IP=$(docker inspect domaeka-mariadb | grep -i "ipaddress" | grep -E '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | head -1 | awk -F'"' '{print $4}')

if [ -z "$MARIADB_IP" ]; then
    echo "❌ MariaDB 컨테이너를 찾을 수 없습니다."
    echo "다음 명령으로 컨테이너 목록을 확인하세요:"
    echo "  docker ps | grep mariadb"
    exit 1
fi

echo "✅ MariaDB 컨테이너 IP: $MARIADB_IP"

# /etc/hosts 파일에 추가
echo ""
echo "다음 줄을 /etc/hosts 파일에 추가하세요 (sudo 권한 필요):"
echo ""
echo "$MARIADB_IP    domaeka-mariadb"
echo ""
echo "명령어:"
echo "  echo '$MARIADB_IP    domaeka-mariadb' | sudo tee -a /etc/hosts"