<?php
include_once('./_common.php');

// 카카오 로그인 세션 문제 해결을 위한 헬퍼 파일

// 세션이 없으면 시작
if (!session_id()) {
    // 세션 설정 개선
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 7200); // 2시간
    
    // 세션 쿠키 도메인 설정
    if (defined('G5_COOKIE_DOMAIN') && G5_COOKIE_DOMAIN) {
        ini_set('session.cookie_domain', G5_COOKIE_DOMAIN);
    }
    
    session_start();
}

// HybridAuth 세션 재생성 함수
function regenerate_hybridauth_session() {
    // 기존 세션 데이터 백업
    $backup = array();
    if (isset($_SESSION['HA::STORE'])) {
        $backup['HA::STORE'] = $_SESSION['HA::STORE'];
    }
    if (isset($_SESSION['HA::CONFIG'])) {
        $backup['HA::CONFIG'] = $_SESSION['HA::CONFIG'];
    }
    
    // 세션 재생성
    session_regenerate_id(true);
    
    // 백업한 데이터 복원
    if (!empty($backup)) {
        foreach ($backup as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }
}

// 카카오 프로필 정보 캐싱 함수
function cache_kakao_profile($user_id, $profile_data) {
    $cache_key = 'kakao_profile_' . $user_id;
    $cache_data = serialize($profile_data);
    
    // 세션에 캐시
    $_SESSION[$cache_key] = $cache_data;
    $_SESSION[$cache_key . '_time'] = time();
}

// 캐시된 카카오 프로필 가져오기
function get_cached_kakao_profile($user_id) {
    $cache_key = 'kakao_profile_' . $user_id;
    
    // 캐시 유효시간 체크 (1시간)
    if (isset($_SESSION[$cache_key]) && isset($_SESSION[$cache_key . '_time'])) {
        if (time() - $_SESSION[$cache_key . '_time'] < 3600) {
            return unserialize($_SESSION[$cache_key]);
        }
    }
    
    return null;
}

// 카카오 세션 유효성 체크
function check_kakao_session() {
    // HybridAuth 세션 체크
    if (!isset($_SESSION['HA::STORE']) || !isset($_SESSION['HA::CONFIG'])) {
        return false;
    }
    
    // 카카오 인증 상태 체크
    if (isset($_SESSION['HA::STORE']['hauth_session.kakao.is_logged_in'])) {
        $is_logged_in = unserialize($_SESSION['HA::STORE']['hauth_session.kakao.is_logged_in']);
        return $is_logged_in === true;
    }
    
    return false;
}

// 카카오 세션 강제 설정
function force_kakao_session($provider_id, $user_id) {
    // HybridAuth 세션 구조 생성
    if (!isset($_SESSION['HA::STORE'])) {
        $_SESSION['HA::STORE'] = array();
    }
    
    // 카카오 로그인 상태 설정
    $_SESSION['HA::STORE']['hauth_session.kakao.is_logged_in'] = serialize(true);
    $_SESSION['HA::STORE']['hauth_session.kakao.id_provider'] = serialize($provider_id);
    $_SESSION['HA::STORE']['hauth_session.kakao.user_id'] = serialize($user_id);
    
    // 세션 타임스탬프 설정
    $_SESSION['HA::STORE']['hauth_session.kakao.login_time'] = serialize(time());
}
?>