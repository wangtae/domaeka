import datetime
import logging
from core.timeout_manager import timeout_manager
from core.utils.send_message import send_message_response

# 오목 세션 저장소 (channel_id -> OmokSession)
omok_sessions = {}

# 오목 타임아웃 등록/갱신
# 타임아웃 시퀀스 넘버 (채널별 등록 순서 가독성용)
timeout_seq_dict = {}

def reset_omok_timeout(context, move_timeout):
    channel_id = str(context["channel_id"])
    # 채널별 시퀀스 관리: 해제 후 재등록 시 1부터 시작
    seq = timeout_seq_dict.get(channel_id, 0) + 1
    timeout_seq_dict[channel_id] = seq
    context["timeout_seq"] = seq
    session = None
    if "omok_session" in context:
        session = context["omok_session"]
    elif hasattr(context, "omok_session"):
        session = context.omok_session
    turn = session.turn if session else None
    player1 = session.player1 if session else None
    player2 = session.player2 if session else None
    now = datetime.datetime.now()
    expire_at = now + datetime.timedelta(seconds=move_timeout)
    logger = logging.getLogger(__name__)
    logger.info(f"[OMOK][DEBUG][타임아웃등록][TIMEOUT_SEQ={seq}] reset_omok_timeout 진입: channel_id={context['channel_id']}, move_timeout={move_timeout}")
    logger.info(
        f"[OMOK][DEBUG][타임아웃등록][TIMEOUT_SEQ={seq}] key=omok:{channel_id}, move_timeout={move_timeout}, "
        f"turn={turn}, player1={player1}, player2={player2}, context_channel_id={context['channel_id']}, "
        f"등록시각={now.strftime('%Y-%m-%d %H:%M:%S')}, 만료예정={expire_at.strftime('%Y-%m-%d %H:%M:%S')}"
    )
    logger.info(f"[OMOK][DEBUG][타임아웃등록][TIMEOUT_SEQ={seq}] 등록 전 전체 타임아웃 키: {getattr(timeout_manager, 'list_keys', lambda: 'N/A')()}")
    timeout_manager.add(
        key=f'omok:{channel_id}',
        timeout_sec=move_timeout,
        callback=handle_omok_timeout,
        context=context
    )
    logger.info(f"[OMOK][DEBUG][타임아웃등록][TIMEOUT_SEQ={seq}] 등록 후 전체 타임아웃 키: {getattr(timeout_manager, 'list_keys', lambda: 'N/A')()}")

# 오목 타임아웃 해제
def clear_omok_timeout(context):
    channel_id = str(context["channel_id"])
    key = f'omok:{channel_id}'
    logger = logging.getLogger(__name__)
    logger.info(f"[OMOK][DEBUG][타임아웃해제] clear_omok_timeout 진입: key={key}, context_channel_id={context['channel_id']}")
    print(f"[OMOK][DEBUG][타임아웃해제] clear_omok_timeout 진입: key={key}, context_channel_id={context['channel_id']}")
    logger.info(f"[OMOK][DEBUG][타임아웃해제] 해제 전 전체 타임아웃 키: {getattr(timeout_manager, 'list_keys', lambda: 'N/A')()}")
    timeout_manager.remove(key)
    logger.info(f"[OMOK][DEBUG][타임아웃해제] remove 호출 완료: key={key}")
    print(f"[OMOK][DEBUG][타임아웃해제] remove 호출 완료: key={key}")
    logger.info(f"[OMOK][DEBUG][타임아웃해제] 해제 후 전체 타임아웃 키: {getattr(timeout_manager, 'list_keys', lambda: 'N/A')()}")
    print(f"[OMOK][DEBUG][타임아웃해제] 해제 후 전체 타임아웃 키: {getattr(timeout_manager, 'list_keys', lambda: 'N/A')()}")
    # 타임아웃 해제 시 시퀀스 초기화
    timeout_seq_dict[channel_id] = 0

# 오목 타임아웃 콜백
async def handle_omok_timeout(context):
    channel_id = context.get("channel_id")
    logger = logging.getLogger(__name__)
    logger.info(f"[OMOK][DEBUG] handle_omok_timeout 진입: channel_id={channel_id}")
    session = omok_sessions.get(channel_id)
    if not session or session.state not in ("playing", "opening"):
        logger.info(f"[OMOK][DEBUG] 타임아웃 발생 시 세션 없음 또는 진행 상태 아님: {channel_id}")
        return
    room = context.get("room")
    if not room:
        logger.warning(f"[OMOK][DEBUG] 타임아웃 발생 시 room 정보 없음: {channel_id}")
        return
    # 게임 미시작(start_timeout)
    if (session.player2 is None and session.ai_level is None and not session.move_history):
        session.state = "ended"
        clear_omok_timeout(context)
        await send_message_response(
            context,
            "⏰ 제한 시간 내에 게임이 시작되지 않아 게임이 무효 처리되었습니다."
        )
        del omok_sessions[channel_id]
        return
    # 일반 타임아웃(착수 지연)
    loser = session.get_current_player_name()
    winner = session.get_opponent_color()
    session.state = "ended"
    clear_omok_timeout(context)
    await send_message_response(
        context,
        f"⏰ 제한 시간 초과! {loser}님이 패배하였습니다."
    )
    await session.end_game(winner, context)
    del omok_sessions[channel_id] 