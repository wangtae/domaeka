"""
로스 AI 법률 검색 서비스 모듈
- #로스 {질문} 명령어 처리
- ross.hyowontec.com API 호출 및 결과 포맷
"""
import httpx
import re

ROSS_API_URL = "https://ross.hyowontec.com/api/search"
STATIC_COOKIE = "_ga_EY3HX1QV54=GS2.1.s1747670349$o1$g1$t1747670378$j0$l0$h0; _ga=GA1.1.701042216.1747670350"  # 실제 값으로 교체 필요
STATIC_CSRF_TOKEN = "ct5cdbnk8cmav9sw39"  # 실제 값으로 교체 필요

async def handle_ross_ai_command(context, prompt):
    """
    #로스 {질문} 명령어 처리
    Args:
        context (dict): 메시지 컨텍스트
        prompt (str): 질문 내용
    Returns:
        list[str]: 응답 메시지 리스트
    """
    if not prompt:
        return [
            "[로스 AI 법률 검색 사용법]\n\n#로스 (질문 내용)\n예시: #로스 개인정보 유출시 처벌은 어떻게 되나요?\n출처: https://ross.hyowontec.com/"
        ]

    request_data = {
        "query": prompt,
        "mode": "refined",
        "top_k": 30,
        "use_llm": False,
        "adaptive_search": True,
        "use_summary": True,
        "client_ip": "8.8.8.8"
    }
    headers = {
        "Accept": "*/*",
        "Accept-Language": "ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7",
        "Connection": "keep-alive",
        "Content-Type": "application/json;charset=UTF-8",
        "Cookie": STATIC_COOKIE,
        "Origin": "https://ross.hyowontec.com",
        "Referer": "https://ross.hyowontec.com/",
        "User-Agent": "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36",
        "X-CSRF-Token": STATIC_CSRF_TOKEN,
    }

    try:
        async with httpx.AsyncClient(timeout=30, verify=False) as client:
            resp = await client.post(ROSS_API_URL, json=request_data, headers=headers)
            resp.raise_for_status()
            data = resp.json()
    except Exception as e:
        return [f"[로스 AI] API 요청 실패: {e}"]

    results = data.get("results", [])
    if not results:
        return ["검색 결과가 없습니다."]

    messages = []
    for i, item in enumerate(results[:3]):  # 상위 3개만 출력
        title = item.get("title", "")
        law_name = item.get("law_name", "")
        similarity = item.get("similarity", 0)
        summary = item.get("summary", "")
        url = item.get("url", "")

        bar = create_progress_bar(similarity)
        msg = f"[{i+1}] {title}\n{bar}\n{law_name}\n{format_summary(summary)}\n{url}"
        messages.append(msg)

    return messages

def create_progress_bar(similarity, bar_length=10):
    try:
        similarity = float(similarity)
    except Exception:
        return "N/A"
    percentage = round(similarity * 100)
    filled = int(similarity * bar_length)
    empty = bar_length - filled
    return f"[{'█'*filled}{'░'*empty}] {percentage}%"

def format_summary(text):
    if not text:
        return ""
    text = text.replace("\n\n", "\n")
    text = re.sub(r"(\d+\.)\n", r"\1 ", text)
    return text.strip() 