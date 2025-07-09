<?php

/**
 * 회원가입
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/register.css">', 120);
?>

<div class="register-wrap">
    <div class="register-header container mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-person-fill-add"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>

    <form name="fregister" id="fregister" action="<?php echo $register_action_url ?>" onsubmit="return fregister_submit(this);" method="POST" autocomplete="off">
        <?php
        // 소셜로그인 사용시 소셜로그인 버튼
        @include_once get_social_skin_path() . '/social_register.skin.php';
        ?>
        <div class='container'>
            <div class='bg-white border p-2 p-lg-4'>
                <section class="mb-5">
                    <h2 class="fs-5">회원가입약관</h2>
                    <textarea readonly class='form-control' rows="10"><?php echo get_text($config['cf_stipulation']) ?></textarea>
                    <label for="agree11"><input type="checkbox" name="agree" value="1" id="agree11" class="selec_chk">회원가입약관의 내용에 동의합니다.</label>
                </section>

                <section class="mb-5">
                    <h2 class="fs-5">개인정보 수집 및 이용</h2>
                    <table class="table table-bordered mb-0">
                        <thead class="text-center">
                            <tr>
                                <th>목적</th>
                                <th>항목</th>
                                <th>보유기간</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>이용자 식별 및 본인여부 확인</td>
                                <td>아이디, 이름, 비밀번호<?php echo ($config['cf_cert_use']) ? ", 생년월일, 휴대폰 번호(본인인증 할 때만, 아이핀 제외), 암호화된 개인식별부호(CI)" : ""; ?></td>
                                <td>회원 탈퇴 시까지</td>
                            </tr>
                            <tr>
                                <td>고객서비스 이용에 관한 통지,<br>CS대응을 위한 이용자 식별</td>
                                <td>연락처 (이메일, 휴대전화번호)</td>
                                <td>회원 탈퇴 시까지</td>
                            </tr>
                        </tbody>
                    </table>

                    <input type="checkbox" name="agree2" value="1" id="agree21" class="selec_chk">
                    <label for="agree21">개인정보 수집 및 이용의 내용에 동의합니다.</label>
                </section>
                <div class="alert alert-info"><i class="bi bi-info-circle" aria-hidden="true"></i> 회원가입약관 및 개인정보 수집 및 이용의 내용에 동의하셔야 회원가입 하실 수 있습니다.</div>

                <div id="fregister_chkall" class="chk_all">
                    <input type="checkbox" name="chk_all" id="chk_all" class="selec_chk">
                    <label for="chk_all"><span></span>회원가입 약관에 모두 동의합니다</label>
                </div>

                <div class="d-flex justify-content-between mt-3 border-top pt-3">
                    <a href="<?php echo G5_URL ?>" class="btn btn-outline-danger">취소</a>
                    <button type="submit" class="btn btn-outline-primary ms-auto">회원가입</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function fregister_submit(f) {
            if (!f.agree.checked) {
                alert("회원가입약관의 내용에 동의하셔야 회원가입 하실 수 있습니다.");
                f.agree.focus();
                return false;
            }

            if (!f.agree2.checked) {
                alert("개인정보 수집 및 이용의 내용에 동의하셔야 회원가입 하실 수 있습니다.");
                f.agree2.focus();
                return false;
            }

            return true;
        }

        jQuery(function($) {
            // 모두선택
            $("input[name=chk_all]").click(function() {
                if ($(this).prop('checked')) {
                    $("input[name^=agree]").prop('checked', true);
                } else {
                    $("input[name^=agree]").prop("checked", false);
                }
            });
        });
    </script>
</div>
<!-- } 회원가입 약관 동의 끝 -->