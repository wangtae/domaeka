<?php
include_once './_common.php';

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('distributor_form')) {
    alert('접근 권한이 없습니다.');
}

check_token();

$w = isset($_POST['w']) ? sql_escape_string(trim($_POST['w'])) : '';
$mb_id = isset($_POST['mb_id']) ? sql_escape_string(trim($_POST['mb_id'])) : '';
$mb_password = isset($_POST['mb_password']) ? trim($_POST['mb_password']) : '';
$mb_password_re = isset($_POST['mb_password_re']) ? trim($_POST['mb_password_re']) : '';
$mb_name = isset($_POST['mb_name']) ? sql_escape_string(trim($_POST['mb_name'])) : '';
$mb_hp = isset($_POST['mb_hp']) ? sql_escape_string(trim($_POST['mb_hp'])) : '';
$mb_email = isset($_POST['mb_email']) ? sql_escape_string(trim($_POST['mb_email'])) : '';
$mb_zip = isset($_POST['mb_zip']) ? sql_escape_string(trim($_POST['mb_zip'])) : '';
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
}
if (!$mb_name) {
    alert('이름이 누락되었습니다.');
}

if ($w == '') { // 등록
    // ID 중복 확인
    $row = sql_fetch(" SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '$mb_id' ");
    if ($row['mb_id']) {
        alert('이미 존재하는 회원 아이디입니다.');
    }

    if (!$mb_password) {
        alert('비밀번호를 입력해 주십시오.');
    }
    if ($mb_password != $mb_password_re) {
        alert('비밀번호가 일치하지 않습니다.');
    }
    if (strlen($mb_password) < 3) {
        alert('비밀번호를 3글자 이상 입력하십시오.');
    }

    $mb_password_hash = get_encrypt_password($mb_password);

    // g5_member 테이블에 총판 등록
    $sql = " INSERT INTO {$g5['member_table']} SET
                mb_id = '$mb_id',
                mb_password = '$mb_password_hash',
                mb_name = '$mb_name',
                mb_hp = '$mb_hp',
                mb_email = '$mb_email',
                mb_zip = '$mb_zip',
                mb_addr1 = '$mb_addr1',
                mb_addr2 = '$mb_addr2',
                mb_addr3 = '$mb_addr3',
                mb_addr_jibeon = '$mb_addr_jibeon',
                mb_datetime = now(),
                mb_ip = '$REMOTE_ADDR',
                mb_level = " . DMK_DISTRIBUTOR_MB_LEVEL . ",
                dmk_mb_type = " . DMK_DISTRIBUTOR_MB_TYPE . ",
                dmk_admin_type = '" . DMK_DISTRIBUTOR_ADMIN_TYPE . "';";
    sql_query($sql);

    // dmk_distributor 테이블에 총판 정보 등록
    $sql = " INSERT INTO dmk_distributor SET
                dt_id = '$mb_id',
                dt_datetime = now(),
                dt_status = '$dt_status',
                dt_created_by = '{$member['mb_id']}',
                dt_admin_type = '" . DMK_DISTRIBUTOR_ADMIN_TYPE . "';";
    sql_query($sql);

    // 관리자 액션 로그
    dmk_log_admin_action('insert', '총판 등록', '총판ID: '.$mb_id, json_encode($_POST));

    goto_url('./distributor_list.php', '총판이 성공적으로 등록되었습니다.');

} else if ($w == 'u') { // 수정
    if ($mb_password) {
        if ($mb_password != $mb_password_re) {
            alert('비밀번호가 일치하지 않습니다.');
        }
        if (strlen($mb_password) < 3) {
            alert('비밀번호를 3글자 이상 입력하십시오.');
        }
        $mb_password_hash = ", mb_password = '" . get_encrypt_password($mb_password) . "'";
    } else {
        $mb_password_hash = '';
    }

    // g5_member 테이블 정보 업데이트
    $sql = " UPDATE {$g5['member_table']} SET
                mb_name = '$mb_name',
                mb_hp = '$mb_hp',
                mb_email = '$mb_email',
                mb_zip = '$mb_zip',
                mb_addr1 = '$mb_addr1',
                mb_addr2 = '$mb_addr2',
                mb_addr3 = '$mb_addr3',
                mb_addr_jibeon = '$mb_addr_jibeon'
                $mb_password_hash
              WHERE mb_id = '$mb_id' ";
    sql_query($sql);

    // dmk_distributor 테이블 상태 업데이트
    $sql = " UPDATE dmk_distributor SET
                dt_status = '$dt_status'
              WHERE dt_id = '$mb_id' ";
    sql_query($sql);

    // 관리자 액션 로그
    dmk_log_admin_action('edit', '총판 정보 수정', '총판ID: '.$mb_id, json_encode($_POST));

    goto_url('./distributor_form.php?w=u&mb_id='.$mb_id, '총판 정보가 성공적으로 수정되었습니다.');
} else {
    alert('잘못된 접근입니다.');
}

include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 