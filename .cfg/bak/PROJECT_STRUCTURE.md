# 도매까 프로젝트 Docker 파일 구조

## 권장 파일 배치

```
/data/projects/domaeka/                    # 프로젝트 루트
├── docker-compose.yml                     # 메인 설정 (여기서 실행)
├── .env                                   # 환경 변수
│
├── domaeka.live/
│   ├── server/
│   │   ├── Dockerfile                     # Python 서버용
│   │   ├── supervisord.conf               # Python 프로세스 관리
│   │   ├── main.py
│   │   └── requirements.txt
│   │
│   └── web/
│       ├── Dockerfile                     # PHP 웹 서버용
│       ├── index.php
│       └── data/                          # 업로드 파일 등
│
├── conf/                                  # 공통 설정
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   └── php.ini
│   └── mysql/
│       └── my.cnf
│
├── logs/                                  # 로그 디렉토리
│   ├── nginx/
│   ├── php/
│   ├── python/
│   └── mysql/
│
└── mysql-data/                            # DB 데이터
```

## docker-compose.yml 위치
- **위치**: `/data/projects/domaeka/docker-compose.yml`
- **이유**: 프로젝트 전체를 관리하는 최상위 위치

## Dockerfile 위치
1. **웹 서버**: `/data/projects/domaeka/domaeka.live/web/Dockerfile`
   - web 디렉토리와 함께 관리
   - web 전용 설정 포함

2. **Python 서버**: `/data/projects/domaeka/domaeka.live/server/Dockerfile`
   - server 디렉토리와 함께 관리
   - Python 전용 설정 포함

## 실행 방법
```bash
cd /data/projects/domaeka
docker-compose up -d
```

## 장점
1. **명확한 구조**: 각 서비스의 Dockerfile이 해당 코드와 함께
2. **Git 관리 용이**: 각 서브프로젝트가 독립적으로 버전 관리
3. **배포 간편**: 루트에서 docker-compose 한 번 실행
4. **설정 공유**: conf/ 디렉토리로 공통 설정 관리