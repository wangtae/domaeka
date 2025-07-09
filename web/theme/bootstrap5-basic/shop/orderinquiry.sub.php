<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

if (!defined("_ORDERINQUIRY_")) {
    exit;
}
?>
<?php if (!$limit) { ?>총 <?php echo $cnt; ?> 건<?php } ?>
<?php
if (strpos($_SERVER['PHP_SELF'], 'orderinquiry.php')) { ?>
    <div class="order-list-wrap container mt-2">
        <div class="bg-white p-4 mb-2 border">
            <h1 class="fs-5 fw-bolder mb-0"><i class="bi bi-list-task"></i> <?php echo $g5['title'] ?></h1>
        </div>
        <div class="bg-white p-4 mb-2 border">
        <?php } ?>
        <div class="table-responsive">
            <table class="table">
                <thead class="text-center">
                    <tr>
                        <th scope="col">주문서번호</th>
                        <th class="d-none d-md-table-cell" scope="col">주문일시</th>
                        <th class="d-none d-md-table-cell" scope="col">상품수</th>
                        <th class="d-none d-md-table-cell" scope="col">주문금액</th>
                        <th class="d-none d-md-table-cell" scope="col">입금액</th>
                        <th class="d-none d-md-table-cell" scope="col">미입금액</th>
                        <th class="d-none d-md-table-cell" scope="col">상태</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = " select *
               from {$g5['g5_shop_order_table']}
              where mb_id = '{$member['mb_id']}'
              order by od_id desc
              $limit ";
                    $result = sql_query($sql);
                    for ($i = 0; $row = sql_fetch_array($result); $i++) {
                        $uid = md5($row['od_id'] . $row['od_time'] . $row['od_ip']);

                        switch ($row['od_status']) {
                            case '주문':
                                $od_status = '<span class="status_01">입금확인중</span>';
                                break;
                            case '입금':
                                $od_status = '<span class="status_02">입금완료</span>';
                                break;
                            case '준비':
                                $od_status = '<span class="status_03">상품준비중</span>';
                                break;
                            case '배송':
                                $od_status = '<span class="status_04">상품배송</span>';
                                break;
                            case '완료':
                                $od_status = '<span class="status_05">배송완료</span>';
                                break;
                            default:
                                $od_status = '<span class="status_06">주문취소</span>';
                                break;
                        }
                    ?>

                        <tr class="text-center">
                            <td>
                                <a href="<?php echo G5_SHOP_URL; ?>/orderinquiryview.php?od_id=<?php echo $row['od_id']; ?>&amp;uid=<?php echo $uid; ?>" class="fw-bold text-primary"><?php echo $row['od_id']; ?></a>
                                <div class="d-flex d-md-none text-start">
                                    <ul class="list-group d-flex flex-fill">
                                        <li class="list-group-item flex-fill">주문일시 : <?php echo substr($row['od_time'], 2, 14); ?> (<?php echo get_yoil($row['od_time']); ?>)</li>
                                        <li class="list-group-item flex-fill">상품수 : <?php echo $row['od_cart_count']; ?></li>
                                        <li class="list-group-item flex-fill">주문금액 : <?php echo display_price($row['od_cart_price'] + $row['od_send_cost'] + $row['od_send_cost2']); ?></li>
                                        <li class="list-group-item flex-fill">입금액 : <?php echo display_price($row['od_receipt_price']); ?></li>
                                        <li class="list-group-item flex-fill">미입금액 : <?php echo display_price($row['od_misu']); ?></li>
                                        <li class="list-group-item flex-fill">상태 : <?php echo $od_status; ?></li>
                                    </ul>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell"><?php echo substr($row['od_time'], 2, 14); ?> (<?php echo get_yoil($row['od_time']); ?>)</td>
                            <td class="d-none d-md-table-cell"><?php echo $row['od_cart_count']; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo display_price($row['od_cart_price'] + $row['od_send_cost'] + $row['od_send_cost2']); ?></td>
                            <td class="d-none d-md-table-cell"><?php echo display_price($row['od_receipt_price']); ?></td>
                            <td class="d-none d-md-table-cell"><?php echo display_price($row['od_misu']); ?></td>
                            <td class="d-none d-md-table-cell"><?php echo $od_status; ?></td>
                        </tr>
                    <?php
                    }

                    if ($i == 0) {
                        echo '<tr><td colspan="7" class="empty-item p-5 text-center">주문 내역이 없습니다.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php if (strpos($_SERVER['PHP_SELF'], 'orderinquiry.php')) { ?>
            <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
                <?php
                $_paging = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page=");

                $paging = str_replace("sound_only", "visually-hidden", $_paging);
                $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
                $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
                $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
                $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
                echo $paging;
                ?>
            </div>
        </div>
    </div><!--//.order-list-wrap -->
<?php } ?>