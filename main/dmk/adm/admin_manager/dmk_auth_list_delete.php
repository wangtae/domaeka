<?php
$sub_menu = "190700";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

check_admin_token();

$dmk_auth = dmk_get_admin_auth();

// 도매까 관련 메뉴만 허용
$dmk_menu_codes = array('190000', '190100', '190200', '190300', '190400', '190500', '190600', '190700');

if (!isset($_POST['chk']) || !is_array($_POST['chk'])) {
    alert('삭제할 항목을 선택하세요.');
}

$chk = $_POST['chk'];
$au_menu = isset($_POST['au_menu']) ? $_POST['au_menu'] : array();
$mb_id = isset($_POST['mb_id']) ? $_POST['mb_id'] : array();

$delete_count = 0;

for ($i=0; $i<count($chk); $i++) {
    $k = (int)$chk[$i];
    
    if (!isset($au_menu[$k]) || !isset($mb_id[$k])) {
        continue;
    }
    
    $del_au_menu = clean_xss_tags($au_menu[$k]);
    $del_mb_id = clean_xss_tags($mb_id[$k]);
    
    // 도매까 메뉴만 삭제 허용
    if (!in_array($del_au_menu, $dmk_menu_codes)) {
        continue;
    }
    
    // 해당 관리자의 레벨 확인
    $sql = " SELECT mb_level FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($del_mb_id)."' ";
    $member = sql_fetch($sql);
    
    if (!$member) {
        continue;
    }
    
    // 삭제 권한 체크
    $can_delete = false;
    if ($dmk_auth['is_super']) {
        $can_delete = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $member['mb_level'] <= DMK_MB_LEVEL_DISTRIBUTOR) {
        $can_delete = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $member['mb_level'] <= DMK_MB_LEVEL_AGENCY) {
        $can_delete = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $member['mb_level'] == DMK_MB_LEVEL_BRANCH) {
        $can_delete = true;
    }
    
    if (!$can_delete) {
        continue;
    }
    
    // 권한 삭제
    $sql = " DELETE FROM {$g5['auth_table']} 
             WHERE mb_id = '".sql_escape_string($del_mb_id)."' 
             AND au_menu = '".sql_escape_string($del_au_menu)."' ";
    sql_query($sql);
    
    $delete_count++;
}

if ($delete_count > 0) {
    alert($delete_count . '건의 권한이 삭제되었습니다.', './dmk_auth_list.php');
} else {
    alert('삭제된 항목이 없습니다.', './dmk_auth_list.php');
}
?> 