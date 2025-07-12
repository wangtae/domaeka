"""
TTS(Text-to-Speech) 서비스 모듈 - Google TTS API (서비스 계정 인증 방식)
"""
import os
import uuid
import re
import json
import base64
import asyncio
import aiohttp
import emoji
from datetime import datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo
from core import globals as g
from core.logger import logger
from core.utils.yourls_client import shorten_url_with_yourls

from config.loader import load_config

CONFIG = load_config()
TTS_API = CONFIG['APIs']['Google TTS']

KST = ZoneInfo("Asia/Seoul")

# TTS 관련 설정을 모듈 내부에서 정의
TTS_CONFIG = {
    "voice_options": {
        # 확인된 정확한 음성 이름으로 설정
        "ko-KR": ["ko-KR-Chirp3-HD-Zephyr", "ko-KR-Chirp3-HD-Leda"],
        "en-US": ["en-US-Chirp3-HD-Achernar"]
    },
    "default_language": "ko-KR",
    "default_voice": "ko-KR-Chirp3-HD-Leda",  # 기본 음성 (여성)
    "speaking_rate": 1.0
}


# 이모티콘 및 특수 문자 제거 함수
def clean_text_for_tts(text: str) -> str:
    """
    TTS를 위한 자연스러운 한글 유지형 전처리:
    - 이모티콘 제거
    - 마크다운 기호 제거
    - 시각적 특수기호만 제거
    """

    # 1. 불필요한 접두어 제거
    ko_match = re.search(r'^한국어\s+(.*)', text, re.DOTALL)
    en_match = re.search(r'^영어\s+(.*)', text, re.DOTALL)
    if ko_match:
        text = ko_match.group(1).strip()
    elif en_match:
        text = en_match.group(1).strip()

    # 2. 이모지 제거 (emoji 라이브러리 사용)
    text = emoji.replace_emoji(text, replace='')

    # 3. 마크다운 강조 제거 (**강조**, ~~취소선~~ 등)
    text = re.sub(r'(\*\*|__|~~)(.*?)\1', r'\2', text)

    # 4. 시각적 특수기호 제거 (일부 Unicode만)
    text = re.sub(r'[•◆▶→■▪️★☆※✔️◇…→▶]', '', text)

    # 5. 연속 공백 정리
    text = re.sub(r'\s+', ' ', text).strip()

    return text


async def get_tts_output_path():
    """
    TTS 출력 파일이 저장될 경로 가져오기 (일별 폴더)
    """
    # 기본 경로
    base_path = "/home/loa/public_html/projects/kakao-bot"

    # 오늘 날짜로 폴더명 생성
    today = datetime.now(KST).strftime("%Y%m%d")
    output_dir = os.path.join(base_path, today)

    # 폴더가 없으면 생성
    os.makedirs(output_dir, exist_ok=True)

    return output_dir


# 액세스 토큰 캐시 (토큰 재사용)
_access_token = None
_token_expiry = datetime.now(KST)


async def get_access_token():
    """
    서비스 계정으로부터 액세스 토큰 생성

    Returns:
        str: 액세스 토큰
    """
    global _access_token, _token_expiry

    # 토큰이 유효하면 재사용
    if _access_token and _token_expiry > datetime.now(KST):
        return _access_token

    try:
        # 서비스 계정 정보 가져오기
        service_account_info = TTS_API

        # 필요한 정보 추출
        token_uri = service_account_info.get('token_uri', 'https://oauth2.googleapis.com/token')
        client_email = service_account_info.get('client_email')
        private_key = service_account_info.get('private_key')

        if not all([token_uri, client_email, private_key]):
            logger.error("[TTS] 서비스 계정 정보에 필요한 필드가 없습니다.")
            return None

        # JWT 헤더
        header = {
            "alg": "RS256",
            "typ": "JWT"
        }

        # 현재 시간과 만료 시간 계산
        now = datetime.now(KST)
        iat = int(now.timestamp())  # 발급 시간
        exp = int((now + timedelta(hours=1)).timestamp())  # 만료 시간 (1시간 후)

        # JWT 클레임
        claims = {
            "iss": client_email,
            "scope": "https://www.googleapis.com/auth/cloud-platform",
            "aud": token_uri,
            "exp": exp,
            "iat": iat
        }

        # JWT 헤더와 클레임을 base64url로 인코딩
        import base64
        def base64url_encode(data):
            if isinstance(data, dict):
                data = json.dumps(data).encode('utf-8')
            return base64.urlsafe_b64encode(data).rstrip(b'=').decode('utf-8')

        header_b64 = base64url_encode(header)
        claims_b64 = base64url_encode(claims)

        # 서명할 데이터 준비
        to_sign = f"{header_b64}.{claims_b64}"

        # 서명 생성
        from cryptography.hazmat.backends import default_backend
        from cryptography.hazmat.primitives import hashes
        from cryptography.hazmat.primitives.asymmetric import padding
        from cryptography.hazmat.primitives.serialization import load_pem_private_key

        # 개인 키 로드
        private_key_bytes = private_key.encode('utf-8')
        private_key_obj = load_pem_private_key(private_key_bytes, password=None, backend=default_backend())

        # 서명 생성
        signature = private_key_obj.sign(
            to_sign.encode('utf-8'),
            padding.PKCS1v15(),
            hashes.SHA256()
        )

        # 서명을 base64url로 인코딩
        signature_b64 = base64url_encode(signature)

        # JWT 토큰 완성
        jwt_token = f"{header_b64}.{claims_b64}.{signature_b64}"

        # OAuth2 토큰 요청
        async with aiohttp.ClientSession() as session:
            payload = {
                'grant_type': 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion': jwt_token
            }

            async with session.post(token_uri, data=payload) as response:
                if response.status != 200:
                    error_text = await response.text()
                    logger.error(f"[TTS] 액세스 토큰 요청 실패: {response.status} - {error_text}")
                    return None

                token_response = await response.json()
                _access_token = token_response.get('access_token')

                if not _access_token:
                    logger.error("[TTS] 액세스 토큰이 응답에 없습니다.")
                    return None

                # 만료 시간 설정 (응답의 expires_in 또는 기본값 3600초)
                expires_in = token_response.get('expires_in', 3600)
                _token_expiry = now + timedelta(seconds=expires_in - 300)  # 5분 여유 추가

                logger.info(f"[TTS] 새 액세스 토큰 생성 완료. 만료: {_token_expiry.strftime('%Y-%m-%d %H:%M:%S')}")
                return _access_token

    except Exception as e:
        logger.error(f"[TTS] 액세스 토큰 생성 중 오류: {str(e)}")
        return None


async def generate_voice_using_google_tts(text, language=None, voice_name=None, speaking_rate=None):
    """
    Google TTS API를 사용하여 음성 파일 생성 (서비스 계정 인증 방식)

    Args:
        text (str): 변환할 텍스트
        language (str): 언어 코드 (ko-KR 또는 en-US)
        voice_name (str): 음성 이름 (없으면 언어에 따라 자동 선택)
        speaking_rate (float): 말하기 속도

    Returns:
        dict: 생성된 음성 파일 정보 (성공 시)
            {
                'url': '원본 URL',
                'short_url': '단축 URL',
                'file_path': '파일 경로',
                'duration': '재생 시간(초)'
            }
        None: 실패 시
    """
    try:
        # 모듈 내부 정의 설정에서 기본값 가져오기
        if not language:
            language = TTS_CONFIG.get('default_language', 'ko-KR')

        if not voice_name:
            voice_name = TTS_CONFIG.get('default_voice')
            # 기본 음성이 없으면 언어에 따라 선택
            if not voice_name:
                voice_options = TTS_CONFIG.get('voice_options', {})
                if language in voice_options and voice_options[language]:
                    voice_name = voice_options[language][0]
                else:
                    # 최후의 기본값
                    voice_name = "ko-KR-Chirp3-HD-Zephyr" if language == "ko-KR" else "en-US-Chirp3-HD-Achernar"

        if not speaking_rate:
            speaking_rate = float(TTS_CONFIG.get('speaking_rate', 1.0))

        # 텍스트 정제
        clean_text = text  # clean_text_for_tts(text)
        if not clean_text:
            logger.warning("[TTS] 변환할 텍스트가 없습니다.")
            return None

        # 텍스트가 너무 짧은 경우 변환 거부 (최소 2글자 이상 필요)
        if len(clean_text) < 2:
            logger.warning(f"[TTS] 텍스트가 너무 짧습니다: '{clean_text}'")
            return None

        # 텍스트 길이 제한 (Google TTS API 제한: 5000자)
        if len(clean_text) > 5000:
            logger.warning(f"[TTS] 텍스트가 너무 깁니다. 5000자로 제한합니다. (원본: {len(clean_text)}자)")
            clean_text = clean_text[:5000]

        # 출력 경로 및 파일명 설정
        output_dir = await get_tts_output_path()
        timestamp = datetime.now(KST).strftime("%Y%m%d%H%M%S")
        file_uuid = str(uuid.uuid4())[:8]
        filename = f"tts_{timestamp}_{file_uuid}.mp3"
        file_path = os.path.join(output_dir, filename)

        # 액세스 토큰 가져오기
        access_token = await get_access_token()
        if not access_token:
            logger.error("[TTS] 액세스 토큰을 가져올 수 없습니다.")
            return None

        # Google Cloud TTS API 요청 데이터 준비 (일반 텍스트 사용)
        tts_request = {
            "input": {
                "text": clean_text
            },
            "voice": {
                "languageCode": language,
                "name": voice_name
            },
            "audioConfig": {
                "audioEncoding": "MP3",
                "speakingRate": speaking_rate,
                "pitch": 0
            }
        }

        logger.info(f"[TTS] 음성 생성 요청: 언어={language}, 음성={voice_name}, 텍스트 길이={len(clean_text)}자")
        logger.debug(f"[TTS] 요청 데이터: {json.dumps(tts_request)}")

        # Google TTS API 호출
        async with aiohttp.ClientSession() as session:
            url = "https://texttospeech.googleapis.com/v1/text:synthesize"
            headers = {
                "Authorization": f"Bearer {access_token}",
                "Content-Type": "application/json; charset=utf-8",
                "X-Goog-User-Project": TTS_API.get('project_id', '')  # 프로젝트 ID 추가
            }

            # 첫 번째 시도
            async with session.post(url, headers=headers, json=tts_request, timeout=180) as response:
                if response.status != 200:
                    error_text = await response.text()
                    logger.error(f"[TTS] Google TTS API 오류: {response.status} - {error_text}")

                    # HD 음성에서 실패한 경우 표준 음성으로 재시도
                    if "HD" in voice_name:
                        # 표준 음성으로 대체
                        standard_voice = "ko-KR-Standard-A" if language == "ko-KR" else "en-US-Standard-F"
                        logger.info(f"[TTS] HD 음성 오류. 표준 음성({standard_voice})으로 재시도합니다.")

                        # 요청 데이터 업데이트
                        tts_request["voice"]["name"] = standard_voice

                        # 재시도
                        async with session.post(url, headers=headers, json=tts_request) as retry_response:
                            if retry_response.status != 200:
                                retry_error = await retry_response.text()
                                logger.error(f"[TTS] 재시도 실패: {retry_response.status} - {retry_error}")
                                return None

                            result = await retry_response.json()
                    else:
                        # 일반 오류인 경우 실패 처리
                        return None
                else:
                    result = await response.json()

                # base64 디코딩 및 파일 저장
                audio_content = result.get('audioContent')
                if not audio_content:
                    logger.error("[TTS] 오디오 콘텐츠가 없습니다.")
                    return None

                audio_data = base64.b64decode(audio_content)

                with open(file_path, 'wb') as f:
                    f.write(audio_data)

                # 웹에서 접근 가능한 URL 생성
                today = datetime.now(KST).strftime("%Y%m%d")
                file_url = f"https://loa.best/projects/kakao-bot/{today}/{filename}"

                # URL 단축
                short_url = shorten_url_with_yourls(file_url)

                # 결과 반환
                result = {
                    'url': file_url,
                    'short_url': short_url,
                    'file_path': file_path,
                    'duration': 0  # 재생 시간 측정 기능은 추후 추가 가능
                }

                logger.info(f"[TTS] 음성 생성 성공: {short_url}")

                return result

    except Exception as e:
        logger.error(f"[TTS] 음성 생성 중 오류 발생: {str(e)}")
        return None


async def handle_tts_command(prompt, language=None):
    """
    TTS 명령어 처리 핸들러

    Args:
        prompt (str): TTS로 변환할 텍스트
        language (str, optional): 언어 코드 (기본값: ko-KR)

    Returns:
        str: 응답 메시지
    """
    if not prompt or prompt.strip() == "":
        return "변환할 텍스트를 입력해주세요."

    # 언어 감지 (간단 구현)
    if not language:
        # 한글이 포함되어 있으면 한국어, 아니면 영어로 가정
        if re.search(r'[가-힣]', prompt):
            language = "ko-KR"
        else:
            language = "en-US"

    # TTS 생성
    result = await generate_voice_using_google_tts(prompt, language=language)

    if not result:
        return "음성 변환에 실패했습니다. 다시 시도해주세요."

    # 응답 메시지 생성
    message = f"🔊 TTS 변환 완료!\n\n{result['short_url']}"

    return message


# 명령어 별칭 처리용 함수들
async def handle_tts_ko_command(prompt):
    """한국어 TTS 명령어 처리 (한국어 부분만 추출)"""
    # 가능하면 '한국어' 텍스트를 제외하고 실제 TTS 대상 텍스트만 추출
    match = re.search(r'한국어\s+(.*)', prompt, re.DOTALL)
    if match:
        text_only = match.group(1).strip()
        if text_only:  # 텍스트가 있으면 해당 텍스트만 TTS
            return await handle_tts_command(text_only, language="ko-KR")

    # 아니면 그냥 전체 텍스트를 TTS
    return await handle_tts_command(prompt, language="ko-KR")


async def handle_tts_en_command(prompt):
    """영어 TTS 명령어 처리 (영어 부분만 추출)"""
    # 가능하면 '영어' 텍스트를 제외하고 실제 TTS 대상 텍스트만 추출
    match = re.search(r'영어\s+(.*)', prompt, re.DOTALL)
    if match:
        text_only = match.group(1).strip()
        if text_only:  # 텍스트가 있으면 해당 텍스트만 TTS
            return await handle_tts_command(text_only, language="en-US")

    # 아니면 그냥 전체 텍스트를 TTS
    return await handle_tts_command(prompt, language="en-US")
