"""
인증 및 보안 처리 모듈
"""
import hmac
import hashlib
import time
from typing import Dict, Any, Optional, Tuple
from core.logger import logger


class AuthValidator:
    """
    클라이언트 인증 검증 클래스
    """
    
    def __init__(self, secret_key: str = "8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8"):
        """
        인증 검증기 초기화
        
        Args:
            secret_key: HMAC 서명 검증용 비밀키
        """
        self.secret_key = secret_key.encode('utf-8')
        self.bot_specific_salt = "7f$kLz9^&*1pXyZ2"
        
    def validate_auth(self, auth_data: Dict[str, Any]) -> Tuple[bool, str]:
        """
        클라이언트 인증 정보 검증
        
        Args:
            auth_data: 클라이언트로부터 받은 인증 데이터
            
        Returns:
            Tuple[bool, str]: (검증 성공 여부, 오류 메시지)
        """
        try:
            # 필수 필드 확인
            required_fields = ['clientType', 'botName', 'deviceUUID', 'timestamp', 'signature']
            for field in required_fields:
                if field not in auth_data:
                    return False, f"필수 인증 필드 누락: {field}"
            
            # 타임스탬프 검증 (5분 이내)
            current_time = int(time.time() * 1000)
            auth_timestamp = auth_data.get('timestamp', 0)
            if abs(current_time - auth_timestamp) > 300000:  # 5분
                return False, "인증 시간 초과"
            
            # HMAC 서명 검증
            if not self._verify_signature(auth_data):
                return False, "서명 검증 실패"
            
            logger.info(f"[AUTH] 인증 성공: {auth_data.get('botName')} ({auth_data.get('deviceUUID')})")
            return True, ""
            
        except Exception as e:
            logger.error(f"[AUTH] 인증 검증 오류: {e}")
            return False, f"인증 검증 오류: {str(e)}"
    
    def _verify_signature(self, auth_data: Dict[str, Any]) -> bool:
        """
        HMAC 서명 검증
        
        Args:
            auth_data: 인증 데이터
            
        Returns:
            bool: 서명 검증 성공 여부
        """
        try:
            # 서명 문자열 생성 (클라이언트와 동일한 방식)
            sign_string = "|".join([
                auth_data.get('clientType', ''),
                auth_data.get('botName', ''),
                auth_data.get('deviceUUID', ''),
                auth_data.get('macAddress', ''),
                auth_data.get('ipAddress', ''),
                str(auth_data.get('timestamp', '')),
                self.bot_specific_salt
            ])
            
            # HMAC 서명 계산
            expected_signature = hmac.new(
                self.secret_key,
                sign_string.encode('utf-8'),
                hashlib.sha256
            ).hexdigest()
            
            received_signature = auth_data.get('signature', '')
            
            # 서명 비교
            return hmac.compare_digest(expected_signature, received_signature)
            
        except Exception as e:
            logger.error(f"[AUTH] 서명 검증 오류: {e}")
            return False
    
    def extract_client_info(self, auth_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        클라이언트 정보 추출
        
        Args:
            auth_data: 인증 데이터
            
        Returns:
            Dict[str, Any]: 추출된 클라이언트 정보
        """
        return {
            'client_type': auth_data.get('clientType', ''),
            'bot_name': auth_data.get('botName', ''),
            'device_uuid': auth_data.get('deviceUUID', ''),
            'mac_address': auth_data.get('macAddress', ''),
            'ip_address': auth_data.get('ipAddress', ''),
            'version': auth_data.get('version', ''),
            'timestamp': auth_data.get('timestamp', 0)
        }


# 전역 인증 검증기 인스턴스
auth_validator = AuthValidator()


def validate_client_auth(auth_data: Optional[Dict[str, Any]]) -> Tuple[bool, str, Dict[str, Any]]:
    """
    클라이언트 인증 검증 헬퍼 함수
    
    Args:
        auth_data: 인증 데이터 (None일 수 있음)
        
    Returns:
        Tuple[bool, str, Dict]: (검증 성공 여부, 오류 메시지, 클라이언트 정보)
    """
    if not auth_data:
        return False, "인증 정보 없음", {}
    
    is_valid, error_msg = auth_validator.validate_auth(auth_data)
    client_info = auth_validator.extract_client_info(auth_data) if is_valid else {}
    
    return is_valid, error_msg, client_info