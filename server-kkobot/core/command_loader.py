import json
import os
from pathlib import Path
from core.logger import logger
from typing import Dict, List, Tuple, Optional
import core.globals as g

def load_prefix_map_from_json(json_data):
    prefix_map = {}
    enabled_prefixes = []

    for main_prefix, info in json_data.items():
        prefix_map[main_prefix] = info
        enabled_prefixes.append(main_prefix)

        # ✅ aliases 처리
        for alias in info.get("aliases", []):
            if alias not in prefix_map:
                prefix_map[alias] = info
                enabled_prefixes.append(alias)

    return prefix_map, enabled_prefixes


def load_bot_commands(bot_name: str) -> bool:
    """
    특정 봇의 명령어 파일을 로드하여 메모리에 저장합니다.
    
    Args:
        bot_name (str): 봇 이름 (파일명으로 사용됨)
        
    Returns:
        bool: 로드 성공 여부
    """
    try:
        commands_file = g.COMMANDS_DIR / f"{bot_name}.json"
        
        if not commands_file.exists():
            logger.warning(f"[COMMAND_LOADER] 봇 명령어 파일을 찾을 수 없음: {commands_file}")
            return False
            
        with commands_file.open("r", encoding="utf-8") as f:
            bot_command_data = json.load(f)
            
        # JSON 구조 검증
        if "global_commands" not in bot_command_data:
            logger.error(f"[COMMAND_LOADER] 잘못된 명령어 파일 구조: {commands_file} (global_commands 필드 없음)")
            return False
            
        # 전역 명령어에서 PREFIX_MAP과 ENABLED_PREFIXES 생성
        global_commands = bot_command_data["global_commands"]
        prefix_map, enabled_prefixes = load_prefix_map_from_json(global_commands)
        
        # room_overrides 처리 (향후 구현 예정)
        room_overrides = bot_command_data.get("room_overrides", {})
        
        # 봇별 명령어 데이터 저장
        g.bot_commands[bot_name] = {
            "command_data": global_commands,
            "prefix_map": prefix_map,
            "enabled_prefixes": enabled_prefixes,
            "room_overrides": room_overrides,
            "file_path": str(commands_file)
        }
        
        logger.info(f"[COMMAND_LOADER] 봇 명령어 로드 성공: {bot_name} ({len(global_commands)}개 명령어)")
        return True
        
    except json.JSONDecodeError as e:
        logger.error(f"[COMMAND_LOADER] JSON 파싱 오류 - {commands_file}: {e}")
        return False
    except Exception as e:
        logger.error(f"[COMMAND_LOADER] 봇 명령어 로드 실패 - {bot_name}: {e}")
        return False


def get_bot_commands(bot_name: str) -> Optional[Dict]:
    """
    특정 봇의 명령어 데이터를 반환합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        Optional[Dict]: 봇의 명령어 데이터, 없으면 None
    """
    return g.bot_commands.get(bot_name)


def get_bot_prefix_map(bot_name: str) -> Dict:
    """
    특정 봇의 PREFIX_MAP을 반환합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        Dict: 봇의 PREFIX_MAP, 없으면 빈 딕셔너리
    """
    bot_data = g.bot_commands.get(bot_name)
    if bot_data:
        return bot_data.get("prefix_map", {})
    return {}


def get_bot_enabled_prefixes(bot_name: str) -> List[str]:
    """
    특정 봇의 활성화된 접두어 리스트를 반환합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        List[str]: 봇의 활성화된 접두어 리스트, 없으면 빈 리스트
    """
    bot_data = g.bot_commands.get(bot_name)
    if bot_data:
        return bot_data.get("enabled_prefixes", [])
    return []


def reload_bot_commands(bot_name: str) -> bool:
    """
    특정 봇의 명령어를 다시 로드합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        bool: 리로드 성공 여부
    """
    logger.info(f"[COMMAND_LOADER] 봇 명령어 리로드 시작: {bot_name}")
    
    # 기존 데이터 제거
    if bot_name in g.bot_commands:
        del g.bot_commands[bot_name]
    
    # 다시 로드
    return load_bot_commands(bot_name)


def reload_all_bot_commands() -> Dict[str, bool]:
    """
    모든 봇의 명령어를 다시 로드합니다.
    
    Returns:
        Dict[str, bool]: 각 봇별 리로드 성공/실패 결과
    """
    results = {}
    
    try:
        # commands 디렉토리의 모든 JSON 파일 스캔
        if not g.COMMANDS_DIR.exists():
            logger.error(f"[COMMAND_LOADER] commands 디렉토리가 존재하지 않음: {g.COMMANDS_DIR}")
            return results
            
        for json_file in g.COMMANDS_DIR.glob("*.json"):
            bot_name = json_file.stem  # 파일명에서 확장자 제거
            results[bot_name] = reload_bot_commands(bot_name)
            
        logger.info(f"[COMMAND_LOADER] 전체 봇 명령어 리로드 완료: {len(results)}개 봇")
        
    except Exception as e:
        logger.error(f"[COMMAND_LOADER] 전체 봇 명령어 리로드 중 오류: {e}")
        
    return results


def is_bot_commands_loaded(bot_name: str) -> bool:
    """
    특정 봇의 명령어가 로드되어 있는지 확인합니다.
    
    Args:
        bot_name (str): 봇 이름
        
    Returns:
        bool: 로드 여부
    """
    return bot_name in g.bot_commands
