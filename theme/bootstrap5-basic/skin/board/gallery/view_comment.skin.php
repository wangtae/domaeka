<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
?>

<script>
    // 글자수 제한
    var char_min = parseInt(<?php echo $comment_min ?>); // 최소
    var char_max = parseInt(<?php echo $comment_max ?>); // 최대
</script>
<button type="button" class="btn btn-outline-secondary cmt_btn"><span class="total"><b>댓글</b> <?php echo $view['wr_comment']; ?></span><span class="cmt_more"></span></button>
<!-- 댓글 시작 { -->
<section class="comments-basic bg-white border p-2 p-lg-4 mb-2" id="bo_vc">
    <span class="visually-hidden">댓글목록</span>
    <?php
    $cmt_amt = count($list);
    for ($i = 0; $i < $cmt_amt; $i++) {
        $comment_id = $list[$i]['wr_id'];
        $cmt_depth = strlen($list[$i]['wr_comment_reply']) * 20;
        $comment = $list[$i]['content'];
        /*
        if (strstr($list[$i]['wr_option'], "secret")) {
            $str = $str;
        }
        */
        $comment = preg_replace("/\[\<a\s.*href\=\"(http|https|ftp|mms)\:\/\/([^[:space:]]+)\.(mp3|wma|wmv|asf|asx|mpg|mpeg)\".*\<\/a\>\]/i", "<script>doc_write(obj_movie('$1://$2.$3'));</script>", $comment);
        $c_reply_href = $comment_common_url . '&amp;c_id=' . $comment_id . '&amp;w=c#bo_vc_w';
        $c_edit_href = $comment_common_url . '&amp;c_id=' . $comment_id . '&amp;w=cu#bo_vc_w';
        $is_comment_reply_edit = ($list[$i]['is_reply'] || $list[$i]['is_edit'] || $list[$i]['is_del']) ? 1 : 0;
    ?>

        <div class='comments-wrap d-flex flex-row mb-3 pb-3 border-bottom' id="c_<?php echo $comment_id ?>" <?php if ($cmt_depth) { ?>style="margin-left:<?php echo $cmt_depth ?>px;border-top-color:#e0e0e0" <?php } ?>>
            <div class='d-flex me-2'>
                <div class="writer-wrap">
                    <div class="prifle-image align-content-center">
                        <?php echo get_member_profile_img($list[$i]['mb_id']); ?>
                    </div>

                    <!-- 작성자 -->
                    <div class="dropdown align-content-center flex-wrap me-2 writer">
                        <a href="#name<?php echo $i ?>" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text($list[$i]['wr_name']) ?></a>
                        <div class="dropdown-menu p-2 shadow">
                            <?php echo $list[$i]['name'] ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class='flex-grow-1'>
                <!-- 댓글 출력 -->
                <div class="comments-wrap">
                    <div class="comments-info d-flex flex-row">
                        <?php if ($is_ip_view) { ?>
                            <div class='ip-address d-flex align-content-center flex-wrap'>
                                <span class="visually-hidden">아이피</span>
                                <span><?php echo $list[$i]['ip']; ?></span>
                            </div>
                        <?php } ?>
                        <span class="visually-hidden">작성일</span>
                        <span class="writer-datetime d-flex align-content-center flex-wrap">&nbsp; <i class="bi bi-clock" aria-hidden="true"></i> <time datetime="<?php echo date('Y-m-d\TH:i:s+09:00', strtotime($list[$i]['datetime'])) ?>"><?php echo $list[$i]['datetime'] ?></time></span>
                        <?php include G5_SNS_PATH . '/view_comment_list.sns.skin.php'; ?>
                        <span class='visually-hidden'><?php echo get_text($list[$i]['wr_name']); ?>님의 <?php if ($cmt_depth) { ?><span class="visually-hidden">댓글의</span><?php } ?> 댓글</span>

                        <?php if ($is_comment_reply_edit) { ?>
                            <div class="dropdown ms-auto ">
                                <button type="button" class="btn btn-sm btn-outline-light text-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                    <span class="visually-hidden">댓글 옵션</span>
                                </button>
                                <ul class="dropdown-menu shadow">
                                    <?php if ($list[$i]['is_reply']) { ?>
                                        <li><a href="<?php echo $c_reply_href; ?>" onclick="comment_box('<?php echo $comment_id ?>', 'c'); return false;" class="dropdown-item">답변</a></li>
                                    <?php } ?>
                                    <?php if ($list[$i]['is_edit']) { ?>
                                        <li><a href="<?php echo $c_edit_href; ?>" onclick="comment_box('<?php echo $comment_id ?>', 'cu'); return false;" class="dropdown-item">수정</a></li>
                                    <?php } ?>
                                    <?php if ($list[$i]['is_del']) { ?>
                                        <li><a href="<?php echo $list[$i]['del_link']; ?>" onclick="return comment_delete();" class="dropdown-item">삭제</a></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        <?php } ?>
                    </div>

                    <article class='comments-view'>
                        <?php if (strstr($list[$i]['wr_option'], "secret")) { ?>
                            <!-- 비밀글 -->
                        <?php } ?>
                        <?php echo $comment ?>
                    </article>
                    <?php if ($is_comment_reply_edit) {
                        if ($w == 'cu') {
                            $sql = " select wr_id, wr_content, mb_id from $write_table where wr_id = '{$c_id}' and wr_is_comment = '1' ";
                            $cmt = sql_fetch($sql);
                            if (isset($cmt)) {
                                if (!($is_admin || ($member['mb_id'] == $cmt['mb_id'] && $cmt['mb_id']))) {
                                    $cmt['wr_content'] = '';
                                }
                                $c_wr_content = $cmt['wr_content'];
                            }
                        }
                    ?>
                    <?php } ?>
                </div>
                <span id="edit_<?php echo $comment_id ?>" class="bo_vc_w"></span><!-- 수정 -->
                <span id="reply_<?php echo $comment_id ?>" class="bo_vc_w"></span><!-- 답변 -->

                <input type="hidden" value="<?php echo strstr($list[$i]['wr_option'], "secret") ?>" id="secret_comment_<?php echo $comment_id ?>">
                <textarea id="save_comment_<?php echo $comment_id ?>" style="display:none"><?php echo get_text($list[$i]['content1'], 0) ?></textarea>

            </div>
        </div>
    <?php } ?>
    <?php if ($i == 0) { ?>
        <div class="empty-item" id="bo_vc_empty">등록된 댓글이 없습니다.</div>
    <?php } ?>

</section>

<!-- 댓글쓰기 폼 -->
<?php if ($is_comment_write) {
    if ($w == '') {
        $w = 'c';
    }
?>

    <div id="bo_vc_w" class="bo_vc_w border bg-white p-2 p-lg-4 mb-5 mt-5">
        <span class="visually-hidden">댓글쓰기</span>
        <form name="fviewcomment" id="fviewcomment" action="<?php echo $comment_action_url; ?>" onsubmit="return fviewcomment_submit(this);" method="post" autocomplete="off">
            <input type="hidden" name="w" value="<?php echo $w ?>" id="w">
            <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
            <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
            <input type="hidden" name="comment_id" value="<?php echo $c_id ?>" id="comment_id">
            <input type="hidden" name="sca" value="<?php echo $sca ?>">
            <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
            <input type="hidden" name="stx" value="<?php echo $stx ?>">
            <input type="hidden" name="spt" value="<?php echo $spt ?>">
            <input type="hidden" name="page" value="<?php echo $page ?>">
            <input type="hidden" name="is_good" value="">

            <span class="visually-hidden">내용</span>
            <div class='row g-0 d-flex justify-content-center mt-2'>
                <div class="col-sm-12 d-flex">
                    <div class="me-auto">
                        <input type="checkbox" name="wr_secret" value="secret" id="wr_secret" class="form-check-input">
                        <label for="wr_secret"><span></span>비밀글</label>
                    </div>
                    <div class="ms-auto">
                        <?php echo (isset($comment_max) && $comment_max) ? "최대 " . $comment_max . "글자" : ""; ?>
                        <?php if ($comment_min || $comment_max) { ?>
                            / <strong id="char_cnt"><span id="char_count"></span>글자</strong>
                        <?php } ?>
                    </div>
                </div>
                <div class="col-sm-12 col-md-10 col-lg-11">
                    <textarea id="wr_content" name="wr_content" maxlength="10000" required class="required form-control bg-light" rows="6" title="내용" placeholder="댓글내용을 입력해주세요" <?php if ($comment_min || $comment_max) { ?>onkeyup="check_byte('wr_content', 'char_count');" <?php } ?>><?php echo $c_wr_content; ?></textarea>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-1">
                    <button type="submit" id="btn_submit" class="btn btn-primary w-100 h-100">댓글등록</button>
                </div>
            </div>
            <?php if ($comment_min || $comment_max) { ?>
                <script>
                    check_byte('wr_content', 'char_count');
                </script>
            <?php } ?>
            <script>
                $(document).on("keyup change", "textarea#wr_content[maxlength]", function() {
                    var str = $(this).val()
                    var mx = parseInt($(this).attr("maxlength"))
                    if (str.length > mx) {
                        $(this).val(str.substr(0, mx));
                        return false;
                    }
                });
            </script>
            <div class="guest-wrap mt-2">
                <div class="">
                    <?php if ($is_guest) { ?>
                        <div class="input-group mb-2">
                            <label for="wr_name" class="visually-hidden">이름<strong> 필수</strong></label>
                            <input type="text" name="wr_name" value="<?php echo get_cookie("ck_sns_name"); ?>" id="wr_name" required class="form-control required" size="25" placeholder="이름">
                            <label for="wr_password" class="visually-hidden">비밀번호<strong> 필수</strong></label>
                            <input type="password" name="wr_password" id="wr_password" required class="form-control required" size="25" placeholder="비밀번호" autocomplete="new-password">
                        </div>
                    <?php } ?>

                    <?php if ($board['bo_use_sns'] && ($config['cf_facebook_appid'] || $config['cf_twitter_key'])) { ?>
                        <script>
                            $(function() {
                                // sns 등록
                                $("#bo_vc_send_sns").load(
                                    "<?php echo G5_SNS_URL; ?>/view_comment_write.sns.skin.php?bo_table=<?php echo $bo_table; ?>",
                                    function() {
                                        save_html = document.getElementById('bo_vc_w').innerHTML;
                                    }
                                );
                            });
                        </script>
                        <span class="visually-hidden">SNS 동시등록</span>
                        <span id="bo_vc_send_sns"></span>
                    <?php } ?>
                    <?php if ($is_guest) { ?>
                        <?php
                        $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                        $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                        $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);

                        echo $captcha_html;
                        ?>
                    <?php } ?>
                </div>
            </div>
        </form>
    </div>

    <script>
        var save_before = '';
        var save_html = document.getElementById('bo_vc_w').innerHTML;

        function good_and_write() {
            var f = document.fviewcomment;
            if (fviewcomment_submit(f)) {
                f.is_good.value = 1;
                f.submit();
            } else {
                f.is_good.value = 0;
            }
        }

        function fviewcomment_submit(f) {
            var pattern = /(^\s*)|(\s*$)/g; // \s 공백 문자

            f.is_good.value = 0;

            var subject = "";
            var content = "";
            $.ajax({
                url: g5_bbs_url + "/ajax.filter.php",
                type: "POST",
                data: {
                    "subject": "",
                    "content": f.wr_content.value
                },
                dataType: "json",
                async: false,
                cache: false,
                success: function(data, textStatus) {
                    subject = data.subject;
                    content = data.content;
                }
            });

            if (content) {
                alert("내용에 금지단어('" + content + "')가 포함되어있습니다");
                f.wr_content.focus();
                return false;
            }

            // 양쪽 공백 없애기
            var pattern = /(^\s*)|(\s*$)/g; // \s 공백 문자
            document.getElementById('wr_content').value = document.getElementById('wr_content').value.replace(pattern, "");
            if (char_min > 0 || char_max > 0) {
                check_byte('wr_content', 'char_count');
                var cnt = parseInt(document.getElementById('char_count').innerHTML);
                if (char_min > 0 && char_min > cnt) {
                    alert("댓글은 " + char_min + "글자 이상 쓰셔야 합니다.");
                    return false;
                } else if (char_max > 0 && char_max < cnt) {
                    alert("댓글은 " + char_max + "글자 이하로 쓰셔야 합니다.");
                    return false;
                }
            } else if (!document.getElementById('wr_content').value) {
                alert("댓글을 입력하여 주십시오.");
                return false;
            }

            if (typeof(f.wr_name) != 'undefined') {
                f.wr_name.value = f.wr_name.value.replace(pattern, "");
                if (f.wr_name.value == '') {
                    alert('이름이 입력되지 않았습니다.');
                    f.wr_name.focus();
                    return false;
                }
            }

            if (typeof(f.wr_password) != 'undefined') {
                f.wr_password.value = f.wr_password.value.replace(pattern, "");
                if (f.wr_password.value == '') {
                    alert('비밀번호가 입력되지 않았습니다.');
                    f.wr_password.focus();
                    return false;
                }
            }

            <?php if ($is_guest) echo chk_captcha_js();  ?>

            set_comment_token(f);

            document.getElementById("btn_submit").disabled = "disabled";

            return true;
        }

        function comment_box(comment_id, work) {
            var el_id,
                form_el = 'fviewcomment',
                respond = document.getElementById(form_el);

            // 댓글 아이디가 넘어오면 답변, 수정
            if (comment_id) {
                if (work == 'c')
                    el_id = 'reply_' + comment_id;
                else
                    el_id = 'edit_' + comment_id;
            } else
                el_id = 'bo_vc_w';

            if (save_before != el_id) {
                if (save_before) {
                    document.getElementById(save_before).style.display = 'none';
                }

                document.getElementById(el_id).style.display = '';
                document.getElementById(el_id).appendChild(respond);
                //입력값 초기화
                document.getElementById('wr_content').value = '';

                // 댓글 수정
                if (work == 'cu') {
                    document.getElementById('wr_content').value = document.getElementById('save_comment_' + comment_id).value;
                    if (typeof char_count != 'undefined')
                        check_byte('wr_content', 'char_count');
                    if (document.getElementById('secret_comment_' + comment_id).value)
                        document.getElementById('wr_secret').checked = true;
                    else
                        document.getElementById('wr_secret').checked = false;
                }

                document.getElementById('comment_id').value = comment_id;
                document.getElementById('w').value = work;

                if (save_before)
                    $("#captcha_reload").trigger("click");

                save_before = el_id;
            }
        }

        function comment_delete() {
            return confirm("이 댓글을 삭제하시겠습니까?");
        }

        comment_box('', 'c'); // 댓글 입력폼이 보이도록 처리하기위해서 추가 (root님)
    </script>
<?php } ?>
<!-- } 댓글 쓰기 끝 -->
<script>
    jQuery(function($) {
        //댓글열기
        $(".cmt_btn").click(function(e) {
            e.preventDefault();
            $(this).toggleClass("cmt_btn_op");
            $("#bo_vc").toggle();
        });
    });
</script>