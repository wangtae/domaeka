import json
import asyncio
from core.logger import logger

async def reload_all_json_files(ctx=None):
    """
    모든 JSON 설정 파일을 다시 로드합니다.

    :param ctx: 컨텍스트 정보 (옵션)
    :return: 처리 결과 메시지
    """
    from core import globals as g

    g.logger.info("[RELOAD_ALL_JSON] 🔄 JSON 설정 리로드 함수 실행 시작")
    results = []

    # ✅ 처리 전 스케줄 데이터 정보 기록 (디버깅)
    before_schedule_count = sum(len(channels) for bot, channels in g.schedule_rooms.items())
    g.logger.debug(f"[RELOAD_ALL_JSON] 리로드 전 스케줄 방 개수: {before_schedule_count}")

    for key, path in g.JSON_CONFIG_FILES.items():
        g.logger.debug(f"[RELOAD_ALL_JSON] 🔍 처리 중: {key} → {path}")
        g_var = g.JSON_DATA_VARS.get(key)

        try:
            with path.open(encoding='utf-8') as f:
                loaded_data = json.load(f)

            # 데이터 로드 성공 시 전역 변수에 적용
            g.__dict__[g_var] = loaded_data

            # ✅ schedule_rooms와 scheduled_messages 동기화 (schedule_rooms가 변경된 경우)
            if key == "schedule_rooms":
                # scheduled_messages와 schedule_rooms는 동일한 참조여야 함
                # 이미 globals.py에서 설정됨

                # 스케줄 변경 이벤트 설정 (스케줄러에게 알림)
                g.schedule_reload_event.set()
                g.logger.info("[RELOAD_ALL_JSON] 스케줄 데이터 변경 이벤트 설정 완료")

            g.logger.info(f"[RELOAD_ALL_JSON] ✅ {key} 리로드 성공")
            results.append(f"✅ {key} 리로드 완료")

            # 후처리 함수가 존재할 경우 실행
            if key == "auto_replies":
                g.logger.debug("[RELOAD_ALL_JSON] 🔄 auto_replies 후처리 함수 실행")
                await g.load_auto_replies()

        except Exception as e:
            g.__dict__[g_var] = {}
            g.logger.error(f"[RELOAD_ALL_JSON] ❌ {key} 리로드 실패 → {e}")
            results.append(f"❌ {key} 리로드 실패: {str(e)}")

    # ✅ PREFIX_MAP 및 ENABLED_PREFIXES 재로딩
    try:
        from core.command_loader import load_prefix_map_from_json
        g.PREFIX_MAP, g.ENABLED_PREFIXES = load_prefix_map_from_json(g.json_command_data)
        g.logger.info("[RELOAD_ALL_JSON] ✅ PREFIX_MAP 및 ENABLED_PREFIXES 재로딩 완료")
    except Exception as e:
        g.logger.error(f"[RELOAD_ALL_JSON] ❌ PREFIX_MAP 로딩 실패 → {e}")
        results.append(f"❌ PREFIX_MAP 로딩 실패: {str(e)}")

    # ✅ 처리 후 스케줄 데이터 정보 기록 (디버깅)
    after_schedule_count = sum(len(channels) for bot, channels in g.schedule_rooms.items())
    g.logger.debug(f"[RELOAD_ALL_JSON] 리로드 후 스케줄 방 개수: {after_schedule_count}")

    if before_schedule_count != after_schedule_count:
        results.append(f"✅ 스케줄 데이터 변경: {before_schedule_count}개 → {after_schedule_count}개 방")

    result_text = "\n".join(results).strip()
    if not result_text:
        g.logger.warning("[RELOAD_ALL_JSON] ⚠️ 결과 메시지가 비어 있습니다.")
        result_text = "⚠️ 리로드 결과가 없습니다. 로그를 확인해 주세요."
    else:
        g.logger.info(f"[RELOAD_ALL_JSON] ✅ 전체 리로드 결과:\n{result_text}")

    return result_text


async def handle_reload_json_command(ctx):
    """
    # reload json 명령어 핸들러
    """
    result = await reload_all_json_files(ctx)

    # 추가 디버깅: 현재 메모리에 로드된 스케줄 정보 요약
    from core import globals as g

    debug_info = []
    schedule_count = 0

    for bot, channels in g.schedule_rooms.items():
        for cid, conf in channels.items():
            room_name = conf.get("room_name", "알 수 없음")
            schedules_count = len(conf.get("schedules", []))
            schedule_count += schedules_count

            # 중요한 방 정보만 로그에 기록
            if schedules_count > 0:
                debug_info.append(f"- {bot}/{cid} ({room_name}): {schedules_count}개 스케줄")

    summary = f"\n현재 총 {len(g.schedule_rooms)}개 봇, {schedule_count}개 스케줄 로드됨"
    g.logger.info(f"[RELOAD DEBUG] 스케줄 정보 요약:{summary}")

    if debug_info:
        g.logger.debug("[RELOAD DEBUG] 스케줄 세부 정보:\n" + "\n".join(debug_info))

    # 결과 메시지에 스케줄 정보 요약 추가
    return f"{result}\n\n{summary}"