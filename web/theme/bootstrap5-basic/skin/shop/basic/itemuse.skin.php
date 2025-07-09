<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/itemuse.css">', 200);
?>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<section class="item-use-wrap">
    <div class="item-total-score mb-2 p-2 p-lg-4">
        <h3 class="fs-4 fw-bolder ff-noto mb-3">등록된 사용후기</h3>
        <?php if ($star_score) { ?>
            <h4 class="fs-6 fw-bolder">구매고객 총평점 <span>(총 <strong><?php echo $total_count; ?></strong> 건 상품평 기준)</span></h4>
            <img src="<?php echo G5_SHOP_URL; ?>/img/s_star<?php echo $star_score ?>.png" alt="" class="sit_star">
        <?php } ?>
        <div class="d-flex justify-content-end mb-5">
            <a href="<?php echo $itemuse_form; ?>" class="btn btn-outline-primary btn-sm itemuse_form"><i class="bi bi-pencil-fill"></i> 사용후기 쓰기<span class="visually-hidden"> 새 창</span></a>
            <a href="<?php echo $itemuse_list; ?>" class="btn btn-outline-secondary btn-sm itemuse_list ms-1">더보기</a>
        </div>
    </div>

    <?php
    $thumbnail_width = 500;

    for ($i = 0; $row = sql_fetch_array($result); $i++) {
        $is_num     = $total_count - ($page - 1) * $rows - $i;
        $is_star    = get_star($row['is_score']);
        $is_name    = get_text($row['is_name']);
        $is_subject = conv_subject($row['is_subject'], 50, "…");
        $is_content = get_view_thumbnail(conv_content($row['is_content'], 1), $thumbnail_width);
        $is_reply_name = !empty($row['is_reply_name']) ? get_text($row['is_reply_name']) : '';
        $is_reply_subject = !empty($row['is_reply_subject']) ? conv_subject($row['is_reply_subject'], 50, "…") : '';
        $is_reply_content = !empty($row['is_reply_content']) ? get_view_thumbnail(conv_content($row['is_reply_content'], 1), $thumbnail_width) : '';
        $is_time    = substr($row['is_time'], 2, 8);

        $hash = md5($row['is_id'] . $row['is_time'] . $row['is_ip']);

        if ($i == 0) echo '<ul class="list-group border-0 p-0">';
    ?>

        <li class="list-group-item p-0 border-0">
            <ul class="list-group list-group-horizontal justify-content-between border-bottom border-top mb-2">
                <li class="list-group-item border-0"><i class="bi bi-person"></i> <?php echo $is_name; ?> </li>
                <li class="list-group-item border-0 align-content-center d-flex flex-wrap flex-fill"><img src="<?php echo G5_SHOP_URL; ?>/img/s_star<?php echo $is_star; ?>.png" alt="별<?php echo $is_star; ?>개" width="85"></li>
                <li class="list-group-item border-0 align-content-center"><i class="bi bi-clock" aria-hidden="true"></i> <?php echo $is_time; ?></li>
            </ul>
            <div class="review-titlemb-2 d-flex mb-4 p-2 p-lg-4">
                <div class="reivew-thumb d-flex me-1"><?php echo get_itemuselist_thumbnail($row['it_id'], $row['is_content'], 30, 30); ?></div>
                <div class="d-flex fs-5 fw-bolder ff-noto text-secondary align-content-center flex-wrap"><?php echo $is_subject; ?></div>
            </div>

            <div class="review-content p-2 p-lg-4">
                <div class="ff-nanum">
                    <?php echo $is_content; ?>
                </div>

                <?php if ($is_admin || $row['mb_id'] == $member['mb_id']) { ?>
                    <div class="d-flex justify-content-end mb-3">
                        <a href="<?php echo $itemuse_form . "&amp;is_id={$row['is_id']}&amp;w=u"; ?>" class="itemuse_form btn btn-outline-primary me-1 btn-sm" onclick="return false;"><i class="bi bi-pencil-square"></i> 수정</a>
                        <a href="<?php echo $itemuse_formupdate . "&amp;is_id={$row['is_id']}&amp;w=d&amp;hash={$hash}"; ?>" class="itemuse_delete btn btn-outline-danger btn-sm"><i class="bi bi-trash-fill"></i>삭제</a>
                    </div>
                <?php } ?>

                <?php if ($is_reply_subject) { ?>
                    <div class="use-reply-wrap p-2 mb-5">
                        <div class="fs-5 fw-bolder ff-noto mb-2"><i class="bi bi-reply"></i> 답변</div>
                        <div class="reply-name">
                            <i class="bi bi-person-badge"></i> <?php echo $is_reply_name; ?>
                        </div>
                        <div class="reply-title fw-bolder ff-noto text-secondary mb-4">
                            <?php echo $is_reply_subject; ?>
                        </div>
                        <div class="reply-content ff-nanum">
                            <?php echo $is_reply_content; ?>
                        </div>
                    </div>
                <?php } //end if 
                ?>
            </div>
        </li>

    <?php }

    if ($i > 0) {
        echo '</ul>';
    }

    if (!$i) {
        echo '<div class="empty-item text-center p-4">사용후기가 없습니다.</div>';
    }
    ?>
</section>
<div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
    <?php
    //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
    $write_pages = itemuse_page(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,  G5_SHOP_URL . "/itemuse.php?it_id={$it_id}&amp;page=", "");
    $paging = str_replace("sound_only", "visually-hidden", $write_pages);
    $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
    $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
    $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
    $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
    echo $paging;
    ?>
</div>

<script>
    $(function() {
        $(".itemuse_form").click(function() {
            window.open(this.href, "itemuse_form", "width=810,height=680,scrollbars=1");
            return false;
        });

        $(".itemuse_delete").click(function() {
            if (confirm("정말 삭제 하시겠습니까?\n\n삭제후에는 되돌릴수 없습니다.")) {
                return true;
            } else {
                return false;
            }
        });

        $(".pg_page").click(function() {
            $("#itemuse").load($(this).attr("href"));
            return false;
        });
    });
</script>