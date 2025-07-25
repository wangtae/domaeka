<?php
include_once './_common.php';
require_once G5_LIB_PATH . "/register.lib.php";
require_once G5_PATH . "/dmk/adm/lib/admin.log.lib.php";

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');


$w = isset($_POST['w']) ? sql_escape_string(trim($_POST['w'])) : '';
$ag_id = isset($_POST['ag_id']) ? sql_escape_string(trim($_POST['ag_id'])) : '';

// 현재 로그인한 관리자 정보 (early definition)
$current_admin = dmk_get_admin_auth();

// 메뉴 접근 권한 확인
// dmk_authenticate_form_access 함수를 사용하여 통합 권한 체크
dmk_authenticate_form_access('agency_form', $w, $ag_id);

check_admin_token();

// POST 데이터 처리
$dt_id = isset($_POST['dt_id']) ? sql_escape_string(trim($_POST['dt_id'])) : '';
$ag_status = isset($_POST['ag_status']) ? (int)$_POST['ag_status'] : 1;

// 대리점 관리자가 자신의 대리점을 수정하는 경우 총판 ID 자동 설정
if ($w == 'u' && $current_admin['mb_type'] == DMK_MB_TYPE_AGENCY) {
    if (empty($dt_id) && !empty($current_admin['dt_id'])) {
        $dt_id = $current_admin['dt_id'];
    }
}

// g5_member 관련 필드들
$mb_password = isset($_POST['mb_password']) ? trim($_POST['mb_password']) : '';
$mb_password_confirm = isset($_POST['mb_password_confirm']) ? trim($_POST['mb_password_confirm']) : '';
$mb_name = isset($_POST['mb_name']) ? sql_escape_string(trim($_POST['mb_name'])) : '';
$mb_nick = isset($_POST['mb_nick']) ? sql_escape_string(trim($_POST['mb_nick'])) : '';
$mb_email = isset($_POST['mb_email']) ? sql_escape_string(trim($_POST['mb_email'])) : '';
$mb_tel = isset($_POST['mb_tel']) ? sql_escape_string(trim($_POST['mb_tel'])) : '';
$mb_hp = isset($_POST['mb_hp']) ? sql_escape_string(trim($_POST['mb_hp'])) : '';
// mb_zip을 mb_zip1과 mb_zip2로 분리
$mb_zip_full = isset($_POST['mb_zip']) ? trim($_POST['mb_zip']) : '';
$mb_zip1 = substr($mb_zip_full, 0, 3);
$mb_zip2 = substr($mb_zip_full, 3);
$mb_addr1 = isset($_POST['mb_addr1']) ? sql_escape_string(trim($_POST['mb_addr1'])) : '';
$mb_addr2 = isset($_POST['mb_addr2']) ? sql_escape_string(trim($_POST['mb_addr2'])) : '';
$mb_addr3 = isset($_POST['mb_addr3']) ? sql_escape_string(trim($_POST['mb_addr3'])) : '';
$mb_addr_jibeon = isset($_POST['mb_addr_jibeon']) ? sql_escape_string(trim($_POST['mb_addr_jibeon'])) : '';

// 대리점은 mb_level 6, dmk_mb_type 2, dmk_admin_type main 고정
define('DMK_AGENCY_MB_LEVEL', 6);
define('DMK_AGENCY_MB_TYPE', 2);
define('DMK_AGENCY_ADMIN_TYPE', 'main');

// 필수 필드 검사
if (!$dt_id) {
    alert('소속 총판이 누락되었습니다.');
    exit;
}

if (!$ag_id) {
    alert('대리점 ID가 누락되었습니다.');
    exit;
}

// 아이디 유효성 검증
$id_validation = dmk_validate_member_id($ag_id);
if ($id_validation !== true) {
    alert($id_validation);
    exit;
}

if (!$mb_name) {
    alert('대리점 이름이 누락되었습니다.');
    exit;
}
if (!$mb_nick) {
    alert('회사명이 누락되었습니다.');
    exit;
}

// 권한 검사: 총판 관리자는 자신의 총판에만 대리점 등록 가능
if (!$current_admin['is_super'] && $current_admin['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    if ($dt_id != $current_admin['dt_id']) {
        alert('자신의 총판에만 대리점을 등록할 수 있습니다.');
        exit;
    }
}

if ($w == '') { // 등록
    // ID 중복 확인
    $row = sql_fetch(" SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '$ag_id' ");
    if ($row && isset($row['mb_id'])) {
        alert('이미 존재하는 회원 아이디입니다.');
        exit;
    }

    if (!$mb_password) {
        alert('비밀번호를 입력해 주십시오.');
        exit;
    }
    
    // 비밀번호 유효성 검증
    $password_validation = dmk_validate_password($ag_id, $mb_password, $mb_password_confirm);
    if ($password_validation !== true) {
        alert($password_validation);
        exit;
    }

    $mb_password_hash = sql_password($mb_password);

    // g5_member 테이블에 대리점 등록
    $sql = " INSERT INTO {$g5['member_table']} SET
                mb_id = '$ag_id',
                mb_password = '$mb_password_hash',
                mb_name = '$mb_name',
                mb_nick = '$mb_nick',
                mb_hp = '$mb_hp',
                mb_tel = '$mb_tel',
                mb_email = '$mb_email',
                mb_zip1 = '$mb_zip1',
                mb_zip2 = '$mb_zip2',
                mb_addr1 = '$mb_addr1',
                mb_addr2 = '$mb_addr2',
                mb_addr3 = '$mb_addr3',
                mb_addr_jibeon = '$mb_addr_jibeon',
                mb_datetime = now(),
                mb_ip = '$REMOTE_ADDR',
                mb_level = " . DMK_AGENCY_MB_LEVEL . ",
                dmk_mb_type = " . DMK_AGENCY_MB_TYPE . ",
                dmk_dt_id = '$dt_id',
                dmk_ag_id = '$ag_id',
                dmk_admin_type = '" . DMK_AGENCY_ADMIN_TYPE . "';";
    sql_query($sql);

    // dmk_agency 테이블에 대리점 정보 등록
    $sql = " INSERT INTO dmk_agency SET
                ag_id = '$ag_id',
                dt_id = '$dt_id',
                ag_status = '$ag_status',
                ag_created_by = '$current_admin[mb_id]',
                ag_admin_type = '" . DMK_AGENCY_ADMIN_TYPE . "';";
    sql_query($sql);

    // 관리자 액션 로그
    dmk_log_admin_action('insert', '대리점 등록', '대리점ID: '.$ag_id, json_encode($_POST), null, 'agency_form', 'g5_member,dmk_agency');

    goto_url('./agency_list.php');
    exit;

} else if ($w == 'u') { // 수정
    // 수정 전 기존 데이터 조회 (로그용)
    $old_member_sql = " SELECT mb_id, mb_name, mb_nick, mb_hp, mb_tel, mb_email, 
                               mb_zip1, mb_zip2, mb_addr1, mb_addr2, mb_addr3, mb_addr_jibeon
                        FROM {$g5['member_table']} WHERE mb_id = '$ag_id' ";
    $old_member_row = sql_fetch($old_member_sql);
    
    $old_agency_sql = " SELECT ag_id, ag_status FROM dmk_agency WHERE ag_id = '$ag_id' ";
    $old_agency_row = sql_fetch($old_agency_sql);
    
    // 기존 데이터 병합
    $old_data = array_merge($old_member_row ?: [], $old_agency_row ?: []);
    
    if ($mb_password) {
        // 비밀번호 유효성 검증
        $password_validation = dmk_validate_password($ag_id, $mb_password, $mb_password_confirm);
        if ($password_validation !== true) {
            alert($password_validation);
            exit;
        }
        
        $mb_password_hash = ", mb_password = '" . sql_password($mb_password) . "'";
    } else {
        $mb_password_hash = '';
    }

    // g5_member 테이블 정보 업데이트
    $sql = " UPDATE {$g5['member_table']} SET
                mb_name = '$mb_name',
                mb_nick = '$mb_nick',
                mb_hp = '$mb_hp',
                mb_tel = '$mb_tel',
                mb_email = '$mb_email',
                mb_zip1 = '$mb_zip1',
                mb_zip2 = '$mb_zip2',
                mb_addr1 = '$mb_addr1',
                mb_addr2 = '$mb_addr2',
                mb_addr3 = '$mb_addr3',
                mb_addr_jibeon = '$mb_addr_jibeon'
                $mb_password_hash
              WHERE mb_id = '$ag_id' ";
    sql_query($sql);

    // dmk_agency 테이블 상태 업데이트
    $sql = " UPDATE dmk_agency SET
                ag_status = '$ag_status'
              WHERE ag_id = '$ag_id' ";
    sql_query($sql);

    // 새로운 데이터 구성 (비밀번호 제외)
    $new_data = [
        'mb_id' => $ag_id,
        'mb_name' => $mb_name,
        'mb_nick' => $mb_nick,
        'mb_hp' => $mb_hp,
        'mb_tel' => $mb_tel,
        'mb_email' => $mb_email,
        'mb_zip1' => $mb_zip1,
        'mb_zip2' => $mb_zip2,
        'mb_addr1' => $mb_addr1,
        'mb_addr2' => $mb_addr2,
        'mb_addr3' => $mb_addr3,
        'mb_addr_jibeon' => $mb_addr_jibeon,
        'ag_status' => $ag_status
    ];

    // 관리자 액션 로그 (향상된 변경 내용 추적)
    dmk_log_update_action('대리점 정보 수정', '대리점ID: '.$ag_id, $new_data, $old_data, 'agency_form', 'g5_member,dmk_agency');

    goto_url('./agency_form.php?w=u&ag_id='.$ag_id);
    exit;
} else {
    alert('잘못된 접근입니다.');
    exit;
}

include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 