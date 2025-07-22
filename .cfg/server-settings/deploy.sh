#!/bin/bash

# Domaeka 서버 설정 배포 스크립트
# 사용법: ./deploy.sh [대상경로]

TARGET_DIR=${1:-/data/projects/domaeka}
SOURCE_DIR="$(dirname "$0")/domaeka"
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)

echo "=== Domaeka 서버 설정 배포 ==="
echo "소스: $SOURCE_DIR"
echo "대상: $TARGET_DIR"
echo "백업 시간: $BACKUP_DATE"
echo ""

# 대상 디렉토리 확인
if [ ! -d "$TARGET_DIR" ]; then
    echo "오류: 대상 디렉토리가 존재하지 않습니다: $TARGET_DIR"
    exit 1
fi

# 확인
read -p "계속하시겠습니까? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

# 백업 함수
backup_if_exists() {
    local file="$1"
    if [ -f "$file" ]; then
        local backup_file="${file}.${BACKUP_DATE}"
        echo "백업: $file → $backup_file"
        cp -p "$file" "$backup_file"
    fi
}

# 파일 복사 함수
deploy_file() {
    local source="$1"
    local target="$2"
    
    # 대상 디렉토리가 없으면 생성
    local target_dir=$(dirname "$target")
    if [ ! -d "$target_dir" ]; then
        echo "디렉토리 생성: $target_dir"
        mkdir -p "$target_dir"
    fi
    
    # 기존 파일 백업
    backup_if_exists "$target"
    
    # 파일 복사
    echo "복사: $source → $target"
    cp -v "$source" "$target"
}

echo ""
echo "=== 파일 배포 시작 ==="

# docker-compose.yml 배포
deploy_file "$SOURCE_DIR/docker-compose.yml" "$TARGET_DIR/docker-compose.yml"

# Dockerfile 배포
deploy_file "$SOURCE_DIR/domaeka.live/Dockerfile.python-server" "$TARGET_DIR/domaeka.live/Dockerfile.python-server"

# supervisord.conf 배포
deploy_file "$SOURCE_DIR/domaeka.live/supervisord.conf" "$TARGET_DIR/domaeka.live/supervisord.conf"

# .env 파일 처리
if [ ! -f "$TARGET_DIR/.env" ]; then
    echo ""
    echo ".env 파일 생성..."
    cp -v "$SOURCE_DIR/.env.example" "$TARGET_DIR/.env"
    echo "⚠️  주의: .env 파일을 확인하고 필요에 따라 수정하세요!"
else
    echo ""
    echo "✓ .env 파일이 이미 존재합니다. (유지)"
    echo "  새로운 .env 예시는 다음 위치에서 확인하세요:"
    echo "  $SOURCE_DIR/.env.example"
fi

echo ""
echo "=== 백업 파일 목록 ==="
find "$TARGET_DIR" -name "*.$BACKUP_DATE" -type f 2>/dev/null | while read backup_file; do
    echo "  - $backup_file"
done

echo ""
echo "=== 배포 완료 ==="
echo ""
echo "다음 단계:"
echo "1. 설정 확인:"
echo "   cd $TARGET_DIR"
echo "   docker-compose config"
echo ""
echo "2. 이미지 빌드 (첫 배포 시):"
echo "   docker-compose build domaeka-server-test-01"
echo ""
echo "3. 서비스 시작:"
echo "   docker-compose up -d"
echo ""
echo "문제 발생 시 복구:"
echo "  백업 파일들은 .$BACKUP_DATE 확장자로 저장되어 있습니다."
echo "  예: mv docker-compose.yml.$BACKUP_DATE docker-compose.yml"