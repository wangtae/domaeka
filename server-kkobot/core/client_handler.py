import asyncio
import json
from core import globals as g
from core.message_processor import process_message
from core.utils.send_message import send_message_response
from core.logger import logger
from core.performance import Timer
from collections import defaultdict  # [추가] 세마포어용
from core.globals import BOT_CONCURRENCY, ROOM_CONCURRENCY, MAX_CONCURRENT_WORKERS, schedule_rooms  # [설정값 import]

# ====== [추가] 메시지 큐 및 워커 관련 전역 변수 ======
message_queue = asyncio.Queue()

# [추가] 봇별 세마포어
bot_semaphores = defaultdict(lambda: asyncio.Semaphore(BOT_CONCURRENCY))
# [변경] 방별 세마포어: channel_id(문자열)로만 관리
room_semaphores = {}
room_semaphore_lock = asyncio.Lock()  # 세마포어 생성/갱신 보호용

def get_room_concurrency(bot_name, channel_id):
    try:
        room_config = schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
        return int(room_config.get("room_concurrency", ROOM_CONCURRENCY))
    except Exception as e:
        logger.error(f"[SEMAPHORE] room_concurrency 조회 오류: {e}")
        return ROOM_CONCURRENCY

async def get_room_semaphore(channel_id, concurrency):
    async with room_semaphore_lock:
        if channel_id not in room_semaphores:
            logger.info(f"[SEMAPHORE] 세마포어 생성: channel_id={channel_id}, concurrency={concurrency}")
            room_semaphores[channel_id] = asyncio.Semaphore(concurrency)
        return room_semaphores[channel_id]

async def process_message_with_limit(received_message):
    bot_name = received_message['bot_name']
    # channelId 또는 channel_id 모두 확인
    channel_id = str(received_message.get('channel_id') or received_message.get('channelId', ''))
    sender = received_message.get('sender')
    text = received_message.get('text')
    if not channel_id or channel_id == 'None':
        logger.error(f"[SEMAPHORE] channel_id 누락: {received_message}")
        return
    
    # 로그 설정 확인
    room_config = schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
    log_settings = room_config.get("log_settings", {})
    disable_chat_logs = log_settings.get("disable_chat_logs", False)
    
    concurrency = get_room_concurrency(bot_name, channel_id)
    room_semaphore = await get_room_semaphore(channel_id, concurrency)
    
    if not disable_chat_logs:
        logger.info(f"[SEMAPHORE] 진입 대기: channel_id={channel_id}, concurrency={concurrency}, sender={sender}, text={text}")
    
    async with room_semaphore:
        if not disable_chat_logs:
            logger.info(f"[SEMAPHORE] 진입: channel_id={channel_id}, concurrency={concurrency}, sender={sender}, text={text}")
        try:
            await process_message(received_message)
        finally:
            if not disable_chat_logs:
                logger.info(f"[SEMAPHORE] 반환: channel_id={channel_id}, sender={sender}, text={text[:30]}...")

async def message_worker():
    while True:
        received_message = await message_queue.get()
        display_key = received_message.get("channel_id") or received_message.get("room") or "unknown"
        with Timer(f"process_message for {received_message.get('bot_name')}/{display_key}"):
            try:
                await process_message_with_limit(received_message)
            except Exception as e:
                logger.error(f"[ERROR] 메시지 처리 실패 → {e}", exc_info=True)
                await send_message_response(received_message, "@no-reply")
            finally:
                message_queue.task_done()
# ====== [끝] ======

async def handle_client(reader, writer):
    addr = writer.get_extra_info('peername')
    logger.info(f"[CONNECT] 클라이언트 연결됨 → {addr}\n\n\n")

    bot_name = None
    bot_version = None

    try:
        # ✅ 강화된 핸드셰이크 수신 및 디바이스 등록
        handshake = await reader.readline()
        handshake_data = json.loads(handshake.decode().strip())
        
        # 필수 필드 확인 (구 버전 호환성 지원)
        bot_name = handshake_data.get('botName', '')
        bot_version = handshake_data.get('version', '')
        device_id = handshake_data.get('deviceID', '')
        
        # v3.2.0 이상의 확장 필드 (선택 사항)
        client_type = handshake_data.get('clientType', 'MessengerBotR')  # 기본값 설정
        device_ip = handshake_data.get('deviceIP', str(addr).split(':')[0] if ':' in str(addr) else 'unknown')
        device_info = handshake_data.get('deviceInfo', '')
        
        # 핵심 필드 검증 (구 버전 호환)
        required_fields = ['botName', 'version', 'deviceID']
        for field in required_fields:
            if not handshake_data.get(field):
                logger.error(f"[HANDSHAKE] {field} 필드 누락: {addr}")
                writer.close()
                await writer.wait_closed()
                return
        
        logger.info(f"[HANDSHAKE] 수신: {addr} - {client_type} {bot_name} v{bot_version}")
        logger.info(f"[HANDSHAKE] Device: {device_id}, IP: {device_ip}, Info: {device_info}")
        
        # kb_bot_devices 테이블과 연동하여 승인 상태 확인
        from database.device_manager import validate_and_register_device
        is_approved, status_message = await validate_and_register_device(handshake_data, str(addr))
        
        if not is_approved:
            logger.warning(f"[HANDSHAKE] 승인되지 않은 디바이스: {addr} - {status_message}")
            # 승인되지 않았어도 연결은 허용하되, 제한 모드로 동작
        
        logger.info(f"[HANDSHAKE] 성공: {addr} - {bot_name} v{bot_version} (상태: {'approved' if is_approved else 'pending'})")

        if not bot_name:
            logger.error(f"[ERROR] 핸드셰이크 실패 → {addr}")
            writer.close()
            await writer.wait_closed()
            return

        g.clients.setdefault(bot_name, {})[addr] = writer
        # g.room_to_writer.setdefault(bot_name, {})  # 레거시 코드 제거

        logger.info(f"[HANDSHAKE COMPLETE] 봇 이름 등록 완료 → {bot_name}")

        # 🆕 봇 연결 시 자동으로 해당 봇의 설정 파일 생성 (새로운 KB 테이블 사용)
        try:
            from services.config_generator_service import generate_bot_settings_from_new_db
            success, message = await generate_bot_settings_from_new_db(bot_name)
            if success:
                logger.warning(f"[BOT_SETTINGS] '{bot_name}' 봇 설정 파일 생성 완료: {message}")
            else:
                logger.error(f"[BOT_SETTINGS] '{bot_name}' 봇 설정 파일 생성 실패: {message}")
        except Exception as e:
            logger.error(f"[BOT_SETTINGS] '{bot_name}' 봇 설정 파일 생성 중 오류: {e}")

        # 🆕 봇 연결 시 자동으로 해당 봇의 명령어 파일 생성 (새로운 KB 테이블 사용)
        try:
            from services.config_generator_service import generate_bot_commands_from_new_db
            success, message = await generate_bot_commands_from_new_db(bot_name)
            if success:
                logger.warning(f"[BOT_COMMANDS] '{bot_name}' 봇 명령어 파일 생성 완료: {message}")
            else:
                logger.error(f"[BOT_COMMANDS] '{bot_name}' 봇 명령어 파일 생성 실패: {message}")
        except Exception as e:
            logger.error(f"[BOT_COMMANDS] '{bot_name}' 봇 명령어 파일 생성 중 오류: {e}")

        # 최초 연결 시점에 디바이스 등록/갱신
        try:
            from services.user_service import save_or_update_bot_device
            # addr: (ip, port) 튜플
            ip_address = addr[0] if isinstance(addr, tuple) else str(addr)
            await save_or_update_bot_device(
                bot_name=bot_name,
                ip_address=ip_address,
                client_type="MessengerBotR",  # 또는 handshake에서 받는 client_type
                client_version=bot_version or "unknown",
                status_hint=None
            )
        except Exception as e:
            logger.error(f"[BOT_DEVICE_REGISTER] 최초 등록/갱신 실패: {e}")

        while True:
            data = await reader.readline()
            if not data:
                logger.info(f"[DISCONNECT] 연결 끊김 → {addr}")
                break

            message = data.decode('utf-8').strip() 

            try:
                packet = json.loads(message)
                data_field = packet.get('data', {})

                # ✅ 로그 설정 확인 (handle_client 내부에서)
                current_channel_id = str(data_field.get('channelId') or data_field.get('channel_id', ''))
                room_config = g.schedule_rooms.get(bot_name, {}).get(current_channel_id, {})
                log_settings = room_config.get("log_settings", {})
                disable_chat_logs = log_settings.get("disable_chat_logs", False)
                
                if not disable_chat_logs:
                    logger.debug(f"[RECEIVED] {addr} -> {message}")

                received_message = dict(data_field)  # data_field의 모든 필드 복사
                received_message.update({
                    "bot_name": bot_name,
                    "bot_version": bot_version,
                    "writer": writer,
                    "server_status": None
                })

                # ping 이벤트 처리
                if packet.get('event') == 'ping':
                    from core.utils.ping_monitor import save_ping_result
                    await save_ping_result(data_field)
                    continue  # ping 패킷은 일반 메시지 처리로 넘기지 않음

                # 기존: await process_message(received_message)
                # 변경: 큐에 넣기
                await message_queue.put(received_message)

            except json.JSONDecodeError:
                logger.error(f"[ERROR] JSON 파싱 실패 → {addr} → {message}")

    except Exception as e:
        logger.exception(f"[ERROR] 클라이언트 처리 중 오류 발생 → {e}")

    finally:
        # 연결 종료 시 정리
        if bot_name:
            if addr in g.clients.get(bot_name, {}):
                del g.clients[bot_name][addr]
            logger.info(f"[CLEANUP] 클라이언트 정리 완료 → {bot_name} / {addr}")

        try:
            writer.close()
            await writer.wait_closed()
        except Exception as e:
            logger.error(f"[ERROR] writer 종료 중 오류 발생 → {e}")

        logger.info(f"[DISCONNECT] 연결 종료됨 → {addr}")

# ====== [서버 시작 시 워커 태스크 여러 개 실행] ======
def start_message_workers():
    for _ in range(MAX_CONCURRENT_WORKERS):
        asyncio.create_task(message_worker())
# main 함수 등에서 start_message_workers()를 호출해 주세요.
# ====== [끝] ======

async def disconnect_client(bot_name, addr, writer):
    logger.info(f"[DISCONNECT] 클라이언트 종료 처리 시작 → {bot_name}, {addr}")

    if bot_name:
        # room_to_writer 레거시 코드 제거
        # room_to_writer = g.room_to_writer.get(bot_name, {})
        # channels_to_remove = [cid for cid, w in room_to_writer.items() if w == writer]
        # 
        # for cid in channels_to_remove:
        #     del room_to_writer[cid]
        #     logger.debug(f"[DISCONNECT] room_to_writer 제거 → {bot_name} / {cid}")

        # g.clients에서만 제거
        g.clients.get(bot_name, {}).pop(addr, None)
        logger.debug(f"[DISCONNECT] g.clients에서 제거 → {bot_name} / {addr}")

    # ✅ writer 종료 처리
    try:
        if not writer.is_closing():
            writer.close()
            await writer.wait_closed()
            logger.info(f"[DISCONNECT] writer 정상 종료 → {bot_name} / {addr}")
    except Exception as e:
        logger.error(f"[DISCONNECT] writer 종료 실패 → {e}", exc_info=True)

    logger.info(f"[DISCONNECT] 클라이언트 종료 처리 완료 → {bot_name}, {addr}")
