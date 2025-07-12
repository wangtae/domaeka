import os
import json
import asyncio
from typing import Dict, Any, Optional
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
from core.logger import logger

class ConfigManager:
    """
    4계층 DB → JSON 자동 생성 시스템에서 생성된 JSON 파일을 관리하는 클래스
    웹 관리자(web/admin)에서 생성한 JSON 파일을 실시간으로 로드하고 캐시합니다.
    """
    
    def __init__(self):
        self._commands_cache: Dict[str, Dict[str, Any]] = {}
        self._settings_cache: Dict[str, Dict[str, Any]] = {}
        self._cache_lock = asyncio.Lock()
        
        # 기존 서버 config 경로 사용 (개발자 검증 후 적용)
        project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
        self.bot_configs_base_path = os.path.join(project_root, 'config')
        
        self.commands_path = os.path.join(self.bot_configs_base_path, 'bots-commands')
        self.settings_path = os.path.join(self.bot_configs_base_path, 'bots-settings')
        
        # TODO: 향후 웹 관리자 JSON 검증 완료 후 아래 경로로 변경
        # web_admin_path = os.path.join(project_root, '..', 'web', 'admin', 'storage', 'app', 'bot_configs')
        # self.commands_path = os.path.join(web_admin_path, 'bots-commands')
        # self.settings_path = os.path.join(web_admin_path, 'bots-settings')
        
        # 디렉토리가 없으면 생성
        os.makedirs(self.commands_path, exist_ok=True)
        os.makedirs(self.settings_path, exist_ok=True)
        
        logger.info(f"ConfigManager initialized with paths:")
        logger.info(f"  Commands: {self.commands_path}")
        logger.info(f"  Settings: {self.settings_path}")
        
    async def load_initial_configs(self):
        """서버 시작 시 모든 봇 설정 로드"""
        logger.info("Loading initial bot configurations...")
        
        # Commands 디렉토리의 모든 JSON 파일 스캔
        if os.path.exists(self.commands_path):
            for bot_file in os.listdir(self.commands_path):
                if bot_file.endswith('.json'):
                    bot_name = bot_file.replace('.json', '')
                    await self.reload_bot_config(bot_name)
        
        logger.info(f"Loaded configurations for {len(self._commands_cache)} bots")
        
    async def reload_bot_config(self, bot_name: str):
        """특정 봇 설정 리로드"""
        async with self._cache_lock:
            try:
                # Commands 캐시 갱신
                commands_file = os.path.join(self.commands_path, f"{bot_name}.json")
                if os.path.exists(commands_file):
                    with open(commands_file, 'r', encoding='utf-8') as f:
                        self._commands_cache[bot_name] = json.load(f)
                    logger.debug(f"Loaded commands config for {bot_name}")
                else:
                    self._commands_cache[bot_name] = {}
                    logger.warning(f"Commands file not found for {bot_name}: {commands_file}")

                # Settings 캐시 갱신
                settings_file = os.path.join(self.settings_path, f"{bot_name}.json")
                if os.path.exists(settings_file):
                    with open(settings_file, 'r', encoding='utf-8') as f:
                        self._settings_cache[bot_name] = json.load(f)
                    logger.debug(f"Loaded settings config for {bot_name}")
                else:
                    self._settings_cache[bot_name] = {}
                    logger.warning(f"Settings file not found for {bot_name}: {settings_file}")
                
                logger.info(f"Bot config reloaded: {bot_name}")

            except Exception as e:
                logger.error(f"Failed to reload config for {bot_name}: {e}")
                # 오류 시 빈 설정으로 초기화
                self._commands_cache[bot_name] = {}
                self._settings_cache[bot_name] = {}

    async def get_bot_commands(self, bot_name: str) -> Dict[str, Any]:
        """봇의 명령어 설정 반환"""
        async with self._cache_lock:
            return self._commands_cache.get(bot_name, {})
    
    async def get_bot_settings(self, bot_name: str) -> Dict[str, Any]:
        """봇의 설정 반환"""
        async with self._cache_lock:
            return self._settings_cache.get(bot_name, {})
    
    async def get_room_settings(self, bot_name: str, channel_id: str) -> Dict[str, Any]:
        """특정 방의 설정 반환"""
        settings = await self.get_bot_settings(bot_name)
        return settings.get('rooms', {}).get(channel_id, {})
    
    def get_available_bots(self) -> list:
        """사용 가능한 봇 목록 반환"""
        return list(self._commands_cache.keys())
    
    async def is_bot_available(self, bot_name: str) -> bool:
        """봇이 설정되어 있는지 확인"""
        return bot_name in self._commands_cache


class ConfigChangeHandler(FileSystemEventHandler):
    """JSON 파일 변경 감지 핸들러"""
    
    def __init__(self, config_manager: ConfigManager):
        self.config_manager = config_manager
        
    def on_modified(self, event):
        if event.is_directory:
            return
            
        if event.src_path.endswith('.json'):
            # 파일 경로에서 봇 이름 추출
            file_name = os.path.basename(event.src_path)
            bot_name = file_name.replace('.json', '')
            
            logger.info(f"Config file modified: {event.src_path}. Triggering reload for {bot_name}")
            
            # 비동기 컨텍스트에서 실행
            try:
                loop = asyncio.get_event_loop()
                loop.create_task(self.config_manager.reload_bot_config(bot_name))
            except RuntimeError:
                # 이벤트 루프가 없는 경우 새로 생성
                asyncio.run(self.config_manager.reload_bot_config(bot_name))


async def start_config_watcher(config_manager: ConfigManager):
    """설정 파일 감시자 시작"""
    observer = Observer()
    event_handler = ConfigChangeHandler(config_manager)
    
    # commands 및 settings 디렉토리 감시
    observer.schedule(event_handler, config_manager.commands_path, recursive=False)
    observer.schedule(event_handler, config_manager.settings_path, recursive=False)
    
    observer.start()
    logger.info(f"Config watcher started for:")
    logger.info(f"  {config_manager.commands_path}")
    logger.info(f"  {config_manager.settings_path}")
    
    try:
        while True:
            await asyncio.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
        logger.info("Config watcher stopped")
    observer.join()


# 전역 ConfigManager 인스턴스
config_manager = ConfigManager() 