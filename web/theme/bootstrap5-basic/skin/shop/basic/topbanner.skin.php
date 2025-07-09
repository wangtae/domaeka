<?php

/**
 * 쇼핑몰 상단 배너
 * 동일 높이의 배너를 사용하세요.
 * 설치설명서 확인 후 영카트 배너관리자 수정할것
 * 
 */
if (!defined("_GNUBOARD_")) {
    exit;
}
//쿠키가 있으면 1일동안 출력하지 않음
if (isset($_COOKIE['close-top-banner']) && $_COOKIE['close-top-banner'] == 1) {
    return;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/banner.css">', 200);
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/bxslider/dist/jquery.bxslider.min.css">', 150);
add_javascript('<script src="' . BB_ASSETS_URL . '/js/bxslider/dist/jquery.bxslider.min.js"></script>', 10);
?>
<div class='banner-wrap bg-white position-relative d-none'>
    <div class="position-absolute close-item">
        <button type="button" class="btn-close"><span class="visually-hidden">배너닫기</span></button>
    </div>
    <div class="top-banner-wrap d-flex flex-row justify-content-center">
        <?php
        for ($i = 0; $row = sql_fetch_array($result); $i++) {

            if ($i == 0) {
                echo '<div class="top-banner-slider bxslider">' . PHP_EOL;
            }

            $bn_border  = ($row['bn_border']) ? ' class="border"' : '';;
            $bn_new_win = ($row['bn_new_win']) ? ' target="_blank"' : '';
            $bimg = G5_DATA_PATH . '/banner/' . $row['bn_id'];
            if (file_exists($bimg)) {
                $banner = '';
                $size = getimagesize($bimg);
                echo '<div class="banner-items">' . PHP_EOL;
                if ($row['bn_url'][0] == '#') {
                    $banner .= '<a href="' . $row['bn_url'] . '">';
                } else if ($row['bn_url'] && $row['bn_url'] != 'http://') {
                    $banner .= '<a href="' . G5_SHOP_URL . '/bannerhit.php?bn_id=' . $row['bn_id'] . '"' . $bn_new_win . ' class="d-flex justify-content-center">';
                }
                echo $banner . '<img src="' . G5_DATA_URL . '/banner/' . $row['bn_id'] . '?' . preg_replace('/[^0-9]/i', '', $row['bn_time']) . '" alt="' . get_text($row['bn_alt']) . '" width="' . $size[0] . '" height="' . $size[1] . '"' . $bn_border . ' class="img-fluid">';
                if ($banner) {
                    echo '</a>' . PHP_EOL;
                }
                echo '</div>' . PHP_EOL;
            }
        }
        if ($i > 0) {
            echo '</div>' . PHP_EOL;
        }
        ?>
    </div>

</div>
<script>
    $(function() {
        //이미지 늦게 로딩. 커졌다 줄어드는것 방지
        setTimeout(function() {
            $('.banner-wrap').removeClass('d-none');
            $('.top-banner-slider').show().bxSlider({
                speed: 800,
                pager: false,
                controls: false,
                preloadImages: 'visible'

            });
            $('.btn-close').on('click', function() {
                //닫기 클릭하면 1일 쿠키 생성한다.
                Cookies.set('close-top-banner', '1', {
                    expires: 1
                })
                //광고 닫기
                $('.banner-wrap').animate({
                    height: '0px'
                }, 200, function() {
                    $(this).hide();
                });
            });
        }, 100);
    });
</script>