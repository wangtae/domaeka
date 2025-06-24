<?php

/**
 * main.003.skin.php
 * 최신상품
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/main.skin.css">', 210);

?>
<div class='main003-new-wrap main-common'>
<?php
    $i = 0;
    $common_css = "row g-2 row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4";
    foreach ((array) $list as $row) {

        if (empty($row)) {
            continue;
        }
        $i++;

        $item_link_href = shop_item_url($row['it_id']);
        $star_score = $row['it_use_avg'] ? (int) get_star($row['it_use_avg']) : '';
        $is_soldout = is_soldout($row['it_id'], true);   // 품절인지 체크


        if ($i == 1) {
            echo "<div class='{$common_css}' data-value='" . $this->list_mod . "'>" . PHP_EOL;
        }

        echo "<div class='col'>";
        echo "<div class='border p-2 inner-wrap h-100'>";
        echo "<div class='image-wrap position-relative mb-2'>" . PHP_EOL;
        // 품절
        if ($is_soldout) {
            echo '<div class="position-absolute text-center d-flex w-100 h-100 align-content-center justify-content-center soldout-text flex-wrap fs-4 fw-bolder ff-noto text-light"> SOLD OUT </div>';
        }
        //it_id
        if ($this->view_it_id) {
            echo "<div class='item-it_id position-absolute'>[" . stripslashes($row['it_id']) . "]</div>" . PHP_EOL;
        }
        // 사용후기 평점표시
        if ($this->view_star && $star_score) {
            echo "<div class='item-star position-absolute'><span class='visually-hidden'>고객평점</span><img src=" . G5_SHOP_URL . "/img/s_star" . $star_score . ".png alt='별점' " . $star_score . "'점 class='img-fluid'></div>\n";
        }
        if ($this->view_it_icon) {
            echo "<div class='item-icons-wrap position-absolute'>" . item_icon($row) . "</div>" . PHP_EOL;
        }
        if ($this->href) {
            echo "<a href='{$item_link_href}'>" . PHP_EOL;
        }

        if ($this->view_it_img) {
            echo get_it_image($row['it_id'], $this->img_width, $this->img_height, '', 'img-fluid', stripslashes($row['it_name'])) . "" . PHP_EOL;
        }

        if ($this->href) {
            echo "</a>" . PHP_EOL;
        }

        echo "</div>" . PHP_EOL;



        if ($this->href) {
            echo "<div class='item-title text-truncate'><a href='{$item_link_href}'>" . PHP_EOL;
        }

        if ($this->view_it_name) {
            echo stripslashes($row['it_name']) . PHP_EOL;
        }

        if ($this->href) {
            echo "</a></div>" . PHP_EOL;
        }
        if ($this->view_it_basic) {
            echo "<div class='item-basic'>";
            echo stripslashes($row['it_basic']) . PHP_EOL;
            echo "</div>";
        }

        if ($this->view_it_cust_price || $this->view_it_price) {


            if ($this->view_it_cust_price && $row['it_cust_price']) {
                //할인률 계산
                $dc = 100 - floor((get_price($row) / $row['it_cust_price']) * 100);
                echo "<div class='discount-price text-center text-secondary'><span class='dc-price-percent'>{$dc}%</span> <span class='text-decoration-line-through'>" . display_price($row['it_cust_price']) . "</span></div>" . PHP_EOL;
            }
            if ($this->view_it_price) {
                echo "<div class='item-price text-center'>" . PHP_EOL;
                echo display_price(get_price($row), $row['it_tel_inq']) . "" . PHP_EOL;
                echo "</div>" . PHP_EOL;
            }
        }

        echo "</div>" . PHP_EOL;
        echo "</div>" . PHP_EOL;
    }

    if ($i >= 1) {
        echo "</div>" . PHP_EOL;
    }

    if ($i == 0) {
        echo "<div class='sct_noitem'>등록된 상품이 없습니다.</div>" . PHP_EOL;
    }
    ?>
</div>