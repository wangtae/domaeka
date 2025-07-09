<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

// 이벤트 정보
$hsql = " SELECT ev_id, ev_subject, ev_subject_strong from {$g5['g5_shop_event_table']} where ev_use = '1' order by ev_id desc limit 3";
$hresult = sql_query($hsql);

if (sql_num_rows($hresult)) {
    add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/event.css">', 300);
?>
    <div class='main-event-wrap'>
        <div class='border-bottom pb-2 mb-3 title-wrap'>
            <h2 class='fs-6 fw-bolder ff-noto m-0 d-inline pb-2 pe-2 ps-2'><i class="bi bi-balloon-fill"></i> 이벤트</h2>
        </div>
        <div class='d-flex flex-wrap flex-row'>
            <?php
            for ($i = 0; $row = sql_fetch_array($hresult); $i++) {
                echo '<div class="d-flex flex-fill border-bottom mb-3 pb-3  position-relative mb-2"><div class="item-list-wrap">';
                $href = G5_SHOP_URL . '/event.php?ev_id=' . $row['ev_id'];

                $event_img = G5_DATA_PATH . '/event/' . $row['ev_id'] . '_m'; // 이벤트 이미지

                if (file_exists($event_img)) {
                    echo '<div class="event-title mb-4 position-relative"><div class="image-title-text position-absolute">' . $row['ev_subject'] . '</div><a href="' . $href . '" class="event-title-link"><img src="' . G5_DATA_URL . '/event/' . $row['ev_id'] . '_m" alt="' . $row['ev_subject'] . '" class="img-fluid"></a></div>' . PHP_EOL;
                } else { // 없다면 텍스트 출력
                    echo '<div class="event-title mb-4"><a href="' . $href . '" class="event-title-link">';
                    if ($row['ev_subject_strong']) {
                        echo '<strong>';
                    }
                    echo '<i class="bi bi-check"></i> ';
                    echo $row['ev_subject'];
                    if ($row['ev_subject_strong']) {
                        echo '</strong>';
                    }
                    echo '</a></div>' . PHP_EOL;
                }

                // 이벤트 상품
                $sql2 = " SELECT b.* from `{$g5['g5_shop_event_item_table']}` a left join `{$g5['g5_shop_item_table']}` b on (a.it_id = b.it_id) where a.ev_id = '{$row['ev_id']}' order by it_id desc limit 0, 6 ";
                $result2 = sql_query($sql2);
                for ($k = 1; $row2 = sql_fetch_array($result2); $k++) {
                    if ($k == 1) {
                        echo '<div class="row g-1 row-cols-1 row-cols-md-3 row-cols-xl-6">' . PHP_EOL;
                    }

                    $item_href = shop_item_url($row2['it_id']);

                    echo '<div class="col mb-3">' . PHP_EOL;
                    echo '<div class="product-image d-flex justify-content-center">' . get_it_image($row2['it_id'], 400, 300, get_text($row2['it_name']), 'img-fluid') . '</div>' . PHP_EOL;
                    echo '<div class="product-link d-flex justify-content-center flex-column"><a href="' . $item_href . '" class="product-title text-truncate text-center">' . get_text(cut_str($row2['it_name'], 30)) . '</a>' . PHP_EOL;
                    echo '<div class="product-price d-flex justify-content-center text-success fw-bold">' . display_price(get_price($row2), $row2['it_tel_inq']) . '</div></div>' . PHP_EOL;
                    echo '</div>' . PHP_EOL;
                }
                if ($k > 1) {
                    echo '</div>' . PHP_EOL;
                }

                if ($k == 1) {
                    echo '<div class="ev_prd">' . PHP_EOL;
                    echo '<div class="no_prd">등록된 상품이 없습니다.</div>' . PHP_EOL;
                    echo '</div>' . PHP_EOL;
                }
                //echo '<a href="'.$href.'" class="sev_more">더보기</a>'.PHP_EOL;
                echo '</div>' . PHP_EOL;
                echo '</div>' . PHP_EOL;
            }

            if ($i == 0) {
                echo '<div class="empty-item text-center p-4">이벤트 없음</div>' . PHP_EOL;
            }
            ?>
        </div>
    </div>
<?php
}
