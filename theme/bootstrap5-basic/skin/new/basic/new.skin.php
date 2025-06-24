<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

// 선택삭제으로 인해 셀합치기가 가변적으로 변함
$colspan = 5;

if ($is_admin) $colspan++;

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/new/new-basic.css">', 150);
?>
<div class='new-list-wrap'>
    <div class="new-header container mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
            <span class='board-count-info text-secondary'>
                <span>Total <?php echo number_format($total_count) ?>건</span>
                <?php echo $page ?> 페이지
            </span>
        </div>
    </div>

    <!-- list -->
    <section class="container">
        <div class='bg-white p-2 p-lg-4 border'>
            <!-- 전체게시물 검색 -->
            <fieldset class="d-flex align-content-center justify-content-center mt-2 mb-4">
                <legend class="visually-hidden">상세검색</legend>
                <form name="fnew" method="get">
                    <div class="input-group">
                        <?php
                        $group_select = str_replace("sound_only", "visually-hidden", $group_select);
                        $group_select = str_replace('id="gr_id"', 'id="gr_id" class="form-control"', $group_select);
                        echo $group_select;
                        ?>
                        <label for="view" class="visually-hidden">검색대상</label>
                        <select name="view" id="view" class="form-select">
                            <option value="">전체</option>
                            <option value="w">원글만</option>
                            <option value="c">코멘트만</option>
                        </select>
                        <label for="mb_id" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
                        <input type="text" name="mb_id" value="<?php echo $mb_id ?>" id="mb_id" required class="form-control" size="40" placeholder="회원아이디 입력">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search" aria-hidden="true"></i> <span class="d-none d-md-inline">검색</span></button>
                    </div>
                </form>
                <script>
                    document.getElementById("gr_id").value = "<?php echo $gr_id ?>";
                    document.getElementById("view").value = "<?php echo $view ?>";
                </script>
            </fieldset>
            <form name="fnewlist" id="fnewlist" method="post" action="#" onsubmit="return fnew_submit(this);">
                <input type="hidden" name="sw" value="move">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
                <input type="hidden" name="stx" value="<?php echo $stx; ?>">
                <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
                <input type="hidden" name="page" value="<?php echo $page; ?>">
                <input type="hidden" name="pressed" value="">

                <?php if ($is_admin) { ?>
                    <div class="admin-button d-flex">
                        <button type="submit" onclick="document.pressed=this.title" title="선택삭제" class="btn btn-outline-danger ms-auto"><i class="bi bi-trash-fill" aria-hidden="true"></i><span class="">선택삭제</span></button>
                    </div>
                <?php } ?>
                <ul class="list-group list-group-flush">
                    <?php if ($is_admin) { ?>
                        <li class='list-group-item'>
                            <input type="checkbox" id="all_chk" class="form-checkbox">
                            <label for="all_chk">
                                <span></span>
                                <b class="visually-hidden">목록 전체</b>
                            </label>
                        </li>
                    <?php } ?>
                    <?php
                    for ($i = 0; $i < count($list); $i++) {
                        $num = $total_count - ($page - 1) * $config['cf_page_rows'] - $i;
                        $gr_subject = cut_str($list[$i]['gr_subject'], 20);
                        $bo_subject = cut_str($list[$i]['bo_subject'], 20);
                        $wr_subject = get_text(cut_str($list[$i]['wr_subject'], 80));
                    ?>
                        <li class='list-group-item d-flex justify-content-between'>
                            <?php if ($is_admin) { ?>
                                <div class='checkbox-wrap d-flex me-1'>
                                    <input type="checkbox" name="chk_bn_id[]" value="<?php echo $i; ?>" id="chk_bn_id_<?php echo $i; ?>" class="selec_chk">
                                    <label for="chk_bn_id_<?php echo $i; ?>">
                                        <span></span>
                                        <b class="visually-hidden"><?php echo $num ?>번</b>
                                    </label>
                                    <input type="hidden" name="bo_table[<?php echo $i; ?>]" value="<?php echo $list[$i]['bo_table']; ?>">
                                    <input type="hidden" name="wr_id[<?php echo $i; ?>]" value="<?php echo $list[$i]['wr_id']; ?>">
                                </div>
                            <?php } ?>
                            <div class='new-datetime d-none d-md-flex me-1 align-content-center flex-wrap'>
                                <span class="badge bg-secondary">
                                    <?php echo $list[$i]['datetime2'] ?>
                                </span>
                            </div>
                            <div class="new-grsubject d-none d-md-flex me-1 align-content-center flex-wrap">
                                <a href="./new.php?gr_id=<?php echo $list[$i]['gr_id'] ?>" class="d-flex"><span class="badge bg-secondary"><?php echo $gr_subject ?></span></a>
                            </div>
                            <div class="new-bosubject d-none d-md-flex align-content-center flex-wrap me-2">
                                <a href="<?php echo get_pretty_url($list[$i]['bo_table']); ?>" class="d-flex"><span class="badge bg-secondary"><?php echo $bo_subject ?></span></a>
                            </div>
                            <div class="new-subject d-flex flex-grow-1 align-content-center flex-wrap text-truncate">
                                <a href="<?php echo $list[$i]['href'] ?>" class="text-truncate"><?php echo $list[$i]['comment'] ?><?php echo $wr_subject ?></a>
                            </div>
                            <!-- 작성자 -->
                            <div class="dropdown align-content-center d-none d-md-flex flex-wrap me-2 writer align-content-center">
                                <a href="#name<?php echo $i ?>" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text($list[$i]['wr_name']) ?></a>
                                <div class="dropdown-menu p-2 shadow">
                                    <?php echo $list[$i]['name'] ?>
                                </div>
                            </div>
                        </li>
                    <?php } ?>

                    <?php if ($i === 0) {
                        echo '<li class="list-group-item"><p class="empty-item p-4 text-center">게시물이 없습니다.</p></li>';
                    }
                    ?>
                </ul>
                <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
                    <?php
                    //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
                    $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,"?gr_id=$gr_id&amp;view=$view&amp;mb_id=$mb_id&amp;page=");
                    $paging = str_replace("sound_only", "visually-hidden", $write_pages);
                    $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
                    $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
                    $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
                    $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
                    echo $paging;
                    ?>
                </div>
            </form>
        </div>
    </section>
    <?php if ($is_admin) { ?>
        <script>
            $(function() {
                $('#all_chk').click(function() {
                    $('[name="chk_bn_id[]"]').attr('checked', this.checked);
                });
            });

            function fnew_submit(f) {
                f.pressed.value = document.pressed;

                var cnt = 0;
                for (var i = 0; i < f.length; i++) {
                    if (f.elements[i].name == "chk_bn_id[]" && f.elements[i].checked)
                        cnt++;
                }

                if (!cnt) {
                    alert(document.pressed + "할 게시물을 하나 이상 선택하세요.");
                    return false;
                }

                if (!confirm("선택한 게시물을 정말 " + document.pressed + " 하시겠습니까?\n\n한번 삭제한 자료는 복구할 수 없습니다")) {
                    return false;
                }

                f.action = "./new_delete.php";

                return true;
            }
        </script>
    <?php } ?>
    <!-- } 전체게시물 목록 끝 -->
</div>