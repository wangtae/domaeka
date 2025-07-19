FROM python:3.12-slim

# 작업 디렉토리 설정
WORKDIR /app

# 시스템 패키지 업데이트 및 필요한 패키지 설치
RUN apt-get update && apt-get install -y \
    gcc \
    python3-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# 의존성 파일 복사 및 설치
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 로그 디렉토리 생성
RUN mkdir -p /app/logs

# 포트 노출
EXPOSE 1490 1491

# Supervisor 설정 파일 복사
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 애플리케이션 코드는 볼륨으로 마운트되므로 복사하지 않음

# Supervisor를 사용하여 여러 프로세스 실행
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]