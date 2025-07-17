<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 카카오 로그인 시 자동 회원가입 처리 함수
 * 
 * @param object $user_profile 소셜 로그인 사용자 프로필
 * @param string $provider_name 소셜 제공자 이름 (Kakao)
 * @param string $url 회원가입 후 리디렉션할 URL
 * @return bool 성공 여부
 */
function social_auto_register_member($user_profile, $provider_name, $url = '') {
    global $g5, $config;
    
    // 사용자 정보 추출
    $user_nick = social_relace_nick($user_profile->displayName);
    if (!$user_nick) {
        $user_nick = '카카오사용자';
    }
    
    $user_email = isset($user_profile->emailVerified) ? $user_profile->emailVerified : $user_profile->email;
    if (!$user_email) {
        // 카카오에서 이메일이 없는 경우 임시 이메일 생성
        $user_email = 'kakao_' . $user_profile->identifier . '@kakao.temp';
    }
    
    // 카카오 고유 ID로 회원 ID 생성
    $user_id = 'kakao_' . $user_profile->identifier;
    
    // 중복 ID 체크 및 처리
    $user_id = exist_mb_id_recursive($user_id);
    $user_nick = exist_mb_nick_recursive($user_nick, '');
    
    // 랜덤 비밀번호 생성
    $mb_password = md5(pack('V*', rand(), rand(), rand(), rand()));
    
    // 회원 정보 자동 입력
    $sql = " insert into {$g5['member_table']}
                set mb_id = '{$user_id}',
                    mb_password = '".get_encrypt_string($mb_password)."',
                    mb_name = '{$user_nick}',
                    mb_nick = '{$user_nick}',
                    mb_nick_date = '".G5_TIME_YMD."',
                    mb_email = '{$user_email}',
                    mb_email_certify = '".G5_TIME_YMDHIS."',
                    mb_today_login = '".G5_TIME_YMDHIS."',
                    mb_datetime = '".G5_TIME_YMDHIS."',
                    mb_ip = '{$_SERVER['REMOTE_ADDR']}',
                    mb_level = '{$config['cf_register_level']}',
                    mb_login_ip = '{$_SERVER['REMOTE_ADDR']}',
                    mb_mailling = '0',
                    mb_sms = '0',
                    mb_open = '1',
                    mb_open_date = '".G5_TIME_YMD."',
                    mb_1 = '자동가입' ";
                    
    $result = sql_query($sql, false);
    
    if ($result) {
        // 회원가입 후 처리
        $mb = get_member($user_id);
        
        // 소셜 프로필 연결
        social_login_success_after($mb, '', 'register');
        
        // 회원가입 포인트 지급
        if ($config['cf_register_point']) {
            insert_point($mb['mb_id'], $config['cf_register_point'], '회원가입 축하', '@member', $mb['mb_id'], '회원가입');
        }
        
        // 회원가입 메일 발송 (옵션)
        if ($config['cf_email_mb_member']) {
            $subject = '['.$config['cf_title'].'] 회원가입을 축하드립니다.';
            
            $mb_md5 = md5($mb['mb_id'].$mb['mb_email'].$mb['mb_datetime']);
            $certify_href = G5_BBS_URL.'/email_certify.php?mb_id='.$mb['mb_id'].'&amp;mb_md5='.$mb_md5;
            
            ob_start();
            include_once (G5_BBS_PATH.'/register_form_update_mail1.php');
            $content = ob_get_clean();
            
            mailer($config['cf_admin_email_name'], $config['cf_admin_email'], $mb['mb_email'], $subject, $content, 1);
        }
        
        // 최고관리자에게 메일 발송 (옵션)
        if ($config['cf_email_mb_super_admin']) {
            $subject = '['.$config['cf_title'].'] '.$mb['mb_nick'] .' 님께서 회원으로 가입하셨습니다.';
            
            ob_start();
            include_once (G5_BBS_PATH.'/register_form_update_mail2.php');
            $content = ob_get_clean();
            
            mailer($mb['mb_nick'], $mb['mb_email'], $config['cf_admin_email'], $subject, $content, 1);
        }
        
        // 자동 로그인 처리
        set_session('ss_mb_id', $mb['mb_id']);
        set_session('ss_mb_reg', $mb['mb_id']);
        
        // 쿠키 설정
        if ($config['cf_use_member_icon'] && $mb['mb_icon']) {
            set_cookie('ck_mb_icon', $mb['mb_icon'], 86400 * 31);
        }
        
        // 원래 요청한 페이지로 리다이렉트
        if ($url) {
            $link = urldecode($url);
            
            if (!preg_match('#^(http|https)://#', $link)) {
                $link = G5_URL . $link;
            }
            
            goto_url($link);
        } else {
            goto_url(G5_URL);
        }
        
        return true;
    }
    
    return false;
}

// exist_mb_id_recursive와 exist_mb_nick_recursive 함수는 
// functions.php에 이미 정의되어 있으므로 여기서는 제거합니다.