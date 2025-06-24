<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/personalpay.css">', 200);
?>
<div class="personalpay-wrap container mt-2">
    <div class="bg-white p-4 border mb-2">
        <h1 class="fs-4 fw-bolder mb-0"><i class="bi bi-person-circle"></i> <?php echo $g5['title'] ?></h1>
    </div>
    <div class="bg-white p-4 border">
        <?php
        for ($i = 1; $row = sql_fetch_array($result); $i++) {
            if ($list_mod >= 2) { // 1줄 이미지 : 2개 이상
                if ($i % $list_mod == 0) $sct_last = ' sct_last'; // 줄 마지막
                else if ($i % $list_mod == 1) $sct_last = ' sct_clear'; // 줄 첫번째
                else $sct_last = '';
            } else { // 1줄 이미지 : 1개
                $sct_last = ' sct_clear';
            }

            if ($i == 1) {
                echo "<div class='row g-2'>\n";
            }

            $href = G5_SHOP_URL . '/personalpayform.php?pp_id=' . $row['pp_id'] . '&amp;page=' . $page;
        ?>
            <div class="col-sm-12 col-md-3">
                <div class="image-wrap text-center"><a href="<?php echo $href; ?>" class="sct_a"><img src="<?php echo G5_SHOP_SKIN_URL; ?>/img/personal.jpg" alt=""></a></div>
                <div class="pay-title text-center"><a href="<?php echo $href; ?>" class="sct_a"><?php echo get_text($row['pp_name']) . '님 개인결제'; ?></a></div>
                <div class="pay-price text-center text-primary"><?php echo display_price($row['pp_price']); ?></div>
            </div>
        <?php } ?>
    </div>
</div>
<?php

if ($i > 1) {
    echo "</div>\n";
}

if ($i == 1) {
    echo "<div class='empty-item p-4 text-center'>등록된 개인결제가 없습니다.</div>\n";
}
