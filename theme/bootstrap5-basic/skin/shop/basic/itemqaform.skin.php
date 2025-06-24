<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

?>

<div id="sit_qa_write" class="new_win container-fluid">
    <div class="bg-white border p-3 p-lg-4 mt-2 mb-2">
        <h1 id="win_title" class="fs-5 fw-bolder ff-noto mb-0"><i class="bi bi-pencil-fill"></i> 상품문의 쓰기</h1>
    </div>

    <form name="fitemqa" method="post" action="<?php echo G5_SHOP_URL; ?>/itemqaformupdate.php" onsubmit="return fitemqa_submit(this);" autocomplete="off">
        <input type="hidden" name="w" value="<?php echo $w; ?>">
        <input type="hidden" name="it_id" value="<?php echo $it_id; ?>">
        <input type="hidden" name="iq_id" value="<?php echo $iq_id; ?>">

        <div class="form_01 new_win_con bg-white border p-2 p-lg-4 mb-5">
            <ul class="list-group border-0 p-0">
                <li class="list-group-item border-0 p-0">
                    <strong class="visually-hidden">옵션</strong>
                    <input type="checkbox" name="iq_secret" id="iq_secret" value="1" <?php echo $chk_secret; ?> class="selec_chk">
                    <label for="iq_secret"><span></span>비밀글</label>
                </li>
                <li class="list-group-item border-0 p-1">
                    <div class="">
                        <label for="iq_email" class="visually-hidden">이메일</label>
                        <input type="text" name="iq_email" id="iq_email" value="<?php echo get_text($qa['iq_email']); ?>" class="form-control" size="30" placeholder="이메일">
                        <div class="alert alert-info alert-sm mt-1 mb-1 p-2"><i class="bi bi-info-circle"></i>  이메일을 입력하시면 답변 등록 시 답변이 이메일로 전송됩니다.</div>
                    </div>
                    <div class="">
                        <label for="iq_hp" class="visually-hidden">휴대폰</label>
                        <input type="text" name="iq_hp" id="iq_hp" value="<?php echo get_text($qa['iq_hp']); ?>" class="form-control" size="20" placeholder="휴대폰">
                        <div class="alert alert-info alert-sm mt-1 mb-1 p-2"><i class="bi bi-info-circle"></i> 휴대폰번호를 입력하시면 답변 등록 시 답변등록 알림이 SMS로 전송됩니다.</div>
                    </div>
                </li>
                <li class="list-group-item border-0 p-1">
                    <label for="iq_subject" class="visually-hidden">제목<strong> 필수</strong></label>
                    <input type="text" name="iq_subject" value="<?php echo get_text($qa['iq_subject']); ?>" id="iq_subject" required class="required form-control" maxlength="250" placeholder="제목">
                </li>
                <li class="list-group-item border-0 p-1">

                    <label for="iq_question" class="visually-hidden">질문</label>
                    <?php echo $editor_html; ?>
                </li>
            </ul>

            <div class="d-flex justify-content-between mt-2">
                <button type="submit" class="btn_submit btn btn-outline-primary">작성완료</button>
                <button type="button" onclick="self.close();" class="btn_close btn btn-outline-secondary">닫기</button>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    function fitemqa_submit(f) {
        <?php echo $editor_js; ?>

        return true;
    }
</script>
