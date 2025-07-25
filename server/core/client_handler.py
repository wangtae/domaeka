"""
클라이언트 연결 처리 모듈 - v3.3.0 프로토콜 지원
"""
import asyncio
import json
import base64
import random
from typing import Dict, Any
from core.logger import logger
from core.message_processor import process_message
from core.client_status import client_status_manager
from core.timeout_handler import TimeoutRetryHandler, ConnectionHealthChecker
import core.globals as g

# 클라이언트별 ping 태스크 관리
client_ping_tasks: Dict[tuple, asyncio.Task] = {}  # {(bot_name, device_id): asyncio.Task}


async def client_ping_task(bot_name: str, device_id: str, writer):
    """
    클라이언트별 독립 ping 태스크
    """
    logger.info(f"[PING_TASK] ping 태스크 시작 → {bot_name}@{device_id}")
    
    try:
        from core.response_utils import send_ping_event_to_client
        from datetime import datetime
        import pytz
    except ImportError as e:
        logger.error(f"[PING_TASK] import 오류 → {bot_name}@{device_id}: {e}")
        return
    
    # 초기 지연 (1-59초 랜덤) - 서버 재시작 시 ping 부하 분산
    initial_delay = random.uniform(1, 59)
    await asyncio.sleep(initial_delay)
    logger.info(f"[PING_TASK] 초기 지연 {initial_delay:.1f}초 후 시작 → {bot_name}@{device_id}")
    
    try:
        while not g.shutdown_event.is_set():
            try:
                # writer가 여전히 유효한지 확인
                if writer.is_closing():
                    logger.info(f"[PING_TASK] 연결이 닫혀있어 ping 태스크 종료 → {bot_name}@{device_id}")
                    break
                    
                # ping 전송을 위한 context 생성
                context = {
                    'bot_name': bot_name,
                    'channel_id': 'ping',
                    'room': 'PING',
                    'user_hash': 'system',
                    'writer': writer,  # 특정 writer를 명시적으로 전달
                    'server_timestamp': datetime.now(pytz.timezone('Asia/Seoul')).strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
                }
                
                try:
                    success = await send_ping_event_to_client(context)
                    if success:
                        logger.debug(f"[PING_TASK] ping 전송 성공 → {bot_name}@{device_id}")
                    else:
                        logger.warning(f"[PING_TASK] ping 전송 실패 → {bot_name}@{device_id}")
                except Exception as e:
                    logger.error(f"[PING_TASK] ping 전송 중 오류 → {bot_name}@{device_id}: {e}")
                    
                # 지정된 간격만큼 대기
                await asyncio.sleep(g.PING_INTERVAL_SECONDS)
                
            except Exception as e:
                logger.error(f"[PING_TASK] ping 루프 내부 오류 → {bot_name}@{device_id}: {e}")
                await asyncio.sleep(5)  # 오류 시 재시도 전 대기
                    
    except asyncio.CancelledError:
        logger.info(f"[PING_TASK] ping 태스크 취소됨 → {bot_name}@{device_id}")
        raise
    except Exception as e:
        logger.error(f"[PING_TASK] ping 태스크 오류 → {bot_name}@{device_id}: {e}")
    finally:
        logger.info(f"[PING_TASK] ping 태스크 종료 → {bot_name}@{device_id}")


async def read_limited_line(reader: asyncio.StreamReader, max_size: int = None):
    """
    크기 제한이 있는 readline 구현
    
    Args:
        reader: 스트림 리더
        max_size: 최대 읽기 크기 (기본값: MAX_MESSAGE_SIZE)
    
    Returns:
        bytes: 읽은 데이터 (None이면 연결 종료)
        
    Raises:
        ValueError: 메시지 크기가 제한을 초과한 경우
    """
    if max_size is None:
        max_size = g.MAX_MESSAGE_SIZE
    
    try:
        # EOF 체크
        if reader.at_eof():
            return None
            
        # readuntil을 사용하여 줄바꿈까지 읽기
        # limit 매개변수로 최대 크기 제한
        data = await reader.readuntil(b'\n')
        
        # 크기 체크
        if len(data) > max_size:
            raise ValueError(f"메시지 크기가 {max_size} 바이트를 초과했습니다: {len(data)} 바이트")
            
        return data
        
    except asyncio.LimitOverrunError as e:
        # StreamReader의 내부 버퍼 제한 초과
        raise ValueError(f"메시지 크기가 스트림 버퍼 제한을 초과했습니다: {e}")
    except asyncio.IncompleteReadError as e:
        # 연결이 종료되어 데이터가 불완전한 경우
        if e.partial:
            return e.partial
        return None
    except asyncio.CancelledError:
        raise
    except (ConnectionResetError, BrokenPipeError, OSError) as e:
        logger.debug(f"[READ] 연결 종료: {e}")
        return None
    except asyncio.TimeoutError:
        # TimeoutError는 상위로 전파
        raise
    except Exception as e:
        logger.error(f"[READ] 데이터 읽기 오류: {e}")
        # 알 수 없는 오류는 None 반환하여 연결 종료
        return None


def parse_v330_message(raw_msg: str) -> tuple:
    """
    v3.3.0 프로토콜 메시지 파싱 (JSON + Raw 데이터)
    
    Args:
        raw_msg: 원본 메시지 문자열
        
    Returns:
        tuple: (json_message, raw_content) 또는 (json_message, None) for 레거시
    """
    try:
        # JSON 끝 위치 찾기
        json_end_index = raw_msg.rfind('}')
        
        if json_end_index == -1:
            # JSON 형태가 아님
            return None, None
        
        # JSON 부분 추출
        json_part = raw_msg[:json_end_index + 1]
        json_message = json.loads(json_part)
        
        # v3.3.0 프로토콜 이벤트 확인
        event = json_message.get('event', '')
        data = json_message.get('data', {})
        new_protocol_events = ["message", "scheduleMessage", "broadcastMessage"]
        
        if event in new_protocol_events and 'message_positions' in data:
            # v3.3.0: Raw 데이터 추출
            raw_content = raw_msg[json_end_index + 1:]
            if raw_content.endswith('\n'):
                raw_content = raw_content[:-1]
            return json_message, raw_content
        else:
            # 레거시 프로토콜 (ping, handshake 등)
            return json_message, None
            
    except json.JSONDecodeError:
        logger.error(f"[PARSE] JSON 파싱 실패")
        return None, None
    except Exception as e:
        logger.error(f"[PARSE] 메시지 파싱 오류: {e}")
        return None, None


async def ping_client_periodically(writer: asyncio.StreamWriter, client_addr, bot_name: str):
    """
    클라이언트에게 주기적으로 ping 전송 - v3.3.0 프로토콜 지원
    
    Args:
        writer: 스트림 라이터
        client_addr: 클라이언트 주소
        bot_name: 봇 이름
    """
    import random
    # 클라이언트별로 분산시키기 위해 0~5초 랜덤 대기
    initial_delay = random.uniform(0, 5)
    await asyncio.sleep(initial_delay)
    
    while not g.shutdown_event.is_set():
        try:
            if writer.is_closing():
                logger.debug(f"[PING] Writer가 닫혀있음, ping 중단: {client_addr}")
                break
                
            # v3.3.0: ping 메시지 전송 (JSON+Raw 구조)
            from core.response_utils import send_json_response
            ping_message = {
                'event': 'ping',
                'data': {
                    'bot_name': bot_name,
                    'server_timestamp': int(asyncio.get_event_loop().time() * 1000)
                }
            }
            # send_json_response가 자동으로 v3.3.0 필드들을 추가함
            await send_json_response(writer, ping_message)
            logger.debug(f"[PING] v3.3.0 전송 완료: {client_addr}")
            
            # 다음 ping까지 대기
            await asyncio.sleep(g.PING_INTERVAL_SECONDS)
            
        except Exception as e:
            logger.error(f"[PING] 전송 실패: {client_addr} - {e}")
            break


async def handle_client(reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
    """
    클라이언트 연결 처리 - v3.3.0 프로토콜 지원
    
    Args:
        reader: 스트림 리더
        writer: 스트림 라이터
    """
    import time
    connection_start_time = time.time()  # 연결 시작 시간 기록
    
    client_addr = writer.get_extra_info('peername')
    
    # 전체 연결 수 제한 적용
    try:
        await g.connection_semaphore.acquire()
        logger.info(f"[CLIENT] 새 클라이언트 연결: {client_addr} (활성 연결: {g.MAX_CONCURRENT_CONNECTIONS - g.connection_semaphore._value}/{g.MAX_CONCURRENT_CONNECTIONS})")
    except Exception as e:
        logger.error(f"[CLIENT] 연결 수 제한 초과: {client_addr} - {e}")
        writer.close()
        await writer.wait_closed()
        return
    
    # 핸드셰이크 처리
    handshake_completed = False
    bot_name = None
    device_id = None
    client_key = None  # (bot_name, device_id) 튜플
    
    try:
        # 핸드셰이크 타임아웃 처리 (10초)
        if not handshake_completed:
            try:
                async with asyncio.timeout(10):
                    try:
                        data = await read_limited_line(reader, g.MAX_MESSAGE_SIZE)
                        if not data:
                            logger.error(f"[HANDSHAKE] 핸드셰이크 데이터 없음: {client_addr}")
                            writer.close()
                            await writer.wait_closed()
                            return
                        
                        # 핸드셰이크 처리
                        message = data.decode('utf-8').strip()
                        logger.debug(f"[RECV] {client_addr}: {message}")
                    except ValueError as e:
                        logger.error(f"[HANDSHAKE] 메시지 크기 초과: {client_addr} - {e}")
                        writer.close()
                        await writer.wait_closed()
                        return
                    
                    handshake_result = await handle_handshake(message, client_addr, writer)
                    if isinstance(handshake_result, tuple) and len(handshake_result) >= 4:
                        handshake_completed, bot_name, device_id, max_message_size = handshake_result[:4]
                        if handshake_completed:
                            # 클라이언트 등록
                            client_key = (bot_name, device_id)
                            
                            # 기존 연결이 있으면 종료
                            if client_key in g.clients:
                                old_writer = g.clients[client_key]
                                if old_writer and not old_writer.is_closing():
                                    logger.warning(f"[CLIENT] 기존 연결 종료: {client_key}")
                                    old_writer.close()
                                    await old_writer.wait_closed()
                            
                            # 새 연결 등록
                            g.clients[client_key] = writer
                            g.clients_by_addr[client_addr] = client_key
                            g.client_max_message_sizes[client_key] = max_message_size
                            # 승인 상태도 캐싱
                            is_approved = handshake_result[4] if len(handshake_result) > 4 else True
                            g.client_approval_status[client_key] = is_approved
                            logger.info(f"[CLIENT] 클라이언트 등록: {client_key} from {client_addr} (메시지 크기 제한: {max_message_size/1024/1024:.1f}MB, 승인: {is_approved})")
                            
                            # 클라이언트별 ping 태스크 시작
                            ping_task_key = (bot_name, device_id)
                            ping_task = asyncio.create_task(client_ping_task(bot_name, device_id, writer))
                            client_ping_tasks[ping_task_key] = ping_task
                            logger.info(f"[PING_TASK] 클라이언트 ping 태스크 시작됨 → {client_key}")
                        else:
                            logger.error(f"[HANDSHAKE] 핸드셰이크 실패: {client_addr}")
                            writer.close()
                            await writer.wait_closed()
                            return
                    else:
                        logger.error(f"[HANDSHAKE] 핸드셰이크 실패: {client_addr}")
                        writer.close()
                        await writer.wait_closed()
                        return
                        
            except asyncio.TimeoutError:
                logger.error(f"[HANDSHAKE] 핸드셰이크 타임아웃: {client_addr}")
                writer.close()
                await writer.wait_closed()
                return
        
        # 일반 메시지 처리 루프
        timeout_handler = TimeoutRetryHandler(max_retries=3, initial_delay=0.5, max_delay=5.0)
        health_checker = ConnectionHealthChecker()
        
        while not g.shutdown_event.is_set():
            # 연결 상태 확인
            if not await health_checker.is_healthy(reader, writer):
                logger.warning(f"[CLIENT] 연결 상태 불량: {client_addr}")
                break
            
            # 클라이언트로부터 메시지 수신 (10분 타임아웃 - ping 주기 30초를 고려한 충분한 시간)
            try:
                async with asyncio.timeout(600):
                    try:
                        client_max_size = g.client_max_message_sizes.get(client_key, g.MAX_MESSAGE_SIZE) if client_key else g.MAX_MESSAGE_SIZE
                        data = await read_limited_line(reader, client_max_size)
                        # 데이터를 성공적으로 읽었으면 핸들러 리셋
                        timeout_handler.reset()
                    except ValueError as e:
                        logger.error(f"[CLIENT] 메시지 크기 초과: {client_addr} - {e}")
                        # 크기 초과 시 연결 종료
                        break
            except asyncio.TimeoutError:
                if not timeout_handler.on_timeout():
                    timeout_handler.log_timeout(f"클라이언트 {client_addr}")
                    logger.error(f"[TIMEOUT] 최대 타임아웃 횟수 초과, 연결 종료: {client_addr}")
                    break
                
                timeout_handler.log_timeout(f"클라이언트 {client_addr}")
                delay = timeout_handler.get_delay()
                await asyncio.sleep(delay)
                continue
                
            if not data:
                # EOF 발생 시 상세 정보 로깅
                import time
                current_time = time.time()
                connection_duration = current_time - connection_start_time if 'connection_start_time' in locals() else 0
                
                # 마지막 ping 시간 확인
                last_ping_info = ""
                if client_addr:
                    client_info = client_status_manager.get_client_info(client_addr)
                    if client_info and 'last_ping_time' in client_info:
                        last_ping_ago = current_time - client_info['last_ping_time']
                        last_ping_info = f", 마지막 ping: {last_ping_ago:.1f}초 전"
                
                logger.warning(f"[CLIENT] 클라이언트 연결 종료 (EOF): {client_addr}, "
                             f"봇: {bot_name}@{device_id}, "
                             f"연결 지속시간: {connection_duration:.1f}초{last_ping_info}")
                break
            
            # 메시지 파싱 및 처리
            try:
                message = data.decode('utf-8').strip()
                
                # 클라이언트별 메시지 크기 체크 (메모리 공격 방지)
                client_max_size = g.client_max_message_sizes.get(client_key, g.MAX_MESSAGE_SIZE) if client_key else g.MAX_MESSAGE_SIZE
                if len(message) > client_max_size:
                    logger.error(f"[CLIENT] 디코딩된 메시지 크기 초과: {client_addr} - {len(message)} 바이트 (제한: {client_max_size} 바이트)")
                    break
                
                # v3.3.0 프로토콜 파싱
                json_message, raw_content = parse_v330_message(message)
                
                if json_message is None:
                    logger.error(f"[CLIENT] 메시지 파싱 실패: {client_addr}")
                    continue
                
                # ping 메시지는 LOG_CONFIG에 따라 로그 출력 제어
                event = json_message.get('event', '')
                if event == 'ping':
                    ping_config = g.LOG_CONFIG.get('ping', {})
                    if ping_config.get('enabled', True) and ping_config.get('detailed', False):
                        logger.debug(f"[RECV] {client_addr}: {message}")
                else:
                    logger.debug(f"[RECV] {client_addr}: {message[:200]}..." if len(message) > 200 else f"[RECV] {client_addr}: {message}")
                
                # v3.3.0: message 이벤트 처리 (Base64 디코딩)
                if event == 'message' and raw_content is not None:
                    msg_data = json_message.get('data', {})
                    
                    # Base64 디코딩
                    if msg_data.get('content_encoding') == 'base64':
                        try:
                            decoded_content = base64.b64decode(raw_content).decode('utf-8')
                            # 디코딩된 내용을 data에 설정
                            msg_data['text'] = decoded_content
                            logger.debug(f"[CLIENT] Base64 디코딩 완료: {len(raw_content)} -> {len(decoded_content)} chars")
                        except Exception as e:
                            logger.error(f"[CLIENT] Base64 디코딩 실패: {e}")
                            msg_data['text'] = raw_content  # 실패 시 원본 사용
                    else:
                        msg_data['text'] = raw_content
                    
                    # 기존 analyze 호환 필드 매핑
                    compat_data = {
                        'room': msg_data.get('room'),
                        'text': msg_data.get('text'),
                        'sender': msg_data.get('sender'),
                        'isGroupChat': msg_data.get('is_group_chat'),
                        'channelId': msg_data.get('channel_id'),
                        'logId': msg_data.get('log_id'),
                        'userHash': msg_data.get('user_hash'),
                        'isMention': msg_data.get('is_mention'),
                        'timestamp': msg_data.get('timestamp'),
                        'botName': msg_data.get('bot_name'),
                        'clientType': msg_data.get('client_type'),
                        'auth': msg_data.get('auth')
                    }
                    
                    # analyze 이벤트로 변환하여 기존 로직 재사용
                    json_message['event'] = 'analyze'
                    json_message['data'] = compat_data
                
                # 카카오톡 메시지 내용 길이 체크
                if json_message.get('event') == 'analyze':
                    msg_data = json_message.get('data', {})
                    msg_text = msg_data.get('text', '')
                    if len(msg_text) > g.MAX_KAKAOTALK_MESSAGE_LENGTH:
                        logger.warning(f"[CLIENT] 카카오톡 메시지 길이 초과: {client_addr} - {len(msg_text)} 글자")
                        # 길이 초과 메시지는 처리하지 않고 무시
                        continue
                
                # 순환 참조 방지: writer 객체 대신 필요한 정보만 전달
                json_message['bot_name'] = bot_name
                json_message['device_id'] = device_id
                json_message['client_key'] = (bot_name, device_id)
                json_message['client_addr'] = client_addr
                
                # ping 이벤트 처리
                if json_message.get('event') == 'ping':
                    from database.ping_monitor import save_ping_result
                    ping_data = json_message.get('data', {})
                    # bot_name과 device_id 정보 추가
                    ping_data['bot_name'] = bot_name
                    ping_data['device_id'] = device_id
                    
                    # 서버 프로세스 모니터링 정보 추가
                    if hasattr(g, 'process_monitor') and g.process_monitor:
                        monitor_stats = g.process_monitor.get_current_stats()
                        ping_data['process_name'] = monitor_stats.get('process_name')
                        ping_data['server_cpu_usage'] = monitor_stats.get('server_cpu_usage', 0.0)
                        ping_data['server_cpu_max'] = monitor_stats.get('server_cpu_max', 0.0)
                        ping_data['server_memory_usage'] = monitor_stats.get('server_memory_usage', 0.0)
                        ping_data['server_memory_max'] = monitor_stats.get('server_memory_max', 0.0)
                        logger.debug(f"[PING] 서버 모니터링 정보 추가 - CPU: {ping_data['server_cpu_usage']}%, MEM: {ping_data['server_memory_usage']}MB")
                    
                    await save_ping_result(ping_data)
                    continue  # ping 패킷은 일반 메시지 처리로 넘기지 않음
                
                # 메시지를 큐에 추가 (워커가 처리)
                await g.message_queue.put(json_message)
                logger.debug(f"[CLIENT] 메시지를 큐에 추가: {client_addr}") 
                
            except json.JSONDecodeError as e:
                logger.error(f"[CLIENT] JSON 파싱 실패: {e}")
            except Exception as e:
                logger.error(f"[CLIENT] 메시지 처리 실패: {e}")
                
    except asyncio.CancelledError:
        logger.info(f"[CLIENT] 연결 취소됨: {client_addr}")
    except Exception as e:
        logger.error(f"[CLIENT] 연결 오류: {client_addr} -> {e}")
    finally:
        # 연결 종료 시 상세 정보 로깅
        import time
        current_time = time.time()
        connection_duration = current_time - connection_start_time
        
        # ping 태스크 정리
        if bot_name and device_id:
            ping_task_key = (bot_name, device_id)
            if ping_task_key in client_ping_tasks:
                ping_task = client_ping_tasks[ping_task_key]
                if not ping_task.done():
                    ping_task.cancel()
                    try:
                        await ping_task
                    except asyncio.CancelledError:
                        pass
                del client_ping_tasks[ping_task_key]
                logger.info(f"[PING_TASK] ping 태스크 정리 완료 → {bot_name}@{device_id}")
        
        # 클라이언트 정리
        if client_addr in g.clients_by_addr:
            client_key = g.clients_by_addr[client_addr]
            if client_key in g.clients:
                del g.clients[client_key]
            if client_key in g.client_max_message_sizes:
                del g.client_max_message_sizes[client_key]
            if client_key in g.client_approval_status:
                del g.client_approval_status[client_key]
            del g.clients_by_addr[client_addr]
            logger.info(f"[CLIENT] 클라이언트 제거: {client_key}")
        
        client_status_manager.remove_client(str(client_addr))
        writer.close()
        await writer.wait_closed()
        
        # 연결 수 제한 세마포어 해제
        g.connection_semaphore.release()
        logger.info(f"[CLIENT] 클라이언트 연결 해제: {client_addr}, "
                   f"봇: {bot_name}@{device_id}, "
                   f"연결 지속시간: {connection_duration:.1f}초, "
                   f"활성 연결: {g.MAX_CONCURRENT_CONNECTIONS - g.connection_semaphore._value - 1}/{g.MAX_CONCURRENT_CONNECTIONS}")


async def handle_handshake(message: str, client_addr, writer):
    """
    클라이언트 핸드셰이크 처리 및 kb_bot_devices 테이블 연동 - v3.3.0 프로토콜 지원
    
    Args:
        message: 핸드셰이크 메시지
        client_addr: 클라이언트 주소
        writer: 스트림 라이터
        
    Returns:
        tuple: (핸드셰이크 성공 여부, bot_name, device_id, max_message_size) 또는 bool
    """
    try:
        # 핸드셰이크 메시지는 JSON 형태여야 함
        handshake_message = json.loads(message)
        
        # v3.3.0 프로토콜 확인
        if handshake_message.get('event') == 'handshake' and 'data' in handshake_message:
            # v3.3.0: data 객체에서 필드 추출
            handshake_data = handshake_message.get('data', {})
            logger.debug(f"[HANDSHAKE] v3.3.0 프로토콜 감지: {client_addr}")
        else:
            # 레거시: 최상위 레벨에서 필드 추출
            handshake_data = handshake_message
            logger.debug(f"[HANDSHAKE] 레거시 프로토콜: {client_addr}")
        
        # 필수 필드 확인 (구 버전 호환성 지원)
        bot_name = handshake_data.get('botName', '')
        version = handshake_data.get('version', '')
        device_id = handshake_data.get('deviceID', '')
        
        # v3.2.0 이상의 확장 필드 (선택 사항)
        client_type = handshake_data.get('clientType', 'MessengerBotR')  # 기본값 설정
        device_ip = handshake_data.get('deviceIP', str(client_addr).split(':')[0] if ':' in str(client_addr) else 'unknown')
        device_info = handshake_data.get('deviceInfo', '')
        
        # 핵심 필드 검증
        required_fields = ['botName', 'version', 'deviceID']
        missing_fields = []
        for field in required_fields:
            if not handshake_data.get(field):
                missing_fields.append(field)
        
        if missing_fields:
            logger.error(f"[HANDSHAKE] 필수 필드 누락: {client_addr} - {missing_fields}")
            logger.debug(f"[HANDSHAKE] 수신된 데이터: {handshake_data}")
            return False
        
        logger.info(f"[HANDSHAKE] 수신: {client_addr} - {client_type} {bot_name} v{version}")
        logger.info(f"[HANDSHAKE] Device: {device_id}, IP: {device_ip}, Info: {device_info}")
        
        # kb_bot_devices 테이블과 연동하여 승인 상태 확인
        from database.device_manager import validate_and_register_device
        is_approved, status_message, max_message_size = await validate_and_register_device(handshake_data, str(client_addr))
        
        if not is_approved:
            logger.warning(f"[HANDSHAKE] 승인되지 않은 디바이스: {client_addr} - {status_message}")
            # 승인되지 않았어도 연결은 허용하되, 제한 모드로 동작
        
        # 클라이언트 상태 관리자에 등록 (승인 상태 포함)
        handshake_data['approval_status'] = 'approved' if is_approved else 'pending'
        handshake_data['status_message'] = status_message
        client_info = client_status_manager.register_client(str(client_addr), handshake_data)
        
        logger.info(f"[HANDSHAKE] 성공: {client_addr} - {bot_name} v{version} (상태: {handshake_data['approval_status']}, 메시지 크기 제한: {max_message_size/1024/1024:.1f}MB)")
        
        # v3.3.0: 핸드셰이크 성공 응답 전송 (JSON+Raw 구조)
        from core.response_utils import send_json_response
        handshake_response = {
            'event': 'handshakeComplete',
            'data': {
                'success': True,
                'approved': is_approved,
                'message': status_message,
                'server_version': '1.0.0'
            }
        }
        # send_json_response가 자동으로 v3.3.0 필드들을 추가함
        await send_json_response(writer, handshake_response)
        logger.info(f"[HANDSHAKE] v3.3.0 응답 전송 완료: {client_addr}")
        
        return True, bot_name, device_id, max_message_size, is_approved
        
    except json.JSONDecodeError:
        logger.error(f"[HANDSHAKE] JSON 파싱 실패: {client_addr}")
        return False
    except Exception as e:
        logger.error(f"[HANDSHAKE] 처리 오류: {client_addr} -> {e}")
        return False