<?php
include_once('../../../_common.php');
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

// 관리자 권한 확인
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth || (!$dmk_auth['is_super'] && !$dmk_auth['is_admin'])) {
    echo json_encode(['success' => false, 'message' => '접근 권한이 없습니다.']);
    exit;
}

if (!isset($_GET['mb_id']) || empty($_GET['mb_id'])) {
    echo json_encode(['success' => false, 'message' => 'mb_id가 필요합니다.']);
    exit;
}

$mb_id = clean_xss_tags($_GET['mb_id']);

// 회원 정보 조회
$sql = "SELECT dmk_dt_id, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'";
$member = sql_fetch($sql);

if ($member) {
    // 계층별 접근 제어 - 하위 계층은 상위 계층 정보 숨김
    $response = ['success' => true];
    
    if ($dmk_auth['is_super']) {
        // 본사 관리자는 모든 정보 접근 가능
        $response['dmk_dt_id'] = $member['dmk_dt_id'] ?? '';
        $response['dmk_ag_id'] = $member['dmk_ag_id'] ?? '';
        $response['dmk_br_id'] = $member['dmk_br_id'] ?? '';
    } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 대리점, 지점 정보만 접근 가능
        $response['dmk_dt_id'] = $member['dmk_dt_id'] ?? '';
        $response['dmk_ag_id'] = $member['dmk_ag_id'] ?? '';
        $response['dmk_br_id'] = $member['dmk_br_id'] ?? '';
    } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 지점 정보만 접근 가능
        $response['dmk_dt_id'] = '';  // 상위 계층 정보 숨김
        $response['dmk_ag_id'] = $member['dmk_ag_id'] ?? '';
        $response['dmk_br_id'] = $member['dmk_br_id'] ?? '';
    } else {
        // 지점 관리자는 자신의 지점 정보만 접근 가능
        $response['dmk_dt_id'] = '';  // 상위 계층 정보 숨김
        $response['dmk_ag_id'] = '';  // 상위 계층 정보 숨김
        $response['dmk_br_id'] = $member['dmk_br_id'] ?? '';
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false, 
        'message' => '회원 정보를 찾을 수 없습니다.'
    ]);
}
?>
