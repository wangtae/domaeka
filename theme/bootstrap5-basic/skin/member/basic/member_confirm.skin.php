<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/member-confirm.css">', 120);
?>

<div class="member-confirm-wrap">
    <div class="container">
        <div class='d-flex justify-content-center'>
            <div class='row'>
                <div class="p-4 p-lg-4 bg-white border mt-5 shadow">
                    <h1 class="fs-4 fw-bolder"><?php echo $g5['title'] ?></h1>
                    <hr />

                    <div class="alert alert-info">
                        <strong>비밀번호를 한번 더 입력해주세요.</strong>
                        <?php if ($url == 'member_leave.php') { ?>
                            비밀번호를 입력하시면 회원탈퇴가 완료됩니다.
                        <?php } else { ?>
                            회원님의 정보를 안전하게 보호하기 위해 비밀번호를 한번 더 확인합니다.
                        <?php }  ?>
                    </div>

                    <form name="fmemberconfirm" action="<?php echo $url ?>" onsubmit="return fmemberconfirm_submit(this);" method="post">
                        <input type="hidden" name="mb_id" value="<?php echo $member['mb_id'] ?>">
                        <input type="hidden" name="w" value="u">

                        <div class="input-group mb-1">
                            <span class="input-group-text">회원아이디</span>
                            <span id="mb_confirm_id" class="form-control"><?php echo $member['mb_id'] ?></span>
                        </div>
                        <div class="input-group">
                            <label for="confirm_mb_password" class="input-group-text">비밀번호</label>
                            <input type="password" name="mb_password" id="confirm_mb_password" required class="required form-control" size="15" maxLength="20" placeholder="비밀번호">
                            <input type="submit" value="확인" id="btn_submit" class="btn btn-outline-primary">
                        </div>
                    </form>
                    <hr />
                    <a href='#' onclick="history.go(-1);"><i class="bi bi-reply-fill"></i> 뒤로</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function fmemberconfirm_submit(f) {
        document.getElementById("btn_submit").disabled = true;

        return true;
    }
</script>