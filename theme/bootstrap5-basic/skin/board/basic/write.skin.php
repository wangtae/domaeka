<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/board/basic/basic-write.css">', 90);
?>

<section class='board-write container mt-2'>
    <div class='border bg-white p-2 p-lg-4'>
        <div class="fs-4 fw-bolder mb-4">
            <?php echo $g5['title'] ?>
        </div>
        <!-- 게시물 작성/수정 시작 { -->
        <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" onsubmit="return fwrite_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off" style="width:<?php echo $width; ?>">
            <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
            <input type="hidden" name="w" value="<?php echo $w ?>">
            <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
            <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
            <input type="hidden" name="sca" value="<?php echo $sca ?>">
            <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
            <input type="hidden" name="stx" value="<?php echo $stx ?>">
            <input type="hidden" name="spt" value="<?php echo $spt ?>">
            <input type="hidden" name="sst" value="<?php echo $sst ?>">
            <input type="hidden" name="sod" value="<?php echo $sod ?>">
            <input type="hidden" name="page" value="<?php echo $page ?>">
            <?php
            $option = '';
            $option_hidden = '';
            if ($is_notice || $is_html || $is_secret || $is_mail) {
                $option = '';
                if ($is_notice) {
                    $option .= PHP_EOL . '<input type="checkbox" id="notice" name="notice"  class="selec_chk" value="1" ' . $notice_checked . '>' . PHP_EOL . '<label for="notice"><span></span> 공지&nbsp; </label>';
                }
                if ($is_html) {
                    if ($is_dhtml_editor) {
                        $option_hidden .= '<input type="hidden" value="html1" name="html">';
                    } else {
                        $option .= PHP_EOL . '<input type="checkbox" id="html" name="html" onclick="html_auto_br(this);" class="selec_chk" value="' . $html_value . '" ' . $html_checked . '>' . PHP_EOL . '<label for="html"><span></span> html&nbsp;</label>';
                    }
                }
                if ($is_secret) {
                    if ($is_admin || $is_secret == 1) {
                        $option .= PHP_EOL . '<input type="checkbox" id="secret" name="secret"  class="selec_chk" value="secret" ' . $secret_checked . '>' . PHP_EOL . '<label for="secret"><span></span> 비밀글&nbsp;</label>';
                    } else {
                        $option_hidden .= '<input type="hidden" name="secret" value="secret">';
                    }
                }
                if ($is_mail) {
                    $option .= PHP_EOL . '<input type="checkbox" id="mail" name="mail"  class="selec_chk" value="mail" ' . $recv_email_checked . '>' . PHP_EOL . '<label for="mail"><span></span> 답변메일받기&nbsp;</label>';
                }
            }
            echo $option_hidden;
            ?>

            <div class="guest-info">
                <div class="row">
                    <?php if ($is_name) { ?>
                        <div class="col-sm-12 col-md-6">
                            <label for="wr_name" class="visually-hidden">이름<strong>필수</strong></label>
                            <input type="text" name="wr_name" value="<?php echo $name ?>" id="wr_name" required class="form-control half_input required" placeholder="이름">
                        </div>
                    <?php } ?>

                    <?php if ($is_password) { ?>
                        <div class="col-sm-12 col-md-6">
                            <label for="wr_password" class="visually-hidden">비밀번호<strong>필수</strong></label>
                            <input type="password" name="wr_password" id="wr_password" <?php echo $password_required ?> autocomplete="new-password" class="form-control half_input <?php echo $password_required ?>" placeholder="비밀번호">
                        </div>
                    <?php } ?>

                    <?php if ($is_email) { ?>
                        <div class="col-sm-12 col-md-6">
                            <label for="wr_email" class="visually-hidden">이메일</label>
                            <input type="text" name="wr_email" value="<?php echo $email ?>" id="wr_email" class="form-control half_input email " placeholder="이메일">
                        </div>
                    <?php } ?>

                    <?php if ($is_homepage) { ?>
                        <div class="col-sm-12 col-md-6">
                            <label for="wr_homepage" class="visually-hidden">홈페이지</label>
                            <input type="text" name="wr_homepage" value="<?php echo $homepage ?>" id="wr_homepage" class="form-control half_input" size="50" placeholder="홈페이지">
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="mt-4 d-md-flex flex-row subject-wrap">
                <?php if ($is_category) { ?>
                    <div class="category-wrap">
                        <label for="ca_name" class="visually-hidden">분류<strong>필수</strong></label>
                        <select name="ca_name" id="ca_name" required class="form-control">
                            <option value="">분류 선택</option>
                            <?php echo $category_option ?>
                        </select>
                    </div>
                <?php } ?>
                <div id="autosave_wrapper" class="flex-grow-1">
                    <label for="wr_subject" class="visually-hidden">제목<strong>필수</strong></label>
                    <input type="text" name="wr_subject" value="<?php echo $subject ?>" id="wr_subject" required class="form-control required" size="50" maxlength="255" placeholder="제목">
                </div>
                <?php if (isset($is_member) && $is_member == true) { ?>
                    <div class="autosave-wrap">
                        <script src="<?php echo BB_ASSETS_URL; ?>/js/autosave.js"></script>
                        <?php
                        if (isset($editor_content_js) && $editor_content_js) {
                            echo $editor_content_js;
                        }
                        ?>
                        <button type="button" id="btn_autosave" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#autosaveModal">
                            임시저장(<span id="autosave_count"><?php echo $autosave_count; ?></span>)
                        </button>

                        <!-- Modal -->
                        <div class="modal fade" id="autosaveModal" tabindex="-1" aria-labelledby="autosaveModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title fs-5" id="autosaveModalLabel">임시저장 목록</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="autosave_pop">
                                            <span class="visually-hidden">임시 저장된 글 목록</span>
                                            <ul class="list-group list-group-flush"></ul>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                <?php } ?>
            </div>

            <div class="write_div">
                <?php if ($option) { ?>
                    <div class="d-flex justify-content-end">
                        <span class="visually-hidden">옵션</span>
                        <?php echo $option ?>
                    </div>
                <?php } ?>
                <label for="wr_content" class="visually-hidden">내용<strong>필수</strong></label>
                <div class="wr_content <?php echo $is_dhtml_editor ? $config['cf_editor'] : ''; ?>">
                    <?php if ($write_min || $write_max) { ?>
                        <!-- 최소/최대 글자 수 사용 시 -->
                        <p id="char_count_desc">이 게시판은 최소 <strong><?php echo $write_min; ?></strong>글자 이상, 최대 <strong><?php echo $write_max; ?></strong>글자 이하까지 글을 쓰실 수 있습니다.</p>
                    <?php } ?>
                    <?php
                    // 에디터 사용시는 에디터로, 아니면 textarea 로 노출 
                    echo $editor_html;
                    ?>
                    <?php if ($write_min || $write_max) { ?>
                        <!-- 최소/최대 글자 수 사용 시 -->
                        <div id="char_count_wrap"><span id="char_count"></span>글자</div>
                    <?php } ?>
                </div>

            </div>
            <div class="board-links mt-2 mb-2">
                <?php for ($i = 1; $is_link && $i <= G5_LINK_COUNT; $i++) { ?>
                    <div class="input-group mb-1">
                        <span class="input-group-text">
                            <label for="wr_link<?php echo $i ?>"><i class="bi bi-link" aria-hidden="true"></i><span class="visually-hidden"> 링크 #<?php echo $i ?></span></label>
                        </span>
                        <input type="text" name="wr_link<?php echo $i ?>" value="<?php if ($w == "u") {
                                                                                        echo $write['wr_link' . $i];
                                                                                    } ?>" id="wr_link<?php echo $i ?>" class="form-control full_input" size="50">
                    </div>
                <?php } ?>
            </div>

            <div class="attach-files">
                <?php for ($i = 0; $is_file && $i < $file_count; $i++) { ?>
                    <div class="input-group mb-1">
                        <span class="input-group-text">
                            <label for="bf_file_<?php echo $i + 1 ?>" class="file-icon"><i class="bi bi-archive-fill"></i><span class="visually-hidden"> 파일 #<?php echo $i + 1 ?></span></label>
                        </span>
                        <input type="file" name="bf_file[]" id="bf_file_<?php echo $i + 1 ?>" class="form-control" title="파일첨부 <?php echo $i + 1 ?> : 용량 <?php echo $upload_max_filesize ?> 이하만 업로드 가능" class="frm_file ">
                        <?php if ($is_file_content) { ?>
                            <input type="text" name="bf_content[]" value="<?php echo ($w == 'u') ? $file[$i]['bf_content'] : ''; ?>" title="파일 설명을 입력해주세요." class="form-control" size="50" placeholder="파일 설명을 입력해주세요.">
                        <?php } ?>

                        <?php if ($w == 'u' && $file[$i]['file']) { ?>
                            <?php if (!is_mobile()) { ?>
                                <span class="input-group-text file-delete d-none d-md-flex">
                                    <input type="checkbox" id="bf_file_del<?php echo $i ?>" name="bf_file_del[<?php echo $i;  ?>]" value="1">
                                    <label for="bf_file_del<?php echo $i ?>">
                                        <span class="d-none d-md-inline"><?php echo $file[$i]['source'] . '(' . $file[$i]['size'] . ')'; ?></span><i class="bi bi-trash-fill"></i>
                                    </label>
                                </span>
                            <?php } else { ?>
                                <button class="btn btn-outline-secondary dropdown-toggle d-inline d-md-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">파일 <i class="bi bi-trash-fill"></i></button>
                                <div class="dropdown-menu dropdown-menu-end p-2 shadow">
                                    <input type="checkbox" id="bf_file_del<?php echo $i ?>" name="bf_file_del[<?php echo $i;  ?>]" value="1">
                                    <label for="bf_file_del<?php echo $i ?>">
                                        <i class="bi bi-trash-fill"></i> <?php echo $file[$i]['source'] . '(' . $file[$i]['size'] . ')'; ?>
                                    </label>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>



            <?php if ($is_use_captcha) { ?>
                <div class="captcha-wrap">
                    <?php
                    $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                    $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                    $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);
                    echo $captcha_html;
                    ?>
                </div>
            <?php } ?>

            <div class="d-flex mt-5 border-top pt-3">
                <a href="<?php echo get_pretty_url($bo_table); ?>" class="btn btn-danger"><i class="bi bi-x-circle-fill"></i> 취소</a>
                <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn btn-primary ms-auto"> <i class="bi bi-pencil-fill"></i> 작성완료</button>
            </div>
        </form>

        <script>
            <?php if ($write_min || $write_max) { ?>
                // 글자수 제한
                var char_min = parseInt(<?php echo $write_min; ?>); // 최소
                var char_max = parseInt(<?php echo $write_max; ?>); // 최대
                check_byte("wr_content", "char_count");

                $(function() {
                    $("#wr_content").on("keyup", function() {
                        check_byte("wr_content", "char_count");
                    });
                });

            <?php } ?>

            function html_auto_br(obj) {
                if (obj.checked) {
                    result = confirm("자동 줄바꿈을 하시겠습니까?\n\n자동 줄바꿈은 게시물 내용중 줄바뀐 곳을<br>태그로 변환하는 기능입니다.");
                    if (result)
                        obj.value = "html2";
                    else
                        obj.value = "html1";
                } else
                    obj.value = "";
            }

            function fwrite_submit(f) {
                <?php echo $editor_js; // 에디터 사용시 자바스크립트에서 내용을 폼필드로 넣어주며 내용이 입력되었는지 검사함   
                ?>

                var subject = "";
                var content = "";
                $.ajax({
                    url: g5_bbs_url + "/ajax.filter.php",
                    type: "POST",
                    data: {
                        "subject": f.wr_subject.value,
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

                if (subject) {
                    alert("제목에 금지단어('" + subject + "')가 포함되어있습니다");
                    f.wr_subject.focus();
                    return false;
                }

                if (content) {
                    alert("내용에 금지단어('" + content + "')가 포함되어있습니다");
                    if (typeof(ed_wr_content) != "undefined")
                        ed_wr_content.returnFalse();
                    else
                        f.wr_content.focus();
                    return false;
                }

                if (document.getElementById("char_count")) {
                    if (char_min > 0 || char_max > 0) {
                        var cnt = parseInt(check_byte("wr_content", "char_count"));
                        if (char_min > 0 && char_min > cnt) {
                            alert("내용은 " + char_min + "글자 이상 쓰셔야 합니다.");
                            return false;
                        } else if (char_max > 0 && char_max < cnt) {
                            alert("내용은 " + char_max + "글자 이하로 쓰셔야 합니다.");
                            return false;
                        }
                    }
                }

                <?php echo $captcha_js; // 캡챠 사용시 자바스크립트에서 입력된 캡챠를 검사함  
                ?>

                document.getElementById("btn_submit").disabled = "disabled";

                return true;
            }
        </script>
    </div>
</section>