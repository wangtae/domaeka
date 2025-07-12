"""
동적 명령어 관리 시스템

이 모듈은 봇별/방별 명령어 설정을 JSON 파일에서 로드하고 관리합니다.
- config/bots-commands/{bot-name}.json 파일에서 봇별 명령어 설정 로드
- global_commands와 room_overrides를 지원
- 메모리 캐싱 및 선택적 리로드 기능 제공
"""

import json
import asyncio
from pathlib import Path
from typing import Dict, Any, Optional, Tuple
from core.logger import logger
from core.command_loader import load_prefix_map_from_json


class CommandManager:
    """봇별/방별 명령어 설정을 관리하는 클래스"""
    
    def __init__(self):
        self.commands_dir = Path(__file__).parent.parent / "config" / "bots-commands"
        self.loaded_bots: Dict[str, Dict[str, Any]] = {}  # {bot_name: command_config}
        self.prefix_maps: Dict[str, Tuple[Dict, list]] = {}  # {bot_name: (PREFIX_MAP, ENABLED_PREFIXES)}
        self._lock = asyncio.Lock()
        
        # 명령어 디렉토리가 없으면 생성
        self.commands_dir.mkdir(parents=True, exist_ok=True)
        
    def _load_bot_commands_internal(self, bot_name: str, force_reload: bool = False) -> bool:
        """
        내부용: 락 없이 봇 명령어를 로드합니다.
        이미 락을 획득한 상태에서 호출되어야 합니다.
        
        Args:
            bot_name: 봇 이름
            force_reload: 강제 리로드 여부
            
        Returns:
            bool: 로드 성공 여부
        """
        logger.warning(f"[COMMAND_MANAGER] _load_bot_commands_internal \'{bot_name}\' 시작")
        
        if not force_reload and bot_name in self.loaded_bots:
            logger.warning(f"[COMMAND_MANAGER] 봇 \'{bot_name}\' 명령어가 이미 로드됨")
            return True
        
        config_file = self.commands_dir / f"{bot_name}.json"
        logger.warning(f"[COMMAND_MANAGER] 설정 파일 경로: {config_file}")
        
        if not config_file.exists():
            logger.error(f"[COMMAND_MANAGER] ❌ 봇 \'{bot_name}\' 명령어 파일이 없음: {config_file}")
            return False
            
        try:
            logger.warning(f"[COMMAND_MANAGER] JSON 파일 읽기 시작: {config_file}")
            with config_file.open("r", encoding="utf-8") as f:
                config_data = json.load(f)
            logger.warning(f"[COMMAND_MANAGER] JSON 파일 읽기 완료")
                
            self.loaded_bots[bot_name] = config_data
            logger.warning(f"[COMMAND_MANAGER] 봇 데이터 저장 완료")
            
            # PREFIX_MAP과 ENABLED_PREFIXES 생성
            global_commands = config_data.get("global_commands", {})
            logger.warning(f"[COMMAND_MANAGER] PREFIX_MAP 생성 시작 ({len(global_commands)}개 명령어)")
            prefix_map, enabled_prefixes = load_prefix_map_from_json(global_commands)
            logger.warning(f"[COMMAND_MANAGER] PREFIX_MAP 생성 완료")
            self.prefix_maps[bot_name] = (prefix_map, enabled_prefixes)
            
            logger.warning(f"[COMMAND_MANAGER] ✅ 봇 \'{bot_name}\' 명령어 로드 완료 ({len(global_commands)}개 명령어)")
            return True
            
        except Exception as e:
            logger.error(f"[COMMAND_MANAGER] 봇 \'{bot_name}\' 명령어 로드 실패: {e}", exc_info=True)
            return False

    async def load_bot_commands(self, bot_name: str, force_reload: bool = False) -> bool:
        """
        특정 봇의 명령어 설정을 로드합니다.
        
        Args:
            bot_name: 봇 이름
            force_reload: 강제 리로드 여부
            
        Returns:
            bool: 로드 성공 여부
        """
        logger.warning(f"[COMMAND_MANAGER] 🔄 load_bot_commands \'{bot_name}\' 시작 (락 획득 시도)")
        async with self._lock:
            logger.warning(f"[COMMAND_MANAGER] load_bot_commands \'{bot_name}\' 락 획득 완료")
            return self._load_bot_commands_internal(bot_name, force_reload)
    
    async def load_all_bot_commands(self) -> Dict[str, bool]:
        """
        commands 디렉토리의 모든 봇 명령어 파일을 로드합니다.
        
        Returns:
            Dict[str, bool]: {bot_name: 로드_성공_여부}
        """
        logger.warning(f"[COMMAND_MANAGER] ⚠️ load_all_bot_commands 시작 ⚠️")
        results = {}
        
        if not self.commands_dir.exists():
            logger.error(f"[COMMAND_MANAGER] ❌ 명령어 디렉토리가 없음: {self.commands_dir}")
            return results
        
        logger.warning(f"[COMMAND_MANAGER] 명령어 디렉토리 존재 확인: {self.commands_dir}")
        
        # JSON 파일 목록 먼저 확인
        json_files = list(self.commands_dir.glob("*.json"))
        logger.warning(f"[COMMAND_MANAGER] 발견된 JSON 파일들: {[f.name for f in json_files]}")
        
        logger.warning(f"[COMMAND_MANAGER] 파일 로드 시작 (락 획득 시도)")
        async with self._lock:
            logger.warning(f"[COMMAND_MANAGER] 파일 로드 락 획득 완료")
            
            for config_file in json_files:
                bot_name = config_file.stem
                logger.warning(f"[COMMAND_MANAGER] 🔄 봇 \'{bot_name}\' 로드 시작...")
                try:
                    # 이미 락을 획득한 상태이므로 내부 함수 사용 (데드락 방지)
                    success = self._load_bot_commands_internal(bot_name, force_reload=True) 
                    results[bot_name] = success
                    logger.warning(f"[COMMAND_MANAGER] ✅ 봇 \'{bot_name}\' 로드 완료: {success}")
                except Exception as e:
                    logger.error(f"[COMMAND_MANAGER] ❌ 봇 \'{bot_name}\' 로드 중 오류: {e}", exc_info=True)
                    results[bot_name] = False
                        
            logger.warning(f"[COMMAND_MANAGER] ✅ 전체 봇 명령어 로드 완료: {results}")
            return results
    
    def get_command_config(self, bot_name: str, channel_id: str, command_prefix: str) -> Optional[Dict[str, Any]]:
        """
        특정 봇의 특정 방에서 특정 명령어의 설정을 가져옵니다.
        방별 오버라이드를 우선 적용합니다.
        
        Args:
            bot_name: 봇 이름
            channel_id: 채널 ID (방 ID)
            command_prefix: 명령어 접두어
            
        Returns:
            Dict[str, Any]: 명령어 설정 (없으면 None)
        """
        if bot_name not in self.loaded_bots:
            logger.debug(f"[COMMAND_MANAGER] 봇 '{bot_name}' 명령어가 로드되지 않음")
            return None
            
        config = self.loaded_bots[bot_name]
        
        # 1. 방별 오버라이드 확인
        room_overrides = config.get("room_overrides", {})
        if channel_id in room_overrides:
            room_commands = room_overrides[channel_id].get("commands", {})
            if command_prefix in room_commands:
                logger.debug(f"[COMMAND_MANAGER] 방별 오버라이드 명령어 사용: {bot_name}/{channel_id}/{command_prefix}")
                return room_commands[command_prefix]
        
        # 2. 글로벌 명령어 확인
        global_commands = config.get("global_commands", {})
        if command_prefix in global_commands:
            logger.debug(f"[COMMAND_MANAGER] 글로벌 명령어 사용: {bot_name}/{command_prefix}")
            return global_commands[command_prefix]
            
        return None
    
    def get_bot_prefix_map(self, bot_name: str) -> Tuple[Dict, list]:
        """
        특정 봇의 PREFIX_MAP과 ENABLED_PREFIXES를 가져옵니다.
        
        Args:
            bot_name: 봇 이름
            
        Returns:
            Tuple[Dict, list]: (PREFIX_MAP, ENABLED_PREFIXES)
        """
        if bot_name in self.prefix_maps:
            return self.prefix_maps[bot_name]
        else:
            logger.warning(f"[COMMAND_MANAGER] 봇 '{bot_name}' PREFIX_MAP이 없음")
            return {}, []
    
    def get_bot_command_info(self, bot_name: str, channel_id: str, command_prefix: str) -> Optional[Dict[str, Any]]:
        """
        특정 봇의 특정 방에서 PREFIX_MAP에서 명령어 정보를 가져옵니다.
        방별 오버라이드가 적용된 PREFIX_MAP을 사용합니다.
        
        Args:
            bot_name: 봇 이름  
            channel_id: 채널 ID
            command_prefix: 명령어 접두어
            
        Returns:
            Dict[str, Any]: 명령어 정보 (없으면 None)
        """
        if bot_name not in self.loaded_bots:
            return None
            
        # 방별 오버라이드를 적용한 모든 명령어 가져오기
        all_commands = self.get_all_commands_for_bot(bot_name, channel_id)
        
        # 해당 명령어가 있는지 확인
        if command_prefix in all_commands:
            command_config = all_commands[command_prefix]
            logger.debug(f"[COMMAND_MANAGER] 명령어 '{command_prefix}' 발견: type={command_config.get('type')}")
            return command_config
        else:
            logger.debug(f"[COMMAND_MANAGER] 명령어 '{command_prefix}' 없음. 사용 가능한 명령어: {list(all_commands.keys())[:10]}...")
            return None
    
    def get_bot_enabled_prefixes(self, bot_name: str, channel_id: str) -> list:
        """
        특정 봇의 특정 방에서 활성화된 접두어 목록을 가져옵니다.
        
        Args:
            bot_name: 봇 이름
            channel_id: 채널 ID
            
        Returns:
            list: 활성화된 접두어 목록
        """
        if bot_name not in self.loaded_bots:
            return []
            
        # get_all_commands_for_bot을 사용하여 command_overrides 적용된 명령어 목록 가져오기
        all_commands = self.get_all_commands_for_bot(bot_name, channel_id)
        
        # ENABLED_PREFIXES 생성
        _, enabled_prefixes = load_prefix_map_from_json(all_commands)
        
        return enabled_prefixes
    
    async def reload_bot_commands(self, bot_name: str) -> bool:
        """
        특정 봇의 명령어를 다시 로드합니다.
        
        Args:
            bot_name: 봇 이름
            
        Returns:
            bool: 리로드 성공 여부
        """
        logger.info(f"[COMMAND_MANAGER] 봇 '{bot_name}' 명령어 리로드 시작")
        return await self.load_bot_commands(bot_name, force_reload=True)
    
    def get_loaded_bots(self) -> list:
        """로드된 봇 목록을 반환합니다."""
        return list(self.loaded_bots.keys())
    
    def is_bot_loaded(self, bot_name: str) -> bool:
        """특정 봇이 로드되었는지 확인합니다."""
        return bot_name in self.loaded_bots
    
    def get_all_commands_for_bot(self, bot_name: str, channel_id: str = None) -> Dict[str, Any]:
        """
        특정 봇의 모든 명령어를 가져옵니다. 방별 오버라이드가 적용됩니다.
        
        Args:
            bot_name: 봇 이름
            channel_id: 채널 ID (선택사항, 없으면 글로벌 명령어만)
            
        Returns:
            Dict[str, Any]: 모든 명령어 설정
        """
        if bot_name not in self.loaded_bots:
            return {}
            
        config = self.loaded_bots[bot_name]
        all_commands = config.get("global_commands", {}).copy()
        
        # 방별 오버라이드 적용
        if channel_id:
            room_overrides = config.get("room_overrides", {})
            if channel_id in room_overrides:
                room_data = room_overrides[channel_id]
                
                # 1. 기존 commands 방식 (하위호환성)
                room_commands = room_data.get("commands", {})
                all_commands.update(room_commands)
                
                # 2. 새로운 command_overrides 방식
                command_overrides = room_data.get("command_overrides", {})
                for original_cmd, override_config in command_overrides.items():
                    if original_cmd in all_commands:
                        # 원본 명령어 설정을 베이스로 시작
                        original_config = all_commands[original_cmd].copy()
                        
                        # replace_with가 있으면 명령어 이름 변경
                        new_cmd_name = override_config.get("replace_with", original_cmd)
                        
                        # 오버라이드 설정을 원본에 병합 (replace_with 제외)
                        override_without_replace = {k: v for k, v in override_config.items() if k != "replace_with"}
                        original_config.update(override_without_replace)
                        
                        # 원본 명령어 제거하고 새 명령어 추가
                        if new_cmd_name != original_cmd:
                            del all_commands[original_cmd]
                        all_commands[new_cmd_name] = original_config
                        
                        logger.debug(f"[COMMAND_OVERRIDE] '{original_cmd}' → '{new_cmd_name}' (채널: {channel_id})")
        
        return all_commands


# 전역 명령어 매니저 인스턴스
command_manager = CommandManager() 