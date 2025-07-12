"""
show_llm_models 서비스 모듈 (API 실시간 모델 목록)

- 관리자만 사용 가능
- OPENAI, GEMINI는 공식 API로 모델 목록 조회
- GROK, DEEPSEEK는 공식 API 미제공 안내
- 모든 결과는 리스트(문자열)로 반환
"""
import core.globals as g
from core.logger import logger
import httpx
from config.loader import load_config

CONFIG = load_config()

# 관리자 권한 체크 함수 (context 기반)
def is_admin_ext(context):
    admin_users = getattr(g, 'ADMIN_USERS', [])
    user_hash = context.get('user_hash')
    channel_id = context.get('_original_channel_id') or context.get('channel_id')
    for admin in admin_users:
        if admin.get('user_hash') == user_hash and (admin.get('channel_id') == channel_id or admin.get('channel_id') == "*"):
            return True
    return False

async def fetch_openai_models():
    api_key = CONFIG['APIs']['OPENAI']['KEY']
    url = 'https://api.openai.com/v1/models'
    headers = {'Authorization': f'Bearer {api_key}'}
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.get(url, headers=headers)
            resp.raise_for_status()
            data = resp.json()
            models = sorted(set(m['id'] for m in data.get('data', [])))
            return models
    except Exception as e:
        logger.error(f"[OPENAI] 모델 목록 조회 실패: {e}")
        return [f"[오류] 모델 목록 조회 실패: {e}"]

async def fetch_gemini_models():
    api_key = CONFIG['APIs']['GEMINI']['KEY']
    url = f'https://generativelanguage.googleapis.com/v1beta/models?key={api_key}'
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.get(url)
            resp.raise_for_status()
            data = resp.json()
            models = sorted(set(m['name'].split('/')[-1] for m in data.get('models', [])))
            return models
    except Exception as e:
        logger.error(f"[GEMINI] 모델 목록 조회 실패: {e}")
        return [f"[오류] 모델 목록 조회 실패: {e}"]

async def show_llm_models(context):
    """
    각 LLM 회사의 모델 리스트를 API 호출로 받아와 출력합니다. (관리자 전용)
    """
    try:
        if not is_admin_ext(context):
            logger.info(f"show_llm_models: 권한 없음 user_hash={context.get('user_hash')}")
            return ['@no-reply']

        msg = []
        msg.append('[지원 LLM 모델 목록]')
        msg.append('')

        # OPENAI
        openai_models = await fetch_openai_models()
        msg.append('[OPENAI]')
        for m in openai_models:
            msg.append(f'- {m}')
        msg.append('')

        # GEMINI
        gemini_models = await fetch_gemini_models()
        msg.append('[GEMINI]')
        for m in gemini_models:
            msg.append(f'- {m}')
        msg.append('')

        # GROK
        msg.append('[GROK]')
        msg.append('- 공식 API 미제공, 수동 관리 필요')
        msg.append('')

        # DEEPSEEK
        msg.append('[DEEPSEEK]')
        msg.append('- 공식 API 미제공, 수동 관리 필요')

        logger.info(f"show_llm_models: 관리자 {context.get('user_hash')}가 LLM 목록 조회(API 실시간)")
        return ['\n'.join(msg)]
    except Exception as e:
        logger.error(f"show_llm_models 오류: {e}")
        return ['오류가 발생했습니다. 관리자에게 문의하세요.'] 