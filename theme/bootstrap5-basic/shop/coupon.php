<?php

/**
 * 내 쿠폰
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/coupon.css">', 200);
if ($is_guest) {
    alert_close('회원만 조회하실 수 있습니다.');
}
$g5['title'] = $member['mb_nick'] . ' 님의 쿠폰 내역';
include_once G5_THEME_PATH . '/head.sub.php';

$sql = " SELECT cp_id, cp_subject, cp_method, cp_target, cp_start, cp_end, cp_type, cp_price
            from {$g5['g5_shop_coupon_table']}
            where mb_id IN ( '{$member['mb_id']}', '전체회원' )
              and cp_start <= '" . G5_TIME_YMD . "'
              and cp_end >= '" . G5_TIME_YMD . "'
            order by cp_no ";
$result = sql_query($sql);
?>
<div id="my-coupon" class="new_win coupon-wrap mt-2">
    <div class="coupon-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h2 class="fs-4 fw-bolder mb-0"><i class="bi bi-cash"></i> <?php echo $g5['title'] ?></h2>
        </div>
    </div>
    <div class='container-fluid'>
        <div class='bg-white p-2 p-lg-4 border'>
            <ul class="list-group list-group-flush">
                <?php
                $cp_count = 0;
                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                    if (is_used_coupon($member['mb_id'], $row['cp_id']))
                        continue;

                    if ($row['cp_method'] == 1) {
                        $sql = " select ca_name from {$g5['g5_shop_category_table']} where ca_id = '{$row['cp_target']}' ";
                        $ca = sql_fetch($sql);
                        $cp_target = $ca['ca_name'] . '의 상품할인';
                    } else if ($row['cp_method'] == 2) {
                        $cp_target = '결제금액 할인';
                    } else if ($row['cp_method'] == 3) {
                        $cp_target = '배송비 할인';
                    } else {
                        $it = get_shop_item($row['cp_target'], true);
                        $cp_target = $it['it_name'] . ' 상품할인';
                    }

                    if ($row['cp_type'])
                        $cp_price = $row['cp_price'] . '%';
                    else
                        $cp_price = number_format($row['cp_price']) . '원';

                    $cp_count++;
                ?>
                    <li class="list-group-item">
                        <div class="cou_top">
                            <span class="cou_tit"><?php echo $row['cp_subject']; ?></span>
                            <span class="text-primary"><?php echo $cp_price; ?></span>
                        </div>
                        <div class="text-end">
                            <span class="text-secondary"><?php echo $cp_target; ?> <i class="fa fa-angle-right" aria-hidden="true"></i></span>
                            <span class="text-secondary"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo substr($row['cp_start'], 2, 8); ?> ~ <?php echo substr($row['cp_end'], 2, 8); ?></span>
                        </div>
                    </li>
                <?php
                }

                if (!$cp_count) {
                    echo '<li class="empty_li">사용할 수 있는 쿠폰이 없습니다.</li>';
                }
                ?>
            </ul>

            <?php if ($is_member && (defined('BB_PROFILE_POPUP') && BB_PROFILE_POPUP == true)) { ?>
                <div class='d-flex justify-content-center p-5'>
                    <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close">창닫기</button>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php
include_once G5_THEME_PATH . '/tail.sub.php';
