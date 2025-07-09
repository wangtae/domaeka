<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/couponzone.css">', 200);

?>
<div class="couponzone-wrap container mt-2">
    <div class="coupon-header bg-white border p-3 p-lg-4 mb-2">
        <h2 class="fs-4 fw-bolder"><?php echo $g5['title'] ?></h2>
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> <?php echo $default['de_admin_company_name']; ?> 회원이시라면 쿠폰 다운로드 후 바로 사용하실 수 있습니다.</div>
    </div>
    <section class="download-coupon mb-5">
        <div class="coupon-content bg-white border p-2 p-lg-4">
            <h3 class="fs-5 fw-bolder">다운로드 쿠폰</h3>
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">

                <?php
                $sql = " SELECT * $sql_common and cz_type = '0' $sql_order ";
                $result = sql_query($sql);

                $coupon = '';
                $coupon_info_class = '';

                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                    $coupon .= '<div class="col">';
                    if (!$row['cz_file']) {
                        continue;
                    }

                    $img_file = G5_DATA_PATH . '/coupon/' . $row['cz_file'];
                    if (!is_file($img_file)) {
                        continue;
                    }

                    $subj = get_text($row['cz_subject']);

                    switch ($row['cp_method']) {
                        case '0':
                            $row3 = get_shop_item($row['cp_target'], true);
                            $cp_target = '개별상품할인';
                            $cp_link = '<a href="' . shop_item_url($row3['it_id']) . '" target="_blank">' . get_text($row3['it_name']) . '</a>';
                            $coupon_info_class = 'cp_2';
                            break;
                        case '1':
                            $sql3 = " SELECT ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id = '{$row['cp_target']}' ";
                            $row3 = sql_fetch($sql3);
                            $cp_target = '카테고리할인';
                            $cp_link = '<a href="' . shop_category_url($row3['ca_id']) . '" target="_blank">' . get_text($row3['ca_name']) . '</a>';
                            $coupon_info_class = 'cp_1';
                            break;
                        case '2':
                            $cp_link = $cp_target = '주문금액할인';
                            $coupon_info_class = 'cp_3';
                            break;
                        case '3':
                            $cp_link = $cp_target = '배송비할인';
                            $coupon_info_class = 'cp_4';
                            break;
                    }

                    // 다운로드 쿠폰인지
                    $disabled = '';
                    if (is_coupon_downloaded($member['mb_id'], $row['cz_id'])) {
                        $disabled = ' disabled';
                    }

                    // $row['cp_type'] 값이 있으면 % 이며 없으면 원 입니다.
                    $print_cp_price = $row['cp_type'] ? '<b>' . $row['cp_price'] . '</b> %' : '<b>' . number_format($row['cp_price']) . '</b> 원';

                    $coupon .= '<div class="card">' . PHP_EOL;
                    $coupon .= '<div class="position-relative">' . PHP_EOL;
                    $coupon .= '<img src="' . str_replace(G5_PATH, G5_URL, $img_file) . '" alt="' . $subj . '" class="coupon-image img-fluid card-img-top">';
                    $coupon .= '<div class="position-absolute text-truncate coupon-title">' . $subj . '<br><span class="text-primary">' . $print_cp_price . '</span></div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;

                    $coupon .= '<div class="card-body">' . PHP_EOL;
                    $coupon .= '<div class="cp_cnt">' . PHP_EOL;
                    $coupon .= '<div class="coupon-target">' . PHP_EOL;
                    $coupon .= '<div class="coupon-info mt-1"><h4 class="card-title fs-5 fw-bolder">' . $cp_target . '</h4><ul class="list-group list-group-flush"><li class="list-group-item">적용 : ' . $cp_link . '</li>';

                    if ($row['cp_minimum']) {   // 쿠폰에 최소주문금액이 있다면
                        $coupon .= '<li class="list-group-item">최소주문금액 : <span class="cp_evt"><b>' . number_format($row['cp_minimum']) . '</b>원</span></li>';
                    }
                    $coupon .= '<li class="list-group-item coupon_date"><span class="limit">기한 : </span>다운로드 후 ' . number_format($row['cz_period']) . '일</li>' . PHP_EOL;

                    $coupon .= '</ul></div><!--//.coupon-info-->' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    //cp_1 카테고리할인
                    //cp_2 개별상품할인
                    //cp_3 주문금액할인
                    //cp_4 배송비할인
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '<div class="card-footer text-center"><button type="button" class="coupon_download btn btn-primary' . $disabled . '" ' . $disabled . '  data-cid="' . $row['cz_id'] . '">쿠폰 다운로드</button></div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '</div><!--//.col-->';
                }

                if ($coupon) {
                    echo PHP_EOL . $coupon . PHP_EOL;
                } else {
                    echo '<div class="empty-item p-4 text-center">사용할 수 있는 쿠폰이 없습니다.</div>';
                }
                ?>
            </div>

        </div>
    </section>

    <section class="couponzone_list" id="point_coupon">
        <div class="coupon-content bg-white border p-2 p-lg-4">

            <h2 class="fs-4 fw-bolder">포인트 쿠폰</h2>
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">

                <?php
                $sql = " select * $sql_common and cz_type = '1' $sql_order ";
                $result = sql_query($sql);

                $coupon = '';
                $coupon_info_class = '';

                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                    $coupon .= '<div class="col">';
                    if (!$row['cz_file']) {
                        continue;
                    }
                    $img_file = G5_DATA_PATH . '/coupon/' . $row['cz_file'];
                    if (!is_file($img_file))
                        continue;

                    $subj = get_text($row['cz_subject']);

                    switch ($row['cp_method']) {
                        case '0':
                            $row3 = get_shop_item($row['cp_target'], true);
                            $cp_link = '<a href="' . shop_item_url($row3['it_id']) . '" target="_blank">' . get_text($row3['it_name']) . '</a>';
                            $cp_target = '개별상품할인';
                            $coupon_info_class = 'cp_2';
                            break;
                        case '1':
                            $sql3 = " select ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id = '{$row['cp_target']}' ";
                            $row3 = sql_fetch($sql3);
                            $cp_link = '<a href="' . shop_category_url($row3['ca_id']) . '" target="_blank">' . get_text($row3['ca_name']) . '</a>';
                            $cp_target = '카테고리할인';
                            $coupon_info_class = 'cp_1';
                            break;
                        case '2':
                            $cp_link = $cp_target = '주문금액할인';
                            $coupon_info_class = 'cp_3';
                            break;
                        case '3':
                            $cp_link = $cp_target = '배송비할인';
                            $coupon_info_class = 'cp_4';
                            break;
                    }

                    // 다운로드 쿠폰인지
                    $disabled = '';
                    if (is_coupon_downloaded($member['mb_id'], $row['cz_id'])) {
                        $disabled = ' disabled';
                    }

                    // $row['cp_type'] 값이 있으면 % 이며 없으면 원 입니다.
                    $print_cp_price = $row['cp_type'] ? '<b>' . $row['cp_price'] . '</b> %' : '<b>' . number_format($row['cp_price']) . '</b> 원';

                    $coupon .= '<div class="card">' . PHP_EOL;
                    $coupon .= '<div class="position-relative">' . PHP_EOL;
                    $coupon .= '<img src="' . str_replace(G5_PATH, G5_URL, $img_file) . '" alt="' . $subj . '" class="coupon-image img-fluid card-img-top">';
                    $coupon .= '<div class="position-absolute text-truncate coupon-title">' . $subj . '<br><span class="text-primary">' . $print_cp_price . '</span></div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;

                    $coupon .= '<div class="card-body">' . PHP_EOL;
                    $coupon .= '<div class="cp_cnt">' . PHP_EOL;
                    $coupon .= '<div class="coupon-target">' . PHP_EOL;
                    $coupon .= '<div class="coupon-info mt-1"><h4 class="card-title fs-5 fw-bolder">' . $cp_target . '</h4><ul class="list-group list-group-flush"><li class="list-group-item">적용 : ' . $cp_link . '</li>';

                    if ($row['cp_minimum']) {   // 쿠폰에 최소주문금액이 있다면
                        $coupon .= '<li class="list-group-item">최소주문금액 : <span class="cp_evt"><b>' . number_format($row['cp_minimum']) . '</b>원</span></li>';
                    }
                    $coupon .= '<li class="list-group-item coupon_date"><span class="limit">기한 : </span>다운로드 후 ' . number_format($row['cz_period']) . '일</li>' . PHP_EOL;

                    $coupon .= '</ul></div><!--//.coupon-info-->' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    //cp_1 카테고리할인
                    //cp_2 개별상품할인
                    //cp_3 주문금액할인
                    //cp_4 배송비할인
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '<div class="card-footer text-center"><button type="button" class="coupon_download btn btn-primary' . $disabled . '" ' . $disabled . '  data-cid="' . $row['cz_id'] . '">쿠폰 다운로드</button></div>' . PHP_EOL;
                    $coupon .= '</div>' . PHP_EOL;
                    $coupon .= '</div><!--//.col-->';
                }

                if ($coupon) {
                    echo PHP_EOL . $coupon .  PHP_EOL;
                } else {
                    echo '<div class="empty-item p-5 text-center">사용할 수 있는 쿠폰이 없습니다.</div>';
                }
                ?>
            </div>
        </div>
    </section>
</div>