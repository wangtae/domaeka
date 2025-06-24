<?php
$sub_menu = "190700";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

check_admin_token();

$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$au_menu = isset($_POST['au_menu']) ? clean_xss_tags($_POST['au_menu']) : '';
$au_auth = isset($_POST['au_auth']) ? clean_xss_tags($_POST['au_auth']) : '';

$dmk_auth = dmk_get_admin_auth();

// 도매까 관련 메뉴만 허용
$dmk_menu_codes = array('190000', '190100', '190200', '190300', '190400', '190500', '190600', '190700');

if (!$mb_id) {
    alert('관리자아이디를 입력하세요.');
}

if (!$au_menu) {
    alert('접근가능메뉴를 선택하세요.');
}

if (!in_array($au_menu, $dmk_menu_codes)) {
    alert('도매까 관련 메뉴만 설정 가능합니다.');
}

if (!$au_auth) {
    alert('권한을 입력하세요.');
}

// 권한 문자열 검증
if (!preg_match('/^[rwd]+$/', $au_auth)) {
    alert('권한은 r, w, d 조합으로만 입력 가능합니다.');
}

// 관리자 존재 여부 및 레벨 확인
$sql = " SELECT mb_level FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
$member = sql_fetch($sql);

if (!$member) {
    alert('존재하지 않는 관리자아이디입니다.');
}

if ($member['mb_level'] < 4) {
    alert('관리자 레벨(4 이상)의 아이디만 권한 설정이 가능합니다.');
}

// 권한 설정 권한 체크
$can_set_auth = false;
if ($dmk_auth['is_super']) {
    $can_set_auth = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $member['mb_level'] <= DMK_MB_LEVEL_DISTRIBUTOR) {
    $can_set_auth = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $member['mb_level'] <= DMK_MB_LEVEL_AGENCY) {
    $can_set_auth = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $member['mb_level'] == DMK_MB_LEVEL_BRANCH) {
    $can_set_auth = true;
}

if (!$can_set_auth) {
    alert('해당 관리자에게 권한을 설정할 수 없습니다.');
}

// 기존 권한 확인
$sql = " SELECT COUNT(*) as cnt FROM {$g5['auth_table']} 
         WHERE mb_id = '".sql_escape_string($mb_id)."' AND au_menu = '".sql_escape_string($au_menu)."' ";
$row = sql_fetch($sql);

if ($row['cnt']) {
    // 기존 권한 업데이트
    $sql = " UPDATE {$g5['auth_table']} SET 
                au_auth = '".sql_escape_string($au_auth)."' 
             WHERE mb_id = '".sql_escape_string($mb_id)."' AND au_menu = '".sql_escape_string($au_menu)."' ";
    sql_query($sql);
    
    alert('권한이 수정되었습니다.', './dmk_auth_list.php');
} else {
    // 새 권한 추가
    $sql = " INSERT INTO {$g5['auth_table']} SET 
                mb_id = '".sql_escape_string($mb_id)."',
                au_menu = '".sql_escape_string($au_menu)."',
                au_auth = '".sql_escape_string($au_auth)."' ";
    sql_query($sql);
    
    alert('권한이 추가되었습니다.', './dmk_auth_list.php');
}
?> 