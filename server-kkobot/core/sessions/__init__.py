"""
세션 관리 패키지
- 채팅 세션 관리 및 처리 기능 제공
"""

# 세션 관리 패키지 버전
__version__ = '1.0.0'

# 필요한 하위 모듈 import
from core.sessions.session_manager import (
    create_private_session, create_group_session,
    extend_session, end_session, get_active_session,
    get_all_active_sessions, get_session_stats,
    cleanup_expired_sessions, session_cleanup_task
)

# 변경된 경로에서 임포트
from services.llm_chat_sessions.session_processor import (
    process_session_message, generate_session_response
)

from services.llm_chat_sessions.session_commands import (
    handle_session_command
)

from core.sessions.session_scheduler import (
    check_expiring_sessions, session_scheduler_task
)

from core.sessions.session_store import (
    active_sessions, daily_usage, channel_daily_usage,
    generate_session_id, check_and_reset_daily_limits,
    create_private_session_info, create_group_session_info
)

# 외부로 노출할 이름 정의
__all__ = [
    # session_manager
    'create_private_session', 'create_group_session',
    'extend_session', 'end_session', 'get_active_session',
    'get_all_active_sessions', 'get_session_stats',
    'cleanup_expired_sessions', 'session_cleanup_task',
    
    # session_processor
    'process_session_message', 'generate_session_response',
    
    # session_commands
    'handle_session_command',
    
    # session_scheduler
    'check_expiring_sessions', 'session_scheduler_task',

    # session_store (직접 접근이 필요한 경우를 대비)
    'active_sessions', 'daily_usage', 'channel_daily_usage',
    'generate_session_id', 'check_and_reset_daily_limits',
    'create_private_session_info', 'create_group_session_info'
]

# 패키지 초기화 시 필요한 작업이 있으면 여기 추가
