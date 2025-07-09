<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$navi_datas = $ca_ids = array();
$is_item_view = (isset($it_id) && isset($it) && isset($it['it_id']) && $it_id === $it['it_id']) ? true : false;

if (!$is_item_view && $ca_id) {
    $navi_datas = get_shop_navigation_data(true, $ca_id);
    $ca_ids = array(
        'ca_id' => substr($ca_id, 0, 2),
        'ca_id2' => substr($ca_id, 0, 4),
        'ca_id3' => substr($ca_id, 0, 6),
    );
} else if ($is_item_view && isset($it) && is_array($it)) {
    $navi_datas = get_shop_navigation_data(true, $it['ca_id']);
    $ca_ids = array(
        'ca_id' => substr($it['ca_id'], 0, 2),
        'ca_id2' => substr($it['ca_id'], 0, 4),
        'ca_id3' => substr($it['ca_id'], 0, 6)
    );
}

$location_class = array();
if ($is_item_view) {
    $location_class[] = 'view_location';    // view_location는 리스트 말고 상품보기에서만 표시
} else {
    $location_class[] = 'is_list is_right';    // view_location는 리스트 말고 상품보기에서만 표시
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/navigation.css">', 200);
add_javascript('<script src="' . G5_JS_URL . '/shop.category.navigation.js"></script>', 10);
?>
<div class="container view-location mt-2 d-none d-md-block">
    <div class="border bg-white py-2">
        <div id="sct_location" class="p-2 <?php echo implode(' ', $location_class); ?> d-flex"> <!-- class="view_location" --> <!-- view_location는 리스트 말고 상품보기에서만 표시 -->
            <a href='<?php echo G5_SHOP_URL; ?>/' class="go_home"><span class="visually-hidden">HOME</span><i class="bi bi-house-fill"></i></a>
            <i class="bi bi-chevron-right"></i>
            <?php if (is_array($navi_datas) && $navi_datas) { ?>

                <?php if (isset($navi_datas[0]) && $navi_datas[0]) { ?>
                    <select class="shop_hover_selectbox category1 align-content-center">
                        <?php foreach ((array) $navi_datas[0] as $data) { ?>
                            <option value="<?php echo $data['ca_id']; ?>" data-url="<?php echo $data['url']; ?>" <?php if ($ca_ids['ca_id'] === $data['ca_id']) echo 'selected'; ?>><?php echo $data['ca_name']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                <?php if (isset($navi_datas[1]) && $navi_datas[1]) { ?>
                    <i class="bi bi-chevron-right"></i>
                    <select class="shop_hover_selectbox category2 align-content-center">
                        <?php foreach ((array) $navi_datas[1] as $data) { ?>
                            <option value="<?php echo $data['ca_id']; ?>" data-url="<?php echo $data['url']; ?>" <?php if ($ca_ids['ca_id2'] === $data['ca_id']) echo 'selected'; ?>><?php echo $data['ca_name']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                <?php if (isset($navi_datas[2]) && $navi_datas[2]) { ?>
                    <i class="bi bi-chevron-right"></i>
                    <select class="shop_hover_selectbox category3 align-content-center">
                        <?php foreach ((array) $navi_datas[2] as $data) { ?>
                            <option value="<?php echo $data['ca_id']; ?>" data-url="<?php echo $data['url']; ?>" <?php if ($ca_ids['ca_id3'] === $data['ca_id']) echo 'selected'; ?>><?php echo $data['ca_name']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            <?php } else { ?>
                <?php echo get_text($g5['title']); ?>
            <?php } ?>
        </div>
        <script>
            jQuery(function($) {
                $(document).ready(function() {
                    $("#sct_location select").on("change", function(e) {
                        var url = $(this).find(':selected').attr("data-url");

                        if (typeof itemlist_ca_id != "undefined" && itemlist_ca_id === this.value) {
                            return false;
                        }

                        window.location.href = url;
                    });

                    $("select.shop_hover_selectbox").shop_select_to_html();
                });
            });
        </script>
    </div>
</div>