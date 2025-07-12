import requests
from bs4 import BeautifulSoup

def fetch_genesis_chapter(chapter_num):
    url = f"http://kingjamesbiblekorea.com/Genesis/{chapter_num}"
    response = requests.get(url)
    if response.status_code != 200:
        print(f"[ERROR] {chapter_num}장 가져오기 실패: {response.status_code}")
        return []

    soup = BeautifulSoup(response.text, 'html.parser')
    verse_blocks = soup.select(".verse")  # 실제 클래스 확인 필요

    data = []
    for block in verse_blocks:
        try:
            number = int(block.select_one(".verse-num").text.strip().replace('.', ''))
            text = block.select_one(".verse-text").text.strip()
            data.append((chapter_num, number, text))
        except Exception as e:
            print(f"[WARN] 파싱 실패: {e}")
    return data

def generate_insert_sql(data, book=1):
    lines = []
    for chapter, verse, verse_text in data:
        verse_text = verse_text.replace("'", "''")  # SQL escape
        lines.append(f"({book}, {chapter}, {verse}, '{verse_text}')")
    values_sql = ",\n".join(lines)
    full_sql = (
        "INSERT INTO bible_korSKJV (book, chapter, verse, verse_text)\n"
        f"VALUES\n{values_sql};"
    )
    return full_sql

# 예: 창세기 1장부터 2장까지 저장
all_sql = []
for chapter in range(1, 3):  # 원하는 범위로 조절
    verses = fetch_genesis_chapter(chapter)
    if verses:
        sql = generate_insert_sql(verses, book=1)
        all_sql.append(sql)

# SQL 파일로 저장
with open("genesis_1_2_korSKJV.sql", "w", encoding="utf-8") as f:
    f.write("\n\n".join(all_sql))

print("[완료] SQL 파일 생성됨: genesis_1_2_korSKJV.sql")
