<?php
include_once('./_common.php');

// 카카오 자동 회원가입 활성화
define('G5_KAKAO_AUTO_REGISTER', true);

if( function_exists('social_check_login_before') ){
    $social_login_html = social_check_login_before();
}

$g5['title'] = '도매까 로그인';

$od_id = isset($_POST['od_id']) ? safe_replace_regex($_POST['od_id'], 'od_id') : '';

// URL 파라미터 처리
$url = isset($_GET['url']) ? $_GET['url'] : '';

// provider 파라미터 처리 - redirect_to_idp는 무시
$provider = isset($_GET['provider']) ? $_GET['provider'] : '';

// provider가 kakao인 경우 바로 카카오 로그인 처리
if ($provider === 'kakao') {
    // 카카오 소셜 로그인으로 리디렉션
    $kakao_login_url = G5_BBS_URL . '/login.php?provider=kakao&url=' . urlencode($url);
    header('Location: ' . $kakao_login_url);
    exit;
}

// url 체크
check_url_host($url);

// 이미 로그인 중이라면
if ($is_member) {
    if ($url)
        goto_url($url);
    else
        goto_url(G5_URL);
}

$login_url        = login_url($url);
$login_action_url = G5_HTTPS_BBS_URL."/login_check.php";
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $g5['title'] ?> | <?php echo $config['cf_title'] ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-height: 100vh;
    background: white;
}

.kakao-login-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.kakao-login-nav {
    border-bottom: 1px solid #e5e7eb;
    background: white;
}

.kakao-login-header {
    display: flex;
    align-items: center;
    padding: 0 24px;
    height: 64px;
    max-width: 1024px;
    margin: 0 auto;
}

.kakao-login-title {
    font-weight: bold;
    font-size: 18px;
}

.kakao-login-content {
    text-align: center;
    margin-top: 24px;
    margin-bottom: 32px;
    padding: 0 20px;
}

.kakao-login-subtitle {
    font-size: 18px;
    line-height: 1.5;
}

.kakao-login-main {
    display: block;
    margin-top: 8px;
    font-size: 24px;
    font-weight: 500;
    line-height: 1.4;
}

.kakao-login-button-wrapper {
    padding: 0 20px;
    margin: 0 auto;
    max-width: 400px;
    width: 100%;
}

.kakao-login-button {
    display: block;
    width: 100%;
    height: auto;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.kakao-login-button:hover {
    opacity: 0.95;
}

.kakao-login-button img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 8px;
}

/* 모바일 우선 반응형 디자인 */
@media (max-width: 480px) {
    .kakao-login-button-wrapper {
        max-width: 200px;
    }
    
    .kakao-login-button img {
        border-radius: 6px;
    }
}

@media (min-width: 481px) and (max-width: 768px) {
    .kakao-login-button-wrapper {
        max-width: 350px;
    }
    
    .kakao-login-button img {
        border-radius: 8px;
    }
}

@media (min-width: 769px) {
    .kakao-login-button-wrapper {
        max-width: 400px;
    }
    
    .kakao-login-button img {
        border-radius: 12px;
    }
}

.kakao-login-links {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 40px auto 0;
    padding: 0 20px;
    max-width: 400px;
}

.kakao-login-links a {
    color: black;
    text-decoration: underline;
    font-size: 14px;
    text-align: center;
}
</style>
</head>
<body>
<div class="kakao-login-wrapper">
    <!-- Navigation -->
    <nav class="kakao-login-nav">
        <header class="kakao-login-header">
            <div class="kakao-login-title">도매까 로그인</div>
        </header>
    </nav>
    
    <!-- Main Content -->
    <div class="kakao-login-content">
        <span class="kakao-login-subtitle">간편하게 로그인하고</span><br>
        <span class="kakao-login-main">
            도매까 공동구매를<br> 
            경험해 보세요
        </span>
    </div>
    
    <!-- Kakao Login Button -->
    <div class="kakao-login-button-wrapper">
        <?php
        // 카카오 소셜 로그인 URL 생성 (정상적인 login.php를 통해)
        $kakao_login_url = G5_BBS_URL . '/login.php?provider=kakao&auto_register=1';
        
        // URL 파라미터가 있으면 추가
        if ($url) {
            $kakao_login_url .= '&url=' . urlencode($url);
        }
        ?>
        <a href="<?php echo $kakao_login_url; ?>" class="kakao-login-button">
            <picture>
                <source media="(max-width: 480px)" srcset="<?php echo G5_URL; ?>/assets/domaeka/logo/kakao_login/ko/kakao_login_medium_narrow.png">
                <source media="(max-width: 768px)" srcset="<?php echo G5_URL; ?>/assets/domaeka/logo/kakao_login/ko/kakao_login_large_narrow.png">
                <img src="<?php echo G5_URL; ?>/assets/domaeka/logo/kakao_login/ko/kakao_login_large_wide.png" alt="카카오 로그인">
            </picture>
        </a>
    </div>
</div>
</body>
</html>