<?php

/**
 * Point list skin
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/point.css">', 120);
?>

<div id="point" class="new_win point-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
            <div class="text-end">
                보유포인트
                <?php echo number_format($member['mb_point']); ?>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="bg-white p-2 border">
            <ul class="list-group list-group-flush">
                <?php
                $sum_point1 = $sum_point2 = $sum_point3 = 0;

                $i = 0;
                foreach ((array) $list as $row) {
                    $point1 = $point2 = 0;
                    $point_use_class = '';
                    if ($row['po_point'] > 0) {
                        $point1 = '+' . number_format($row['po_point']);
                        $sum_point1 += $row['po_point'];
                    } else {
                        $point2 = number_format($row['po_point']);
                        $sum_point2 += $row['po_point'];
                        $point_use_class = 'point_use';
                    }

                    $po_content = $row['po_content'];

                    $expr = '';
                    if ($row['po_expired'] == 1)
                        $expr = ' txt_expired';
                ?>
                    <li class="list-group-item <?php echo $point_use_class; ?>">
                        <div class="point_top">
                            <span class="fw-bold"><?php echo $po_content; ?></span>
                            <span class="primary fw-bolder">
                                <?php if ($point1) {
                                    echo $point1;
                                } else {
                                    echo $point2;
                                } ?>
                            </span>
                        </div>
                        <div class="text-end">
                            <span class=""><i class="bi bi-clock" aria-hidden="true"></i> <?php echo $row['po_datetime']; ?></span>
                            <span class="point_date<?php echo $expr; ?>">
                                <?php if ($row['po_expired'] == 1) { ?>
                                    만료 <?php echo substr(str_replace('-', '', $row['po_expire_date']), 2); ?>
                                <?php } else {
                                    echo $row['po_expire_date'] == '9999-12-31' ? '&nbsp;' : $row['po_expire_date'];
                                } ?>
                            </span>
                        </div>
                    </li>
                <?php
                    $i++;
                }   // end foreach

                if ($i == 0)
                    echo '<li class="empty_li">자료가 없습니다.</li>';
                else {
                    if ($sum_point1 > 0)
                        $sum_point1 = "+" . number_format($sum_point1);
                    $sum_point2 = number_format($sum_point2);
                }
                ?>

                <li class="list-group-item text-end">
                    소계
                    <span><?php echo $sum_point1; ?></span>
                    <span><?php echo $sum_point2; ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
        <?php
        //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
        $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'] . '?' . $qstr . '&amp;page=');
        $paging = str_replace("sound_only", "visually-hidden", $write_pages);
        $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
        $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
        $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
        $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
        echo $paging;
        ?>
    </div>
    <?php if ($is_member && (defined('BB_POINT_POPUP') && BB_POINT_POPUP == true)) { ?>
        <div class='d-flex justify-content-center p-5'>
            <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close">창닫기</button>
        </div>
    <?php } ?>
</div>
<script>
    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.point-modal-body').height();
        $(window.parent.document).find('.point-modal-body iframe').height(parentHeight + 'px');
    });
</script>