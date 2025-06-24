<?php
if (!defined("_GNUBOARD_")) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/itemqalist.css">', 200);

?>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<div class="itemqalist-wrap container mt-2">
    <div class="bg-white p-4 border mb-2">
        <h1 class="fs-4 fw-bolder mb-0"><i class="bi bi-question-square"></i> <?php echo $g5['title'] ?></h1>
    </div>
    <div class="bg-white border">
        <form method="get" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
            <div class="d-flex justify-content-center m-5">
                <div class="d-flex col-sm-12 col-md-6">
                    <label for="sfl" class="visually-hidden">검색항목 필수</label>
                    <div class="input-group">
                        <select name="sfl" id="sfl" required class="form-select">
                            <option value="">선택</option>
                            <option value="b.it_name" <?php echo get_selected($sfl, "b.it_name", true); ?>>상품명</option>
                            <option value="a.it_id" <?php echo get_selected($sfl, "a.it_id"); ?>>상품코드</option>
                            <option value="a.iq_subject" <?php echo get_selected($sfl, "a.iq_subject"); ?>>문의제목</option>
                            <option value="a.iq_question" <?php echo get_selected($sfl, "a.iq_question"); ?>>문의내용</option>
                            <option value="a.iq_name" <?php echo get_selected($sfl, "a.iq_name"); ?>>작성자명</option>
                            <option value="a.mb_id" <?php echo get_selected($sfl, "a.mb_id"); ?>>작성자아이디</option>
                        </select>
                        <label for="stx" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
                        <input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="form-control">
                        <button type="submit" value="검색" class="btn btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">검색</span></button>
                        <?php echo (isset($stx) && $stx != '') ? "<a href='{$_SERVER['SCRIPT_NAME']}' class='input-group-text'>전체</a>" : ""; ?>
                    </div>
                </div>
            </div>
        </form>

        <div class='itemqalist'>
            <?php
            $thumbnail_width = 500;
            $num = $total_count - ($page - 1) * $rows;

            for ($i = 0; $row = sql_fetch_array($result); $i++) {
                $iq_subject = conv_subject($row['iq_subject'], 50, "…");

                $is_secret = false;
                if ($row['iq_secret']) {
                    $iq_subject .= ' <i class="fa fa-lock" aria-hidden="true"></i>';

                    if ($is_admin || $member['mb_id'] == $row['mb_id']) {
                        $iq_question = get_view_thumbnail(conv_content($row['iq_question'], 1), $thumbnail_width);
                    } else {
                        $iq_question = '<div class="alert alert-danger"><i class="bi bi-lock-fill"></i> 비밀글로 보호된 문의입니다.</div>';
                        $is_secret = true;
                    }
                } else {
                    $iq_question = get_view_thumbnail(conv_content($row['iq_question'], 1), $thumbnail_width);
                }

                $it_href = shop_item_url($row['it_id']);

                if ($row['iq_answer']) {
                    $iq_answer = get_view_thumbnail(conv_content($row['iq_answer'], 1), $thumbnail_width);
                    $iq_stats = '<i class="bi bi-check"></i> 답변완료';
                    $iq_style = 'sit_qaa_done';
                    $is_answer = true;
                } else {
                    $iq_stats = '<i class="bi bi-hourglass-top"></i> 답변대기';
                    $iq_style = 'sit_qaa_yet';
                    $iq_answer = '<div class="fs-6 fw-bold p-3"><i class="bi bi-reply"></i> 답변이 등록되지 않았습니다.</div>';
                    $is_answer = false;
                }

                if ($i == 0) {
                    echo '<div class="product">';
                }
            ?>
                <div class="mt-5 ">
                    <ul class="list-group list-group-horizontal p-2 border-bottom border-top justify-content-between">
                        <li class="list-group-item border-0 p-0 me-2"><i class="bi bi-person" aria-hidden="true"></i> <?php echo $row['iq_name']; ?></li>
                        <li class="list-group-item border-0 p-0"><i class="bi bi-clock" aria-hidden="true"></i> <?php echo substr($row['iq_time'], 0, 10); ?></li>
                        <li class="list-group-item border-0 p-0 flex-fill text-end "><span class="<?php echo $iq_style; ?>"><?php echo $iq_stats; ?></span></li>

                    </ul>
                    <div class="product-image d-flex flex-row m-2">
                        <a href="<?php echo $it_href; ?>" class="product-image-link me-2">
                            <?php echo get_it_image($row['it_id'], 50, 50); ?>
                        </a>
                        <div class="product-name text-primary"><?php echo $row['it_name']; ?></div>
                    </div>

                    <div class="border-top p-2">
                        <h2 class="fs-6 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $iq_subject; ?></h2>
                        <div class="listqa-content">
                            <div class="sit_qa_qaq">
                                <strong class="visually-hidden">문의내용</strong>
                                <?php echo $iq_question; // 상품 문의 내용 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!$is_secret) { ?>
                        <div class="listqa-reply border-top p-2">
                            <strong class="visually-hidden">답변</strong>
                            <?php echo $iq_answer; ?>
                        </div>
                    <?php } ?>
                </div>
            <?php
                $num--;
            }

            if ($i > 0) {
                echo '</div>';
            }
            if ($i == 0) {
                echo '<p id="sqa_empty">자료가 없습니다.</p>';
            }
            ?>
        </div>
    </div>
</div>
<div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
    <?php
    //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
    $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,  "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page=");
    $paging = str_replace("sound_only", "visually-hidden", $write_pages);
    $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
    $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
    $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
    $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
    echo $paging;
    ?>
</div>