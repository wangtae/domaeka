<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/login.css">', 120);
?>

<div class='login-wrap container justify-content-center d-flex'>
    <div class="card shadow border mt-5">
        <div class="card-header bg-white">
            <h5 class="card-title fw-bolder fs-3"><?php echo $g5['title'] ?></h5>
        </div>
        <div class="card-body">
            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post">
                <input type="hidden" name="url" value="<?php echo $login_url ?>">
                <fieldset class="d-flex flex-row">
                    <legend class="visually-hidden">회원로그인</legend>
                    <div class='flex-grow-1'>
                        <div class="input-group">
                            <label for="login_id" class="input-group-text">회원아이디<strong class="visually-hidden"> 필수</strong></label>
                            <input type="text" name="mb_id" id="login_id" required class="form-control required" size="20" maxLength="20" placeholder="아이디">
                        </div>
                        <div class="input-group password-group">
                            <label for="login_pw" class="input-group-text">비밀번호<strong class="visually-hidden"> 필수</strong></label>
                            <input type="password" name="mb_password" id="login_pw" required class="form-control required" size="20" maxLength="20" placeholder="비밀번호">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary login-button">로그인</button>
                </fieldset>

            </form>
        </div>
        <div class="card-footer">
            <div class='d-flex justify-content-around'>
                <label for="login_auto_login">
                    <input type="checkbox" name="auto_login" id="login_auto_login" class="selec_chk">
                    자동로그인
                </label>
                <a href="<?php echo G5_BBS_URL ?>/password_lost.php"><i class="bi bi-binoculars-fill"></i> ID/PW 찾기</a>
                <a href="<?php echo G5_BBS_URL ?>/register.php" class="join"><i class="bi bi-person-fill-add"></i> 회원가입</a>
            </div>
        </div>
        <div class="p-2">
            <?php @include_once get_social_skin_path() . '/social_login.skin.php'; ?>
            <hr />
            <a href='#' onclick="history.go(-1);"><i class="bi bi-reply-fill"></i> 뒤로</a>
        </div>

        <?php
        /**
         * 쇼핑몰 사용시
         */
        if (isset($default['de_level_sell']) && $default['de_level_sell'] == 1) { ?>
            <!-- 주문하기, 신청하기 -->
            <?php if (preg_match("/orderform.php/", $url)) { ?>
                <div class='card-body no-member'>
                    <h2 class=" fw-bolder fs-3">비회원 구매</h2>
                    <p class="alert alert-danger"><i class="bi bi-info-circle-fill"></i> 비회원으로 주문하시는 경우 포인트는 지급하지 않습니다.</p>

                    <div id="guest_privacy">
                        <?php echo conv_content($default['de_guest_privacy'], $config['cf_editor']); ?>
                    </div>

                    <div class="input-group">
                        <label for="agree" class="">
                            <input type="checkbox" id="agree" value="1" class="form-checkbox">
                            개인정보수집에 대한 내용을 읽었으며 이에 동의합니다.
                        </label>
                        <a href="javascript:guest_submit(document.flogin);" class="btn btn-outline-primary">비회원으로 구매하기</a>

                    </div>


                    <script>
                        function guest_submit(f) {
                            if (document.getElementById('agree')) {
                                if (!document.getElementById('agree').checked) {
                                    alert("개인정보수집에 대한 내용을 읽고 이에 동의하셔야 합니다.");
                                    return;
                                }
                            }

                            f.url.value = "<?php echo $url; ?>";
                            f.action = "<?php echo $url; ?>";
                            f.submit();
                        }
                    </script>
                </div>

            <?php } else if (preg_match("/orderinquiry.php$/", $url)) { ?>
                <div class="card-body">
                    <h2 class="fs-3 fw-bolder">비회원 주문조회 </h2>

                    <legend class="visually-hidden">비회원 주문조회</legend>
                    <form name="forderinquiry" method="post" action="<?php echo urldecode($url); ?>" autocomplete="off">
                        <fieldset class="d-flex flex-row">
                            <div class='flex-grow-1'>
                                <div class="input-group">
                                    <label for="od_id" class="od_id input-group-text">주문서번호<strong class="visually-hidden"> 필수</strong></label>
                                    <input type="text" name="od_id" value="<?php echo get_text($od_id); ?>" id="od_id" required class="form-control required" size="20" placeholder="주문서번호">
                                </div>
                                <div class="input-group no-memober-password">
                                    <label for="od_pwd" class="od_pwd input-group-text">비밀번호 <span class="visually-hidden">필수</span></label>
                                    <input type="password" name="od_pwd" size="20" id="od_pwd" required class="form-control required" placeholder="비밀번호" autocomplete="new-password">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">확인</button>
                        </fieldset>

                    </form>

                    <div class="alert alert-info mt-2">
                        <i class="bi bi-info-circle-fill"></i> 메일로 발송해드린 주문서의 <strong>주문번호</strong> 및 주문 시 입력하신 <strong>비밀번호</strong>를 정확히 입력해주십시오.
                    </div>

                </div>
            <?php } ?>

        <?php } ?>

    </div>

    <script>
        jQuery(function($) {
            $("#login_auto_login").click(function() {
                if (this.checked) {
                    this.checked = confirm("자동로그인을 사용하시면 다음부터 회원아이디와 비밀번호를 입력하실 필요가 없습니다.\n\n공공장소에서는 개인정보가 유출될 수 있으니 사용을 자제하여 주십시오.\n\n자동로그인을 사용하시겠습니까?");
                }
            });
        });

        function flogin_submit(f) {
            if ($(document.body).triggerHandler('login_sumit', [f, 'flogin']) !== false) {
                return true;
            }
            return false;
        }
    </script>
</div>