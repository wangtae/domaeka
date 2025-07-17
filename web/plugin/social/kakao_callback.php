<?php
include_once('./_common.php');

// 소셜 로그인 사용 확인
if (!$config['cf_social_login_use']) {
    die('소셜 로그인을 사용하지 않습니다.');
}

// 소셜 로그인 프로필 정보 가져오기
$provider_name = social_get_request_provider();
$user_profile = social_session_exists_check();

if (!$user_profile || strtolower($provider_name) !== 'kakao') {
    goto_url(G5_URL);
    exit;
}

// 원래 URL 저장
$url = isset($_GET['url']) ? clean_xss_tags(clean_xss_attributes(strip_tags($_GET['url']))) : '';
if ($url) {
    $_SESSION['ss_redirect_url'] = urldecode($url);
}

// 이미 가입된 회원인지 확인
$sm_id = $user_profile->sid;
$sql = "SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '".sql_real_escape_string($sm_id)."'";
$mb = sql_fetch($sql);

if ($mb['mb_id']) {
    // 이미 가입된 회원이면 로그인 처리
    set_session('ss_mb_id', $mb['mb_id']);
    if (function_exists('update_auth_session_token')) {
        update_auth_session_token(G5_TIME_YMDHIS);
    }
    
    // 원래 URL로 리다이렉트
    $redirect_url = isset($_SESSION['ss_redirect_url']) ? $_SESSION['ss_redirect_url'] : G5_URL;
    unset($_SESSION['ss_redirect_url']);
    goto_url($redirect_url);
} else {
    // 신규 회원이면 자동 회원가입 페이지로
    goto_url(G5_PLUGIN_URL.'/social/register_member_kakao_direct.php');
}
?>