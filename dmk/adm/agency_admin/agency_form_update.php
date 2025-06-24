<?php
$sub_menu = "200100";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('agency_form')) {
    alert('접근 권한이 없습니다.');
}

auth_check_menu($auth, $sub_menu, 'w');

check_demo();

$w = $_POST['w'];
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$ag_name = isset($_POST['ag_name']) ? clean_xss_tags($_POST['ag_name']) : '';
$ag_ceo_name = isset($_POST['ag_ceo_name']) ? clean_xss_tags($_POST['ag_ceo_name']) : '';
$ag_phone = isset($_POST['ag_phone']) ? clean_xss_tags($_POST['ag_phone']) : '';
$ag_address = isset($_POST['ag_address']) ? clean_xss_tags($_POST['ag_address']) : '';
$ag_status = isset($_POST['ag_status']) ? (int)$_POST['ag_status'] : 1;

// 관리자 계정 정보
$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_phone = isset($_POST['mb_phone']) ? clean_xss_tags($_POST['mb_phone']) : '';

// 현재 로그인한 관리자 정보
$current_admin = dmk_get_admin_auth();

if (!$ag_id) {
    alert('대리점 ID를 입력하세요.');
}

if (!$ag_name) {
    alert('대리점명을 입력하세요.');
}

if (!$mb_id) {
    alert('관리자 아이디를 입력하세요.');
}

if (!$mb_name) {
    alert('관리자 이름을 입력하세요.');
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

if ($w == 'u') {
    // 수정
    $sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if (!$row) {
        alert('존재하지 않는 대리점입니다.');
    }
    
    // 대리점 정보 업데이트
    $sql = " UPDATE dmk_agency SET 
                ag_name = '" . sql_escape_string($ag_name) . "',
                ag_ceo_name = '" . sql_escape_string($ag_ceo_name) . "',
                ag_phone = '" . sql_escape_string($ag_phone) . "',
                ag_address = '" . sql_escape_string($ag_address) . "',
                ag_status = $ag_status
             WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    sql_query($sql);
    
    // 관리자 정보 업데이트 (비밀번호 제외)
    $sql = " UPDATE {$g5['member_table']} SET 
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_phone = '" . sql_escape_string($mb_phone) . "'
             WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    sql_query($sql);
    
    $msg = '대리점이 수정되었습니다.';
    
} else {
    // 등록
    
    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
    }
    
    // 비밀번호 강도 체크
    if (strlen($mb_password) < 6) {
        alert('비밀번호는 6자 이상이어야 합니다.');
    }
    
    // 대리점 ID 중복 확인
    $sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 대리점 ID입니다.');
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
                mb_nick = '" . sql_escape_string($mb_name) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_phone = '" . sql_escape_string($mb_phone) . "',
                mb_level = 6,
                mb_datetime = NOW(),
                mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                dmk_mb_type = 2,
                dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                dmk_admin_type = 'main',
                mb_email_certify = NOW(),
                mb_mailling = 1,
                mb_open = 1 ";
    sql_query($sql);
    
    // 대리점 등록
    $sql = " INSERT INTO dmk_agency SET 
                ag_id = '" . sql_escape_string($ag_id) . "',
                ag_name = '" . sql_escape_string($ag_name) . "',
                ag_ceo_name = '" . sql_escape_string($ag_ceo_name) . "',
                ag_phone = '" . sql_escape_string($ag_phone) . "',
                ag_address = '" . sql_escape_string($ag_address) . "',
                ag_mb_id = '" . sql_escape_string($mb_id) . "',
                ag_datetime = NOW(),
                ag_status = $ag_status,
                ag_created_by = '" . sql_escape_string($current_admin['mb_id']) . "',
                ag_admin_type = 'main' ";
    sql_query($sql);
    
    $msg = '대리점과 관리자가 등록되었습니다.';
}

goto_url('./agency_list.php?'.$qstr, false, $msg);
?> 