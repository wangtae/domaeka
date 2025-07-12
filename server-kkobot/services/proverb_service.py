import random
from core import db_utils
from core.logger import logger  # get_logger 대신 logger 직접 임포트

async def get_investment_proverb(category=None):
    """
    투자 격언을 데이터베이스에서 가져옵니다.

    Args:
        category (str, optional): 격언 카테고리. 기본값은 None으로, 전체 카테고리에서 선택합니다.

    Returns:
        str: 포맷된 투자 격언
    """

    try:
        if category:
            # 카테고리가 지정된 경우, 해당 카테고리의 격언만 가져옴
            query = """
                SELECT quote, person_name, comment, category_name
                FROM kb_investment_proverbs
                WHERE category_name = %s
                ORDER BY RAND()
                LIMIT 1
            """
            result = await db_utils.fetchone(query, (category,))

            if not result:
                # 지정된 카테고리에 격언이 없는 경우
                # 사용 가능한 카테고리 목록 가져오기
                categories_query = """
                    SELECT DISTINCT category_name 
                    FROM kb_investment_proverbs
                    ORDER BY category_name
                """
                categories = await db_utils.fetchall(categories_query)

                if categories:
                    available_categories = ", ".join([f"'{cat['category_name']}'" for cat in categories])
                    return f"(슬픔) '{category}' 카테고리를 찾을 수 없습니다.\n\n사용 가능한 카테고리: {available_categories}"
                else:
                    return "(슬픔) 등록된 투자격언이 없습니다."
        else:
            # 카테고리 미지정 시 랜덤 격언 가져오기
            query = """
                SELECT quote, person_name, comment, category_name
                FROM kb_investment_proverbs
                ORDER BY RAND()
                LIMIT 1
            """
            result = await db_utils.fetchone(query)

        if not result:
            return "(슬픔) 등록된 투자격언이 없습니다."

        quote = result["quote"]
        person = result["person_name"]
        comment = result["comment"] or "설명 없음"
        category_name = result["category_name"]

        return f"""(굿) 오늘의 투자격언!

"{quote}" - {person}

💬 {comment}

📖 카테고리: {category_name}"""

    except Exception as e:
        logger.error(f"투자격언 조회 중 오류 발생: {str(e)}")
        return f"투자격언을 조회하는 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요."

async def handle_today_proverb(prompt):
    """
    '# [오늘의 투자격언]' 명령어 처리 함수

    Args:
        prompt (str): 명령어 이후의 추가 텍스트 (카테고리)

    Returns:
        str: 투자 격언
    """
    # 프롬프트에서 카테고리 추출
    category = prompt.strip() if prompt else None

    # 카테고리가 있으면 해당 카테고리의 격언만, 없으면 전체에서 랜덤 선택
    return await get_investment_proverb(category)

# 기존 get_random_investment_proverb 함수도 유지하여 하위 호환성 보장
async def get_random_investment_proverb():
    """
    랜덤 투자 격언을 반환합니다. (기존 호환성 유지를 위한 함수)
    """
    return await get_investment_proverb(None)