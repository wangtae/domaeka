<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/password-reset.css">', 120);
?>

<div id="pw_reset" class="new_win">
    <div class="password-reset-wrap container">
        <div class="bg-white border p-3 p-lg-4 mb-2">
            <h1 class="fs-4 fw-bolder mb-0"><?php echo $g5['title'] ?></h1>
        </div>
        <div class="bg-white border p-2 p-lg-4">

            <form name="fpasswordreset" action="<?php echo $action_url; ?>" onsubmit="return fpasswordreset_submit(this);" method="post" autocomplete="off">
                <fieldset id="info_fs">
                    <div class='alert alert-info'>새로운 비밀번호를 입력해주세요.</div>
                    <label for="mb_id" class="visually-hidden">아이디</label>
                    <div class="input-group mb-1">
                        <span class="input-group-text">회원 아이디</span><span class="form-control"><?php echo get_text($_POST['mb_id']); ?></span>
                    </div>
                    <div class="input-group">
                        <label for="mb_pw" class="visually-hidden">새 비밀번호<strong class="visually-hidden">필수</strong></label>
                        <input type="password" name="mb_password" id="mb_pw" required class="required form-control" size="30" placeholder="새 비밀번호">
                        <label for="mb_pw2" class="visually-hidden">새 비밀번호 확인<strong class="visually-hidden">필수</strong></label>
                        <input type="password" name="mb_password_re" id="mb_pw2" required class="required form-control" size="30" placeholder="새 비밀번호 확인">
                    </div>
                </fieldset>
                <div class="d-flex mt-5">
                    <button type="submit" class="btn btn-outline-primary ms-auto">확인</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--//.password-reset-wrap-->

<script>
    function fpasswordreset_submit(f) {
        if ($("#mb_pw").val() == $("#mb_pw2").val()) {
            alert("비밀번호 변경되었습니다. 다시 로그인해 주세요.");
        } else {
            alert("새 비밀번호와 비밀번호 확인이 일치하지 않습니다.");
            return false;
        }
    }
</script>
<!-- } 비밀번호 재설정 끝 -->