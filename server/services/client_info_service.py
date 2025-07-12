"""
클라이언트 정보 조회 서비스
"""
import asyncio
from typing import Dict, Any
from core.logger import logger
from core.response_utils import send_message_response
from core.client_status import client_status_manager


async def handle_client_info_command(context: Dict[str, Any], text: str):
    """
    클라이언트 정보 조회 명령어 처리
    
    Args:
        context: 메시지 컨텍스트
        text: 명령어 텍스트
    """
    try:
        writer = context.get("writer")
        if not writer:
            logger.error("[CLIENT_INFO] Writer not found in context")
            return
            
        room = context.get("room", "")
        channel_id = context.get("channel_id", "")
        
        # 명령어 파싱
        command_parts = text.strip().split()
        
        if len(command_parts) == 2 and command_parts[1] == "summary":
            # 클라이언트 요약 정보
            summary = client_status_manager.get_client_summary()
            
            response_text = f"""📊 클라이언트 상태 요약
총 연결: {summary['total_clients']}개
활성 클라이언트: {summary['active_clients']}개

📋 연결된 클라이언트:"""
            
            for i, client in enumerate(summary['clients'], 1):
                bot_name = client.get('bot_name', 'Unknown')
                device_id = client.get('device_id', 'N/A')
                version = client.get('version', 'N/A')
                
                response_text += f"\n{i}. {bot_name} (v{version})"
                if device_id != 'N/A':
                    response_text += f"\n   Device: {device_id}"
                
                # RAM 정보 표시
                status = client.get('status')
                if status and status.get('ram_used_mb'):
                    ram_used = status.get('ram_used_mb', 0)
                    ram_max = status.get('ram_max_mb', 0)
                    ram_percent = (ram_used / ram_max * 100) if ram_max > 0 else 0
                    response_text += f"\n   RAM: {ram_used}MB/{ram_max}MB ({ram_percent:.1f}%)"
                
                # 모니터링 정보 표시
                monitoring = client.get('monitoring')
                if monitoring:
                    uptime = monitoring.get('uptime', 0)
                    message_count = monitoring.get('message_count', 0)
                    uptime_hours = uptime // 3600000 if uptime else 0
                    response_text += f"\n   가동시간: {uptime_hours}시간, 메시지: {message_count}개"
            
            await send_message_response(writer, room, response_text, channel_id)
            
        else:
            # 사용법 안내
            usage_text = """🔍 클라이언트 정보 명령어 사용법:

# client_info summary - 클라이언트 요약 정보 조회"""
            
            await send_message_response(writer, room, usage_text, channel_id)
        
        logger.info(f"[CLIENT_INFO] 명령어 처리 완료: {text}")
        
    except Exception as e:
        logger.error(f"[CLIENT_INFO] 명령어 처리 오류: {e}")
        writer = context.get("writer")
        if writer:
            await send_message_response(
                writer, 
                context.get("room", ""), 
                f"클라이언트 정보 조회 중 오류가 발생했습니다: {str(e)}", 
                context.get("channel_id", "")
            )