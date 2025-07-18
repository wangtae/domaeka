<?php
include_once('./_common.php');
include_once(G5_LIB_PATH.'/register.lib.php');
include_once(G5_LIB_PATH.'/mailer.lib.php');

define('ASIDE_DISABLE', 1);

if( ! $config['cf_social_login_use'] ){
    alert('소셜 로그인을 사용하지 않습니다.');
}

if( $is_member ){
    alert('이미 회원가입 하였습니다.', G5_URL);
}

$provider_name = social_get_request_provider();

// GET 요청일 때만 소셜 로그인 세션 체크
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_profile = social_session_exists_check();
    if( ! $user_profile ){
        alert( "소셜로그인을 하신 분만 접근할 수 있습니다.", G5_URL);
    }
} else {
    // POST 요청일 때는 세션 체크를 건너뛰고 null로 설정
    $user_profile = null;
}

// POST 요청인 경우 회원가입 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 소셜 로그인 세션 복원 (소셜 프로필 저장용)
    $user_profile = social_session_exists_check();
    
    // 회원가입 처리 로직
    $mb_id = trim($_POST['mb_id']);
    $mb_nick = trim($_POST['mb_nick']);
    $mb_email = trim($_POST['mb_email']);
    $mb_name = trim($_POST['mb_name']);
    $mb_hp = trim($_POST['mb_hp']);
    $mb_password = md5(pack('V*', rand(), rand(), rand(), rand()));
    
    // 이메일이 없는 경우 임시 이메일 생성
    if (empty($mb_email)) {
        $mb_email = 'kakao_' . $mb_id . '@noemail.local';
    }
    
    // 필수 검증 (이메일 체크 제외)
    if ($msg = valid_mb_id($mb_id))         alert($msg, "", true, true);
    if ($msg = empty_mb_name($mb_name))     alert($msg, "", true, true);
    if ($msg = empty_mb_nick($mb_nick))     alert($msg, "", true, true);
    if ($msg = reserve_mb_id($mb_id))       alert($msg, "", true, true);
    if ($msg = reserve_mb_nick($mb_nick))   alert($msg, "", true, true);
    if ($msg = valid_mb_nick($mb_nick))     alert($msg, "", true, true);
    
    // 이메일이 실제 이메일인 경우에만 검증
    if ($mb_email && !strpos($mb_email, '@noemail.local')) {
        if ($msg = valid_mb_email($mb_email))   alert($msg, "", true, true);
        if ($msg = prohibit_mb_email($mb_email)) alert($msg, "", true, true);
    }
    
    // 중복 체크
    if ($msg = exist_mb_id($mb_id))         alert($msg);
    if ($msg = exist_mb_nick($mb_nick, $mb_id)) alert($msg, "", true, true);
    
    // 실제 이메일인 경우에만 중복 체크
    if ($mb_email && !strpos($mb_email, '@noemail.local')) {
        if ($msg = exist_mb_email($mb_email, $mb_id)) alert($msg, "", true, true);
    }
    
    // 이미 등록된 회원인지 확인
    if( $mb = get_member($mb_id) ){
        alert("이미 등록된 회원이 존재합니다.", G5_URL);
    }
    
    // 회원정보 입력
    $sql = " insert into {$g5['member_table']}
                set mb_id = '{$mb_id}',
                    mb_password = '".get_encrypt_string($mb_password)."',
                    mb_name = '{$mb_name}',
                    mb_nick = '{$mb_nick}',
                    mb_nick_date = '".G5_TIME_YMD."',
                    mb_email = '{$mb_email}',
                    mb_email_certify = '".G5_TIME_YMDHIS."',
                    mb_hp = '{$mb_hp}',
                    mb_today_login = '".G5_TIME_YMDHIS."',
                    mb_datetime = '".G5_TIME_YMDHIS."',
                    mb_ip = '{$_SERVER['REMOTE_ADDR']}',
                    mb_level = '{$config['cf_register_level']}',
                    mb_login_ip = '{$_SERVER['REMOTE_ADDR']}',
                    mb_mailling = '0',
                    mb_sms = '0',
                    mb_open = '0',
                    mb_open_date = '".G5_TIME_YMD."' ";
    
    $result = sql_query($sql, false);
    
    if($result) {
        // 회원가입 포인트 부여
        insert_point($mb_id, $config['cf_register_point'], '회원가입 축하', '@member', $mb_id, '회원가입');
        
        // 최고관리자님께 메일 발송
        if ($config['cf_email_mb_super_admin']) {
            $subject = '['.$config['cf_title'].'] '.$mb_nick .' 님께서 카카오 소셜 로그인으로 회원가입하셨습니다.';
            
            ob_start();
            include_once (G5_BBS_PATH.'/register_form_update_mail2.php');
            $content = ob_get_contents();
            ob_end_clean();
            
            mailer($mb_nick, $mb_email, $config['cf_admin_email'], $subject, $content, 1);
        }
        
        $mb = get_member($mb_id);
        
        // 소셜 로그인 계정 추가
        if( function_exists('social_login_success_after') && $user_profile ){
            social_login_success_after($mb, '', 'register');
        } else if (!$user_profile) {
            // 소셜 프로필 세션이 없는 경우 수동으로 프로필 저장
            $fake_profile = new stdClass();
            $fake_profile->identifier = str_replace('kakao_', '', $mb_id);
            $fake_profile->displayName = $mb_nick;
            $fake_profile->email = $mb_email;
            $fake_profile->sid = $mb_id;
            
            social_user_profile_replace($mb_id, $provider_name, $fake_profile);
        }
        
        set_session('ss_mb_reg', $mb['mb_id']);
        
        // 회원 프로필 사진 처리 (POST 요청에서는 생략)
        if( false && !empty($user_profile->photoURL) && ($config['cf_register_level'] >= $config['cf_icon_level']) ){
            // 회원아이콘
            $mb_dir = G5_DATA_PATH.'/member/'.substr($mb_id,0,2);
            @mkdir($mb_dir, G5_DIR_PERMISSION);
            @chmod($mb_dir, G5_DIR_PERMISSION);
            $dest_path = "$mb_dir/$mb_id.gif";
            
            social_profile_img_resize($dest_path, $user_profile->photoURL, $config['cf_member_icon_width'], $config['cf_member_icon_height'] );
            
            // 회원이미지
            if( is_dir(G5_DATA_PATH.'/member_image/') ) {
                $mb_dir = G5_DATA_PATH.'/member_image/'.substr($mb_id,0,2);
                @mkdir($mb_dir, G5_DIR_PERMISSION);
                @chmod($mb_dir, G5_DIR_PERMISSION);
                $dest_path = "$mb_dir/$mb_id.gif";
                
                social_profile_img_resize($dest_path, $user_profile->photoURL, $config['cf_member_img_width'], $config['cf_member_img_height'] );
            }
        }
        
        // 자동 로그인 처리
        set_session('ss_mb_id', $mb['mb_id']);
        if(function_exists('update_auth_session_token')) update_auth_session_token(G5_TIME_YMDHIS);
        
        // 신규회원 쿠폰발생
        if($default['de_member_reg_coupon_use'] && $default['de_member_reg_coupon_term'] > 0 && $default['de_member_reg_coupon_price'] > 0) {
            $j = 0;
            $create_coupon = false;
            
            do {
                $cp_id = get_coupon_id();
                
                $sql3 = " select count(*) as cnt from {$g5['g5_shop_coupon_table']} where cp_id = '$cp_id' ";
                $row3 = sql_fetch($sql3);
                
                if(!$row3['cnt']) {
                    $create_coupon = true;
                    break;
                } else {
                    if($j > 20)
                        break;
                }
            } while(1);
            
            if($create_coupon) {
                $cp_subject = '카카오 로그인 신규 회원가입 축하 쿠폰';
                $cp_method = 2;
                $cp_target = '';
                $cp_start = G5_TIME_YMD;
                $cp_end = date("Y-m-d", (G5_SERVER_TIME + (86400 * ((int)$default['de_member_reg_coupon_term'] - 1))));
                $cp_type = 0;
                $cp_price = $default['de_member_reg_coupon_price'];
                $cp_trunc = 1;
                $cp_minimum = $default['de_member_reg_coupon_minimum'];
                $cp_maximum = 0;
                
                $sql = " INSERT INTO {$g5['g5_shop_coupon_table']}
                            ( cp_id, cp_subject, cp_method, cp_target, mb_id, cp_start, cp_end, cp_type, cp_price, cp_trunc, cp_minimum, cp_maximum, cp_datetime )
                        VALUES
                            ( '$cp_id', '$cp_subject', '$cp_method', '$cp_target', '$mb_id', '$cp_start', '$cp_end', '$cp_type', '$cp_price', '$cp_trunc', '$cp_minimum', '$cp_maximum', '".G5_TIME_YMDHIS."' ) ";
                
                $res = sql_query($sql, false);
                
                if($res)
                    set_session('ss_member_reg_coupon', 1);
            }
        }
        
        // 원래 이동하려던 URL로 리다이렉트
        $redirect_url = $url ? $url : G5_URL;
        
        // 회원가입 완료 메시지 표시 후 리다이렉트
        alert('카카오 로그인으로 회원가입이 완료되었습니다.', $redirect_url);
        
    } else {
        alert('회원 가입 오류!', G5_URL);
    }
}

// 소셜 가입된 내역이 있는지 확인 상수 G5_SOCIAL_DELETE_DAY 관련
$is_exists_social_account = social_before_join_check($url);

$user_nick = social_relace_nick($user_profile->displayName);
$user_email = isset($user_profile->emailVerified) ? $user_profile->emailVerified : $user_profile->email;
$user_id = $user_profile->sid ? preg_replace("/[^0-9a-z_]+/i", "", $user_profile->sid) : get_social_convert_id($user_profile->identifier, $provider_name);
$user_phone = $user_profile->phone;

// 이메일이 없는 경우 임시 이메일 생성
if (empty($user_email)) {
    $user_email = 'kakao_' . $user_id . '@noemail.local';
}

if(! $user_nick) {
    $tmp = explode('_', $user_id);
    $user_nick = $tmp[1];
}

// 중복 체크 및 고유값 생성
$user_id = exist_mb_id_recursive($user_id);
$user_nick = exist_mb_nick_recursive($user_nick, '');
$is_exists_email = $user_email ? exist_mb_email($user_email, '') : false;
$user_name = isset($user_profile->username) ? $user_profile->username : ''; 

// 불법접근을 막도록 토큰생성
$token = md5(uniqid(rand(), true));
set_session("ss_token", $token);

$g5['title'] = '카카오 자동 회원가입 - '.social_get_provider_service_name($provider_name);

include_once(G5_BBS_PATH.'/_head.php');

$register_action_url = https_url(G5_PLUGIN_DIR.'/'.G5_SOCIAL_LOGIN_DIR, true).'/register_member_kakao.php';
$login_action_url = G5_HTTPS_BBS_URL."/login_check.php";
$req_nick = !isset($member['mb_nick_date']) || (isset($member['mb_nick_date']) && $member['mb_nick_date'] <= date("Y-m-d", G5_SERVER_TIME - ($config['cf_nick_modify'] * 86400)));
$required = ($w=='') ? 'required' : '';
$readonly = ($w=='u') ? 'readonly' : '';
$login_url = '';
?>

<style>
.auto-register-loading {
    text-align: center;
    padding: 50px 20px;
    font-family: 'Noto Sans KR', sans-serif;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 2s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text {
    font-size: 18px;
    color: #333;
    margin: 20px 0;
}

.user-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    text-align: left;
}

.user-info h3 {
    margin-bottom: 15px;
    color: #333;
}

.user-info p {
    margin: 5px 0;
    color: #666;
}
</style>

<div class="auto-register-loading">
    <div class="spinner"></div>
    <div class="loading-text">카카오 로그인 정보로 자동 회원가입을 진행 중입니다...</div>
    
    <div class="user-info">
        <h3>가입 정보</h3>
        <p><strong>닉네임:</strong> <?php echo htmlspecialchars($user_nick); ?></p>
        <p><strong>이메일:</strong> <?php echo htmlspecialchars($user_email); ?></p>
        <p><strong>아이디:</strong> <?php echo htmlspecialchars($user_id); ?></p>
        <?php if($user_phone): ?>
        <p><strong>연락처:</strong> <?php echo htmlspecialchars($user_phone); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- 숨겨진 폼으로 자동 회원가입 처리 -->
<form id="auto_register_form" method="post" action="<?php echo $register_action_url; ?>" style="display: none;">
    <input type="hidden" name="w" value="">
    <input type="hidden" name="mb_id" value="<?php echo htmlspecialchars($user_id); ?>">
    <input type="hidden" name="mb_nick" value="<?php echo htmlspecialchars($user_nick); ?>">
    <input type="hidden" name="mb_email" value="<?php echo htmlspecialchars($user_email); ?>">
    <input type="hidden" name="mb_name" value="<?php echo htmlspecialchars($user_name ? $user_name : $user_nick); ?>">
    <input type="hidden" name="mb_hp" value="<?php echo htmlspecialchars($user_phone); ?>">
    <input type="hidden" name="mb_password" value="">
    <input type="hidden" name="mb_password_re" value="">
    <input type="hidden" name="mb_mailling" value="0">
    <input type="hidden" name="mb_open" value="0">
    <input type="hidden" name="agree" value="1">
    <input type="hidden" name="agree2" value="1">
    <input type="hidden" name="token" value="<?php echo $token; ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 3초 후 자동 submit
    setTimeout(function() {
        document.getElementById('auto_register_form').submit();
    }, 3000);
});
</script>

<?php
include_once(G5_BBS_PATH.'/_tail.php');
?>