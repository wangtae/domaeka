<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/qna/qna-basic.css">', 550);
?>
<div class='qna-write-wrap'>
    <div class="qna-header container mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class='container'>
        <section id="bo_w" class="bg-white border p-2 p-lg-4">
            <!-- 게시물 작성  -->
            <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" onsubmit="return fwrite_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="w" value="<?php echo $w ?>">
                <input type="hidden" name="qa_id" value="<?php echo $qa_id ?>">
                <input type="hidden" name="sca" value="<?php echo $sca ?>">
                <input type="hidden" name="stx" value="<?php echo $stx ?>">
                <input type="hidden" name="page" value="<?php echo $page ?>">
                <input type="hidden" name="token" value="<?php echo $token ?>">
                <?php
                $option = '';
                $option_hidden = '';
                $option = '';

                if ($is_dhtml_editor) {
                    $option_hidden .= '<input type="hidden" name="qa_html" value="1">';
                } else {
                    $option .= "\n" . '<input type="checkbox" id="qa_html" name="qa_html" onclick="html_auto_br(this);" value="' . $html_value . '" ' . $html_checked . '>' . "\n" . '<label for="qa_html">html</label>';
                }

                echo $option_hidden;
                ?>

                <div class="form-write">
                    <div class='d-flex'>
                        <?php if ($is_email) { ?>
                            <div class="input-group bo_w_mail chk_box">
                                <label for="qa_email" class="visually-hidden">이메일</label>
                                <input type="text" name="qa_email" value="<?php echo get_text($write['qa_email']); ?>" id="qa_email" <?php echo $req_email; ?> class="<?php echo $req_email . ' '; ?> form-control email" size="50" maxlength="100" placeholder="이메일">
                                <label for="qa_email_recv" class="input-group-text">
                                    <input type="checkbox" name="qa_email_recv" id="qa_email_recv" value="1" <?php if ($write['qa_email_recv']) echo 'checked="checked"'; ?> class="selec_chk">
                                    답변받기
                                </label>
                            </div>
                        <?php } ?>
                        <?php if ($is_hp) { ?>
                            <div class="form-group bo_w_hp chk_box">
                                <label for="qa_hp" class="visually-hidden">휴대폰</label>
                                <input type="text" name="qa_hp" value="<?php echo get_text($write['qa_hp']); ?>" id="qa_hp" <?php echo $req_hp; ?> class="<?php echo $req_hp . ' '; ?> form-control" size="30" placeholder="휴대폰">
                                <?php if ($qaconfig['qa_use_sms']) { ?>
                                    <input type="checkbox" name="qa_sms_recv" id="qa_sms_recv" value="1" <?php if ($write['qa_sms_recv']) echo 'checked="checked"'; ?> class="selec_chk">
                                    <label for="qa_sms_recv" class="frm_info"><span></span>답변등록 SMS알림 수신</label>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="input-group mt-2 mb-2">
                        <?php if ($category_option) { ?>
                            <label for="qa_category" class="visually-hidden">분류<strong>필수</strong></label>
                            <select name="qa_category" id="qa_category" required class='qa_category form-select'>
                                <option value="">분류를 선택하세요</option>
                                <?php echo $category_option ?>
                            </select>
                        <?php } ?>
                        <label for="qa_subject" class="visually-hidden">제목<strong class="visually-hidden">필수</strong></label>
                        <input type="text" name="qa_subject" value="<?php echo get_text($write['qa_subject']); ?>" id="qa_subject" required class="form-control required" size="50" maxlength="255" placeholder="제목">
                    </div>

                    <div class="form-group qa_content_wrap <?php echo $is_dhtml_editor ? $config['cf_editor'] : ''; ?>">
                        <label for="qa_content" class="visually-hidden">내용<strong class="visually-hidden">필수</strong></label>
                        <?php echo $editor_html; ?>
                    </div>

                    <?php if ($option) { ?>
                        <?php echo $option; ?>
                    <?php } ?>
                    <div class="mb-2 mt-4">
                        <div class="input-group">
                            <label for="bf_file_1" class="input-group-text"><i class="bi bi-files" aria-hidden="true"></i><span class="visually-hidden"> 파일 #1</span></label>
                            <input type="file" name="bf_file[1]" id="bf_file_1" title="파일첨부 1 :  용량 <?php echo $upload_max_filesize; ?> 이하만 업로드 가능" class="form-control">
                            <?php if ($w == 'u' && $write['qa_file1']) { ?>
                                <span class="input-group-text">
                                    <input type="checkbox" id="bf_file_del1" name="bf_file_del[1]" value="1"> <label for="bf_file_del1"><span class='d-none d-md-inline'><?php echo $write['qa_source1']; ?></span><i class="bi bi-trash-fill"></i></label>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="input-group">
                            <label for="bf_file_2" class="input-group-text"><i class="bi bi-files" aria-hidden="true"></i><span class="visually-hidden"> 파일 #2</span></label>
                            <input type="file" name="bf_file[2]" id="bf_file_2" title="파일첨부 2 :  용량 <?php echo $upload_max_filesize; ?> 이하만 업로드 가능" class="form-control">
                            <?php if ($w == 'u' && $write['qa_file2']) { ?>
                                <span class="input-group-text">
                                    <input type="checkbox" id="bf_file_del2" name="bf_file_del[2]" value="1"> <label for="bf_file_del2"><span class='d-none d-md-inline'><?php echo $write['qa_source2']; ?></span><i class="bi bi-trash-fill"></i></label>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-5">
                    <a href="<?php echo $list_href; ?>" class="btn btn-danger">취소</a>
                    <button type="submit" id="btn_submit" accesskey="s" class="btn btn-primary">작성완료</button>
                </div>

            </form>

            <script>
                function html_auto_br(obj) {
                    if (obj.checked) {
                        result = confirm("자동 줄바꿈을 하시겠습니까?\n\n자동 줄바꿈은 게시물 내용중 줄바뀐 곳을<br>태그로 변환하는 기능입니다.");
                        if (result)
                            obj.value = "2";
                        else
                            obj.value = "1";
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
                            "subject": f.qa_subject.value,
                            "content": f.qa_content.value
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
                        f.qa_subject.focus();
                        return false;
                    }

                    if (content) {
                        alert("내용에 금지단어('" + content + "')가 포함되어있습니다");
                        if (typeof(ed_qa_content) != "undefined")
                            ed_qa_content.returnFalse();
                        else
                            f.qa_content.focus();
                        return false;
                    }

                    <?php if ($is_hp) { ?>
                        var hp = f.qa_hp.value.replace(/[0-9\-]/g, "");
                        if (hp.length > 0) {
                            alert("휴대폰번호는 숫자, - 으로만 입력해 주십시오.");
                            return false;
                        }
                    <?php } ?>

                    document.getElementById("btn_submit").disabled = "disabled";

                    return true;
                }
            </script>
        </section>
    </div>
</div>