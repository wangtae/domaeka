# 개발 환경 경로 설정 가이드

## 표준 경로 사용하기

개발과 운영 환경의 일관성을 위해 동일한 경로를 사용합니다.

### 표준 경로
- **운영 서버**: `/data/projects`
- **개발 PC (WSL)**: `/data/projects` (동일하게 설정)

## WSL에서 설정하기

### 1. 디렉토리 생성 및 권한 설정
```bash
# /data 디렉토리 생성
sudo mkdir -p /data/projects

# 현재 사용자에게 소유권 부여
sudo chown -R $USER:$USER /data/projects

# 권한 확인
ls -la /data/
```

### 2. 기존 프로젝트 이동 (기존 사용자)
```bash
# 기존 프로젝트가 있는 경우
mv ~/projects/* /data/projects/

# 또는 복사 후 확인
cp -r ~/projects/* /data/projects/
```

### 3. 새로운 프로젝트 시작 (신규 사용자)
```bash
# 프로젝트 클론
cd /data/projects
git clone [repository-url] domaeka

# 인프라 디렉토리 생성
mkdir -p /data/infrastructure
```

### 4. 심볼릭 링크 생성 (선택사항)
기존 스크립트나 단축키 호환성을 위해:
```bash
# 홈 디렉토리에 심볼릭 링크 생성
ln -s /data/projects ~/projects
ln -s /data/infrastructure ~/infrastructure

# 확인
ls -la ~/
```

## 환경 변수 설정

### .bashrc 또는 .zshrc에 추가
```bash
# 프로젝트 루트 경로
export PROJECT_ROOT="/data/projects"
export INFRA_ROOT="/data/infrastructure"

# 별칭 설정 (선택사항)
alias cdp='cd $PROJECT_ROOT'
alias cdi='cd $INFRA_ROOT'
alias cdd='cd $PROJECT_ROOT/domaeka'
```

### 적용
```bash
source ~/.bashrc  # 또는 source ~/.zshrc
```

## Docker 볼륨 경로 주의사항

### WSL2에서 최적 성능을 위한 경로
- ✅ **좋음**: `/data/projects` (WSL 파일시스템)
- ❌ **피함**: `/mnt/c/projects` (Windows 파일시스템)

### 이유
- WSL 파일시스템이 Docker 볼륨 마운트 시 훨씬 빠름
- Windows 경로는 성능 저하 발생

## 디렉토리 구조

```
/data/
├── projects/                 # 프로젝트 디렉토리
│   ├── domaeka/
│   │   ├── domaeka.live/   # 운영 코드
│   │   └── domaeka.test/   # 테스트 코드
│   ├── loa/
│   └── other-projects/
│
└── infrastructure/          # 인프라 서비스
    ├── nginx-proxy-manager/
    ├── portainer/
    └── monitoring/
```

## 권한 문제 해결

### 권한 오류 발생 시
```bash
# 특정 디렉토리 권한 수정
sudo chown -R $USER:$USER /data/projects/domaeka

# Docker 소켓 권한 (필요시)
sudo usermod -aG docker $USER
newgrp docker
```

### Git 권한 문제
```bash
# Git safe directory 설정
git config --global --add safe.directory /data/projects/domaeka
```

## 백업 및 이전

### 프로젝트 백업
```bash
# 전체 백업
tar -czf ~/backup-projects-$(date +%Y%m%d).tar.gz -C /data projects

# 복원
tar -xzf ~/backup-projects-*.tar.gz -C /data
```

## 장점 정리

1. **경로 일관성**: 개발/운영 동일 경로
2. **스크립트 호환**: 수정 없이 재사용
3. **Docker 성능**: WSL 네이티브 파일시스템
4. **팀 협업**: 동일한 경로로 문서화 간편

이제 개발 환경도 운영 서버와 동일한 `/data/projects` 경로를 사용합니다!