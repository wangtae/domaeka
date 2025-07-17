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
    
    // URL을 세션에 저장
    if ($url) {
        $_SESSION['ss_redirect_url'] = $url;
    }
    
    // 자동 회원가입 페이지로 리다이렉트 (기존 register_member.php 활용)
    $register_url = G5_PLUGIN_URL . '/social/register_member_auto.php?provider=' . $provider_name;
    if ($url) {
        $register_url .= '&url=' . urlencode($url);
    }
    
    goto_url($register_url);
    return true;
}