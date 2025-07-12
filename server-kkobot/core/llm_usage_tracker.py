# core/llm_usage_tracker.py

import time
import json
import uuid
import asyncio
from datetime import datetime
from typing import Dict, Any, Optional, Tuple, List, Union

from core import globals as g

# 설정 파일 경로
# MODEL_PRICING_PATH = "config/model_pricing.json"  # 이 줄 제거

# 모델 가격 정보 캐시
_model_pricing_cache = {}
_last_pricing_load_time = 0
_pricing_cache_ttl = 60 * 15  # 15분마다 캐시 갱신

# 설정 정보 캐시
_room_config_cache = {}
_last_config_load_time = 0
_config_cache_ttl = 60 * 5  # 5분마다 캐시 갱신


async def load_model_pricing() -> Dict:
    """모델 가격 정보를 로드합니다."""
    global _model_pricing_cache, _last_pricing_load_time

    current_time = time.time()

    # 캐시가 유효하면 캐시된 데이터 반환
    if _model_pricing_cache and (current_time - _last_pricing_load_time) < _pricing_cache_ttl:
        return _model_pricing_cache

    try:
        with open(g.JSON_CONFIG_FILES["model_pricing"], 'r', encoding='utf-8') as f:
            pricing_data = json.load(f)

        _model_pricing_cache = pricing_data
        _last_pricing_load_time = current_time

        # 추가적으로 DB에서도 최신 가격 정보 로드
        await sync_pricing_with_db(pricing_data)

        return pricing_data
    except Exception as e:
        g.logger.error(f"모델 가격 정보 로드 실패: {str(e)}")
        # 캐시된 데이터가 있으면 그것이라도 반환
        if _model_pricing_cache:
            return _model_pricing_cache
        # 아무것도 없으면 기본 가격 정보 반환
        return {
            "openai": {
                "gpt-4": {"prompt_price_per_1k": 0.03, "completion_price_per_1k": 0.06},
                "gpt-3.5-turbo": {"prompt_price_per_1k": 0.0015, "completion_price_per_1k": 0.002}
            },
            "google": {
                "gemini-pro": {"prompt_price_per_1k": 0.0005, "completion_price_per_1k": 0.0015}
            },
            "deepseek": {
                "deepseek-chat": {"prompt_price_per_1k": 0.0002, "completion_price_per_1k": 0.0002}
            },
            "default_conversion_rate": {"USD_to_KRW": 1350}
        }


async def sync_pricing_with_db(pricing_data: Dict) -> None:
    """JSON 설정의 가격 정보를 DB와 동기화합니다."""
    try:
        async with g.db_pool.acquire() as conn:
            # 현재 시간 기준으로 유효한 가격 정보를 설정 (이전 가격 정보는 valid_to 설정)
            now = datetime.now()

            # 각 모델별로 처리
            for provider, models in pricing_data.items():
                if provider == "default_conversion_rate":
                    continue

                for model_name, prices in models.items():
                    full_model_name = f"{provider}-{model_name}"

                    # 기존 유효한 가격 정보 확인
                    query = """
                        SELECT id, prompt_price_per_1k, completion_price_per_1k 
                        FROM kb_model_pricing 
                        WHERE model = %s AND valid_to IS NULL
                    """
                    existing = await conn.fetchone(query, (full_model_name,))

                    prompt_price = prices.get("prompt_price_per_1k", 0)
                    completion_price = prices.get("completion_price_per_1k", 0)

                    if existing:
                        # 가격이 변경되었는지 확인
                        if (existing['prompt_price_per_1k'] != prompt_price or
                                existing['completion_price_per_1k'] != completion_price):
                            # 기존 가격 정보의 valid_to 업데이트
                            update_query = """
                                UPDATE kb_model_pricing 
                                SET valid_to = %s 
                                WHERE id = %s
                            """
                            await conn.execute(update_query, (now, existing['id']))

                            # 새로운 가격 정보 추가
                            insert_query = """
                                INSERT INTO kb_model_pricing 
                                (model, prompt_price_per_1k, completion_price_per_1k, valid_from) 
                                VALUES (%s, %s, %s, %s)
                            """
                            await conn.execute(insert_query, (full_model_name, prompt_price, completion_price, now))
                    else:
                        # 새로운 모델 추가
                        insert_query = """
                            INSERT INTO kb_model_pricing 
                            (model, prompt_price_per_1k, completion_price_per_1k, valid_from) 
                            VALUES (%s, %s, %s, %s)
                        """
                        await conn.execute(insert_query, (full_model_name, prompt_price, completion_price, now))

    except Exception as e:
        g.logger.error(f"모델 가격 정보 DB 동기화 실패: {str(e)}")


async def get_model_pricing(model: str) -> Optional[Dict]:
    """특정 모델의 가격 정보를 가져옵니다."""
    pricing_data = await load_model_pricing()

    # 모델 이름에서 provider와 model 분리
    parts = model.split('-', 1)
    if len(parts) == 2:
        provider, model_name = parts
    else:
        # 기본값으로 처리
        for provider, models in pricing_data.items():
            if provider != "default_conversion_rate" and model in models:
                return models[model]

        # DB에서 조회
        try:
            async with g.db_pool.acquire() as conn:
                query = """
                    SELECT prompt_price_per_1k, completion_price_per_1k 
                    FROM kb_model_pricing 
                    WHERE model = %s AND valid_to IS NULL
                """
                result = await conn.fetchone(query, (model,))
                if result:
                    return {
                        "prompt_price_per_1k": float(result['prompt_price_per_1k']),
                        "completion_price_per_1k": float(result['completion_price_per_1k'])
                    }
        except Exception as e:
            g.logger.error(f"모델 가격 정보 DB 조회 실패: {str(e)}")

        # 기본 가격 정보 반환
        return {"prompt_price_per_1k": 0.001, "completion_price_per_1k": 0.002}

    # JSON 설정에서 조회
    if provider in pricing_data and model_name in pricing_data[provider]:
        return pricing_data[provider][model_name]

    # DB에서 조회
    try:
        async with g.db_pool.acquire() as conn:
            query = """
                SELECT prompt_price_per_1k, completion_price_per_1k 
                FROM kb_model_pricing 
                WHERE model = %s AND valid_to IS NULL
            """
            result = await conn.fetchone(query, (model,))
            if result:
                return {
                    "prompt_price_per_1k": float(result['prompt_price_per_1k']),
                    "completion_price_per_1k": float(result['completion_price_per_1k'])
                }
    except Exception as e:
        g.logger.error(f"모델 가격 정보 DB 조회 실패: {str(e)}")

    # 기본 가격 정보 반환
    return {"prompt_price_per_1k": 0.001, "completion_price_per_1k": 0.002}


async def get_conversion_rate() -> float:
    """USD에서 KRW로의 환율을 가져옵니다."""
    pricing_data = await load_model_pricing()
    return pricing_data.get("default_conversion_rate", {}).get("USD_to_KRW", 1350)


async def calculate_cost(model: str, prompt_tokens: int, completion_tokens: int) -> Dict:
    """토큰 사용량을 기반으로 비용을 계산합니다."""
    # 모델 가격 정보 가져오기
    pricing = await get_model_pricing(model)

    if not pricing:
        # 기본 가격 사용
        pricing = {"prompt_price_per_1k": 0.001, "completion_price_per_1k": 0.002}

    # 토큰당 비용 계산
    prompt_cost = (prompt_tokens / 1000) * pricing['prompt_price_per_1k']
    completion_cost = (completion_tokens / 1000) * pricing['completion_price_per_1k']
    total_cost = prompt_cost + completion_cost

    # USD에서 KRW로 변환
    conversion_rate = await get_conversion_rate()
    total_cost_krw = total_cost * conversion_rate

    return {
        'prompt_cost': prompt_cost,
        'completion_cost': completion_cost,
        'total_cost': total_cost_krw,
        'total_cost_usd': total_cost,
        'currency': 'KRW',
        'conversion_rate': conversion_rate
    }

# core/llm_usage_tracker.py (계속)

class LLMUsageTracker:
    def __init__(self):
        self.schedule_path = g.JSON_CONFIG_FILES["schedule_rooms"]


async def load_room_config(channel_id: str) -> Dict:
    """특정 채널의 설정 정보를 로드합니다."""
    global _room_config_cache, _last_config_load_time

    current_time = time.time()

    # 캐시가 유효하면 캐시된 데이터 반환
    if channel_id in _room_config_cache and (current_time - _last_config_load_time) < _config_cache_ttl:
        return _room_config_cache[channel_id]

    try:
        with open(self.schedule_path, 'r', encoding='utf-8') as f:
            all_config = json.load(f)

        # 전체 설정 갱신
        _last_config_load_time = current_time
        _room_config_cache = {}

        # 각 봇별 설정
        for bot_name, channels in all_config.items():
            for ch_id, config in channels.items():
                _room_config_cache[ch_id] = config

        # 요청한 채널 설정 반환
        if channel_id in _room_config_cache:
            return _room_config_cache[channel_id]

        # 없으면 기본 설정 반환
        return {
            "room_name": "Unknown",
            "llm_settings": {
                "show_usage": True,
                "show_cost": True,
                "show_model": True,
                "show_monthly_usage": False,
                "show_lifetime_usage": False,
                "usage_display_format": "[모델: {model}] 토큰: {total_tokens}개 ({cost:.2f}원)",
                "custom_message": "",
                "operation_mode": "free",
                "system_calls": {
                    "usage_tracking": True,
                    "default_model": "deepseek-chat",
                    "cost_tracking": True,
                    "deduct_from_channel": False
                },
                "user_calls": {
                    "daily_limit": 0,
                    "monthly_limit": 0,
                    "require_credits": False,
                    "free_credits_monthly": 0,
                    "allowed_prefixes": [">", ">>", ">>>"],
                    "deduct_from_channel": False,
                    "deduct_from_user": False,
                    "fallback_to_user": False,
                    "admin_free_usage": True
                },
                "free_usage_rules": {
                    "free_for_users": [],
                    "free_prefixes": [">"],
                    "daily_free_quota": 0,
                    "monthly_free_quota": 0
                }
            }
        }
    except Exception as e:
        g.logger.error(f"채널 설정 로드 실패: {str(e)}")
        # 기본 설정 반환
        return {
            "room_name": "Unknown",
            "llm_settings": {
                "show_usage": True,
                "show_cost": True,
                "show_model": True,
                "operation_mode": "free"
            }
        }


async def log_llm_usage(
        context: Dict[str, Any],
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
        total_tokens: int,
        estimated_cost: float,
        response_time_ms: int,
        caller_type: str = 'user',
        success: bool = True,
        error_message: Optional[str] = None
) -> None:
    """LLM 사용량을 로그에 기록합니다."""
    try:
        channel_id = context.get('channel_id', '')
        user_hash = context.get('user_hash', None) if caller_type == 'user' else None
        username = context.get('sender', '') if caller_type == 'user' else 'SYSTEM'
        room_name = context.get('room', '')
        command_prefix = context.get('prefix', '')
        request_id = str(uuid.uuid4())

        # DB에 로그 저장
        async with g.db_pool.acquire() as conn:
            query = """
                INSERT INTO kb_llm_usage_logs (
                    channel_id, room_name, caller_type, user_hash, username,
                    model, prompt_tokens, completion_tokens, total_tokens,
                    estimated_cost, response_time_ms, command_prefix,
                    success, error_message, request_id
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """

            values = (
                channel_id, room_name, caller_type, user_hash, username,
                model, prompt_tokens, completion_tokens, total_tokens,
                estimated_cost, response_time_ms, command_prefix,
                success, error_message, request_id
            )

            await conn.execute(query, values)

        # 로그 출력
        log_msg = (
            f"LLM 사용 로그: [{caller_type}] {channel_id}/{room_name} "
            f"모델={model}, 토큰={total_tokens}, 비용={estimated_cost:.6f}원, "
            f"응답시간={response_time_ms}ms, 성공={success}"
        )

        if success:
            g.logger.info(log_msg)
        else:
            g.logger.error(f"{log_msg}, 오류: {error_message}")

        return request_id

    except Exception as e:
        g.logger.error(f"LLM 사용량 로깅 실패: {str(e)}")
        return None


async def create_usage_display(
        context: Dict[str, Any],
        model: str,
        usage: Dict[str, int],
        cost_info: Dict[str, float]
) -> str:
    """사용량 정보를 표시하는 메시지를 생성합니다."""
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')

    # 채널 설정 가져오기
    room_config = await load_room_config(channel_id)
    llm_settings = room_config.get('llm_settings', {})

    # 표시 여부 설정 확인
    show_usage = llm_settings.get('show_usage', False)
    show_cost = llm_settings.get('show_cost', False)
    show_model = llm_settings.get('show_model', False)

    if not (show_usage or show_cost or show_model):
        return ""  # 표시 설정 없음

    # 템플릿 가져오기
    template = llm_settings.get('usage_display_format', "[모델: {model}] 토큰: {total_tokens}개 ({cost:.2f}원)")
    custom_message = llm_settings.get('custom_message', "")

    # 기본 정보 수집
    display_data = {
        'model': model,
        'prompt_tokens': usage.get('prompt_tokens', 0),
        'completion_tokens': usage.get('completion_tokens', 0),
        'total_tokens': usage.get('total_tokens', 0),
        'cost': cost_info['total_cost']
    }

    # 월간 사용량 추가 (설정된 경우)
    if llm_settings.get('show_monthly_usage', False):
        monthly_usage = await get_monthly_usage(channel_id, user_hash)
        display_data['monthly_tokens'] = monthly_usage['total_tokens']
        display_data['monthly_cost'] = monthly_usage['total_cost']

    # 전체 사용량 추가 (설정된 경우)
    if llm_settings.get('show_lifetime_usage', False):
        lifetime_usage = await get_lifetime_usage(channel_id, user_hash)
        display_data['lifetime_tokens'] = lifetime_usage['total_tokens']
        display_data['lifetime_cost'] = lifetime_usage['total_cost']

    # 템플릿 적용
    try:
        usage_message = template.format(**display_data)
    except KeyError:
        # 템플릿에 누락된 키가 있는 경우 기본 템플릿 사용
        usage_message = f"[모델: {model}] 토큰: {display_data['total_tokens']}개 ({cost_info['total_cost']:.2f}원)"

    # 커스텀 메시지 추가 (비어있지 않을 경우에만)
    result = f"\n\n{usage_message}"
    if custom_message:
        result += f"\n{custom_message}"

    return result


async def get_monthly_usage(channel_id: str, user_hash: Optional[str] = None) -> Dict[str, Any]:
    """현재 월의 사용량 통계를 가져옵니다."""
    async with g.db_pool.acquire() as conn:
        # 이번달 시작일과 현재 날짜
        current_month_start = datetime.now().replace(day=1, hour=0, minute=0, second=0, microsecond=0)

        # 쿼리 조건 설정
        conditions = ["timestamp >= %s", "channel_id = %s"]
        params = [current_month_start, channel_id]

        if user_hash:
            conditions.append("user_hash = %s")
            params.append(user_hash)

        # 쿼리 실행
        where_clause = " AND ".join(conditions)
        query = f"""
            SELECT 
                SUM(prompt_tokens) as total_prompt_tokens,
                SUM(completion_tokens) as total_completion_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost
            FROM kb_llm_usage_logs
            WHERE {where_clause}
        """

        result = await conn.fetchone(query, params)

        return {
            'total_prompt_tokens': result['total_prompt_tokens'] or 0,
            'total_completion_tokens': result['total_completion_tokens'] or 0,
            'total_tokens': result['total_tokens'] or 0,
            'total_cost': float(result['total_cost'] or 0)
        }


async def get_lifetime_usage(channel_id: str, user_hash: Optional[str] = None) -> Dict[str, Any]:
    """전체 기간 사용량 통계를 가져옵니다."""
    async with g.db_pool.acquire() as conn:
        # 쿼리 조건 설정
        conditions = ["channel_id = %s"]
        params = [channel_id]

        if user_hash:
            conditions.append("user_hash = %s")
            params.append(user_hash)

        # 쿼리 실행
        where_clause = " AND ".join(conditions)
        query = f"""
            SELECT 
                SUM(prompt_tokens) as total_prompt_tokens,
                SUM(completion_tokens) as total_completion_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost
            FROM kb_llm_usage_logs
            WHERE {where_clause}
        """

        result = await conn.fetchone(query, params)

        return {
            'total_prompt_tokens': result['total_prompt_tokens'] or 0,
            'total_completion_tokens': result['total_completion_tokens'] or 0,
            'total_tokens': result['total_tokens'] or 0,
            'total_cost': float(result['total_cost'] or 0)
        }


async def check_usage_limits(context: Dict[str, Any]) -> Dict[str, Any]:
    """사용자의 사용량 제한을 확인합니다."""
    channel_id = context.get('channel_id')
    user_hash = context.get('user_hash')
    prefix = context.get('prefix', '')

    # 관리자 확인
    is_admin = await check_if_admin(channel_id, user_hash)

    # 채널 설정 가져오기
    room_config = await load_room_config(channel_id)
    llm_settings = room_config.get('llm_settings', {})
    user_call_settings = llm_settings.get('user_calls', {})
    free_usage_rules = llm_settings.get('free_usage_rules', {})

    # 운영 모드 확인
    operation_mode = llm_settings.get('operation_mode', 'free')

    # 무료 모드라면 항상 허용
    if operation_mode == 'free':
        return {'allowed': True, 'reason': 'free_mode'}

    # 관리자 무료 사용 설정 확인
    if is_admin and user_call_settings.get('admin_free_usage', True):
        return {'allowed': True, 'reason': 'admin_free_usage'}

    # 특정 사용자 무료 설정 확인
    free_for_users = free_usage_rules.get('free_for_users', [])
    if user_hash in free_for_users:
        return {'allowed': True, 'reason': 'free_for_user'}

    # 무료 접두어 확인
    free_prefixes = free_usage_rules.get('free_prefixes', [])
    if prefix in free_prefixes:
        return {'allowed': True, 'reason': 'free_prefix'}

    # 일일/월간 무료 할당량 확인
    daily_free_quota = free_usage_rules.get('daily_free_quota', 0)
    monthly_free_quota = free_usage_rules.get('monthly_free_quota', 0)

    async with g.db_pool.acquire() as conn:
        results = {}

        # 일일 사용량 제한 확인
        if daily_free_quota > 0 or user_call_settings.get('daily_limit', 0) > 0:
            today_start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
            query = """
                SELECT COUNT(*) as count
                FROM kb_llm_usage_logs
                WHERE channel_id = %s AND user_hash = %s AND timestamp >= %s
            """
            daily_count = await conn.fetchone(query, (channel_id, user_hash, today_start))
            daily_count_value = daily_count['count'] if daily_count else 0

            # 일일 무료 할당량 내인지 확인
            if daily_free_quota > 0 and daily_count_value < daily_free_quota:
                results['daily_allowed'] = True
            else:
                results['daily_allowed'] = False

        # 월간 사용량 제한 확인
        if monthly_free_quota > 0 or user_call_settings.get('monthly_limit', 0) > 0:
            current_month_start = datetime.now().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
            query = """
                SELECT COUNT(*) as count
                FROM kb_llm_usage_logs
                WHERE channel_id = %s AND user_hash = %s AND timestamp >= %s AND timestamp < %s
            """
            monthly_count = await conn.fetchone(query, (channel_id, user_hash, current_month_start, datetime.now()))
            monthly_count_value = monthly_count['count'] if monthly_count else 0

            # 월간 무료 할당량 내인지 확인
            if monthly_free_quota > 0 and monthly_count_value < monthly_free_quota:
                results['monthly_allowed'] = True
            else:
                results['monthly_allowed'] = False

        # 최종 결과 반환
        return results
