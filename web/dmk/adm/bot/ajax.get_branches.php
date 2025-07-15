<?php
/**
 * 대리점별 지점 목록 조회 (AJAX)
 */

include_once('./_common.php');

header('Content-Type: application/json');

// 권한 체크
$user_info = dmk_get_admin_auth($member['mb_id']);
if (!$user_info || ($user_info['type'] != 'super' && $user_info['type'] != 'distributor')) {
    die(json_encode([]));
}

$ag_id = $_GET['ag_id'];
if (!$ag_id) {
    die(json_encode([]));
}

// 총판 관리자인 경우 자신의 대리점만 조회 가능
if ($user_info['type'] == 'distributor') {
    $sql = " SELECT COUNT(*) as cnt FROM g5_dmk_agencies WHERE ag_id = '$ag_id' AND dt_id = '{$user_info['key']}' ";
    $chk = sql_fetch($sql);
    if (!$chk['cnt']) {
        die(json_encode([]));
    }
}

$branches = [];
$sql = " SELECT br_id, br_name FROM g5_dmk_branches WHERE ag_id = '$ag_id' ORDER BY br_name ";
$result = sql_query($sql);
while($row = sql_fetch_array($result)) {
    $branches[] = array(
        'br_id' => $row['br_id'],
        'br_name' => $row['br_name']
    );
}

echo json_encode($branches);
?>