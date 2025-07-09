<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/itemqa.css">', 200);

?>
<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<!-- 상품문의 목록 시작 { -->
<section id="sit_qa_list" class="item-qa-wrap">
    <h3 class="visually-hidden">등록된 상품문의</h3>

    <div class="d-flex justify-content-end p-4">
        <a href="<?php echo $itemqa_form; ?>" class="btn btn-outline-primary btn-sm itemqa_form me-1"><i class="bi bi-pen-fill"></i> 상품문의 쓰기<span class="visually-hidden">새 창</span></a>
        <a href="<?php echo $itemqa_list; ?>" id="itemqa_list" class="btn btn-outline-secondary btn-sm">더보기</a>
    </div>

    <?php
    $thumbnail_width = 500;
    $iq_num     = $total_count - ($page - 1) * $rows;

    for ($i = 0; $row = sql_fetch_array($result); $i++) {
        $iq_name    = get_text($row['iq_name']);
        $iq_subject = conv_subject($row['iq_subject'], 50, "…");

        $is_secret = false;
        if ($row['iq_secret']) {
            $iq_subject .= ' <img src="' . G5_SHOP_SKIN_URL . '/img/icon_secret.gif" alt="비밀글">';

            if ($is_admin || $member['mb_id'] == $row['mb_id']) {
                $iq_question = get_view_thumbnail(conv_content($row['iq_question'], 1), $thumbnail_width);
            } else {
                $iq_question = '비밀글로 보호된 문의입니다.';
                $is_secret = true;
            }
        } else {
            $iq_question = get_view_thumbnail(conv_content($row['iq_question'], 1), $thumbnail_width);
        }
        $iq_time    = substr($row['iq_time'], 2, 8);

        $hash = md5($row['iq_id'] . $row['iq_time'] . $row['iq_ip']);

        $iq_stats = '';
        $iq_style = '';
        $iq_answer = '';

        if ($row['iq_answer']) {
            $iq_answer = get_view_thumbnail(conv_content($row['iq_answer'], 1), $thumbnail_width);
            $iq_stats = '답변완료';
            $iq_style = 'sit_qaa_done';
            $is_answer = true;
        } else {
            $iq_stats = '답변대기';
            $iq_style = 'sit_qaa_yet';
            $iq_answer = '답변이 등록되지 않았습니다.';
            $is_answer = false;
        }

        if ($i == 0) {
            echo '<ul class="list-group border-0 p-0">';
        }
    ?>

        <li class="list-group-item p-0 border-0 mb-5">
            <div class="border-bottom border-top p-2"><i class="bi bi-person"></i> <?php echo $iq_name; ?> <i class="bi bi-clock" aria-hidden="true"></i> <?php echo $iq_time; ?></div>

            <div class="p-2 p-lg-4">
                <div class="question-content">
                    <strong class="visually-hidden">문의내용</strong>
                    <i class="bi bi-question-circle fs-6 text-primary"></i>
                    <?php echo $iq_question; ?>
                </div>
                <?php if (!$is_secret) { ?>
                    <div class="reply-content p-2 p-lg-4">
                        <strong class="visually-hidden">답변</strong>
                        <i class="bi bi-check-circle-fill fs-6 text-primary"></i>
                        <?php echo $iq_answer; ?>
                    </div>
                <?php } ?>
                <?php if ($is_admin || ($row['mb_id'] == $member['mb_id'] && !$is_answer)) { ?>
                    <div class="d-flex justify-content-end mt-2">
                        <a href="<?php echo $itemqa_form . "&amp;iq_id={$row['iq_id']}&amp;w=u"; ?>" class="itemqa_form btn btn-outline-primary btn-sm me-1" onclick="return false;"><i class="bi bi-pencil-square"></i> 수정</a>
                        <a href="<?php echo $itemqa_formupdate . "&amp;iq_id={$row['iq_id']}&amp;w=d&amp;hash={$hash}"; ?>" class="itemqa_delete btn btn-outline-danger btn-sm"><i class="bi bi-trash2-fill"></i> 삭제</a>
                    </div>
                <?php } ?>

            </div>
        </li>

    <?php
        $iq_num--;
    }

    if ($i > 0) echo '</ul>';

    if (!$i) echo '<div class="empty-item p-4 text-center">상품문의가 없습니다.</div>';
    ?>
</section>


<div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
    <?php
    //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
    $write_pages = itemuse_page(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,  G5_SHOP_URL . "/itemqa.php?it_id={$it_id}&amp;page=", "");
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
        $(".itemqa_form").click(function() {
            window.open(this.href, "itemqa_form", "width=810,height=680,scrollbars=1");
            return false;
        });

        $(".itemqa_delete").click(function() {
            return confirm("정말 삭제 하시겠습니까?\n\n삭제후에는 되돌릴수 없습니다.");
        });

        $(".qa_page").click(function() {
            $("#itemqa").load($(this).attr("href"));
            return false;
        });
    });
</script>
<!-- } 상품문의 목록 끝 -->