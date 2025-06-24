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
$ag_mb_id = isset($_POST['ag_mb_id']) ? clean_xss_tags($_POST['ag_mb_id']) : '';
$ag_status = isset($_POST['ag_status']) ? (int)$_POST['ag_status'] : 1;

if (!$ag_id) {
    alert('대리점 ID를 입력하세요.');
}

if (!$ag_name) {
    alert('대리점명을 입력하세요.');
}

if (!$ag_mb_id) {
    alert('대리점 관리자를 선택하세요.');
}

// 관리자 회원 존재 여부 확인
$member_sql = " SELECT mb_id, dmk_mb_type, dmk_ag_id FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($ag_mb_id) . "' ";
$member = sql_fetch($member_sql);

if (!$member) {
    alert('선택한 관리자 회원이 존재하지 않습니다.');
}

// 이미 다른 대리점의 관리자인지 확인 (수정시 자신 제외)
if ($member['dmk_mb_type'] == 2 && $member['dmk_ag_id'] && ($w != 'u' || $member['dmk_ag_id'] != $ag_id)) {
    alert('선택한 회원은 이미 다른 대리점의 관리자입니다.');
}

if ($w == 'u') {
    // 수정
    $sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if (!$row) {
        alert('존재하지 않는 대리점입니다.');
    }
    
    // 기존 관리자 정보 조회
    $old_agency_sql = " SELECT ag_mb_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $old_agency = sql_fetch($old_agency_sql);
    
    // 대리점 정보 업데이트
    $sql = " UPDATE dmk_agency SET 
                ag_name = '" . sql_escape_string($ag_name) . "',
                ag_ceo_name = '" . sql_escape_string($ag_ceo_name) . "',
                ag_phone = '" . sql_escape_string($ag_phone) . "',
                ag_address = '" . sql_escape_string($ag_address) . "',
                ag_mb_id = '" . sql_escape_string($ag_mb_id) . "',
                ag_status = $ag_status
             WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    sql_query($sql);
    
    // 기존 관리자의 권한 해제 (관리자가 변경된 경우)
    if ($old_agency['ag_mb_id'] != $ag_mb_id && $old_agency['ag_mb_id']) {
        $sql = " UPDATE {$g5['member_table']} SET 
                    dmk_mb_type = 0,
                    dmk_ag_id = NULL
                 WHERE mb_id = '" . sql_escape_string($old_agency['ag_mb_id']) . "' ";
        sql_query($sql);
    }
    
    // 새 관리자 권한 설정
    $sql = " UPDATE {$g5['member_table']} SET 
                dmk_mb_type = 2,
                dmk_ag_id = '" . sql_escape_string($ag_id) . "'
             WHERE mb_id = '" . sql_escape_string($ag_mb_id) . "' ";
    sql_query($sql);
    
    $msg = '대리점이 수정되었습니다.';
    
} else {
    // 등록
    
    // 대리점 ID 중복 확인
    $sql = " SELECT ag_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 대리점 ID입니다.');
    }
    
    // 대리점 등록
    $sql = " INSERT INTO dmk_agency SET 
                ag_id = '" . sql_escape_string($ag_id) . "',
                ag_name = '" . sql_escape_string($ag_name) . "',
                ag_ceo_name = '" . sql_escape_string($ag_ceo_name) . "',
                ag_phone = '" . sql_escape_string($ag_phone) . "',
                ag_address = '" . sql_escape_string($ag_address) . "',
                ag_mb_id = '" . sql_escape_string($ag_mb_id) . "',
                ag_datetime = NOW(),
                ag_status = $ag_status ";
    sql_query($sql);
    
    // 관리자 권한 설정
    $sql = " UPDATE {$g5['member_table']} SET 
                dmk_mb_type = 2,
                dmk_ag_id = '" . sql_escape_string($ag_id) . "'
             WHERE mb_id = '" . sql_escape_string($ag_mb_id) . "' ";
    sql_query($sql);
    
    $msg = '대리점이 등록되었습니다.';
}

goto_url('./agency_list.php?'.$qstr, false, $msg);
?> 