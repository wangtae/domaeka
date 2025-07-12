"""
유튜브 동영상 정보 추출 및 요약 서비스
"""
import re
import asyncio
import aiohttp
import os
import tempfile
import subprocess
from bs4 import BeautifulSoup
from urllib.parse import urlparse, parse_qs
import json
import logging
from datetime import datetime, timedelta
from core.globals import LLM_DEFAULT_SYSTEM_PROMPT
from core.globals import apply_kakao_readmore
from services.llm_fallback_service import call_llm_with_fallback
from core.utils.auth_utils import is_admin  # 꼭 추가하세요
from core.utils.send_message import send_message_response
from services.webpage_service import handle_webpage_summary

# 로깅 설정
logger = logging.getLogger("youtube_service")


def is_playlist_url(url: str) -> bool:
    """유튜브 URL이 플레이리스트인지 확인"""
    parsed = urlparse(url)
    query = parse_qs(parsed.query)
    return 'list' in query


def extract_youtube_url(text):
    """
    메시지 텍스트에서 첫 번째 유튜브 URL을 추출합니다.
    """
    url_pattern = r'https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)[\w-]+'
    match = re.search(url_pattern, text)

    if match:
        return match.group(0)
    return None


def extract_custom_model(prompt: str) -> tuple[str, dict | None]:
    """
    예시 입력: https://youtube.com/watch?v=abc gemini:gemini-1.5-pro
    → 출력: ("https://youtube.com/watch?v=abc", {"name": "gemini", "model": "gemini-1.5-pro"})
    """
    match = re.search(r'\b([a-zA-Z0-9_-]+):([a-zA-Z0-9.\-_]+)', prompt)
    if match:
        name = match.group(1).strip()
        model = match.group(2).strip()
        cleaned_prompt = re.sub(r'\b[a-zA-Z0-9_-]+:[a-zA-Z0-9.\-_]+', '', prompt).strip()
        return cleaned_prompt, {"name": name, "model": model}
    return prompt, None


# 유튜브 URL에서 동영상 ID를 추출하는 함수
def extract_video_id(url):
    if not url:
        return None

    # 숏츠 URL 지원 추가
    shorts_match = re.search(r'youtube\.com/shorts/([a-zA-Z0-9_-]{11})', url)
    if shorts_match:
        return shorts_match.group(1)

    # 기존 일반적인 유튜브 URL 패턴
    youtube_regex = r'(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})'
    match = re.search(youtube_regex, url)

    if match:
        return match.group(1)

    # URL 파싱
    parsed_url = urlparse(url)
    if 'youtube.com' in parsed_url.netloc:
        if '/watch' in parsed_url.path:
            query = parse_qs(parsed_url.query)
            if 'v' in query:
                return query['v'][0]
    elif 'youtu.be' in parsed_url.netloc:
        return parsed_url.path[1:]

    return None


async def fetch_video_info(video_id):
    """
    유튜브 동영상 ID를 이용하여 동영상 정보를 가져옵니다.
    제목, 설명, 채널명, 조회수, 업로드 날짜, 영상 길이 등의 정보를 수집합니다.
    """
    url = f"https://www.youtube.com/watch?v={video_id}"

    try:
        async with aiohttp.ClientSession() as session:
            async with session.get(url) as response:
                if response.status != 200:
                    return {"error": f"동영상 정보를 가져오는데 실패했습니다. 상태 코드: {response.status}"}

                html = await response.text()

                # BeautifulSoup으로 HTML 파싱
                soup = BeautifulSoup(html, 'html.parser')

                # 동영상 정보가 포함된 JSON 데이터 찾기
                video_data = {
                    'title': '제목 정보 없음',
                    'description': '설명 정보 없음',
                    'channel': '채널명 정보 없음',
                    'views': '조회수 정보 없음',
                    'published_date': '게시일 정보 없음',
                    'duration': '영상 길이 정보 없음',
                    'duration_seconds': 0  # 영상 길이(초)
                }

                # 제목 추출
                title_tag = soup.find('meta', property='og:title')
                if title_tag:
                    video_data['title'] = title_tag['content']

                # 설명 추출
                description_tag = soup.find('meta', property='og:description')
                if description_tag:
                    video_data['description'] = description_tag['content']

                # 채널명 추출
                try:
                    for script in soup.find_all('script'):
                        if script.string and '"channelName":"' in script.string:
                            match = re.search(r'"channelName":"([^"]+)"', script.string)
                            if match:
                                video_data['channel'] = match.group(1)
                                break
                except Exception as e:
                    logger.error(f"채널명 추출 중 오류: {e}")

                # 영상 길이 추출
                try:
                    for script in soup.find_all('script'):
                        if script.string and '"lengthSeconds":"' in script.string:
                            match = re.search(r'"lengthSeconds":"(\d+)"', script.string)
                            if match:
                                length_seconds = int(match.group(1))
                                video_data['duration_seconds'] = length_seconds

                                # 시:분:초 형식으로 변환
                                minutes, seconds = divmod(length_seconds, 60)
                                hours, minutes = divmod(minutes, 60)

                                if hours > 0:
                                    video_data['duration'] = f"{hours}시간 {minutes}분 {seconds}초"
                                else:
                                    video_data['duration'] = f"{minutes}분 {seconds}초"
                                break
                except Exception as e:
                    logger.error(f"영상 길이 추출 중 오류: {e}")

                # 조회수 및 날짜 추출 - JSON 파싱 오류 보완
                try:
                    # 수정된 코드
                    for script in soup.find_all('script'):
                        if script.string and 'var ytInitialData' in script.string:
                            try:
                                json_text = script.string.strip()

                                # 정확히 JSON 객체 { ... } 만 추출
                                start_idx = json_text.find('{')
                                end_idx = json_text.rfind('}')
                                if start_idx != -1 and end_idx != -1 and end_idx > start_idx:
                                    json_str = json_text[start_idx:end_idx + 1]
                                    data = json.loads(json_str)

                                    # 🎯 여기서부터 기존 로직 정상 진행
                                    contents = data.get('contents', {}).get('twoColumnWatchNextResults', {}).get(
                                        'results', {}).get('results', {}).get('contents', [])
                                    if contents and len(contents) > 0:
                                        video_primary_info = contents[0].get('videoPrimaryInfoRenderer', {})

                                        # 조회수 추출
                                        view_count_renderer = video_primary_info.get('viewCount', {}).get(
                                            'videoViewCountRenderer', {})
                                        view_count_text = view_count_renderer.get('viewCount', {}).get('simpleText',
                                                                                                       '조회수 정보 없음')
                                        if '조회수' in view_count_text:
                                            view_count_text = view_count_text.replace('조회수', '').strip()
                                        video_data['views'] = view_count_text

                                        # 게시일 추출
                                        date_text = video_primary_info.get('dateText', {}).get('simpleText',
                                                                                               '게시일 정보 없음')
                                        video_data['published_date'] = date_text

                            except Exception as e:
                                logger.error(f"ytInitialData 파싱 실패: {e}")
                            break

                except Exception as e:
                    logger.error(f"조회수/날짜 추출 중 오류: {e}")

                return video_data

    except aiohttp.ClientError as e:
        logger.error(f"동영상 정보 요청 중 오류: {e}")
        return {"error": f"동영상 정보를 가져오는데 실패했습니다: {str(e)}"}
    except Exception as e:
        logger.error(f"동영상 정보 파싱 중 예상치 못한 오류: {e}")
        return {"error": f"동영상 정보 처리 중 오류가 발생했습니다: {str(e)}"}


async def fetch_subtitles(video_id):
    """
    유튜브 동영상의 자막을 가져옵니다.
    없을 경우 빈 문자열을 반환합니다.
    """
    try:
        from youtube_transcript_api import YouTubeTranscriptApi, TranscriptsDisabled, NoTranscriptFound

        try:
            # 자막 가져오기 시도
            logger.debug(f"[YOUTUBE] 동영상 ID {video_id}의 자막 검색 시작")
            transcript_list = YouTubeTranscriptApi.list_transcripts(video_id)
            transcript = None

            # 한국어 자막 우선, 없으면 영어, 그 다음 자동 생성된 자막 시도
            try:
                logger.debug(f"[YOUTUBE] 한국어(ko) 자막 검색 시도")
                transcript = transcript_list.find_transcript(['ko'])
                logger.debug(f"[YOUTUBE] 한국어(ko) 자막 발견")
            except NoTranscriptFound:
                try:
                    logger.debug(f"[YOUTUBE] 한국어(ko-KR) 자막 검색 시도")
                    transcript = transcript_list.find_transcript(['ko-KR'])
                    logger.debug(f"[YOUTUBE] 한국어(ko-KR) 자막 발견")
                except NoTranscriptFound:
                    try:
                        logger.debug(f"[YOUTUBE] 영어(en) 자막 검색 시도")
                        transcript = transcript_list.find_transcript(['en'])
                        logger.debug(f"[YOUTUBE] 영어(en) 자막 발견")
                    except NoTranscriptFound:
                        # 사용 가능한 첫 번째 자막 사용
                        available_transcripts = list(transcript_list._transcripts.values())
                        if available_transcripts:
                            transcript = available_transcripts[0]
                            logger.debug(f"[YOUTUBE] 기타 언어 자막 발견: {transcript.language}")
                        else:
                            logger.debug(f"[YOUTUBE] 사용 가능한 자막이 없음")

            if transcript:
                try:
                    # 자막 데이터 가져오기
                    subtitle_data = transcript.fetch()

                    # 자막 텍스트 추출 및 결합 - 안전한 처리 추가
                    subtitle_texts = []
                    for item in subtitle_data:
                        try:
                            if isinstance(item, dict) and 'text' in item:
                                subtitle_texts.append(item['text'])
                        except (TypeError, KeyError) as e:
                            logger.warning(f"[YOUTUBE] 자막 항목 처리 중 오류: {e}, 항목 타입: {type(item)}")
                            continue

                    full_text = " ".join(subtitle_texts)
                    logger.info(f"[YOUTUBE] 자막 추출 성공: {len(full_text)} 글자")
                    return full_text
                except Exception as e:
                    logger.error(f"[YOUTUBE] 자막 텍스트 추출 중 오류: {e}")
                    return ""
            else:
                logger.info(f"[YOUTUBE] 동영상 {video_id}에 자막이 없습니다.")
                return ""

        except TranscriptsDisabled:
            logger.info(f"[YOUTUBE] 동영상 {video_id}에 자막이 비활성화되어 있습니다.")
            return ""
        except Exception as e:
            logger.error(f"[YOUTUBE] 자막 가져오기 실패: {e}")
            return ""

    except ImportError:
        logger.error("[YOUTUBE] youtube-transcript-api 라이브러리가 설치되지 않았습니다.")
        return ""


async def download_audio_from_youtube(video_id, output_dir=None):
    """
    유튜브 영상에서 오디오를 추출하여 임시 파일로 저장합니다.
    """
    if output_dir is None:
        output_dir = tempfile.gettempdir()

    output_file = os.path.join(output_dir, f"{video_id}.mp3")

    try:
        # yt-dlp 명령어 실행하여 오디오 다운로드
        youtube_url = f"https://www.youtube.com/watch?v={video_id}"
        cmd = [
            "yt-dlp",
            "-x",
            "--audio-format", "mp3",
            "--audio-quality", "0",  # 최고 품질
            "-o", output_file,
            youtube_url
        ]

        # 명령어 실행
        process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )

        stdout, stderr = await process.communicate()

        if process.returncode != 0:
            logger.error(f"오디오 다운로드 실패: {stderr.decode()}")
            return None

        logger.info(f"오디오 다운로드 성공: {output_file}")
        return output_file

    except Exception as e:
        logger.error(f"오디오 다운로드 중 오류 발생: {str(e)}")
        return None


async def generate_subtitles_with_whisper(audio_file, model="whisper-1"):
    """
    OpenAI Whisper API를 사용하여 오디오 파일에서 자막을 생성합니다.
    """
    import openai
    from config.loader import load_config
    import core.globals as g

    try:
        # config.json에서 API 키 로드
        CONFIG = load_config()
        API_KEY = CONFIG['APIs']['OPENAI']['KEY']

        if not API_KEY:
            logger.error("[WHISPER] OpenAI API 키가 설정되지 않았습니다.")
            return ""

        # 비동기 코드에서는 run_in_executor를 사용하여 동기 코드 실행
        loop = asyncio.get_running_loop()

        def run_whisper():
            try:
                # OpenAI 클라이언트 초기화 (동기 방식)
                client = openai.OpenAI(api_key=API_KEY)

                # 오디오 파일 열기
                with open(audio_file, "rb") as audio:
                    # Whisper API 호출 (동기 방식)
                    response = client.audio.transcriptions.create(
                        model=model,
                        file=audio,
                        response_format="text"
                    )

                    return response
            except Exception as e:
                logger.error(f"[WHISPER] Whisper API 호출 중 오류: {str(e)}")
                return ""

        # 동기 코드를 별도 스레드에서 실행
        transcription = await loop.run_in_executor(None, run_whisper)

        if transcription:
            logger.info(f"[WHISPER] 자막 생성 완료: {len(transcription)} 글자")
        else:
            logger.error("[WHISPER] 자막 생성 실패")

        return transcription

    except Exception as e:
        logger.error(f"[WHISPER] 자막 생성 중 예외 발생: {str(e)}")
        return ""
    finally:
        # 임시 파일 정리
        try:
            if os.path.exists(audio_file):
                os.remove(audio_file)
                logger.debug(f"[WHISPER] 임시 오디오 파일 삭제: {audio_file}")
        except Exception as e:
            logger.error(f"[WHISPER] 임시 파일 삭제 중 오류: {str(e)}")


async def should_generate_transcripts(video_info, context=None):
    """
    자동 자막 생성 여부를 결정합니다.
    조건 1: 트랜스크립션 기능이 활성화되어 있어야 함
    조건 2: 영상 길이가 설정된 최대 길이 이내여야 함
    조건 3: 일일 사용량 제한을 초과하지 않아야 함
    """
    import core.globals as g

    if not context:
        logger.debug("[WHISPER] 컨텍스트 정보 없음, 자막 생성 중단")
        return False

    channel_id = context.get("channel_id")
    bot_name = context.get("bot_name", "")

    # 설정 확인
    try:
        if hasattr(g, 'schedule_rooms'):
            room_config = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
            youtube_summary = room_config.get("youtube_summary", {})
            transcription = youtube_summary.get("transcription", {})

            # 자막 생성 기능 활성화 여부
            if not transcription.get("enabled", False):
                logger.debug(f"[WHISPER] 자막 생성 기능 비활성화됨 (채널: {channel_id})")
                return False

            # 최대 영상 길이 확인
            max_duration_minutes = transcription.get("max_duration_minutes", 0)
            if max_duration_minutes <= 0:
                logger.debug(f"[WHISPER] 최대 영상 길이가 설정되지 않음 (채널: {channel_id})")
                return False

            # 영상 길이 확인
            video_duration_seconds = video_info.get("duration_seconds", 0)
            max_duration_seconds = max_duration_minutes * 60

            if video_duration_seconds <= 0:
                logger.debug(f"[WHISPER] 영상 길이 정보를 가져올 수 없음 (채널: {channel_id})")
                return False

            if video_duration_seconds > max_duration_seconds:
                logger.debug(
                    f"[WHISPER] 영상 길이({video_duration_seconds}초)가 최대 허용 길이({max_duration_seconds}초)보다 김 (채널: {channel_id})")
                return False

            logger.debug(
                f"[WHISPER] 영상 길이 조건 충족: {video_duration_seconds}초 <= {max_duration_seconds}초 (채널: {channel_id})")

            # 일일 제한 확인
            daily_limit = transcription.get("daily_limit", 0)
            if daily_limit > 0:
                current_date = datetime.now().strftime("%Y-%m-%d")

                # DB 쿼리로 오늘의 사용량 확인
                if hasattr(g, 'db_pool') and g.db_pool:
                    query = """
                    SELECT COUNT(*) as count FROM kb_youtube_summary_logs 
                    WHERE channel_id = %s AND date(created_at) = %s AND type = 'transcription'
                    """

                    async with g.db_pool.acquire() as conn:
                        async with conn.cursor() as cursor:
                            await cursor.execute(query, (channel_id, current_date))
                            result = await cursor.fetchone()
                            usage_count = result[0] if result else 0

                            if usage_count >= daily_limit:
                                logger.info(f"[WHISPER] 자막 생성 일일 제한 도달: {usage_count}/{daily_limit} (채널: {channel_id})")
                                return False

                            logger.debug(f"[WHISPER] 일일 사용량 제한 이내: {usage_count}/{daily_limit} (채널: {channel_id})")

            # 자막 생성 공급자 확인
            provider = transcription.get("provider", "")
            if not provider or provider.lower() != "whisper":
                logger.debug(f"[WHISPER] 지원되지 않는 자막 생성 공급자: {provider} (채널: {channel_id})")
                return False

            # 모든 조건 만족
            logger.info(f"[WHISPER] 자막 생성 조건 모두 충족, 생성 시작 (채널: {channel_id}, 영상 길이: {video_duration_seconds}초)")
            return True

    except Exception as e:
        logger.error(f"자막 생성 조건 확인 중 오류: {e}")
        return False


async def handle_youtube_summary(prompt, context=None):
    import core.globals as g

    # ✅ name:model 분리 처리
    prompt, requested_provider = extract_custom_model(prompt)

    # ✅ 비관리자는 무시
    is_admin_user = is_admin(context.get("channel_id"), context.get("user_hash")) if context else False
    if not is_admin_user:
        requested_provider = None

    # 기존 URL 처리
    url = prompt

    if is_playlist_url(url):
        logger.info(f"[YOUTUBE] 플레이리스트 URL 감지: {url}")
        return ""

    video_id = extract_video_id(url)
    if not video_id:
        return "유효한 유튜브 URL이 아닙니다. 올바른 유튜브 동영상 URL을 입력해주세요."

    logger.debug(f"유튜브 동영상 ID 추출 성공: {video_id}")
    video_info = await fetch_video_info(video_id)
    if "error" in video_info:
        return video_info["error"]

    # 자막 가져오기
    subtitles = await fetch_subtitles(video_id)

    # 자막이 없고 자동 생성 조건에 부합하는 경우
    transcription_generated = False
    if not subtitles and context and await should_generate_transcripts(video_info, context):
        # 대기 메시지 발송 (자막 생성 시작)
        await send_message_response(context, "⏳ 자막이 없어 자동으로 생성하고 있어요... (시간이 다소 소요될 수 있습니다)")

        try:
            # 설정에서 제공자 및 모델 정보 가져오기
            room_config = g.schedule_rooms.get(context.get("bot_name", ""), {}).get(str(context.get("channel_id")),
                                                                                         {})
            youtube_summary = room_config.get("youtube_summary", {})
            transcription = youtube_summary.get("transcription", {})
            provider = transcription.get("provider", "whisper")
            model = transcription.get("model", "whisper-1")

            if provider == "whisper":
                # 오디오 다운로드
                audio_file = await download_audio_from_youtube(video_id)
                if audio_file:
                    # Whisper로 자막 생성
                    subtitles = await generate_subtitles_with_whisper(audio_file, model)
                    if subtitles:
                        transcription_generated = True

                        # 사용량 기록
                        try:
                            if hasattr(g, 'db_pool') and g.db_pool:
                                insert_query = """
                                INSERT INTO kb_youtube_summary_logs 
                                (channel_id, user_hash, type, video_url, created_at) 
                                VALUES (%s, %s, %s, %s, NOW())
                                """
                                async with g.db_pool.acquire() as conn:
                                    async with conn.cursor() as cursor:
                                        await cursor.execute(insert_query, (
                                            context.get("channel_id"),
                                            context.get("user_hash"),
                                            "transcription",
                                            url
                                        ))
                        except Exception as e:
                            logger.error(f"자막 생성 사용량 기록 중 오류: {e}")

            elif provider == "google_stt":
                # 향후 Google STT 구현
                pass

        except Exception as e:
            logger.error(f"자막 자동 생성 중 오류: {e}")

    # 요약 생성
    summary = await summarize_video_with_llm(video_info, subtitles, requested_provider, is_admin_user, context)

    # 자막 관련 메시지 추가
    if transcription_generated:
        summary = "✨ 자막이 없어 AI로 자동 생성한 자막을 기반으로 요약했어요.\n\n" + summary
    elif not subtitles:
        summary = "⚠️ 자막 정보가 없어 요약의 정확도가 다소 낮을 수 있어요.\n\n" + summary

    # kakao_readmore 적용을 위한 config 정의
    config = {}
    if context:
        bot_name = context.get("bot_name", "")
        channel_id = str(context.get("channel_id"))
        room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
        config = room_config.get("youtube_summary", {})
    # kakao_readmore 적용
    kakao_readmore = config.get("kakao_readmore", {})
    kakao_type = kakao_readmore.get("type", "lines")
    kakao_value = kakao_readmore.get("value", 1)
    total_message = f"🎬 유튜브 동영상 요약\n\n{summary}"

    # ✅ 요약 메시지 뒤에 자막 내용 추가
    if subtitles:
        # 너무 긴 자막은 잘라내기
        display_subtitles = subtitles
        if len(display_subtitles) > 5000:
            display_subtitles = display_subtitles[:5000] + "\n\n... (자막이 너무 길어 일부만 표시됩니다)"
        total_message += f"\n\n--- 원문 자막 내용 ---\n{display_subtitles}"
        
    total_message = apply_kakao_readmore(total_message, kakao_type, kakao_value)

    # 로그 기록
    if context:
        try:
            if hasattr(g, 'db_pool') and g.db_pool:
                insert_query = """
                INSERT INTO kb_youtube_summary_logs 
                (channel_id, user_hash, type, video_url, created_at) 
                VALUES (%s, %s, %s, %s, NOW())
                """
                async with g.db_pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute(insert_query, (
                            context.get("channel_id"),
                            context.get("user_hash"),
                            "command",
                            url
                        ))
        except Exception as e:
            logger.error(f"명령어 사용량 기록 중 오류: {e}")
            
    return total_message


async def summarize_video_with_llm(video_info, subtitles, requested_provider=None, is_admin_user=False, context: dict | None = None):
    title = video_info.get('title', '제목 없음')
    channel = video_info.get('channel', '채널명 없음')
    description = video_info.get('description', '')
    views = video_info.get('views', '조회수 정보 없음')
    published_date = video_info.get('published_date', '게시일 정보 없음')
    duration = video_info.get('duration', '영상 길이 정보 없음')

    if subtitles and len(subtitles) > 5000:
        subtitles = subtitles[:5000] + "... (자막이 너무 길어 일부만 사용됨)"

    user_prompt = f"""다음은 유튜브 동영상의 정보입니다:\n\n제목: {title}\n채널: {channel}\n게시일: {published_date}\n조회수: {views}\n영상 길이: {duration}\n설명:\n{description}\n
동영상 자막:\n{subtitles if subtitles else '(자막이 제공되지 않음)'}\n
위 정보를 바탕으로 동영상의 핵심 내용을 간결하게 요약해주세요. 다음 형식으로 요약을 제공해주세요:\n\n1. 동영상 기본 정보 (제목, 채널명, 게시일, 영상 길이)\n2. 핵심 주제\n3. 주요 내용 요약 (3-5개 요점)\n4. 동영상의 의의나 가치\n
최대 600자 이내로 요약해주세요.\n"""

    system_prompt = (
        "당신은 유튜브 동영상을 요약하는 전문가입니다. 제공된 동영상 정보와 자막을 분석하여 핵심 내용을 간결하게 요약해야 합니다."
        "사용자가 영상을 직접 보지 않고도 중요한 내용을 빠르게 파악할 수 있도록 도와주세요."
        "이모티콘 등을 활용해서 가독성을 높여주세요."
        "**, ## 마크다운 문자는 사용하지 마세요"
        "제목은 이미 가장 상단에 표시되므로 따로 언급할 필요가 없습니다."
        "안녕하세요 같은 인사말이나 요약해 드릴께요! 란 말은 할 필요 없이 그냥 요약을 해주세요."
        "요약 내용을 여러 섹션으로 나눌 수 있다면 각 섹션을 대표하는 이모티콘으로 구분해 주세요."
        "각 섹션의 타이틀만 봐도 대략적인 내용을 파악할 수 있도록 세분화 하면 좋습니다."
        "각 섹션의 내용은 리스트 형식으로 간략하게 작성해 주세요."
        "가장 마지막 섹션은 '💡 핵심 포인트'로 마무리 해주세요."
        "영상 길이 정보도 필요한 경우 요약에 포함해주세요."
    )

    received_message = {
        "text": user_prompt,
        "bot_name": context.get("bot_name", ""),
        "video_title": title,
        "video_channel": channel,
        "video_summary": True
    }

    # ✅ 관리자 + 지정 모델이 있으면 우선 적용
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

    return await call_llm_with_fallback(received_message, user_prompt, providers)


# 메시지에서 유튜브 URL을 추출하는 함수
def extract_video_id(url):
    """
    다양한 형태의 유튜브 URL에서 동영상 ID를 추출합니다.
    지원하는 URL 형식:
    - https://www.youtube.com/watch?v=VIDEO_ID
    - https://youtu.be/VIDEO_ID
    - https://www.youtube.com/embed/VIDEO_ID
    - https://www.youtube.com/v/VIDEO_ID
    - https://www.youtube.com/shorts/VIDEO_ID
    """
    if not url:
        return None

    # 숏츠 URL 지원 추가
    shorts_match = re.search(r'youtube\.com/shorts/([a-zA-Z0-9_-]{11})', url)
    if shorts_match:
        return shorts_match.group(1)

    # 기존 일반적인 유튜브 URL 패턴
    youtube_regex = r'(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})'
    match = re.search(youtube_regex, url)

    if match:
        return match.group(1)

    # URL 파싱을 통한 추출 시도
    parsed_url = urlparse(url)
    if 'youtube.com' in parsed_url.netloc:
        if '/watch' in parsed_url.path:
            query = parse_qs(parsed_url.query)
            if 'v' in query:
                return query['v'][0]
    elif 'youtu.be' in parsed_url.netloc:
        return parsed_url.path[1:]

    return None


# 채널의 마지막 요약 시간을 저장하는 전역 딕셔너리
last_summary_time = {}


# 자동 요약 처리 함수
async def process_auto_youtube_summary(message):
    """
    일반 메시지에서 유튜브 URL을 감지하고, 자동 요약 기능이 활성화된 경우 요약합니다.
    새로운 youtube_summary 설정 구조를 지원합니다.
    """
    try:
        channel_id = message.get("channel_id")
        room = message.get("room")
        text = message.get("text", "")
        bot_name = message.get("bot_name", "")
        sender = message.get("sender")
        user_hash = message.get("user_hash")

        # 메시지에서 유튜브 URL 추출
        youtube_url = extract_youtube_url(text)
        if not youtube_url:
            return None

        # 채널에 유튜브 요약 기능이 활성화되어 있는지 확인
        import core.globals as g
        room_config = {}
        try:
            if hasattr(g, 'schedule_rooms'):
                room_config = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
        except Exception as e:
            logger.error(f"설정 확인 중 오류: {e}")
            return None

        # 새로운 설정 구조 확인
        youtube_summary = room_config.get("youtube_summary", {})

        # 기능 전체 활성화 여부 확인
        if not youtube_summary.get("enabled", False):
            # 이전 설정 방식 확인 (하위 호환성)
            legacy_enabled = room_config.get("youtube_auto_summary", False)
            if not legacy_enabled:
                logger.debug(f"유튜브 요약 기능 비활성화 (채널: {channel_id})")
                return None

        # 자동 감지 설정 확인
        auto_detection = youtube_summary.get("auto_detection", {})
        if not auto_detection.get("enabled", True):
            logger.debug(f"유튜브 자동 요약 기능 비활성화 (채널: {channel_id})")
            return None

        # 일일 제한 확인
        daily_limit = auto_detection.get("daily_limit", 0)
        if daily_limit > 0:
            # 일일 사용량 확인 로직 (DB 필요)
            current_date = datetime.now().strftime("%Y-%m-%d")

            try:
                # 데이터베이스에서 오늘 사용량 조회
                query = """
                SELECT COUNT(*) as count FROM kb_youtube_summary_logs 
                WHERE channel_id = %s AND date(created_at) = %s AND type = 'auto'
                """

                # DB 풀이 있는지 확인
                if hasattr(g, 'db_pool') and g.db_pool:
                    async with g.db_pool.acquire() as conn:
                        async with conn.cursor() as cursor:
                            await cursor.execute(query, (channel_id, current_date))
                            result = await cursor.fetchone()
                            usage_count = result[0] if result else 0

                            if usage_count >= daily_limit:
                                logger.info(f"유튜브 자동 요약 일일 제한 도달: {usage_count}/{daily_limit} (채널: {channel_id})")
                                return None
            except Exception as e:
                logger.error(f"일일 사용량 확인 중 오류: {e}")
                # 오류 발생 시 계속 진행 (제한 검사 생략)

        # 쿨다운 시간 확인
        cooldown_seconds = auto_detection.get("cooldown_seconds", 60)
        current_time = datetime.now()
        if channel_id in last_summary_time:
            time_diff = (current_time - last_summary_time[channel_id]).total_seconds()
            if time_diff < cooldown_seconds:
                logger.debug(f"최근 요약 후 {time_diff}초 경과 - 쿨다운 중 (최소 {cooldown_seconds}초, 채널: {channel_id})")
                return None

        # 마지막 요약 시간 업데이트
        last_summary_time[channel_id] = current_time

        # 대기 메시지 표시 설정 확인
        show_waiting_message = auto_detection.get("show_waiting_message", True)

        # 대기 메시지 발송 (show_waiting_message 설정에 따라)
        if show_waiting_message:
            await send_message_response(context=message, message=f"⏳ 유튜브 동영상을 요약하고 있어요...")

        # 요약 처리
        logger.info(f"유튜브 URL 감지, 요약 시작: {youtube_url}")
        summary = await handle_youtube_summary(youtube_url, message)  # context 전달

        # 일일 사용량 기록 (DB 필요)
        try:
            if hasattr(g, 'db_pool') and g.db_pool:
                insert_query = """
                INSERT INTO kb_youtube_summary_logs 
                (channel_id, user_hash, type, video_url, created_at) 
                VALUES (%s, %s, %s, %s, NOW())
                """

                async with g.db_pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute(insert_query, (channel_id, user_hash, "auto", youtube_url))
        except Exception as e:
            logger.error(f"사용량 기록 중 오류: {e}")

        return summary

    except Exception as e:
        logger.error(f"자동 유튜브 요약 중 오류: {e}")
        return None
