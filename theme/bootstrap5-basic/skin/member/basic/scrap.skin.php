<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/scrap.css">', 120);
?>

<div class="new_win scrap-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-bookmark-plus-fill"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class="container-fluid">
        <div class="bg-white p-2 p-lg-4 border">
            <ul class="list-group list-group-flush">
                <?php for ($i = 0; $i < count($list); $i++) {  ?>
                    <li class="list-group-item">
                        <a href="<?php echo $list[$i]['opener_href_wr_id'] ?>" class="scrap_tit" target="_blank" onclick="opener.document.location.href='<?php echo $list[$i]['opener_href_wr_id'] ?>'; return false;"><?php echo $list[$i]['subject'] ?></a>
                        <a href="<?php echo $list[$i]['opener_href'] ?>" class="scrap_cate" target="_blank" onclick="opener.document.location.href='<?php echo $list[$i]['opener_href'] ?>'; return false;"><?php echo $list[$i]['bo_subject'] ?></a>
                        <div class="text-end">
                            <i class="bi bi-clock" aria-hidden="true"></i> <?php echo $list[$i]['ms_datetime'] ?>
                            <a href="<?php echo $list[$i]['del_href'];  ?>" onclick="del(this.href); return false;" class="btn btn-outline-danger btn-sm"><i class="fa fa-trash-o" aria-hidden="true"></i><span class="visually-hidden">삭제</span></a>
                        </div>
                    </li>
                <?php }  ?>

                <?php if ($i === 0) {
                    echo "<li class='list-group-item'><p class='empty-item p-5 text-center'>자료가 없습니다.</p></li>";
                }  ?>
            </ul>

            <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
                <?php
                //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
                $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "?$qstr&amp;page=");
                $paging = str_replace("sound_only", "visually-hidden", $write_pages);
                $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
                $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
                $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
                $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
                echo $paging;
                ?>
            </div>
            <?php if ($is_member && (defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP == true)) { ?>
                <div class='d-flex justify-content-center p-5'>
                    <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close">창닫기</button>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.scrap-modal-body').height();
        $(window.parent.document).find('.scrap-modal-body iframe').height(parentHeight + 'px');
    });
</script>