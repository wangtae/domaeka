#!/usr/bin/env python3
"""
Docker 컨테이너 내부 헬스체크 스크립트
"""
import sys
import psutil
import requests
from datetime import datetime

def check_health():
    """종합적인 헬스체크"""
    
    # 1. CPU 사용률 체크
    cpu_percent = psutil.cpu_percent(interval=1)
    if cpu_percent > 80:
        print(f"CPU 사용률 초과: {cpu_percent}%")
        return False
    
    # 2. 메모리 사용률 체크
    memory = psutil.virtual_memory()
    if memory.percent > 80:
        print(f"메모리 사용률 초과: {memory.percent}%")
        return False
    
    # 3. HTTP 엔드포인트 체크
    try:
        response = requests.get('http://localhost:1490/health', timeout=5)
        if response.status_code != 200:
            print(f"HTTP 상태 이상: {response.status_code}")
            return False
    except Exception as e:
        print(f"HTTP 체크 실패: {e}")
        return False
    
    # 4. 디스크 공간 체크
    disk = psutil.disk_usage('/')
    if disk.percent > 90:
        print(f"디스크 사용률 초과: {disk.percent}%")
        return False
    
    print(f"헬스체크 성공 - {datetime.now()}")
    return True

if __name__ == "__main__":
    if check_health():
        sys.exit(0)  # 성공
    else:
        sys.exit(1)  # 실패 (Docker가 컨테이너 재시작)