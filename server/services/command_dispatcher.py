"""
명령어 디스패처 모듈
각 명령어 타입에 따라 적절한 서비스로 라우팅
"""
from typing import Dict, Any, Optional
import core.globals as g
from core.logger import logger
from core.utils.command_parser import parse_command_params, validate_parameters, check_channel_restriction
from services.echo_service import handle_echo_command
from services.client_info_service import handle_client_info_command
from services.imgext_service import handle_imgext_command
from services.reload_bots_config_service import reload_bots_config


async def process_command(context: Dict[str, Any], command_prefix: str, text_after_prefix: str) -> Optional[Any]:
    """
    명령어를 처리하고 결과를 반환
    
    Args:
        context: 메시지 컨텍스트
        command_prefix: 명령어 접두어 (예: "# echo")
        text_after_prefix: 명령어 뒤의 전체 텍스트 (프롬프트 + 파라미터)
        
    Returns:
        처리 결과 또는 None
    """
    # 명령어 정보 가져오기
    command_info = g.COMMAND_PREFIX_MAP.get(command_prefix)
    if not command_info:
        logger.warning(f"[COMMAND] 알 수 없는 명령어: {command_prefix}")
        return None
    
    command_type = command_info['type']
    admin_only = command_info.get('admin_only', False)
    prompt_required = command_info.get('prompt_required', False)
    param_definitions = command_info.get('parameters', [])
    
    # 프롬프트와 파라미터 파싱
    prompt, raw_params = parse_command_params(text_after_prefix)
    
    # 파라미터 검증 및 타입 변환
    is_valid, error_msg, params = validate_parameters(raw_params, param_definitions)
    if not is_valid:
        return f"❌ 파라미터 오류: {error_msg}"
    
    # 채널 제한 확인
    current_channel_id = context.get('channel_id', '')
    if not check_channel_restriction(params, current_channel_id):
        # 채널이 일치하지 않으면 조용히 무시
        logger.info(f"[COMMAND] 채널 제한으로 명령어 무시: {command_prefix}")
        return None
    
    # 관리자 권한 체크 (필요시 구현)
    if admin_only:
        # TODO: 관리자 권한 확인 로직 추가
        pass
    
    # 프롬프트 필수 체크
    if prompt_required and not prompt:
        return f"사용법: {command_prefix} {{내용}}"
    
    # 명령어 타입별 처리
    try:
        if command_type == 'echo':
            await handle_echo_command(context, prompt, params)
            return None  # 직접 응답 처리
            
        elif command_type == 'client_info':
            await handle_client_info_command(context, prompt, params)
            return None  # 직접 응답 처리
            
        elif command_type == 'imgext':
            await handle_imgext_command(context, prompt, params)
            return None  # 직접 응답 처리
            
        elif command_type == 'reload_bots_config':
            result = await reload_bots_config(context)
            return result  # 리스트 형태의 응답 반환
            
        else:
            logger.error(f"[COMMAND] 구현되지 않은 명령어 타입: {command_type}")
            return "이 명령어는 아직 구현되지 않았습니다."
            
    except Exception as e:
        logger.error(f"[COMMAND] 명령어 처리 중 오류: {command_type} - {e}")
        return f"명령어 처리 중 오류가 발생했습니다: {str(e)}"


def parse_command(text: str) -> tuple[Optional[str], Optional[str]]:
    """
    텍스트에서 명령어 접두어와 프롬프트를 분리
    
    Args:
        text: 전체 텍스트
        
    Returns:
        (명령어 접두어, 프롬프트) 튜플. 명령어가 아니면 (None, None)
    """
    # 활성화된 명령어 확인
    for prefix in g.ENABLED_PREFIXES:
        if text.startswith(prefix):
            # 명령어 뒤의 텍스트 추출
            prompt = text[len(prefix):].strip()
            return prefix, prompt
    
    return None, None