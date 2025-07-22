# Docker 기초 개념

## Dockerfile vs docker-compose.yml

### Dockerfile
- **역할**: Docker 이미지를 만들기 위한 설계도
- **위치**: 각 서비스의 소스 코드와 함께 위치
- **내용**: 
  - 기본 이미지 선택 (FROM)
  - 패키지 설치 (RUN)
  - 파일 복사 (COPY)
  - 환경 설정
  - 실행 명령 (CMD)

### docker-compose.yml
- **역할**: 여러 컨테이너를 함께 실행하고 연결하는 설정
- **위치**: 프로젝트 루트
- **내용**:
  - 서비스 정의
  - 네트워크 설정
  - 볼륨 마운트
  - 포트 매핑
  - 서비스 간 의존성

## 도매까 프로젝트 예시

### 1. Python 서버의 Dockerfile (`server/Dockerfile`)
```dockerfile
FROM python:3.12-slim              # 1. 기본 이미지
RUN apt-get install supervisor     # 2. 필요한 프로그램 설치
COPY requirements.txt .            # 3. 파일 복사
RUN pip install -r requirements.txt # 4. Python 패키지 설치
CMD ["supervisord"]                # 5. 컨테이너 실행 시 명령
```

이미지 빌드 과정:
1. Python 3.12가 설치된 가벼운 Linux 시작
2. Supervisor 설치
3. Python 패키지 목록 복사
4. 패키지 설치
5. 실행할 명령 지정

### 2. docker-compose.yml에서 사용
```yaml
domaeka-bot-server:
  build: 
    context: ./domaeka.live/server  # Dockerfile 위치
    dockerfile: Dockerfile          # Dockerfile 이름
  ports:
    - "1490:1490"                  # 포트 연결
  volumes:
    - ./server:/app                # 코드 마운트
```

## 실행 흐름

1. **이미지 빌드** (Dockerfile 사용)
   ```bash
   docker-compose build domaeka-bot-server
   ```
   - Dockerfile의 지시사항대로 이미지 생성
   - Python, Supervisor, 패키지들이 설치된 이미지 완성

2. **컨테이너 실행** (docker-compose.yml 사용)
   ```bash
   docker-compose up -d domaeka-bot-server
   ```
   - 빌드된 이미지로 컨테이너 생성
   - 포트, 볼륨, 네트워크 설정 적용
   - 다른 서비스와 연결

## 왜 두 개가 필요한가?

### Dockerfile만 있다면:
- 이미지는 만들 수 있지만
- 포트, 볼륨, 네트워크 설정을 매번 명령줄에 입력해야 함
- 여러 서비스 연결이 복잡함

### docker-compose.yml만 있다면:
- 기존 이미지만 사용 가능
- 커스텀 설정 불가능
- 필요한 패키지 설치 불가능

## 정리

- **Dockerfile**: "이미지를 어떻게 만들 것인가?"
- **docker-compose.yml**: "컨테이너를 어떻게 실행하고 연결할 것인가?"

두 파일이 함께 작동하여 완전한 Docker 환경을 구성합니다.