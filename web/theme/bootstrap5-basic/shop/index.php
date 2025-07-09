<?php

/**
 * 쇼핑몰 메인 페이지
 * shop/index.php
 */
include_once('./_common.php');

if (G5_IS_MOBILE) {
    include_once G5_THEME_MSHOP_PATH . '/index.php';
    return;
}

if (!defined('_INDEX_')) {
    define('_INDEX_', TRUE);
}

include_once G5_THEME_SHOP_PATH . '/shop.head.php';

####################
##  메인 슬라이더 ##
####################
include_once G5_THEME_SHOP_PATH . "/_slider-owl.php";
?>
<div class="container">
    <div class="bg-white p-2 p-lg-4 border mt-2">
        <h1 class="visually-hidden">쇼핑 INDEX</h1>
        <?php if ($default['de_type4_list_use']) { ?>
            <div class="d-flex mb-5">

                <!-- 인기상품 시작 { -->
                <section id="side_pd">
                    <h2 class='fs-5 fw-bolder ff-noto mb-4'><a href="<?php echo shop_type_url('4'); ?>"><i class="bi bi-heart-fill"></i> 인기상품</a><span class="text-secondary fs-6 fw-light"> 쇼핑몰에서 엄선한 가장 HOT한 상품!</span></h2>
                    <?php
                    $list = new item_list();
                    $list->set_type(4);
                    $list->set_view('it_id', false);
                    $list->set_view('it_name', true);
                    $list->set_view('it_basic', false);
                    $list->set_view('it_cust_price', true);
                    $list->set_view('it_price', true);
                    $list->set_view('it_icon', true);
                    $list->set_view('star', true);
                    echo $list->run();
                    ?>
                </section>
            </div>
        <?php } ?>
        <?php if ($default['de_type1_list_use']) { ?>
            <div class="d-flex mb-5">
                <!-- 히트상품 시작 { -->
                <section id="idx_hit" class="items-wrap">
                    <h2 class='fs-5 fw-bolder ff-noto mb-4'><a href="<?php echo shop_type_url('1'); ?>"><i class="bi bi-heart-fill"></i> 히트상품</a><span class="text-secondary fs-6 fw-light"> 쇼핑몰에서 히트한 상품!</span></h2>
                    <?php
                    $list = new item_list();
                    $list->set_type(1);
                    $list->set_view('it_img', true);
                    $list->set_view('it_id', false);
                    $list->set_view('it_name', true);
                    $list->set_view('it_basic', true);
                    $list->set_view('it_cust_price', true);
                    $list->set_view('it_price', true);
                    $list->set_view('it_icon', true);
                    $list->set_view('sns', true);
                    $list->set_view('star', true);
                    echo $list->run();
                    ?>
                </section>
            </div>
            <!-- } 히트상품 끝 -->
        <?php } ?>
        <?php if ($default['de_type3_list_use']) { ?>
            <div class="d-flex mb-5">
                <!-- 최신상품 시작 { -->
                <section class="items-wrap">
                    <h2 class='fs-5 fw-bolder ff-noto mb-4'><a href="<?php echo shop_type_url('3'); ?>"><i class="bi bi-heart-fill"></i> 최신상품</a><span class="text-secondary fs-6 fw-light"> 새로 등록된 상품</span></h2>
                    <?php
                    $list = new item_list();
                    $list->set_type(3);
                    $list->set_view('it_id', false);
                    $list->set_view('it_name', true);
                    $list->set_view('it_basic', true);
                    $list->set_view('it_cust_price', true);
                    $list->set_view('it_price', true);
                    $list->set_view('it_icon', true);
                    $list->set_view('sns', true);
                    $list->set_view('star', true);
                    echo $list->run();
                    ?>
                </section>
                <!-- } 최신상품 끝 -->
            </div>
        <?php } ?>
        <?php if ($default['de_type2_list_use']) { ?>
            <div class="d-flex mb-5">

                <!-- 추천상품 시작 { -->
                <section class="items-wrap">
                    <h2 class='fs-5 fw-bolder ff-noto mb-4'><a href="<?php echo shop_type_url('2'); ?>"><i class="bi bi-heart-fill"></i> 추천상품</a><span class="text-secondary fs-6 fw-light"> MD 추천 상품</span></h2>
                    <?php
                    $list = new item_list();
                    $list->set_type(2);
                    $list->set_view('it_id', false);
                    $list->set_view('it_name', true);
                    $list->set_view('it_basic', true);
                    $list->set_view('it_cust_price', true);
                    $list->set_view('it_price', true);
                    $list->set_view('it_icon', true);
                    $list->set_view('sns', true);
                    $list->set_view('star', true);
                    echo $list->run();
                    ?>
                </section>
            </div>

        <?php } ?>

        <?php if ($default['de_type5_list_use']) { ?>
            <div class="d-flex mb-5">
                <!-- 할인상품 시작 { -->
                <section class="items-wrap">
                    <h2 class='fs-5 fw-bolder ff-noto mb-4'><a href="<?php echo shop_type_url('5'); ?>"><i class="bi bi-heart-fill"></i> 할인상품</a><span class="text-secondary fs-6 fw-light"> 할인된 가격으로 구매하세요!</span></h2>

                    <?php
                    $list = new item_list();
                    $list->set_type(5);
                    $list->set_view('it_id', false);
                    $list->set_view('it_name', true);
                    $list->set_view('it_basic', true);
                    $list->set_view('it_cust_price', true);
                    $list->set_view('it_price', true);
                    $list->set_view('it_icon', true);
                    $list->set_view('sns', true);
                    $list->set_view('star', true);
                    echo $list->run();
                    ?>
                </section>
            </div>
            <!-- } 할인상품 끝 -->
        <?php } ?>
    </div>
</div>
</div>
<?php
include_once(G5_THEME_SHOP_PATH . '/shop.tail.php');
