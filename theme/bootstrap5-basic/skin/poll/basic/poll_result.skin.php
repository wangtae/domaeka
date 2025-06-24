<?php

/**
 * 설문결과 basic
 */
if (!defined("_GNUBOARD_")) {
    exit;
}

$get_max_cnt = 0;

if ((int) $total_po_cnt > 0) {
    foreach ($list as $k => $v) {
        $get_max_cnt = max(array($get_max_cnt, $v['cnt'])); // 가장 높은 투표수를 뽑습니다.
    }
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/poll/poll.css">', 350);
?>

<div class="poll-basic-wrap">
    <div class="container-fluid mt-2">
        <div class="bg-white border p-4">
            <h1 id="win_title" class='fs-5 fw-bolder ff-noto'>
                <?php echo $g5['title'] ?>
            </h1>
        </div>
    </div>
    <div id="poll_result" class="new_win container-fluid mt-2">
        <div class="new_win_con2">
            <!-- 설문조사 결과 그래프 시작 { -->
            <span class="poll_all">전체
                <?php echo $nf_total_po_cnt ?>표
            </span>
            <section id="poll_result_list">
                <h2>
                    <?php echo $po_subject ?> 결과
                </h2>
                <ol>
                    <?php
                    for ($i = 1; $i <= count($list); $i++) {
                        // 가장 높은 투표수와 같으면 li 태그에 poll_1st 클래스가 붙습니다.
                        $poll_1st_class = ($get_max_cnt && ((int) $list[$i]['cnt'] === (int) $get_max_cnt)) ? 'poll_1st' : '';
                        ?>
                        <li class="<?php echo $poll_1st_class; ?>">
                            <span>
                                <?php echo $list[$i]['content'] ?>
                            </span>
                            <div class="poll_result_graph">
                                <span style="width:<?php echo number_format($list[$i]['rate'], 1) ?>%"></span>
                            </div>
                            <div class="poll_numerical">
                                <strong class="poll_cnt">
                                    <?php echo $list[$i]['cnt'] ?> 표
                                </strong>
                                <span class="poll_percent">
                                    <?php echo number_format($list[$i]['rate'], 1) ?> %
                                </span>
                            </div>
                        </li>
                    <?php } ?>
                </ol>
            </section>
            <!-- } 설문조사 결과 그래프 끝 -->

            <!-- 설문조사 기타의견 시작 { -->
            <?php if ($is_etc) { ?>
                <section id="poll_result_cmt">
                    <h2 class='fs-5 fw-bolder ff-noto'>이 설문에 대한 기타의견</h2>
                    <ul class="list-group mb-2">
                        <?php for ($i = 0; $i < count($list2); $i++) { ?>
                            <li class='list-group-item'>
                                <h6><i class="bi bi-person"></i>
                                    <?php echo $list2[$i]['pc_name'] ?><span class="visually-hidden">님의 의견</span>
                                </h6>
                                <span class="poll_datetime"><i class="fa fa-clock-o" aria-hidden="true"></i>
                                    <?php echo $list2[$i]['datetime'] ?>
                                </span>
                                <span class="poll_cmt_del">
                                    <?php if ($list2[$i]['del']) {
                                        echo $list2[$i]['del'] . "<i class='bi bi-trash-fill' aria-hidden='true'></i><span class='visually-hidden'>삭제</span></a>";
                                    } ?>
                                </span>
                                <p class='text-secondary'>
                                    <?php echo $list2[$i]['idea'] ?>
                                </p>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if ($member['mb_level'] >= $po['po_level']) { ?>
                        <form name="fpollresult" action="./poll_etc_update.php" onsubmit="return fpollresult_submit(this);"
                            method="post" autocomplete="off" id="poll_other_q">
                            <input type="hidden" name="po_id" value="<?php echo $po_id ?>">
                            <input type="hidden" name="w" value="">
                            <input type="hidden" name="skin_dir" value="<?php echo urlencode($skin_dir); ?>">
                            <?php if ($is_member) { ?><input type="hidden" name="pc_name"
                                    value="<?php echo get_text(cut_str($member['mb_nick'], 255)) ?>"><?php } ?>
                            <div id="poll_result_wcmt">
                                <h3><span>기타의견</span>
                                    <?php echo $po_etc ?>
                                </h3>
                                <div>
                                    <label for="pc_idea" class="visually-hidden">의견<strong>필수</strong></label>
                                    <input type="text" id="pc_idea" name="pc_idea" required class="form-control required"
                                        size="47" maxlength="100" placeholder="의견을 입력해주세요">
                                </div>
                            </div>
                            <?php if ($is_guest) { ?>
                                <div class="poll_guest">
                                    <label for="pc_name" class="visually-hidden">이름<strong>필수</strong></label>
                                    <input type="text" name="pc_name" id="pc_name" required class="form-control required" size="20"
                                        placeholder="이름">
                                </div>
                                <?php
                                $captcha_html = captcha_html();
                                $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                                $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                                $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);

                                echo $captcha_html;
                                ?>

                            <?php } ?>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-primary mt-2">의견남기기</button>
                            </div>
                        </form>
                    <?php } ?>

                </section>
            <?php } ?>
            <!-- } 설문조사 기타의견 끝 -->

            <!-- 설문조사 다른 결과 보기 시작 { -->
            <aside id="poll_result_oth">
                <div class='bg-white border p-4'>
                    <h2 class='fs-5 fw-bolder ff-noto'>다른 투표 결과 보기</h2>
                    <ul class="list-group list-group-flush">
                        <?php for ($i = 0; $i < count($list3); $i++) { ?>
                            <li class='list-group-item'><a
                                    href="./poll_result.php?po_id=<?php echo $list3[$i]['po_id'] ?>&amp;skin_dir=<?php echo urlencode($skin_dir); ?>">
                                    <?php echo $list3[$i]['subject'] ?> </a><span><i class="fa fa-clock-o"
                                        aria-hidden="true"></i>
                                    <?php echo $list3[$i]['date'] ?>
                                </span></li>
                        <?php } ?>
                    </ul>
                </div>

            </aside>
            <!-- } 설문조사 다른 결과 보기 끝 -->

            <div class="win_btn d-flex justify-content-sm-center mb-5">
                <button type="button" onclick="window.close();" class="btn btn-danger">창닫기</button>
            </div>
        </div>
    </div>
    <script>
        $(function () {
            $(".poll_delete").click(function () {
                if (!confirm("해당 기타의견을 삭제하시겠습니까?"))
                    return false;
            });
        });

        function fpollresult_submit(f) {
            <?php if ($is_guest) {
                echo chk_captcha_js();
            } ?>

            return true;
        }
    </script>
</div>