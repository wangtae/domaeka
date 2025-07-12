# auth_utils.py 수정
import core.globals as g  # g 변수 직접 임포트 대신 모듈 임포트
from core.logger import logger
import hmac
import hashlib
import json
import time
from typing import Dict, Optional, Tuple
from dataclasses import dataclass
from core.globals import AUTH_CONFIG_PATH
import os
import asyncio
from services.user_service import save_or_update_bot_device


def is_admin(channel_id, user_hash):
    """
    사용자가 관리자인지 확인합니다.

    Args:
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시

    Returns:
        bool: 관리자 여부
    """
    try:
        for admin in g.ADMIN_USERS:  # g.ADMIN_USERS로 접근
            if admin.get('user_hash') == user_hash and (
                    admin.get('channel_id') == channel_id or admin.get('channel_id') == "*"):
                return True
        return False
    except Exception as e:
        logger.error(f"[ADMIN_CHECK_ERROR] 관리자 확인 중 오류 발생: {e}")
        return False


def is_admin_ext(context):
    """
    context에서 _original_channel_id(우선) 또는 channel_id, user_hash를 사용해 관리자 여부를 확인합니다.
    Args:
        context (dict): 메시지 컨텍스트
    Returns:
        bool: 관리자 여부
    """
    channel_id = context.get("_original_channel_id") or context.get("channel_id")
    user_hash = context.get("user_hash")
    return is_admin(channel_id, user_hash)


def is_room_owner(channel_id, user_hash):
    """
    사용자가 해당 채널의 방장인지 확인합니다.

    Args:
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시

    Returns:
        bool: 방장 여부
    """
    try:
        for bot_name, channels in g.schedule_rooms.items():
            if str(channel_id) in channels:
                channel_config = channels[str(channel_id)]
                room_owners = channel_config.get('room_owners', [])

                if user_hash in room_owners:
                    return True
        return False
    except Exception as e:
        logger.error(f"[ROOM_OWNER_CHECK_ERROR] 방장 확인 중 오류 발생: {e}")
        return False


def is_admin_or_room_owner(channel_id, user_hash):
    """
    사용자가 관리자 또는 방장인지 확인합니다.

    Args:
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시

    Returns:
        bool: 관리자 또는 방장 여부
    """
    return is_admin(channel_id, user_hash) or is_room_owner(channel_id, user_hash)


# =====================
# HMAC 인증 유틸리티 (1단계)
# =====================

@dataclass
class ClientInfo:
    """인증된 클라이언트 정보"""
    client_type: str
    bot_name: str
    uuid: str
    mac_address: str
    ip_address: str
    connection_id: str
    version: str
    auth_time: float

class AuthenticationError(Exception):
    """인증 관련 예외 클래스"""
    pass

class BaseAuthValidator:
    """기본 인증 검증 클래스"""
    def __init__(self, config: Dict):
        self.config = config

    def validate_auth(self, auth_data: Dict) -> Tuple[bool, str, Optional[ClientInfo]]:
        try:
            required_fields = ["clientType", "botName", "deviceUUID", "macAddress", "ipAddress", "timestamp", "signature", "version"]
            for field in required_fields:
                if field not in auth_data:
                    return False, f"필수 인증 필드 누락: {field}", None
            bot_name = auth_data["botName"]
            bot_config = self.config.get("bots", {}).get(bot_name)
            if not bot_config:
                return False, f"등록되지 않은 봇: {bot_name}", None
            current_time = int(time.time() * 1000)
            auth_time = int(auth_data["timestamp"])
            max_time_diff = 5 * 60 * 1000
            if abs(current_time - auth_time) > max_time_diff:
                return False, "유효하지 않은 타임스탬프", None
            if not self._validate_device(bot_config, auth_data):
                return False, "허용되지 않은 장치", None
            if not self._validate_signature(bot_config, auth_data):
                return False, "유효하지 않은 서명", None
            client_info = ClientInfo(
                client_type=auth_data["clientType"],
                bot_name=bot_name,
                uuid=auth_data["deviceUUID"],
                mac_address=auth_data["macAddress"],
                ip_address=auth_data["ipAddress"],
                version=auth_data["version"],
                connection_id=f"{bot_name}_{auth_data['deviceUUID']}_{int(time.time())}",
                auth_time=time.time()
            )
            return True, "인증 성공", client_info
        except Exception as e:
            return False, f"인증 처리 오류: {str(e)}", None

    def _validate_device(self, bot_config: Dict, auth_data: Dict) -> bool:
        if not bot_config.get("validate_uuid", True) and \
           not bot_config.get("validate_mac", True) and \
           not bot_config.get("validate_ip", True):
            return True
        for device in bot_config.get("allowed_devices", []):
            # 디바이스 상태 체크 (approved만 통과)
            if device.get('status', 'pending') != 'approved':
                continue
            if bot_config.get("validate_uuid", True):
                if auth_data["deviceUUID"] != device.get("uuid"):
                    continue
            if bot_config.get("validate_mac", True):
                if auth_data["macAddress"] not in device.get("mac_addresses", []):
                    continue
            if bot_config.get("validate_ip", True):
                client_ip = auth_data["ipAddress"]
                ip_allowed = False
                for allowed_ip in device.get("ip_addresses", []):
                    if '/' in allowed_ip:
                        import ipaddress
                        try:
                            network = ipaddress.ip_network(allowed_ip)
                            if ipaddress.ip_address(client_ip) in network:
                                ip_allowed = True
                                break
                        except Exception:
                            pass
                    elif client_ip == allowed_ip:
                        ip_allowed = True
                        break
                if not ip_allowed:
                    continue
            return True
        return False

    def _validate_signature(self, bot_config: Dict, auth_data: Dict) -> bool:
        return False

class MessengerBotAuthValidator(BaseAuthValidator):
    """메신저봇R 전용 인증 검증기"""
    def _validate_signature(self, bot_config: Dict, auth_data: Dict) -> bool:
        secret_key = bot_config.get("secret_key", "")
        salt = bot_config.get("salt", "")
        sign_string = '|'.join([
            "MessengerBotR",
            auth_data["botName"],
            auth_data["deviceUUID"],
            auth_data["macAddress"],
            auth_data["ipAddress"],
            str(auth_data["timestamp"]),
            salt
        ])
        expected_signature = hmac.new(
            key=secret_key.encode('utf-8'),
            msg=sign_string.encode('utf-8'),
            digestmod=hashlib.sha256
        ).hexdigest()
        # HMAC 로그는 필요시에만 활성화 (성능상 이유로 기본 비활성화)
        # logger.debug(f"[HMAC] sign_string={sign_string}")
        # logger.debug(f"[HMAC] expected_signature={expected_signature}")
        # logger.debug(f"[HMAC] received_signature={auth_data['signature']}")
        return hmac.compare_digest(expected_signature, auth_data["signature"])

class Customer1AuthValidator(BaseAuthValidator):
    """CustomerBot1 맞춤 인증 검증기 (예시)"""
    def _validate_signature(self, bot_config: Dict, auth_data: Dict) -> bool:
        # 예시: custom_auth_logic_1 방식 (실제 고객사 요구에 맞게 구현)
        secret_key = bot_config.get("secret_key", "")
        salt = bot_config.get("salt", "")
        # 예: 필드 순서/구분자/논스 등 맞춤 처리
        sign_data = [
            auth_data["clientType"],
            auth_data["botName"],
            auth_data["deviceUUID"],
            salt,
            str(auth_data["timestamp"])
        ]
        sign_string = "#".join(sign_data)
        expected_signature = hmac.new(
            key=secret_key.encode('utf-8'),
            msg=sign_string.encode('utf-8'),
            digestmod=hashlib.sha256
        ).hexdigest()
        return hmac.compare_digest(expected_signature, auth_data["signature"])

class ClientAuthFactory:
    @staticmethod
    def create_validator(client_type: str, config: Dict, bot_name: str = None) -> BaseAuthValidator:
        # 고객사별 맞춤 로직 우선
        if bot_name:
            bot_config = config.get('bots', {}).get(bot_name, {})
            if bot_config.get('custom_auth_logic') == 'custom_logic_1':
                return Customer1AuthValidator(config)
        if client_type == "MessengerBotR":
            return MessengerBotAuthValidator(config)
        else:
            return BaseAuthValidator(config)

async def save_auth_fail_log_to_db(log_entry):
    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cur:
                await cur.execute(
                    """
                    INSERT INTO kb_auth_fail_logs
                    (client_type, bot_name, reason, timestamp, uuid, mac_address, ip_address, auth_data, raw_log)
                    VALUES (%s, %s, %s, NOW(), %s, %s, %s, %s, %s)
                    """,
                    (
                        log_entry.get("clientType"),
                        log_entry.get("botName"),
                        log_entry.get("reason"),
                        log_entry.get("auth_data", {}).get("deviceUUID"),
                        log_entry.get("auth_data", {}).get("macAddress"),
                        log_entry.get("auth_data", {}).get("ipAddress"),
                        json.dumps(log_entry.get("auth_data"), ensure_ascii=False),
                        json.dumps({k: v for k, v in log_entry.items() if k != 'signature'}, ensure_ascii=False)
                    )
                )
            await conn.commit()
    except Exception as e:
        logger.error(f"[AUTH_FAIL_DB] 인증 실패 로그 DB 저장 오류: {e}")

class AuthenticationService:
    def __init__(self, config_path: str = AUTH_CONFIG_PATH):
        self.config = self._load_config(config_path)
        self.auth_enabled = self.config.get("auth_enabled", True)
        self.authenticated_clients = {}
        self.failed_auth_logs = []  # 인증 실패 로그
        self.failed_auth_log_path = 'server/logs/auth_fail.log'

    def _load_config(self, config_path: str) -> Dict:
        try:
            with open(config_path, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            logger.error(f"[AUTH_CONFIG_LOAD_ERROR] 인증 설정 파일 로드 실패: {e}")
            return {"auth_enabled": False, "bots": {}, "default_policy": "deny"}

    def reload_config(self, config_path: str = AUTH_CONFIG_PATH) -> None:
        self.config = self._load_config(config_path)
        self.auth_enabled = self.config.get("auth_enabled", True)
        logger.info(f"[AUTH_CONFIG] 인증 설정 리로드 완료")

    async def validate_auth_async(self, auth_data: dict, status_hint=None):
        """
        인증 비동기 검증 및 디바이스 등록/갱신
        """
        try:
            required_fields = ["clientType", "botName", "deviceUUID", "macAddress", "ipAddress", "timestamp", "signature", "version"]
            for field in required_fields:
                if field not in auth_data:
                    if status_hint == 'deny':
                        logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={auth_data.get('botName')}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                        await save_or_update_bot_device(
                            auth_data.get("botName", "unknown"),
                            auth_data.get("clientType", "unknown"),
                            auth_data.get("deviceUUID", "unknown"),
                            auth_data.get("macAddress", "unknown"),
                            auth_data.get("ipAddress", "unknown"),
                            auth_data.get("version", "unknown"),
                            status_hint='deny'
                        )
                    return False, f"필수 인증 필드 누락: {field}", None
            bot_name = auth_data["botName"]
            bot_config = self.config.get("bots", {}).get(bot_name)
            if not bot_config:
                if status_hint == 'deny':
                    logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                    await save_or_update_bot_device(
                        bot_name,
                        auth_data.get("clientType", "unknown"),
                        auth_data.get("deviceUUID", "unknown"),
                        auth_data.get("macAddress", "unknown"),
                        auth_data.get("ipAddress", "unknown"),
                        auth_data.get("version", "unknown"),
                        status_hint='deny'
                    )
                return False, f"등록되지 않은 봇: {bot_name}", None
            current_time = int(time.time() * 1000)
            auth_time = int(auth_data["timestamp"])
            max_time_diff = 5 * 60 * 1000
            if abs(current_time - auth_time) > max_time_diff:
                if status_hint == 'deny':
                    logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                    await save_or_update_bot_device(
                        bot_name,
                        auth_data.get("clientType", "unknown"),
                        auth_data.get("deviceUUID", "unknown"),
                        auth_data.get("macAddress", "unknown"),
                        auth_data.get("ipAddress", "unknown"),
                        auth_data.get("version", "unknown"),
                        status_hint='deny'
                    )
                return False, "유효하지 않은 타임스탬프", None
            if not self._validate_device(bot_config, auth_data):
                if status_hint == 'deny':
                    logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                    await save_or_update_bot_device(
                        bot_name,
                        auth_data.get("clientType", "unknown"),
                        auth_data.get("deviceUUID", "unknown"),
                        auth_data.get("macAddress", "unknown"),
                        auth_data.get("ipAddress", "unknown"),
                        auth_data.get("version", "unknown"),
                        status_hint='deny'
                    )
                return False, "허용되지 않은 장치", None
            if not self._validate_signature(bot_config, auth_data):
                if status_hint == 'deny':
                    logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                    await save_or_update_bot_device(
                        bot_name,
                        auth_data.get("clientType", "unknown"),
                        auth_data.get("deviceUUID", "unknown"),
                        auth_data.get("macAddress", "unknown"),
                        auth_data.get("ipAddress", "unknown"),
                        auth_data.get("version", "unknown"),
                        status_hint='deny'
                    )
                return False, "유효하지 않은 서명", None
            # 인증 성공 시 디바이스 정보 등록/갱신
            # logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=None")
            # await save_or_update_bot_device(
            #     bot_name,
            #     auth_data["clientType"],
            #     auth_data["deviceUUID"],
            #     auth_data["macAddress"],
            #     auth_data["ipAddress"],
            #     auth_data["version"],
            #     status_hint=None
            # )
            client_info = ClientInfo(
                client_type=auth_data["clientType"],
                bot_name=bot_name,
                uuid=auth_data["deviceUUID"],
                mac_address=auth_data["macAddress"],
                ip_address=auth_data["ipAddress"],
                version=auth_data["version"],
                connection_id=f"{bot_name}_{auth_data['deviceUUID']}_{int(time.time())}",
                auth_time=time.time()
            )
            return True, "인증 성공", client_info
        except Exception as e:
            if status_hint == 'deny':
                logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={auth_data.get('botName')}, uuid={auth_data.get('deviceUUID')}, status_hint=deny")
                await save_or_update_bot_device(
                    auth_data.get("botName", "unknown"),
                    auth_data.get("clientType", "unknown"),
                    auth_data.get("deviceUUID", "unknown"),
                    auth_data.get("macAddress", "unknown"),
                    auth_data.get("ipAddress", "unknown"),
                    auth_data.get("version", "unknown"),
                    status_hint='deny'
                )
            return False, f"인증 처리 오류: {str(e)}", None

    def validate_auth(self, auth_data: Dict) -> Tuple[bool, str, Optional[ClientInfo]]:
        if not self.auth_enabled:
            return True, "인증 비활성화됨", None
        client_type = auth_data.get("clientType", "unknown")
        bot_name = auth_data.get("botName")
        validator = ClientAuthFactory.create_validator(client_type, self.config, bot_name)
        success, message, client_info = validator.validate_auth(auth_data)
        if success and client_info:
            self.authenticated_clients[client_info.connection_id] = client_info
        else:
            log_entry = {
                "clientType": client_type,
                "botName": bot_name,
                "reason": message,
                "timestamp": time.strftime('%Y-%m-%d %H:%M:%S', time.localtime()),
                "auth_data": {k: v for k, v in auth_data.items() if k != 'signature'}
            }
            self.failed_auth_logs.append(log_entry)
            logger.warning(f"[AUTH_FAIL_LOG] {log_entry}")
            # DB 저장 (비동기)
            asyncio.create_task(save_auth_fail_log_to_db(log_entry))
        # 디바이스 등록/갱신 로그
        # logger.info(f"[AUTH_DEVICE] save_or_update_bot_device 호출: bot={bot_name}, uuid={auth_data.get('deviceUUID')}, status_hint=None")
        # asyncio.create_task(save_or_update_bot_device(
        #     bot_name,
        #     auth_data.get("clientType", "unknown"),
        #     auth_data.get("deviceUUID", "unknown"),
        #     auth_data.get("macAddress", "unknown"),
        #     auth_data.get("ipAddress", "unknown"),
        #     auth_data.get("version", "unknown"),
        #     status_hint=None
        # ))
        return success, message, client_info

    def persist_failed_auth_logs(self):
        """인증 실패 로그를 파일에 저장 (JSON Lines)"""
        if not self.failed_auth_logs:
            return
        log_dir = os.path.dirname(self.failed_auth_log_path)
        os.makedirs(log_dir, exist_ok=True)
        with open(self.failed_auth_log_path, 'a', encoding='utf-8') as f:
            for entry in self.failed_auth_logs:
                f.write(json.dumps(entry, ensure_ascii=False) + '\n')
        self.failed_auth_logs.clear()

    def get_recent_failed_auth_logs(self, n=20):
        """최근 n건의 인증 실패 로그 반환 (파일에서 역순 조회)"""
        if not os.path.exists(self.failed_auth_log_path):
            return []
        with open(self.failed_auth_log_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()
        return [json.loads(line) for line in lines[-n:]]
