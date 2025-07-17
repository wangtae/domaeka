<?php
include_once('./_common.php');
include_once(G5_LIB_PATH.'/register.lib.php');
include_once(G5_LIB_PATH.'/mailer.lib.php');

// 소셜 로그인 사용 확인
if (!$config['cf_social_login_use']) {
    alert('소셜 로그인을 사용하지 않습니다.', G5_URL);
}

// 이미 로그인된 경우
if ($is_member) {
    alert('이미 회원가입 하였습니다.', G5_URL);
}

// 소셜 로그인 정보 확인
$provider_name = social_get_request_provider();
$user_profile = social_session_exists_check();
if (!$user_profile) {
    alert("소셜로그인을 하신 분만 접근할 수 있습니다.", G5_URL);
}

// 이미 가입된 회원인지 확인
$is_exists_social_account = social_before_join_check($url);

// 카카오 프로필 정보 추출
$user_id = $user_profile->sid ? preg_replace("/[^0-9a-z_]+/i", "", $user_profile->sid) : get_social_convert_id($user_profile->identifier, $provider_name);
$user_nick = social_relace_nick($user_profile->displayName);
$user_email = isset($user_profile->emailVerified) ? $user_profile->emailVerified : $user_profile->email;
$user_name = isset($user_profile->username) ? $user_profile->username : '';
$user_phone = isset($user_profile->phone) ? $user_profile->phone : '';

// 닉네임이 없으면 ID에서 생성
if (!$user_nick) {
    $tmp = explode('_', $user_id);
    $user_nick = isset($tmp[1]) ? $tmp[1] : $user_id;
}

// 이름이 없으면 닉네임 사용
if (!$user_name) {
    $user_name = $user_nick;
}

// 이메일이 없으면 임시 이메일 생성
if (!$user_email) {
    $user_email = 'kakao_' . $user_id . '@kakao.local';
}

// 중복 체크하여 고유한 ID와 닉네임 생성
$mb_id = exist_mb_id_recursive($user_id);
$mb_nick = exist_mb_nick_recursive($user_nick, '');

// 랜덤 비밀번호 생성
$mb_password = md5(pack('V*', rand(), rand(), rand(), rand()));

// 휴대폰 번호 형식 처리
$mb_hp = hyphen_hp_number($user_phone);

// URL에서 지점 코드 추출 및 계층 정보 설정
$dmk_br_id = '';
$dmk_ag_id = '';
$dmk_dt_id = '';

// 세션에 저장된 원래 URL에서 코드 추출
$original_url = isset($_SESSION['ss_redirect_url']) ? $_SESSION['ss_redirect_url'] : '';
if ($original_url) {
    // URL에서 /go/코드 패턴 추출
    if (preg_match('#/go/([a-zA-Z0-9_-]+)#', $original_url, $matches)) {
        $url_code = $matches[1];
        
        // 지점 정보 조회
        $branch_sql = " SELECT b.br_id, b.ag_id
                        FROM dmk_branch b 
                        WHERE (b.br_shortcut_code = '".sql_real_escape_string($url_code)."' 
                               OR b.br_id = '".sql_real_escape_string($url_code)."')
                        AND b.br_status = 1 
                        LIMIT 1 ";
        $branch = sql_fetch($branch_sql);
        
        if ($branch) {
            $dmk_br_id = $branch['br_id'];
            $dmk_ag_id = $branch['ag_id'];
            // 도매까에서는 총판(distributor) 계층이 없으므로 dt_id는 빈 값으로 설정
            $dmk_dt_id = '';
        }
    }
}

// 회원 정보 DB 삽입
$sql = " INSERT INTO {$g5['member_table']} SET
            mb_id = '".sql_real_escape_string($mb_id)."',
            mb_password = '".get_encrypt_string($mb_password)."',
            mb_name = '".sql_real_escape_string($user_name)."',
            mb_nick = '".sql_real_escape_string($mb_nick)."',
            mb_nick_date = '".G5_TIME_YMD."',
            mb_email = '".sql_real_escape_string($user_email)."',
            mb_email_certify = '".G5_TIME_YMDHIS."',
            mb_hp = '".sql_real_escape_string($mb_hp)."',
            mb_today_login = '".G5_TIME_YMDHIS."',
            mb_datetime = '".G5_TIME_YMDHIS."',
            mb_ip = '{$_SERVER['REMOTE_ADDR']}',
            mb_level = '{$config['cf_register_level']}',
            mb_login_ip = '{$_SERVER['REMOTE_ADDR']}',
            mb_mailling = '0',
            mb_sms = '0',
            mb_open = '0',
            mb_open_date = '".G5_TIME_YMD."',
            dmk_br_id = '".sql_real_escape_string($dmk_br_id)."',
            dmk_ag_id = '".sql_real_escape_string($dmk_ag_id)."',
            dmk_dt_id = '".sql_real_escape_string($dmk_dt_id)."' ";

$result = sql_query($sql, false);

if ($result) {
    // 회원가입 포인트 지급
    if ($config['cf_register_point']) {
        insert_point($mb_id, $config['cf_register_point'], '회원가입 축하', '@member', $mb_id, '회원가입');
    }
    
    // 최고관리자에게 메일 발송
    if ($config['cf_email_mb_super_admin']) {
        $subject = '['.$config['cf_title'].'] '.$mb_nick .' 님께서 카카오 로그인으로 회원가입하셨습니다.';
        
        ob_start();
        include_once(G5_BBS_PATH.'/register_form_update_mail2.php');
        $content = ob_get_contents();
        ob_end_clean();
        
        mailer($mb_nick, $user_email, $config['cf_admin_email'], $subject, $content, 1);
    }
    
    $mb = get_member($mb_id);
    
    // 소셜 로그인 계정 추가
    if (function_exists('social_login_success_after')) {
        social_login_success_after($mb, '', 'register');
    }
    
    set_session('ss_mb_reg', $mb['mb_id']);
    
    // 회원 프로필 사진 처리
    if (!empty($user_profile->photoURL) && ($config['cf_register_level'] >= $config['cf_icon_level'])) {
        $mb_dir = G5_DATA_PATH.'/member/'.substr($mb_id,0,2);
        @mkdir($mb_dir, G5_DIR_PERMISSION);
        @chmod($mb_dir, G5_DIR_PERMISSION);
        $dest_path = "$mb_dir/$mb_id.gif";
        
        social_profile_img_resize($dest_path, $user_profile->photoURL, $config['cf_member_icon_width'], $config['cf_member_icon_height']);
        
        if (is_dir(G5_DATA_PATH.'/member_image/')) {
            $mb_dir = G5_DATA_PATH.'/member_image/'.substr($mb_id,0,2);
            @mkdir($mb_dir, G5_DIR_PERMISSION);
            @chmod($mb_dir, G5_DIR_PERMISSION);
            $dest_path = "$mb_dir/$mb_id.gif";
            
            social_profile_img_resize($dest_path, $user_profile->photoURL, $config['cf_member_img_width'], $config['cf_member_img_height']);
        }
    }
    
    // 자동 로그인 처리
    set_session('ss_mb_id', $mb_id);
    if (function_exists('update_auth_session_token')) {
        update_auth_session_token(G5_TIME_YMDHIS);
    }
    
    // 신규회원 쿠폰 발급
    if ($default['de_member_reg_coupon_use'] && $default['de_member_reg_coupon_term'] > 0 && $default['de_member_reg_coupon_price'] > 0) {
        $j = 0;
        $create_coupon = false;
        
        do {
            $cp_id = get_coupon_id();
            
            $sql3 = " SELECT COUNT(*) as cnt FROM {$g5['g5_shop_coupon_table']} WHERE cp_id = '$cp_id' ";
            $row3 = sql_fetch($sql3);
            
            if (!$row3['cnt']) {
                $create_coupon = true;
                break;
            } else {
                if ($j > 20) break;
                $j++;
            }
        } while(1);
        
        if ($create_coupon) {
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
            
            if ($res) {
                set_session('ss_member_reg_coupon', 1);
            }
        }
    }
    
    // 세션 정리
    unset($_SESSION['auto_register']);
    
    // 원래 URL로 리다이렉트
    $redirect_url = isset($_SESSION['ss_redirect_url']) ? $_SESSION['ss_redirect_url'] : G5_URL;
    unset($_SESSION['ss_redirect_url']);
    
    // 회원가입 완료 후 즉시 리다이렉트
    goto_url($redirect_url);
    
} else {
    alert('회원가입 중 오류가 발생했습니다.', G5_URL);
}
?>