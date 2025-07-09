<?php

/**
 * 상품 스킨 10
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_javascript('<script src="' . G5_THEME_JS_URL . '/theme.shop.list.js"></script>', 100);
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/list10skin.css">', 210);

?>
<div class="container">
    <div class="bg-white border p-3 p-lg-4 mt-2">
        <h1 class="fs-4 fw-bolder ff-noto mb-0"><i class="bi bi-list-task"></i> <?php echo $g5['title'] ?></h1>
    </div>
</div>
<div class="container list10-wrap mb-5">
    <div class="bg-white border p-2 p-lg-4 mt-2">

        <?php
        $i = 0;

        $this->view_star = (method_exists($this, 'view_star')) ? $this->view_star : true;

        foreach ((array) $list as $row) {
            if (empty($row)) continue;

            $item_link_href = shop_item_url($row['it_id']);     // 상품링크
            $star_score = $row['it_use_avg'] ? (int) get_star($row['it_use_avg']) : '';     //사용자후기 평균별점
            $list_mod = $this->list_mod;    // 분류관리에서 1줄당 이미지 수 값 또는 파일에서 지정한 가로 수
            $is_soldout = is_soldout($row['it_id'], true);   // 품절인지 체크

            $classes = array();

            $classes[] = 'col-row-' . $list_mod;

            if ($i && ($i % $list_mod == 0)) {
                $classes[] = 'row-clear';
            }

            $i++;   // 변수 i 를 증가

            if ($i === 1) {
                if ($this->css) {
                    echo "<div class='{$this->css} list10-wrap row g-4 row-cols-1 row-cols-md-3 row-cols-lg-4 row-cols-xl-5'>" . PHP_EOL;
                } else {
                    echo "<div class='sct sct_10 lists-row list10-wrap row g-4 row-cols-1 row-cols-md-3 row-cols-lg-4 row-cols-xl-5'>" . PHP_EOL;
                }
            }
            echo "<div class='col border-bottom pb-5 pt-5'>";
            echo "<div class='list-item position-relative p-2 " . implode(' ', $classes) . "' data-css='nocss'>" . PHP_EOL;
            echo "<div class='img-wrap position-relative pt-4 mb-4 text-center'>" . PHP_EOL;

            if ($this->href) {
                echo "<a href='{$item_link_href}' class='d-block'>" . PHP_EOL;
            }

            if ($this->view_it_img) {
                echo get_it_image($row['it_id'], $this->img_width, $this->img_height, '', 'img-fluid', stripslashes($row['it_name'])) . "" . PHP_EOL;
            }
            if ($this->href) {
                echo "</a>" . PHP_EOL;
            }
            // 품절출력
            if ($is_soldout) {
                echo '<div class="position-absolute text-center d-flex w-100 h-100 align-content-center justify-content-center soldout-text flex-wrap fs-4 fw-bolder ff-noto text-light"> SOLD OUT </div>';
            }
            if (!$is_soldout) {
                //장바구니 버튼 출력
                echo "<div class='sct_btn list-10-btn position-absolute add-cart-wrap w-100'>";
                echo "<button type='button' class='w-100 btn btn-outline-primary border-0 btn_cart sct_cart add-cart-button' data-it_id='{$row['it_id']}'><i class='fa fa-shopping-cart' aria-hidden='true'></i> <span>장바구니</span></button>" . PHP_EOL;
                echo "</div>" . PHP_EOL;
            }

            echo "<div class='cart-layer position-absolute top-0'></div>" . PHP_EOL;


            echo "</div>" . PHP_EOL;

            echo "<div class='sct_ct_wrap list-item-info pb-2'>" . PHP_EOL;



            if ($this->view_it_id) {
                echo "<div class='sct_id'>&lt;" . stripslashes($row['it_id']) . "&gt;</div>" . PHP_EOL;
            }

            if ($this->href) {
                echo "<div class='sct_txt item-title'><a href='{$item_link_href}' class='ff-noto'>" . PHP_EOL;
            }

            if ($this->view_it_name) {
                echo stripslashes($row['it_name']) . "" . PHP_EOL;
            }

            if ($this->href) {
                echo "</a></div>" . PHP_EOL;
            }
            /*
            if ($this->view_it_basic && $row['it_basic']) {
                echo "<div class='sct_basic text-secondary'>" . stripslashes($row['it_basic']) . "</div>" . PHP_EOL;
            }*/
            echo "<div class='item-price fs-5 fw-bolder ff-noto text-primary'>" . PHP_EOL;

            if ($this->view_it_cust_price || $this->view_it_price) {

                echo "<div class='sct_cost'>" . PHP_EOL;
                if ($this->view_it_price) {
                    echo display_price(get_price($row), $row['it_tel_inq']) . "" . PHP_EOL;
                }
                if ($this->view_it_cust_price && $row['it_cust_price']) {
                    echo "<span class='sct_dict d-none'>" . display_price($row['it_cust_price']) . "</span>" . PHP_EOL;
                }
                echo "</div>" . PHP_EOL;
            }

            echo "</div>";
            // 사용후기 평점표시
            if ($this->view_star && $star_score) {
                echo "<div class='star-wrap'><span class='visually-hidden'>고객평점</span><img src='" . G5_SHOP_URL . "/img/s_star" . $star_score . ".png' alt='별점 " . $star_score . "점' class='sit_star'></div>" . PHP_EOL;
            }
            //아이콘
            if ($this->view_it_icon) {
                echo "<div class='item-icons-wrap'>" . item_icon($row) . "</div>" . PHP_EOL;
            }

            echo "</div>" . PHP_EOL;
            echo "</div>" . PHP_EOL;
            echo "</div>" . PHP_EOL;
        }   //end foreach

        if ($i >= 1) {
            echo "</div>" . PHP_EOL;
        }

        if ($i === 0) {
            echo "<div class='empty-item text-center p-4'>등록된 상품이 없습니다.</div>" . PHP_EOL;
        }
        ?>
    </div>
</div>