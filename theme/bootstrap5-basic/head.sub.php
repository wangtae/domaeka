<?php
// 이 파일은 새로운 파일 생성시 반드시 포함되어야 함
if (!defined('_GNUBOARD_')) {
    exit;
}

$g5_debug['php']['begin_time'] = $begin_time = get_microtime();

if (!isset($g5['title'])) {
    $g5['title'] = $config['cf_title'];
    $g5_head_title = $g5['title'];
} else {
    // 상태바에 표시될 제목
    $g5_head_title = implode(' | ', array_filter(array($g5['title'], $config['cf_title'])));
}

$g5['title'] = strip_tags($g5['title']);
$g5_head_title = strip_tags($g5_head_title);

// 현재 접속자
// 게시판 제목에 ' 포함되면 오류 발생
$g5['lo_location'] = addslashes($g5['title']);
if (!$g5['lo_location'])
    $g5['lo_location'] = addslashes(clean_xss_tags($_SERVER['REQUEST_URI']));
$g5['lo_url'] = addslashes(clean_xss_tags($_SERVER['REQUEST_URI']));
if (strstr($g5['lo_url'], '/' . G5_ADMIN_DIR . '/') || $is_admin == 'super') $g5['lo_url'] = '';

/*
// 만료된 페이지로 사용하시는 경우
header("Cache-Control: no-cache"); // HTTP/1.1
header("Expires: 0"); // rfc2616 - Section 14.21
header("Pragma: no-cache"); // HTTP/1.0
*/
?>
<!doctype html>
<html lang="ko" data-bs-theme="<?php echo (defined('BB_DARK_MODE') && BB_DARK_MODE === true) ? "dark" : "light"; ?>">

<head>
    <meta charset="utf-8">
    <!-- PC용만 사용, 반응형 -->
    <meta name="viewport" id="meta_viewport" content="width=device-width,initial-scale=1.0,minimum-scale=0,maximum-scale=10">
    <meta name="HandheldFriendly" content="true">
    <meta name="format-detection" content="telephone=no">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BB_ASSETS_URL ?>/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo BB_ASSETS_URL ?>/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BB_ASSETS_URL ?>/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo BB_ASSETS_URL ?>/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BB_ASSETS_URL ?>/favicon/favicon-16x16.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="<?php echo BB_ASSETS_URL ?>/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">

    <?php

    if ($config['cf_add_meta'])
        echo $config['cf_add_meta'] . PHP_EOL;
    ?>
    <title><?php echo $g5_head_title; ?></title>
    <?php
    //삭제 금지
    $shop_css = '';
    if (defined('_SHOP_')) $shop_css = '_shop';
    echo '<link rel="stylesheet" href="' . run_replace('head_css_url', G5_THEME_CSS_URL . '/' . (G5_IS_MOBILE ? 'mobile' : 'default') . $shop_css . '.css?ver=' . G5_CSS_VER, G5_THEME_URL) . '">' . PHP_EOL;
    ?>
    <script>
        // 자바스크립트에서 사용하는 전역변수 선언
        var g5_url = "<?php echo G5_URL ?>";
        var g5_bbs_url = "<?php echo G5_BBS_URL ?>";
        var g5_is_member = "<?php echo isset($is_member) ? $is_member : ''; ?>";
        var g5_is_admin = "<?php echo isset($is_admin) ? $is_admin : ''; ?>";
        var g5_is_mobile = "<?php echo G5_IS_MOBILE ?>";
        var g5_bo_table = "<?php echo isset($bo_table) ? $bo_table : ''; ?>";
        var g5_sca = "<?php echo isset($sca) ? $sca : ''; ?>";
        var g5_editor = "<?php echo ($config['cf_editor'] && $board['bo_use_dhtml_editor']) ? $config['cf_editor'] : ''; ?>";
        var g5_cookie_domain = "<?php echo G5_COOKIE_DOMAIN ?>";
        <?php if (defined('G5_USE_SHOP') && G5_USE_SHOP) { ?>
            var g5_theme_shop_url = "<?php echo G5_THEME_SHOP_URL; ?>";
            var g5_shop_url = "<?php echo G5_SHOP_URL; ?>";
        <?php } ?>
        <?php if (defined('G5_IS_ADMIN')) { ?>
            var g5_admin_url = "<?php echo G5_ADMIN_URL; ?>";
        <?php } ?>

        //bootstrap5 basic용 상수 선언
        <?php if (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == false) { ?>
            //메모팝업 사용안함
            const BB_MEMO_POPUP = false;
        <?php } ?>
        <?php if (defined('BB_MAIL_POPUP') && BB_MAIL_POPUP == false) { ?>
            //MAIL 팝업 사용안함
            const BB_MAIL_POPUP = false;
        <?php } ?>
        <?php if (defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP == false) { ?>
            //SCRAP 팝업 사용안함
            const BB_SCRAP_POPUP = false;
        <?php } ?>
        <?php if (defined('BB_PROFILE_POPUP') && BB_PROFILE_POPUP == false) { ?>
            //자기소개 팝업 사용안함
            const BB_PROFILE_POPUP = false;
        <?php } ?>
        <?php if (defined('BB_POINT_POPUP') && BB_POINT_POPUP == false) { ?>
            //포인트내역 팝업 사용안함
            const BB_POINT_POPUP = false;
        <?php } ?>
        <?php if (defined('BB_COUPON_POPUP') && BB_COUPON_POPUP == false) { ?>
            //영카트 쿠폰 팝업 사용안함
            const BB_COUPON_POPUP = false;
        <?php } ?>
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nanum+Gothic:wght@400;700;800&family=Noto+Sans+KR:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
    <?php
    ############################################################################################
    ## 압축 및 파일 합치기 사용시 css , js 뒤에 ?ver=' . G5_JS_VER . ' 붙이면 안됩니다.
    ############################################################################################
    //jQuery 최신 사용
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/jquery/dist/jquery.min.js"></script>', 0);
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/jquery-migrate/dist/jquery-migrate.min.js"></script>', 1);
    //Bootstrap 사용
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/bootstrap.css">', 200);

    //Bootstrap Icons
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/bootstrap-icons/font/bootstrap-icons.css">', 6);
    //Material Icons
    //add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/material-symbols/index.css">', 10);
    //add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/material-icons/css/material-icons.css">', 15);
    //add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/@material-design-icons/font/index.css">', 15);
    //THEME
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/askseo.css">', 15);
    add_javascript('<script src="' . BB_ASSETS_URL . '/css/bootstrap/dist/js/bootstrap.bundle.min.js"></script>', 20);

    add_javascript('<script src="' . BB_ASSETS_URL . '/js/js-cookie/dist/js.cookie.min.js"></script>', 10);
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/askseo.js"></script>', 20);

    //https://clipboardjs.com/
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/clipboard/dist/clipboard.min.js"></script>', 20);
    //https://highlightjs.org/
    //add_javascript('<script src="' . BB_ASSETS_URL . '/js/highlight/highlight.min.js"></script>', 25);
    //add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/highlight/styles/default.min.css">', 25);
    //prism.js
    //https://prismjs.com/index.html#examples
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/prism.js"></script>', 25);
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/prism.css">', 25);
    //scrollmagic 
    //http://scrollmagic.io/
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/scrollmagic/scrollmagic/minified/ScrollMagic.min.js"></script>', 30);
    //mhead
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/mhead-js/dist/mhead.js"></script>', 40);
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/mhead-js/dist/mhead.css">', 40);
    //https://swiperjs.com/get-started
    add_javascript('<script src="' . BB_ASSETS_URL . '/js/swiper/swiper-bundle.min.js"></script>', 240);
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/swiper/swiper-bundle.min.css">', 240);
    add_javascript('<script src="' . G5_JS_URL . '/common.js"></script>', 0);
    add_javascript('<script src="' . G5_JS_URL . '/wrest.js"></script>', 0);
    add_javascript('<script src="' . G5_JS_URL . '/placeholders.min.js"></script>', 0);
    add_stylesheet('<link rel="stylesheet" href="' . G5_JS_URL . '/font-awesome/css/font-awesome.min.css">', 0);

    if (G5_IS_MOBILE) {
        add_javascript('<script src="' . G5_JS_URL . '/modernizr.custom.70111.js"></script>', 1); // overflow scroll 감지
    }
    if (!defined('G5_IS_ADMIN')) {
        echo $config['cf_add_script'];
    }
    ?>
</head>

<body <?php echo isset($g5['body_script']) ? $g5['body_script'] : ''; ?> class='bg-light'>