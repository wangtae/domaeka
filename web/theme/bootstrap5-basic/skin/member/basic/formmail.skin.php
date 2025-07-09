<?php
/**
 * Formmail Basic
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/formmail.css">', 120);

?>

<div id="formmail" class="new_win formmail-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-envelope-at-fill"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class='container-fluid'>
        <div class='bg-white p-2 p-lg-4 border'>
            <form name="fformmail" action="./formmail_send.php" onsubmit="return fformmail_submit(this);" method="post" enctype="multipart/form-data" style="margin:0px;">
                <input type="hidden" name="to" value="<?php echo $email ?>">
                <input type="hidden" name="attach" value="2">
                <?php if ($is_member) { ?>
                    <input type="hidden" name="fnick" value="<?php echo get_text($member['mb_nick']) ?>">
                    <input type="hidden" name="fmail" value="<?php echo $member['mb_email'] ?>">
                <?php } ?>

                <h2 class="visually-hidden">메일쓰기</h2>
                <ul class="list-group list-group-flush">
                    <?php if (!$is_member) {  ?>
                        <li class="list-group-item">
                            <label for="fnick" class="visually-hidden">이름<strong>필수</strong></label>
                            <input type="text" name="fnick" id="fnick" required class="form-control required" placeholder="이름">
                        </li>
                        <li class="list-group-item">
                            <label for="fmail" class="visually-hidden">E-mail<strong>필수</strong></label>
                            <input type="text" name="fmail" id="fmail" required class="form-control required" placeholder="E-mail">
                        </li>
                    <?php } ?>
                    <li class="list-group-item">
                        <label for="subject" class="visually-hidden">제목<strong>필수</strong></label>
                        <input type="text" name="subject" id="subject" required class="form-control required" placeholder="제목">
                    </li>
                    <li class="list-group-item">
                        <span class="visually-hidden">형식</span>
                        <input type="radio" name="type" value="0" id="type_text" checked>
                        <label for="type_text"><span></span>TEXT</label>

                        <input type="radio" name="type" value="1" id="type_html">
                        <label for="type_html"><span></span>HTML</label>

                        <input type="radio" name="type" value="2" id="type_both">
                        <label for="type_both"><span></span>TEXT+HTML</label>
                    </li>
                    <li class="list-group-item">
                        <label for="content" class="visually-hidden">내용<strong>필수</strong></label>
                        <textarea name="content" id="content" required class="required form-control" rows="6"></textarea>
                    </li>
                    <li class="list-group-item">
                        <div class='input-group'>
                            <label for="file1" class="input-group-text"><i class="bi bi-download" aria-hidden="true"></i><span class="visually-hidden"> 첨부 파일 1</span></label>
                            <input type="file" name="file1" id="file1" class="form-control">
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class='input-group'>
                            <label for="file2" class="input-group-text"><i class="bi bi-download" aria-hidden="true"></i><span class="visually-hidden"> 첨부 파일 2</span></label>
                            <input type="file" name="file2" id="file2" class="form-control">
                        </div>
                    </li>
                    <li class="list-group-item">
                        <span class="visually-hidden">자동등록방지</span>
                        <?php
                        $captcha_html = captcha_html('formmail-captcha');
                        $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                        $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                        $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);
                        echo $captcha_html;
                        ?>
                    </li>
                </ul>
                <div class="alert alert-info"><i class="bi bi-info-circle"></i> 첨부 파일은 누락될 수 있으므로 메일을 보낸 후 파일이 첨부 되었는지 반드시 확인해 주시기 바랍니다.</div>
                <div class='d-flex justify-content-center p-5'>
                    <button type="submit" id="btn_submit" class="btn btn-outline-primary">메일발송</button>
                    <?php if ($is_member && (defined('BB_MAIL_POPUP') && BB_MAIL_POPUP == true)) { ?>
                        <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close ms-1">창닫기</button>
                    <?php } ?>
                </div>


            </form>
        </div>
    </div>
</div>

<script>
    with(document.fformmail) {
        if (typeof fname != "undefined")
            fname.focus();
        else if (typeof subject != "undefined")
            subject.focus();
    }

    function fformmail_submit(f) {
        <?php echo chk_captcha_js();  ?>

        if (f.file1.value || f.file2.value) {
            // 4.00.11
            if (!confirm("첨부파일의 용량이 큰경우 전송시간이 오래 걸립니다.\n\n메일보내기가 완료되기 전에 창을 닫거나 새로고침 하지 마십시오."))
                return false;
        }

        document.getElementById('btn_submit').disabled = true;

        return true;
    }

    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.mail-modal-body').height();
        $(window.parent.document).find('.mail-modal-body iframe').height(parentHeight + 'px');
    });
</script>