from core import globals as g
import aiomysql
import re
import time

# 통합된 성경 정보 정의
BIBLE_VERSIONS = [
    {
        "code": "korHRV",
        "name": "개역한글",
        "display_name": "개역한글",
        "hashtag": "#개역한글",
        "is_admin_only": False
    },
    {
        "code": "korKRV",
        "name": "개역개정",
        "display_name": "개역개정",
        "hashtag": "#개역개정",
        "is_admin_only": True
    },
    {
        "code": "korSKJV",
        "name": "표준킹제임스",
        "display_name": "표준킹제임스",
        "hashtag": "#표준킹제임스",
        "is_admin_only": False
    },
    {
        "code": "korESY",
        "name": "쉬운말",
        "display_name": "쉬운말",
        "hashtag": "#쉬운말",
        "is_admin_only": False
    },
    {
        "code": "korMOD",
        "name": "현대어",
        "display_name": "현대어",
        "hashtag": "#현대어",
        "is_admin_only": False
    },
    {
        "code": "web",
        "name": "WEB",
        "display_name": "WEB (1994)",
        "hashtag": "#WEB",
        "is_admin_only": False
    },
    {
        "code": "ylt",
        "name": "YLT",
        "display_name": "YLT (1862)",
        "hashtag": "#YLT",
        "is_admin_only": False
    },
    {
        "code": "asv1901",
        "name": "ASV",
        "display_name": "ASV (1901)",
        "hashtag": "#ASV",
        "is_admin_only": False
    },
    {
        "code": "kjv1769",
        "name": "KJV",
        "display_name": "KJV (1769)",
        "hashtag": "#KJV",
        "is_admin_only": False
    },
    {
        "code": "kjv1611",
        "name": "KJV (1611)",
        "display_name": "KJV (1611)",
        "hashtag": "#KJV1611",
        "is_admin_only": False
    }
]

# 기존 변수들을 이 통합 정보에서 생성
BIBLE_VERSION_NAME = {version["code"]: version["display_name"] for version in BIBLE_VERSIONS}
HASHTAG_TO_VERSION = {version["hashtag"]: version["code"] for version in BIBLE_VERSIONS}
ALL_AVAILABLE_BIBLES = [
    {"code": version["code"], "name": version["name"], "is_admin_only": version["is_admin_only"]}
    for version in BIBLE_VERSIONS
]

# 추가 유틸리티 상수들
ALL_BIBLE_CODES = [bible["code"] for bible in ALL_AVAILABLE_BIBLES]
ADMIN_ONLY_BIBLE_VERSIONS = {bible["code"] for bible in ALL_AVAILABLE_BIBLES if bible["is_admin_only"]}

DEFAULT_HIGHLIGHT = "*"

book_mapping = {
    "창": 1, "출": 2, "레": 3, "민": 4, "신": 5,
    "수": 6, "삿": 7, "룻": 8, "삼상": 9, "삼하": 10,
    "왕상": 11, "왕하": 12, "대상": 13, "대하": 14,
    "스": 15, "느": 16, "에": 17, "욥": 18, "시": 19,
    "잠": 20, "전": 21, "아": 22, "사": 23, "렘": 24, "애": 25,
    "겔": 26, "단": 27, "호": 28, "욜": 29, "암": 30,
    "옵": 31, "욘": 32, "미": 33, "나": 34, "합": 35,
    "습": 36, "학": 37, "슥": 38, "말": 39,
    "마": 40, "막": 41, "눅": 42, "요": 43,
    "행": 44, "롬": 45, "고전": 46, "고후": 47,
    "갈": 48, "엡": 49, "빌": 50, "골": 51,
    "살전": 52, "살후": 53, "딤전": 54, "딤후": 55,
    "딛": 56, "몬": 57, "히": 58, "약": 59,
    "벧전": 60, "벧후": 61, "요일": 62, "요이": 63,
    "요삼": 64, "유": 65, "계": 66
}

number_to_eng_book = {
    1: "Gen", 2: "Exo", 3: "Lev", 4: "Num", 5: "Deut",
    6: "Josh", 7: "Judg", 8: "Ruth", 9: "1Sam", 10: "2Sam",
    11: "1Kgs", 12: "2Kgs", 13: "1Chr", 14: "2Chr", 15: "Ezra",
    16: "Neh", 17: "Esth", 18: "Job", 19: "Ps", 20: "Prov",
    21: "Eccl", 22: "Song", 23: "Isa", 24: "Jer", 25: "Lam",
    26: "Ezek", 27: "Dan", 28: "Hos", 29: "Joel", 30: "Amos",
    31: "Obad", 32: "Jonah", 33: "Mic", 34: "Nah", 35: "Hab",
    36: "Zeph", 37: "Hag", 38: "Zech", 39: "Mal",
    40: "Matt", 41: "Mark", 42: "Luke", 43: "John", 44: "Acts",
    45: "Rom", 46: "1Cor", 47: "2Cor", 48: "Gal", 49: "Eph",
    50: "Phil", 51: "Col", 52: "1Thess", 53: "2Thess", 54: "1Tim",
    55: "2Tim", 56: "Titus", 57: "Phlm", 58: "Heb", 59: "Jas",
    60: "1Pet", 61: "2Pet", 62: "1John", 63: "2John", 64: "3John",
    65: "Jude", 66: "Rev"
}

# book_mapping 아래에 추가
full_to_short_book = {
    "창세기": "창", "출애굽기": "출", "레위기": "레", "민수기": "민", "신명기": "신",
    "여호수아": "수", "사사기": "삿", "룻기": "룻", "사무엘상": "삼상", "사무엘하": "삼하",
    "열왕기상": "왕상", "열왕기하": "왕하", "역대상": "대상", "역대하": "대하",
    "에스라": "스", "느헤미야": "느", "에스더": "에", "욥기": "욥", "시편": "시",
    "잠언": "잠", "전도서": "전", "아가": "아", "이사야": "사", "예레미야": "렘",
    "예레미야애가": "애", "에스겔": "겔", "다니엘": "단", "호세아": "호", "요엘": "욜",
    "아모스": "암", "오바댜": "옵", "요나": "욘", "미가": "미", "나훔": "나",
    "하박국": "합", "스바냐": "습", "학개": "학", "스가랴": "슥", "말라기": "말",
    "마태복음": "마", "마가복음": "막", "누가복음": "눅", "요한복음": "요",
    "사도행전": "행", "로마서": "롬", "고린도전서": "고전", "고린도후서": "고후",
    "갈라디아서": "갈", "에베소서": "엡", "빌립보서": "빌", "골로새서": "골",
    "데살로니가전서": "살전", "데살로니가후서": "살후", "디모데전서": "딤전", "디모데후서": "딤후",
    "디도서": "딛", "빌레몬서": "몬", "히브리서": "히", "야고보서": "약",
    "베드로전서": "벧전", "베드로후서": "벧후", "요한일서": "요일", "요한이서": "요이",
    "요한삼서": "요삼", "유다서": "유", "요한계시록": "계"
}

book_order = list(book_mapping.keys())
book_to_number = book_mapping
number_to_book = {v: k for k, v in book_mapping.items()}


# schedule-rooms.json에서 가져온 Bible 버전을 표준화하는 함수 추가
def normalize_bible_version(version):
    # 대소문자 정규화 (WEB -> web)
    if version.lower() == "web":
        return "web"
    return version


def get_book_range(start_book, end_book=None):
    if start_book == "전체":
        return (1, 66)
    if start_book == "구약":
        return (1, 39)
    if start_book == "신약":
        return (40, 66)

    if start_book not in book_to_number:
        return None

    if end_book and end_book not in book_to_number:
        return None

    start_idx = book_order.index(start_book)
    end_idx = book_order.index(end_book) if end_book else start_idx

    if start_idx > end_idx:
        return None

    return (book_to_number[book_order[start_idx]], book_to_number[book_order[end_idx]])


async def is_admin(channel_id, user_hash):
    admin_users = getattr(g, 'ADMIN_USERS', [])
    print(f"[DEBUG] 관리자 체크: channel_id={channel_id}, user_hash={user_hash}")
    for admin in admin_users:
        print(f"[DEBUG] 비교 대상: {admin}")
        if admin['channel_id'] == channel_id and admin['user_hash'] == user_hash:
            print(f"[DEBUG] ✅ 관리자 인증 성공")
            return True
    print(f"[DEBUG] ❌ 관리자 인증 실패")
    return False


# schedule-rooms.json 설정에서 기본 성경 버전 가져오기
def get_default_bibles_from_config(channel_id, user_hash=None):
    # g.schedule_rooms에서 설정 찾기
    schedule_data = getattr(g, 'schedule_rooms', {})

    # 관리자 여부 확인
    is_admin_user = False
    if user_hash:
        # 비동기 함수를 동기 컨텍스트에서 호출하는 문제를 피하기 위해
        # 이 함수에서는 관리자 체크를 하지 않고, 호출하는 쪽에서 전달받음
        is_admin_user = user_hash == 'is_admin'  # 실제 구현 시 이 부분은 수정 필요

    # 기본적으로 관리자 전용이 아닌 성경 목록만 추출
    non_admin_bibles = [bible["code"] for bible in ALL_AVAILABLE_BIBLES if not bible["is_admin_only"]]

    selected_versions = None

    for bot_name, rooms in schedule_data.items():
        if channel_id in rooms:
            room_config = rooms[channel_id]

            # 2진수 문자열 형식 처리
            if "default_bibles" in room_config and isinstance(room_config["default_bibles"], str):
                binary_pattern = room_config["default_bibles"]
                print(f"[DEBUG] 2진수 형식의 default_bibles 발견: {binary_pattern}")

                # 패턴 길이 조정 (부족하면 앞에 0 추가)
                if len(binary_pattern) < len(non_admin_bibles):
                    binary_pattern = binary_pattern.zfill(len(non_admin_bibles))

                # 패턴이 너무 길면 뒷부분만 사용 (오른쪽 정렬)
                if len(binary_pattern) > len(non_admin_bibles):
                    binary_pattern = binary_pattern[-len(non_admin_bibles):]

                selected_versions = []
                for i, bit in enumerate(binary_pattern):
                    if i >= len(non_admin_bibles):
                        break

                    if bit == '1':
                        selected_versions.append(non_admin_bibles[i])

            # 기존 리스트 형식 처리 (하위 호환성 유지)
            elif "default_bibles" in room_config and isinstance(room_config["default_bibles"], list):
                selected_versions = [v.lower() if v.upper() == "WEB" else v for v in room_config["default_bibles"]]
                # 관리자 전용 성경 필터링 (일반 사용자용)
                if not is_admin_user:
                    selected_versions = [v for v in selected_versions if v not in ADMIN_ONLY_BIBLE_VERSIONS]

    # 설정이 없으면 기본값 사용
    if selected_versions is None:
        selected_versions = non_admin_bibles

    # ✅ 관리자인 경우 관리자용 성경 자동 추가
    if is_admin_user:
        admin_only_bibles = [bible["code"] for bible in ALL_AVAILABLE_BIBLES if bible["is_admin_only"]]
        # 이미 포함된 성경은 중복 추가하지 않음
        for admin_bible in admin_only_bibles:
            if admin_bible not in selected_versions:
                selected_versions.append(admin_bible)

    return selected_versions


async def check_bible_table_exists(db_pool, version):
    """
    해당 성경 버전의 테이블이 존재하는지 확인
    """
    table = f"bible_{version}"
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor() as cur:
            # 테이블 존재 여부 확인하는 SQL
            await cur.execute(f"""
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = %s
            """, (table,))
            result = await cur.fetchone()
            return result[0] > 0


async def bible_query(command_type, prompt, channel_id=None, user_hash=None):
    db_pool = g.db_pool
    if not db_pool:
        return "[DB 오류] 데이터베이스 연결이 없습니다."

    prompt = prompt.strip()
    print(f"[DEBUG] 요청 시작: {command_type}, {prompt}, {channel_id}")

    # ✅ 관리자 여부 확인 - 명확하게 처리
    is_admin_user = False
    if channel_id and user_hash:
        is_admin_user = await is_admin(channel_id, user_hash)
    print(f"[DEBUG] 관리자 여부: {is_admin_user}")

    # ✅ 해시태그로 선택된 번역본 추출
    selected_versions = []

    # #ALL 또는 #all 매개변수 체크
    all_bible_pattern = r'#(?:ALL|all)\b'
    if re.search(all_bible_pattern, prompt):
        print(f"[DEBUG] #ALL 또는 #all 매개변수 발견, 전체 성경 사용")
        # 관리자 여부에 따라 접근 가능한 모든 성경 사용
        for bible in ALL_AVAILABLE_BIBLES:
            if not is_admin_user and bible["is_admin_only"]:
                print(f"[DEBUG] 관리자 전용 성경 {bible['code']} 건너뜀 (#ALL)")
                continue
            selected_versions.append(bible["code"])

        # 프롬프트에서 #ALL 또는 #all 제거
        prompt = re.sub(all_bible_pattern, '', prompt).strip()
        print(f"[DEBUG] #ALL/#all 제거 후 남은 프롬프트: '{prompt}'")

        # 다른 패턴 처리 건너뛰기
        binary_pattern = None

    else:
        # 이진수 패턴 해시태그 확인 (#1001 같은 형식)
        binary_pattern = None
        binary_tags = re.findall(r'#([01]+)(\b|$)', prompt)  # 단어 경계 또는 문자열 끝 확인

        if binary_tags:
            binary_pattern = binary_tags[0][0]  # 튜플의 첫 번째 요소가 패턴
            # 이진수 패턴 해시태그 제거
            prompt = re.sub(r'#([01]+)(\b|$)', '', prompt).strip()
            print(f"[DEBUG] 이진수 패턴 발견 및 제거: {binary_pattern}, 남은 프롬프트: '{prompt}'")

        # 일반 해시태그 처리
        for tag, version in HASHTAG_TO_VERSION.items():
            if tag in prompt:
                # 관리자 전용 성경 체크
                if version in ADMIN_ONLY_BIBLE_VERSIONS and not is_admin_user:
                    print(f"[DEBUG] 관리자 전용 성경 {version} 건너뜀 (일반 사용자)")
                    continue
                selected_versions.append(version)
                prompt = prompt.replace(tag, '').strip()
                print(f"[DEBUG] 해시태그로 성경 {version} 추가")

    # ✅ 아무 해시태그 없을 경우 버전 설정
    if not selected_versions and not binary_pattern:
        # schedule-rooms.json 설정에서 기본 성경 버전 확인
        default_bibles = get_default_bibles_from_config(channel_id) if channel_id else None
        print(f"[DEBUG] 설정에서 가져온 기본 성경: {default_bibles}")

        if default_bibles:
            # 설정된 기본 성경 버전을 사용
            selected_versions = default_bibles.copy()
            print(f"[DEBUG] 설정에서 가져온 성경 사용: {selected_versions}")
        else:
            # 기본 설정 사용
            if command_type == 'bible_random':
                selected_versions = ['korHRV', 'korSKJV', 'kjv1769']
            else:
                # 순서를 유지하면서 접근 가능한 성경 선택
                selected_versions = []
                for bible in ALL_AVAILABLE_BIBLES:
                    if not is_admin_user and bible["is_admin_only"]:
                        print(f"[DEBUG] 관리자 전용 성경 {bible['code']} 건너뜀 (기본 목록)")
                        continue
                    selected_versions.append(bible["code"])
                print(f"[DEBUG] 기본 성경 목록 생성: {selected_versions}")

    # ✅ 관리자인 경우 관리자용 성경을 포함 (이진수 패턴이 없는 경우에만)
    if is_admin_user and not binary_pattern and not re.search(all_bible_pattern, prompt):
        # 현재 선택된 성경 목록에서 관리자 전용 성경이 빠져있는지 확인
        admin_only_bibles = [bible["code"] for bible in ALL_AVAILABLE_BIBLES if bible["is_admin_only"]]

        # 관리자 전용 성경이 누락된 경우에만 추가 (순서 유지를 위해)
        full_versions = []
        admin_bibles_added = set()

        # ALL_AVAILABLE_BIBLES 순서대로 순회하며 관리자 성경 포함
        for bible in ALL_AVAILABLE_BIBLES:
            code = bible["code"]

            # 관리자 전용 성경이면 추가
            if bible["is_admin_only"]:
                if code not in selected_versions:
                    full_versions.append(code)
                    admin_bibles_added.add(code)
                    print(f"[DEBUG] 관리자 전용 성경 추가: {code}")
                else:
                    full_versions.append(code)
            # 일반 성경이면 선택된 경우에만 추가
            elif code in selected_versions:
                full_versions.append(code)

        # 추가된 성경이 있으면 로그 출력
        if admin_bibles_added:
            print(f"[DEBUG] 관리자 전용 성경 추가됨: {admin_bibles_added}")
            selected_versions = full_versions

    # ✅ 이진수 패턴이 있는 경우 선택된 버전 재정의
    if binary_pattern:
        # 사용 가능한 성경 목록 - 관리자 여부에 따라 필터링하되 순서 유지
        available_versions = []
        for bible in ALL_AVAILABLE_BIBLES:
            if not is_admin_user and bible["is_admin_only"]:
                continue
            available_versions.append(bible["code"])

        print(f"[DEBUG] 이진수 패턴 적용 기준 성경 순서: {available_versions}")

        # 선택된 버전 초기화
        binary_selected_versions = []

        # 패턴 길이 조정 (왼쪽 정렬로 변경 - 부족하면 뒤에 0 추가)
        while len(binary_pattern) < len(available_versions):
            binary_pattern = binary_pattern + '0'  # 패턴을 왼쪽 정렬로 처리

        # 패턴이 너무 길면 앞부분만 사용 (왼쪽 정렬)
        if len(binary_pattern) > len(available_versions):
            binary_pattern = binary_pattern[:len(available_versions)]

        print(f"[DEBUG] 조정된 이진수 패턴: {binary_pattern}, 길이: {len(binary_pattern)}")

        # 이진수 패턴 적용
        for i, bit in enumerate(binary_pattern):
            if i >= len(available_versions):
                break

            if bit == '1':
                version = available_versions[i]
                print(f"[DEBUG] 패턴에 의해 선택된 버전: {version} (인덱스 {i})")
                binary_selected_versions.append(version)

        # 이진수 패턴으로 선택된 버전이 있는 경우 적용
        if binary_selected_versions:
            selected_versions = binary_selected_versions
        else:
            print("[WARNING] 이진수 패턴으로 선택된 버전이 없어 기본값 유지")

    # 선택된 버전이 없으면 기본값 사용
    if not selected_versions:
        selected_versions = ['korHRV']

    print(f"[DEBUG] 최종 선택된 성경 버전들: {selected_versions}")

    # ✅ 존재하는 테이블만 필터링 (순서 유지)
    valid_versions = []
    for version in selected_versions:
        table_exists = await check_bible_table_exists(db_pool, version)
        if table_exists:
            valid_versions.append(version)
        else:
            print(f"[WARNING] 테이블 bible_{version}이 존재하지 않습니다. 건너뜁니다.")

    # 유효한 버전이 없으면 기본 성경 사용 시도
    if not valid_versions:
        print("[WARNING] 유효한 성경 버전이 없습니다. 기본 성경(korHRV)을 사용합니다.")
        if await check_bible_table_exists(db_pool, "korHRV"):
            valid_versions = ["korHRV"]
        else:
            return "[성경 검색 오류] 사용 가능한 성경 버전이 없습니다."

    print(f"[DEBUG] 실제 사용 가능한 성경 버전들: {valid_versions}")

    results = []

    for idx, version in enumerate(valid_versions):
        if command_type == 'bible':
            if is_bible_passage_format(prompt):
                r = await single_version_query(prompt, db_pool, version)
            else:
                match = re.match(r'^([가-힣]+)\s+(\d+)$', prompt)
                if match:
                    book_name = match.group(1)
                    chapter = int(match.group(2))
                    book_num = book_to_number.get(book_name)
                    if not book_num:
                        continue
                    table = f"bible_{version}"
                    end_verse = await get_last_verse(db_pool, table, book_num, chapter)
                    full_range = f"{book_name} {chapter}:1~{end_verse}"
                    r = await single_version_query(full_range, db_pool, version)
                else:
                    r = await search_texts(prompt, db_pool, version)
        elif command_type == 'bible_random':
            ref = await get_random_reference(db_pool)
            if not ref:
                return "[성경 검색 오류] 랜덤 구절을 찾을 수 없습니다."

            for idx, version in enumerate(valid_versions):
                r = await get_specific_verse(db_pool, version, ref['book'], ref['chapter'], ref['verse'])
                if r:
                    version_name = BIBLE_VERSION_NAME.get(version, version)
                    body = f"📖 {version_name}\n\n{r}"
                    if idx == 0:
                        results.append(body)
                    else:
                        results.append(f"\n\n\n{body}")

            # ✅ 랜덤 처리 후 중복 실행 방지
            return "".join(results)

        elif command_type == 'bible_search_all':
            r = await search_texts(prompt, db_pool, version, book_range=(1, 66))
        elif command_type == 'bible_search_old':
            r = await search_texts(prompt, db_pool, version, book_range=(1, 39))
        elif command_type == 'bible_search_new':
            r = await search_texts(prompt, db_pool, version, book_range=(40, 66))
        else:
            r = None

        if r:
            version_name = BIBLE_VERSION_NAME.get(version, version)
            body = f"📖 {version_name}\n\n{r}"
            if idx == 0:
                results.append(body)
            else:
                results.append(f"\n\n\n{body}")

    return "".join(results) if results else "[성경 검색 오류] 출력 가능한 결과가 없습니다."


async def get_random_reference(db_pool):
    sql = "SELECT book, chapter, verse FROM bible_korHRV ORDER BY RAND() LIMIT 1"
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql)
            row = await cur.fetchone()
    return row


async def get_specific_verse(db_pool, version, book, chapter, verse):
    table = f"bible_{version}"
    sql = f"""
        SELECT verse_text FROM {table}
        WHERE book = %s AND chapter = %s AND verse = %s
    """
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, (book, chapter, verse))
            row = await cur.fetchone()
    if row:
        return f"{row['verse_text']} {get_book_name(book, version)} {chapter}:{verse}"
    return None


def is_bible_passage_format(text):
    pattern = r'^[가-힣]+\s*\d+:\d+([~\-](\d+:)?\d+)?$'
    return bool(re.match(pattern, text.strip()))


async def single_version_query(search_text, db_pool, version='korHRV'):
    result = await fetch_bible_passage(db_pool, search_text, version)
    if result:
        return result
    return "[성경 검색 오류] 검색 결과가 없습니다."


async def dual_version_query(search_text, db_pool):
    korhrv = await fetch_bible_passage(db_pool, search_text, 'korHRV')
    korkrv = await fetch_bible_passage(db_pool, search_text, 'korKRV')

    if not korhrv and not korkrv:
        return "[성경 검색 오류] 검색 결과가 없습니다."

    return (
        f"📖 {BIBLE_VERSION_NAME['korHRV']}\n\n{korhrv or '결과 없음'}\n\n"
        f"📖 {BIBLE_VERSION_NAME['korKRV']}\n\n{korkrv or '결과 없음'}"
    )


async def random_verse_query(db_pool, version='korHRV'):
    table = f"bible_{version}"
    sql = f"""
        SELECT book, chapter, verse, verse_text
        FROM {table}
        ORDER BY RAND()
        LIMIT 1
    """
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql)
            row = await cur.fetchone()
    if not row:
        return "[성경 검색 오류] 랜덤 구절을 찾을 수 없습니다."

    return f"{row['verse_text']} {get_book_name(row['book'], version)} {row['chapter']}:{row['verse']}"


async def fetch_bible_passage(db_pool, text, version):
    table = f"bible_{version}"
    match = re.match(
        r'(?P<book>[가-힣]+)\s*(?P<start_chap>\d+):(?P<start_verse>\d+)(?:[~\-](?:(?P<end_chap>\d+):)?(?P<end_verse>\d+))?',
        text.strip()
    )

    if not match:
        return "[성경 검색 오류] 형식 오류! 예: 창 1:1 또는 창 1:1~3 또는 창 1:1~2:3"

    book = match.group('book')
    # ✅ 풀네임을 약칭으로 변환
    if book in full_to_short_book:
        book = full_to_short_book[book]

    book_num = book_to_number.get(book)
    if not book_num:
        return f"[성경 검색 오류] 지원되지 않는 책 이름: {book}"

    start_chap = int(match.group('start_chap'))
    start_verse = int(match.group('start_verse'))
    end_chap = int(match.group('end_chap')) if match.group('end_chap') else start_chap
    end_verse = int(match.group('end_verse')) if match.group('end_verse') else start_verse

    if start_chap == end_chap:
        sql = f"""
            SELECT book, chapter, verse, verse_text
            FROM {table}
            WHERE book = %s AND chapter = %s AND verse BETWEEN %s AND %s
            ORDER BY verse ASC
        """
        params = (book_num, start_chap, start_verse, end_verse)
    elif start_chap == end_chap and start_verse == end_verse:
        sql = f"""
            SELECT book, chapter, verse, verse_text
            FROM {table}
            WHERE book = %s AND chapter = %s AND verse = %s
        """
        params = (book_num, start_chap, start_verse)
    else:
        sql = f"""
            SELECT book, chapter, verse, verse_text
            FROM {table}
            WHERE book = %s
              AND (
                (chapter = %s AND verse >= %s)
                OR (chapter > %s AND chapter < %s)
                OR (chapter = %s AND verse <= %s)
              )
            ORDER BY chapter ASC, verse ASC
        """
        params = (
            book_num,
            start_chap, start_verse,
            start_chap, end_chap,
            end_chap, end_verse
        )

    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, params)
            rows = await cur.fetchall()

    return format_bible_passage(rows, book_num, start_chap, start_verse, end_chap, end_verse, version)


async def get_last_verse(db_pool, table, book_num, chapter):
    sql = f"SELECT MAX(verse) as max_verse FROM {table} WHERE book = %s AND chapter = %s"
    async with db_pool.acquire() as conn:
        await conn.set_charset('utf8mb4')
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, (book_num, chapter))
            row = await cur.fetchone()
            return row['max_verse'] if row else 150  # fallback


async def search_texts(prompt, db_pool, version='korHRV', book_range=None):
    try:
        parsed = parse_advanced_prompt(prompt, book_range=book_range)
        if not parsed:
            return "[검색 오류] 파싱에 실패했습니다."
    except Exception as e:
        return f"[검색 오류] {str(e)}"

    sql = f"""
        SELECT book, chapter, verse, verse_text
        FROM bible_{version}
        WHERE {parsed['where']}
        ORDER BY book, chapter, verse
        LIMIT 1480
    """

    try:
        async with db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(sql, parsed['params'])
                rows = await cur.fetchall()

        if not rows:
            return f"[검색 결과 없음] 조건: {prompt}"

        result = [
            f"{get_book_name(r['book'], version)} {r['chapter']}:{r['verse']} - "
            f"{apply_highlight(r['verse_text'], parsed['highlight'], parsed['wrapper'])}"
            for r in rows
        ]

        return (
                f"🔍 총 {len(rows)}개 구절 검색됨 (키워드: '{parsed['wrapper']}')\n\n"
                + "\n\n".join(result)
        )

    except Exception as e:
        print(f"[DB ERROR] 텍스트 검색 실패: {e}")
        return "[DB 오류] 텍스트 검색 중 문제가 발생했습니다."


def parse_bible_parameter(parameter, channel_id, is_admin=False):
    """
    성경 파라미터 파싱 (# 형식 지원)
    """
    # 관리자 전용이 아닌 성경 목록만 추출
    available_bibles = ALL_BIBLE_CODES
    if not is_admin:
        available_bibles = [bible["code"] for bible in ALL_AVAILABLE_BIBLES if not bible["is_admin_only"]]

    # # 형식 확인 (예: "창 1:1 #101010")
    hash_pattern = r'#([01]+)'
    hash_match = re.search(hash_pattern, parameter)

    if hash_match:
        binary_format = hash_match.group(1)
        selected_bibles = []

        # 패턴 길이 조정
        if len(binary_format) < len(available_bibles):
            binary_format = binary_format.zfill(len(available_bibles))
        if len(binary_format) > len(available_bibles):
            binary_format = binary_format[-len(available_bibles):]

        for i, bit in enumerate(binary_format):
            if i >= len(available_bibles):
                break

            if bit == '1':
                selected_bibles.append(available_bibles[i])

        # 파라미터에서 # 부분 제거
        clean_parameter = re.sub(hash_pattern, '', parameter).strip()

        return clean_parameter, selected_bibles

    # # 형식이 없으면 기본 성경 사용
    return parameter, get_default_bibles_from_config(channel_id)


def parse_expression(expr):
    tokens = tokenize_expression(expr)

    def parse(tokens):
        stack = []
        local_params = []
        local_keywords = []

        negated_keywords = []

        prev_was_expr = False

        while tokens:
            token = tokens.pop(0)

            if token == '(':
                if prev_was_expr:
                    stack.append("AND")
                inner_sql, inner_params, inner_keywords = parse(tokens)
                stack.append(f"({inner_sql})")
                local_params.extend(inner_params)
                local_keywords.extend(inner_keywords)
                prev_was_expr = True

            elif token == ')':
                break

            elif token == '|':
                stack.append("OR")
                prev_was_expr = False

            elif token == '-':
                if not tokens:
                    raise ValueError("'-' 뒤에는 키워드가 와야 합니다.")
                neg_kw = tokens.pop(0)
                negated_keywords.append(neg_kw)
                continue  # 즉시 쿼리에는 넣지 않음

            else:
                if prev_was_expr:
                    stack.append("AND")
                stack.append("verse_text LIKE %s")
                local_params.append(f"%{token}%")
                local_keywords.append(token)
                prev_was_expr = True

        # 🔥 최종적으로 AND NOT 조건은 바깥에서 묶음 처리
        if stack:
            base_expr = f"({' '.join(stack)})"
        else:
            base_expr = ""

        for neg_kw in negated_keywords:
            base_expr += f" AND NOT verse_text LIKE %s"
            local_params.append(f"%{neg_kw}%")
            local_keywords.append(neg_kw)

        return base_expr, local_params, local_keywords

    return parse(tokens)


def extract_wrapper(prompt: str, default='*'):
    prompt = prompt.strip()
    # 'with '(굿)' 또는 "with "(굿)" 형식
    match = re.search(r'with\s*([\'"])(.+?)\1', prompt)
    if match:
        return match.group(2)
    # with (굿) ← 괄호 포함된 문자열도 인식
    match = re.search(r'with\s+(\S+)', prompt)
    if match:
        return match.group(1)
    return default


def tokenize_expression(expr):
    # ✅ 따옴표 감싼 전체 문자열 추출 (싱글/더블 모두 지원)
    quoted = re.findall(r"""(['\"])(.*?)\1""", expr)

    # ✅ 추출된 문자열을 임시 저장하고, 본문에서 제거
    quoted_map = {}
    for i, (_, content) in enumerate(quoted):
        key = f"__QUOTE{i}__"
        quoted_map[key] = content
        expr = expr.replace(f"'{content}'", key)
        expr = expr.replace(f'"{content}"', key)

    # ✅ 'with |' 또는 'with '|'' → wrapper 설정으로 처리
    wrapper_match = re.search(r"with\s*['\"]?\|(.*?)['\"]?$", expr)
    if wrapper_match:
        wrapper = '|'
        expr = re.sub(r"with\s*['\"]?\|(.*?)['\"]?$", '', expr)
        global DEFAULT_HIGHLIGHT
        DEFAULT_HIGHLIGHT = wrapper

    expr = re.sub(r'([()|\-])', r' \1 ', expr)
    tokens = expr.split()

    result = []
    for token in tokens:
        if token in quoted_map:
            result.append(quoted_map[token])
        else:
            result.append(token.strip('"\''))

    return [t for t in result if t]


def apply_highlight(text, keywords, wrapper=DEFAULT_HIGHLIGHT):
    for kw in sorted(set(keywords), key=len, reverse=True):
        text = re.sub(f"({re.escape(kw)})", f"{wrapper}\\1{wrapper}", text)
    return text


def get_book_name(book_num, version=None):
    if version and version.startswith("kjv") or version == "web":  # 영어 성경일 경우
        return number_to_eng_book.get(book_num, f"Book {book_num}")
    return number_to_book.get(book_num, f"책 {book_num}")


def format_bible_passage(rows, book_num, start_chap, start_verse, end_chap, end_verse, version=None):
    if not rows:
        return "[검색 결과 없음] 해당 구절을 찾을 수 없습니다."

    book_name = get_book_name(book_num, version)

    if len(rows) == 1:
        row = rows[0]
        return f"{row['verse_text']} {book_name} {row['chapter']}:{row['verse']}"

    if all(row['chapter'] == start_chap for row in rows):
        merged = " ".join([f"{row['verse']} {row['verse_text']}" for row in rows])
        return f"{merged} {book_name} {start_chap}:{start_verse}~{end_verse}"

    lines = [f"{row['chapter']}:{row['verse']} {row['verse_text']}" for row in rows]
    ref = f"{book_name} {start_chap}:{start_verse}~{end_chap}:{end_verse}"
    return "\n".join(lines) + f"\n\n📖 {ref}"


def parse_advanced_prompt(prompt, book_range=None):
    """
    고급 검색 프롬프트 파싱 (AND, OR, NOT 조건 지원)
    """
    # with 부분 처리 (하이라이트 사용자 정의)
    wrapper_pattern = r'\s+with\s+.*$'
    wrapper_match = re.search(wrapper_pattern, prompt)

    if wrapper_match:
        wrapper = extract_wrapper(prompt)
        prompt = re.sub(wrapper_pattern, '', prompt)
    else:
        wrapper = DEFAULT_HIGHLIGHT

    # 책 범위 확인 및 처리
    book_part = ""
    book_params = []

    # 전체, 구약, 신약 키워드 또는 특정 책 범위 처리
    first_word = prompt.split()[0] if prompt.split() else ""

    # 범위가 지정된 경우
    if book_range:
        start_book, end_book = book_range
        book_part = "book BETWEEN %s AND %s"
        book_params = [start_book, end_book]
    # 첫 단어가 '전체', '구약', '신약'인 경우
    elif first_word in ["전체", "구약", "신약"]:
        start_book, end_book = get_book_range(first_word)
        book_part = "book BETWEEN %s AND %s"
        book_params = [start_book, end_book]
        prompt = ' '.join(prompt.split()[1:])  # 첫 단어 제거
    # 특정 책 범위가 지정된 경우 (예: '창세기~레위기 생명')
    elif "~" in first_word or "-" in first_word:
        range_separator = '~' if '~' in first_word else '-'
        books = first_word.split(range_separator)

        if len(books) == 2:
            start_book_name, end_book_name = books
            book_range = get_book_range(start_book_name, end_book_name)

            if book_range:
                start_book, end_book = book_range
                book_part = "book BETWEEN %s AND %s"
                book_params = [start_book, end_book]
                prompt = ' '.join(prompt.split()[1:])  # 첫 단어 제거
    # 단일 책 지정된 경우 (예: '창세기 생명')
    elif first_word in book_to_number:
        book_num = book_to_number[first_word]
        book_part = "book = %s"
        book_params = [book_num]
        prompt = ' '.join(prompt.split()[1:])  # 첫 단어 제거

    # 빈 프롬프트 체크
    if not prompt.strip():
        return None

    # 쿼리 표현식 파싱
    sql_expr, params, keywords = parse_expression(prompt)

    # 최종 WHERE 절 구성
    if book_part and sql_expr:
        where_clause = f"{book_part} AND {sql_expr}"
        all_params = book_params + params
    elif book_part:
        where_clause = book_part
        all_params = book_params
    else:
        where_clause = sql_expr
        all_params = params

    return {
        "where": where_clause,
        "params": all_params,
        "highlight": keywords,
        "wrapper": wrapper
    }
