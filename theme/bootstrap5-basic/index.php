<?php

/**
 * 커뮤니티 인덱스 페이지
 */
if (!defined('_INDEX_')) {
    define('_INDEX_', true);
}

if (!defined('_GNUBOARD_')) {
    exit;
}

//커뮤니티 미사용시 쇼핑몰
if (G5_COMMUNITY_USE === false) {
    include_once G5_THEME_SHOP_PATH . '/index.php';
    return;
}

include_once G5_THEME_PATH . '/head.php';

?>

<div class="container">
    <div class="px-4 pt-5 my-5 text-center border-bottom">
        <h1 class="display-4 fw-bold">ASK-SEO</h1>
        <div class="col-lg-8 mx-auto">
            <p class="lead mb-4">
                쉽고 빠르게 그누보드 SEO 설정을 할 수 있는 ASKSEO 입니다.<br />
                RSS, SITEMAP, 개시판별 SEO 설정, 그룹별 SEO 설정, 일반페이지 SEO 설정, Robot text 설정 등 다양한 기능을 관리자를 통해 쉽게 설정 할 수 있습니다.
                그누보드, 영카트, 각종 빌더 등 그누보드 기반 솔루션에서 모두 이용 가능합니다.
            </p>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mb-5">
                <a href="https://sir.kr/cmall/1542957738" class="btn btn-primary btn-lg px-4 me-sm-3">살펴보기</a>
            </div>
        </div>
        <div class="overflow-hidden" style="max-height: 30vh;">
            <div class="container px-5">
                <img src="<?php echo G5_THEME_URL ?>/screenshot.png" class="img-fluid border rounded-3 shadow-lg mb-4" alt="Example image" width="700" height="500" loading="lazy">
            </div>
        </div>
    </div>
</div>
<div class="container">
    <div class="row row-cols-sm-1 row-cols-lg-3">
        <div class="col">
            <?php echo latest('theme/basic', 'notice', 10, 90); ?>
        </div>
        <div class="col">
            <?php echo latest('theme/basic', 'qa', 10, 90); ?>
        </div>
        <div class="col">
            <?php echo latest('theme/basic', 'free', 10, 90); ?>
        </div>
    </div>
</div>
<div class="container">
    <?php echo latest('theme/gallery', 'gallery', 8, 60); ?>
</div>
<?php
include_once(G5_THEME_PATH . '/tail.php');
