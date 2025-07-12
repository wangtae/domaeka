# core/llm_credit_manager.py

import time
import json
import asyncio
from datetime import datetime
from typing import Dict, Any, Optional, List, Union

import g  # 전역 변수 모듈

async def get_user_credit(channel_id: str, user_hash: str, username: Optional[str] = None) -> Dict[str, Any]:
    """사용자의 크레딧 정보를 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT credit_balance, lifetime_credits, lifetime_usage, username
                FROM kb_user_credits 
                WHERE channel_id = %s AND user_hash = %s
            """
            result = await conn.fetchone(query, (channel_id, user_hash))

            if not result:
                # 신규 사용자인 경우 기본 레코드 생성
                if not username:
                    # 사용자 이름이 제공되지 않았다면 DB에서 검색
                    name_query = "SELECT username FROM kb_users WHERE channel_id = %s AND user_hash = %s LIMIT 1"
                    name_result = await conn.fetchone(name_query, (channel_id, user_hash))
                    username = name_result['username'] if name_result else "Unknown"

                # 채널 설정 가져오기 및 무료 크레딧 확인
                from core.llm_usage_tracker import load_room_config
                room_config = await load_room_config(channel_id)
                free_credits = room_config.get('llm_settings', {}).get('user_calls', {}).get('free_credits_monthly', 0)

                # 신규 사용자 등록
                query = """
                    INSERT INTO kb_user_credits 
                    (channel_id, user_hash, username, credit_balance, lifetime_credits, lifetime_usage)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """
                await conn.execute(query, (channel_id, user_hash, username, free_credits, free_credits, 0))

                # 무료 크레딧 지급 기록
                if free_credits > 0:
                    await log_user_credit_transaction(
                        channel_id=channel_id,
                        user_hash=user_hash,
                        username=username,
                        amount=free_credits,
                        transaction_type='gift',
                        description='초기 무료 크레딧',
                        balance_after=free_credits
                    )

                return {
                    'credit_balance': free_credits,
                    'lifetime_credits': free_credits,
                    'lifetime_usage': 0,
                    'username': username
                }

            return {
                'credit_balance': float(result['credit_balance']),
                'lifetime_credits': float(result['lifetime_credits']),
                'lifetime_usage': float(result['lifetime_usage']),
                'username': result['username']
            }

    except Exception as e:
        g.logger.error(f"사용자 크레딧 조회 실패: {str(e)}")
        # 에러 발생 시 기본값 반환
        return {
            'credit_balance': 0,
            'lifetime_credits': 0,
            'lifetime_usage': 0,
            'username': username or "Unknown"
        }


async def get_channel_credit(channel_id: str) -> Dict[str, Any]:
    """채널의 크레딧 정보를 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT credit_balance, lifetime_credits, lifetime_usage, room_name, operation_mode
                FROM kb_channel_credits 
                WHERE channel_id = %s
            """
            result = await conn.fetchone(query, (channel_id,))

            if not result:
                # 채널 정보 가져오기
                from core.llm_usage_tracker import load_room_config
                room_config = await load_room_config(channel_id)
                room_name = room_config.get('room_name', 'Unknown')
                operation_mode = room_config.get('llm_settings', {}).get('operation_mode', 'free')

                # 신규 채널 등록
                query = """
                    INSERT INTO kb_channel_credits 
                    (channel_id, room_name, credit_balance, lifetime_credits, lifetime_usage, operation_mode)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """
                await conn.execute(query, (channel_id, room_name, 0, 0, 0, operation_mode))

                return {
                    'credit_balance': 0,
                    'lifetime_credits': 0,
                    'lifetime_usage': 0,
                    'room_name': room_name,
                    'operation_mode': operation_mode
                }

            return {
                'credit_balance': float(result['credit_balance']),
                'lifetime_credits': float(result['lifetime_credits']),
                'lifetime_usage': float(result['lifetime_usage']),
                'room_name': result['room_name'],
                'operation_mode': result['operation_mode']
            }

    except Exception as e:
        g.logger.error(f"채널 크레딧 조회 실패: {str(e)}")
        # 에러 발생 시 기본값 반환
        return {
            'credit_balance': 0,
            'lifetime_credits': 0,
            'lifetime_usage': 0,
            'room_name': 'Unknown',
            'operation_mode': 'free'
        }


async def add_user_credit(
        channel_id: str,
        user_hash: str,
        username: str,
        amount: float,
        description: str,
        admin_user_hash: Optional[str] = None,
        transaction_type: str = 'charge'
) -> float:
    """사용자 크레딧을 추가합니다."""
    if amount <= 0:
        raise ValueError("크레딧 추가 금액은 0보다 커야 합니다.")

    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                user_credit = await get_user_credit(channel_id, user_hash, username)

                # 새로운 잔액 계산
                new_balance = user_credit['credit_balance'] + amount
                new_lifetime = user_credit['lifetime_credits'] + amount

                # 크레딧 업데이트
                update_query = """
                    UPDATE kb_user_credits 
                    SET credit_balance = %s, lifetime_credits = %s, last_updated = NOW() 
                    WHERE channel_id = %s AND user_hash = %s
                """
                await conn.execute(update_query, (new_balance, new_lifetime, channel_id, user_hash))

                # 트랜잭션 기록
                await log_user_credit_transaction(
                    channel_id=channel_id,
                    user_hash=user_hash,
                    username=username,
                    amount=amount,
                    transaction_type=transaction_type,
                    description=description,
                    admin_user_hash=admin_user_hash,
                    balance_after=new_balance
                )

                g.logger.info(f"사용자 크레딧 추가: {channel_id}/{username}({user_hash}) +{amount} ({description})")

                return new_balance

    except Exception as e:
        g.logger.error(f"사용자 크레딧 추가 실패: {str(e)}")
        raise


async def deduct_user_credit(
        channel_id: str,
        user_hash: str,
        username: str,
        amount: float,
        description: str
) -> float:
    """사용자 크레딧을 차감합니다."""
    if amount <= 0:
        raise ValueError("크레딧 차감 금액은 0보다 커야 합니다.")

    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                user_credit = await get_user_credit(channel_id, user_hash, username)

                # 잔액 부족 확인
                if user_credit['credit_balance'] < amount:
                    raise ValueError(f"크레딧 잔액 부족: 현재 {user_credit['credit_balance']}, 필요 {amount}")

                # 새로운 잔액 계산
                new_balance = user_credit['credit_balance'] - amount
                new_usage = user_credit['lifetime_usage'] + amount

                # 크레딧 업데이트
                update_query = """
                    UPDATE kb_user_credits 
                    SET credit_balance = %s, lifetime_usage = %s, last_updated = NOW() 
                    WHERE channel_id = %s AND user_hash = %s
                """
                await conn.execute(update_query, (new_balance, new_usage, channel_id, user_hash))

                # 트랜잭션 기록
                await log_user_credit_transaction(
                    channel_id=channel_id,
                    user_hash=user_hash,
                    username=username,
                    amount=-amount,  # 음수로 기록
                    transaction_type='usage',
                    description=description,
                    balance_after=new_balance
                )

                g.logger.info(f"사용자 크레딧 차감: {channel_id}/{username}({user_hash}) -{amount} ({description})")

                return new_balance

    except Exception as e:
        g.logger.error(f"사용자 크레딧 차감 실패: {str(e)}")
        raise


async def add_channel_credit(
        channel_id: str,
        amount: float,
        description: str,
        admin_user_hash: Optional[str] = None,
        transaction_type: str = 'charge'
) -> float:
    """채널 크레딧을 추가합니다."""
    if amount <= 0:
        raise ValueError("크레딧 추가 금액은 0보다 커야 합니다.")

    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                channel_credit = await get_channel_credit(channel_id)

                # 새로운 잔액 계산
                new_balance = channel_credit['credit_balance'] + amount
                new_lifetime = channel_credit['lifetime_credits'] + amount

                # 크레딧 업데이트
                update_query = """
                    UPDATE kb_channel_credits 
                    SET credit_balance = %s, lifetime_credits = %s, last_updated = NOW() 
                    WHERE channel_id = %s
                """
                await conn.execute(update_query, (new_balance, new_lifetime, channel_id))

                # 트랜잭션 기록
                await log_channel_credit_transaction(
                    channel_id=channel_id,
                    amount=amount,
                    transaction_type=transaction_type,
                    description=description,
                    admin_user_hash=admin_user_hash,
                    balance_after=new_balance
                )

                g.logger.info(f"채널 크레딧 추가: {channel_id}/{channel_credit['room_name']} +{amount} ({description})")

                return new_balance

    except Exception as e:
        g.logger.error(f"채널 크레딧 추가 실패: {str(e)}")
        raise


async def deduct_channel_credit(
        channel_id: str,
        amount: float,
        description: str
) -> float:
    """채널 크레딧을 차감합니다."""
    if amount <= 0:
        raise ValueError("크레딧 차감 금액은 0보다 커야 합니다.")

    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                channel_credit = await get_channel_credit(channel_id)

                # 잔액 부족 확인
                if channel_credit['credit_balance'] < amount:
                    raise ValueError(f"채널 크레딧 잔액 부족: 현재 {channel_credit['credit_balance']}, 필요 {amount}")

                # 새로운 잔액 계산
                new_balance = channel_credit['credit_balance'] - amount
                new_usage = channel_credit['lifetime_usage'] + amount

                # 크레딧 업데이트
                update_query = """
                    UPDATE kb_channel_credits 
                    SET credit_balance = %s, lifetime_usage = %s, last_updated = NOW() 
                    WHERE channel_id = %s
                """
                await conn.execute(update_query, (new_balance, new_usage, channel_id))

                # 트랜잭션 기록
                await log_channel_credit_transaction(
                    channel_id=channel_id,
                    amount=-amount,  # 음수로 기록
                    transaction_type='usage',
                    description=description,
                    balance_after=new_balance
                )

                g.logger.info(f"채널 크레딧 차감: {channel_id}/{channel_credit['room_name']} -{amount} ({description})")

                return new_balance

    except Exception as e:
        g.logger.error(f"채널 크레딧 차감 실패: {str(e)}")
        raise


async def log_user_credit_transaction(
        channel_id: str,
        user_hash: str,
        username: str,
        amount: float,
        transaction_type: str,
        description: str,
        balance_after: float,
        admin_user_hash: Optional[str] = None,
        reference_id: Optional[str] = None
) -> None:
    """사용자 크레딧 트랜잭션을 기록합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                INSERT INTO kb_user_credit_transactions (
                    channel_id, user_hash, username, transaction_type, amount,
                    balance_after, description, admin_user_hash, reference_id
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """

            await conn.execute(query, (
                channel_id, user_hash, username, transaction_type, amount,
                balance_after, description, admin_user_hash, reference_id
            ))

    except Exception as e:
        g.logger.error(f"사용자 크레딧 트랜잭션 로그 실패: {str(e)}")


async def log_channel_credit_transaction(
        channel_id: str,
        amount: float,
        transaction_type: str,
        description: str,
        balance_after: float,
        admin_user_hash: Optional[str] = None,
        reference_id: Optional[str] = None
) -> None:
    """채널 크레딧 트랜잭션을 기록합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                INSERT INTO kb_channel_credit_transactions (
                    channel_id, transaction_type, amount,
                    balance_after, description, admin_user_hash, reference_id
                ) VALUES (%s, %s, %s, %s, %s, %s, %s)
            """

            await conn.execute(query, (
                channel_id, transaction_type, amount,
                balance_after, description, admin_user_hash, reference_id
            ))

    except Exception as e:
        g.logger.error(f"채널 크레딧 트랜잭션 로그 실패: {str(e)}")


async def get_user_credit_history(
        channel_id: str,
        user_hash: str,
        limit: int = 10,
        offset: int = 0
) -> List[Dict[str, Any]]:
    """사용자의 크레딧 거래 내역을 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT 
                    id, timestamp, transaction_type, amount, 
                    balance_after, description, admin_user_hash, reference_id
                FROM kb_user_credit_transactions
                WHERE channel_id = %s AND user_hash = %s
                ORDER BY timestamp DESC
                LIMIT %s OFFSET %s
            """

            result = await conn.fetch(query, (channel_id, user_hash, limit, offset))

            return [dict(row) for row in result]

    except Exception as e:
        g.logger.error(f"사용자 크레딧 내역 조회 실패: {str(e)}")
        return []


async def get_channel_credit_history(
        channel_id: str,
        limit: int = 10,
        offset: int = 0
) -> List[Dict[str, Any]]:
    """채널의 크레딧 거래 내역을 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT 
                    id, timestamp, transaction_type, amount, 
                    balance_after, description, admin_user_hash, reference_id
                FROM kb_channel_credit_transactions
                WHERE channel_id = %s
                ORDER BY timestamp DESC
                LIMIT %s OFFSET %s
            """

            result = await conn.fetch(query, (channel_id, limit, offset))

            return [dict(row) for row in result]

    except Exception as e:
        g.logger.error(f"채널 크레딧 내역 조회 실패: {str(e)}")
        return []


async def get_admin_username(channel_id: str, admin_user_hash: str) -> str:
    """관리자 사용자의 이름을 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT username FROM kb_users
                WHERE channel_id = %s AND user_hash = %s
                LIMIT 1
            """

            result = await conn.fetchone(query, (channel_id, admin_user_hash))
            return result['username'] if result else "알 수 없는 관리자"

    except Exception as e:
        g.logger.error(f"관리자 이름 조회 실패: {str(e)}")
        return "알 수 없는 관리자"


async def charge_credit_for_llm_usage(
        context: Dict[str, Any],
        model: str,
        cost: float,
        description: str
) -> Dict[str, Any]:
    """LLM 사용에 대한 크레딧을 차감합니다."""
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')
    username = context.get('sender')
    caller_type = context.get('caller_type', 'user')

    # 채널 설정 가져오기
    from core.llm_usage_tracker import load_room_config
    room_config = await load_room_config(channel_id)
    llm_settings = room_config.get('llm_settings', {})

    operation_mode = llm_settings.get('operation_mode', 'free')

    # 무료 모드는 차감하지 않음
    if operation_mode == 'free':
        return {'success': True, 'message': '무료 모드'}

    # 관리자 확인
    if caller_type == 'user':
        from core.llm_usage_tracker import check_if_admin
        is_admin = await check_if_admin(channel_id, user_hash)

        # 관리자 무료 사용 설정
        admin_free_usage = llm_settings.get('user_calls', {}).get('admin_free_usage', True)
        if is_admin and admin_free_usage:
            return {'success': True, 'message': '관리자 무료 사용'}

    # 시스템 호출의 경우
    if caller_type == 'system':
        system_settings = llm_settings.get('system_calls', {})

        # 시스템 호출 비용 차감 설정
        deduct_from_channel = system_settings.get('deduct_from_channel', False)

        if deduct_from_channel:
            try:
                await deduct_channel_credit(
                    channel_id=channel_id,
                    amount=cost,
                    description=f"시스템 LLM 사용: {description}"
                )
                return {'success': True, 'source': 'channel', 'amount': cost}
            except ValueError as e:
                g.logger.warning(f"채널 크레딧 차감 실패: {str(e)}")
                return {'success': False, 'message': str(e)}
        else:
            return {'success': True, 'message': '시스템 호출 비용 차감 비활성화'}

    # 사용자 호출의 경우
    elif caller_type == 'user':
        user_settings = llm_settings.get('user_calls', {})

        # 접두어 및 특정 사용자 무료 사용 체크는 이미 check_usage_limits에서 수행

        # 채널 크레딧에서 차감
        deduct_from_channel = user_settings.get('deduct_from_channel', False)
        if deduct_from_channel:
            try:
                await deduct_channel_credit(
                    channel_id=channel_id,
                    amount=cost,
                    description=f"사용자 LLM 사용: {username}({user_hash}) - {description}"
                )
                return {'success': True, 'source': 'channel', 'amount': cost}
            except ValueError as e:
                # 채널 크레딧 부족, 사용자 크레딧으로 전환 시도
                g.logger.warning(f"채널 크레딧 차감 실패, 사용자 크레딧 시도: {str(e)}")
                fallback_to_user = user_settings.get('fallback_to_user', False)
                if not fallback_to_user:
                    return {'success': False, 'message': '채널 크레딧 부족'}

        # 사용자 크레딧에서 차감
        deduct_from_user = user_settings.get('deduct_from_user', False) or (deduct_from_channel and user_settings.get('fallback_to_user', False))
        if deduct_from_user:
            try:
                await deduct_user_credit(
                    channel_id=channel_id,
                    user_hash=user_hash,
                    username=username,
                    amount=cost,
                    description=f"LLM 사용: {model} - {description}"
                )
                return {'success': True, 'source': 'user', 'amount': cost}
            except ValueError as e:
                g.logger.warning(f"사용자 크레딧 차감 실패: {str(e)}")
                return {'success': False, 'message': '사용자 크레딧 부족'}

        # 채널, 사용자 크레딧 모두 비활성화된 경우
        return {'success': True, 'message': '크레딧 차감 설정 비활성화'}

    # 알 수 없는 호출 유형
    return {'success': False, 'message': f'알 수 없는 호출 유형: {caller_type}'}


# 정기적인 무료 크레딧 지급 스케줄러
async def free_credits_scheduler():
    """월별 무료 크레딧을 지급하는 스케줄러 함수입니다."""
    while True:
        try:
            # 현재 날짜 확인, 매월 1일에만 실행
            now = datetime.now()
            if now.day != 1:
                # 다음 실행까지 대기 (다음 날 오전 1시)
                next_day = now.replace(day=now.day+1, hour=1, minute=0, second=0, microsecond=0)
                if next_day.day == 1:  # 다음 날이 1일이면 실행
                    wait_seconds = (next_day - now).total_seconds()
                    await asyncio.sleep(wait_seconds)
                    continue
                else:  # 아니면 하루 후에 다시 체크
                    await asyncio.sleep(24 * 60 * 60)
                    continue

            g.logger.info("월별 무료 크레딧 지급 작업 시작")

            # 모든 채널 설정 가져오기
            from core.llm_usage_tracker import load_room_config
            with open(g.SCHEDULE_ROOMS_PATH, 'r', encoding='utf-8') as f:
                all_config = json.load(f)

            # 각 봇/채널별로 처리
            for bot_name, channels in all_config.items():
                for channel_id, config in channels.items():
                    llm_settings = config.get('llm_settings', {})
                    free_credits_monthly = llm_settings.get('user_calls', {}).get('free_credits_monthly', 0)

                    if free_credits_monthly <= 0:
                        continue  # 무료 크레딧 없으면 스킵

                    # 해당 채널의 모든 사용자 가져오기
                    async with g.db_pool.acquire() as conn:
                        query = """
                            SELECT DISTINCT user_hash, username 
                            FROM kb_users 
                            WHERE channel_id = %s
                        """
                        users = await conn.fetch(query, (channel_id,))

                        for user in users:
                            user_hash = user['user_hash']
                            username = user['username']

                            # 무료 크레딧 지급
                            try:
                                await add_user_credit(
                                    channel_id=channel_id,
                                    user_hash=user_hash,
                                    username=username,
                                    amount=free_credits_monthly,
                                    description=f"{now.year}년 {now.month}월 무료 크레딧",
                                    transaction_type='gift'
                                )
                                g.logger.info(f"무료 크레딧 지급 완료: {channel_id}/{username} +{free_credits_monthly}")
                            except Exception as e:
                                g.logger.error(f"무료 크레딧 지급 실패: {channel_id}/{username} - {str(e)}")

            g.logger.info("월별 무료 크레딧 지급 작업 완료")

            # 다음 달 1일까지 대기
            next_month = now.replace(month=now.month+1 if now.month < 12 else 1,
                                     year=now.year if now.month < 12 else now.year+1,
                                     day=1, hour=1, minute=0, second=0, microsecond=0)
            wait_seconds = (next_month - now).total_seconds()
            await asyncio.sleep(wait_seconds)

        except Exception as e:
            g.logger.error(f"무료 크레딧 스케줄러 오류: {str(e)}")
            # 오류 발생 시 1시간 후 재시도
            await asyncio.sleep(60 * 60)

            (channel_id, user_hash, username, credit_balance, lifetime_credits, lifetime_usage)
            VALUES (%s, %s, %s, %s, %s, %s)
        """
        await conn.execute(query, (channel_id, user_hash, username, free_credits, free_credits, 0))
        
        # 무료 크레딧 지급 기록
        if free_credits > 0:
            await log_user_credit_transaction(
                channel_id=channel_id,
                user_hash=user_hash,
                username=username,
                amount=free_credits,
                transaction_type='gift',
                description='초기 무료 크레딧',
                balance_after=free_credits
            )
        
        return {
            'credit_balance': free_credits,
            'lifetime_credits': free_credits,
            'lifetime_usage': 0,
            'username': username
        }
    
    return {
        'credit_balance': float(result['credit_balance']),
        'lifetime_credits': float(result['lifetime_credits']),
        'lifetime_usage': float(result['lifetime_usage']),
        'username': result['username']
    }

except Exception as e:
g.logger.error(f"사용자 크레딧 조회 실패: {str(e)}")
# 에러 발생 시 기본값 반환
return {
    'credit_balance': 0,
    'lifetime_credits': 0,
    'lifetime_usage': 0,
    'username': username or "Unknown"
}


async def get_channel_credit(channel_id: str) -> Dict[str, Any]:
"""채널의 크레딧 정보를 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
        SELECT credit_balance, lifetime_credits, lifetime_usage, room_name, operation_mode
        FROM kb_channel_credits
        WHERE channel_id = %s
    """
    result = await conn.fetchone(query, (channel_id,))
    
    if not result:
        # 채널 정보 가져오기
        from core.llm_usage_tracker import load_room_config
        room_config = await load_room_config(channel_id)
        room_name = room_config.get('room_name', 'Unknown')
        operation_mode = room_config.get('llm_settings', {}).get('operation_mode', 'free')
        
        # 신규 채널 등록
        query = """
    INSERT INTO kb_channel_credits
    (channel_id, room_name, credit_balance, lifetime_credits, lifetime_usage, operation_mode)
    VALUES (%s, %s, %s, %s, %s, %s)
"""
await conn.execute(query, (channel_id, room_name, 0, 0, 0, operation_mode))

return {
    'credit_balance': 0,
    'lifetime_credits': 0,
    'lifetime_usage': 0,
    'room_name': room_name,
    'operation_mode': operation_mode
}

return {
'credit_balance': float(result['credit_balance']),
'lifetime_credits': float(result['lifetime_credits']),
'lifetime_usage': float(result['lifetime_usage']),
'room_name': result['room_name'],
'operation_mode': result['operation_mode']
}

except Exception as e:
g.logger.error(f"채널 크레딧 조회 실패: {str(e)}")
# 에러 발생 시 기본값 반환
return {
'credit_balance': 0,
'lifetime_credits': 0,
'lifetime_usage': 0,
'room_name': 'Unknown',
'operation_mode': 'free'
}


async def add_user_credit(
channel_id: str, 
user_hash: str, 
username: str, 
amount: float, 
description: str, 
admin_user_hash: Optional[str] = None,
transaction_type: str = 'charge'
) -> float:
"""사용자 크레딧을 추가합니다."""
    if amount <= 0:
        raise ValueError("크레딧 추가 금액은 0보다 커야 합니다.")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                user_credit = await get_user_credit(channel_id, user_hash, username)
                
                # 새로운 잔액 계산
                new_balance = user_credit['credit_balance'] + amount
                new_lifetime = user_credit['lifetime_credits'] + amount
                
                # 크레딧 업데이트
                update_query = """
UPDATE kb_user_credits
SET credit_balance = %s, lifetime_credits = %s, last_updated = NOW()
WHERE channel_id = %s AND user_hash = %s
"""
await conn.execute(update_query, (new_balance, new_lifetime, channel_id, user_hash))

# 트랜잭션 기록
await log_user_credit_transaction(
    channel_id=channel_id,
    user_hash=user_hash,
    username=username,
    amount=amount,
    transaction_type=transaction_type,
    description=description,
    admin_user_hash=admin_user_hash,
    balance_after=new_balance
)

g.logger.info(f"사용자 크레딧 추가: {channel_id}/{username}({user_hash}) +{amount} ({description})")

return new_balance

except Exception as e:
g.logger.error(f"사용자 크레딧 추가 실패: {str(e)}")
raise


async def deduct_user_credit(
channel_id: str, 
user_hash: str, 
username: str, 
amount: float, 
description: str
) -> float:
"""사용자 크레딧을 차감합니다."""
    if amount <= 0:
        raise ValueError("크레딧 차감 금액은 0보다 커야 합니다.")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                user_credit = await get_user_credit(channel_id, user_hash, username)
                
                # 잔액 부족 확인
                if user_credit['credit_balance'] < amount:
                    raise ValueError(f"크레딧 잔액 부족: 현재 {user_credit['credit_balance']}, 필요 {amount}")
                
                # 새로운 잔액 계산
                new_balance = user_credit['credit_balance'] - amount
                new_usage = user_credit['lifetime_usage'] + amount
                
                # 크레딧 업데이트
                update_query = """
UPDATE kb_user_credits
SET credit_balance = %s, lifetime_usage = %s, last_updated = NOW()
WHERE channel_id = %s AND user_hash = %s
"""
await conn.execute(update_query, (new_balance, new_usage, channel_id, user_hash))

# 트랜잭션 기록
await log_user_credit_transaction(
    channel_id=channel_id,
    user_hash=user_hash,
    username=username,
    amount=-amount,  # 음수로 기록
    transaction_type='usage',
    description=description,
    balance_after=new_balance
)

g.logger.info(f"사용자 크레딧 차감: {channel_id}/{username}({user_hash}) -{amount} ({description})")

return new_balance

except Exception as e:
g.logger.error(f"사용자 크레딧 차감 실패: {str(e)}")
raise


async def add_channel_credit(
channel_id: str, 
amount: float, 
description: str, 
admin_user_hash: Optional[str] = None,
transaction_type: str = 'charge'
) -> float:
"""채널 크레딧을 추가합니다."""
    if amount <= 0:
        raise ValueError("크레딧 추가 금액은 0보다 커야 합니다.")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                channel_credit = await get_channel_credit(channel_id)
                
                # 새로운 잔액 계산
                new_balance = channel_credit['credit_balance'] + amount
                new_lifetime = channel_credit['lifetime_credits'] + amount
                
                # 크레딧 업데이트
                update_query = """
UPDATE kb_channel_credits
SET credit_balance = %s, lifetime_credits = %s, last_updated = NOW()
WHERE channel_id = %s
"""
await conn.execute(update_query, (new_balance, new_lifetime, channel_id))

# 트랜잭션 기록
await log_channel_credit_transaction(
    channel_id=channel_id,
    amount=amount,
    transaction_type=transaction_type,
    description=description,
    admin_user_hash=admin_user_hash,
    balance_after=new_balance
)

g.logger.info(f"채널 크레딧 추가: {channel_id}/{channel_credit['room_name']} +{amount} ({description})")

return new_balance

except Exception as e:
g.logger.error(f"채널 크레딧 추가 실패: {str(e)}")
raise


async def deduct_channel_credit(
channel_id: str, 
amount: float, 
description: str
) -> float:
"""채널 크레딧을 차감합니다."""
    if amount <= 0:
        raise ValueError("크레딧 차감 금액은 0보다 커야 합니다.")
    
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.transaction():
                # 현재 크레딧 확인
                channel_credit = await get_channel_credit(channel_id)
                
                # 잔액 부족 확인
                if channel_credit['credit_balance'] < amount:
                    raise ValueError(f"채널 크레딧 잔액 부족: 현재 {channel_credit['credit_balance']}, 필요 {amount}")
                
                # 새로운 잔액 계산
                new_balance = channel_credit['credit_balance'] - amount
                new_usage = channel_credit['lifetime_usage'] + amount
                
                # 크레딧 업데이트
                update_query = """
UPDATE kb_channel_credits
SET credit_balance = %s, lifetime_usage = %s, last_updated = NOW()
WHERE channel_id = %s
"""
await conn.execute(update_query, (new_balance, new_usage, channel_id))

# 트랜잭션 기록
await log_channel_credit_transaction(
    channel_id=channel_id,
    amount=-amount,  # 음수로 기록
    transaction_type='usage',
    description=description,
    balance_after=new_balance
)

g.logger.info(f"채널 크레딧 차감: {channel_id}/{channel_credit['room_name']} -{amount} ({description})")

return new_balance

except Exception as e:
g.logger.error(f"채널 크레딧 차감 실패: {str(e)}")
raise


async def log_user_credit_transaction(
channel_id: str,
user_hash: str,
username: str,
amount: float,
transaction_type: str,
description: str,
balance_after: float,
admin_user_hash: Optional[str] = None,
reference_id: Optional[str] = None
) -> None:
"""사용자 크레딧 트랜잭션을 기록합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
INSERT INTO kb_user_credit_transactions (
    channel_id, user_hash, username, transaction_type, amount,
    balance_after, description, admin_user_hash, reference_id
) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
"""

await conn.execute(query, (
    channel_id, user_hash, username, transaction_type, amount,
    balance_after, description, admin_user_hash, reference_id
))

except Exception as e:
g.logger.error(f"사용자 크레딧 트랜잭션 로그 실패: {str(e)}")


async def log_channel_credit_transaction(
channel_id: str,
amount: float,
transaction_type: str,
description: str,
balance_after: float,
admin_user_hash: Optional[str] = None,
reference_id: Optional[str] = None
) -> None:
"""채널 크레딧 트랜잭션을 기록합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
INSERT INTO kb_channel_credit_transactions (
    channel_id, transaction_type, amount,
    balance_after, description, admin_user_hash, reference_id
) VALUES (%s, %s, %s, %s, %s, %s, %s)
"""

await conn.execute(query, (
    channel_id, transaction_type, amount,
    balance_after, description, admin_user_hash, reference_id
))

except Exception as e:
g.logger.error(f"채널 크레딧 트랜잭션 로그 실패: {str(e)}")


async def get_user_credit_history(
channel_id: str, 
user_hash: str, 
limit: int = 10, 
offset: int = 0
) -> List[Dict[str, Any]]:
"""사용자의 크레딧 거래 내역을 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
SELECT
id, timestamp, transaction_type, amount,
balance_after, description, admin_user_hash, reference_id
FROM kb_user_credit_transactions
WHERE channel_id = %s AND user_hash = %s
ORDER BY timestamp DESC
LIMIT %s OFFSET %s
"""

result = await conn.fetch(query, (channel_id, user_hash, limit, offset))

return [dict(row) for row in result]

except Exception as e:
g.logger.error(f"사용자 크레딧 내역 조회 실패: {str(e)}")
return []


async def get_channel_credit_history(
channel_id: str, 
limit: int = 10, 
offset: int = 0
) -> List[Dict[str, Any]]:
"""채널의 크레딧 거래 내역을 조회합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            query = """
SELECT
id, timestamp, transaction_type, amount,
balance_after, description, admin_user_hash, reference_id
FROM kb_channel_credit_transactions
WHERE channel_id = %s
ORDER BY timestamp DESC
LIMIT %s OFFSET %s
"""

result = await conn.fetch(query, (channel_id, limit, offset))

return [dict(row) for row in result]

except Exception as e:
g.logger.error(f"채널 크레딧 내역 조회 실패: {str(