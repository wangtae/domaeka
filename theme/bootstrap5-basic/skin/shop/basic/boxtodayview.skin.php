<?php

/**
 * 오늘본 상품
 */
if (!defined("_GNUBOARD_")) {
    exit;
}

$tv_datas = get_view_today_items(true);

$tv_div['top'] = 0;
$tv_div['img_width'] = 120;
$tv_div['img_height'] = 80;
$tv_div['img_length'] = 5; // 한번에 보여줄 이미지 수

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/box.css">', 200);
?>
<div class='box-today-view-wrap mb-5'>
    <h5 class="today-view-header mb-2 border-bottom p-2" id="today-view-one">
        오늘 본 상품 &nbsp; <span class="badge text-bg-secondary"><?php echo get_view_today_items_count(); ?><span>
    </h5>
    <?php if ($tv_datas) { ?>
        <?php
        $tv_tot_count = 0;
        $k = 0;
        $i = 1;
        foreach ($tv_datas as $rowx) {
            if (!$rowx['it_id']) {
                continue;
            }

            $tv_it_id = $rowx['it_id'];

            if ($tv_tot_count % $tv_div['img_length'] == 0) {
                $k++;
            }

            $it_name = get_text($rowx['it_name']);
            $img = get_it_image($tv_it_id, $tv_div['img_width'], $tv_div['img_height'], $tv_it_id, '', $it_name, true);
            $it_price = get_price($rowx);
            $print_price = is_int($it_price) ? number_format($it_price) : $it_price;
        ?>
            <div class="d-flex flex-row mb-4">
                <div class="product-image me-2"><?php echo $img; ?></div>
                <div class="d-flex flex-column">
                    <span class="product-name text-primary py-1"><?php echo cut_str($it_name, 10, '') . PHP_EOL; ?></span>
                    <span class="product-cost"><?php echo $print_price . PHP_EOL; ?></span>
                </div>
            </div>
        <?php
            $tv_tot_count++;
            $i++;
        }
    } else { ?>

        <div class="d-flex align-content-center flex-column mb-4">
            <div class="epmty-item p-5 fs-4 text-center">오늘본 상품이 없습니다.</div>
        </div>

    <?php } ?>

</div>