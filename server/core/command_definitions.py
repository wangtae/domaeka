"""
명령어 정의 시스템
server-kkobot 구조를 참고하여 확장 가능한 명령어 관리 시스템 구현
"""

# 명령어 정의 딕셔너리
COMMAND_DEFINITIONS = {
    "# IMGEXT": {
        "type": "multi_image_generator",
        "desc": "텍스트를 단어별로 이미지로 변환하여 멀티 이미지 전송",
        "parameters": {
            "media-wait-time": {
                "description": "미디어 전송 후 대기시간 (밀리초)",
                "required": False,
                "type": "int",
                "min": 1000,
                "max": 30000,
                "default": None
            }
        }
    },
    
    "# echo": {
        "type": "echo",
        "desc": "에코 테스트 명령어",
        "parameters": {}
    },
    
    "# ping": {
        "type": "ping",
        "desc": "연결 상태 확인",
        "parameters": {}
    }
}


def get_command_definition(command: str) -> dict:
    """
    명령어 정의 조회
    
    Args:
        command: 명령어 문자열
    
    Returns:
        명령어 정의 딕셔너리 또는 None
    """
    return COMMAND_DEFINITIONS.get(command)


def get_all_commands() -> dict:
    """
    모든 명령어 정의 반환
    
    Returns:
        전체 명령어 정의 딕셔너리
    """
    return COMMAND_DEFINITIONS


def parse_command_parameters(command: str, text: str) -> tuple:
    """
    명령어 파라미터 파싱
    
    Args:
        command: 명령어 (예: "# IMGEXT")
        text: 전체 텍스트
    
    Returns:
        (파싱된 파라미터 딕셔너리, 남은 텍스트)
    """
    command_def = get_command_definition(command)
    if not command_def:
        return {}, text
    
    # 명령어 부분 제거
    remaining_text = text.replace(command, "").strip()
    parsed_params = {}
    
    # 파라미터가 정의되어 있는 경우에만 파싱
    if "parameters" in command_def:
        parameters_def = command_def["parameters"]
        
        # --로 시작하는 옵션들 파싱
        import re
        
        for param_name, param_def in parameters_def.items():
            # --param-name=value 형태의 옵션 찾기
            pattern = rf"--{param_name}=([^\s]+)"
            match = re.search(pattern, remaining_text)
            
            if match:
                value_str = match.group(1)
                
                # 타입에 따른 변환
                if param_def.get("type") == "int":
                    try:
                        value = int(value_str)
                        # min/max 검증
                        if "min" in param_def and value < param_def["min"]:
                            value = param_def["min"]
                        if "max" in param_def and value > param_def["max"]:
                            value = param_def["max"]
                        parsed_params[param_name.replace("-", "_")] = value
                    except ValueError:
                        pass  # 잘못된 값이면 무시
                elif param_def.get("type") == "string":
                    parsed_params[param_name.replace("-", "_")] = value_str
                
                # 파싱된 옵션을 텍스트에서 제거
                remaining_text = re.sub(pattern, "", remaining_text).strip()
    
    return parsed_params, remaining_text


def validate_command_parameters(command: str, params: dict) -> tuple:
    """
    명령어 파라미터 유효성 검증
    
    Args:
        command: 명령어
        params: 파라미터 딕셔너리
    
    Returns:
        (유효성 여부, 오류 메시지)
    """
    command_def = get_command_definition(command)
    if not command_def:
        return False, f"알 수 없는 명령어: {command}"
    
    if "parameters" not in command_def:
        return True, ""
    
    parameters_def = command_def["parameters"]
    
    # 필수 파라미터 검증
    for param_name, param_def in parameters_def.items():
        param_key = param_name.replace("-", "_")
        
        if param_def.get("required", False) and param_key not in params:
            return False, f"필수 파라미터 누락: {param_name}"
    
    return True, ""


def get_command_help(command: str) -> str:
    """
    명령어 도움말 생성
    
    Args:
        command: 명령어
    
    Returns:
        도움말 문자열
    """
    command_def = get_command_definition(command)
    if not command_def:
        return f"알 수 없는 명령어: {command}"
    
    help_text = f"{command}\n"
    help_text += f"설명: {command_def.get('desc', '설명 없음')}\n"
    
    if "parameters" in command_def and command_def["parameters"]:
        help_text += "\n파라미터:\n"
        for param_name, param_def in command_def["parameters"].items():
            required = " (필수)" if param_def.get("required", False) else " (선택)"
            param_type = param_def.get("type", "string")
            description = param_def.get("description", "설명 없음")
            
            help_text += f"  --{param_name}={{{param_type}}}{required}: {description}\n"
            
            if param_def.get("min") is not None:
                help_text += f"    최소값: {param_def['min']}\n"
            if param_def.get("max") is not None:
                help_text += f"    최대값: {param_def['max']}\n"
            if param_def.get("default") is not None:
                help_text += f"    기본값: {param_def['default']}\n"
    
    return help_text


def get_all_commands_help() -> str:
    """
    모든 명령어 도움말 생성
    
    Returns:
        전체 명령어 도움말 문자열
    """
    help_text = "사용 가능한 명령어 목록:\n\n"
    
    for command in COMMAND_DEFINITIONS.keys():
        command_def = COMMAND_DEFINITIONS[command]
        help_text += f"{command}: {command_def.get('desc', '설명 없음')}\n"
    
    help_text += "\n각 명령어의 상세 도움말을 보려면 '# help {명령어}'를 입력하세요."
    
    return help_text