<?php

/**
 * board list gallery
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
include_once G5_LIB_PATH . '/thumbnail.lib.php';
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/board/gallery/gallery-list.css">', 100);
?>
<div class='board-list-header container mt-2'>
    <div class="p-3 p-lg-4 bg-white border border-opacity-25 mb-2 d-flex flex-row">
        <div class='board-title'>
            <h1 class="fs-1 fw-bolder flex-grow-1 d-flex align-items-center"><?php echo $board['bo_subject'] ?></h1>
            <div class="d-flex align-items-center text-secondary">
                <span>Total <?php echo number_format($total_count) ?>건</span>
                <?php echo $page ?> 페이지
            </div>
        </div>
        <div class="ms-auto">
            <?php if ($admin_href) { ?>
                <a href="<?php echo $admin_href ?>" class="btn btn-white fs-2 text-secondary" title="관리자"><i class="bi bi-gear-fill"></i><span class="visually-hidden">관리자</span></a>
            <?php } ?>
            <?php if ($rss_href) { ?>
                <a href="<?php echo $rss_href ?>" class="btn btn-white fs-2 text-secondary" title="RSS"><i class="bi bi-rss-fill"></i><span class="visually-hidden">RSS</span></a>
            <?php } ?>
        </div>
    </div>
</div>
<!-- 목록 -->
<article class='board-wrap basic list container'>
    <!-- 폼 -->
    <form name="fboardlist" id="fboardlist" action="<?php echo G5_BBS_URL; ?>/board_list_update.php" onsubmit="return fboardlist_submit(this);" method="post">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
        <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
        <input type="hidden" name="stx" value="<?php echo $stx ?>">
        <input type="hidden" name="spt" value="<?php echo $spt ?>">
        <input type="hidden" name="sca" value="<?php echo $sca ?>">
        <input type="hidden" name="sst" value="<?php echo $sst ?>">
        <input type="hidden" name="sod" value="<?php echo $sod ?>">
        <input type="hidden" name="page" value="<?php echo $page ?>">
        <input type="hidden" name="sw" value="">

        <!-- 게시판 버튼 -->
        <div class="btn-toolbar bg-white mb-2 board-buttons p-2 p-lg-3 border" role="toolbar" aria-label="Toolbar">
            <div class="btn-group me-2" role="group" aria-label="category, write">
                <!-- 분류 -->
                <?php if ($is_category) { ?>
                    <div class="category-wrap">
                        <div class="dropdown">
                            <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-tags-fill"></i> <span class="d-none d-md-inline">분류</span>
                            </button>
                            <ul class="dropdown-menu shadow">
                                <?php echo str_replace("href=", "class='dropdown-item' href=", $category_option) ?>
                            </ul>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($write_href) { ?>
                    <a href="<?php echo $write_href ?>" class="btn btn-outline-primary ms-1" title="글쓰기"><i class="fa fa-pencil" aria-hidden="true"></i><span class="d-none d-lg-inline"> 글쓰기</span></a>
                <?php } ?>
            </div>
            <div class="btn-group ms-auto me-1" role="group" aria-label="category, write">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#boardSearch" title="게시판 검색"><i class="fa fa-search" aria-hidden="true"></i><span class="d-none d-lg-inline"> 검색</span></button>
                <?php if ($stx) { ?>
                    <a href='<?php echo $list_href ?>' class="btn btn-outline-secondary ms-1">목록으로</a>
                <?php } ?>
            </div>

            <?php if ($is_admin == 'super' || $is_auth) {  ?>
                <!-- 관리자 메뉴 -->
                <div class="btn-group" role="group" aria-label="category, write">
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-clipboard-check-fill"></i> <span class="d-none d-md-inline">관리</span></button>
                        <?php if ($is_checkbox) { ?>
                            <ul class="dropdown-menu shadow">
                                <li><button type="submit" name="btn_submit" value="선택삭제" onclick="document.pressed=this.value" class='dropdown-item'><i class="fa fa-trash-o" aria-hidden="true"></i> 선택삭제</button></li>
                                <li><button type="submit" name="btn_submit" value="선택복사" onclick="document.pressed=this.value" class='dropdown-item'><i class="fa fa-files-o" aria-hidden="true"></i> 선택복사</button></li>
                                <li><button type="submit" name="btn_submit" value="선택이동" onclick="document.pressed=this.value" class='dropdown-item'><i class="fa fa-arrows" aria-hidden="true"></i> 선택이동</button></li>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            <?php }  ?>
        </div>
        <!--//.board-buttons-->

        <div class="board-list">
            <ul class="list-group mb-2">
                <li class="list-group-item board-list-head d-flex flex-row">
                    <span class="visually-hidden"><?php echo $board['bo_subject'] ?> 목록</span>
                    <?php if ($is_checkbox) { ?>
                        <div class='checkbox-wrap me-auto d-flex align-content-center'>
                            <input type="checkbox" id="chkall" onclick="if (this.checked) all_checked(true); else all_checked(false);" class="selec_chk">
                            <label for="chkall"><span class="visually-hidden">현재 페이지 게시물 전체선택</span></label>
                        </div>
                    <?php } ?>
                    <div class="sort-wrap">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">정렬</button>
                            <div class="dropdown-menu shadow p-2">
                                <div class=' d-flex flex-column'>
                                    <?php
                                    //그누보드 게시판 정렬 기능 짧은주소 지원안함;
                                    if (isset($sst) && isset($sod) && $sst && $sod) {
                                        echo "<a href='" . get_pretty_url($bo_table) . "' class=''><i class='bi bi-x-circle'></i> 정렬취소</a>";
                                    }

                                    //조회수 정렬
                                    echo "<a href='" . askseko_subject_sort_link('wr_hit', $qstr2, 1) . "'>";
                                    echo "<i class='bi bi-dot'></i> 조회";
                                    if ((isset($sst) && isset($sod)) && $sst == 'wr_hit' && $sod == 'asc') {
                                        echo ' <i class="bi bi-sort-down"></i>';
                                    }
                                    if ((isset($sst) && isset($sod)) && $sst == 'wr_hit' && $sod == 'desc') {
                                        echo ' <i class="bi bi-sort-up"></i>';
                                    }
                                    echo '</a>';

                                    //추천
                                    if ($is_good) {
                                        echo "<a href='" . askseko_subject_sort_link('wr_good', $qstr2, 1) . "'>";
                                        echo "<i class='bi bi-dot'></i> 추천";
                                        if ((isset($sst) && isset($sod)) && $sst == 'wr_good' && $sod == 'asc') {
                                            echo ' <i class="bi bi-sort-down"></i>';
                                        }
                                        if ((isset($sst) && isset($sod)) && $sst == 'wr_good' && $sod == 'desc') {
                                            echo ' <i class="bi bi-sort-up"></i>';
                                        }
                                        echo '</a>';
                                    }
                                    //비추천
                                    if ($is_nogood) {
                                        echo "<a href='" . askseko_subject_sort_link('wr_nogood', $qstr2, 1) . "'>";
                                        echo "<i class='bi bi-dot'></i> 비추천";
                                        if ((isset($sst) && isset($sod)) && $sst == 'wr_nogood' && $sod == 'asc') {
                                            echo ' <i class="bi bi-sort-up"></i>';
                                        }
                                        if ((isset($sst) && isset($sod)) && $sst == 'wr_nogood' && $sod == 'desc') {
                                            echo ' <i class="bi bi-sort-down"></i>';
                                        }
                                        echo '</a>';
                                    }
                                    //날짜
                                    echo "<a href='" . askseko_subject_sort_link('wr_datetime', $qstr2, 1) . "'>";
                                    echo "<i class='bi bi-dot'></i> 날짜";
                                    if ((isset($sst) && isset($sod)) && $sst == 'wr_datetime' && $sod == 'asc') {
                                        echo ' <i class="bi bi-sort-down"></i>';
                                    }
                                    if ((isset($sst) && isset($sod)) && $sst == 'wr_datetime' && $sod == 'desc') {
                                        echo ' <i class="bi bi-sort-up"></i>';
                                    }
                                    echo '</a>';
                                    ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </li>

                <li class="list-group-item pt-4 pb-4">
                    <div class='row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4'>
                        <?php
                        for ($i = 0; $i < count($list); $i++) {
                            $_thumb = get_list_thumbnail($board['bo_table'], $list[$i]['wr_id'], $board['bo_gallery_width'], $board['bo_gallery_height'], false, true);
                            $img = '';
                            if ($_thumb['src']) {
                                $img = "<img src='{$_thumb['src']}' class='card-img-top img-fluid'>";
                            } else {
                                $img = "<img src='" . G5_THEME_URL . "/img/noimage.png' class='card-img-top img-fluid'>";
                            }
                        ?>
                            <div class="col">
                                <div class='card shadow-sm border-opacity-25'>
                                    <div class="top-image position-relative">
                                        <div class='position-absolute checkbox-wrap'>
                                            <?php if ($is_checkbox) { ?>
                                                <input type="checkbox" name="chk_wr_id[]" value="<?php echo $list[$i]['wr_id'] ?>" id="chk_wr_id_<?php echo $i ?>" class="selec_chk">
                                                <label for="chk_wr_id_<?php echo $i ?>">
                                                    <span></span>
                                                    <span class="visually-hidden"><?php echo $list[$i]['subject'] ?></span>
                                                </label>
                                            <?php } ?>
                                        </div>
                                        <?php
                                        //분류
                                        if ($is_category && $list[$i]['ca_name']) { ?>
                                            <div class="shadow opacity-75 position-absolute category-name">
                                                <a href="<?php echo $list[$i]['ca_name_href'] ?>" class="badge text-bg-warning"><?php echo $list[$i]['ca_name'] ?></a>
                                            </div>
                                        <?php } ?>
                                        <div class="ratio ratio-4x3">
                                            <a href="<?php echo $list[$i]['href'] ?>"><?php echo $img ?></a>
                                        </div>
                                        <div class='badge-wrap position-absolute d-flex flex-row opacity-75 shadow'>
                                            <?php if ($list[$i]['comment_cnt']) { ?>
                                                <div class='align-content-center d-flex flex-wrap'>
                                                    <span class="visually-hidden">댓글</span>
                                                    <span class="badge text-bg-light">
                                                        <i class="bi bi-chat"></i>
                                                        <?php echo $list[$i]['wr_comment']; ?></span>
                                                    <span class="visually-hidden">개</span>
                                                </div>
                                            <?php } ?>
                                            <?php
                                            //아이콘 - 모바일은 출력하지 않는다.
                                            if (isset($list[$i]['icon_new']) && $list[$i]['icon_new'] != '') {
                                                echo "<div class='icon-new'>";
                                                echo "<span class='badge text-bg-light fw-bolder'><i class='bi bi-app-indicator'></i><span class='visually-hidden'>새글</span></span> ";
                                                echo "</div>";
                                            }
                                            if (isset($list[$i]['icon_hot']) && $list[$i]['icon_hot'] != '') {
                                                echo "<div class='icon-hot'>";
                                                echo "<span class='badge text-bg-light'><i class='bi bi-heart-fill'></i><span class='visually-hidden'>인기</span></span> ";
                                                echo "</div>";
                                            }
                                            if (isset($list[$i]['icon_file']) && $list[$i]['icon_file'] != '') {
                                                echo "<div class='icon-file'>";
                                                echo "<span class='badge text-bg-light'><i class='bi bi-file-earmark'></i><span class='visually-hidden'>파일</span></span> ";
                                                echo "</div>";
                                            }
                                            if (isset($list[$i]['icon_link']) && $list[$i]['icon_link'] != '') {
                                                echo "<div class='icon-link'>";
                                                echo "<span class='badge text-bg-light'><i class='bi bi-link-45deg'></i><span class='visually-hidden'>링크</span></span> ";
                                                echo "</div>";
                                            }
                                            if (isset($list[$i]['icon_secret']) && $list[$i]['icon_secret'] != '') {
                                                echo "<div class='icon-secret'>";
                                                echo "<span class='badge text-bg-light'><i class='bi bi-lock-fill'></i><span class='visually-hidden'>비밀글</span></span> ";
                                                echo "</div>";
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <!-- 제목 -->
                                    <div class="board-subject card-body ">
                                        <a href="<?php echo $list[$i]['href'] ?>" class="card-title d-block text-truncate">
                                            <?php echo $list[$i]['subject']; ?>
                                        </a>
                                    </div>

                                    <div class="card-footer d-flex">
                                        <!-- 작성자 -->
                                        <div class="dropdown writer">
                                            <a href="#name<?php echo $i ?>" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text($list[$i]['wr_name']) ?></a>
                                            <div class="dropdown-menu p-2 shadow">
                                                <?php echo $list[$i]['name'] ?>
                                            </div>
                                        </div>

                                        <div class='ms-auto write-info-wrap'>
                                            <?php if ($is_good) { ?>
                                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                                <?php echo $list[$i]['wr_good'] ?>
                                                <span class="visually-hidden">추천수</span>
                                            <?php } ?>
                                            <?php if ($is_nogood) { ?>
                                                <i class="bi bi-hand-thumbs-down-fill"></i>
                                                <?php echo $list[$i]['wr_nogood'] ?>
                                                <span class="visually-hidden">비추천수</span>
                                            <?php } ?>
                                            <i class="bi bi-eye-fill"></i>
                                            <?php echo $list[$i]['wr_hit'] ?>

                                            <span class="visually-hidden">작성일</span>
                                            <span class="badge text-bg-light"><?php echo $list[$i]['datetime2'] ?></span>
                                        </div>
                                    </div>
                                </div><!--//.card-->
                            </div>
                            <!--//.col-->
                        <?php } ?>
                        <?php if (count($list) == 0) {
                            echo "<li class='list-group-item text-center p-5'> 게시물이 없습니다.</li>";
                        } ?>
                    </div>
                </li>
            </ul>
            <!--//.list-group -->
        </div>
        <!--//.board-list -->

        <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
            <?php
            //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
            $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, get_pretty_url($bo_table, '', $qstr . '&amp;page='));
            $paging = str_replace("sound_only", "visually-hidden", $write_pages);
            $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
            $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
            $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
            $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
            echo $paging;
            ?>
        </div>

        <!-- 하단 버튼 -->
        <?php if ($list_href || $is_checkbox || $write_href) { ?>
            <div class="button-bottom-wrap">
                <?php if ($list_href || $write_href) { ?>
                    <div class="d-flex justify-content-end">
                        <?php if ($write_href) { ?>
                            <a href="<?php echo $write_href ?>" class="btn btn-outline-primary" title="글쓰기"><i class="bi bi-pencil" aria-hidden="true"></i> 글쓰기</a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </form>
    <!-- Modal 검색창 -->
    <div class="modal fade" id="boardSearch" tabindex="-1" aria-labelledby="boardSearchLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="boardSearchLabel">게시판 검색</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- 게시판 검색 시작 -->
                    <div class="board-search-wrap">
                        <h3 class="visually-hidden">검색</h3>
                        <form name="fsearch" method="get">
                            <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
                            <input type="hidden" name="sca" value="<?php echo $sca ?>">
                            <input type="hidden" name="sop" value="and">
                            <label for="sfl" class="visually-hidden">검색대상</label>
                            <div class="input-group">
                                <select name="sfl" id="sfl" class="form-control">
                                    <?php echo get_board_sfl_select_options($sfl); ?>
                                </select>
                                <label for="stx" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
                                <input type="text" name="stx" value="<?php echo stripslashes($stx) ?>" required id="stx" class="form-control" size="25" maxlength="20" placeholder=" 검색어를 입력해주세요">
                                <button type="submit" value="검색" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">검색</span></button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</article>

<?php if ($is_checkbox) { ?>
    <noscript>
        <p>자바스크립트를 사용하지 않는 경우<br>별도의 확인 절차 없이 바로 선택삭제 처리하므로 주의하시기 바랍니다.</p>
    </noscript>
<?php } ?>

<?php if ($is_checkbox) { ?>
    <script>
        function all_checked(sw) {
            var f = document.fboardlist;

            for (var i = 0; i < f.length; i++) {
                if (f.elements[i].name == "chk_wr_id[]")
                    f.elements[i].checked = sw;
            }
        }

        function fboardlist_submit(f) {
            var chk_count = 0;

            for (var i = 0; i < f.length; i++) {
                if (f.elements[i].name == "chk_wr_id[]" && f.elements[i].checked)
                    chk_count++;
            }

            if (!chk_count) {
                alert(document.pressed + "할 게시물을 하나 이상 선택하세요.");
                return false;
            }

            if (document.pressed == "선택복사") {
                select_copy("copy");
                return;
            }

            if (document.pressed == "선택이동") {
                select_copy("move");
                return;
            }

            if (document.pressed == "선택삭제") {
                if (!confirm("선택한 게시물을 정말 삭제하시겠습니까?\n\n한번 삭제한 자료는 복구할 수 없습니다\n\n답변글이 있는 게시글을 선택하신 경우\n답변글도 선택하셔야 게시글이 삭제됩니다."))
                    return false;

                f.removeAttribute("target");
                f.action = g5_bbs_url + "/board_list_update.php";
            }

            return true;
        }

        // 선택한 게시물 복사 및 이동
        function select_copy(sw) {
            var f = document.fboardlist;

            if (sw == "copy")
                str = "복사";
            else
                str = "이동";

            var sub_win = window.open("", "move", "left=50, top=50, width=500, height=550, scrollbars=1");

            f.sw.value = sw;
            f.target = "move";
            f.action = g5_bbs_url + "/move.php";
            f.submit();
        }

        // 게시판 리스트 관리자 옵션
        jQuery(function($) {
            $(".btn_more_opt.is_list_btn").on("click", function(e) {
                e.stopPropagation();
                $(".more_opt.is_list_btn").toggle();
            });
            $(document).on("click", function(e) {
                if (!$(e.target).closest('.is_list_btn').length) {
                    $(".more_opt.is_list_btn").hide();
                }
            });
        });
    </script>
<?php } ?>
<!-- } 게시판 목록 끝 -->