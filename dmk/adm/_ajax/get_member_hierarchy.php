<?php
include_once('../../../_common.php');

header('Content-Type: application/json');

if (!isset($_GET['mb_id']) || empty($_GET['mb_id'])) {
    echo json_encode(['success' => false, 'message' => 'mb_id가 필요합니다.']);
    exit;
}

$mb_id = clean_xss_tags($_GET['mb_id']);

// 회원 정보 조회
$sql = "SELECT dmk_dt_id, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'";
$member = sql_fetch($sql);

if ($member) {
    echo json_encode([
        'success' => true,
        'dmk_dt_id' => $member['dmk_dt_id'] ?? '',
        'dmk_ag_id' => $member['dmk_ag_id'] ?? '',
        'dmk_br_id' => $member['dmk_br_id'] ?? ''
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => '회원 정보를 찾을 수 없습니다.'
    ]);
}
?>
