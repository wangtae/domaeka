<?php
$sub_menu = "190200";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
// 관리자 액션 로깅 라이브러리 포함
include_once(G5_DMK_PATH . "/adm/lib/admin.log.lib.php"); 

// 메뉴 접근 권한 확인
$w = $_POST['w'];
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';

dmk_authenticate_form_access('agency_form', $w, $ag_id);

check_demo();

// POST 데이터 처리
$dt_id = isset($_POST['dt_id']) ? clean_xss_tags($_POST['dt_id']) : '';
$ag_status = isset($_POST['ag_status']) ? (int)$_POST['ag_status'] : 1;

// g5_member 관련 필드들
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_password_confirm = isset($_POST['mb_password_confirm']) ? $_POST['mb_password_confirm'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_nick = isset($_POST['mb_nick']) ? clean_xss_tags($_POST['mb_nick']) : '';
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_tel = isset($_POST['mb_tel']) ? clean_xss_tags($_POST['mb_tel']) : '';
$mb_hp = isset($_POST['mb_hp']) ? clean_xss_tags($_POST['mb_hp']) : '';
$mb_zip1 = isset($_POST['mb_zip1']) ? clean_xss_tags($_POST['mb_zip1']) : '';
$mb_zip2 = isset($_POST['mb_zip2']) ? clean_xss_tags($_POST['mb_zip2']) : '';
$mb_addr1 = isset($_POST['mb_addr1']) ? clean_xss_tags($_POST['mb_addr1']) : '';
$mb_addr2 = isset($_POST['mb_addr2']) ? clean_xss_tags($_POST['mb_addr2']) : '';
$mb_addr3 = isset($_POST['mb_addr3']) ? clean_xss_tags($_POST['mb_addr3']) : '';
$mb_addr_jibeon = isset($_POST['mb_addr_jibeon']) ? clean_xss_tags($_POST['mb_addr_jibeon']) : '';

// 현재 로그인한 관리자 정보
$current_admin = dmk_get_admin_auth();

// 필수 필드 검사
if (!$ag_id) {
    alert('대리점 ID를 입력하세요.');
}

if (!$mb_nick) {
    alert('회사명/대리점명을 입력하세요.');
}

if (!$mb_name) {
    alert('대표자명을 입력하세요.');
}

if (!$mb_email) {
    alert('이메일을 입력하세요.');
}

if (!$dt_id) {
    alert('소속 총판을 선택하세요.');
}

// 대리점 ID 유효성 검사
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $ag_id)) {
    alert('대리점 ID는 영문, 숫자, 언더스코어만 사용 가능하며 3~20자여야 합니다.');
}

// 이메일 유효성 검사
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert('올바른 이메일 형식이 아닙니다.');
}

// 비밀번호 검사 (입력된 경우에만)
if ($mb_password) {
    if (strlen($mb_password) < 8) {
        alert('비밀번호는 8자 이상이어야 합니다.');
    }
    
    if ($mb_password !== $mb_password_confirm) {
        alert('비밀번호가 일치하지 않습니다.');
    }
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
                dt_id = '" . sql_escape_string($dt_id) . "',
                ag_status = $ag_status
             WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    sql_query($sql);
    
    // 관리자 정보 업데이트
    $sql_member = " UPDATE {$g5['member_table']} SET 
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_nick = '" . sql_escape_string($mb_nick) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_tel = '" . sql_escape_string($mb_tel) . "',
                mb_hp = '" . sql_escape_string($mb_hp) . "',
                mb_zip1 = '" . sql_escape_string($mb_zip1) . "',
                mb_zip2 = '" . sql_escape_string($mb_zip2) . "',
                mb_addr1 = '" . sql_escape_string($mb_addr1) . "',
                mb_addr2 = '" . sql_escape_string($mb_addr2) . "',
                mb_addr3 = '" . sql_escape_string($mb_addr3) . "',
                mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "',
                dmk_dt_id = '" . sql_escape_string($dt_id) . "' ";
    
    // 비밀번호가 입력된 경우에만 업데이트
    if ($mb_password) {
        $sql_member .= ", mb_password = '" . sql_escape_string(get_encrypt_password($mb_password)) . "' ";
    }
    
    $sql_member .= " WHERE mb_id = '" . sql_escape_string($ag_id) . "' ";
    sql_query($sql_member);
    
    // 관리자 액션 로깅
    dmk_log_admin_action('edit', '대리점 정보 수정', '대리점ID: ' . $ag_id, json_encode($_POST), null, 'agency_form', 'dmk_agency');
    
    $msg = '대리점이 수정되었습니다.';
    
} else {
    // 등록
    
    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
    }
    
    // 대리점 ID 중복 확인
    $sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 대리점 ID입니다.');
    }
    
    // 관리자 아이디 중복 확인 (대리점 ID가 관리자 ID로 사용됨)
    $sql = " SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 관리자 아이디입니다. (대리점 ID와 동일)');
    }
    
    // 이메일 중복 확인
    $sql = " SELECT mb_id FROM {$g5['member_table']} WHERE mb_email = '" . sql_escape_string($mb_email) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 이메일입니다.');
    }
    
    // 비밀번호 암호화
    $mb_password_encrypted = get_encrypt_password($mb_password);
    
    // 관리자 계정 생성 (main 관리자로 설정)
    $sql = " INSERT INTO {$g5['member_table']} SET 
                mb_id = '" . sql_escape_string($ag_id) . "',
                mb_password = '" . sql_escape_string($mb_password_encrypted) . "',
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_nick = '" . sql_escape_string($mb_nick) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_tel = '" . sql_escape_string($mb_tel) . "',
                mb_hp = '" . sql_escape_string($mb_hp) . "',
                mb_zip1 = '" . sql_escape_string($mb_zip1) . "',
                mb_zip2 = '" . sql_escape_string($mb_zip2) . "',
                mb_addr1 = '" . sql_escape_string($mb_addr1) . "',
                mb_addr2 = '" . sql_escape_string($mb_addr2) . "',
                mb_addr3 = '" . sql_escape_string($mb_addr3) . "',
                mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "',
                mb_level = 6,
                mb_datetime = NOW(),
                mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                dmk_mb_type = 2,
                dmk_dt_id = '" . sql_escape_string($dt_id) . "',
                dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                dmk_admin_type = 'main',
                mb_email_certify = NOW(),
                mb_mailling = 1,
                mb_open = 1 ";
    sql_query($sql);
    
    // 대리점 등록
    $sql = " INSERT INTO dmk_agency SET 
                ag_id = '" . sql_escape_string($ag_id) . "',
                dt_id = '" . sql_escape_string($dt_id) . "',
                ag_datetime = NOW(),
                ag_status = $ag_status,
                ag_created_by = '" . sql_escape_string($current_admin['mb_id']) . "',
                ag_admin_type = 'main' ";
    sql_query($sql);
    
    // 관리자 액션 로깅
    dmk_log_admin_action('insert', '대리점 등록', '대리점ID: ' . $ag_id, json_encode($_POST), null, 'agency_form', 'dmk_agency');
    
    $msg = '대리점과 관리자가 등록되었습니다.';
}

// 기존 get_query_string()의 역할을 대체합니다.
$current_query_string = $_SERVER['QUERY_STRING'] ?? '';
parse_str($current_query_string, $params);

// 제외할 파라미터들
$exclude_params = array('w', 'ag_id');
foreach ($exclude_params as $param) {
    unset($params[$param]);
}

$qstr = http_build_query($params, '', '&');

goto_url('./agency_list.php?' . $qstr, false, $msg);
?> 