<?php
if (!defined("_GNUBOARD_")) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/search/search-basic.css">', 550);

?>
<div class="search-wrap container">
    <div class="bg-white p-3 p-lg-4 border mb-2 mt-2">
        <h2 class="fs-5 fw-bold ff-noto mb-0"><?php echo $g5['title'] ?></h2>
    </div>
    <div class="bg-white p-3 p-lg-4 border mb-2">
        <form name="fsearch" onsubmit="return fsearch_submit(this);" method="get">
            <input type="hidden" name="srows" value="<?php echo $srows ?>">
            <div class='search-container d-flex flex-column'>
                <div class="d-flex justify-content-center flex-wrap">
                    <div class="input-group ms-1 ms-md-5 me-1 me-md-5 ps-md-5 pe-md-5">
                        <?php echo str_replace('class="select"', 'class="form-select"', $group_select); ?>
                        <script>
                            document.getElementById("gr_id").value = "<?php echo $gr_id ?>";
                        </script>

                        <label for="sfl" class="visually-hidden">검색조건</label>
                        <select name="sfl" id="sfl" class="form-select">
                            <option value="wr_subject||wr_content" <?php echo get_selected($sfl, "wr_subject||wr_content") ?>>제목+내용</option>
                            <option value="wr_subject" <?php echo get_selected($sfl, "wr_subject") ?>>제목</option>
                            <option value="wr_content" <?php echo get_selected($sfl, "wr_content") ?>>내용</option>
                            <option value="mb_id" <?php echo get_selected($sfl, "mb_id") ?>>회원아이디</option>
                            <option value="wr_name" <?php echo get_selected($sfl, "wr_name") ?>>이름</option>
                        </select>

                        <label for="stx" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
                        <input type="text" name="stx" value="<?php echo $text_stx ?>" id="stx" required class="form-control" size="40">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search" aria-hidden="true"></i> 검색</button>
                    </div>
                </div>
                <script>
                    function fsearch_submit(f) {
                        var stx = f.stx.value.trim();
                        if (stx.length < 2) {
                            alert("검색어는 두글자 이상 입력하십시오.");
                            f.stx.select();
                            f.stx.focus();
                            return false;
                        }

                        // 검색에 많은 부하가 걸리는 경우 이 주석을 제거하세요.
                        var cnt = 0;
                        for (var i = 0; i < stx.length; i++) {
                            if (stx.charAt(i) == ' ')
                                cnt++;
                        }

                        if (cnt > 1) {
                            alert("빠른 검색을 위하여 검색어에 공백은 한개만 입력할 수 있습니다.");
                            f.stx.select();
                            f.stx.focus();
                            return false;
                        }
                        f.stx.value = stx;

                        f.action = "";
                        return true;
                    }
                </script>

                <div class="d-flex justify-content-center p-2">
                    <input type="radio" value="and" <?php echo ($sop == "and") ? "checked" : ""; ?> id="sop_and" name="sop">
                    <label for="sop_and">&nbsp;AND</label>&nbsp;&nbsp;
                    <input type="radio" value="or" <?php echo ($sop == "or") ? "checked" : ""; ?> id="sop_or" name="sop">
                    <label for="sop_or">&nbsp;OR</label>
                </div>
                </fieldset>
        </form>
    </div>
    <div class="bg-white p-3 p-lg-4 border mb-2">
        <div class="result-wrap">
            <?php if ($stx && $board_count) { ?>
                <section class="result-head p-2">
                    <h2 class="fs-5 fw-bolder ff-noto mb-0"><strong><?php echo $stx ?></strong> 전체검색 결과</h2>
                    <div class="d-flex flex-row justify-content-end mt-2">
                        <div class='d-flex me-2'>게시판 <?php echo $board_count ?>개</div>
                        <div class='d-flex me-2'>게시물 <?php echo number_format($total_count) ?>개</div>
                        <div class='d-flex'><?php echo number_format($page) ?>/<?php echo number_format($total_page) ?><span class="visually-hidden"> 페이지 열람 중</span></div>
                    </div>
                </section>
            <?php } ?>

            <?php
            if ($stx) {
                if ($board_count) {
            ?>
                    <ul class='list-group list-group-horizontal flex-wrap list-group-flush'>
                        <li class='list-group-item text-center m-1 flex-fill border'><a href="?<?php echo $search_query ?>&amp;gr_id=<?php echo $gr_id ?>" <?php echo $sch_all ?>>전체게시판</a></li>
                        <?php echo str_replace('<li>', "<li class='list-group-item text-center m-1 flex-fill border'>", $str_board_list); ?>
                    </ul>
                <?php } else { ?>
                    <div class="empty-item p-5 text-center">검색된 자료가 없습니다.</div>
            <?php }
            }  ?>

            <?php if ($stx && $board_count) { ?>
                <section class="search-list-wrap mt-5 border-top pt-5">
                <?php }  ?>
                <?php
                $k = 0;
                for ($idx = $table_index, $k = 0; $idx < count($search_table) && $k < $rows; $idx++) {
                ?>
                    <div class="board-result d-flex flex-column">
                        <h2 class="fs-6 fw-bold ff-noto mb-3"><a href="<?php echo get_pretty_url($search_table[$idx], '', $search_query); ?>"><?php echo $bo_subject[$idx] ?> 게시판 내 결과</a></h2>
                        <a href="<?php echo get_pretty_url($search_table[$idx], '', $search_query); ?>" class="ms-auto d-flex btn btn-outline-secondary btn-sm">더보기</a>
                        <ul class="list-group border-0">
                            <?php
                            for ($i = 0; $i < count($list[$idx]) && $k < $rows; $i++, $k++) {
                                if ($list[$idx][$i]['wr_is_comment']) {
                                    $comment_def = '<span class="cmt_def"><i class="fa fa-commenting-o" aria-hidden="true"></i><span class="visually-hidden">댓글</span></span> ';
                                    $comment_href = '#c_' . $list[$idx][$i]['wr_id'];
                                } else {
                                    $comment_def = '';
                                    $comment_href = '';
                                }
                            ?>

                                <li class="list-group-item border-0 mb-5">
                                    <div class="result-title">
                                        <a href="<?php echo $list[$idx][$i]['href'] ?><?php echo $comment_href ?>" class="fw-bolder"><?php echo $comment_def ?><?php echo $list[$idx][$i]['subject'] ?></a>
                                        <a href="<?php echo $list[$idx][$i]['href'] ?><?php echo $comment_href ?>" target="_blank" class="pop_a"><i class="fa fa-window-restore" aria-hidden="true"></i><span class="visually-hidden">새창</span></a>
                                    </div>
                                    <p class="result-sumary"><?php echo $list[$idx][$i]['content'] ?></p>
                                    <div class="result-write d-flex justify-content-end">
                                        <?php echo $list[$idx][$i]['wr_name'] ?>
                                        <span class="sch_datetime"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo $list[$idx][$i]['wr_datetime'] ?></span>
                                    </div>
                                </li>
                            <?php }  ?>
                        </ul>
                    </div>
                <?php }        //end for
                ?>
                <?php if ($stx && $board_count) {  ?>
                </section>
            <?php }  ?>

            <?php echo $write_pages ?>

        </div>
        <!-- } 전체검색 끝 -->
    </div>
</div>