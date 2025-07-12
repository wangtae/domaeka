"""
스케줄 메시지 처리 모듈

설정된 시간에 자동으로 메시지를 전송하는 기능을 담당합니다.
TTS 처리를 command_dispatcher.py로 이관
"""

import asyncio
import json
import random
from datetime import datetime
import pytz
from pathlib import Path

from core import globals as g
from core.utils.send_message import send_message, send_message_response
from core.logger import logger
from core.performance import measure_performance
from core.utils.template_variables import process_template_variables_async

from core.utils.cache_service import clear_expired_cache
from services.user_service import get_bot_device_status

KST = pytz.timezone('Asia/Seoul')


# ✅ 스케줄 JSON 단발성 로드 함수
@measure_performance("fetch_schedule_data")
async def fetch_schedule_data():
    schedule_file_path = g.JSON_CONFIG_FILES["schedule_rooms"]
    logger.info(f"[SCHEDULE] JSON 스케줄 파일 로드 시도 → {schedule_file_path}")

    try:
        if not schedule_file_path.exists():
            logger.warning(f"[SCHEDULE] 스케줄 파일이 존재하지 않음 → {schedule_file_path}")
            return False, "스케줄 파일이 존재하지 않습니다."

        logger.info(f"[SCHEDULE] {schedule_file_path} 파일 로딩 시작")

        with schedule_file_path.open('r', encoding='utf-8') as file:
            data = json.load(file)

        if not isinstance(data, dict):
            msg = f"[SCHEDULE] 잘못된 포맷 → dict가 아님 ({type(data)})"
            logger.warning(msg)
            return False, msg

        # ✅ 스케줄 데이터 저장 전 디버깅 정보 기록
        old_count = sum(len(channels) for bot, channels in g.schedule_rooms.items())

        # ✅ g.schedule_rooms와 g.scheduled_messages는 동일한 객체 참조
        g.schedule_rooms.clear()
        g.schedule_rooms.update(data)

        new_count = sum(len(channels) for bot, channels in g.schedule_rooms.items())

        # ✅ 스케줄 변경 이벤트 설정 (스케줄러에게 알림)
        g.schedule_reload_event.set()

        msg = f"[SCHEDULE] 로드 성공! {old_count}개 → {new_count}개 채널 데이터 갱신"
        logger.info(msg)
        return True, msg

    except Exception as e:
        msg = f"[SCHEDULE ERROR] 스케줄 파일 로드 실패 → {e}"
        logger.error(msg, exc_info=True)
        return False, msg


# ✅ 스케줄 메시지 전송 함수 (result가 리스트인 경우에 대한 처리 추가)
async def send_scheduled_message(context, result):
    """
    스케줄 메시지 전송을 위한 헬퍼 함수
    리스트 형태의 메시지를 순차적으로 처리합니다.
    context: {'bot_name': ..., 'channel_id': ..., 'room': ...}
    
    Note: writer는 send_message_response에서 bot_name을 통해 자동으로 검색됩니다.
    """
    try:
        from core.utils.send_message import send_message_response
        from core.db_utils import save_chat_to_db
        from core.utils.prefix_utils import parse_prefix

        # 리스트 형태의 메시지 처리
        if isinstance(result, list):
            for i, message in enumerate(result):
                await send_message_response(context, message)
                
                # DB에 저장
                prefix, _ = parse_prefix(message, bot_name=context.get('bot_name'))
                await save_chat_to_db(
                    pool=g.db_pool,
                    room_name=context.get('room'),
                    sender=context.get('bot_name'),
                    message=message,
                    bot_name=context.get('bot_name'),
                    is_mention=False,
                    is_group_chat=True,
                    channel_id=context.get('channel_id'),
                    user_hash="(bot)",
                    is_bot=True,
                    directive="SCHEDULED_MSG",  # 스케줄 메시지임을 명시 (길이 제한 준수)
                    message_type="scheduled",
                    is_meaningful=0,
                    is_scheduled=True
                )
                
                if i != len(result) - 1:
                    await asyncio.sleep(1)  # 메시지 간 1초 지연
            logger.info(f"[SCHEDULED_MESSAGE] 전송 성공 → {context.get('bot_name', 'Unknown')} / {context.get('room')} / {str(result)[:50]}...")
            return True
        else:
            await send_message_response(context, result)
            
            # DB에 저장
            prefix, _ = parse_prefix(result, bot_name=context.get('bot_name'))
            await save_chat_to_db(
                pool=g.db_pool,
                room_name=context.get('room'),
                sender=context.get('bot_name'),
                message=result,
                bot_name=context.get('bot_name'),
                is_mention=False,
                is_group_chat=True,
                channel_id=context.get('channel_id'),
                user_hash="(bot)",
                is_bot=True,
                directive="SCHEDULED_MSG",  # 스케줄 메시지임을 명시 (길이 제한 준수)
                message_type="scheduled",
                is_meaningful=0,
                is_scheduled=True
            )
            
            logger.info(f"[SCHEDULED_MESSAGE] 전송 성공 → {context.get('bot_name', 'Unknown')} / {context.get('room')} / {str(result)[:50]}...")
            return True
    except Exception as e:
        logger.error(f"[SCHEDULE ERROR] 메시지 전송 실패 → {e}", exc_info=True)
        return False


# ✅ 스케줄 메시지 전송 루프
@measure_performance("scheduled_sender")
async def scheduled_sender():
    logger.info("[SCHEDULE] 스케줄 메시지 전송기 시작")

    while True:
        try:
            now = datetime.now(KST)
            current_time = now.strftime("%H:%M")
            logger.debug(f"[SCHEDULE] 현재 시각: {current_time}")

            # 매 시간 정각에 만료된 캐시 정리
            if current_time.endswith(":00"):
                try:
                    deleted_count = await clear_expired_cache()
                    if deleted_count > 0:
                        logger.info(f"[SCHEDULE] 만료된 캐시 {deleted_count}개 정리 완료")
                except Exception as cache_error:
                    logger.error(f"[SCHEDULE] 캐시 정리 중 오류 발생: {cache_error}")

            logger.debug(f"[SCHEDULE] 스케줄 데이터 확인 시작 → 봇 수: {len(g.schedule_rooms)}")
            
            for bot_name, channels in g.schedule_rooms.items():
                logger.debug(f"[SCHEDULE] 봇 처리 시작 → {bot_name}, 채널 수: {len(channels)}")
                
                # status 체크 추가
                status = await get_bot_device_status(bot_name)
                if status != 'approved':
                    logger.warning(f"[SCHEDULE_BLOCKED] 승인되지 않은 봇: bot={bot_name}, status={status} → 스케줄 메시지 발송 차단")
                    continue
                    
                for channel_id, channel_data in channels.items():
                    room_name = channel_data.get("room_name", "알 수 없는 방")  # 기본값 추가
                    schedules = channel_data.get("schedules", [])
                    
                    for schedule_idx, sched in enumerate(schedules):
                        days = sched.get("days", [])
                        times = sched.get("times", [])
                        
                        should_send_result = should_send(sched, now, current_time)
                        
                        if not should_send_result:
                            continue
                        
                        logger.info(f"[SCHEDULE] ✅ 조건 만족! 메시지 전송 시작 → {bot_name}/{room_name}[{schedule_idx}]")

                        try:
                            messages = sched.get("messages", [])
                            
                            if not messages:
                                logger.warning(f"[SCHEDULE] 메시지가 없음 → {bot_name}/{room_name}[{schedule_idx}]")
                                continue
                                
                            message = random.choice(messages)

                            # 템플릿 변수 처리
                            context = {
                                "room": room_name,
                                "channel_id": channel_id
                            }

                            # {{DATE_HASH}} 변수가 있는 경우를 위한 특수 처리
                            if "{{DATE_HASH}}" in message:
                                context["date_hash_modulo"] = 66  # 성경 66권 처리를 위한 모듈로

                            message = await process_template_variables_async(message, context)

                            # 명령어 처리
                            from core.utils.prefix_utils import parse_prefix
                            from services.command_dispatcher import process_command

                            prefix, prompt = parse_prefix(message, bot_name=bot_name)

                            # TTS 설정 가져오기 (TTS 설정만 전달)
                            tts_config = sched.get("tts", {})

                            if prefix is None:
                                # 일반 텍스트 메시지 (명령어가 아닌 경우)
                                result = message
                            else:
                                try:
                                    # ✅ command_dispatcher.py의 process_command 함수로 TTS 설정 전달
                                    result = await process_command({
                                        "prefix": prefix,
                                        "prompt": prompt,
                                        "channel_id": channel_id,
                                        "bot_name": bot_name,
                                        "room": room_name,
                                        "writer": None,  # 스케줄러는 특정 클라이언트가 없으므로 None
                                        "is_scheduled": True,  # 스케줄링된 메시지임을 표시
                                        "schedule_index": schedule_idx,  # 현재 실행 중인 스케줄 인덱스
                                        "tts_config": tts_config  # TTS 설정 전달
                                    })

                                    if result is None:
                                        logger.warning(
                                            f"[SCHEDULE] 명령 실행 결과 없음 → {bot_name} / {room_name} / prefix: {prefix}")
                                        continue
                                except Exception as e:
                                    # LLM 오류 처리
                                    logger.error(f"[SCHEDULE] 명령어 실행 오류 → {e}")
                                    # 스케줄 메시지는 오류 시 메시지를 보내지 않음 (continue로 처리)
                                    continue

                            # ✅ send_message_response가 자동으로 writer를 찾도록 context 구성
                            context = {
                                'bot_name': bot_name,
                                'channel_id': channel_id,
                                'room': room_name
                            }
                            
                            await send_scheduled_message(context, result)
                            logger.info(f"[SCHEDULE] ✅ 메시지 전송 성공 → {bot_name}/{room_name}[{schedule_idx}]: {str(result)[:30]}...")

                        except Exception as e:
                            logger.error(f"[SCHEDULE ERROR] {bot_name} / {room_name} 처리 실패 → {e}", exc_info=True)

        except Exception as loop_error:
            logger.error(f"[SCHEDULE ERROR] 전체 루프 실패 → {loop_error}", exc_info=True)

        # 기존 리로드 로직 유지
        try:
            await asyncio.wait_for(g.schedule_reload_event.wait(), 60)
            g.schedule_reload_event.clear()
            logger.info("[SCHEDULE] 스케줄 데이터 리로드 감지! 즉시 새 데이터 적용")
        except asyncio.TimeoutError:
            pass


# ✅ 발송 조건 검사 함수
def should_send(schedule, now, current_time):
    days = schedule.get("days", [])
    times = schedule.get("times", [])

    weekday_map = {
        "Mon": "월", "Tue": "화", "Wed": "수", "Thu": "목",
        "Fri": "금", "Sat": "토", "Sun": "일"
    }

    today = now.strftime("%a")  # 예: "Mon"
    today_korean = weekday_map.get(today, "")
    
    day_match = today_korean in days
    time_match = current_time in times
    
    return day_match and time_match
