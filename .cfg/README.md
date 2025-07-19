# Docker 설정 파일

이 디렉토리는 도매까 프로젝트의 Docker 관련 설정 파일들을 포함합니다.

## 파일 구조

```
.cfg/
├── docker-compose.yml          # 전체 서비스 구성
└── README.md                   # 이 파일
```

## docker-compose.yml

원격 서버의 `/data/projects/domaeka/docker-compose.yml`과 동일한 파일입니다.
버전 관리를 위해 프로젝트 내에 유지합니다.

### 서비스 구성

1. **domaeka-web**: 운영 웹 서버 (포트 8089)
2. **domaeka-dev-web**: 개발 웹 서버 (포트 8090)
3. **domaeka-bot-server**: Python 봇 서버 (포트 1490, 1491)
4. **domaeka-mariadb**: 데이터베이스 서버 (포트 3307)

### 배포 방법

1. 이 파일을 원격 서버의 `/data/projects/domaeka/docker-compose.yml`로 복사
2. 필요한 경우 환경에 맞게 수정 (비밀번호, 포트 등)
3. `docker-compose up -d` 실행

### 주의사항

- `MYSQL_ROOT_PASSWORD`는 실제 운영 환경에서는 환경 변수나 `.env` 파일로 관리하세요
- 외부 네트워크 `docker-network`가 미리 생성되어 있어야 합니다
- 로그 디렉토리가 미리 생성되어 있어야 합니다