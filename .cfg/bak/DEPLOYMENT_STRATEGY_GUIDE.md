# 날짜 기반 배포 전략 가이드 (심볼릭 링크 방식)

## 개요

심볼릭 링크를 활용하여 개발 → 테스트 → 운영으로 이어지는 안전한 배포 전략을 구현합니다.
- 개발: `domaeka.dev` → `dev/domaeka.YYMMDD`
- 배포: `domaeka.YYMMDD` (날짜 기반 디렉토리)
- 테스트/운영: 심볼릭 링크로 버전 전환

## 심볼릭 링크란?

심볼릭 링크는 다른 파일이나 디렉토리를 가리키는 특별한 파일입니다. Windows의 바로가기와 유사하지만 더 강력합니다.

### 기본 명령어
```bash
# 심볼릭 링크 생성
ln -s [원본] [링크이름]

# 심볼릭 링크 업데이트 (강제)
ln -sfn [새원본] [링크이름]

# 심볼릭 링크 확인
ls -la | grep "^l"

# 심볼릭 링크가 가리키는 원본 확인
readlink [링크이름]
```

## 디렉토리 구조

### 개발 환경 (개발 PC)
```
/data/projects/domaeka/
├── dev/                        # 개발 버전 관리
│   ├── domaeka.250719/        # 3일 전 버전
│   ├── domaeka.250720/        # 어제 버전
│   └── domaeka.250721/        # 오늘 작업 중
├── domaeka.dev → dev/domaeka.250721     # 현재 개발 버전
├── current → domaeka.dev       # Docker가 바라보는 디렉토리
└── docker-compose.yml
```

### 운영 환경 (서버)
```
/data/projects/domaeka/
├── releases/                   # 릴리즈 버전 관리
│   ├── domaeka.250719/
│   ├── domaeka.250720/
│   └── domaeka.250721/
├── domaeka.test → releases/domaeka.250721  # 테스트 환경
├── domaeka.live → releases/domaeka.250720  # 운영 환경
├── current → domaeka.test      # Docker가 바라보는 디렉토리
└── docker-compose.yml
```

## 심볼릭 링크 설정 방법

### 1. 초기 설정 (개발 환경)

```bash
# 1. 디렉토리 구조 생성
cd /data/projects/domaeka
mkdir -p dev

# 2. 첫 개발 버전 생성
cp -r domaeka.dev dev/domaeka.$(date +%y%m%d)

# 3. 심볼릭 링크 생성
ln -sfn dev/domaeka.$(date +%y%m%d) domaeka.dev
ln -sfn domaeka.dev current

# 4. 확인
ls -la
# lrwxrwxrwx  1 user user   21 Jan 19 10:00 current -> domaeka.dev
# lrwxrwxrwx  1 user user   21 Jan 19 10:00 domaeka.dev -> dev/domaeka.250721
```

### 2. 새 개발 버전 시작

```bash
# 1. 이전 버전 복사
cp -r dev/domaeka.250721 dev/domaeka.250722

# 2. 심볼릭 링크 변경
ln -sfn dev/domaeka.250722 domaeka.dev

# 3. Docker 재시작 (필요시)
docker compose restart
```

### 3. 이전 버전으로 롤백

```bash
# 1. 이전 버전 확인
ls -la dev/

# 2. 심볼릭 링크 변경
ln -sfn dev/domaeka.250720 domaeka.dev

# 3. Docker 재시작
docker compose restart
```

## Docker Compose 설정

### docker-compose.yml (심볼릭 링크 기반)

`/data/projects/domaeka/docker-compose.yml`:
```yaml
version: '3.8'

services:
  domaeka-web:
    build:
      context: ./current/web    # 항상 current 심볼릭 링크 사용
      dockerfile: Dockerfile
    container_name: domaeka-web
    networks:
      - docker-network
    ports:
      - "8089:80"
    volumes:
      - ./current/web:/var/www/html
      - ./logs/nginx:/var/log/nginx
    restart: unless-stopped

  domaeka-bot-server:
    build:
      context: ./current/server
      dockerfile: Dockerfile
    container_name: domaeka-bot-server
    networks:
      - docker-network
    ports:
      - "1490:1490"
      - "1491:1491"
    volumes:
      - ./current/server:/app
      - ./current/web/data:/web/data:ro
    restart: unless-stopped

  domaeka-mariadb:
    image: mariadb:10.3.7
    container_name: domaeka-mariadb
    networks:
      - docker-network
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: domaeka
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./mysql-data:/var/lib/mysql
      - ./current/web/dmk/sql:/docker-entrypoint-initdb.d:ro
    restart: unless-stopped

networks:
  docker-network:
    external: true
```

### 왜 심볼릭 링크 방식이 최고인가?

1. **단순함**: 환경 변수나 override 파일 없이 링크만 변경
2. **투명성**: `ls -la`로 현재 어떤 버전인지 즉시 확인
3. **안전성**: 잘못된 경로 입력 시 Docker가 즉시 오류 표시
4. **일관성**: 개발/운영 모두 동일한 docker-compose.yml 사용

## 실제 작업 예시

### 예시 1: 새로운 기능 개발

```bash
# 1. 현재 상태 확인
cd /data/projects/domaeka
ls -la
# current -> domaeka.dev
# domaeka.dev -> dev/domaeka.250721

# 2. 새 개발 버전 생성
VERSION=$(date +%y%m%d)  # 250722
cp -r dev/domaeka.250721 dev/domaeka.${VERSION}

# 3. 심볼릭 링크 변경
ln -sfn dev/domaeka.${VERSION} domaeka.dev

# 4. 개발 진행
cd domaeka.dev
# ... 코드 수정 ...

# 5. Docker 재시작
cd ..
docker compose restart
```

### 예시 2: 긴급 버그 수정

```bash
# 1. 운영 버전을 기반으로 핫픽스 버전 생성
LIVE_VERSION=$(readlink domaeka.live)  # releases/domaeka.250720
cp -r ${LIVE_VERSION} dev/domaeka.250722-hotfix

# 2. 개발 환경 전환
ln -sfn dev/domaeka.250722-hotfix domaeka.dev

# 3. 버그 수정
# ... 수정 작업 ...

# 4. 테스트
docker compose restart
```

### 예시 3: 버전 간 비교

```bash
# 1. 두 버전 간 차이 확인
diff -r dev/domaeka.250721 dev/domaeka.250722

# 2. 특정 파일만 비교
diff dev/domaeka.250721/server/main.py dev/domaeka.250722/server/main.py

# 3. 변경된 파일 목록만 보기
diff -qr dev/domaeka.250721 dev/domaeka.250722 | grep -v "Only in"
```

## 스크립트 모음

### 1. 개발 환경 관리 스크립트

**dev-manager.sh** (통합 개발 환경 관리):
```bash
#!/bin/bash

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

ACTION=$1
VERSION=$2

function show_status() {
    echo -e "${BLUE}=== 개발 환경 상태 ===${NC}"
    echo "현재 디렉토리: $(pwd)"
    echo ""
    echo -e "${YELLOW}심볼릭 링크:${NC}"
    ls -la | grep "^l" | grep -E "(current|domaeka.dev)"
    echo ""
    echo -e "${YELLOW}개발 버전:${NC}"
    ls -la dev/ 2>/dev/null | grep "^d" | grep domaeka
    echo ""
    echo -e "${YELLOW}Docker 상태:${NC}"
    docker compose ps --format "table {{.Name}}\t{{.Status}}"
}

function new_version() {
    VERSION=${VERSION:-$(date +%y%m%d)}
    DEV_DIR="dev/domaeka.${VERSION}"
    
    echo -e "${GREEN}새 개발 버전 생성: ${VERSION}${NC}"
    
    # dev 디렉토리 확인
    mkdir -p dev
    
    # 이전 버전에서 복사
    if [ -L "domaeka.dev" ]; then
        PREV=$(readlink domaeka.dev)
        echo "이전 버전 복사: $PREV → $DEV_DIR"
        cp -r "$PREV" "$DEV_DIR"
    else
        echo "새 프로젝트 생성"
        mkdir -p "$DEV_DIR"/{server,web}
    fi
    
    # 심볼릭 링크 업데이트
    ln -sfn "$DEV_DIR" domaeka.dev
    echo -e "${GREEN}✓ 완료${NC}"
}

function switch_version() {
    if [ -z "$VERSION" ]; then
        echo -e "${RED}버전을 지정하세요${NC}"
        echo "사용법: $0 switch <VERSION>"
        echo "예: $0 switch 250721"
        exit 1
    fi
    
    DEV_DIR="dev/domaeka.${VERSION}"
    
    if [ ! -d "$DEV_DIR" ]; then
        echo -e "${RED}오류: $DEV_DIR 가 존재하지 않습니다${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}버전 전환: ${VERSION}${NC}"
    ln -sfn "$DEV_DIR" domaeka.dev
    
    # Docker 재시작 여부 확인
    read -p "Docker를 재시작하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker compose restart
    fi
    
    echo -e "${GREEN}✓ 전환 완료${NC}"
}

function list_versions() {
    echo -e "${BLUE}=== 개발 버전 목록 ===${NC}"
    ls -la dev/ | grep "^d" | grep domaeka | awk '{print $9}' | sed 's/domaeka\.//'
}

function cleanup_old() {
    echo -e "${YELLOW}7일 이상 된 개발 버전 정리${NC}"
    find dev/ -maxdepth 1 -type d -name "domaeka.*" -mtime +7 -print
    
    read -p "위 버전들을 삭제하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        find dev/ -maxdepth 1 -type d -name "domaeka.*" -mtime +7 -exec rm -rf {} \;
        echo -e "${GREEN}✓ 정리 완료${NC}"
    fi
}

case $ACTION in
    status)
        show_status
        ;;
    new)
        new_version
        ;;
    switch)
        switch_version
        ;;
    list)
        list_versions
        ;;
    cleanup)
        cleanup_old
        ;;
    *)
        echo "사용법: $0 {status|new|switch|list|cleanup}"
        echo ""
        echo "Commands:"
        echo "  status   - 현재 상태 확인"
        echo "  new      - 새 개발 버전 생성"
        echo "  switch   - 다른 버전으로 전환"
        echo "  list     - 모든 개발 버전 목록"
        echo "  cleanup  - 오래된 버전 정리"
        exit 1
        ;;
esac
```

### 2. 배포 스크립트 (운영 서버용)

**deploy-manager.sh**:
```bash
#!/bin/bash

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

ACTION=$1

function deploy_test() {
    VERSION=$(date +%y%m%d)
    RELEASE_DIR="releases/domaeka.${VERSION}"
    
    echo -e "${GREEN}=== 테스트 배포 ===${NC}"
    echo "버전: ${VERSION}"
    
    # 테스트 환경으로 전환
    ln -sfn "$RELEASE_DIR" domaeka.test
    ln -sfn domaeka.test current
    
    # Docker 재시작
    docker compose down
    docker compose up -d --build
    
    echo -e "${GREEN}✓ 테스트 환경 배포 완료${NC}"
}

function deploy_live() {
    echo -e "${YELLOW}운영 배포 시작${NC}"
    
    # 현재 테스트 버전 확인
    TEST_VERSION=$(readlink domaeka.test)
    echo "배포할 버전: $TEST_VERSION"
    
    read -p "운영 환경에 배포하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
    
    # 이전 버전 백업
    PREV_LIVE=$(readlink domaeka.live)
    ln -sfn "$PREV_LIVE" domaeka.prev
    
    # 운영 배포
    ln -sfn "$TEST_VERSION" domaeka.live
    
    echo -e "${GREEN}✓ 운영 배포 완료${NC}"
    echo "이전 버전: $PREV_LIVE"
    echo "현재 버전: $TEST_VERSION"
}

function rollback() {
    echo -e "${RED}=== 롤백 ===${NC}"
    
    CURRENT=$(readlink domaeka.live)
    PREVIOUS=$(readlink domaeka.prev)
    
    echo "현재: $CURRENT"
    echo "롤백 대상: $PREVIOUS"
    
    read -p "롤백하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
    
    ln -sfn "$PREVIOUS" domaeka.live
    ln -sfn domaeka.live current
    docker compose restart
    
    echo -e "${GREEN}✓ 롤백 완료${NC}"
}

case $ACTION in
    test)
        deploy_test
        ;;
    live)
        deploy_live
        ;;
    rollback)
        rollback
        ;;
    *)
        echo "사용법: $0 {test|live|rollback}"
        ;;
esac
```

## 심볼릭 링크 문제 해결

### 링크가 깨진 경우
```bash
# 깨진 링크 확인
find . -type l -! -exec test -e {} \; -print

# 깨진 링크 삭제
find . -type l -! -exec test -e {} \; -delete
```

### 링크 순환 참조
```bash
# 순환 참조 방지
rm -f current
ln -sfn domaeka.dev current  # 절대 current → current 하지 말 것!
```

### Windows WSL에서 심볼릭 링크
```bash
# WSL에서는 일반적으로 문제없이 작동
ln -sfn source target

# 만약 권한 문제 발생 시
sudo ln -sfn source target
```

## 자주 묻는 질문 (FAQ)

### Q1: 왜 환경 변수 대신 심볼릭 링크를 사용하나요?
**A**: 심볼릭 링크는 시각적으로 명확하고, 설정 파일 수정 없이 버전을 전환할 수 있습니다. `ls -la`만으로 현재 버전을 확인할 수 있어 실수를 방지합니다.

### Q2: 심볼릭 링크가 Git에 포함되나요?
**A**: 아니요. `.gitignore`에 다음을 추가하세요:
```
current
domaeka.dev
domaeka.test
domaeka.live
domaeka.prev
```

### Q3: Docker가 심볼릭 링크를 인식하지 못하면?
**A**: Docker는 심볼릭 링크를 잘 인식합니다. 단, 절대 경로가 아닌 상대 경로를 사용하세요:
```yaml
# 좋음
context: ./current/web

# 피함
context: /data/projects/domaeka/current/web
```

### Q4: 여러 프로젝트를 동시에 개발하려면?
**A**: 브랜치별 버전을 만들어 사용하세요:
```bash
# 기능 A 개발
cp -r dev/domaeka.250721 dev/domaeka.250721-feature-a
ln -sfn dev/domaeka.250721-feature-a domaeka.dev

# 기능 B로 전환
ln -sfn dev/domaeka.250721-feature-b domaeka.dev
```

## 정식 배포 전략 (GitHub 기반)

### 언제 사용하나요?

- **운영 환경**: 실제 사용자가 접속하는 서비스
- **중요한 시스템**: 금융, 의료, 이커머스 등
- **팀 협업**: 여러 개발자가 함께 작업
- **버전 관리**: 명확한 릴리즈 히스토리 필요

### 개발 → 운영 서버 배포 프로세스

#### 1. 개발 환경에서 준비

```bash
# 1. 개발 완료 후 Git 커밋
cd /data/projects/domaeka/domaeka.dev
git add .
git commit -m "버전 250720 배포 준비"

# 2. 태그 생성 (권장)
git tag -a v250720 -m "Release version 250720"
git push origin main --tags

# 3. 또는 배포 브랜치 생성
git checkout -b release/250720
git push origin release/250720
```

#### 2. 운영 서버에서 배포

**deploy-from-github.sh** (운영 서버용):
```bash
#!/bin/bash

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# 설정
GITHUB_REPO="https://github.com/username/domaeka.git"
VERSION=${1:-$(date +%y%m%d)}
TAG_OR_BRANCH=${2:-"v${VERSION}"}  # 태그 또는 브랜치
RELEASE_DIR="releases/domaeka.${VERSION}"

echo -e "${BLUE}=== GitHub 기반 배포 ===${NC}"
echo "버전: ${VERSION}"
echo "태그/브랜치: ${TAG_OR_BRANCH}"

# 1. releases 디렉토리 생성
mkdir -p releases

# 2. 기존 디렉토리 확인
if [ -d "$RELEASE_DIR" ]; then
    echo -e "${RED}경고: ${RELEASE_DIR}가 이미 존재합니다.${NC}"
    read -p "덮어쓰시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
    rm -rf "$RELEASE_DIR"
fi

# 3. GitHub에서 클론
echo -e "${YELLOW}GitHub에서 다운로드 중...${NC}"
git clone --depth 1 --branch "$TAG_OR_BRANCH" "$GITHUB_REPO" "$RELEASE_DIR"

# 4. 불필요한 파일 제거
echo -e "${YELLOW}불필요한 파일 정리 중...${NC}"
cd "$RELEASE_DIR"
rm -rf .git .github .gitignore README.md docs/

# 5. 환경 설정 파일 복사
echo -e "${YELLOW}환경 설정 적용 중...${NC}"
cd ../..
if [ -f "configs/production/.env" ]; then
    cp configs/production/.env "$RELEASE_DIR/web/"
fi
if [ -f "configs/production/dbconfig.php" ]; then
    cp configs/production/dbconfig.php "$RELEASE_DIR/web/data/"
fi

# 6. 디렉토리 권한 설정
echo -e "${YELLOW}권한 설정 중...${NC}"
chmod -R 755 "$RELEASE_DIR"
if [ -f "$RELEASE_DIR/web/perms.sh" ]; then
    cd "$RELEASE_DIR/web" && ./perms.sh
    cd ../..
fi

# 7. 테스트 환경으로 설정
echo -e "${YELLOW}테스트 환경 설정 중...${NC}"
ln -sfn "$RELEASE_DIR" domaeka.test
ln -sfn domaeka.test current

# 8. Docker 재시작
echo -e "${YELLOW}Docker 재시작 중...${NC}"
docker compose down
docker compose up -d --build

# 9. 상태 확인
echo -e "${GREEN}=== 배포 완료 ===${NC}"
echo "버전: $RELEASE_DIR"
echo "상태: 테스트 환경"
echo ""
docker compose ps
```

#### 3. 환경별 설정 관리

**디렉토리 구조**:
```
/data/projects/domaeka/
├── configs/                    # 환경별 설정 (Git 제외)
│   ├── production/
│   │   ├── .env
│   │   ├── dbconfig.php
│   │   └── server-config.json
│   └── test/
│       ├── .env
│       └── dbconfig.php
├── releases/
│   └── domaeka.250720/
└── docker-compose.yml
```

**configs/.gitkeep** (설정 디렉토리 구조 유지):
```
# 이 디렉토리는 환경별 설정 파일을 보관합니다
# 실제 설정 파일은 Git에 포함하지 않습니다
```

### 장점과 단점

**장점:**
- ✅ 명확한 버전 관리
- ✅ 팀원 간 코드 동기화
- ✅ 롤백 시 정확한 버전 추적
- ✅ CI/CD 자동화 가능

**단점:**
- ❌ 배포 시간이 오래 걸림
- ❌ 여러 단계 거쳐야 함
- ❌ 긴급 수정 시 번거로움

## 빠른 배포 전략 (속도 우선)

### 언제 사용하나요?

- **내부 도구**: 관리자 페이지, 대시보드
- **개발/테스트**: 빠른 피드백이 필요한 경우
- **긴급 수정**: 즉시 반영해야 하는 상황
- **소규모 프로젝트**: 사용자가 제한적인 서비스

### 방법 1: 개발 PC에서 SFTP 직접 업로드 (권장)

#### 1. 빠른 배포 스크립트

**quick-deploy.sh** (개발 PC용):
```bash
#!/bin/bash

# 색상 정의
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# 설정
SERVER="user@production-server.com"
LOCAL_DIR="domaeka.dev"
REMOTE_LIVE="/data/projects/domaeka/domaeka.live"
BACKUP_DIR="/data/projects/domaeka/backups"

echo -e "${YELLOW}=== 빠른 배포 시작 ===${NC}"

# 1. 서버에서 현재 운영 버전 백업
echo -e "${YELLOW}현재 버전 백업 중...${NC}"
ssh "$SERVER" << EOF
# 백업 디렉토리 생성
mkdir -p ${BACKUP_DIR}

# 현재 live가 심볼릭 링크인 경우
if [ -L "${REMOTE_LIVE}" ]; then
    CURRENT=$(readlink ${REMOTE_LIVE})
    echo "심볼릭 링크 백업: \$CURRENT"
    ln -sfn "\$CURRENT" ${BACKUP_DIR}/backup-$(date +%y%m%d-%H%M%S)
else
    # 실제 디렉토리인 경우 복사
    echo "디렉토리 백업 중..."
    cp -r ${REMOTE_LIVE} ${BACKUP_DIR}/domaeka.$(date +%y%m%d-%H%M%S)
fi
EOF

# 2. 새 파일 직접 업로드
echo -e "${YELLOW}파일 업로드 중...${NC}"
rsync -avz --delete \
  --exclude='.git' \
  --exclude='*.log' \
  --exclude='node_modules' \
  --exclude='__pycache__' \
  --exclude='web/data/dbconfig.php' \
  --exclude='web/.env' \
  --exclude='web/data/session/*' \
  --exclude='web/data/cache/*' \
  "${LOCAL_DIR}/" \
  "${SERVER}:${REMOTE_LIVE}/"

# 3. 서버에서 설정 및 권한 처리
echo -e "${YELLOW}서버 설정 중...${NC}"
ssh "$SERVER" << EOF
cd ${REMOTE_LIVE}

# 환경 설정 확인 (없으면 복사)
if [ ! -f "web/.env" ] && [ -f "/data/projects/domaeka/configs/production/.env" ]; then
    cp /data/projects/domaeka/configs/production/.env web/
fi

# 권한 설정
if [ -f "web/perms.sh" ]; then
    cd web && ./perms.sh
    cd ..
fi

# Docker 재시작
cd /data/projects/domaeka
docker compose restart
EOF

echo -e "${GREEN}✓ 배포 완료!${NC}"
echo "URL: https://domaeka.com"
```

#### 2. 장점과 주의사항

**장점:**
- ✅ 1-2분 내 배포 완료
- ✅ Git 커밋 없이 즉시 반영
- ✅ 수정 → 확인 사이클이 빠름
- ✅ rsync로 변경된 파일만 전송

**주의사항:**
- ⚠️ 항상 배포 전 자동 백업
- ⚠️ 설정 파일은 덮어쓰지 않음
- ⚠️ 세션 파일 제외로 로그아웃 방지
- ⚠️ Git과 운영 서버 코드가 달라질 수 있음

### 방법 2: 서버에서 직접 수정 (긴급 시)

```bash
# 1. 서버 접속
ssh production-server

# 2. 현재 버전 백업
cp -r domaeka.live backups/domaeka.$(date +%y%m%d-%H%M%S)

# 3. 직접 수정
vim domaeka.live/web/파일명.php

# 4. 서비스 재시작 (필요시)
docker compose restart domaeka-web
```

**⚠️ 주의**: 서버 직접 수정은 최후의 수단입니다!

#### 롤백 옵션 1: 이전 백업으로 복구
```bash
# 백업 목록 확인
ls -lt /data/projects/domaeka/backups/

# 심볼릭 링크로 즉시 전환
ln -sfn backups/domaeka.250720-1430 domaeka.live
docker compose restart
```

#### 롤백 옵션 2: 안정 버전으로 복구
```bash
# releases 폴더의 안정 버전으로 전환
ln -sfn releases/domaeka.250719 domaeka.live
docker compose restart
```

#### 최적의 디렉토리 구조

```bash
/data/projects/domaeka/
├── releases/               # GitHub 기반 정식 릴리즈
│   ├── domaeka.250718/    # v2.1.0 (태그)
│   ├── domaeka.250719/    # v2.1.1 (태그)
│   └── domaeka.250720/    # v2.2.0 (태그)
├── domaeka.live/          # 현재 운영 (SFTP 직접)
├── domaeka.stable → releases/domaeka.250720  # 안정 버전
├── backups/               # 자동 백업 (시간별)
│   ├── domaeka.250721-1030/
│   ├── domaeka.250721-1430/
│   └── domaeka.250721-1830/
└── configs/               # 환경별 설정
    └── production/
        ├── .env
        └── dbconfig.php
```

#### 운영 프로세스

1. **평상시**: SFTP로 `domaeka.live`에 직접 배포
2. **주 1회**: 안정된 `live`를 GitHub에 커밋 & 태그
3. **월 1회**: GitHub에서 `releases`로 정식 릴리즈
4. **문제 시**: `domaeka.stable`로 즉시 롤백

### Git 동기화 방법

빠른 배포 후 주기적으로 Git과 동기화:

```bash
# 1. 운영 서버 코드를 로컬로 다운로드
rsync -avz --exclude='.env' --exclude='dbconfig.php' \
  server:/data/projects/domaeka/domaeka.live/ \
  ./sync-from-live/

# 2. Git에 커밋
cd sync-from-live
git add .
git commit -m "운영 서버 동기화: $(date +%Y-%m-%d)"
git tag -a "v$(date +%y%m%d)" -m "Production sync"
git push origin main --tags
```

## 배포 전략 비교표

| 항목 | GitHub 정식 배포 | SFTP 빠른 배포 |
|------|----------------|---------------|
| **배포 시간** | 5-10분 | 1-2분 |
| **버전 관리** | Git 태그로 명확 | 수동 관리 필요 |
| **팀 협업** | 우수 | 주의 필요 |
| **롤백** | 태그로 정확히 | 백업 폴더로 |
| **자동화** | CI/CD 가능 | 스크립트로 |
| **적합한 상황** | 운영 환경 | 개발/내부 도구 |

## 프로젝트별 권장 전략

| 프로젝트 유형 | 권장 전략 | 배포 주기 |
|-------------|----------|----------|
| **금융/의료** | GitHub only | 월 1-2회 |
| **이커머스** | GitHub + 테스트 | 주 1-2회 |
| **관리자 도구** | SFTP + 주간 Git 동기화 | 수시 |
| **내부 대시보드** | SFTP 직접 | 수시 |
| **프로토타입** | SFTP 직접 | 수시 |

## 실전 팁

### 1. 자동 백업 설정 (필수)
```bash
# crontab -e
*/30 * * * * /data/projects/domaeka/auto-backup.sh  # 30분마다
0 0 * * * /data/projects/domaeka/daily-release.sh   # 매일 자정
```

### 2. 배포 전 체크리스트
- [ ] 개발 환경에서 테스트 완료
- [ ] DB 마이그레이션 확인
- [ ] 설정 파일 변경사항 확인
- [ ] 백업 스크립트 동작 확인

### 3. 긴급 상황 대응
```bash
# 30초 내 롤백
ln -sfn domaeka.stable domaeka.live && docker compose restart

# 특정 시간 백업으로 복구
ln -sfn backups/domaeka.250721-1430 domaeka.live && docker compose restart
```

## 분산 시스템 배포 전략 (Python 서버)

### 문제 상황

Python 서버 프로그램이 여러 원격 서버에서 실행되는 경우:
- 각 서버에 일일이 접속하여 배포 → 시간 낭비
- 버전 불일치 가능성 → 오류 발생
- 수동 작업 → 실수 가능성

### 해결 방법 1: Ansible (권장)

#### Ansible 설치 및 설정

**1. 개발 PC에 Ansible 설치**:
```bash
# Ubuntu/WSL
sudo apt update
sudo apt install ansible

# 또는 pip로 설치
pip install ansible
```

**2. 인벤토리 파일 생성**:
`/data/projects/domaeka/ansible/inventory.ini`:
```ini
[bot-servers]
server1 ansible_host=192.168.1.10 ansible_user=ubuntu
server2 ansible_host=192.168.1.11 ansible_user=ubuntu
server3 ansible_host=192.168.1.12 ansible_user=ubuntu

[bot-servers:vars]
ansible_python_interpreter=/usr/bin/python3
project_path=/data/projects/domaeka
```

**3. Ansible Playbook 작성**:
`/data/projects/domaeka/ansible/deploy-bot.yml`:
```yaml
---
- name: Python 봇 서버 배포
  hosts: bot-servers
  become: yes
  
  vars:
    bot_version: "{{ version | default('main') }}"
    github_repo: https://github.com/username/domaeka.git
    
  tasks:
    - name: 백업 디렉토리 생성
      file:
        path: "{{ project_path }}/backups"
        state: directory
        
    - name: 현재 버전 백업
      shell: |
        if [ -d "{{ project_path }}/bot-server" ]; then
          cp -r {{ project_path }}/bot-server \
                {{ project_path }}/backups/bot-$(date +%Y%m%d-%H%M%S)
        fi
        
    - name: GitHub에서 최신 코드 가져오기
      git:
        repo: "{{ github_repo }}"
        dest: "{{ project_path }}/bot-server-new"
        version: "{{ bot_version }}"
        
    - name: Python 의존성 설치
      pip:
        requirements: "{{ project_path }}/bot-server-new/server/requirements.txt"
        virtualenv: "{{ project_path }}/bot-server-new/venv"
        
    - name: 설정 파일 복사
      copy:
        src: "{{ project_path }}/configs/{{ inventory_hostname }}/config.json"
        dest: "{{ project_path }}/bot-server-new/server/config/"
        
    - name: 서비스 중지
      systemd:
        name: domaeka-bot
        state: stopped
      ignore_errors: yes
      
    - name: 새 버전으로 교체
      shell: |
        rm -rf {{ project_path }}/bot-server-old
        if [ -d "{{ project_path }}/bot-server" ]; then
          mv {{ project_path }}/bot-server {{ project_path }}/bot-server-old
        fi
        mv {{ project_path }}/bot-server-new {{ project_path }}/bot-server
        
    - name: 서비스 시작
      systemd:
        name: domaeka-bot
        state: started
        daemon_reload: yes
        
    - name: 헬스 체크
      uri:
        url: "http://localhost:1490/health"
        status_code: 200
      retries: 5
      delay: 3
```

**4. 배포 실행**:
```bash
# 모든 서버에 배포
ansible-playbook -i inventory.ini deploy-bot.yml

# 특정 버전 배포
ansible-playbook -i inventory.ini deploy-bot.yml -e "version=v2.1.0"

# 특정 서버만 배포
ansible-playbook -i inventory.ini deploy-bot.yml --limit server1
```

### 해결 방법 2: 자체 배포 스크립트

**multi-deploy.sh** (개발 PC용):
```bash
#!/bin/bash

# 서버 목록
SERVERS=(
    "ubuntu@192.168.1.10"
    "ubuntu@192.168.1.11"
    "ubuntu@192.168.1.12"
)

VERSION=${1:-main}
PROJECT="domaeka-bot"

echo "=== 다중 서버 배포 시작 ==="
echo "버전: $VERSION"
echo "대상 서버: ${#SERVERS[@]}개"

# 각 서버에 병렬로 배포
for server in "${SERVERS[@]}"; do
    echo "배포 중: $server"
    (
        ssh "$server" << EOF
        # 백업
        cd /data/projects/domaeka
        cp -r bot-server backups/bot-\$(date +%Y%m%d-%H%M%S)
        
        # GitHub에서 다운로드
        git clone --depth 1 --branch $VERSION \
            https://github.com/username/domaeka.git bot-temp
        
        # Python 환경 설정
        cd bot-temp/server
        python3 -m venv venv
        source venv/bin/activate
        pip install -r requirements.txt
        
        # 설정 파일 복사
        cp /data/projects/domaeka/configs/config.json config/
        
        # 서비스 재시작
        sudo systemctl stop domaeka-bot
        cd /data/projects/domaeka
        rm -rf bot-server
        mv bot-temp/server bot-server
        sudo systemctl start domaeka-bot
        
        # 상태 확인
        sleep 3
        curl -s http://localhost:1490/health || echo "헬스체크 실패"
EOF
    ) &
done

# 모든 배포 완료 대기
wait

echo "=== 배포 완료 ==="
```

### 해결 방법 3: Docker + 중앙 레지스트리

**1. Docker 이미지 빌드 및 푸시**:
```bash
# 빌드
docker build -t myregistry.com/domaeka-bot:v2.1.0 ./server

# 푸시
docker push myregistry.com/domaeka-bot:v2.1.0
```

**2. 각 서버에서 실행**:
```bash
# docker-update.sh
#!/bin/bash
docker pull myregistry.com/domaeka-bot:latest
docker stop domaeka-bot
docker run -d --name domaeka-bot \
  -v /data/configs:/app/config \
  -e DB_HOST=main-server.com \
  myregistry.com/domaeka-bot:latest
```

### 해결 방법 4: Pull 기반 자동 업데이트 (보조 방식)

앞의 Push 방식(개발자가 배포 명령 실행)과 달리, 각 서버가 **스스로 업데이트를 확인**하는 방식입니다.

**장점:**
- ✅ 서버 추가/제거 시 중앙 설정 변경 불필요
- ✅ 서버가 일시적으로 다운되어도 복구 후 자동 업데이트
- ✅ 네트워크 문제로 배포 실패 시 자동 재시도

**단점:**
- ❌ 즉시 배포 불가 (주기적 체크)
- ❌ 각 서버에 스크립트 설치 필요
- ❌ 동시 배포 보장 어려움

#### 구현 예시

**1. 자동 업데이트 스크립트**
`/data/projects/domaeka/auto-updater/update-checker.py`:
```python
#!/usr/bin/env python3
import os
import subprocess
import requests
import time
import json
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

CONFIG_FILE = "/data/projects/domaeka/update-config.json"
CHECK_INTERVAL = 300  # 5분

def load_config():
    """설정 파일 로드"""
    with open(CONFIG_FILE) as f:
        return json.load(f)

def check_version():
    """GitHub API로 최신 버전 확인"""
    config = load_config()
    
    # 태그 기반 버전 확인
    url = f"https://api.github.com/repos/{config['repo']}/releases/latest"
    response = requests.get(url, timeout=30)
    response.raise_for_status()
    
    return response.json()['tag_name']

def get_current_version():
    """현재 실행 중인 버전"""
    try:
        with open("/data/projects/domaeka/bot-server/version.txt") as f:
            return f.read().strip()
    except:
        return "unknown"

def update_and_restart():
    """업데이트 및 재시작"""
    logger.info("업데이트 시작...")
    
    # 로컬 배포 스크립트 실행
    result = subprocess.run(
        ["/data/projects/domaeka/deploy-local.sh"],
        capture_output=True,
        text=True
    )
    
    if result.returncode == 0:
        logger.info("업데이트 성공!")
    else:
        logger.error(f"업데이트 실패: {result.stderr}")

def main():
    logger.info("자동 업데이트 서비스 시작")
    
    while True:
        try:
            latest = check_version()
            current = get_current_version()
            
            if latest != current:
                logger.info(f"새 버전 발견: {current} → {latest}")
                update_and_restart()
                
                # 업데이트 후 버전 기록
                with open("/data/projects/domaeka/bot-server/version.txt", "w") as f:
                    f.write(latest)
            else:
                logger.debug(f"현재 최신 버전: {current}")
            
        except Exception as e:
            logger.error(f"오류 발생: {e}")
            
        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
```

**2. 로컬 배포 스크립트**
`/data/projects/domaeka/deploy-local.sh`:
```bash
#!/bin/bash

# 최신 코드 가져오기
cd /data/projects/domaeka
git pull origin main

# Python 의존성 업데이트
cd bot-server
source venv/bin/activate
pip install -r requirements.txt

# 서비스 재시작
sudo systemctl restart domaeka-bot

# 헬스체크
sleep 5
curl -s http://localhost:1490/health || exit 1
```

**3. Systemd 서비스 등록**
`/etc/systemd/system/domaeka-updater.service`:
```ini
[Unit]
Description=Domaeka Auto Updater
After=network.target

[Service]
Type=simple
User=ubuntu
WorkingDirectory=/data/projects/domaeka
ExecStart=/usr/bin/python3 /data/projects/domaeka/auto-updater/update-checker.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 배포 방식 조합 (하이브리드)

실제로는 여러 방식을 조합해서 사용하는 것이 가장 효과적입니다:

```
┌─────────────────────────────────────────────┐
│              배포 전략 조합                  │
├─────────────────────────────────────────────┤
│                                             │
│  1. 정기 배포: Ansible (주 1회)             │
│     - 안정적인 버전 배포                    │
│     - 모든 서버 동시 업데이트               │
│                                             │
│  2. 긴급 패치: 다중 SSH 스크립트            │
│     - 버그 수정 즉시 배포                   │
│     - 특정 서버만 선택 배포                 │
│                                             │
│  3. 자동 업데이트: Pull 방식                │
│     - 새로 추가된 서버 자동 동기화          │
│     - 네트워크 장애 시 자동 복구            │
│                                             │
└─────────────────────────────────────────────┘
```

### 실제 운영 예시

**평상시 운영:**
1. 개발 완료 → Git 태그 생성 (v2.1.0)
2. Ansible로 전체 서버 배포
3. 자동 업데이터가 지속적으로 버전 확인

**서버 추가 시:**
1. 새 서버에 자동 업데이터 설치
2. 자동으로 최신 버전 다운로드 및 실행
3. Ansible 인벤토리에 추가

**긴급 수정 시:**
1. 핫픽스 브랜치에서 수정
2. 다중 SSH 스크립트로 즉시 배포
3. 나중에 정식 태그 생성

### 권장 아키텍처

```
┌─────────────────┐     ┌─────────────────┐
│   개발 PC       │     │  GitHub/GitLab  │
│                 │────▶│   Repository    │
│  Ansible/Script │     └────────┬────────┘
└────────┬────────┘              │
         │                       │
         ▼                       ▼
    ┌────────────────────────────────────┐
    │          배포 대상 서버들           │
    ├─────────────┬─────────────┬────────┤
    │  Server 1   │  Server 2   │ Server N│
    │ Bot Process │ Bot Process │Bot Proc │
    └──────┬──────┴──────┬──────┴────┬───┘
           │             │           │
           └─────────────┴───────────┘
                        │
                        ▼
               ┌─────────────────┐
               │   중앙 DB 서버   │
               │  (운영 서버)     │
               └─────────────────┘
```

### 실전 팁

1. **서버 그룹 관리**:
   - 운영/개발/테스트 그룹 분리
   - 단계적 배포 (카나리 배포)

2. **모니터링**:
   - 각 서버 헬스체크 엔드포인트
   - 중앙 모니터링 시스템 구축

3. **설정 관리**:
   - 서버별 설정 파일 분리
   - 환경 변수로 DB 접속 정보 관리

## 마무리

**핵심은 프로젝트 성격에 맞는 전략 선택**입니다:

1. **단일 서버** → 심볼릭 링크 + GitHub/SFTP
2. **분산 서버** → Ansible 또는 자동화 스크립트
3. **대규모** → Docker + Kubernetes

모든 경우에 **자동화**와 **모니터링**이 핵심입니다!