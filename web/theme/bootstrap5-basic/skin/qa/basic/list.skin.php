<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

// 선택옵션으로 인해 셀합치기가 가변적으로 변함
$colspan = 6;

if ($is_checkbox) {
    $colspan++;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/qna/qna-basic.css">', 150);

?>
<div class='qna-list-wrap'>
    <div class="qna-header container mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
            <span class='board-count-info text-secondary'>
                <span>Total <?php echo number_format($total_count) ?>건</span>
                <?php echo $page ?> 페이지
            </span>
        </div>
    </div>

    <div id="bo_list" class='qa-wrap container'>
        <div class='bg-white p-2 p-lg-4 border'>
            <?php if ($category_option) { ?>
                <!-- 카테고리 시작 { -->
                <h2 class="visually-hidden"><?php echo $qaconfig['qa_title'] ?> 카테고리</h2>
                <nav class="board-category" aria-label='breadcrumb'>
                    <ul class='breadcrumb'>
                        <?php echo str_replace('<li', '<li class="breadcrumb-item"', $category_option) ?>
                    </ul>
                </nav>
                <!-- } 카테고리 끝 -->
            <?php } ?>

            <!-- 게시판 페이지 정보 및 버튼 시작 { -->
            <div id="bo_btn_top" class='btn-navbar d-flex justify-content-end mb-3'>
                <?php if ($admin_href || $write_href) { ?>
                    <div class="btn-group">
                        <?php if ($admin_href) { ?>
                            <a href="<?php echo $admin_href ?>" class="btn btn-secondary" title="관리자"><i class="fa fa-cog"></i><span class="visually-hidden">관리자</span></a>
                        <?php } ?>

                        <?php if ($write_href) { ?>
                            <a href="<?php echo $write_href ?>" class="btn btn-primary ml-1" title="문의등록"><i class="fa fa-pencil" aria-hidden="true"></i><span>문의등록</span></a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <!-- } 게시판 페이지 정보 및 버튼 끝 -->

            <form name="fqalist" id="fqalist" action="./qadelete.php" onsubmit="return fqalist_submit(this);" method="post">
                <input type="hidden" name="stx" value="<?php echo $stx; ?>">
                <input type="hidden" name="sca" value="<?php echo $sca; ?>">
                <input type="hidden" name="page" value="<?php echo $page; ?>">
                <input type="hidden" name="token" value="<?php echo get_text($token); ?>">

                <div class="table-wrap">
                    <table class='table table-striped'>
                        <caption><?php echo $g5['title'] ?> 목록</caption>
                        <thead class="table-secondary">
                            <tr>
                                <?php if ($is_checkbox) { ?>
                                    <th class="all_chk chk_box">
                                        <input type="checkbox" id="chkall" onclick="if (this.checked) all_checked(true); else all_checked(false);" class="selec_chk">
                                        <label for="chkall" class='visually-hidden'>
                                            <span></span>
                                            <sapn class="visually-hidden">현재 페이지 게시물 전체선택</sapn>
                                        </label>
                                    </th>
                                <?php } ?>
                                <th class="td_num d-none d-md-table-cell text-center">번호</th>
                                <th>제목</th>
                                <th class="td_name d-none d-md-table-cell text-center text-center">글쓴이</th>
                                <th class="td_date d-none d-md-table-cell text-center text-center">등록일</th>
                                <th class="td_stat d-none d-md-table-cell text-center text-center">상태</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 0; $i < count($list); $i++) {
                                if ($i % 2 == 0) $lt_class = "even";
                                else $lt_class = "";
                            ?>
                                <tr class="<?php echo $lt_class ?>">
                                    <?php if ($is_checkbox) { ?>
                                        <td class="td_chk chk_box">
                                            <input type="checkbox" name="chk_qa_id[]" value="<?php echo $list[$i]['qa_id'] ?>" id="chk_qa_id_<?php echo $i ?>" class="selec_chk">
                                            <label for="chk_qa_id_<?php echo $i ?>" class="visually-hidden"><span class="visually-hidden"><?php echo $list[$i]['subject'] ?></span></label>
                                        </td>
                                    <?php } ?>
                                    <td class="td_num d-none d-md-table-cell text-center"><?php echo $list[$i]['num']; ?></td>
                                    <td class="td_subject">
                                        <span class="text-bg-warning">&nbsp;<?php echo $list[$i]['category']; ?> </span>
                                        <a href="<?php echo $list[$i]['view_href']; ?>" class="bo_tit">
                                            <?php echo $list[$i]['subject']; ?>
                                            <?php if ($list[$i]['icon_file']) echo " <i class=\"fa fa-download\" aria-hidden=\"true\"></i>"; ?>
                                        </a>
                                        <div class='d-sm-block d-md-none mt-2 wr-info'>
                                            <?php echo $list[$i]['name']; ?> / <?php echo $list[$i]['date']; ?> / <span class=" <?php echo ($list[$i]['qa_status'] ? 'txt_done' : 'txt_rdy'); ?>"><?php echo ($list[$i]['qa_status'] ? '<i class="bi bi-hourglass-bottom"></i> <span class="d-none d-md-inline">답변완료</span>' : '<i class="bi bi-hourglass-split"></i> <span class="d-none d-md-inline">답변대기</span>'); ?>
                                        </div>
                                    </td>
                                    <td class="td_name d-none d-md-table-cell text-center text-center"><?php echo $list[$i]['name']; ?></td>
                                    <td class="td_date d-none d-md-table-cell text-center text-center"><?php echo $list[$i]['date']; ?></td>
                                    <td class="td_stat d-none d-md-table-cell text-center text-center"><span class=" <?php echo ($list[$i]['qa_status'] ? 'txt_done' : 'txt_rdy'); ?>"><?php echo ($list[$i]['qa_status'] ? '<i class="bi bi-hourglass-bottom"></i> <span class="d-none d-md-inline">답변완료</span>' : '<i class="bi bi-hourglass-split"></i> <span class="d-none d-md-inline">답변대기</span>'); ?></span></td>
                                </tr>
                            <?php
                            }
                            ?>

                            <?php if ($i == 0) {
                                echo '<tr><td colspan="' . $colspan . '" class="empty_table p-5 text-center">게시물이 없습니다.</td></tr>';
                            } ?>
                        </tbody>
                    </table>
                </div>
                <!-- 페이지 -->
                <?php echo $list_pages; ?>
                <!-- 페이지 -->

                <div class="btn-navbar d-flex justify-content-end">
                    <?php if ($is_checkbox) { ?>
                        <button type="submit" name="btn_submit" value="선택삭제" title="선택삭제" onclick="document.pressed=this.value" class="btn btn-danger"><i class="fa fa-trash-o" aria-hidden="true"></i><span class="visually-hidden">선택삭제</span></button>
                    <?php } ?>
                    <?php if ($list_href) { ?><a href="<?php echo $list_href ?>" class="btn btn-secondary" title="목록"><i class="fa fa-list" aria-hidden="true"></i><span class="visually-hidden">목록</span></a><?php } ?>
                    <?php if ($write_href) { ?><a href="<?php echo $write_href ?>" class="btn btn-primary" title="문의등록"><i class="fa fa-pencil" aria-hidden="true"></i><span class="visually-hidden"> 문의등록</span></a><?php } ?>
                </div>
            </form>
            <hr />
            <div class='search-wrap mt-3 mb-3'>
                <!-- 게시판 검색 시작 { -->
                <form name="fsearch" method="get">
                    <input type="hidden" name="sca" value="<?php echo $sca ?>">
                    <div class="input-group">
                        <span class='input-group-prepend'>
                            <label for="stx" class='input-group-text'>검색어<strong class="visually-hidden"> 필수</strong></label>
                        </span>
                        <select name="sfl" id="sfl" class="form-control">
                            <?php echo get_qa_sfl_select_options($sfl); ?>
                        </select>
                        <input type="text" name="stx" value="<?php echo stripslashes($stx); ?>" id="stx" required class="form-control" size="25" maxlength="15" placeholder=" 검색어를 입력해주세요">
                        <button type="submit" value="검색" class="btn btn-primary" title="검색"><i class="fa fa-search" aria-hidden="true"></i><span class="visually-hidden">검색</span></button>
                    </div>
                </form>
                <script>
                    // 게시판 검색
                    $(".btn_bo_sch").on("click", function() {
                        $(".bo_sch_wrap").toggle();
                    })
                    $('.bo_sch_bg, .bo_sch_cls').click(function() {
                        $('.bo_sch_wrap').hide();
                    });
                </script>
                <!-- } 게시판 검색 끝 -->
            </div>
        </div>

        <?php if ($is_checkbox) { ?>
            <noscript>
                <p>자바스크립트를 사용하지 않는 경우<br>별도의 확인 절차 없이 바로 선택삭제 처리하므로 주의하시기 바랍니다.</p>
            </noscript>
        <?php } ?>

        <?php if ($is_checkbox) { ?>
            <script>
                function all_checked(sw) {
                    var f = document.fqalist;

                    for (var i = 0; i < f.length; i++) {
                        if (f.elements[i].name == "chk_qa_id[]")
                            f.elements[i].checked = sw;
                    }
                }

                function fqalist_submit(f) {
                    var chk_count = 0;

                    for (var i = 0; i < f.length; i++) {
                        if (f.elements[i].name == "chk_qa_id[]" && f.elements[i].checked)
                            chk_count++;
                    }

                    if (!chk_count) {
                        alert(document.pressed + "할 게시물을 하나 이상 선택하세요.");
                        return false;
                    }

                    if (document.pressed == "선택삭제") {
                        if (!confirm("선택한 게시물을 정말 삭제하시겠습니까?\n\n한번 삭제한 자료는 복구할 수 없습니다"))
                            return false;
                    }

                    return true;
                }
            </script>
        <?php } ?>
    </div>
</div>