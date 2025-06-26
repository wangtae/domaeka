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

check_token();

// POST 데이터 처리
$dt_id = isset($_POST['dt_id']) ? sql_escape_string(trim($_POST['dt_id'])) : '';
$ag_status = isset($_POST['ag_status']) ? (int)$_POST['ag_status'] : 1;

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
if (!$ag_id) {
    alert('대리점 ID가 누락되었습니다.');
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
    if ($mb_password != $mb_password_confirm) {
        alert('비밀번호가 일치하지 않습니다.');
        exit;
    }
    if (strlen($mb_password) < 8) {
        alert('비밀번호를 8글자 이상 입력하십시오.');
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

    goto_url('./agency_list.php', '대리점이 성공적으로 등록되었습니다.');
    exit;

} else if ($w == 'u') { // 수정
    if ($mb_password) {
        if ($mb_password != $mb_password_confirm) {
            alert('비밀번호가 일치하지 않습니다.');
            exit;
        }
        if (strlen($mb_password) < 8) {
            alert('비밀번호를 8글자 이상 입력하십시오.');
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
                mb_addr_jibeon = '$mb_addr_jibeon',
                dmk_dt_id = '$dt_id'
                $mb_password_hash
              WHERE mb_id = '$ag_id' ";
    sql_query($sql);

    // dmk_agency 테이블 상태 업데이트
    $sql = " UPDATE dmk_agency SET
                dt_id = '$dt_id',
                ag_status = '$ag_status'
              WHERE ag_id = '$ag_id' ";
    sql_query($sql);

    // 관리자 액션 로그
    dmk_log_admin_action('edit', '대리점 정보 수정', '대리점ID: '.$ag_id, json_encode($_POST), null, 'agency_form', 'g5_member,dmk_agency');

    goto_url('./agency_form.php?w=u&ag_id='.$ag_id, '대리점 정보가 성공적으로 수정되었습니다.');
    exit;
} else {
    alert('잘못된 접근입니다.');
    exit;
}

include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 