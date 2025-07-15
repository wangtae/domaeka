"""
명령어 파라미터 파싱 유틸리티
"""
import re
from typing import Dict, Any, Tuple, Optional, List
from core.logger import logger


def parse_command_params(text: str) -> Tuple[str, Dict[str, str]]:
    """
    명령어 텍스트에서 프롬프트와 파라미터를 분리
    
    Args:
        text: 전체 명령어 텍스트 (예: "test message --channel-id=room123 --repeat=3")
        
    Returns:
        (프롬프트, 파라미터 딕셔너리) 튜플
    """
    # --key=value 형태의 파라미터 패턴
    param_pattern = r'--([a-zA-Z0-9-]+)=([^\s]+)'
    
    # 모든 파라미터 찾기
    params = {}
    for match in re.finditer(param_pattern, text):
        key = match.group(1)
        value = match.group(2)
        params[key] = value
    
    # 파라미터를 제거한 프롬프트 추출
    prompt = re.sub(param_pattern, '', text).strip()
    
    return prompt, params


def validate_parameters(params: Dict[str, str], param_definitions: List[Dict[str, Any]]) -> Tuple[bool, Optional[str], Dict[str, Any]]:
    """
    파라미터 유효성 검증 및 타입 변환
    
    Args:
        params: 파싱된 파라미터 딕셔너리
        param_definitions: 명령어 정의의 parameters 리스트
        
    Returns:
        (유효성 여부, 오류 메시지, 변환된 파라미터) 튜플
    """
    validated_params = {}
    
    # 파라미터 정의를 이름으로 매핑
    param_def_map = {p['name']: p for p in param_definitions}
    
    # 필수 파라미터 확인
    for param_def in param_definitions:
        name = param_def['name']
        optional = param_def.get('optional', False)
        
        if not optional and name not in params:
            return False, f"필수 파라미터 '{name}'가 누락되었습니다.", {}
    
    # 제공된 파라미터 검증
    for name, value in params.items():
        if name not in param_def_map:
            # 정의되지 않은 파라미터는 무시 (또는 오류 처리)
            logger.warning(f"[PARAM] 정의되지 않은 파라미터: {name}")
            continue
        
        param_def = param_def_map[name]
        param_type = param_def.get('type', 'string')
        
        # 타입 변환
        try:
            if param_type == 'int':
                validated_params[name] = int(value)
            elif param_type == 'float':
                validated_params[name] = float(value)
            elif param_type == 'bool':
                validated_params[name] = value.lower() in ('true', '1', 'yes', 'on')
            else:  # string
                validated_params[name] = value
        except ValueError:
            return False, f"파라미터 '{name}'의 값이 올바른 {param_type} 형식이 아닙니다: {value}", {}
    
    # 기본값 적용
    for param_def in param_definitions:
        name = param_def['name']
        if name not in validated_params and 'default' in param_def:
            validated_params[name] = param_def['default']
    
    return True, None, validated_params


def check_channel_restriction(params: Dict[str, Any], current_channel_id: str) -> bool:
    """
    channel-id 파라미터가 있을 경우 현재 채널과 일치하는지 확인
    
    Args:
        params: 검증된 파라미터
        current_channel_id: 현재 채널 ID
        
    Returns:
        실행 가능 여부
    """
    if 'channel-id' in params:
        target_channel = str(params['channel-id'])
        current_channel = str(current_channel_id)
        
        if target_channel != current_channel:
            logger.info(f"[PARAM] 채널 제한: 대상={target_channel}, 현재={current_channel}")
            return False
    
    return True