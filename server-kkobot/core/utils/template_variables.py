from datetime import datetime
import hashlib
import pytz
import re
import aiomysql
from core import globals as g  # 실제 환경에서만 사용

# 한국 시간대 설정
KST = pytz.timezone('Asia/Seoul')

book_to_number = {
    "창세기": 1, "출애굽기": 2, "레위기": 3, "민수기": 4, "신명기": 5,
    "여호수아": 6, "사사기": 7, "룻기": 8, "사무엘상": 9, "사무엘하": 10,
    "열왕기상": 11, "열왕기하": 12, "역대상": 13, "역대하": 14, "에스라": 15,
    "느헤미야": 16, "에스더": 17, "욥기": 18, "시편": 19, "잠언": 20,
    "전도서": 21, "아가": 22, "이사야": 23, "예레미야": 24, "예레미야애가": 25,
    "에스겔": 26, "다니엘": 27, "호세아": 28, "요엘": 29, "아모스": 30,
    "오바댜": 31, "요나": 32, "미가": 33, "나훔": 34, "하박국": 35,
    "스바냐": 36, "학개": 37, "스가랴": 38, "말라기": 39,
    "마태복음": 40, "마가복음": 41, "누가복음": 42, "요한복음": 43, "사도행전": 44,
    "로마서": 45, "고린도전서": 46, "고린도후서": 47, "갈라디아서": 48, "에베소서": 49,
    "빌립보서": 50, "골로새서": 51, "데살로니가전서": 52, "데살로니가후서": 53, "디모데전서": 54,
    "디모데후서": 55, "디도서": 56, "빌레몬서": 57, "히브리서": 58, "야고보서": 59,
    "베드로전서": 60, "베드로후서": 61, "요한일서": 62, "요한이서": 63, "요한삼서": 64,
    "유다서": 65, "요한계시록": 66
}


def _get_bible_name_by_index(index):
    bible_names = list(book_to_number.keys())
    return bible_names[index - 1] if 1 <= index <= 66 else "성경"


def _get_bible_total_chapters(name):
    chapter_counts = {
        "창세기": 50, "출애굽기": 40, "레위기": 27, "민수기": 36, "신명기": 34,
        "여호수아": 24, "사사기": 21, "룻기": 4, "사무엘상": 31, "사무엘하": 24,
        "열왕기상": 22, "열왕기하": 25, "역대상": 29, "역대하": 36, "에스라": 10,
        "느헤미야": 13, "에스더": 10, "욥기": 42, "시편": 150, "잠언": 31,
        "전도서": 12, "아가": 8, "이사야": 66, "예레미야": 52, "예레미야애가": 5,
        "에스겔": 48, "다니엘": 12, "호세아": 14, "요엘": 3, "아모스": 9,
        "오바댜": 1, "요나": 4, "미가": 7, "나훔": 3, "하박국": 3,
        "스바냐": 3, "학개": 2, "스가랴": 14, "말라기": 4,
        "마태복음": 28, "마가복음": 16, "누가복음": 24, "요한복음": 21, "사도행전": 28,
        "로마서": 16, "고린도전서": 16, "고린도후서": 13, "갈라디아서": 6, "에베소서": 6,
        "빌립보서": 4, "골로새서": 4, "데살로니가전서": 5, "데살로니가후서": 3, "디모데전서": 6,
        "디모데후서": 4, "디도서": 3, "빌레몬서": 1, "히브리서": 13, "야고보서": 5,
        "베드로전서": 5, "베드로후서": 3, "요한일서": 5, "요한이서": 1, "요한삼서": 1,
        "유다서": 1, "요한계시록": 22
    }
    return chapter_counts.get(name, 1)


def _generate_date_hash(date_str, modulo=100):
    return int(hashlib.md5(date_str.encode()).hexdigest(), 16) % modulo


def _get_korean_weekday(date):
    return {
        "Monday": "월요일", "Tuesday": "화요일", "Wednesday": "수요일",
        "Thursday": "목요일", "Friday": "금요일", "Saturday": "토요일", "Sunday": "일요일"
    }.get(date.strftime("%A"), date.strftime("%A"))


async def get_bible_chapter_content(book_name: str, chapter: int, db_pool, version="korHRV") -> str:
    book_num = book_to_number.get(book_name)
    if not book_num:
        return f"[성경 오류] '{book_name}'은 유효한 책 이름이 아닙니다."

    table = f"bible_{version}"
    sql = f"""
        SELECT verse, verse_text
        FROM {table}
        WHERE book = %s AND chapter = %s
        ORDER BY verse ASC
    """

    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, (book_num, chapter))
            rows = await cur.fetchall()

    if not rows:
        return f"[성경 오류] {book_name} {chapter}장은 찾을 수 없습니다."

    return "\n".join(f"{row['verse']} {row['verse_text'].strip()}" for row in rows) + f"\n📖 {book_name} {chapter}장"


async def process_template_variables_async(message, context=None):
    if not message:
        return message

    context = context or {}
    now = datetime.now(KST)
    date_str = now.strftime("%Y-%m-%d")
    date_hash = _generate_date_hash(date_str, 66)
    bible_index = date_hash + 1
    bible_name = _get_bible_name_by_index(bible_index)
    chapter = (_generate_date_hash(f"{date_str}-{now.day}", _get_bible_total_chapters(bible_name))) + 1

    variables = {
        "TODAY": date_str,
        "DATE": date_str,
        "DATE_KR": now.strftime("%Y년 %m월 %d일"),
        "TIME": now.strftime("%H:%M:%S"),
        "WEEKDAY_KR": _get_korean_weekday(now),
        "WEEKDAY_SHORT_KR": _get_korean_weekday(now)[:1],
        "DATE_SIMPLE_KR": now.strftime("%m-%d") + f"({_get_korean_weekday(now)[:1]})" + now.strftime(", %H:%M"),
        "USERNAME": context.get("username", ""),
        "ROOM": context.get("room", ""),
        "RANDOM_BIBLE": bible_name,
        "RANDOM_BIBLE_CHAPTER": f"{bible_name} {chapter}장",
    }

    for key, value in variables.items():
        if value is None:
            value = ""
        message = message.replace(f"{{{{{key}}}}}", str(value))

    if "{{RANDOM_BIBLE_CHAPTER_CONTENT}}" in message:
        passage = await get_bible_chapter_content(bible_name, chapter, g.db_pool)
        message = message.replace("{{RANDOM_BIBLE_CHAPTER_CONTENT}}", passage)

    return message
