<?php
/**
 * 카카오 로그인 에러 처리 및 자동 복구 스크립트
 */

include_once('./_common.php');
include_once(G5_PLUGIN_PATH.'/social/kakao_session_fix.php');

// 카카오 로그인 에러 자동 복구 함수
function handle_kakao_login_error($error_code = 6) {
    global $g5;
    
    // 에러 코드 6인 경우 (프로필 요청 실패)
    if ($error_code == 6) {
        // 1. HybridAuth 세션 초기화
        if (isset($_SESSION['HA::STORE'])) {
            unset($_SESSION['HA::STORE']['hauth_session.kakao']);
        }
        
        // 2. 소셜 로그인 세션 초기화
        if (isset($_SESSION['sl_userprofile'])) {
            unset($_SESSION['sl_userprofile']['kakao']);
        }
        
        // 3. 카카오 관련 세션 쿠키 삭제
        setcookie('kakao_session', '', time() - 3600, '/');
        
        // 4. 세션 ID 재생성
        session_regenerate_id(true);
        
        // 5. 로그인 페이지로 리다이렉트 (자동 재시도)
        $redirect_url = isset($_SESSION['ss_redirect_url']) ? $_SESSION['ss_redirect_url'] : G5_URL;
        $login_url = G5_BBS_URL . '/login-kakao.php?url=' . urlencode($redirect_url) . '&retry=1';
        
        // 재시도 플래그 체크 (무한 루프 방지)
        if (isset($_GET['retry']) && $_GET['retry'] > 2) {
            alert('카카오 로그인에 문제가 발생했습니다. 잠시 후 다시 시도해주세요.', G5_URL);
        } else {
            goto_url($login_url);
        }
    }
}

// 카카오 액세스 토큰 갱신 함수
function refresh_kakao_token($refresh_token) {
    global $config;
    
    // 카카오 REST API 키 가져오기
    $client_id = $config['cf_kakao_rest_key'];
    
    if (!$client_id || !$refresh_token) {
        return false;
    }
    
    $token_url = 'https://kauth.kakao.com/oauth/token';
    $params = array(
        'grant_type' => 'refresh_token',
        'client_id' => $client_id,
        'refresh_token' => $refresh_token
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $token_data = json_decode($response, true);
        if (isset($token_data['access_token'])) {
            // 새로운 토큰을 세션에 저장
            if (isset($_SESSION['HA::STORE']['hauth_session.kakao.token.access_token'])) {
                $_SESSION['HA::STORE']['hauth_session.kakao.token.access_token'] = serialize($token_data['access_token']);
            }
            if (isset($token_data['refresh_token'])) {
                $_SESSION['HA::STORE']['hauth_session.kakao.token.refresh_token'] = serialize($token_data['refresh_token']);
            }
            return true;
        }
    }
    
    return false;
}

// 카카오 세션 상태 체크 및 복구
function check_and_repair_kakao_session() {
    // 카카오 세션이 있는지 체크
    if (!check_kakao_session()) {
        return false;
    }
    
    // 액세스 토큰 유효성 체크
    if (isset($_SESSION['HA::STORE']['hauth_session.kakao.token.access_token'])) {
        $access_token = unserialize($_SESSION['HA::STORE']['hauth_session.kakao.token.access_token']);
        
        // 토큰 정보 조회 API 호출
        $token_info_url = 'https://kapi.kakao.com/v1/user/access_token_info';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_info_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 토큰이 유효하지 않은 경우
        if ($http_code != 200) {
            // 리프레시 토큰으로 갱신 시도
            if (isset($_SESSION['HA::STORE']['hauth_session.kakao.token.refresh_token'])) {
                $refresh_token = unserialize($_SESSION['HA::STORE']['hauth_session.kakao.token.refresh_token']);
                return refresh_kakao_token($refresh_token);
            }
            return false;
        }
        
        return true;
    }
    
    return false;
}
?>