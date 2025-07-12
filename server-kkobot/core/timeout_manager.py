import asyncio
import time
import logging

class TimeoutManager:
    def __init__(self):
        self.tasks = {}  # {key: (expire_time, callback, args, kwargs)}
        self.running = False

    def add(self, key, timeout_sec, callback, *args, **kwargs):
        """
        타임아웃 작업을 등록/갱신합니다.
        key: 고유 식별자(예: 'omok:채널ID')
        timeout_sec: 만료까지 남은 시간(초)
        callback: 만료 시 실행할 비동기 함수
        args, kwargs: 콜백에 전달할 인자
        """
        expire_time = time.time() + timeout_sec
        self.tasks[key] = (expire_time, callback, args, kwargs)

    def remove(self, key):
        """등록된 타임아웃 작업을 제거합니다."""
        if key in self.tasks:
            del self.tasks[key]

    async def run(self, interval=1):
        """
        주기적으로 만료된 작업을 검사하고 콜백을 실행합니다.
        interval: 검사 주기(초)
        """
        self.running = True
        while self.running:
            if not self.tasks:
                await asyncio.sleep(interval)
                continue
            now = time.time()
            print(f"[TIMEOUT_MANAGER][DEBUG] 현재 등록된 타임아웃: {list(self.tasks.keys())}")
            expired = [k for k, (t, _, _, _) in self.tasks.items() if t <= now]
            for k in expired:
                print(f"[TIMEOUT_MANAGER][DEBUG] 만료된 타임아웃: {k}")
                _, cb, args, kwargs = self.tasks.pop(k)
                asyncio.create_task(cb(*args, **kwargs))
            await asyncio.sleep(interval)

    def stop(self):
        self.running = False

# 전역 인스턴스
timeout_manager = TimeoutManager() 