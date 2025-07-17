<?php
include_once('./_common.php');

// 소셜 로그인 사용 확인
if (!$config['cf_social_login_use']) {
    alert('소셜 로그인을 사용하지 않습니다.', G5_URL);
}

// URL 파라미터 처리
$url = isset($_GET['url']) ? clean_xss_tags(clean_xss_attributes(strip_tags($_GET['url']))) : '';

// URL을 세션에 저장 (회원가입 후 리다이렉트용)
if ($url) {
    $_SESSION['ss_redirect_url'] = urldecode($url);
}

// 카카오 자동 회원가입 플래그 설정
$_SESSION['kakao_auto_register'] = true;

// 소셜 로그인 시작 (HybridAuth를 통한 카카오 인증)
$redirect_url = G5_PLUGIN_URL . '/social/index.php?hauth_start=Kakao&hauth_time=' . time();

// 리다이렉트
goto_url($redirect_url);
?>