"""
디바이스 관리 모듈 - kb_bot_devices 테이블 연동
"""
from typing import Dict, Any, Tuple
from datetime import datetime
from core.logger import logger
import core.globals as g


async def validate_and_register_device(handshake_data: Dict[str, Any], client_addr: str) -> Tuple[bool, str]:
    """
    핸드셰이크 정보를 kb_bot_devices 테이블과 연동하여 승인 상태 확인
    
    Args:
        handshake_data: 클라이언트 핸드셰이크 데이터
        client_addr: 클라이언트 주소
        
    Returns:
        (is_approved: bool, status_message: str)
    """
    if not g.db_pool:
        logger.warning("[DEVICE] DB 풀이 없어 승인 확인 건너뜀")
        return True, "DB 연결 없음 - 기본 승인"
    
    bot_name = handshake_data.get('botName', '')
    device_id = handshake_data.get('deviceID', '')
    client_type = handshake_data.get('clientType', 'MessengerBotR')  # 기본값
    version = handshake_data.get('version', '')
    device_ip = handshake_data.get('deviceIP', 'unknown')  # 기본값
    device_info = handshake_data.get('deviceInfo', '')  # 빈 문자열 허용
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                # 1. 기존 디바이스 조회
                select_sql = """
                SELECT id, status, client_version, ip_address 
                FROM kb_bot_devices 
                WHERE bot_name = %s AND device_id = %s
                """
                await cursor.execute(select_sql, (bot_name, device_id))
                existing_device = await cursor.fetchone()
                
                if existing_device:
                    # 기존 디바이스 업데이트
                    device_record_id, current_status, old_version, old_ip = existing_device
                    
                    # 정보 업데이트
                    update_sql = """
                    UPDATE kb_bot_devices 
                    SET ip_address = %s, client_version = %s, updated_at = %s
                    WHERE id = %s
                    """
                    await cursor.execute(update_sql, (device_ip, version, datetime.now(), device_record_id))
                    
                    logger.info(f"[DEVICE] 기존 디바이스 정보 업데이트: {bot_name}@{device_id} (상태: {current_status})")
                    
                    # 승인 상태 확인
                    if current_status == 'approved':
                        return True, "승인된 디바이스입니다"
                    elif current_status == 'pending':
                        return False, "승인 대기 중인 디바이스입니다"
                    elif current_status in ['denied', 'revoked', 'blocked']:
                        return False, f"접근이 차단된 디바이스입니다 ({current_status})"
                    else:
                        return False, f"알 수 없는 상태입니다 ({current_status})"
                        
                else:
                    # 새로운 디바이스 등록 (pending 상태)
                    insert_sql = """
                    INSERT INTO kb_bot_devices 
                    (bot_name, device_id, ip_address, status, client_type, client_version, client_info, descryption, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """
                    now = datetime.now()
                    await cursor.execute(insert_sql, (
                        bot_name, device_id, device_ip, 'pending', 
                        client_type, version, device_info, '', now, now
                    ))
                    
                    logger.info(f"[DEVICE] 새로운 디바이스 등록: {bot_name}@{device_id} (상태: pending)")
                    logger.info(f"[DEVICE] 디바이스 정보: {client_type} v{version}, IP: {device_ip}")
                    if device_info:
                        logger.info(f"[DEVICE] 기기 정보: {device_info}")
                    
                    return False, "디바이스가 승인 대기 상태로 등록되었습니다"
                    
    except Exception as e:
        logger.error(f"[DEVICE] 디바이스 검증 실패: {e}")
        return True, f"검증 실패 - 기본 승인 ({e})"


async def get_device_approval_status(bot_name: str, device_id: str) -> Tuple[bool, str]:
    """
    디바이스의 승인 상태를 조회
    
    Args:
        bot_name: 봇 이름
        device_id: 디바이스 ID
        
    Returns:
        (is_approved: bool, status: str)
    """
    if not g.db_pool:
        return True, "approved"  # DB 연결 없으면 기본 승인
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                SELECT status FROM kb_bot_devices 
                WHERE bot_name = %s AND device_id = %s
                """
                await cursor.execute(sql, (bot_name, device_id))
                result = await cursor.fetchone()
                
                if result:
                    status = result[0]
                    return status == 'approved', status
                else:
                    return False, "not_registered"
                    
    except Exception as e:
        logger.error(f"[DEVICE] 상태 조회 실패: {e}")
        return True, "error"


async def is_device_approved(bot_name: str, device_id: str) -> bool:
    """
    디바이스가 승인되었는지 간단히 확인
    
    Args:
        bot_name: 봇 이름
        device_id: 디바이스 ID
        
    Returns:
        bool: 승인 여부
    """
    is_approved, _ = await get_device_approval_status(bot_name, device_id)
    return is_approved


async def update_device_status(bot_name: str, device_id: str, new_status: str) -> bool:
    """
    디바이스 승인 상태 업데이트 (관리자용)
    
    Args:
        bot_name: 봇 이름
        device_id: 디바이스 ID
        new_status: 새로운 상태 (approved, denied, revoked, blocked)
        
    Returns:
        bool: 업데이트 성공 여부
    """
    if not g.db_pool:
        return False
    
    valid_statuses = ['approved', 'denied', 'revoked', 'blocked', 'pending']
    if new_status not in valid_statuses:
        logger.error(f"[DEVICE] 잘못된 상태값: {new_status}")
        return False
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                UPDATE kb_bot_devices 
                SET status = %s, updated_at = %s
                WHERE bot_name = %s AND device_id = %s
                """
                await cursor.execute(sql, (new_status, datetime.now(), bot_name, device_id))
                
                if cursor.rowcount > 0:
                    logger.info(f"[DEVICE] 상태 업데이트 성공: {bot_name}@{device_id} -> {new_status}")
                    return True
                else:
                    logger.warning(f"[DEVICE] 디바이스를 찾을 수 없음: {bot_name}@{device_id}")
                    return False
                    
    except Exception as e:
        logger.error(f"[DEVICE] 상태 업데이트 실패: {e}")
        return False


async def list_pending_devices():
    """
    승인 대기 중인 디바이스 목록 조회
    
    Returns:
        List[Dict]: 대기 중인 디바이스 목록
    """
    if not g.db_pool:
        return []
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                SELECT id, bot_name, device_id, ip_address, client_type, client_version, created_at
                FROM kb_bot_devices 
                WHERE status = 'pending'
                ORDER BY created_at DESC
                """
                await cursor.execute(sql)
                results = await cursor.fetchall()
                
                devices = []
                for row in results:
                    devices.append({
                        'id': row[0],
                        'bot_name': row[1],
                        'device_id': row[2],
                        'ip_address': row[3],
                        'client_type': row[4],
                        'client_version': row[5],
                        'created_at': row[6]
                    })
                
                return devices
                
    except Exception as e:
        logger.error(f"[DEVICE] 대기 목록 조회 실패: {e}")
        return []


async def get_all_devices():
    """
    모든 디바이스 목록 조회 (관리자용)
    
    Returns:
        List[Dict]: 모든 디바이스 목록
    """
    if not g.db_pool:
        return []
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                sql = """
                SELECT id, bot_name, device_id, ip_address, status, client_type, client_version, created_at, updated_at
                FROM kb_bot_devices 
                ORDER BY created_at DESC
                """
                await cursor.execute(sql)
                results = await cursor.fetchall()
                
                devices = []
                for row in results:
                    devices.append({
                        'id': row[0],
                        'bot_name': row[1],
                        'device_id': row[2],
                        'ip_address': row[3],
                        'status': row[4],
                        'client_type': row[5],
                        'client_version': row[6],
                        'created_at': row[7],
                        'updated_at': row[8]
                    })
                
                return devices
                
    except Exception as e:
        logger.error(f"[DEVICE] 전체 목록 조회 실패: {e}")
        return []