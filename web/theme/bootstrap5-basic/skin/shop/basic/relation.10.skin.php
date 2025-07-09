<?php

/**
 * 관련상품
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/relation.css">', 200);
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/bxslider/dist/jquery.bxslider.min.css">', 200);
add_javascript('<script src="' . BB_ASSETS_URL . '/js/bxslider/dist/jquery.bxslider.min.js"></script>', 10);

?>
<div class="relation-slider-wrap">
    <?php
    for ($i = 1; $row = sql_fetch_array($result); $i++) {

        $item_link_href = shop_item_url($row['it_id']);
        if ($this->list_mod >= 2) { // 1줄 이미지 : 2개 이상
            if ($i % $this->list_mod == 0) $sct_last = ' sct_last'; // 줄 마지막
            else if ($i % $this->list_mod == 1) $sct_last = ' sct_clear'; // 줄 첫번째
            else $sct_last = '';
        } else { // 1줄 이미지 : 1개
            $sct_last = ' sct_clear';
        }

        if ($i == 1) {
            if ($this->css) {
                echo "<ul class='{$this->css}'>\n";
            } else {
                echo "<ul class='relation-items-slider d-flex justify-content-center flex-row'>\n";
            }
        }

        echo "<li class='sct_li slide {$sct_last} d-flex flex-column justify-content-center align-content-center flex-wrap'>\n";

        if ($this->href) {
            echo "<div class='sct_img text-center'><a href='{$item_link_href}' class='sct_a text-center'>\n";
        }

        if ($this->view_it_img) {
            echo get_it_image($row['it_id'], 300, 225, '', '', stripslashes($row['it_name'])) . "\n";
        }

        if ($this->href) {
            echo "</a></div>\n";
        }

        if ($this->view_it_icon) {
            echo "<div class='sct_icon'>" . item_icon($row) . "</div>\n";
        }

        if ($this->view_it_id) {
            echo "<div class='sct_id'>&lt;" . stripslashes($row['it_id']) . "&gt;</div>\n";
        }

        if ($this->href) {
            echo "<div class='sct_txt text-center'><a href='{$item_link_href}' class='sct_a text-secondary fw-bold text-center'>\n";
        }

        if ($this->view_it_name) {
            echo stripslashes($row['it_name']) . "\n";
        }

        if ($this->href) {
            echo "</a></div>\n";
        }

        if ($this->view_it_cust_price || $this->view_it_price) {

            echo "<div class='sct_cost text-center'>\n";

            if ($this->view_it_cust_price && $row['it_cust_price']) {
                echo "<strike>" . display_price($row['it_cust_price']) . "</strike>\n";
            }

            if ($this->view_it_price) {
                echo display_price(get_price($row), $row['it_tel_inq']) . "\n";
            }

            echo "</div>\n";
        }

        if ($this->view_sns) {
            $sns_top = $this->img_height + 10;
            $sns_url  = $item_link_href;
            $sns_title = get_text($row['it_name']) . ' | ' . get_text($config['cf_title']);
            echo "<div class='sct_sns' style='top:{$sns_top}px'>";
            echo get_sns_share_link('facebook', $sns_url, $sns_title, G5_SHOP_SKIN_URL . '/img/sns_fb_s.png');
            echo get_sns_share_link('twitter', $sns_url, $sns_title, G5_SHOP_SKIN_URL . '/img/sns_twt_s.png');
            echo "</div>\n";
        }

        echo "</li>\n";
    }

    if ($i > 1) echo "</ul>\n";

    if ($i == 1) echo "<div class='empty-item p-4 text-center'>등록된 상품이 없습니다.</div>\n";
    ?>
    <!-- } 관련상품 10 끝 -->
    <script>
        $(document).ready(function() {
            $('.relation-items-slider').bxSlider({
                slideWidth: 300,
                minSlides: 1,
                maxSlides: 6,
                slideMargin: 20,
                pager: false,
                responsive: true
            });
        });
    </script>
</div>