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
    
    // 해당 관리자의 정보 확인 (sub 관리자만 권한 삭제 가능)
    $sql = " SELECT mb_level, dmk_mb_type, dmk_admin_type, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($del_mb_id)."' ";
    $member = sql_fetch($sql);
    
    if (!$member) {
        continue;
    }
    
    // SUB 관리자만 권한 삭제 가능
    if ($member['dmk_admin_type'] !== 'sub') {
        continue;
    }
    
    // 삭제 권한 체크 (MAIN 관리자만 SUB 관리자 권한 삭제 가능)
    $can_delete = false;
    if ($dmk_auth['is_super']) {
        $can_delete = true;
    } elseif ($dmk_auth['admin_type'] === 'main') {
        // 같은 계층 또는 하위 계층의 sub 관리자만 권한 삭제 가능
        if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            $can_delete = in_array($member['dmk_mb_type'], [1, 2, 3]); // 총판, 대리점, 지점
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            $can_delete = in_array($member['dmk_mb_type'], [2, 3]) && 
                         ($member['dmk_ag_id'] === $dmk_auth['ag_id'] || $member['dmk_mb_type'] == 2);
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
            $can_delete = $member['dmk_mb_type'] == 3 && $member['dmk_br_id'] === $dmk_auth['br_id'];
        }
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