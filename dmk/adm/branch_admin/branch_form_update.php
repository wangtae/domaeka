<?php
$sub_menu = "190300";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('branch_form')) {
    alert('접근 권한이 없습니다.');
}

auth_check_menu($auth, $sub_menu, 'w');

check_token();

$br_id = isset($_POST['br_id']) ? clean_xss_tags($_POST['br_id']) : '';
$w = isset($_POST['w']) ? clean_xss_tags($_POST['w']) : '';

$br_shortcut_code = isset($_POST['br_shortcut_code']) ? clean_xss_tags(trim($_POST['br_shortcut_code'])) : '';
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$br_name = isset($_POST['br_name']) ? clean_xss_tags($_POST['br_name']) : '';
$br_ceo_name = isset($_POST['br_ceo_name']) ? clean_xss_tags($_POST['br_ceo_name']) : '';
$br_phone = isset($_POST['br_phone']) ? clean_xss_tags($_POST['br_phone']) : '';
$br_address = isset($_POST['br_address']) ? clean_xss_tags($_POST['br_address']) : '';
$br_mb_id = isset($_POST['br_mb_id']) ? clean_xss_tags($_POST['br_mb_id']) : '';
$br_status = isset($_POST['br_status']) ? (int)$_POST['br_status'] : 0;

// br_shortcut_code 유효성 검사
if ($br_shortcut_code && !preg_match('/^[a-zA-Z0-9_-]+$/', $br_shortcut_code)) {
    alert('단축 URL 코드는 영문, 숫자, 하이픈(-), 언더스코어(_)만 사용할 수 있습니다.');
}

// 중복 체크 (등록 및 수정 시)
if ($br_shortcut_code) {
    $sql_check_shortcut = "SELECT COUNT(*) as cnt FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' ";
    if ($w == 'u') {
        // 수정 시에는 현재 지점의 코드는 제외하고 체크
        $sql_check_shortcut .= " AND br_id != '" . sql_escape_string($br_id) . "' ";
    }
    $row_check_shortcut = sql_fetch($sql_check_shortcut);

    if ($row_check_shortcut['cnt'] > 0) {
        alert('이미 사용 중인 단축 URL 코드입니다. 다른 코드를 입력해 주세요.');
    }
}

if ($w == '') {
    // 등록
    $sql = " INSERT INTO dmk_branch
                SET br_id = '" . sql_escape_string($br_id) . "',
                    ag_id = '" . sql_escape_string($ag_id) . "',
                    br_name = '" . sql_escape_string($br_name) . "',
                    br_ceo_name = '" . sql_escape_string($br_ceo_name) . "',
                    br_phone = '" . sql_escape_string($br_phone) . "',
                    br_address = '" . sql_escape_string($br_address) . "',
                    br_mb_id = '" . sql_escape_string($br_mb_id) . "',
                    br_status = '" . sql_escape_string($br_status) . "',
                    br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "',
                    br_datetime = NOW() ";
    sql_query($sql);

    $msg = '지점 정보가 등록되었습니다.';

} else if ($w == 'u') {
    // 수정
    $sql = " UPDATE dmk_branch
                SET ag_id = '" . sql_escape_string($ag_id) . "',
                    br_name = '" . sql_escape_string($br_name) . "',
                    br_ceo_name = '" . sql_escape_string($br_ceo_name) . "',
                    br_phone = '" . sql_escape_string($br_phone) . "',
                    br_address = '" . sql_escape_string($br_address) . "',
                    br_mb_id = '" . sql_escape_string($br_mb_id) . "',
                    br_status = '" . sql_escape_string($br_status) . "',
                    br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "'
              WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    sql_query($sql);

    $msg = '지점 정보가 수정되었습니다.';
}

alert($msg, './branch_list.php');

?> 