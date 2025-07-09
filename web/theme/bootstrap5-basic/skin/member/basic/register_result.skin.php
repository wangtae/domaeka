<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/register.css">', 120);

?>
<div class="container">
    <div class="border bg-white p-lg-3 p-2 mt-2 mb-2">
        <h2 class="fs-4 fw-bolder ff-noto"><?php echo $g5['title'] ?></h2>
    </div>
</div>
<div id="reg_result" class="register">
    <div class="container">
        <div class="border bg-white p-lg-3 p-2 mt-2 mb-2">
            <div class="reg_result_p">
                <i class="fa fa-gift" aria-hidden="true"></i><br>
                <strong><?php echo get_text($mb['mb_name']); ?></strong>님의 회원가입을 진심으로 축하합니다.
            </div>

            <?php if (is_use_email_certify()) {  ?>
                <p class="result_txt">
                    회원 가입 시 입력하신 이메일 주소로 인증메일이 발송되었습니다.<br>
                    발송된 인증메일을 확인하신 후 인증처리를 하시면 사이트를 원활하게 이용하실 수 있습니다.
                </p>
                <div id="result_email">
                    <span>아이디</span>
                    <strong><?php echo $mb['mb_id'] ?></strong><br>
                    <span>이메일 주소</span>
                    <strong><?php echo $mb['mb_email'] ?></strong>
                </div>
                <p>
                    이메일 주소를 잘못 입력하셨다면, 사이트 관리자에게 문의해주시기 바랍니다.
                </p>
            <?php }  ?>

            <div class="result_txt">
                회원님의 비밀번호는 아무도 알 수 없는 암호화 코드로 저장되므로 안심하셔도 좋습니다.<br>
                아이디, 비밀번호 분실시에는 회원가입시 입력하신 이메일 주소를 이용하여 찾을 수 있습니다.<br>
                <br>
                소셜로그인으로 가입한 회원은 암호입력 없시 해당 소셜 미디어를 통해 로그인 됩니다.
            </div>

            <div class="result_txt">
                회원 탈퇴는 언제든지 가능하며 일정기간이 지난 후, 회원님의 정보는 삭제하고 있습니다.<br><br>
                감사합니다.
            </div>
        </div>
        <div class="btn_confirm_reg">
            <a href="<?php echo G5_URL ?>/" class="reg_btn_submit">메인으로</a>
        </div>
    </div>
</div>