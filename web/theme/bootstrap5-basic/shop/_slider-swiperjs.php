<?php

/**
 * 쇼핑몰 메인 슬라이더
 * 큰 슬라이더는 1296px 높이는 동일하게 하면 됩니다.
 * 썸네일은 160x60 입니다. 
 * 슬라이더 개수는 6개가 기본입니다. 
 * 슬라이더 쌍을 잘 맞춰 주세요.
 */

if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/slider.css">', 200);
?>
<!-- Swiper -->
<div class="container shop-index-slider-wrap mt-2">
    <div class="border bg-white position-relative">
        <div class="position-absolute thumb-wrapper bg-white">
            <!-- 썸네일 슬라이더 -->
            <div class="swiper thumb-shop-slier shadow">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_1m.png" />
                    </div>
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_2m.png" />
                    </div>
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_3m.png" />
                    </div>
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_4m.png" />
                    </div>
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_5m.png" />
                    </div>
                    <div class="swiper-slide">
                        <img src="<?php echo BB_ASSETS_URL ?>/image/slide_6m.png" />
                    </div>
                </div>
            </div>
        </div>
        <!-- 메인슬라이더 -->
        <div class="swiper shop-slier">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_1.png" /></a>
                </div>
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_2.png" /></a>
                </div>
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_3.png" /></a>
                </div>
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_4.png" /></a>
                </div>
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_5.png" /></a>
                </div>
                <div class="swiper-slide">
                    <a href='/'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_6.png" /></a>
                </div>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
</div>
<!-- Initialize Swiper -->
<script>
    //썸네일 슬라이더
    var swiper = new Swiper(".thumb-shop-slier", {
        spaceBetween: 0,
        slidesPerView: 6, //슬라이더 개수, 6개로 설정하
        freeMode: true,
        watchSlidesProgress: true,
        direction: "vertical"
    });
    //큰 슬라이더
    var thumb_swiper = new Swiper(".shop-slier", {
        spaceBetween: 5,
        autoHeight: true,
        effect: 'slide',
        speed: 400,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        thumbs: {
            swiper: swiper,
        },
    });
</script>