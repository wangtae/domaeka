"""
TTS(Text-to-Speech) 서비스 모듈 - 웹 API 방식
이 모듈은 https://api.j-touch.com/gcp/texttospeech/v2.0.2/tts-url.php API를 활용하여
텍스트를 음성으로 변환하고 URL을 반환하는 기능을 제공합니다.
"""

import re
import emoji
import aiohttp
import json
import asyncio
import random
from typing import Dict, Optional, Tuple, Any, List, Union

from core import globals as g
from core.logger import logger
from core.utils.yourls_client import shorten_url_with_yourls
from config.loader import load_config

# 설정 로드
CONFIG = load_config()

# TTS 변환 시 특정 마커 이후 내용 제외 (이 마커 아래 내용은 TTS로 변환되지 않음)
TTS_TEXT_CUTOFF_MARKERS = [
    "매일성경 성경 본문 >",
]

# TTS 관련 설정
TTS_CONFIG = {
    "api_endpoint": "https://api.j-touch.com/gcp/texttospeech/v2.0.2/tts-url-chunks.php",
    "default_language": "ko-KR",
    "default_gender": "F",
    "default_voice": "C",
    "default_speaking_rate": 1.0
}

# 사용 가능한 음성 매핑 정의
# 형식: voices[언어][성별][음성코드] = 'API에 전달할 음성 이름'
VOICES = {
    'en-US': {
        'M': {  # 남성 음성
            'A': 'Charon',
            'B': 'Fenrir',
            'C': 'Orus',
            'D': 'Puck',
        },
        'F': {  # 여성 음성
            'A': 'Aoede',
            'B': 'Kore',
            'C': 'Leda',
            'D': 'Zephyr',
        }
    },
    'ko-KR': {
        'M': {  # 남성 음성
            'A': 'Charon',
            'B': 'Fenrir',
            'C': 'Orus',
            'D': 'Puck',
        },
        'F': {  # 여성 음성
            'A': 'Aoede',
            'B': 'Kore',
            'C': 'Leda',
            'D': 'Zephyr',
        }
    }
}

# 성별 및 음성 코드 옵션
GENDER_OPTIONS = ['M', 'F']
VOICE_OPTIONS = ['A', 'B', 'C', 'D']

# 제거할 단어 리스트 (괄호 포함 여부 관계 없음, 수동 등록)
BLOCKED_WORDS_FOR_TTS = {
    "굿", "꺄아"
}


def clean_text_for_tts(text: str) -> str:
    """
    TTS를 위한 자연스러운 한글 유지형 전처리:
    - 불필요한 접두어 제거
    - 이모지 제거
    - 마크다운 제거
    - 등록된 단어 제거 (괄호 안/밖 모두)
    - 시각적 기호 제거
    - 공백 정리
    - 특정 마커 이후 내용 제외
    """
    # 1. 불필요한 접두어 제거
    ko_match = re.search(r'^한국어\s+(.*)', text, re.DOTALL)
    en_match = re.search(r'^영어\s+(.*)', text, re.DOTALL)
    if ko_match:
        text = ko_match.group(1).strip()
    elif en_match:
        text = en_match.group(1).strip()

    # 1.5 특정 마커 이후 내용 제외
    for marker in TTS_TEXT_CUTOFF_MARKERS:
        parts = text.split(marker, 1)
        if len(parts) > 1:
            logger.info(f"[TTS] 마커 '{marker}' 이후 내용 제외됨 (원문 {len(text)} → 처리 후 {len(parts[0])})")
            text = parts[0].strip()
            break

    # 2. 이모지 제거
    text = emoji.replace_emoji(text, replace='')

    # 3. 마크다운 강조 제거
    text = re.sub(r'(\*\*|__|~~)(.*?)\1', r'\2', text)

    # 4. 등록된 단어 제거 (괄호 유무와 상관없이)
    for word in BLOCKED_WORDS_FOR_TTS:
        # 괄호 포함 버전 제거
        text = re.sub(rf'\(\s*{re.escape(word)}\s*\)', '', text)
        # 일반 텍스트에서 제거
        text = re.sub(rf'\b{re.escape(word)}\b', '', text)

    # 5. 시각적 특수기호 제거
    text = re.sub(r'[•◆▶→■▪️★☆※✔️◇…→▶📋🗣📰📊🌱💡💬📖🧏🔔🎧🎤🗣️👂😄✨🔋🌿⏰🔍🙏📜😊]', '', text)

    # 6. 공백 정리
    text = re.sub(r'\s+', ' ', text).strip()

    return text


def detect_language(text: str) -> str:
    """
    텍스트에서 언어를 자동 감지 (한국어 또는 영어)

    Args:
        text: 감지할 텍스트

    Returns:
        감지된 언어 코드 ('ko-KR' 또는 'en-US')
    """
    if re.search(r'[가-힣]', text):
        return 'ko-KR'
    return 'en-US'


def get_random_gender() -> str:
    """
    'M'과 'F' 중 랜덤으로 하나 선택

    Returns:
        랜덤 선택된 성별 ('M' 또는 'F')
    """
    return random.choice(GENDER_OPTIONS)


def get_random_voice() -> str:
    """
    'A', 'B', 'C', 'D' 중 랜덤으로 하나 선택

    Returns:
        랜덤 선택된 음성 코드 ('A', 'B', 'C', 또는 'D')
    """
    return random.choice(VOICE_OPTIONS)


def get_voice_name(language: str, gender: str, voice_code: str) -> str:
    """
    언어, 성별, 음성 코드로부터 실제 음성 이름을 가져옴

    Args:
        language: 언어 코드 ('ko-KR' 또는 'en-US')
        gender: 성별 ('M' 또는 'F')
        voice_code: 음성 코드 ('A', 'B', 등)

    Returns:
        음성 이름 (API에 전달할 값)
    """
    # 'auto' 값 처리
    if gender == 'auto':
        gender = get_random_gender()
        logger.info(f"[TTS] 랜덤 성별 선택: {gender}")

    if voice_code == 'auto':
        voice_code = get_random_voice()
        logger.info(f"[TTS] 랜덤 음성 선택: {voice_code}")

    try:
        return VOICES[language][gender][voice_code]
    except KeyError:
        # 해당 조합이 없으면 기본값 반환
        logger.warning(f"[TTS] 요청한 음성을 찾을 수 없음: {language}/{gender}/{voice_code}, 기본값으로 대체")

        # 언어별 기본 음성 반환
        if language == 'ko-KR':
            return VOICES['ko-KR']['F']['C']  # 한국어 기본 여성 음성
        else:
            return VOICES['en-US']['F']['C']  # 영어 기본 여성 음성


async def get_tts_url(text: str,
                      language: str,
                      gender: str,
                      voice_code: str,
                      speaking_rate: float) -> Tuple[bool, str, Optional[str]]:
    """
    텍스트를 음성 URL로 변환

    Args:
        text: 음성으로 변환할 텍스트
        language: 언어 코드 ('ko-KR', 'en-US' 또는 'auto')
        gender: 성별 ('M', 'F' 또는 'auto')
        voice_code: 음성 코드 ('A', 'B', 등 또는 'auto')
        speaking_rate: 말하기 속도

    Returns:
        (성공 여부, 메시지 또는 오류, URL 또는 None)
    """
    # 텍스트 정제
    clean_text = clean_text_for_tts(text)
    if not clean_text:
        logger.warning("[TTS] 변환할 텍스트가 없습니다.")
        return False, "변환할 텍스트가 없습니다", None

    # 텍스트가 너무 짧은 경우 변환 거부 (최소 2글자 이상 필요)
    if len(clean_text) < 2:
        logger.warning(f"[TTS] 텍스트가 너무 짧습니다: '{clean_text}'")
        return False, "텍스트가 너무 짧습니다", None

    # 텍스트 길이 제한 (4000자)
    if len(clean_text) > 4000:
        logger.warning(f"[TTS] 텍스트가 너무 깁니다. 4000자로 제한합니다. (원본: {len(clean_text)}자)")
        clean_text = clean_text[:4000]

    # 언어 자동 감지가 필요한 경우
    if language == 'auto':
        language = detect_language(clean_text)
        logger.info(f"[TTS] 언어 자동 감지 결과: {language}")

    # 랜덤 성별 및 음성 선택
    if gender == 'auto':
        gender = get_random_gender()
        logger.info(f"[TTS] 랜덤 성별 선택: {gender}")

    if voice_code == 'auto':
        voice_code = get_random_voice()
        logger.info(f"[TTS] 랜덤 음성 선택: {voice_code}")

    # 음성 이름 결정
    voice_name = get_voice_name(language, gender, voice_code)

    # 요청 데이터 준비
    data = {
        "language": language,
        "voice_name": voice_name,
        "speaking_rate": speaking_rate,
        "text": clean_text
    }

    try:
        logger.info(
            f"[TTS] TTS URL 요청 시작: 언어={language}, 성별={gender}, 음성코드={voice_code}, 음성이름={voice_name}, 텍스트 길이={len(clean_text)}자")

        # 항상 새로운 세션 생성
        async with aiohttp.ClientSession() as session:
            try:
                # POST 요청 전송
                async with session.post(TTS_CONFIG["api_endpoint"], data=data, timeout=180) as response:
                    if response.status != 200:
                        error_msg = await response.text()
                        logger.error(f"[TTS] API 요청 실패: HTTP {response.status} - {error_msg}")
                        return False, f"API 요청 실패: HTTP {response.status}", None

                    response_text = await response.text()

                    # JSON 파싱 시도
                    try:
                        result_data = json.loads(response_text)
                        logger.info(f"[TTS] JSON 파싱 성공: {result_data.keys()}")

                        # 결과 확인
                        if 'result' in result_data and result_data['result']:
                            tts_url = result_data.get('url')
                            if tts_url:
                                # URL 단축 시도
                                try:
                                    short_url = shorten_url_with_yourls(tts_url)
                                    logger.info(f"[TTS] TTS URL 생성 성공, 단축 URL: {short_url}")
                                    return True, "성공", short_url
                                except Exception as e:
                                    # URL 단축 실패하면 원본 URL 사용
                                    logger.warning(f"[TTS] URL 단축 실패: {str(e)}, 원본 URL 사용")
                                    return True, "성공", tts_url
                            else:
                                logger.error(f"[TTS] 결과에 URL이 없음: {result_data}")
                                return False, "응답에 오디오 URL이 없습니다", None
                        else:
                            error_msg = result_data.get('message', '알 수 없는 오류')
                            logger.error(f"[TTS] TTS 변환 실패: {error_msg}")
                            return False, f"TTS 변환 실패: {error_msg}", None

                    # JSON 파싱 실패 시 HTML 응답에서 URL 추출 시도
                    except json.JSONDecodeError:
                        logger.warning(f"[TTS] JSON 파싱 실패, 응답: {response_text[:200]}")

                        # MP3 URL 추출 시도
                        url_match = re.search(r'(https?://[^\s"\'<>]+\.mp3)', response_text)
                        if url_match:
                            tts_url = url_match.group(1)
                            logger.info(f"[TTS] HTML 응답에서 MP3 URL 추출: {tts_url}")

                            try:
                                short_url = shorten_url_with_yourls(tts_url)
                                logger.info(f"[TTS] TTS URL 추출 성공, 단축 URL: {short_url}")
                                return True, "성공", short_url
                            except Exception as e:
                                logger.warning(f"[TTS] URL 단축 실패: {str(e)}, 원본 URL 사용")
                                return True, "성공", tts_url
                        else:
                            logger.error("[TTS] HTML 응답에서 MP3 URL을 찾을 수 없음")
                            return False, "응답에서 오디오 URL을 찾을 수 없습니다", None

            except Exception as e:
                logger.error(f"[TTS] API 요청 중 예외 발생: {str(e)}")
                return False, f"API 요청 오류: {str(e)}", None

    except aiohttp.ClientError as e:
        logger.error(f"[TTS] TTS 요청 중 네트워크 오류: {str(e)}")
        return False, f"네트워크 오류: {str(e)}", None
    except asyncio.TimeoutError:
        logger.error("[TTS] TTS 요청 시간 초과")
        return False, "요청 시간 초과", None
    except Exception as e:
        logger.error(f"[TTS] TTS 요청 중 예외 발생: {str(e)}")
        return False, f"처리 중 오류: {str(e)}", None


async def handle_tts_command(prompt: str, tts_config: Optional[Dict[str, Any]] = None) -> Union[str, List[str]]:
    """
    TTS 명령어 핸들러

    Args:
        prompt: TTS로 변환할 텍스트
        tts_config: TTS 설정 (optional)
            {
                "language": 언어 코드 ("ko-KR", "en-US", "auto"),
                "gender": 성별 ("M", "F", "auto"),
                "voice": 음성 코드 ("A", "B", 등, "auto"),
                "speaking_rate": 말하기 속도 (float)
            }

    Returns:
        응답 메시지 또는 메시지 목록
    """
    # 입력 유효성 검사
    if not prompt or prompt.strip() == "":
        return "⚠️ 변환할 텍스트를 입력해주세요.\n\n예시: # tts 안녕하세요. 오늘 날씨가 좋네요."

    # 1. TTS 기본 설정 복사
    config = g.TTS_DEFAULT_CONFIG.copy()

    # 2. 전달된 설정이 있을 경우 일부 override
    if tts_config and isinstance(tts_config, dict):
        for key, value in tts_config.items():
            if key in config:
                config[key] = value

    # 명령줄 옵션 파싱 (예: --lang=en-US --gender=M --voice=B --rate=0.8)
    text_parts = []
    parts = prompt.split()

    for part in parts:
        if part.startswith("--"):
            # 옵션 파싱
            try:
                key, value = part[2:].split("=", 1)
                if key == "lang" or key == "language":
                    config["language"] = value
                elif key == "gender":
                    if value.upper() in ["M", "F", "AUTO"]:
                        config["gender"] = value.upper()
                elif key == "voice":
                    if value.upper() in ["A", "B", "C", "D", "AUTO"]:
                        config["voice"] = value.upper()
                elif key == "rate" or key == "speaking_rate":
                    config["speaking_rate"] = float(value)
            except (ValueError, IndexError):
                # 파싱 실패하면 그냥 텍스트로 간주
                text_parts.append(part)
        else:
            text_parts.append(part)

    # 변환할 텍스트
    text = " ".join(text_parts)

    # 언어 자동 감지 필요한 경우 (명령줄 옵션에서 설정되지 않았을 때)
    if config["language"] != "auto" and config["language"] not in ["ko-KR", "en-US"]:
        # 지원하지 않는 언어 코드면 자동 감지로 설정
        logger.warning(f"[TTS] 지원하지 않는 언어 코드: {config['language']}, 자동 감지로 전환")
        config["language"] = "auto"

    # 음성 설정 로그
    gender_display = "랜덤" if config["gender"] == "AUTO" else config["gender"]
    voice_display = "랜덤" if config["voice"] == "AUTO" else config["voice"]

    logger.info(
        f"[TTS] 음성 변환 요청: 텍스트='{text[:50]}{'...' if len(text) > 50 else ''}', 언어={config['language']}, 성별={gender_display}, 음성={voice_display}")

    # TTS URL 요청
    success, message, url = await get_tts_url(
        text,
        language=config["language"],
        gender=config["gender"],
        voice_code=config["voice"],
        speaking_rate=config["speaking_rate"]
    )

    if success and url:
        voice_info = f"언어: {config['language'] if config['language'] != 'auto' else '자동 감지'}, "
        voice_info += f"성별: {gender_display}, 음성 타입: {voice_display}"

        # 커맨트 랜덤 선택 (comment 필드가 있는 경우)
        intro_text = "🧏 음성으로 녹음해 보았어요!"
        if isinstance(config.get("intro"), list) and config["intro"]:
            intro_text = random.choice(config["intro"])

        short_url = re.sub(r'^https?://', '', url)
        return [f"{intro_text}\n\n⠠ {short_url}"]

    else:
        error_msg = f"⚠️ 음성 변환에 실패했습니다: {message}"
        logger.info(error_msg)
        return ""


async def handle_tts_ko_command(prompt: str) -> Union[str, List[str]]:
    """한국어 TTS 명령어 처리 (한국어 부분만 추출)"""
    match = re.search(r'한국어\s+(.*)', prompt, re.DOTALL)
    if match:
        text_only = match.group(1).strip()
        if text_only:  # 텍스트가 있으면 해당 텍스트만 TTS
            return await handle_tts_command(text_only, {"language": "ko-KR"})

    # 아니면 그냥 전체 텍스트를 TTS
    return await handle_tts_command(prompt, {"language": "ko-KR"})


async def handle_tts_en_command(prompt: str) -> Union[str, List[str]]:
    """영어 TTS 명령어 처리 (영어 부분만 추출)"""
    match = re.search(r'영어\s+(.*)', prompt, re.DOTALL)
    if match:
        text_only = match.group(1).strip()
        if text_only:  # 텍스트가 있으면 해당 텍스트만 TTS
            return await handle_tts_command(text_only, {"language": "en-US"})

    # 아니면 그냥 전체 텍스트를 TTS
    return await handle_tts_command(prompt, {"language": "en-US"})
