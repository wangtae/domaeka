import json
from pathlib import Path
from core.logger import logger

async def reload_json_file(file_path: Path, target: dict, description: str = "JSON 데이터"):
    """
    공용 JSON 파일 리로딩 함수

    :param file_path: JSON 파일 경로 (Path 객체 권장)
    :param target: 데이터를 갱신할 dict 객체
    :param description: 로깅용 설명 텍스트
    :return: (성공 여부: bool, 상세 메시지: str)
    """
    try:
        if not file_path.exists():
            msg = f"{description} 파일이 존재하지 않습니다. → {file_path}"
            logger.warning(f"[RELOAD] {msg}")
            return False, msg

        logger.info(f"[RELOAD] {description} 파일 로딩 시작 → {file_path}")

        with file_path.open('r', encoding='utf-8') as f:
            data = json.load(f)

        if not isinstance(data, dict):
            msg = f"{description} JSON 형식 오류 (dict가 아님). 타입: {type(data)}"
            logger.warning(f"[RELOAD] {msg}")
            return False, msg

        # 기존 데이터 백업 (디버깅용)
        old_keys = list(target.keys())
        old_count = len(old_keys)

        # 데이터 갱신
        target.clear()
        target.update(data)

        new_count = len(target)

        # 변경점 기록
        if old_count != new_count:
            diff_msg = f"항목 수 변경: {old_count} → {new_count}"
            logger.info(f"[RELOAD] {diff_msg}")

        # 로그 메시지
        msg = f"{description} 로드 성공! 항목 수: {new_count}"
        logger.info(f"[RELOAD] {msg}")

        # g.schedule_reload_event가 있는 경우 이벤트 설정
        if description.lower().find("스케줄") >= 0:
            try:
                from core import globals as g
                if hasattr(g, 'schedule_reload_event'):
                    g.schedule_reload_event.set()
                    logger.info("[RELOAD] 스케줄 데이터 변경 이벤트 설정 완료")
            except ImportError:
                logger.warning("[RELOAD] globals 모듈 가져오기 실패, 스케줄 이벤트 설정 생략")

        return True, msg

    except Exception as e:
        msg = f"{description} 로드 실패: {str(e)}"
        logger.error(f"[RELOAD ERROR] {msg}", exc_info=True)
        return False, msg