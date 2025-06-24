<?php
/**
 * gallery view
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
include_once G5_LIB_PATH.'/thumbnail.lib.php';
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/board/gallery/gallery-view.css">', 90);
?>
<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<!-- 게시물 보기 -->
<div class='container board-basic-view mt-2'>
    <div class="view-head border bg-white p-3 p-lg-4 mb-2">
        <span class="board-subject title"><?php echo $board['bo_subject'] ?></span>
        <h1 class='view-title'>
            <?php
            if ($category_name) {
                echo "<span class='badge bg-info'>" . $view['ca_name'] . "</span>";
            } ?>
            <span class="title">
                <?php echo get_text($view['wr_subject']); ?>
            </span>
        </h1>
    </div>

    <section class='view-info bg-white border p-1 p-lg-3 mb-2'>
        <span class="visually-hidden">페이지 정보</span>
        <div class="writer-info">
            <div class="d-flex">
                <div class="profile-image d-flex align-content-center flex-wrap">
                    <?php echo str_replace('alt="profile_image"', 'class="img-fluid rounded me-2" alt="profile image"', get_member_profile_img($view['mb_id'])); ?>
                </div>
                <div class="d-flex me-auto">
                    <span class="visually-hidden">작성자</span>
                    <!-- 작성자 -->
                    <div class="dropdown align-content-center d-flex flex-wrap writer">
                        <a href="#name" class="badge text-bg-light dropdown-toggle fs-6" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text($view['wr_name']) ?></a>
                        <div class="dropdown-menu p-2 shadow">
                            <?php echo $view['name'] ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex ms-auto align-content-center flex-wrap">
                    <ul class="list-group list-group-horizontal-md write-info-ip">
                        <?php if ($is_ip_view) {
                            echo "<li class='list-group-item p-1 p-lg-2'>{$ip}</li>";
                        } ?>
                        <li class='list-group-item p-1 p-lg-2'><a href="#bo_vc"><i class="bi bi-chat-fill" aria-hidden="true"></i> <?php echo number_format($view['wr_comment']) ?>건</a></li>
                        <li class='list-group-item p-1 p-lg-2'><i class="bi bi-eye" aria-hidden="true"></i> <?php echo number_format($view['wr_hit']) ?>회</li>
                        <li class='list-group-item p-1 p-lg-2'><i class="bi bi-clock-fill" aria-hidden="true"></i> <?php echo date("y-m-d H:i", strtotime($view['wr_datetime'])) ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <!-- 상단 주요 버튼 -->
    <?php ob_start(); ?>
    <div class='button-wrap bg-white p-2'>
        <div class='btn-toolbar d-flex'>
            <div class='btn-group btn-group-sm me-2'>
                <a href="<?php echo $list_href ?>" class="btn btn-success" title="목록"><i class="bi bi-list" aria-hidden="true"></i> <span class="d-none d-md-inline">목록</span></a>
            </div>
            <div class='btn-group btn-group-sm me-auto'>
                <?php if (isset($reply_href) && $reply_href != '') { ?>
                    <a href="<?php echo $reply_href ?>" class="btn btn-outline-primary me-2" title="답변"><i class="bi bi-reply" aria-hidden="true"> </i><span class="d-none d-md-inline">답변</span></a>
                <?php } ?>
                <?php if (isset($write_href) && $write_href != '') { ?>
                    <a href="<?php echo $write_href ?>" class="btn btn-outline-primary" title="글쓰기"><i class="bi bi-pencil" aria-hidden="true"></i> <span class="d-none d-md-inline">글쓰기</span></a>
                <?php } ?>
                <?php if (isset($update_href) && $update_href != '') { ?>
                    <a href="<?php echo $update_href ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">수정</span></a>
                <?php } ?>
            </div>
            <div class='btn-group btn-group-sm'>
                <?php if (isset($delete_href) && $delete_href != '') { ?>
                    <a href="<?php echo $delete_href ?>" onclick="del(this.href); return false;" class="btn btn-outline-secondary"><i class="bi bi-trash-fill" aria-hidden="true"></i> <span class="d-none d-md-inline">삭제</span> </a>
                <?php } ?>
                <?php if (isset($copy_href) && $copy_href != '') { ?>
                    <a href="<?php echo $copy_href ?>" onclick="board_move(this.href); return false;" class="btn btn-outline-secondary"> <i class="bi bi-clipboard2-check-fill"></i> <span class="d-none d-md-inline">복사</span></a>
                <?php } ?>
                <?php if (isset($move_href) && $move_href != '') { ?>
                    <a href="<?php echo $move_href ?>" onclick="board_move(this.href); return false;" class="btn btn-outline-secondary"> <i class="bi bi-arrows-move"></i> <span class="d-none d-md-inline">이동</span></a>
                <?php } ?>
                <?php if (isset($search_href) && $search_href != '') { ?>
                    <a href="<?php echo $search_href ?>" class="btn btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i> <span class="d-none d-md-inline">검색목록</span></a>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php
    $link_buttons = ob_get_contents();
    ob_end_flush();
    ?>
    <section class='view-contents bg-white border p-2 p-lg-4 mb-5 position-relative'>
        <span class="visually-hidden">본문</span>
        <div class="sns-share-wrap position-absolute">
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-share-fill"></i>
                </button>
                <ul class="dropdown-menu shadow">
                    <?php include_once G5_SNS_PATH . "/view.bootstrap.sns.skin.php"; ?>
                    <?php if ($scrap_href) { ?>
                        <a href="<?php echo $scrap_href;  ?>" target="_blank" class="dropdown-item" onclick="win_scrap(this.href); return false;"><i class="bi bi-bookmark-fill"></i> 스크랩</a>
                    <?php } ?>
                </ul>
            </div>

        </div>

        <?php
        // 파일 출력
        $v_img_count = count($view['file']);
        if ($v_img_count) {
            echo "<div class='contents-image-wrap mt-5'>\n";
            foreach ($view['file'] as $view_file) {
                echo get_file_thumbnail($view_file);
            }

            echo "</div>\n";
        }
        ?>

        <!-- 내용 출력 -->
        <article class="contents-wrap">
            <?php echo get_view_thumbnail($view['content']); ?>
        </article>
        <?php if (isset($is_signature) && $is_signature) { ?>
            <div class="sign-wrap mt-4 mb-4">
                <?php echo nl2br($signature) ?>
            </div>
        <?php } ?>

        <!--  추천 비추천 시작 -->
        <div class='good-nogood-wrap d-flex justify-content-center'>
            <?php if (isset($good_href) && $good_href != '') { ?>
                <div class="p-2">
                    <a href="<?php echo $good_href . '&amp;' . $qstr ?>" id="good_button" class="btn btn-outline-secondary"><i class="bi bi-hand-thumbs-up"></i><span class="visually-hidden">추천</span><span class='good-nogood-number'><?php echo number_format($view['wr_good']) ?></span></a>
                </div>
            <?php } ?>
            <?php if (isset($nogood_href) && $nogood_href != '') { ?>
                <div class="p-2">
                    <a href="<?php echo $nogood_href . '&amp;' . $qstr ?>" id="nogood_button" class="btn btn-outline-secondary"><i class="bi bi-hand-thumbs-down"></i><span class="visually-hidden">비추천</span><span class='good-nogood-number'><?php echo number_format($view['wr_nogood']) ?></span></a>
                </div>
            <?php } ?>
        </div>
        <?php if (($board['bo_use_good'] || $board['bo_use_nogood']) && !$is_member) { ?>
            <!-- 비회원용출력 -->
            <div class='good-nogood-wrap d-flex justify-content-center'>
                <?php if ($board['bo_use_good']) { ?>
                    <button class="btn btn-outline-secondary me-3" onclick="alert('로그인 후 이용하세요.');"><i class="bi bi-hand-thumbs-up"></i><span class="visually-hidden">추천</span> <?php echo number_format($view['wr_good']) ?></button>
                <?php } ?>
                <?php if ($board['bo_use_nogood']) { ?>
                    <button class="btn btn-outline-secondary" onclick="alert('로그인 후 이용하세요.');"><i class="bi bi-hand-thumbs-down"></i><span class="visually-hidden">비추천</span> <?php echo number_format($view['wr_nogood']) ?></button>
                <?php } ?>
            </div>
        <?php } ?>
    </section>

    <?php
    //첨부파일
    $cnt = 0;
    if ($view['file']['count']) {
        for ($i = 0; $i < count($view['file']); $i++) {
            if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
                $cnt++;
            }
        }
    }
    if ($cnt) { ?>
        <!-- 첨부파일 -->
        <section class="attach-file bg-white border p-1 p-lg-2 mb-2">
            <span class="visually-hidden">첨부파일</span>
            <ul class="list-group list-group-flush">
                <?php
                // 가변 파일
                for ($i = 0; $i < count($view['file']); $i++) {
                    if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
                ?>
                        <li class="list-group-item">
                            <a href="<?php echo $view['file'][$i]['href'];  ?>" class="attach-file-download d-flex d-md-inline">
                                <i class="bi bi-archive"></i>&nbsp;
                                <?php echo $view['file'][$i]['source'] ?> <?php echo $view['file'][$i]['content'] ?> (<?php echo $view['file'][$i]['size'] ?>)
                            </a>
                            <span class="bo_v_file_cnt"><?php echo $view['file'][$i]['download'] ?>회 다운로드 | DATE : <?php echo $view['file'][$i]['datetime'] ?></span>
                        </li>
                <?php
                    }
                }
                ?>
            </ul>
        </section>
    <?php } ?>

    <?php if (isset($view['link']) && array_filter($view['link'])) { ?>
        <!-- 관련링크  -->
        <section class='link-wrap bg-white border p-1 p-lg-2 mb-2'>
            <span class='visually-hidden'>관련링크</span>
            <ul class="list-group list-group-flush">
                <?php
                // 링크
                $cnt = 0;
                for ($i = 1; $i <= count($view['link']); $i++) {
                    if ($view['link'][$i]) {
                        $cnt++;
                        $link = cut_str($view['link'][$i], 70);
                ?>
                        <li class="list-group-item">
                            <a href="<?php echo $view['link_href'][$i] ?>" target="_blank" class="d-flex d-md-inline">
                                <i class="bi bi-link" aria-hidden="true"></i> &nbsp;
                                <?php echo $link ?>
                            </a>
                            <span class="bo_v_link_cnt"><?php echo $view['link_hit'][$i] ?>회 연결</span>
                        </li>
                <?php
                    }
                }
                ?>
            </ul>
        </section>
    <?php } ?>

    <!-- 이전, 다음 링크 -->
    <ul class="list-group list-group-horizontal next-prev-wrap">
        <?php if (isset($prev_href) && $prev_href != '') { ?>
            <li class="list-group-item w-50 text-truncate">
                <span class="badge text-bg-success"><i class="bi bi-chevron-left" aria-hidden="true"></i> <span class='d-none d-md-inline'>이전글</span></span>
                <a href="<?php echo $prev_href ?>"><?php echo $prev_wr_subject; ?></a>
            </li>
        <?php } ?>
        <?php if (isset($next_href) && $next_href != '') { ?>
            <li class="list-group-item w-50 text-truncate">
                <span class="badge text-bg-success"><i class="bi bi-chevron-right" aria-hidden="true"></i> <span class='d-none d-md-inline'>다음글</span></span>
                <a href="<?php echo $next_href ?>"><?php echo $next_wr_subject; ?></a>
            </li>
        <?php } ?>
    </ul>

    <?php
    // 코멘트 입출력
    include_once(G5_BBS_PATH . '/view_comment.php');
    ?>
</div>

<script>
    <?php if ($board['bo_download_point'] < 0) { ?>
        $(function() {
            $("a.attach-file-download").click(function() {
                if (!g5_is_member) {
                    alert("다운로드 권한이 없습니다.\n회원이시라면 로그인 후 이용해 보십시오.");
                    return false;
                }

                var msg = "파일을 다운로드 하시면 포인트가 차감(<?php echo number_format($board['bo_download_point']) ?>점)됩니다.\n\n포인트는 게시물당 한번만 차감되며 다음에 다시 다운로드 하셔도 중복하여 차감하지 않습니다.\n\n그래도 다운로드 하시겠습니까?";

                if (confirm(msg)) {
                    var href = $(this).attr("href") + "&js=on";
                    $(this).attr("href", href);

                    return true;
                } else {
                    return false;
                }
            });
        });
    <?php } ?>

    function board_move(href) {
        window.open(href, "boardmove", "left=50, top=50, width=500, height=550, scrollbars=1");
    }
</script>

<script>
    $(function() {
        $("a.view_image").click(function() {
            window.open(this.href, "large_image", "location=yes,links=no,toolbar=no,top=10,left=10,width=10,height=10,resizable=yes,scrollbars=no,status=no");
            return false;
        });

        // 추천, 비추천
        $("#good_button, #nogood_button").click(function() {
            var $tx;
            if (this.id == "good_button")
                $tx = $("#bo_v_act_good");
            else
                $tx = $("#bo_v_act_nogood");

            excute_good(this.href, $(this), $tx);
            return false;
        });

        // 이미지 리사이즈
        $("#bo_v_atc").viewimageresize();
    });

    function excute_good(href, $el, $tx) {
        $.post(
            href, {
                js: "on"
            },
            function(data) {
                if (data.error) {
                    alert(data.error);
                    return false;
                }

                if (data.count) {
                    $el.find(".good-nogood-number").text(number_format(String(data.count)));
                }
            }, "json"
        );
    }
</script>
<!--//게시물 보기 -->