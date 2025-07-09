<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/memo.css">', 120);

$nick = get_sideview($mb['mb_id'], $mb['mb_nick'], $mb['mb_email'], $mb['mb_homepage']);
if ($kind == "recv") {
    $kind_str = "보낸";
    $kind_date = "받은";
} else {
    $kind_str = "받는";
    $kind_date = "보낸";
}
?>

<div id="memo_list" class="new_win memo-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-envelope"></i> <?php echo $g5['title'] ?></h1>
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
            <div class='memo-content mt-4'>
                <div class="d-flex flex-column">
                    <ul class="list-group list-group-horizontal">
                        <li class="list-group-item">
                            <?php echo get_member_profile_img($mb['mb_id']); ?>
                            <div class="dropdown align-content-center d-flex flex-wrap me-2 writer z-index">
                                <a href="#name" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text(strip_tags($mb['mb_nick'])); ?></a>
                                <div class="dropdown-menu p-2 shadow">
                                    <?php echo $nick ?>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item"><span class="visually-hidden"><?php echo $kind_date ?>시간</span><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo $memo['me_send_datetime'] ?></li>
                    </ul>
                    <div class="btn btn-group mt-3 mb-3 ms-auto">
                        <?php if ($prev_link) {  ?>
                            <a href="<?php echo $prev_link ?>" class="me-1 btn btn-outline-secondary"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-md-inline">이전쪽지</span></a>
                        <?php }  ?>
                        <?php if ($next_link) {  ?>
                            <a href="<?php echo $next_link ?>" class="me-1 btn btn-outline-secondary"><span class="d-none d-md-inline">다음쪽지</span> <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        <?php }  ?>
                        <a href="<?php echo $list_link ?>" class="btn btn-outline-secondary"><i class="bi bi-list" aria-hidden="true"></i><span class="visually-hidden">목록</span></a>
                        <a href="<?php echo $del_link; ?>" onclick="del(this.href); return false;" class="memo_del btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> <span class="visually-hidden">삭제</span></a>
                    </div>
                </div>
                <article class="p-1">
                    <?php echo conv_content($memo['me_memo'], 0) ?>
                </article>
            </div>
            <div class="button-wrap d-flex justify-content-end mt-5">
                <?php if ($kind == 'recv') {  ?>
                    <a href="./memo_form.php?me_recv_mb_id=<?php echo $mb['mb_id'] ?>&amp;me_id=<?php echo $memo['me_id'] ?>" class="btn btn-outline-primary"><i class="bi bi-reply"></i> 답장</a>
                <?php } ?>
                <?php if ($is_member && (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == true)) { ?>
                    <button type="button" onclick="window.close();" class="btn btn-outline-danger">창닫기</button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<script>
    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.memo-modal-body').height();
        $(window.parent.document).find('.memo-modal-body iframe').height(parentHeight + 'px');
    });
</script>