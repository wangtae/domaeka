<?php
$sub_menu = "190700";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

check_admin_token();

$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$au_menu = isset($_POST['au_menu']) ? clean_xss_tags($_POST['au_menu']) : '';
$au_auth_raw = isset($_POST['au_auth']) ? $_POST['au_auth'] : ''; // 원본 값

// 권한 문자열 정제 및 SET 형식으로 변환
if ($au_auth_raw && preg_match('/^[rwd]+$/', $au_auth_raw)) {
    // SET 타입을 위해 각 문자를 쉼표로 구분
    $auth_chars = str_split($au_auth_raw);
    $auth_chars = array_unique($auth_chars); // 중복 제거
    $au_auth = implode(',', $auth_chars);
} else {
    $au_auth = ''; // 비어있거나 잘못된 경우 빈 문자열
}

// 디버깅: POST 데이터 확인
error_log("DMK_AUTH_UPDATE: mb_id = " . $mb_id);
error_log("DMK_AUTH_UPDATE: au_menu = " . $au_menu);
error_log("DMK_AUTH_UPDATE: au_auth_raw = " . $au_auth_raw);
error_log("DMK_AUTH_UPDATE: au_auth = " . $au_auth);
error_log("DMK_AUTH_UPDATE: _POST data: " . print_r($_POST, true));

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

// 권한 문자열 검증 (SET 형식에서 허용되는 값들)
$valid_auth_values = ['', 'r', 'w', 'd', 'r,w', 'r,d', 'w,d', 'r,w,d'];
if (!in_array($au_auth, $valid_auth_values)) {
    alert('권한은 r, w, d 조합으로만 입력 가능합니다.');
}

// 관리자 존재 여부 및 타입 확인 (sub 관리자만 권한 설정 가능)
$sql = " SELECT mb_level, dmk_mb_type, dmk_admin_type, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
$member = sql_fetch($sql);

if (!$member) {
    alert('존재하지 않는 관리자아이디입니다.');
}

if ($member['mb_level'] < 4) {
    alert('관리자 레벨(4 이상)의 아이디만 권한 설정이 가능합니다.');
}

// SUB 관리자만 권한 설정 가능
if ($member['dmk_admin_type'] !== 'sub') {
    alert('SUB 관리자만 개별 권한을 설정할 수 있습니다. MAIN 관리자는 해당 계층의 모든 권한을 자동으로 가집니다.');
}

// 권한 설정 권한 체크 (MAIN 관리자만 SUB 관리자 권한 설정 가능)
$can_set_auth = false;
if ($dmk_auth['is_super']) {
    $can_set_auth = true;
} elseif ($dmk_auth['admin_type'] === 'main') {
    // 같은 계층 또는 하위 계층의 sub 관리자만 권한 설정 가능
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        $can_set_auth = in_array($member['dmk_mb_type'], [1, 2, 3]); // 총판, 대리점, 지점
    } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        $can_set_auth = in_array($member['dmk_mb_type'], [2, 3]) && 
                       ($member['dmk_ag_id'] === $dmk_auth['ag_id'] || $member['dmk_mb_type'] == 2);
    } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        $can_set_auth = $member['dmk_mb_type'] == 3 && $member['dmk_br_id'] === $dmk_auth['br_id'];
    }
}

if (!$can_set_auth) {
    alert('해당 관리자에게 권한을 설정할 수 없습니다. MAIN 관리자만 SUB 관리자의 권한을 설정할 수 있습니다.');
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
    error_log("DMK_AUTH_UPDATE: UPDATE SQL = " . $sql);
    $result = sql_query($sql);
    error_log("DMK_AUTH_UPDATE: UPDATE result = " . ($result ? 'success' : 'failed'));
    
    alert('권한이 수정되었습니다.', './dmk_auth_list.php');
} else {
    // 새 권한 추가
    $sql = " INSERT INTO {$g5['auth_table']} SET 
                mb_id = '".sql_escape_string($mb_id)."',
                au_menu = '".sql_escape_string($au_menu)."',
                au_auth = '".sql_escape_string($au_auth)."' ";
    error_log("DMK_AUTH_UPDATE: INSERT SQL = " . $sql);
    $result = sql_query($sql);
    error_log("DMK_AUTH_UPDATE: INSERT result = " . ($result ? 'success' : 'failed'));
    
    alert('권한이 추가되었습니다.', './dmk_auth_list.php');
}
?>