<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/password-lost.css">', 120);

if ($config['cf_cert_use'] && ($config['cf_cert_simple'] || $config['cf_cert_ipin'] || $config['cf_cert_hp'])) { ?>
    <script src="<?php echo G5_JS_URL ?>/certify.js?v=<?php echo G5_JS_VER; ?>"></script>
<?php } ?>

<div id="find_info" class="new_win mt-2 <?php if ($config['cf_cert_use'] != 0 && $config['cf_cert_find'] != 0) { ?> cert<?php } ?>">
    <div class="password-wrap container">
        <div class="bg-white border p-3 p-lg-4 mb-2">
            <h1 class="fs-4 fw-bolder mb-0"><?php echo $g5['title'] ?></h1>
        </div>
        <div class="bg-white border p-2 p-lg-4">
            <form name="fpasswordlost" action="<?php echo $action_url ?>" onsubmit="return fpasswordlost_submit(this);" method="post" autocomplete="off">
            <input type="hidden" name="cert_no" value="">
                <fieldset>
                    <div class="alert alert-info">
                        회원가입 시 등록하신 이메일 주소를 입력해 주세요.<br>
                        해당 이메일로 아이디와 비밀번호 정보를 보내드립니다.
                    </div>
                    <div class="input-group">
                        <label for="mb_email" class="input-group-text">E-mail 주소<strong class="visually-hidden">필수</strong></label>
                        <input type="text" name="mb_email" id="mb_email" required class="required form-control email" size="30" placeholder="E-mail 주소">
                    </div>

                </fieldset>
                <div class="captcha-wrap mt-5">
                    <?php
                    $captcha_html = captcha_html();
                    $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                    $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                    $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);
                    echo $captcha_html;
                    ?>
                </div>

                <div class="d-flex">
                    <button type="submit" class="btn btn-outline-primary ms-auto">인증메일 보내기</button>
                </div>
            </form>

            <?php if ($config['cf_cert_use'] != 0 && $config['cf_cert_find'] != 0) { ?>
                <div class="border-top mt-5 pt-5">
                    <h3 class="fs-5 fw-bolder mb-3">본인인증으로 찾기</h3>
                    <div class="btn-group">
                        <?php if (!empty($config['cf_cert_simple'])) { ?>
                            <button type="button" id="win_sa_kakao_cert" class="btn btn-outline-secondary win_sa_cert" data-type="">간편인증</button>
                        <?php }
                        if (!empty($config['cf_cert_hp']) || !empty($config['cf_cert_ipin'])) { ?>
                            <?php if (!empty($config['cf_cert_hp'])) { ?>
                                <button type="button" id="win_hp_cert" class="btn btn-outline-secondary">휴대폰 본인확인</button>
                            <?php }
                            if (!empty($config['cf_cert_ipin'])) { ?>
                                <button type="button" id="win_ipin_cert" class="btn btn-outline-secondary">아이핀 본인확인</button>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
    $(function() {
        $("#reg_zip_find").css("display", "inline-block");
        var pageTypeParam = "pageType=find";

        <?php if ($config['cf_cert_use'] && $config['cf_cert_simple']) { ?>
            // TOSS 간편인증
            var url = "<?php echo G5_INICERT_URL; ?>/ini_request.php";
            var type = "";
            var params = "";
            var request_url = "";


            $(".win_sa_cert").click(function() {
                type = $(this).data("type");
                params = "?directAgency=" + type + "&" + pageTypeParam;
                request_url = url + params;
                call_sa(request_url);
            });
        <?php } ?>
        <?php if ($config['cf_cert_use'] && $config['cf_cert_ipin']) { ?>
            // 아이핀인증
            var params = "";
            $("#win_ipin_cert").click(function() {
                params = "?" + pageTypeParam;
                var url = "<?php echo G5_OKNAME_URL; ?>/ipin1.php" + params;
                certify_win_open('kcb-ipin', url);
                return;
            });

        <?php } ?>
        <?php if ($config['cf_cert_use'] && $config['cf_cert_hp']) { ?>
            // 휴대폰인증
            var params = "";
            $("#win_hp_cert").click(function() {
                params = "?" + pageTypeParam;
                <?php
                switch ($config['cf_cert_hp']) {
                    case 'kcb':
                        $cert_url = G5_OKNAME_URL . '/hpcert1.php';
                        $cert_type = 'kcb-hp';
                        break;
                    case 'kcp':
                        $cert_url = G5_KCPCERT_URL . '/kcpcert_form.php';
                        $cert_type = 'kcp-hp';
                        break;
                    case 'lg':
                        $cert_url = G5_LGXPAY_URL . '/AuthOnlyReq.php';
                        $cert_type = 'lg-hp';
                        break;
                    default:
                        echo 'alert("기본환경설정에서 휴대폰 본인확인 설정을 해주십시오");';
                        echo 'return false;';
                        break;
                }
                ?>

                certify_win_open("<?php echo $cert_type; ?>", "<?php echo $cert_url; ?>" + params);
                return;
            });
        <?php } ?>
    });

    function fpasswordlost_submit(f) {
        <?php echo chk_captcha_js();  ?>

        return true;
    }
</script>