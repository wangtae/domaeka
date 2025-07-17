<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 카카오 로그인 시 자동 회원가입 처리 함수
 * 
 * @param object $user_profile 소셜 로그인 사용자 프로필
 * @param string $provider_name 소셜 제공자 이름 (Kakao)
 * @param string $url 회원가입 후 리디렉션할 URL
 * @return bool 성공 여부
 */
function social_auto_register_member($user_profile, $provider_name, $url = '') {
    global $g5, $config;
    
    if (!$user_profile || strtolower($provider_name) !== 'kakao') {
        return false;
    }
    
    // 카카오 자동 회원가입 페이지로 리다이렉트
    $register_url = G5_SOCIAL_LOGIN_URL . '/register_member_kakao.php?provider=' . $provider_name;
    
    if ($url) {
        $_SESSION['ss_redirect_url'] = $url;
    }
    
    goto_url($register_url);
    return true;
}

// exist_mb_id_recursive와 exist_mb_nick_recursive 함수는 
// functions.php에 이미 정의되어 있으므로 여기서는 제거합니다.