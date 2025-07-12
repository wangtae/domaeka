# ===== # services/credit_service.py

from typing import Dict, Any, Optional, List, Union
from datetime import datetime, timedelta
import g  # 전역 변수 모듈
from core.llm_credit_manager import (
    get_user_credit,
    get_channel_credit,
    add_user_credit,
    deduct_user_credit,
    add_channel_credit,
    deduct_channel_credit,
    get_user_credit_history,
    get_channel_credit_history,
    get_admin_username
)
from core.llm_usage_tracker import (
    load_room_config,
    get_monthly_usage,
    get_lifetime_usage,
    check_if_admin
)


async def handle_credit_command(context: Dict[str, Any], prompt: str) -> str:
    """
    크레딧 관련 명령어를 처리합니다.

    Args:
        context: 메시지 컨텍스트 정보
        prompt: 명령어 프롬프트

    Returns:
        처리 결과 메시지
    """
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')
    username = context.get('sender')

    # 프롬프트 처리
    parts = prompt.strip().split()
    subcommand = parts[0].lower() if parts else ""

    # 기본 명령어 - 잔액 확인
    if not subcommand or subcommand in ['확인', '조회', '잔액', 'balance']:
        return await cmd_check_balance(channel_id, user_hash, username)

    # 통계 명령어 - 상세 사용량
    elif subcommand in ['통계', '내역', 'stats', 'history']:
        return await cmd_check_stats(channel_id, user_hash, username)

    # 크레딧 충전 명령어 (관리자 전용)
    elif subcommand in ['충전', '추가', 'charge', 'add'] and len(parts) >= 3:
        is_admin = await check_if_admin(channel_id, user_hash)
        if not is_admin:
            return "❌ 관리자만 크레딧 충전이 가능합니다."

        # 타겟 사용자와 금액 파싱
        target_user = parts[1]
        try:
            amount = float(parts[2])
            if amount <= 0:
                return "❌ 충전 금액은 0보다 커야 합니다."
        except ValueError:
            return "❌ 유효한 충전 금액을 입력해주세요."

        return await cmd_charge_credit(channel_id, user_hash, username, target_user, amount)

    # 크레딧 내역 조회
    elif subcommand in ['내역', 'transactions', 'history', 'tx']:
        limit = 5  # 기본 조회 개수
        try:
            if len(parts) > 1:
                limit = min(int(parts[1]), 10)  # 최대 10개까지만 허용
        except ValueError:
            pass

        return await cmd_credit_history(channel_id, user_hash, username, limit)

    # 도움말
    elif subcommand in ['도움말', '사용법', 'help']:
        return await cmd_credit_help()

    # 알 수 없는 하위 명령어
    else:
        return (
            "❌ 알 수 없는 명령어입니다. 사용 가능한 명령어:\n"
            "• # 크레딧 - 잔액 확인\n"
            "• # 크레딧 통계 - 사용량 통계\n"
            "• # 크레딧 내역 [개수] - 거래 내역 조회\n"
            "• # 크레딧 충전 [사용자] [금액] - 관리자 전용\n"
            "• # 크레딧 도움말 - 도움말 표시"
        )


async def handle_channel_credit_command(context: Dict[str, Any], prompt: str) -> str:
    """
    채널 크레딧 관련 명령어를 처리합니다. (관리자 전용)

    Args:
        context: 메시지 컨텍스트 정보
        prompt: 명령어 프롬프트

    Returns:
        처리 결과 메시지
    """
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')
    username = context.get('sender')

    # 관리자 권한 확인
    is_admin = await check_if_admin(channel_id, user_hash)
    if not is_admin:
        return "❌ 관리자만 채널 크레딧을 관리할 수 있습니다."

    # 프롬프트 처리
    parts = prompt.strip().split()
    subcommand = parts[0].lower() if parts else ""

    # 기본 명령어 - 채널 크레딧 확인
    if not subcommand or subcommand in ['확인', '조회', '정보', 'info']:
        return await cmd_check_channel_balance(channel_id)

    # 크레딧 충전 명령어
    elif subcommand in ['충전', '추가', 'charge', 'add'] and len(parts) >= 2:
        try:
            amount = float(parts[1])
            if amount <= 0:
                return "❌ 충전 금액은 0보다 커야 합니다."
        except ValueError:
            return "❌ 유효한 충전 금액을 입력해주세요."

        return await cmd_charge_channel_credit(channel_id, user_hash, amount)

    # 설정 변경 명령어
    elif subcommand in ['설정', 'set', 'config'] and len(parts) >= 3:
        key = parts[1].lower()
        value = parts[2].lower()

        # 조건 추가 필요: 설정 가능한 키 제한 등
        return await cmd_set_channel_config(channel_id, user_hash, key, value)

    # 크레딧 내역 조회
    elif subcommand in ['내역', 'transactions', 'history', 'tx']:
        limit = 5  # 기본 조회 개수
        try:
            if len(parts) > 1:
                limit = min(int(parts[1]), 10)  # 최대 10개까지만 허용
        except ValueError:
            pass

        return await cmd_channel_credit_history(channel_id, limit)

    # 도움말
    elif subcommand in ['도움말', '사용법', 'help']:
        return await cmd_channel_credit_help()

    # 알 수 없는 하위 명령어
    else:
        return (
            "❌ 알 수 없는 명령어입니다. 사용 가능한 명령어:\n"
            "• # 방크레딧 - 채널 정보 확인\n"
            "• # 방크레딧 충전 [금액] - 크레딧 충전\n"
            "• # 방크레딧 설정 [키] [값] - 설정 변경\n"
            "• # 방크레딧 내역 [개수] - 거래 내역 조회\n"
            "• # 방크레딧 도움말 - 도움말 표시"
        )


# ===== 사용자 크레딧 명령어 처리 함수 =====

async def cmd_check_balance(channel_id: str, user_hash: str, username: str) -> str:
    """사용자 크레딧 잔액을 확인합니다."""
    try:
        # 사용자 크레딧 정보 조회
        credit_info = await get_user_credit(channel_id, user_hash, username)
        monthly_usage = await get_monthly_usage(channel_id, user_hash)

        message = [
            f"🪙 {username}님의 크레딧 정보",
            f"현재 잔액: {credit_info['credit_balance']:.2f} 크레딧",
            f"이번달 사용량: {monthly_usage['total_cost']:.2f} 크레딧 ({monthly_usage['total_tokens']:,} 토큰)"
        ]

        # 채널 크레딧 정보 추가 (관리자의 경우)
        is_admin = await check_if_admin(channel_id, user_hash)
        if is_admin:
            channel_credit = await get_channel_credit(channel_id)
            message.append("")
            message.append(f"📢 채널 크레딧 정보 (관리자 전용)")
            message.append(f"채널 잔액: {channel_credit['credit_balance']:.2f} 크레딧")
            message.append(f"채널 모드: {get_operation_mode_text(channel_credit['operation_mode'])}")

        return "\n".join(message)

    except Exception as e:
        g.logger.error(f"크레딧 잔액 확인 실패: {str(e)}")
        return f"❌ 크레딧 정보 조회 중 오류가 발생했습니다: {str(e)}"


async def cmd_check_stats(channel_id: str, user_hash: str, username: str) -> str:
    """사용자 크레딧 상세 통계를 확인합니다."""
    try:
        # 사용자 크레딧 정보 조회
        credit_info = await get_user_credit(channel_id, user_hash, username)
        monthly_usage = await get_monthly_usage(channel_id, user_hash)
        lifetime_usage = await get_lifetime_usage(channel_id, user_hash)

        # 설정 가져오기
        room_config = await load_room_config(channel_id)
        llm_settings = room_config.get('llm_settings', {})
        operation_mode = llm_settings.get('operation_mode', 'free')

        message = [
            f"📊 {username}님의 LLM 사용 통계",
            f"현재 잔액: {credit_info['credit_balance']:.2f} 크레딧",
            f"총 충전 크레딧: {credit_info['lifetime_credits']:.2f}",
            f"총 사용 크레딧: {credit_info['lifetime_usage']:.2f}",
            ""
        ]

        # 채널 운영 모드에 따라 다른 메시지
        if operation_mode == 'free':
            message.append("🔹 현재 이 채널은 무료 모드로 운영 중입니다.")
        elif operation_mode == 'paid':
            message.append("🔹 현재 이 채널은 유료 모드로 운영 중입니다.")
        elif operation_mode == 'mixed':
            message.append("🔹 현재 이 채널은 혼합 모드로 운영 중입니다.")

            # 무료 사용 규칙 확인
            free_usage_rules = llm_settings.get('free_usage_rules', {})
            daily_free_quota = free_usage_rules.get('daily_free_quota', 0)
            monthly_free_quota = free_usage_rules.get('monthly_free_quota', 0)
            free_prefixes = free_usage_rules.get('free_prefixes', [])

            if daily_free_quota > 0:
                message.append(f"- 일일 무료 사용 횟수: {daily_free_quota}회")
            if monthly_free_quota > 0:
                message.append(f"- 월간 무료 사용 횟수: {monthly_free_quota}회")
            if free_prefixes:
                message.append(f"- 무료 접두어: {', '.join(free_prefixes)}")

        message.append("")
        message.append("🔸 이번달 사용량:")
        message.append(f"- 총 토큰: {monthly_usage['total_tokens']:,}개")
        message.append(f"- 프롬프트 토큰: {monthly_usage['total_prompt_tokens']:,}개")
        message.append(f"- 응답 토큰: {monthly_usage['total_completion_tokens']:,}개")
        message.append(f"- 비용: {monthly_usage['total_cost']:.2f} 크레딧")

        message.append("")
        message.append("🔸 전체 사용량:")
        message.append(f"- 총 토큰: {lifetime_usage['total_tokens']:,}개")
        message.append(f"- 프롬프트 토큰: {lifetime_usage['total_prompt_tokens']:,}개")
        message.append(f"- 응답 토큰: {lifetime_usage['total_completion_tokens']:,}개")
        message.append(f"- 비용: {lifetime_usage['total_cost']:.2f} 크레딧")

        return "\n".join(message)

    except Exception as e:
        g.logger.error(f"크레딧 통계 확인 실패: {str(e)}")
        return f"❌ 크레딧 통계 조회 중 오류가 발생했습니다: {str(e)}"


async def cmd_charge_credit(channel_id: str, admin_user_hash: str, admin_username: str, target_user: str,
                            amount: float) -> str:
    """다른 사용자의 크레딧을 충전합니다. (관리자 전용)"""
    try:
        # 사용자 해시 찾기
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT user_hash, username FROM kb_users
                WHERE channel_id = %s AND username = %s
                ORDER BY last_active DESC
                LIMIT 1
            """
            result = await conn.fetchone(query, (channel_id, target_user))

            if not result:
                return f"❌ 사용자 '{target_user}'를 찾을 수 없습니다."

            target_user_hash = result['user_hash']
            target_username = result['username']

        # 크레딧 추가
        new_balance = await add_user_credit(
            channel_id=channel_id,
            user_hash=target_user_hash,
            username=target_username,
            amount=amount,
            description=f"관리자({admin_username}) 충전",
            admin_user_hash=admin_user_hash,
            transaction_type='charge'
        )

        return f"✅ {target_username}님에게 {amount:.2f} 크레딧이 충전되었습니다. (잔액: {new_balance:.2f})"

    except Exception as e:
        g.logger.error(f"크레딧 충전 실패: {str(e)}")
        return f"❌ 크레딧 충전 중 오류가 발생했습니다: {str(e)}"


async def cmd_credit_history(channel_id: str, user_hash: str, username: str, limit: int = 5) -> str:
    """사용자의 크레딧 거래 내역을 조회합니다."""
    try:
        history = await get_user_credit_history(channel_id, user_hash, limit)

        if not history:
            return f"📜 {username}님의 크레딧 거래 내역이 없습니다."

        message = [f"📜 {username}님의 최근 크레딧 거래 내역 (최대 {limit}건)"]

        for tx in history:
            timestamp = tx['timestamp'].strftime('%Y-%m-%d %H:%M')
            tx_type = get_transaction_type_text(tx['transaction_type'])
            amount = tx['amount']
            balance = tx['balance_after']
            description = tx['description']

            # 관리자 정보 추가
            admin_text = ""
            if tx['admin_user_hash']:
                admin_name = await get_admin_username(channel_id, tx['admin_user_hash'])
                admin_text = f" (by {admin_name})"

            message.append(f"- {timestamp}: {tx_type} {amount:+.2f} → {balance:.2f}{admin_text}")
            if description:
                message.append(f"  {description}")

        return "\n".join(message)

    except Exception as e:
        g.logger.error(f"크레딧 내역 조회 실패: {str(e)}")
        return f"❌ 크레딧 내역 조회 중 오류가 발생했습니다: {str(e)}"


async def cmd_credit_help() -> str:
    """크레딧 명령어 도움말을 표시합니다."""
    return (
        "🔰 크레딧 명령어 도움말\n"
        "\n"
        "기본 명령어:\n"
        "• # 크레딧 - 현재 크레딧 잔액 확인\n"
        "• # 크레딧 통계 - 상세 사용량 통계 확인\n"
        "• # 크레딧 내역 [개수] - 최근 거래 내역 조회 (기본 5건)\n"
        "\n"
        "관리자 전용 명령어:\n"
        "• # 크레딧 충전 [사용자] [금액] - 사용자에게 크레딧 충전\n"
        "\n"
        "채널 크레딧 관리 (관리자 전용):\n"
        "• # 방크레딧 - 채널 크레딧 관리 명령어"
    )


# ===== 채널 크레딧 명령어 처리 함수 =====

async def cmd_check_channel_balance(channel_id: str) -> str:
    """채널 크레딧 잔액을 확인합니다."""
    try:
        # 채널 크레딧 정보 조회
        channel_credit = await get_channel_credit(channel_id)
        monthly_usage = await get_monthly_usage(channel_id)

        # 설정 가져오기
        room_config = await load_room_config(channel_id)
        llm_settings = room_config.get('llm_settings', {})

        message = [
            f"🏦 채널 크레딧 정보",
            f"채널: {channel_credit['room_name']} ({channel_id})",
            f"운영 모드: {get_operation_mode_text(channel_credit['operation_mode'])}",
            f"현재 잔액: {channel_credit['credit_balance']:.2f} 크레딧",
            f"총 충전 크레딧: {channel_credit['lifetime_credits']:.2f}",
            f"총 사용 크레딧: {channel_credit['lifetime_usage']:.2f}",
            "",
            f"이번달 채널 전체 사용량: {monthly_usage['total_cost']:.2f} 크레딧 ({monthly_usage['total_tokens']:,} 토큰)"
        ]

        # 시스템/사용자 호출 설정 확인
        system_calls = llm_settings.get('system_calls', {})
        user_calls = llm_settings.get('user_calls', {})

        message.append("")
        message.append("🔹 시스템 호출 설정:")
        message.append(f"- 사용량 추적: {'켬' if system_calls.get('usage_tracking', True) else '끔'}")
        message.append(f"- 비용 추적: {'켬' if system_calls.get('cost_tracking', True) else '끔'}")
        message.append(f"- 기본 모델: {system_calls.get('default_model', 'deepseek-chat')}")
        message.append(f"- 채널 크레딧 차감: {'켬' if system_calls.get('deduct_from_channel', False) else '끔'}")

        message.append("")
        message.append("🔹 사용자 호출 설정:")
        message.append(f"- 일일 제한: {user_calls.get('daily_limit', 0)}회 (0=무제한)")
        message.append(f"- 월간 제한: {user_calls.get('monthly_limit', 0)}회 (0=무제한)")
        message.append(f"- 월별 무료 크레딧: {user_calls.get('free_credits_monthly', 0)}")
        message.append(f"- 크레딧 필요: {'켬' if user_calls.get('require_credits', False) else '끔'}")
        message.append(f"- 채널 크레딧 차감: {'켬' if user_calls.get('deduct_from_channel', False) else '끔'}")
        message.append(f"- 사용자 크레딧 차감: {'켬' if user_calls.get('deduct_from_user', False) else '끔'}")
        message.append(f"- 관리자 무료 사용: {'켬' if user_calls.get('admin_free_usage', True) else '끔'}")

        # 무료 사용 규칙 확인
        free_usage_rules = llm_settings.get('free_usage_rules', {})
        if channel_credit['operation_mode'] in ['mixed', 'paid']:
            message.append("")
            message.append("🔹 무료 사용 규칙:")
            message.append(f"- 일일 무료 할당량: {free_usage_rules.get('daily_free_quota', 0)}회")
            message.append(f"- 월간 무료 할당량: {free_usage_rules.get('monthly_free_quota', 0)}회")

            free_prefixes = free_usage_rules.get('free_prefixes', [])
            if free_prefixes:
                message.append(f"- 무료 접두어: {', '.join(free_prefixes)}")

            free_users_count = len(free_usage_rules.get('free_for_users', []))
            if free_users_count > 0:
                message.append(f"- 무료 사용자: {free_users_count}명")

        return "\n".join(message)

    except Exception as e:
        g.logger.error(f"채널 크레딧 확인 실패: {str(e)}")
        return f"❌ 채널 크레딧 정보 조회 중 오류가 발생했습니다: {str(e)}"


async def cmd_charge_channel_credit(channel_id: str, admin_user_hash: str, amount: float) -> str:
    """채널 크레딧을 충전합니다."""
    try:
        # 관리자 이름 가져오기
        admin_username = await get_admin_username(channel_id, admin_user_hash)

        # 크레딧 추가
        new_balance = await add_channel_credit(
            channel_id=channel_id,
            amount=amount,
            description=f"관리자({admin_username}) 충전",
            admin_user_hash=admin_user_hash,
            transaction_type='charge'
        )

        channel_credit = await get_channel_credit(channel_id)

        return f"✅ 채널 '{channel_credit['room_name']}'에 {amount:.2f} 크레딧이 충전되었습니다. (잔액: {new_balance:.2f})"

    except Exception as e:
        g.logger.error(f"채널 크레딧 충전 실패: {str(e)}")
        return f"❌ 채널 크레딧 충전 중 오류가 발생했습니다: {str(e)}"


async def cmd_set_channel_config(channel_id: str, admin_user_hash: str, key: str, value: str) -> str:
    """채널 설정을 변경합니다."""
    try:
        # 채널 정보 가져오기
        channel_credit = await get_channel_credit(channel_id)

        # 관리자 이름 가져오기
        admin_username = await get_admin_username(channel_id, admin_user_hash)

        # 설정 변경
        if key == "operation_mode" or key == "mode":
            valid_modes = ["free", "paid", "mixed"]
            if value not in valid_modes:
                return f"❌ 유효하지 않은 운영 모드입니다. 가능한 값: {', '.join(valid_modes)}"

            # DB 업데이트
            async with g.db_pool.acquire() as conn:
                query = """
                    UPDATE kb_channel_credits
                    SET operation_mode = %s, last_updated = NOW()
                    WHERE channel_id = %s
                """
                await conn.execute(query, (value, channel_id))

            # 설정 파일 업데이트 필요 (schedule-rooms.json)
            # 이 부분은 실제 구현에서 설정 파일 업데이트 로직 필요

            return f"✅ 채널 '{channel_credit['room_name']}'의 운영 모드가 '{get_operation_mode_text(value)}'로 변경되었습니다."

        # 다른 설정 변경이 필요한 경우 여기에 추가

        return f"❌ 알 수 없는 설정 키: {key}"

    except Exception as e:
        g.logger.error(f"채널 설정 변경 실패: {str(e)}")
        return f"❌ 채널 설정 변경 중 오류가 발생했습니다: {str(e)}"


async def cmd_channel_credit_history(channel_id: str, limit: int = 5) -> str:
    """채널의 크레딧 거래 내역을 조회합니다."""
    try:
        history = await get_channel_credit_history(channel_id, limit)
        channel_credit = await get_channel_credit(channel_id)

        if not history:
            return f"📜 채널 '{channel_credit['room_name']}'의 크레딧 거래 내역이 없습니다."

        message = [f"📜 채널 '{channel_credit['room_name']}'의 최근 크레딧 거래 내역 (최대 {limit}건)"]

        for tx in history:
            timestamp = tx['timestamp'].strftime('%Y-%m-%d %H:%M')
            tx_type = get_transaction_type_text(tx['transaction_type'])
            amount = tx['amount']
            balance = tx['balance_after']
            description = tx['description']

            # 관리자 정보 추가
            admin_text = ""
            if tx['admin_user_hash']:
                admin_name = await get_admin_username(channel_id, tx['admin_user_hash'])
                admin_text = f" (by {admin_name})"

            message.append(f"- {timestamp}: {tx_type} {amount:+.2f} → {balance:.2f}{admin_text}")
            if description:
                message.append(f"  {description}")

        return "\n".join(message)

    except Exception as e:
        g.logger.error(f"채널 크레딧 내역 조회 실패: {str(e)}")
        return f"❌ 채널 크레딧 내역 조회 중 오류가 발생했습니다: {str(e)}"


async def cmd_channel_credit_help() -> str:
    """채널 크레딧 명령어 도움말을 표시합니다."""
    return (
        "🔰 채널 크레딧 명령어 도움말 (관리자 전용)\n"
        "\n"
        "기본 명령어:\n"
        "• # 방크레딧 - 채널 크레딧 정보 확인\n"
        "• # 방크레딧 충전 [금액] - 채널에 크레딧 충전\n"
        "• # 방크레딧 내역 [개수] - 최근 거래 내역 조회 (기본 5건)\n"
        "\n"
        "설정 명령어:\n"
        "• # 방크레딧 설정 operation_mode [모드] - 운영 모드 변경\n"
        "  가능한 모드: free(무료), paid(유료), mixed(혼합)\n"
        "\n"
        "※ 자세한 설정은 schedule-rooms.json 파일을 직접 수정하세요."
    )
