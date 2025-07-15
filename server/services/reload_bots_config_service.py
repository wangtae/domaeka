"""
봇 설정 재로드 서비스
DB에서 봇 승인 상태 및 설정을 다시 로드하여 메모리 캐시를 갱신
"""
from typing import Dict, Any
from core.logger import logger
import core.globals as g
from database.device_manager import get_device_approval_status


async def reload_bots_config(context: Dict[str, Any]) -> list:
    """
    봇 설정을 DB에서 다시 로드하여 메모리 캐시를 갱신
    
    Args:
        context: 메시지 컨텍스트
        
    Returns:
        list: 응답 메시지 리스트
    """
    try:
        logger.info("[RELOAD_BOTS_CONFIG] 봇 설정 갱신 시작")
        
        # 모든 연결된 클라이언트의 승인 상태 재조회
        updated_count = 0
        total_count = 0
        
        for client_key in list(g.clients.keys()):
            total_count += 1
            bot_name, device_id = client_key
            
            # DB에서 최신 승인 상태 조회
            is_approved, status = await get_device_approval_status(bot_name, device_id)
            
            # 캐시 업데이트
            old_status = g.client_approval_status.get(client_key, None)
            g.client_approval_status[client_key] = is_approved
            
            if old_status != is_approved:
                updated_count += 1
                logger.info(f"[RELOAD_BOTS_CONFIG] 봇 승인 상태 변경: {bot_name}@{device_id} - {old_status} → {is_approved}")
        
        # max_message_size도 함께 갱신
        if g.db_pool:
            async with g.db_pool.acquire() as conn:
                async with conn.cursor() as cursor:
                    # 현재 연결된 봇들의 설정 조회
                    bot_params = []
                    for bot_name, device_id in g.clients.keys():
                        bot_params.append((bot_name, device_id))
                    
                    if bot_params:
                        # IN 절을 위한 placeholder 생성
                        placeholders = ','.join(['(%s, %s)' for _ in bot_params])
                        sql = f"""
                        SELECT bot_name, device_id, max_message_size 
                        FROM kb_bot_devices 
                        WHERE (bot_name, device_id) IN ({placeholders})
                        """
                        
                        # 파라미터 평탄화
                        flat_params = []
                        for bot_name, device_id in bot_params:
                            flat_params.extend([bot_name, device_id])
                        
                        await cursor.execute(sql, flat_params)
                        results = await cursor.fetchall()
                        
                        for bot_name, device_id, max_message_size in results:
                            client_key = (bot_name, device_id)
                            if max_message_size:
                                old_size = g.client_max_message_sizes.get(client_key, g.MAX_MESSAGE_SIZE)
                                g.client_max_message_sizes[client_key] = max_message_size
                                if old_size != max_message_size:
                                    logger.info(f"[RELOAD_BOTS_CONFIG] 메시지 크기 제한 변경: {bot_name}@{device_id} - {old_size} → {max_message_size}")
        
        result_message = (
            f"✅ 봇 설정 갱신 완료!\n"
            f"• 총 {total_count}개 봇 확인\n"
            f"• {updated_count}개 봇 상태 변경됨"
        )
        
        logger.info(f"[RELOAD_BOTS_CONFIG] 봇 설정 갱신 완료 - 총 {total_count}개, 변경 {updated_count}개")
        
        return [result_message]
        
    except Exception as e:
        logger.error(f"[RELOAD_BOTS_CONFIG] 봇 설정 갱신 오류: {e}")
        return [f"❌ 봇 설정 갱신 실패: {str(e)}"]