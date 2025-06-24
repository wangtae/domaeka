<?php
if (!defined("_GNUBOARD_")) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/box.css">', 200);

?>

<!-- 위시리스트 간략 보기 시작 { -->
<div id="wish" class="side-wish-list">
    <h5 class="today-view-header mb-2 border-bottom p-2">위시리스트 <span><?php echo get_wishlist_datas_count(); ?></span></h5>
    <?php
    $wishlist_datas = get_wishlist_datas($member['mb_id'], true);
    $i = 0;
    foreach ((array) $wishlist_datas as $row) {
        if (!$row['it_id']) continue;

        $item = get_shop_item($row['it_id'], true);

        if (!$item['it_id']) continue;

        echo '<div class="d-flex flex-row mb-4">';
        $it_name = get_text($item['it_name']);

        // 이미지로 할 경우
        $it_img = get_it_image($row['it_id'], 65, 65, true);
        echo '<div class="product-image me-2">' . $it_img . '</div>';
        echo '<div class="product-count">';
        echo '<a href="' . shop_item_url($row['it_id']) . '" class="product-name">' . $it_name . '</a>';
        echo '<div class="product-price">' . display_price(get_price($item), $item['it_tel_inq']) . '</div>';
        echo '</div>' . PHP_EOL;
        echo '</div>';
        $i++;
    }   //end foreach

    if ($i == 0) {
        echo '<div class="empty-item"><div class="text-center m-5 p-5"> 위시리스트가 없습니다.</div></div>' . PHP_EOL;
    } ?>
</div>