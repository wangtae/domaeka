# Docker 모범 사례 - 프로젝트별 Dockerfile 관리

## 왜 프로젝트별 Dockerfile이 필요한가?

### 1. 의존성 격리
- 각 프로젝트가 필요한 패키지만 설치
- 버전 충돌 방지 (PHP 7.4 vs 8.2)
- 불필요한 취약점 감소

### 2. 이미지 최적화
- 작은 이미지 크기
- 빠른 빌드 시간
- 효율적인 리소스 사용

### 3. 유지보수성
- 프로젝트별 독립적인 업데이트
- 명확한 의존성 관리
- 쉬운 문제 해결

## 권장 디렉토리 구조

```
/data/projects/
├── domaeka/
│   ├── .cfg/
│   │   ├── Dockerfile.web      # 도매까 웹 서버용
│   │   └── docker-compose.yml  # 도매까 전체 서비스
│   └── domaeka.live/
│       └── server/
│           └── Dockerfile      # 도매까 봇 서버용
│
├── loa/
│   ├── .cfg/
│   │   ├── Dockerfile         # LOA 프로젝트용
│   │   └── docker-compose.yml # LOA 전체 서비스
│   └── ...
│
└── Dockerfile                 # (삭제 권장) 공통 이미지는 비추천
```

## 마이그레이션 방법

### 1단계: 프로젝트별 Dockerfile 생성
```bash
# 도매까 프로젝트
cp /data/projects/Dockerfile /data/projects/domaeka/.cfg/Dockerfile.web
# 필요한 부분만 남기고 수정

# LOA 프로젝트  
cp /data/projects/Dockerfile /data/projects/loa/.cfg/Dockerfile
# LOA에 맞게 수정
```

### 2단계: docker-compose.yml 수정
```yaml
# 기존
services:
  web:
    image: nginx-php8  # 공통 이미지 사용

# 변경 후
services:
  web:
    build:
      context: .
      dockerfile: .cfg/Dockerfile.web
    image: domaeka-nginx-php8  # 프로젝트별 이미지
```

### 3단계: 이미지 재빌드
```bash
cd /data/projects/domaeka
docker-compose build
docker-compose up -d
```

## 장점

1. **독립성**: 한 프로젝트의 변경이 다른 프로젝트에 영향 없음
2. **보안**: 필요한 패키지만 설치하여 공격 표면 최소화
3. **성능**: 작은 이미지로 빠른 배포와 시작
4. **버전 관리**: 프로젝트와 함께 Dockerfile도 Git으로 관리

## 예외 상황

공통 이미지가 유용한 경우:
- 모든 프로젝트가 정확히 같은 환경 필요
- 중앙 집중식 관리가 필요한 기업 환경
- 이미지 빌드 시간이 매우 긴 경우

하지만 일반적으로는 프로젝트별 Dockerfile이 더 좋은 선택입니다.