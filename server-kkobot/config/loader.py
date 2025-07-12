from pathlib import Path
from cryptography.fernet import Fernet
import json
from core.logger import logger
from core.performance import Timer

def load_config():
    logger.info("[CONFIG] 설정 파일 로드 시작")

    # 현재 파일(loader.py)의 절대 경로를 기준으로 상대 경로 설정
    current_file_path = Path(__file__).resolve()
    # /home/wangt/cursor/projects/py/kakao-bot/server/config/loader.py
    # 프로젝트 루트(/home/wangt/cursor/projects/py/kakao-bot)로 이동
    project_root = current_file_path.parent.parent

    # .kakao-bot.key와 .kakao-bot.enc 파일이 있는 'configs' 디렉토리 경로 (프로젝트 루트의 상위 폴더인 py 폴더에 있다고 가정)
    configs_dir = project_root.parent / '.cfg'

    key_file = configs_dir / '.kkobot.key'
    enc_file = configs_dir / '.kkobot.enc'

    try:
        if not key_file.exists():
            logger.error(f"[CONFIG] 키 파일 없음 → {key_file}")
            raise FileNotFoundError(f"키 파일 없음: {key_file}")

        if not enc_file.exists():
            logger.error(f"[CONFIG] 암호화 파일 없음 → {enc_file}")
            raise FileNotFoundError(f"암호화 파일 없음: {enc_file}")

        with Timer("load_config"):
            with key_file.open('rb') as kf:
                key = kf.read()

            with enc_file.open('rb') as ef:
                encrypted = ef.read()

            cipher = Fernet(key)
            decrypted = cipher.decrypt(encrypted)

            config = json.loads(decrypted.decode('utf-8'))

        # ✅ config 구조 검증 (예시)
        if not isinstance(config, dict):
            logger.error("[CONFIG] 로드된 config가 딕셔너리가 아님")
            raise ValueError("설정 파일 형식 오류")

        logger.info("[CONFIG] 설정 파일 로드 완료")

        return config

    except Exception as e:
        logger.exception(f"[CONFIG] 설정 파일 로드 실패 → {e}")
        raise  # 실패하면 상위에서 처리 (강제 종료 등)
