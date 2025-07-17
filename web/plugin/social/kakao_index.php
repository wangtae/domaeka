<?php
include_once('./_common.php');
include_once(G5_SOCIAL_LOGIN_PATH.'/includes/functions.php');

if (!$config['cf_social_login_use']) {
    alert('소셜 로그인을 사용하지 않습니다.', G5_URL);
}

// 카카오 자동 회원가입 플래그 설정
$_SESSION['kakao_auto_register'] = true;

// URL 파라미터 저장
$url = isset($_GET['url']) ? clean_xss_tags(clean_xss_attributes(strip_tags($_GET['url']))) : '';
if ($url) {
    $_SESSION['ss_redirect_url'] = urldecode($url);
}

// HybridAuth 설정
$config_file_path = G5_SOCIAL_LOGIN_PATH.'/includes/config.php';
if (!file_exists($config_file_path)) {
    die('HybridAuth 설정 파일이 없습니다.');
}

require_once(G5_SOCIAL_LOGIN_PATH.'/includes/autoload.php');

use Hybridauth\Hybridauth;

try {
    // HybridAuth 인스턴스 생성
    $hybridauth = new Hybridauth($config_file_path);
    
    // 카카오 인증 시작
    $adapter = $hybridauth->authenticate('Kakao');
    
    // 사용자 프로필 가져오기
    $user_profile = $adapter->getUserProfile();
    
    if ($user_profile) {
        // 프로필 정보를 세션에 저장
        social_user_profile_cache($user_profile->identifier, 'Kakao', $user_profile);
        $_SESSION['sl_userprofile'] = $user_profile;
        $_SESSION['social_login_redirect_to'] = $url ? $url : G5_URL;
        $_SESSION['provider_name'] = 'Kakao';
        
        // 자동 회원가입 처리 페이지로 리다이렉트
        goto_url(G5_PLUGIN_URL.'/social/register_member_kakao_direct.php');
    }
    
} catch (Exception $e) {
    // 오류 처리
    alert('카카오 로그인 중 오류가 발생했습니다: ' . $e->getMessage(), G5_URL);
}
?>