<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$sct_sort_href = $_SERVER['SCRIPT_NAME'] . '?';

if ($ca_id) {
    $shop_category_url = shop_category_url($ca_id);
    $sct_sort_href = (strpos($shop_category_url, '?') === false) ? $shop_category_url . '?1=1' : $shop_category_url;
} else if ($ev_id) {
    $sct_sort_href .= 'ev_id=' . $ev_id;
}

if ($skin) {
    $sct_sort_href .= '&amp;skin=' . $skin;
}
$sct_sort_href .= '&amp;sort=';

?>

<div class="item-sort-wrap mt-2">
    <h2 class="visually-hidden">상품 정렬</h2>
    <div class="container">
        <ul class="list-group list-group-horizontal justify-content-end">
            <li class="list-group-item text-center d-flex <?php echo $sort == 'it_sum_qty' ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_sum_qty&amp;sortodr=desc">판매 <i class="bi bi-arrow-up-circle"></i></a></li>
            <li class="list-group-item text-center d-flex <?php echo ($sort == 'it_price' && $sortodr == 'asc') ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_price&amp;sortodr=asc">가격 <i class="bi bi-arrow-up-circle"></i></a></li>
            <li class="list-group-item text-center d-flex <?php echo ($sort == 'it_price' && $sortodr == 'desc')  ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_price&amp;sortodr=desc">가격 <i class="bi bi-arrow-down-circle"></i></a></li>
            <li class="list-group-item text-center d-flex <?php echo $sort == 'it_use_avg' ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_use_avg&amp;sortodr=desc">평점 <i class="bi bi-arrow-up-circle"></i></a></li>
            <li class="list-group-item text-center d-flex <?php echo $sort == 'it_use_cnt' ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_use_cnt&amp;sortodr=desc">후기 <i class="bi bi-arrow-up-circle"></i></a></li>
            <li class="list-group-item text-center d-flex <?php echo $sort == 'it_update_time' ? 'active' : ''; ?>"><a href="<?php echo $sct_sort_href; ?>it_update_time&amp;sortodr=desc">최신<span class="d-none d-md-inline">등록순</span></a></li>
        </ul>
    </div>
</div>