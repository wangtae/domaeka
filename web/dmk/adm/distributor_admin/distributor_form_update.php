<?php
include_once './_common.php';
require_once G5_LIB_PATH . "/register.lib.php";
require_once G5_PATH . "/dmk/adm/lib/admin.log.lib.php";

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
// dmk_authenticate_form_access 함수를 사용하여 통합 권한 체크
dmk_authenticate_form_access('distributor_form', $w, $mb_id);

check_token();

$w = isset($_POST['w']) ? sql_escape_string(trim($_POST['w'])) : '';
$mb_id = isset($_POST['mb_id']) ? sql_escape_string(trim($_POST['mb_id'])) : '';
$mb_password = isset($_POST['mb_password']) ? trim($_POST['mb_password']) : '';
$mb_password_re = isset($_POST['mb_password_re']) ? trim($_POST['mb_password_re']) : '';
$mb_name = isset($_POST['mb_name']) ? sql_escape_string(trim($_POST['mb_name'])) : '';
$mb_nick = isset($_POST['mb_nick']) ? sql_escape_string(trim($_POST['mb_nick'])) : '';
$mb_hp = isset($_POST['mb_hp']) ? sql_escape_string(trim($_POST['mb_hp'])) : '';
$mb_tel = isset($_POST['mb_tel']) ? sql_escape_string(trim($_POST['mb_tel'])) : '';
$mb_email = isset($_POST['mb_email']) ? sql_escape_string(trim($_POST['mb_email'])) : '';

// mb_zip을 mb_zip1과 mb_zip2로 분리
$mb_zip_full = isset($_POST['mb_zip']) ? trim($_POST['mb_zip']) : '';
$mb_zip1 = substr($mb_zip_full, 0, 3);
$mb_zip2 = substr($mb_zip_full, 3);

$mb_addr1 = isset($_POST['mb_addr1']) ? sql_escape_string(trim($_POST['mb_addr1'])) : '';
$mb_addr2 = isset($_POST['mb_addr2']) ? sql_escape_string(trim($_POST['mb_addr2'])) : '';
$mb_addr3 = isset($_POST['mb_addr3']) ? sql_escape_string(trim($_POST['mb_addr3'])) : '';
$mb_addr_jibeon = isset($_POST['mb_addr_jibeon']) ? sql_escape_string(trim($_POST['mb_addr_jibeon'])) : '';
$dt_status = isset($_POST['dt_status']) ? (int)$_POST['dt_status'] : 0;

// 총판은 mb_level 8, dmk_mb_type 1, dmk_admin_type main 고정
define('DMK_DISTRIBUTOR_MB_LEVEL', 8);
define('DMK_DISTRIBUTOR_MB_TYPE', 1);
define('DMK_DISTRIBUTOR_ADMIN_TYPE', 'main');

if (!$mb_id) {
    alert('회원 아이디가 누락되었습니다.');
    exit;
}

// 아이디 유효성 검증
$id_validation = dmk_validate_member_id($mb_id);
if ($id_validation !== true) {
    alert($id_validation);
    exit;
}

if (!$mb_name) {
    alert('이름이 누락되었습니다.');
    exit;
}

if ($w == '') { // 등록
    // ID 중복 확인
    $row = sql_fetch(" SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '$mb_id' ");
    if ($row && $row['mb_id']) {
        alert('이미 존재하는 회원 아이디입니다.');
        exit;
    }

    if (!$mb_password) {
        alert('비밀번호를 입력해 주십시오.');
    }
    
    // 비밀번호 유효성 검증
    $password_validation = dmk_validate_password($mb_id, $mb_password, $mb_password_re);
    if ($password_validation !== true) {
        alert($password_validation);
        exit;
    }

    // bcrypt 비밀번호 암호화 함수 사용
    $mb_password_hash = sql_password($mb_password);

    // g5_member 테이블에 총판 등록
    $sql = " INSERT INTO {$g5['member_table']} SET
                mb_id = '$mb_id',
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
                mb_level = " . DMK_DISTRIBUTOR_MB_LEVEL . ",
                dmk_mb_type = " . DMK_DISTRIBUTOR_MB_TYPE . ",
                dmk_dt_id = '$mb_id',
                dmk_admin_type = '" . DMK_DISTRIBUTOR_ADMIN_TYPE . "' ";
    $result1 = sql_query($sql);
    
    if (!$result1) {
        alert('g5_member 테이블 등록 실패: DB 오류');
        exit;
    }

    // dmk_distributor 테이블에 총판 정보 등록
    $sql = " INSERT INTO dmk_distributor SET
                dt_id = '$mb_id',
                dt_status = $dt_status,
                dt_created_by = '{$member['mb_id']}',
                dt_admin_type = '" . DMK_DISTRIBUTOR_ADMIN_TYPE . "' ";
    $result2 = sql_query($sql);
    
    if (!$result2) {
        alert('dmk_distributor 테이블 등록 실패: DB 오류');
        exit;
    }

    // 관리자 액션 로그
    dmk_log_admin_action('insert', '총판 등록', '총판ID: '.$mb_id, json_encode($_POST), null, 'distributor_form', 'g5_member,dmk_distributor');

    goto_url('./distributor_list.php');
    exit;

} else if ($w == 'u') { // 수정
    // 수정 전 기존 데이터 조회 (로그용)
    $old_member_sql = " SELECT mb_id, mb_name, mb_nick, mb_hp, mb_tel, mb_email, 
                               mb_zip1, mb_zip2, mb_addr1, mb_addr2, mb_addr3, mb_addr_jibeon
                        FROM {$g5['member_table']} WHERE mb_id = '$mb_id' ";
    $old_member_row = sql_fetch($old_member_sql);
    
    $old_distributor_sql = " SELECT dt_id, dt_status FROM dmk_distributor WHERE dt_id = '$mb_id' ";
    $old_distributor_row = sql_fetch($old_distributor_sql);
    
    // 기존 데이터 병합
    $old_data = array_merge($old_member_row ?: [], $old_distributor_row ?: []);
    
    if ($mb_password) {
        // 비밀번호 유효성 검증
        $password_validation = dmk_validate_password($mb_id, $mb_password, $mb_password_re);
        if ($password_validation !== true) {
            alert($password_validation);
            exit;
        }
        
        // bcrypt 비밀번호 암호화 함수 사용
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
              WHERE mb_id = '$mb_id' ";
    sql_query($sql);

    // dmk_distributor 테이블 상태 업데이트
    $sql = " UPDATE dmk_distributor SET
                dt_status = $dt_status
              WHERE dt_id = '$mb_id' ";
    $result2 = sql_query($sql);
    
    if (!$result2) {
        alert('dmk_distributor 테이블 업데이트 실패: DB 오류');
        exit;
    }

    // 새로운 데이터 구성 (비밀번호 제외)
    $new_data = [
        'mb_id' => $mb_id,
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
        'dt_status' => $dt_status
    ];

    // 관리자 액션 로그 (향상된 변경 내용 추적)
    dmk_log_update_action('총판 정보 수정', '총판ID: '.$mb_id, $new_data, $old_data, 'distributor_form', 'g5_member,dmk_distributor');

    goto_url('./distributor_form.php?w=u&mb_id='.$mb_id);
    exit;
} else {
    alert('잘못된 접근입니다.');
    exit;
}

include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>