import core.globals as g
import asyncio
import random
import time
import re
import json

from core.db_utils import save_chat_to_db
from core.utils.send_message import send_message_response, send_ping_event_to_client
from core.logger import logger
from core.performance import Timer
from core.utils.auth_utils import is_admin, is_admin_or_room_owner  # 방장 권한 함수 임포트 추가
from core.utils.auth_utils import AuthenticationService  # HMAC 인증 서비스 임포트
from core.utils.template_variables import process_template_variables_async
from core.globals import PREFIX_MAP, WAITING_MESSAGES, LLM_ERROR_MESSAGES, GENERAL_ERROR_MESSAGES, AUTH_CONFIG_PATH
from core.utils.prefix_utils import parse_prefix
from core.auto_reply_handler import handle_auto_reply
from core.utils.cache_service import get_cached_response
from core.conversation_joiner import add_message_to_history
from core.sessions.session_manager import get_active_session
from services.llm_chat_sessions.session_processor import process_session_message
from core.error_notifier import notify_error  # 에러 알림 함수 임포트 추가
from core.utils.bot_response_saver import save_bot_response

from services.user_service import save_or_update_user
from services.user_service import get_bot_device_status, block_bot_device
from services.command_dispatcher import process_command
# 유튜브 서비스 임포트 추가
from services.youtube_service import process_auto_youtube_summary
from services.webpage_service import process_auto_webpage_summary

from games.omok.handlers.omok_globals import omok_sessions
from games.omok.handlers.omok_command_handler import handle_omok_move, handle_swap_color_choice, handle_omok_command
from games.omok.handlers.stop_game_handler import handle_stop_command
from games.omok.handlers.join_game_handler import handle_join_game
from games.omok.handlers.mode_handler import handle_mode_selection

auth_service = AuthenticationService()


def get_ignored_user_settings(channel_id, user_hash, sender):
    """
    채널 ID와 사용자 해시 또는 이름을 기반으로 무시 설정을 반환합니다.

    Args:
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시
        sender (str): 발신자 이름

    Returns:
        dict or None: 무시 설정이 있으면 설정 딕셔너리, 없으면 None
    """
    try:
        # schedule_data는 schedule-rooms.json의 로드된 데이터
        for bot_name, channels in g.schedule_rooms.items():
            if str(channel_id) in channels:
                channel_config = channels[str(channel_id)]
                ignored_users = channel_config.get('ignored_users', [])

                for user in ignored_users:
                    # 해시로 확인하거나 닉네임으로 확인
                    if user.get('user_hash') == user_hash or user.get('nickname') == sender:
                        return user
        return None
    except Exception as e:
        logger.error(f"[무시 사용자 설정 조회 오류] {e}")
        return None


async def process_message(received_message: dict):
    # 0단계: 승인 상태 먼저 체크
    bot_name = received_message.get('bot_name')
    if bot_name:
        status = await get_bot_device_status(bot_name)
        if status != 'approved':
            logger.warning(f"[BOT_DEVICE_DENY] 승인되지 않은 봇: bot={bot_name}, status={status}")
            await send_message_response(received_message, ['@no-reply'])
            return
    
    # 로그 설정 미리 확인
    data = received_message.get('data', {}) if 'data' in received_message else received_message
    auth = data.get('auth') or received_message.get('auth') or {}
    def pick(*args):
        for v in args:
            if v is not None:
                return v
        return None
    channel_id = str(pick(data.get('channelId'), auth.get('channelId'), received_message.get('channel_id'), ''))
    room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
    log_settings = room_config.get("log_settings", {})
    disable_chat_logs = log_settings.get("disable_chat_logs", False)
    
    # HMAC 인증 적용 (1단계)
    auth_data = (
        received_message.get('auth') or
        received_message.get('data', {}).get('auth') or
        received_message.get('data', {}).get('data', {}).get('auth')
    )
    client_info = None
    if auth_data:
        success, msg, client_info = auth_service.validate_auth(auth_data)
        if not success:
            logger.error(f"[AUTH_FAIL][CRITICAL] 인증 실패: {msg} | bot={bot_name} | auth_data={auth_data}")
            await block_bot_device(bot_name)
            return  # 인증 실패 시 즉시 종료(응답 없음)
        received_message['client_info'] = client_info

    #logger.debug(f"[DEBUG] process_messenger_bot_message 호출 직전 message: {received_message}")
    if not disable_chat_logs:
        logger.debug(f"[DEBUG] client_info: {client_info}, client_type: {client_info.client_type if client_info else 'unknown'}")

    # 2단계: 클라이언트 유형별 메시지 처리 분기
    client_type = client_info.client_type if client_info else 'unknown'
    if client_type == 'MessengerBotR':
        await process_messenger_bot_message(received_message)
    elif client_type == 'Iris':
        await process_iris_message(received_message)
    else:
        await process_generic_message(received_message)


async def process_messenger_bot_message(message: dict):
    data = message.get('data', {}) if 'data' in message else message
    auth = data.get('auth') or message.get('auth') or {}
    def pick(*args):
        for v in args:
            if v is not None:
                return v
        return None
    
    # 로그 설정 확인
    bot_name = pick(data.get('botName'), auth.get('botName'), message.get('bot_name'))
    channel_id = str(pick(data.get('channelId'), auth.get('channelId'), message.get('channel_id'), ''))
    room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
    log_settings = room_config.get("log_settings", {})
    disable_chat_logs = log_settings.get("disable_chat_logs", False)
    
    context = {
        'room': pick(data.get('room'), auth.get('room'), message.get('room')),
        'sender': pick(data.get('sender'), auth.get('sender'), message.get('sender')),
        'text': pick(data.get('text'), auth.get('text'), message.get('text')),
        'is_group_chat': pick(data.get('isGroupChat'), auth.get('isGroupChat'), message.get('is_group_chat')),
        'channel_id': channel_id,
        'log_id': pick(data.get('logId'), auth.get('logId'), message.get('log_id')),
        'user_hash': pick(data.get('userHash'), auth.get('userHash'), message.get('user_hash')),
        'is_mention': pick(data.get('isMention'), auth.get('isMention'), message.get('is_mention')),
        'timestamp': pick(data.get('timestamp'), auth.get('timestamp'), message.get('timestamp')),
        'bot_name': bot_name,
        'client_info': message.get('client_info'),
        'auth': auth,
        'writer': message.get('writer'),  # 메시지를 보낸 클라이언트의 writer 포함
    }
    if not disable_chat_logs:
        logger.debug(f"[DEBUG] context 생성: {context}")
        logger.debug(f"[DEBUG] handle_analyze_event 호출 직전 context: {context} (id={id(context)})")
    await handle_analyze_event(context)

async def process_iris_message(message: dict):
    # Iris 메시지: 필드명 변환 후 handle_analyze_event 호출
    iris_data = message.get('data', {})
    # 예시 변환 (실제 구조에 맞게 조정 필요)
    converted = {
        'room': iris_data.get('chatRoom', ''),
        'sender': iris_data.get('sender', ''),
        'text': iris_data.get('content', ''),
        'channel_id': iris_data.get('roomId', ''),
        'user_hash': iris_data.get('senderId', ''),
        'bot_name': iris_data.get('botName', ''),
        'client_info': message.get('client_info'),
        # 기타 필요한 필드 변환 추가
    }
    await handle_analyze_event(converted)

async def process_generic_message(message: dict):
    logger.debug(f"[DEBUG] process_generic_message 진입, message: {message}")
    data = message.get('data', {})
    logger.debug(f"[DEBUG] process_generic_message에서 handle_analyze_event로 넘기는 data: {data}")
    await handle_analyze_event(data)


async def handle_analyze_event(context: dict):
    #logger.debug(f"[DEBUG] handle_analyze_event 진입 context: {context} (id={id(context)})")
    # user_id 보장: userHash가 있으면 user_id로 복사
    if "user_id" not in context and "userHash" in context:
        context["user_id"] = context["userHash"]

    # 디바이스 승인 상태 체크
    bot_name = context.get('bot_name')
    if bot_name:
        status = await get_bot_device_status(bot_name)
        if status != 'approved':
            logger.error(f"[BOT_DEVICE_DENY] 승인되지 않은 디바이스: bot={bot_name}, status={status}")
            await send_message_response(context, ['@no-reply'])
            return

    bot_name = context.get('bot_name')
    room = context.get('room')
    text = context.get('text')
    sender = context.get('sender')
    client_key = context.get('client_key')
    is_mention = context.get('is_mention', False)
    client_timestamp = context.get('timestamp')
    is_group_chat = context.get('is_group_chat', False)
    channel_id = context.get('channel_id')
    log_id = context.get('log_id')
    user_hash = context.get('user_hash')

    # 여기에 에러 알림 초기화 코드 추가
    if hasattr(g, 'error_notifier_initialized') and not g.error_notifier_initialized:
        try:
            from core.error_notifier import notify_error
            await notify_error(
                f"LOA.i 서버 알림 시스템 준비 완료. 버전: {g.VERSION}",
                "INFO",
                {"startup": "true"}
            )
            g.error_notifier_initialized = True
            logger.info("[ERROR_NOTIFIER] 에러 알림 시스템 초기화 완료")
        except Exception as e:
            logger.error(f"[ERROR_NOTIFIER] 알림 초기화 실패: {e}")

    # 로그 설정 확인 (먼저 확인)
    room_config = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
    log_settings = room_config.get("log_settings", {})
    disable_chat_logs = log_settings.get("disable_chat_logs", False)
    disable_command_logs = log_settings.get("disable_command_logs", False)

    # ✅ 미등록 방 명령어 실행 제한 (새로 추가)
    if not room_config:
        logger.error(f"[UNREGISTERED_ROOM_BLOCKED] 미등록 방에서 명령어 실행 시도: bot={bot_name}, channel_id={channel_id}, sender={sender}, text={text}")
        
        if g.UNREGISTERED_ROOM_MESSAGES:
            # 메시지 목록이 비어있지 않으면 랜덤으로 하나 선택하여 응답
            response_message = random.choice(g.UNREGISTERED_ROOM_MESSAGES)
            await send_message_response(context, [response_message])
        else:
            # 메시지 목록이 비어있으면 @no-reply (기존 동작 유지)
            await send_message_response(context, ['@no-reply'])
        return

    # 설정된 무시 대상 사용자인지 확인
    ignored_user_settings = get_ignored_user_settings(channel_id, user_hash, sender)

    # 사용자가 무시 목록에 있는지 확인
    is_ignored = False
    is_bot = False

    if ignored_user_settings:
        is_ignored = ignored_user_settings.get('no_response', False)
        is_bot = ignored_user_settings.get('is_bot', False)

        # no_logging이 True인 경우 로깅 없이 종료
        if ignored_user_settings.get('no_logging', False):
            return # ✅ no_logging이 True면 모든 로깅 없이 즉시 종료

    # ✅ 디버깅: 로그 설정 확인 (로그 비활성화 시에만 출력)
    if not disable_chat_logs: # 이 부분은 변경 없음: 비활성화 아닐 때만 디버그 정보 출력
        logger.debug(f"[LOG_SETTINGS_DEBUG] bot_name={bot_name}, channel_id={channel_id}")
        logger.debug(f"[LOG_SETTINGS_DEBUG] room_config_exists={bool(room_config)}")
        logger.debug(f"[LOG_SETTINGS_DEBUG] log_settings={log_settings}")
        logger.debug(f"[LOG_SETTINGS_DEBUG] disable_chat_logs={disable_chat_logs}, disable_command_logs={disable_command_logs}")

    # 일반 대화 로그 설정에 따라 로그 출력
    if not disable_chat_logs:
        logger.info(f"[HANDLE_ANALYZE_EVENT] channel_id={channel_id}, sender={sender}, client_key={client_key}")
        # 임시 디버깅: ! 명령어 로그 문제 해결을 위한 메시지 내용 확인
        if text and text.startswith("!"):
            logger.warning(f"[DEBUG_EXCLAMATION] sender='{sender}', text='{text}', room='{context.get('room', 'UNKNOWN')}'")
            logger.warning(f"[DEBUG_EXCLAMATION_DETAIL] text_repr={repr(text)}, text_len={len(text)}")

    # ✅ 템플릿 변수 처리를 위한 컨텍스트 준비
    template_context = {
        "username": sender,
        "room": room,
        "channel_id": channel_id
    }

    # ✅ 템플릿 변수 처리
    if text:
        if "{{DATE_HASH}}" in text:
            template_context["date_hash_modulo"] = 66
        text = await process_template_variables_async(text, template_context)
        context['text'] = text

    if not channel_id or not user_hash:
        if not disable_chat_logs:
            logger.warning(
                f"[SKIP_LOGGING] 필수값 누락 → channel_id={channel_id}, user_hash={user_hash}, room={room}, sender={sender}")
        return

    # 채널 ID와 방 이름 매핑 저장 (필요한 경우)
    g.channel_id_to_room[channel_id] = room

    # 접두어 및 명령어 타입 파싱
    prefix, prompt = parse_prefix(text, bot_name=bot_name)
    
    # 동적 명령어 시스템에서 먼저 prefix 매칭 시도
    dynamic_prefix = None
    dynamic_prompt = None
    
    if bot_name:
        from core.command_manager import command_manager
        if command_manager.is_bot_loaded(bot_name):
            # 방별 오버라이드가 적용된 모든 명령어 가져오기
            all_commands = command_manager.get_all_commands_for_bot(bot_name, channel_id)
            
            # 명령어 목록을 길이순으로 정렬 (긴 것부터)
            sorted_commands = sorted(all_commands.keys(), key=len, reverse=True)
            
            # 각 명령어와 매칭 시도
            for cmd_prefix in sorted_commands:
                if text.strip().startswith(cmd_prefix):
                    dynamic_prefix = cmd_prefix
                    dynamic_prompt = text.strip()[len(cmd_prefix):].strip()
                    logger.debug(f"[DYNAMIC_PREFIX_MATCH] 동적 명령어 매칭: {cmd_prefix}")
                    break
    
    # 동적 명령어에서 매칭된 것이 있으면 우선 사용
    if dynamic_prefix:
        final_prefix = dynamic_prefix
        final_prompt = dynamic_prompt
        logger.warning(f"[DYNAMIC_PREFIX] 동적 명령어 사용: {final_prefix}")
    else:
        final_prefix = prefix
        final_prompt = prompt
        if prefix:
            logger.debug(f"[GLOBAL_PREFIX] 글로벌 명령어 사용: {prefix}")
    
    # prefix가 있는 경우에만 동적 명령어 시스템에서 명령어 정보 가져오기
    command_info = {}
    command_type = None
    
    if final_prefix:
        from core.command_manager import command_manager
        
        # 임시 테스트: "# reload commands" 명령어로 동적 명령어 시스템 리로드
        if final_prefix == "# reload commands":
            logger.warning("[RELOAD] 동적 명령어 시스템 리로드 요청됨")
            try:
                results = await command_manager.load_all_bot_commands()
                await send_message_response(context, f"동적 명령어 시스템 리로드 완료: {results}")
                return
            except Exception as e:
                logger.error(f"[RELOAD] 리로드 실패: {e}")
                await send_message_response(context, f"리로드 실패: {e}")
                return
        
        # 디버그: command_manager 상태 확인
        loaded_bots = command_manager.get_loaded_bots()
        logger.debug(f"[DEBUG] 로드된 봇들: {loaded_bots}")
        logger.debug(f"[DEBUG] '{bot_name}' 봇 로드 여부: {command_manager.is_bot_loaded(bot_name)}")
        
        command_info = command_manager.get_bot_command_info(bot_name, channel_id, final_prefix)
        if not command_info:
            # 봇별 명령어에 없으면 글로벌 fallback 사용
            logger.error(f"[ERROR] 봇별 명령어에 없어 글로벌 fallback 사용. 로드된 봇들: {loaded_bots}, 찾는 명령어: {final_prefix}")
            command_info = PREFIX_MAP.get(final_prefix, {})
        else:
            # 동적 명령어 시스템에서 성공적으로 명령어를 찾았음
            logger.warning(f"[SUCCESS] ✅ 동적 명령어 시스템에서 명령어 발견: {final_prefix} → type: {command_info.get('type')}")
            
        command_type = command_info.get('type')
        
        # 최종 사용된 prefix와 prompt 업데이트
        prefix = final_prefix
        prompt = final_prompt

    try:
        await save_or_update_user(bot_name, channel_id, user_hash, sender, client_key)
    except Exception as e:
        logger.exception(f"[NICKNAME_UPDATE_ERROR] 닉네임 업데이트 실패 → {e}")

    try:
        await save_chat_to_db(
            pool=g.db_pool,
            room_name=room,
            sender=sender,
            message=text,
            bot_name=bot_name,
            is_mention=is_mention,
            client_timestamp=client_timestamp,
            is_group_chat=is_group_chat,
            channel_id=channel_id,
            log_id=log_id,
            user_hash=user_hash,
            disable_chat_logs=disable_chat_logs,
            is_bot=is_bot  # 봇 여부 추가
        )
    except Exception as e:
        logger.exception(f"[DB_SAVE_ERROR] 메시지 저장 실패 → {e}")

    # 사용자가 무시 목록에 있고 no_response가 True인 경우 여기서 종료
    if is_ignored:
        logger.debug(f"[IGNORED_USER] 무시된 사용자 메시지 응답 무시 → channel_id={channel_id}, sender={sender}")
        return

    # 접두어가 없는 일반 메시지 처리
    if not prefix:

        if channel_id in omok_sessions:
            session = omok_sessions[channel_id]
            if not disable_chat_logs: # ✅ 오목 세션 로그 조건부 출력
                logger.info(
                    f"[OMOK_SESSION][DEBUG] channel_id={channel_id}, "
                    f"player1={session.player1}, player2={session.player2}, state={session.state}, "
                    f"swap_rule={getattr(session, 'swap_rule', None)}, swap_stage={getattr(session, 'swap_stage', None)}"
                )
            # 오목 이미지 재전송 명령어 세트
            omok_image_commands = [
                "이미지", "사진", "보드", "오목판", "오목 이미지", "오목 사진",
                "board", "image", "picture",
                "다시", "다시 보내", "다시 보여줘",
                "show", "show board", "show image", "show picture",
                "refresh", "refresh board", "리프레시", "새로고침"
            ]
            if text.strip().lower() in omok_image_commands:
                if not disable_chat_logs: # ✅ 오목 이미지 재전송 로그 조건부 출력
                    logger.info(f"[OMOK_IMAGE_RESEND] 오목 이미지 재전송 명령 감지: {text.strip()} → channel_id={channel_id}")
                await session.send_board_image(context)
                return

            # 모드 선택 입력 감지 ("1" 또는 "2")
            user_id = context.get("userHash") or context.get("user_hash")
            if session.player1["user_id"] == user_id and re.fullmatch(r"[12]", text.strip()):
                if not disable_chat_logs: # ✅ 오목 모드 선택 로그 조건부 출력
                    logger.info(f"[OMOK_MODE] 오목 모드 선택 감지: {text.strip()} → channel_id={channel_id}")
                await handle_mode_selection(text.strip(), context)
                return  # 메시지 전송 후 즉시 return하여 @no-reply 방지

            # 유저 대전 대기 상태에서 참여 명령어 감지
            if session.player2 is None and text.strip().lower() in ["참가", "참여", "join", "Join"]:
                user_id = context.get("userHash") or context.get("user_hash")
                if not disable_chat_logs: # ✅ 오목 참여 로그 조건부 출력
                    logger.info(f"[OMOK_JOIN][DEBUG] state={session.state}, player1={session.player1}, player2={session.player2}, user_id={user_id}, context={context}")
                await handle_join_game(text.strip(), context)
                return  # 반드시 dispatcher로 넘어가지 않도록 보장

            # 오목 세션 중 종료 명령어 감지
            if text.strip().lower() in ["종료", "중지", "Close", "close", "finish", "stop", "quit", "abort"]:
                user_id = context.get("userHash") or context.get("user_hash")
                if not disable_chat_logs: # ✅ 오목 중단 로그 조건부 출력
                    logger.info(f"[OMOK_STOP][DEBUG] state={session.state}, player1={session.player1}, player2={session.player2}, user_id={user_id}, context={context}")
                    logger.info(f"[OMOK_STOP] 오목 게임 중단 감지: {text.strip()} → channel_id={channel_id}")
                await handle_stop_command(text.strip(), context)
                return

            # swap 단계별 입력 감지
            if session.swap_rule in ("swap1", "swap2"):
                # 색 선택 단계
                if session.swap_stage == "choose_color" and text.strip() in ("흑", "백"):
                    if not disable_chat_logs: # ✅ 오목 스왑 색 선택 로그 조건부 출력
                        logger.info(f"[OMOK_SWAP] 색 선택 입력 감지: {text.strip()} → channel_id={channel_id}")
                    await handle_swap_color_choice(text.strip(), context)
                    return
                # swap2: 스왑/추가착수 선택 단계(확장 시)
                # if session.swap_stage == "choose_action" and text.strip() in ("스왑", "추가착수"):
                #     if not disable_chat_logs: # ✅ 오목 스왑 액션 선택 로그 조건부 출력
                #         logger.info(f"[OMOK_SWAP] 스왑/추가착수 선택 입력 감지: {text.strip()} → channel_id={channel_id}")
                #     await handle_swap_action_choice(text.strip(), context)
                #     return

            # 오목 좌표 입력 감지 (H8 또는 8H 형식)
            if re.fullmatch(r"[A-Oa-o][1-9][0-5]?|[1-9][0-5]?[A-Oa-o]", text.strip()):
                user_id = context.get("userHash") or context.get("user_hash")
                if not session.is_omok_player(user_id):
                    # 제3자면 무시 (아무 응답도 하지 않음)
                    return
                if not disable_chat_logs: # ✅ 오목 좌표 입력 로그 조건부 출력
                    logger.info(f"[OMOK_MOVE] 오목 좌표 입력 감지: {text.strip()} → channel_id={channel_id}")
                await handle_omok_command(text.strip(), context)
                return

        # 유튜브 URL이 포함된 메시지 처리 - 먼저 시도
        auto_summary = await process_auto_youtube_summary(context)
        if auto_summary:
            if not disable_chat_logs:
                logger.info(f"[유튜브자동요약] 채널: {channel_id}, 사용자: {sender}")
            await send_message_response(context, auto_summary)
            return

        # 📄 웹페이지 자동 요약 처리
        webpage_summary = await process_auto_webpage_summary(context)
        if webpage_summary:
            if not disable_chat_logs:
                logger.info(f"[웹페이지자동요약] 채널: {channel_id}, 사용자: {sender}")
            await send_message_response(context, webpage_summary)
            return

        # 세션 메시지 처리
        active_session = get_active_session(user_hash, channel_id)
        if not active_session and channel_id:
            active_session = get_active_session(None, channel_id)

        if active_session:
            if not disable_chat_logs:
                logger.info(f"[세션메시지] 세션 내 메시지 감지 → 채널: {channel_id}, 사용자: {sender}")
            session_handled = await process_session_message(context)
            if session_handled:
                return

    # 자동 응답 처리
    did_reply = await handle_auto_reply(context)
    if did_reply:
        return

    # 대화 참여 기능을 위한 메시지 추가
    # 의미 있는 메시지인지 확인 - 실제 판단 로직은 코드에 맞게 구현해야 함
    is_meaningful = True

    # 명령어가 아닌 경우만 대화 참여 대상으로 추가
    if not prefix and is_meaningful:
        add_message_to_history(channel_id, context)

    if not disable_command_logs:
        logger.debug(f"[PARSE_PREFIX] prefix={prefix}, prompt={prompt}")

    final_response_text = "@no-reply"

    # 무응답 카운트 및 ping 트리거
    try:
        g.no_reply_count.setdefault(bot_name, 0)
        g.no_reply_count[bot_name] += 1
        if g.no_reply_count[bot_name] % g.PING_TRIGGER_COUNT == 0:
            await send_ping_event_to_client(context)
    except Exception as e:
        logger.error(f"[PING_MONITOR] ping 트리거 처리 중 오류: {e}")

    # prefix가 없는 경우 명령어 처리하지 않음
    if not prefix:
        return

    try:
        category = command_info.get("category")
        allowed_categories = room_config.get("allowed_categories")
        # 스케줄 발송 여부 확인
        is_scheduled_message = context.get('is_scheduled', False)

        # ✅ 미등록 방의 명령어는 이미 위에서 차단되었으므로 여기서는 category만 검사
        if prefix and allowed_categories is not None and category not in allowed_categories:
            if not disable_command_logs:
                logger.warning(f"[BLOCKED_CATEGORY] 허용되지 않은 카테고리: {category} → {room} / {channel_id}")
            final_response_text = "@no-reply"
        else:
            if command_info.get("admin_only", False) and not is_admin(channel_id, user_hash):
                if not disable_command_logs:
                    logger.warning(f"[BLOCKED_ADMIN_ONLY] 관리자 전용 명령 거절 → {channel_id} / {user_hash}")
                final_response_text = "[ERROR] 관리자 전용 명령입니다."
                await send_message_response(context, final_response_text)
                return
            elif command_info.get("room_owner_only", False) and not is_admin_or_room_owner(channel_id, user_hash):
                if not disable_command_logs:
                    logger.warning(f"[BLOCKED_ROOM_OWNER_ONLY] 방장 전용 명령 거절 → {channel_id} / {user_hash}")
                final_response_text = "[ERROR] 방장 전용 명령입니다."
                await send_message_response(context, final_response_text)
                return

            if command_info.get("prompt_required", False) and not prompt:
                if not disable_command_logs:
                    logger.info(f"[SKIP_PROMPT_REQUIRED] prefix={prefix} → 프롬프트 없음")
                return

            # 명령어 처리 전에 캐시 확인 (대기 메시지 발송 전)
            use_waiting_message = command_info.get('use_waiting_message', False)

            # 캐시 확인 (봇별)
            cached_response = None
            if command_type:
                cached_response = await get_cached_response(command_type, prompt)

            if cached_response:
                logger.info(f"[CACHE_HIT] {command_type} - {prompt[:30]}...")

                # 캐시된 응답이 리스트인 경우, 마지막 메시지 외에 모든 메시지에 [WAITING_MESSAGE] 접두어 추가
                if isinstance(cached_response, list) and len(cached_response) > 1:
                    modified_response = []
                    for i, msg_content in enumerate(cached_response):
                        modified_response.append(msg_content)
                    final_response_text = modified_response
                else:
                    final_response_text = cached_response
            else:
                # 캐시에 없는 경우에만 대기 메시지 발송
                if use_waiting_message and not is_scheduled_message:
                    waiting_message = random.choice(WAITING_MESSAGES)
                    if not disable_command_logs:
                        logger.info(f"대기 메시지 전송 → {waiting_message}")
                    await send_message_response(context, waiting_message)

                # 명령어 처리 - 캐시 확인 건너뛰기 플래그 추가
                response = await process_command({
                    "prefix": prefix,
                    "prompt": prompt,
                    "channel_id": channel_id,
                    "bot_name": bot_name,
                    "writer": context.get('writer'),  # context에서 writer 전달
                    "user_hash": user_hash,
                    "room": room,
                    "sender": sender,
                    "client_key": client_key,
                    "disable_command_logs": disable_command_logs,
                    "skip_cache_check": True,  # 캐시 확인 건너뛰기 플래그
                    "is_scheduled": is_scheduled_message  # 스케줄 발송 여부 전달
                })

                if not response:
                    if not disable_command_logs:
                        logger.warning(
                            f"[NO_RESPONSE] 명령 결과 없음 → bot={bot_name}, channel_id={channel_id}, room={room}, sender={sender}, text='{text}'")
                    final_response_text = "@no-reply"
                elif isinstance(response, str) and response.startswith("[ERROR]"):
                    # 오류 발생 시 로그만 기록하고 @no-reply 반환
                    if not disable_command_logs:
                        logger.error(f"[COMMAND_ERROR] 명령어 오류 → {response}, channel_id={channel_id}")
                        final_response_text = "@no-reply"
                else:
                    final_response_text = response

    except Exception as e:
        logger.exception(f"[ERROR] 명령어 처리 중 오류 발생 → {e}")

        # 에러 알림 추가
        try:
            error_context = {
                "channel_id": channel_id,
                "room": room,
                "sender": sender,
                "prefix": prefix,
                "command_type": command_type
            }
            asyncio.create_task(notify_error(
                f"명령어 처리 중 오류: {str(e)}",
                "ERROR",
                error_context,
                e
            ))
        except Exception as notify_error:
            logger.error(f"[ERROR_NOTIFIER] 에러 알림 전송 실패: {notify_error}")

        # 스케줄 발송인 경우 로그만 남기고 @no-reply 처리
        is_scheduled_message = context.get('is_scheduled', False)
        if is_scheduled_message:
            logger.error(f"[SCHEDULED_ERROR] 스케줄 처리 중 예외 발생 → {e}, channel_id={channel_id}")
            final_response_text = "@no-reply"
        else:
            final_response_text = random.choice(LLM_ERROR_MESSAGES)

    if not disable_command_logs:
        if isinstance(final_response_text, list):
            logger.info(f"[SEND_RESPONSE] 멀티 응답 전송 → channel_id: {channel_id}, 메시지 {len(final_response_text)}개")
        else:
            logger.info(f"[SEND_RESPONSE] 응답 전송 → channel_id: {channel_id}, text: {str(final_response_text)[:50]}...")

    # 리스트 형태의 응답 처리 수정: 리스트를 직접 전달하여 send_message.py에서 처리하도록 함
    await send_message_response(context, final_response_text)

    # ✅ 봇 응답 저장
    try:
        # forbidden message는 send_message_from_context에서 이미 걸렀으므로 다시 검사할 필요 없음
        # final_response_text가 리스트면 마지막 메시지만 저장 대상으로 사용
        if isinstance(final_response_text, list):
            response_text = final_response_text[-1]
        else:
            response_text = final_response_text

        await save_bot_response(
            pool=g.db_pool,
            channel_id=channel_id,
            room_name=room,
            bot_name=bot_name,
            message=response_text,
            directive=prefix if prefix else None,
            message_type=command_info.get('category', 'normal') if prefix else 'normal',
            is_meaningful=1,  # 의미 있는 응답은 1
            is_mention=1 if is_mention else 0,
            is_group_chat=1 if is_group_chat else 0,
            user_hash="(bot)"
        )
    except Exception as e:
        logger.exception(f"[SAVE_BOT_RESPONSE_ERROR] 봇 응답 저장 실패 → {e}")


conversation_block_messages = [
    "😊 잠시 쉬고 올게요! 앞으로 {duration}분간 대화에 참여하지 않을게요.\n여러분의 좋은 대화가 계속되길 바랄게요 🌸",
    "🤖 살짝 휴식 들어갈게요! {duration}분 뒤에 다시 만나요~\n모두 즐거운 이야기 나누세요!",
    "😌 조용히 잠깐 빠질게요. {duration}분 동안은 여러분의 대화에 맡길게요 💬",
    "🛋️ 휴식 모드로 전환~ {duration}분간은 제가 조용히 있을게요. 대화는 계속되길 바랄게요!",
    "☕ 잠깐 커피 타임 갖고 올게요~ {duration}분간 대화 참여는 쉬어갈게요 😊",
    "🙋‍♂️ {duration}분간 쉬는 시간 가질게요. 좋은 이야기로 채워주세요!",
    "🌟 제가 한 템포 쉬어갈게요. {duration}분 후에 다시 함께해요!\n즐거운 시간 보내세요 ✨",
    "📵 봇 모드 일시 정지! {duration}분 동안은 조용히 지켜볼게요 🐾",
    "🍀 잠깐 숨 고르며 쉬어갈게요. {duration}분간 여러분만의 대화를 나눠보세요!",
    "🌈 쉬는 시간이에요~ {duration}분 후에 다시 대화에 참여할게요. 좋은 분위기 이어가요!",
    "😴 조금만 쉬고 올게요. {duration}분 동안은 여러분의 이야기를 응원하고 있을게요!",
    "💤 낮잠 한 번 살짝~ {duration}분 동안 조용히 눈 붙이고 올게요!",
    "🧘‍♀️ 마음의 평화를 위해 {duration}분간 명상하러 갈게요. 대화는 평화롭게~",
    "🍃 바람 좀 쐬고 올게요. {duration}분 동안은 여러분의 이야기를 들으며 쉴게요!",
    "🎵 살짝 음악 듣고 올게요. {duration}분간 대화는 맡길게요!",
    "🌞 햇살 아래서 살짝 쉬다 올게요. {duration}분 후에 다시 인사드릴게요!",
    "💬 대화는 여러분께 맡길게요~ {duration}분간은 한 발 물러나 있을게요!",
    "🕊️ 조용히 날아가서 휴식 중이에요~ {duration}분 뒤에 다시 착륙할게요!",
    "🎈 잠깐 가벼워져볼게요~ {duration}분간 떠 있는 기분으로 쉬고 있을게요!",
    "🦥 살짝 느긋해지려 해요~ {duration}분 동안 천천히 쉬고 올게요!",
    "🎯 여러분의 집중을 위해! {duration}분간은 조용히 있을게요 😊",
    "📚 공부하러 가는 건 아니지만! {duration}분간 살짝 사라집니다~",
    "🍫 초콜릿 하나 먹고 올게요! {duration}분 후 다시 등장할게요 🍬",
    "🌤️ 날씨도 좋고, 잠시 여유를 즐기고 올게요~ {duration}분 동안은 대화를 맡길게요!",
    "🧸 휴식 모드로 들어갑니다~ {duration}분간 여러분의 이야기를 응원할게요!",
    "🧭 잠시 방향을 잡고 올게요~ {duration}분 뒤에 더 나은 봇으로 돌아올게요!"
]


async def handle_conversation_block_command(receivedMessage):
    """
    사용자가 '# 대화참여중지' 명령을 보냈을 때 처리
    """
    try:
        channel_id = receivedMessage["channel_id"]
        bot_name = receivedMessage["bot_name"]

        config = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
        conv_config = config.get("conversation_join", {})

        duration = conv_config.get("block_duration_minutes", 0)
        if duration <= 0:
            logger.info(f"[대화참여중지] 기능 비활성화됨 → {channel_id}")
            return False

        g.conversation_block_until[channel_id] = time.time() + duration * 60
        logger.info(f"[대화참여중지] 차단 적용됨 → {channel_id}, {duration}분간 차단")

        # room_to_writer 대신 send_message_response 사용
        message_template = random.choice(conversation_block_messages)
        message = message_template.format(duration=duration)
        
        context = {
            "bot_name": bot_name,
            "channel_id": channel_id,
            "room": config.get("room_name", "알 수 없는 방")
        }
        
        await send_message_response(context, message)
        return True

    except Exception as e:
        logger.error(f"[대화참여중지] 처리 오류: {str(e)}")
        return False


def is_omok_player(session, sender):
    allowed_ids = [session.player1["user_id"]]
    if session.player2:
        allowed_ids.append(session.player2["user_id"])
    return sender in allowed_ids
