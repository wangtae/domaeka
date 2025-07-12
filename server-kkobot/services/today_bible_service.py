"""
매일성경 묵상 서비스

오늘의 성경 구절과 묵상 내용을 제공하는 서비스 모듈입니다.
DB에 이미 등록된 성경 구절과 날짜를 이용해 묵상 내용을 생성하고 저장합니다.
"""

import asyncio
from datetime import datetime
import pytz
import aiomysql
from core import globals as g
from core.logger import logger
from services.bible_service import bible_query
from services.llm_fallback_service import call_llm_with_fallback
# conversation_joiner.py
from core.globals import LLM_DEFAULT_SYSTEM_PROMPT

# 한국 시간대 설정
KST = pytz.timezone('Asia/Seoul')


async def get_bible_text_by_reference(reference):
    """
    성경 구절 참조를 이용해 본문을 가져옵니다.

    Args:
        reference (str): 성경 구절 참조 (예: "창 1:1-10")

    Returns:
        str: 성경 구절 텍스트
    """
    try:
        # bible_query 함수 호출하여 성경 구절 가져오기
        # command_type을 'bible'로 설정
        bible_result = await bible_query('bible', reference)

        # 결과에서 구절 텍스트만 추출 (포맷에 따라 수정 필요할 수 있음)
        # 일반적으로 bible_query의 결과는 포맷팅된 전체 메시지입니다
        if bible_result:
            lines = bible_result.split('\n')
            if len(lines) > 2:
                return '\n'.join(lines[2:])  # 제목과 빈 줄 제외
            return bible_result
        return None
    except Exception as e:
        logger.exception(f"[TODAY_BIBLE] 성경 구절 조회 중 오류 발생: {e}")
        return None


async def get_bible_versions_by_reference(reference, versions=None):
    """
    특정 버전들의 성경 본문을 가져옵니다.

    Args:
        reference (str): 성경 구절 참조 (예: "창 1:1-10")
        versions (list): 조회할 성경 버전 코드 리스트 (기본값: 개역한글, 표준킹제임스)

    Returns:
        dict: 버전별 성경 본문 (버전 코드: 본문)
    """
    if versions is None:
        versions = ["개역한글", "표준킹제임스"]  # 기본값: 개역한글, 표준킹제임스

    result = {}

    for version in versions:
        try:
            # 버전을 지정하여 성경 본문 조회
            # 해시태그 형식으로 버전 지정 (예: #개역한글)
            version_tag = f"#{version}"
            query_text = f"{reference} {version_tag}"

            bible_content = await bible_query('bible', query_text)

            if bible_content:
                # 첫 번째 줄의 버전 정보와 빈 줄 제거
                lines = bible_content.split('\n')
                if len(lines) > 2:
                    content = '\n'.join(lines[2:])
                    result[version] = content
        except Exception as e:
            logger.error(f"[TODAY_BIBLE] {version} 버전 조회 중 오류: {e}")

    return result


async def get_todays_bible_reference():
    """
    오늘 날짜에 해당하는 성경 구절 참조를 DB에서 조회합니다.

    Returns:
        tuple: (구절 참조, 구절 텍스트, meditation 존재 여부, bible_id, meditation)
    """
    try:
        # 오늘 날짜 구하기
        now = datetime.now(KST)
        today_date = now.strftime("%Y-%m-%d")

        # DB에서 오늘 날짜의 성경 구절 조회
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                query = """
                SELECT id, bible, meditation FROM kb_today_bible 
                WHERE date = %s AND source = '매일성경'
                """
                await cursor.execute(query, (today_date,))
                result = await cursor.fetchone()

                if not result:
                    logger.warning(f"[TODAY_BIBLE] 오늘({today_date})에 해당하는 성경 구절이 없습니다.")
                    return None, None, False, None, None

                bible_reference = result['bible']
                meditation = result['meditation']
                has_meditation = meditation is not None and meditation.strip() != ""

                # 성경 구절 텍스트 가져오기
                bible_text = await get_bible_text_by_reference(bible_reference + " #개역한글")

                return bible_reference, bible_text, has_meditation, result['id'], meditation

    except Exception as e:
        logger.exception(f"[TODAY_BIBLE] 오늘의 성경 구절 조회 중 오류 발생: {e}")
        return None, None, False, None, None


async def generate_and_save_meditation(bible_reference, bible_text, bible_id):
    """
    LLM을 사용하여 성경 구절에 대한 묵상 내용을 생성하고 DB에 저장합니다.

    Args:
        bible_reference (str): 성경 구절 참조
        bible_text (str): 성경 구절 텍스트
        bible_id (int): kb_today_bible 테이블의 id

    Returns:
        str: 생성된 묵상 내용
    """
    try:
        prompt = f"""
성경 구절: {bible_reference}
내용: {bible_text}

위 성경 구절에 대한 묵상 글을 작성해 주세요. 
이 묵상 글은 신앙인들의 영적 성장을 돕기 위한 것입니다.
다음 형식으로 작성해 주세요:

📖 본문 이해: 구절의 배경과 핵심 메시지를 간략하게 설명
🔍 적용 포인트: 현대 생활에 어떻게 적용할 수 있는지 
🙏 오늘의 기도: 이 구절과 관련된 짧은 기도문

개혁주의(칼빈주의, 장로교) 신학을 기본으로 해주세요.
전체 길이는 400~600자 정도로 작성해 주세요.
이모티콘 등을 잘 활용해서 가독성 있도록 만들어 주세요. 
##, ### 등으로 강조하지 마세요.
"""

        # LLM으로 묵상 내용 생성 (묵상 내용이므로 품질이 중요, retry=1 설정)
        context = {
            "prompt": prompt,
            "client_key": "meditation_generator"  # 대기 메시지 없이 처리하기 위한 더미 키
        }

        providers = [
            {
                "name": "openai",
                "timeout": 30,
                "model": "gpt-4o",
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "gemini",
                "model": "gemini-1.5-pro",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "gemini-flash",
                "model": "gemini-1.5-flash",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "grok",
                "model": "grok-3-latest",
                "timeout": 30,
                "retry": 0,
                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            },
            {
                "name": "deepseek",
                "timeout": 30,
                "model": "gemini-1.5-pro",
                "retry": 0,

                "system_prompt": LLM_DEFAULT_SYSTEM_PROMPT
            }
        ]

        meditation = await call_llm_with_fallback(context, prompt, providers)

        # 묵상 내용 포맷팅
        if meditation and not meditation.startswith("❌") and not meditation.startswith("⚠️"):
            # DB에 저장할 때는 이모지를 포함하지 않습니다 (문자셋 문제로 인해)
            formatted_meditation = f"(굿) 오늘의 매일성경 ({bible_reference})\n\n{meditation}"

            # DB에 저장
            async with g.db_pool.acquire() as conn:
                await conn.set_charset('utf8mb4')
                async with conn.cursor() as cursor:
                    update_query = """
                    UPDATE kb_today_bible
                    SET meditation = %s
                    WHERE id = %s
                    """
                    await cursor.execute(update_query, (formatted_meditation, bible_id))
                    await conn.commit()

                    logger.info(f"[TODAY_BIBLE] 묵상 저장 완료: id={bible_id}, 구절={bible_reference}")

            # 사용자에게 보여줄 때는 이모지를 추가합니다
            return f"(굿) 오늘의 매일성경 ({bible_reference})\n\n{meditation}"
        else:
            logger.error(f"[TODAY_BIBLE] LLM 응답 오류: {meditation}")
            return None

    except Exception as e:
        logger.exception(f"[TODAY_BIBLE] 묵상 생성 및 저장 중 오류 발생: {e}")
        return None


async def handle_today_bible_command():
    """
    매일성경 묵상 명령어 처리 함수

    Returns:
        str: 오늘의 성경 구절과 묵상 내용 및 성경 본문
    """
    try:
        # 오늘의 성경 구절 가져오기
        bible_reference, bible_text, has_meditation, bible_id, meditation = await get_todays_bible_reference()

        if not bible_reference:
            return "오늘의 성경 구절이 준비되지 않았습니다. 관리자에게 문의해 주세요."

        if not bible_text:
            logger.error(f"[TODAY_BIBLE] 성경 구절 조회 실패: {bible_reference}")
            return "오늘의 성경 구절을 가져오는 중 오류가 발생했습니다."

        # 이미 묵상이 저장되어 있는 경우
        if has_meditation:
            logger.info(f"[TODAY_BIBLE] 기존 묵상 사용: {bible_reference}")
        else:
            # 묵상 내용 생성 및 저장
            logger.info(f"[TODAY_BIBLE] 묵상 생성 시작: {bible_reference}")
            meditation = await generate_and_save_meditation(bible_reference, bible_text, bible_id)

            if not meditation:
                # 묵상 생성 실패 시에는 성경 구절만 반환
                meditation = f"📖 오늘의 말씀 ({bible_reference})\n\n{bible_text}"

        # 성경 버전별 본문 가져오기
        bible_versions = await get_bible_versions_by_reference(bible_reference, ["개역한글", "표준킹제임스", "쉬운말", "WEB", "KJV"])

        # 최종 응답에 성경 본문 추가
        response = meditation + "\n\n"

        if bible_versions:
            response += "\n📜 매일성경 성경 본문 >\n"

            if "개역한글" in bible_versions:
                response += "\n[개역한글]\n" + bible_versions["개역한글"] + "\n"

            if "쉬운말" in bible_versions:
                response += "\n[쉬운말]\n" + bible_versions["쉬운말"] + "\n"

            if "WEB" in bible_versions:
                response += "\n[WEB]\n" + bible_versions["WEB"] + "\n"

            if "KJV" in bible_versions:
                response += "\n[KJV]\n" + bible_versions["KJV"] + "\n"

        return response

    except Exception as e:
        logger.exception(f"[TODAY_BIBLE] 명령어 처리 중 오류 발생: {e}")
        return "매일성경 묵상을 가져오는 중 오류가 발생했습니다."
