import asyncio
import time
import traceback
from datetime import datetime
import core.globals as g
from core.utils.send_message import send_message_response
from core.logger import logger

# 메시지 전송 제한을 위한 상태 관리
last_sent_times = []
# 대기 중인 알림을 위한 큐 (최대 100개로 제한)
pending_notifications = []
# 재귀 에러 방지를 위한 플래그
_in_error_notification = False
# 연속 실패 카운터
_consecutive_failures = 0
# 최대 연속 실패 허용 횟수
MAX_CONSECUTIVE_FAILURES = 3
# 실패 후 대기 시간 (초)
FAILURE_WAIT_TIME = 60
# 큐 최대 크기
MAX_QUEUE_SIZE = 100


async def notify_error(error_message, error_type="ERROR", context=None, exc_info=None):
    """
    에러 메시지를 특정 채널에 전송합니다.

    Args:
        error_message (str): 에러 메시지
        error_type (str): 에러 타입 (ERROR, WARNING, CRITICAL)
        context (dict, optional): 에러 발생 컨텍스트
        exc_info (Exception, optional): 예외 정보
    """
    global _in_error_notification, _consecutive_failures, pending_notifications

    # writer 관련 에러는 큐에 추가하지 않음
    if "writer가 닫혀 있어 전송할 수 없음" in error_message:
        logger.warning("[ERROR_NOTIFIER] writer 관련 에러는 무시됩니다.")
        return

    # 이미 에러 알림 처리 중이면 무한 루프 방지를 위해 종료
    if _in_error_notification:
        logger.warning("[ERROR_NOTIFIER] 이미 에러 알림 처리 중입니다. 중복 알림 방지를 위해 건너뜁니다.")
        return

    # 연속 실패가 너무 많으면 일정 시간 대기
    if _consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
        logger.warning(f"[ERROR_NOTIFIER] 연속 {MAX_CONSECUTIVE_FAILURES}회 실패로 {FAILURE_WAIT_TIME}초 대기")
        await asyncio.sleep(FAILURE_WAIT_TIME)
        _consecutive_failures = 0

    _in_error_notification = True

    try:
        error_config = g.ERROR_NOTIFICATION

        if not error_config.get("enabled", False):
            # 비활성화되어 있으면 무시
            return

        # 로그 레벨 확인
        if error_type not in error_config.get("log_levels", ["ERROR", "CRITICAL"]):
            if error_type == "WARNING" and not error_config.get("include_warning", False):
                return

        # 발송 제한 확인
        current_time = time.time()
        global last_sent_times

        # 1분 이내 메시지만 유지
        last_sent_times = [t for t in last_sent_times if current_time - t < 60]

        max_per_minute = error_config.get("max_per_minute", 5)
        if len(last_sent_times) >= max_per_minute:
            logger.warning(f"[ERROR_NOTIFIER] 분당 {max_per_minute}개 제한 초과로 에러 알림 건너뜀")
            return

        # 메시지 구성
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        message_parts = [
            f"⚠️ {error_type} 발생 ({timestamp})",
            f"메시지: {error_message}"
        ]

        # 컨텍스트 정보 추가
        if context:
            context_info = "\n".join([f"- {k}: {v}" for k, v in context.items() if
                                    k in ["channel_id", "room", "sender", "prefix", "module", "function", "line"]])
            if context_info:
                message_parts.append(f"\n[컨텍스트]\n{context_info}")

        # 스택 트레이스 추가
        if exc_info:
            tb = traceback.format_exception(type(exc_info), exc_info, exc_info.__traceback__)
            stack_trace = "".join(tb).split("\n")[-3:]  # 마지막 3줄만 포함
            message_parts.append(f"\n[스택 트레이스]\n{''.join(stack_trace)}")

        final_message = "\n\n".join(message_parts)

        # 알림 전송
        channel_id = error_config.get("channel_id")
        if channel_id:
            # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
            bot_name = "LOA.i"  # 기본값
            room_name = g.channel_id_to_room.get(channel_id, "알 수 없는 방")
            
            context = {
                'bot_name': bot_name,
                'channel_id': channel_id,
                'room': room_name
            }

            await send_message_response(context, final_message)
            logger.info(f"[ERROR_NOTIFIER] 오류 알림 전송 성공 → {bot_name} / {room_name}")

    except Exception as e:
        # 에러 알림 중 에러가 발생하면 로그만 남김
        _consecutive_failures += 1
        logger.error(f"[ERROR_NOTIFIER] 에러 알림 전송 중 오류 발생: {e}")

    finally:
        # 함수 종료 시 플래그 초기화
        _in_error_notification = False


async def process_pending_notifications():
    """대기 중인 알림 처리 함수"""
    global _in_error_notification, _consecutive_failures, pending_notifications

    # 에러 알림 처리 중이면 건너뜁니다
    if _in_error_notification:
        return

    # 연속 실패가 너무 많으면 일정 시간 대기
    if _consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
        logger.warning(f"[ERROR_NOTIFIER] 연속 {MAX_CONSECUTIVE_FAILURES}회 실패로 {FAILURE_WAIT_TIME}초 대기")
        await asyncio.sleep(FAILURE_WAIT_TIME)
        _consecutive_failures = 0

    _in_error_notification = True

    try:
        if not pending_notifications:
            return

        # 3시간 이상 된 알림은 삭제 (오래된 알림은 의미 없음)
        current_time = time.time()
        pending_notifications = [n for n in pending_notifications if current_time - n["timestamp"] < 10800]

        # 큐가 비었으면 종료
        if not pending_notifications:
            return

        # 큐에 있는 알림 처리 시도
        remaining = []
        for notification in pending_notifications[:10]:  # 한 번에 최대 10개만 처리
            try:
                channel_id = notification["channel_id"]
                message = notification["message"]
                context = notification.get("context")

                # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
                bot_name = "LOA.i"  # 기본값
                room_name = g.channel_id_to_room.get(channel_id, "알 수 없는 방")
                
                context = {
                    'bot_name': bot_name,
                    'channel_id': channel_id,
                    'room': room_name
                }

                success = await send_message_response(context, message)
                if success:
                    logger.info("[ERROR_NOTIFIER] 대기 중이던 알림 전송 성공")
                    continue

                # 실패한 경우 남은 알림 목록에 추가
                remaining.append(notification)

            except Exception as e:
                logger.error(f"[ERROR_NOTIFIER] 대기 알림 처리 중 오류: {e}")
                remaining.append(notification)

        # 남은 알림으로 큐 업데이트
        pending_notifications = remaining

    except Exception as e:
        logger.error(f"[ERROR_NOTIFIER] 대기 알림 처리 중 예외 발생: {e}")

    finally:
        _in_error_notification = False
