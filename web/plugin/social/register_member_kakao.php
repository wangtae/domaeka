<?php
include_once('./_common.php');
include_once(G5_LIB_PATH.'/register.lib.php');
include_once(G5_LIB_PATH.'/mailer.lib.php');

// 소셜 로그인 사용 여부 확인
if( ! $config['cf_social_login_use'] ){
    alert('소셜 로그인을 사용하지 않습니다.', G5_URL);
}

// 이미 로그인된 사용자 확인
if( $is_member ){
    alert('이미 회원가입 하였습니다.', G5_URL);
}

// 소셜 로그인 세션 확인
$provider_name = social_get_request_provider();
$user_profile = social_session_exists_check();
if( ! $user_profile ){
    alert( "소셜로그인을 하신 분만 접근할 수 있습니다.", G5_URL);
}

// 소셜 가입된 내역이 있는지 확인
$is_exists_social_account = social_before_join_check(G5_URL);

// 카카오 프로필 정보 추출
$user_nick = social_relace_nick($user_profile->displayName);
$user_email = isset($user_profile->emailVerified) ? $user_profile->emailVerified : $user_profile->email;
$user_id = $user_profile->sid ? preg_replace("/[^0-9a-z_]+/i", "", $user_profile->sid) : get_social_convert_id($user_profile->identifier, $provider_name);
$user_phone = $user_profile->phone;
$user_name = isset($user_profile->username) ? $user_profile->username : '';

// 닉네임이 없는 경우 처리
if(! $user_nick) {
    $tmp = explode('_', $user_id);
    $user_nick = $tmp[1];
}

// 이름이 없는 경우 이메일 앞부분 사용
if(! $user_name && $user_email) {
    $tmp = explode('@', $user_email);
    $user_name = $tmp[0];
}

// 닉네임과 이름이 모두 없는 경우 처리
if(! $user_nick || ! $user_name) {
    $tmp = explode('@', $user_email);
    $user_nick = $user_nick ? $user_nick : $tmp[0];
    $user_name = $user_name ? $user_name : $tmp[0];
}

// 중복 체크 및 고유값 생성
$user_id = exist_mb_id_recursive($user_id);
$user_nick = exist_mb_nick_recursive($user_nick, '');
$is_exists_email = $user_email ? exist_mb_email($user_email, '') : false;

// 자동 회원가입 데이터 설정
$mb_id = $user_id;
$mb_nick = $user_nick;
$mb_email = $user_email;
$mb_name = $user_name;
$mb_hp = $user_phone ? $user_phone : '';
$mb_password = md5(pack('V*', rand(), rand(), rand(), rand())); // 랜덤 비밀번호

// 필수 검증
if ($msg = valid_mb_id($mb_id))         alert($msg, "", true, true);
if ($msg = empty_mb_name($mb_name))     alert($msg, "", true, true);
if ($msg = empty_mb_nick($mb_nick))     alert($msg, "", true, true);
if ($msg = empty_mb_email($mb_email))   alert($msg, "", true, true);
if ($msg = reserve_mb_id($mb_id))       alert($msg, "", true, true);
if ($msg = reserve_mb_nick($mb_nick))   alert($msg, "", true, true);
if ($msg = valid_mb_nick($mb_nick))     alert($msg, "", true, true);
if ($msg = valid_mb_email($mb_email))   alert($msg, "", true, true);
if ($msg = prohibit_mb_email($mb_email)) alert($msg, "", true, true);

// 중복 체크
if ($msg = exist_mb_id($mb_id))         alert($msg);
if ($msg = exist_mb_nick($mb_nick, $mb_id)) alert($msg, "", true, true);
if ($msg = exist_mb_email($mb_email, $mb_id)) alert($msg, "", true, true);

// 이미 등록된 회원인지 확인
if( $mb = get_member($mb_id) ){
    alert("이미 등록된 회원이 존재합니다.", G5_URL);
}

// 메일 인증 설정
$mb_email_certify = G5_TIME_YMDHIS;

// 소셜 로그인은 메일 인증을 사용하지 않음
if( defined('G5_SOCIAL_CERTIFY_MAIL') && G5_SOCIAL_CERTIFY_MAIL && $config['cf_use_email_certify'] ){
    $mb_email_certify = '';
}

// 기본 설정
$mb_mailling = 0; // 메일링 수신 거부
$mb_open = 0; // 정보 공개 거부

// 이름, 닉네임에 utf-8 이외의 문자가 포함됐다면 처리
$tmp_mb_name = iconv('UTF-8', 'UTF-8//IGNORE', $mb_name);
if($tmp_mb_name != $mb_name) {
    $mb_name = $tmp_mb_name;
}
$tmp_mb_nick = iconv('UTF-8', 'UTF-8//IGNORE', $mb_nick);
if($tmp_mb_nick != $mb_nick) {
    $mb_nick = $tmp_mb_nick;
}

// 휴대폰 번호 처리
$mb_hp = hyphen_hp_number($mb_hp);

// 회원정보 입력
$sql = " insert into {$g5['member_table']}
            set mb_id = '{$mb_id}',
                mb_password = '".get_encrypt_string($mb_password)."',
                mb_name = '{$mb_name}',
                mb_nick = '{$mb_nick}',
                mb_nick_date = '".G5_TIME_YMD."',
                mb_email = '{$mb_email}',
                mb_email_certify = '".$mb_email_certify."',
                mb_hp = '{$mb_hp}',
                mb_today_login = '".G5_TIME_YMDHIS."',
                mb_datetime = '".G5_TIME_YMDHIS."',
                mb_ip = '{$_SERVER['REMOTE_ADDR']}',
                mb_level = '{$config['cf_register_level']}',
                mb_login_ip = '{$_SERVER['REMOTE_ADDR']}',
                mb_mailling = '{$mb_mailling}',
                mb_sms = '0',
                mb_open = '{$mb_open}',
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
    if( function_exists('social_login_success_after') ){
        social_login_success_after($mb, '', 'register');
    }

    set_session('ss_mb_reg', $mb['mb_id']);

    // 회원 프로필 사진 처리
    if( !empty($user_profile->photoURL) && ($config['cf_register_level'] >= $config['cf_icon_level']) ){
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

    // 자동 로그인 처리 (메일 인증 사용 안함)
    if( $mb_email_certify ){
        set_session('ss_mb_id', $mb['mb_id']);
        if(function_exists('update_auth_session_token')) update_auth_session_token(G5_TIME_YMDHIS);
    }

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
    $redirect_url = isset($_SESSION['ss_redirect_url']) ? $_SESSION['ss_redirect_url'] : G5_URL;
    unset($_SESSION['ss_redirect_url']);
    
    // 회원가입 완료 메시지 표시 후 리다이렉트
    alert('카카오 로그인으로 회원가입이 완료되었습니다.', $redirect_url);

} else {
    alert('회원 가입 오류!', G5_URL);
}
?>