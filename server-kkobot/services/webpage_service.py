import aiohttp
from bs4 import BeautifulSoup
from datetime import datetime
import re
from urllib.parse import urlparse, parse_qs, unquote
from core.logger import logger
from core.utils.send_message import send_message_response
from core.utils.auth_utils import is_admin
from services.llm_fallback_service import call_llm_with_fallback
from core.globals import apply_kakao_readmore

# 채널별 마지막 요약 시간 기록
last_summary_time_web = {}


def extract_custom_model(prompt: str) -> tuple[str, dict | None]:
    """
    프롬프트 내에 'name:model' 형태로 지정된 LLM 모델이 있으면 분리해서 반환합니다.
    """
    match = re.search(r'\b([a-zA-Z0-9_-]+):([a-zA-Z0-9.\-_]+)', prompt)
    if match:
        name = match.group(1).strip()
        model = match.group(2).strip()
        cleaned_prompt = re.sub(r'\b[a-zA-Z0-9_-]+:[a-zA-Z0-9.\-_]+', '', prompt).strip()
        return cleaned_prompt, {"name": name, "model": model}
    return prompt, None


def is_valid_url(url: str) -> bool:
    return url.startswith("http://") or url.startswith("https://")


def extract_url_from_text(text: str) -> str | None:
    match = re.search(r'(https?://\S+)', text)
    return match.group(0) if match else None


async def resolve_redirect_url(url: str) -> str:
    """
    네이버 단축 URL(naver.me, link.naver.com)을 처리하여 최종 목적지 URL로 변환합니다.
    """
    try:
        parsed = urlparse(url)

        # 첫 번째 리디렉션: naver.me → link.naver.com
        if parsed.netloc == 'naver.me':
            async with aiohttp.ClientSession() as session:
                async with session.get(url, timeout=5) as response:
                    html = await response.text()
                    soup = BeautifulSoup(html, "html.parser")
                    meta_refresh = soup.find("meta", attrs={"http-equiv": "refresh"})
                    if meta_refresh:
                        content = meta_refresh["content"]
                        match = re.search(r'url=(.+)', content, re.IGNORECASE)
                        if match:
                            intermediate_url = match.group(1).strip()
                            logger.info(f"[REDIRECT_NAVER_ME] 첫 번째 단계 해석 성공 → {intermediate_url}")
                            url = intermediate_url  # URL 갱신 후 다시 처리

                            # 다시 파싱하여 다음 단계로 진행
                            parsed = urlparse(url)

        # 두 번째 리디렉션: link.naver.com → 실제 URL
        if parsed.netloc == 'link.naver.com' and parsed.path == '/bridge':
            qs = parse_qs(parsed.query)
            if 'url' in qs:
                final_url = unquote(qs['url'][0])
                if final_url.startswith(('http://', 'https://')):
                    logger.info(f"[REDIRECT_LINK_NAVER] 두 번째 단계 해석 성공 → {final_url}")
                    return final_url

        # 일반적인 리디렉트 처리
        async with aiohttp.ClientSession() as session:
            async with session.get(url, allow_redirects=True, timeout=5) as response:
                final_url = str(response.url)
                logger.info(f"[REDIRECT_RESOLVED] 일반 리디렉트 해석 성공 → {final_url}")
                return final_url

    except Exception as e:
        logger.warning(f"[REDIRECT_RESOLVE_FAIL] 리디렉션 해석 실패 → {e}")
        return url


async def fetch_webpage_content(url: str) -> tuple[str, str] | None:
    """
    지정된 URL의 HTML을 가져와 제목과 본문 텍스트를 추출합니다.
    """
    try:
        async with aiohttp.ClientSession() as session:
            async with session.get(url, timeout=10) as response:
                if response.status != 200:
                    return None
                html = await response.text()
                soup = BeautifulSoup(html, "html.parser")
                title = soup.title.string.strip() if soup.title else "제목 없음"
                paragraphs = soup.find_all('p')
                content = " ".join(p.get_text(strip=True) for p in paragraphs)
                return title, content[:10000]
    except Exception as e:
        logger.error(f"[FETCH_ERROR] 웹페이지 크롤링 실패: {e}")
        return None


def is_youtube_playlist(url: str) -> bool:
    return "youtube.com/playlist" in url


async def summarize_webpage_with_llm(
        url: str,
        title: str,
        content: str,
        requested_provider: dict | None = None,
        is_admin_user: bool = False,
        context: dict | None = None
) -> str:
    """
    다양한 LLM 프로바이더를 순차적으로 시도하여 요약을 생성합니다.
    """
    prompt = f"""다음은 웹페이지 정보입니다:\n\nURL: {url}\n제목: {title}\n\n내용:\n{content}\n\n이 내용을 바탕으로 요약해주세요. """

    if is_youtube_playlist(url):
        system_prompt = (
            "당신은 웹페이지 내용을 요약하는 전문가입니다."
            "이모티콘을 적절히 활용하여 읽기 쉽게 작성해주세요."
            "**, ## 마크다운 문자는 사용하지 마세요."
            ""
            "아래와 같은 형식으로 플레이리스트를 표시해 주세요."
            ""
            "[{플레이리스트 제목}]"
            ""
            "1. {첫번쨰 음악}"
            "2. {두번쨰 음악}"
            "3. {세번쨰 음악}"
        )
    else:
        system_prompt = (
            "당신은 웹페이지 내용을 요약하는 전문가입니다. 사용자가 페이지를 직접 열지 않아도 핵심 내용을 정확히 파악할 수 있도록 요약해주세요. "
            "기사 출처에 대해서는 자세히 설명할 필요가 없습니다."
            "이모티콘 등을 활용해서 가독성을 높여주세요."
            "**, ## 마크다운 문자는 사용하지 마세요."
            "제목은 이미 가장 상단에 표시되므로 따로 언급할 필요가 없습니다."
            "안녕하세요 같은 인사말이나 요약해 드릴께요! 란 말은 할 필요 없이 그냥 요약을 해주세요."
            "요약 내용을 여러 섹션으로 나눌 수 있다면 각 섹션을 대표하는 이모티콘으로 구분해 주세요."
            "각 섹션의 타이틀만 봐도 대략적인 내용을 파악할 수 있도록 세분화 하면 좋습니다."
            "각 섹션의 내용은 리스트 형식으로 간략하게 작성해 주세요."
            "가장 마지막 섹션은 '💡 핵심 포인트'로 마무리 해주세요."
        )

    received_message = {
        "text": prompt,
        "bot_name": context.get("bot_name", ""),
        "video_summary": False,
        "source": url
    }
    if requested_provider and is_admin_user:
        providers = [{
            "name": requested_provider["name"],
            "model": requested_provider["model"],
            "timeout": 30,
            "retry": 0,
            "system_prompt": system_prompt
        }]
    else:
        providers = [
            {"name": "grok", "model": "grok-3-latest", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
            {"name": "gemini", "model": "gemini-1.5-pro", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
            {"name": "openai", "model": "gpt-4o", "timeout": 30, "retry": 0, "system_prompt": system_prompt},
            {"name": "gemini-flash", "model": "gemini-1.5-flash", "timeout": 30, "retry": 0,
             "system_prompt": system_prompt},
            {"name": "deepseek", "model": "deepseek-chat", "timeout": 30, "retry": 0, "system_prompt": system_prompt}
        ]
    return await call_llm_with_fallback(received_message, prompt, providers)


async def handle_webpage_summary(prompt: str, context: dict | None = None) -> str:
    """
    수동으로 요청된 웹페이지 요약 명령어 처리.
    """
    # 커스텀 모델이 포함된 경우 분리
    url, provider = extract_custom_model(prompt)
    if not is_valid_url(url):
        return "⚠️ 유효한 웹페이지 URL이 아닙니다."

    logger.info(f"[HANDLE_WEBPAGE] 요약 시작 → {url}")
    # URL 단축 서비스인 경우 최종 리디렉션 URL로 변환
    url = await resolve_redirect_url(url)
    # 본문 크롤링
    result = await fetch_webpage_content(url)
    if not result:
        return "⚠️ 웹페이지 요약을 위한 내용을 가져오지 못했습니다."

    title, content = result
    is_admin_user = is_admin(context.get("channel_id"), context.get("user_hash")) if context else False
    if provider and not is_admin_user:
        provider = None

    summary = await summarize_webpage_with_llm(url, title, content, provider, is_admin_user, context)
    # kakao_readmore 적용을 위한 config 정의
    config = {}
    if context:
        import core.globals as g
        bot_name = context.get("bot_name", "")
        channel_id = str(context.get("channel_id"))
        room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
        config = room_config.get("webpage_summary", {})
    # kakao_readmore 적용
    kakao_readmore = config.get("kakao_readmore", {})
    kakao_type = kakao_readmore.get("type", "lines")
    kakao_value = int(kakao_readmore.get("value", 1))
    full_message = f"📝 웹페이지 요약 ({title})\n\n{summary}"    
    full_message = apply_kakao_readmore(full_message, kakao_type, kakao_value)

    # 수동 기록 로깅
    if context:
        try:
            import core.globals as g
            async with g.db_pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute(
                        """
                        INSERT INTO kb_webpage_summary_logs 
                        (channel_id, user_hash, type, url, created_at)
                        VALUES (%s, %s, %s, %s, NOW())
                        """,
                        (
                            context.get("channel_id"),
                            context.get("user_hash"),
                            "command",
                            url
                        )
                    )
        except Exception as e: 
            logger.error(f"[DB_LOG_FAIL] 웹페이지 요약 기록 실패: {e}")
            
    return full_message


async def process_auto_webpage_summary(message: dict) -> str | None:
    """
    자동 검출된 웹페이지 링크에 대해 일정 제한과 쿨다운을 적용하여 자동으로 요약을 실행.
    """
    try:
        import core.globals as g
        channel_id = message.get("channel_id")
        user_hash = message.get("user_hash")
        text = message.get("text")
        # 텍스트에서 URL 추출
        url = extract_url_from_text(text)
        if not url or not is_valid_url(url):
            return None
        # URL 단축 서비스인 경우 최종 리디렉션 URL로 변환
        url = await resolve_redirect_url(url)

        # 방 설정 확인
        room_config = g.schedule_rooms.get(message.get("bot_name", ""), {}).get(str(channel_id), {})
        config = room_config.get("webpage_summary", {})
        if not config.get("enabled", False):
            return None
        detection = config.get("auto_detection", {})
        if not detection.get("enabled", True):
            return None

        # 일일 제한 체크
        limit = detection.get("daily_limit", 0)
        if limit > 0:
            async with g.db_pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute(
                        """
                        SELECT COUNT(*) FROM kb_webpage_summary_logs
                        WHERE channel_id = %s AND DATE(created_at) = %s AND type = 'auto'
                        """,
                        (channel_id, datetime.now().strftime("%Y-%m-%d"))
                    )
                    count = (await cur.fetchone())[0]
                    if count >= limit:
                        return None

        # 쿨다운 적용
        cooldown = detection.get("cooldown_seconds", 60)
        now = datetime.now()
        if channel_id in last_summary_time_web:
            diff = (now - last_summary_time_web[channel_id]).total_seconds()
            if diff < cooldown:
                return None
        last_summary_time_web[channel_id] = now

        # 대기 메시지 전송
        if detection.get("show_waiting_message", True):
            await send_message_response(context=message, message="⏳ 웹페이지 내용을 요약하고 있어요...")

        # 요약 실행
        summary = await handle_webpage_summary(url, message)
        # 자동 로그 기록
        try:
            async with g.db_pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute(
                        """
                        INSERT INTO kb_webpage_summary_logs 
                        (channel_id, user_hash, type, url, created_at)
                        VALUES (%s, %s, %s, %s, NOW())
                        """,
                        (channel_id, user_hash, "auto", url)
                    )
        except Exception as e:
            logger.error(f"[AUTO_LOG_FAIL] 웹페이지 자동 요약 기록 실패: {e}")

        return summary
    except Exception as e:
        logger.error(f"[AUTO_SUMMARY_FAIL] 예외 발생: {e}")
        return None
