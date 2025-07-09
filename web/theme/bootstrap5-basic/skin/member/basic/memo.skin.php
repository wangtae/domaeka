<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/memo.css">', 120);
?>

<div id="memo_list" class="new_win memo-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-envelope"></i> <?php echo $g5['title'] ?></h1>
            <div class="text-end">
                전체 <?php echo $kind_title ?> 쪽지 <?php echo $total_count ?>통
            </div>
        </div>
    </div>
    <div class='container-fluid'>
        <div class='bg-white p-2 p-lg-4 border'>
            <!-- Nav tabs -->
            <ul class="nav nav-tabs sticky-top bg-white" id="memo-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (isset($kind) && $kind === 'recv') ? "active" : ""; ?>" type="button" role="tab"><a href="./memo.php?kind=recv">받은쪽지</a></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (isset($kind) && $kind === 'send') ? "active" : ""; ?>" type="button" role="tab"><a href="./memo.php?kind=send">보낸쪽지</a></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (!isset($kind) && !$kind) ? "active" : ""; ?>" type="button" role="tab"><a href="./memo_form.php">쪽지쓰기</a></button>
                </li>
            </ul>

            <div class="mt-2">
                <ul class="list-group list-group-flush">
                    <?php
                    for ($i = 0; $i < count($list); $i++) {
                        $readed = (substr($list[$i]['me_read_datetime'], 0, 1) == 0) ? '' : 'read';
                        $memo_preview = utf8_strcut(strip_tags($list[$i]['me_memo']), 30, '..');
                    ?>
                        <li class="list-group-item d-flex">
                            <div class="d-md-flex memo-profile">
                                <?php echo get_member_profile_img($list[$i]['mb_id']); ?>
                                <div class="dropdown align-content-center d-flex flex-wrap me-2 writer">
                                    <a href="#name<?php echo $i ?>" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text($list[$i]['mb_nick']) ?></a>
                                    <div class="dropdown-menu p-2 shadow">
                                        <?php echo $list[$i]['name'] ?>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap memo-preview align-content-center flex-grow-1 text-truncate">
                                <a href="<?php echo $list[$i]['view_href']; ?>" class="text-truncate">
                                    <?php if ($readed) { ?>
                                        <i class="bi bi-envelope-paper"></i> &nbsp;
                                    <?php } else { ?>
                                        <i class="bi bi-envelope-fill"></i> &nbsp;
                                        <span class="visually-hidden">안 읽은 쪽지</span>
                                    <?php } ?>
                                    <?php echo $memo_preview; ?>
                                </a>
                                <span class="memo-datetime text-end">
                                    <i class="bi bi-clock" aria-hidden="true"></i> <?php echo $list[$i]['send_datetime']; ?>
                                </span>
                            </div>
                            <div class='d-flex memo-delete'>
                                <a href="<?php echo $list[$i]['del_href']; ?>" onclick="del(this.href); return false;" class="memo_del"><i class="fa fa-trash-o" aria-hidden="true"></i> <span class="visually-hidden">삭제</span></a>
                            </div>
                        </li>
                    <?php } ?>
                    <?php if ($i == 0) {
                        echo '<li class="empty_table">자료가 없습니다.</li>';
                    }  ?>
                </ul>
            </div>
        </div>
        <!-- 페이지 -->
        <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
            <?php
            //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
            $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "./memo.php?kind=$kind" . $qstr . "&amp;page=");
            $paging = str_replace("sound_only", "visually-hidden", $write_pages);
            $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
            $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
            $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
            $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
            echo $paging;
            ?>
        </div>
        <p class="alert alert-info"><i class="bi bi-info-circle" aria-hidden="true"></i> 쪽지 보관일수는 최장 <strong><?php echo $config['cf_memo_del'] ?></strong>일 입니다.</p>

        <?php if ($is_member && (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == true)) { ?>
            <div class='d-flex justify-content-center p-5'>
                <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close">창닫기</button>
            </div>
        <?php } ?>

    </div>
</div>
<script>
    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.memo-modal-body').height();
        $(window.parent.document).find('.memo-modal-body iframe').height(parentHeight + 'px');
    });
</script>