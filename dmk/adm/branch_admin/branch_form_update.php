<?php
$sub_menu = "190300";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('branch_form')) {
    alert('접근 권한이 없습니다.');
}

dmk_auth_check_menu($auth, $sub_menu, 'w');

check_demo();

$w = $_POST['w'];
$br_id = isset($_POST['br_id']) ? clean_xss_tags($_POST['br_id']) : '';
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$br_shortcut_code = isset($_POST['br_shortcut_code']) ? clean_xss_tags($_POST['br_shortcut_code']) : '';
$br_status = isset($_POST['br_status']) ? (int)$_POST['br_status'] : 1;

// 관리자 계정 정보 (g5_member 테이블에 저장될 필드)
$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_password_confirm = isset($_POST['mb_password_confirm']) ? $_POST['mb_password_confirm'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_nick = isset($_POST['mb_nick']) ? clean_xss_tags($_POST['mb_nick']) : ''; // 지점명으로 사용
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_tel = isset($_POST['mb_tel']) ? clean_xss_tags($_POST['mb_tel']) : '';
$mb_hp = isset($_POST['mb_hp']) ? clean_xss_tags($_POST['mb_hp']) : '';
$mb_zip = isset($_POST['mb_zip']) ? clean_xss_tags($_POST['mb_zip']) : '';
$mb_addr1 = isset($_POST['mb_addr1']) ? clean_xss_tags($_POST['mb_addr1']) : '';
$mb_addr2 = isset($_POST['mb_addr2']) ? clean_xss_tags($_POST['mb_addr2']) : '';
$mb_addr3 = isset($_POST['mb_addr3']) ? clean_xss_tags($_POST['mb_addr3']) : '';
$mb_addr_jibeon = isset($_POST['mb_addr_jibeon']) ? clean_xss_tags($_POST['mb_addr_jibeon']) : '';

// 현재 로그인한 관리자 정보
$current_admin = dmk_get_admin_auth();

if (!$br_id) {
    alert('지점 ID를 입력하세요.');
}

if (!$ag_id) {
    alert('소속 대리점을 선택하세요.');
}

if (!$mb_nick) {
    alert('지점명을 입력하세요.');
}

if (!$mb_id) {
    alert('관리자 아이디를 입력하세요.');
}

if (!$mb_email) {
    alert('이메일을 입력하세요.');
}

// 관리자 아이디 유효성 검사
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $mb_id)) {
    alert('관리자 아이디는 영문, 숫자, 언더스코어만 사용 가능하며 3~20자여야 합니다.');
}

// 이메일 유효성 검사
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert('올바른 이메일 형식이 아닙니다.');
}

// 단축 URL 코드 유효성 검사 (입력된 경우)
if ($br_shortcut_code && !preg_match('/^[a-zA-Z0-9_-]+$/', $br_shortcut_code)) {
    alert('단축 URL 코드는 영문, 숫자, 하이픈, 언더스코어만 사용 가능합니다.');
}

// 대리점 존재 여부 확인
$agency_sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' AND ag_status = 1 ";
$agency = sql_fetch($agency_sql);

if (!$agency) {
    alert('선택한 대리점이 존재하지 않거나 비활성 상태입니다.');
}

if ($w == 'u') {
    // 수정
    $sql = " SELECT br_id FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    if (!$row) {
        alert('존재하지 않는 지점입니다.');
    }
    
    // 단축 URL 코드 중복 확인 (자신 제외)
    if ($br_shortcut_code) {
        $sql = " SELECT br_id FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' AND br_id != '" . sql_escape_string($br_id) . "' ";
        $row = sql_fetch($sql);
        
        if ($row) {
            alert('이미 사용중인 단축 URL 코드입니다.');
        }
    }
    
    // 지점 정보 업데이트
    $sql = " UPDATE dmk_branch SET 
                ag_id = '" . sql_escape_string($ag_id) . "',
                br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "',
                br_status = $br_status
             WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    sql_query($sql);
    
    // g5_member 테이블의 관리자 정보 업데이트 (br_id를 mb_id로 사용)
    $mb_password_update = '';
    if ($mb_password) {
        if ($mb_password != $mb_password_confirm) {
            alert('비밀번호가 일치하지 않습니다.');
        }
        if (strlen($mb_password) < 6) {
            alert('비밀번호는 6자 이상이어야 합니다.');
        }
        $mb_password_update = ", mb_password = '" . get_encrypt_string($mb_password) . "'";
    }

    $sql = " UPDATE {$g5['member_table']} SET 
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_nick = '" . sql_escape_string($mb_nick) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_tel = '" . sql_escape_string($mb_tel) . "',
                mb_hp = '" . sql_escape_string($mb_hp) . "',
                mb_zip = '" . sql_escape_string($mb_zip) . "',
                mb_addr1 = '" . sql_escape_string($mb_addr1) . "',
                mb_addr2 = '" . sql_escape_string($mb_addr2) . "',
                mb_addr3 = '" . sql_escape_string($mb_addr3) . "',
                mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "'
                $mb_password_update
             WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    sql_query($sql);
    
    $msg = '지점이 수정되었습니다.';
    
} else {
    // 등록
    
    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
    }
    if ($mb_password != $mb_password_confirm) {
        alert('비밀번호가 일치하지 않습니다.');
    }
    // 비밀번호 강도 체크
    if (strlen($mb_password) < 6) {
        alert('비밀번호는 6자 이상이어야 합니다.');
    }
    
    // 지점 ID 중복 확인
    $sql = " SELECT br_id FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 지점 ID입니다.');
    }
    
    // 단축 URL 코드 중복 확인 (입력된 경우)
    if ($br_shortcut_code) {
        $sql = " SELECT br_id FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' ";
        $row = sql_fetch($sql);
        
        if ($row) {
            alert('이미 사용중인 단축 URL 코드입니다.');
        }
    }
    
    // 관리자 아이디 중복 확인
    $sql = " SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 관리자 아이디입니다.');
    }
    
    // 이메일 중복 확인
    $sql = " SELECT mb_id FROM {$g5['member_table']} WHERE mb_email = '" . sql_escape_string($mb_email) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 이메일입니다.');
    }
    
    // 비밀번호 암호화
    $mb_password_encrypted = get_encrypt_string($mb_password);
    
    // 관리자 계정 생성 (main 관리자로 설정)
    $sql = " INSERT INTO {$g5['member_table']} SET 
                mb_id = '" . sql_escape_string($mb_id) . "',
                mb_password = '" . sql_escape_string($mb_password_encrypted) . "',
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_nick = '" . sql_escape_string($mb_nick) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_tel = '" . sql_escape_string($mb_tel) . "',
                mb_hp = '" . sql_escape_string($mb_hp) . "',
                mb_zip = '" . sql_escape_string($mb_zip) . "',
                mb_addr1 = '" . sql_escape_string($mb_addr1) . "',
                mb_addr2 = '" . sql_escape_string($mb_addr2) . "',
                mb_addr3 = '" . sql_escape_string($mb_addr3) . "',
                mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "',
                mb_level = 4,
                mb_datetime = NOW(),
                mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                dmk_mb_type = 3,
                dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                dmk_br_id = '" . sql_escape_string($br_id) . "',
                dmk_admin_type = 'main',
                mb_email_certify = NOW(),
                mb_mailling = 1,
                mb_open = 1 ";
    sql_query($sql);
    
    // 지점 등록 (dmk_branch 테이블에 존재하는 필드만)
    $sql = " INSERT INTO dmk_branch SET 
                br_id = '" . sql_escape_string($br_id) . "',
                ag_id = '" . sql_escape_string($ag_id) . "',
                br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "',
                br_datetime = NOW(),
                br_status = $br_status ";
    sql_query($sql);
    
    $msg = '지점과 관리자가 등록되었습니다.';
}

goto_url('./branch_list.php?'.$qstr, false, $msg);
?> 