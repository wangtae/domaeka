<?php

/**
 * 쇼핑몰 메인 슬라이더
 * 큰 슬라이더는 width는 1296px, height는 동일하게 하면 됩니다.
 * 썸네일은 160x60 입니다. 
 * 슬라이더 개수는 6개가 기본입니다. 
 * 슬라이더가 6개면 썸네일도 6개 등록하세요.
 */

if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/slider.css">', 200);
?>
<!-- OWL Slider -->
<div class="container shop-index-owl-wrap mt-2">
    <div class="bg-white position-relative">
        <!-- 썸네일 이미지 네비게이션, 사용하지 않는다면 d-none 을 class에 추가한다. -->
        <div class="position-absolute thumb-wrapper bg-whiten visually-hidden">
            <div class="thumb-shop-slier shadow">
                <div class="thumb-wrap">
                    <div class="thumb-link">
                        <a href='#item-0' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_1m.png" /></a>
                    </div>
                    <div class="thumb-link">
                        <a href='#item-1' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_2m.png" /></a>
                    </div>
                    <div class="thumb-link">
                        <a href='#item-2' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_3m.png" /></a>
                    </div>
                    <div class="thumb-link">
                        <a href='#item-3' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_4m.png" /></a>
                    </div>
                    <div class="thumb-link">
                        <a href='#item-4' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_5m.png" /></a>
                    </div>
                    <div class="thumb-link">
                        <a href='#item-5' class='thumb-link-item'><img src="<?php echo BB_ASSETS_URL ?>/image/slide_6m.png" /></a>
                    </div>
                </div>
            </div>
        </div>
        <!-- 메인슬라이더 -->
        <div class='carousel-counter position-absolute'></div>
        <div class="owl-carousel owl-shopslider owl-theme owl-loaded">
            <div class="item" data-hash='item-0'>
                <a href='/shop/' class="d-block">
                    <!-- 테블릿 이상 이미지 -->
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_1.png" class="d-none d-md-block owl-lazy" />
                    <!-- 스마트폰 이미지 -->
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_1.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
            <div class="item" data-hash='item-1'>
                <a href='/shop/' class="d-block">
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_2.png" class="d-none d-md-block owl-lazy" />
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_2.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
            <div class="item" data-hash='item-2'>
                <a href='/shop/' class="d-block">
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_3.png" class="d-none d-md-block owl-lazy" />
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_3.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
            <div class="item" data-hash='item-3'>
                <a href='/shop/' class="d-block">
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_4.png" class="d-none d-md-block owl-lazy" />
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_4.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
            <div class="item" data-hash='item-4'>
                <a href='/shop/' class="d-block">
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_5.png" class="d-none d-md-block owl-lazy" />
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_5.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
            <div class="item" data-hash='item-5'>
                <a href='/shop/' class="d-block">
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/slide_6.png" class="d-none d-md-block owl-lazy" />
                    <img data-src="<?php echo BB_ASSETS_URL ?>/image/mobile_slide_6.png" class="d-block d-md-none owl-lazy" />
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var owl = $('.owl-shopslider');
        owl.owlCarousel({
            items: 1,
            loop: false,
            nav: false,
            stagePadding: 0,
            margin: 10,
            URLhashListener: true,
            startPosition: 'URLHash',
            callbacks: true,
            onChanged: callback,
            autoplayHoverPause: true,
            dots: false,
            autoplay: true,
            autoHeight: true,
            autoplayTimeout: 10000,
            lazyLoad: true,
            onInitialized: counter, //When the plugin has initialized.
            onTranslated: counter //When the translation of the stage has finished.
        });

        owl.on('changed.owl.carousel', function(event) {
            //console.log(owl.index);
        });

        function counter(event) {
            var element = event.target;
            var items = event.item.count;
            var item = event.item.index + 1; // Position of the current item
            // it loop is true then reset counter from 1
            if (item > items) {
                item = item - items
            }
            $('.carousel-counter').html(item + "/" + items);
        }

        function callback(event) {
            // Provided by the core
            var element = event.target; // DOM element, in this example .owl-carousel
            var name = event.type; // Name of the event, in this example dragged
            var namespace = event.namespace; // Namespace of the event, in this example owl.carousel
            var items = event.item.count; // Number of items
            var item = event.item.index; // Position of the current item
            // Provided by the navigation plugin
            var pages = event.page.count; // Number of pages
            var page = event.page.index; // Position of the current page
            var size = event.page.size; // Number of items per page
            var currentData = "item-" + item;
            $('.thumb-link-item').removeClass('opacity-100');
            $('[href=#' + currentData + ']').addClass('opacity-100');
        }
        setTimeout(function() {
            $('.thumb-wrapper').removeClass('visually-hidden');
        }, 1000);
    });
</script>