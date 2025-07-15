<?php
/**
 * 총판별 대리점 목록 조회 (AJAX)
 */

include_once('./_common.php');

header('Content-Type: application/json');

// 권한 체크
$user_info = dmk_get_admin_auth($member['mb_id']);
if (!$user_info || ($user_info['type'] != 'super' && $user_info['type'] != 'distributor')) {
    die(json_encode([]));
}

$dt_id = $_GET['dt_id'];
if (!$dt_id) {
    die(json_encode([]));
}

// 총판 관리자인 경우 자신의 총판만 조회 가능
if ($user_info['type'] == 'distributor' && $dt_id != $user_info['key']) {
    die(json_encode([]));
}

$agencies = [];
$sql = " SELECT ag_id, ag_name FROM g5_dmk_agencies WHERE dt_id = '$dt_id' ORDER BY ag_name ";
$result = sql_query($sql);
while($row = sql_fetch_array($result)) {
    $agencies[] = array(
        'ag_id' => $row['ag_id'],
        'ag_name' => $row['ag_name']
    );
}

echo json_encode($agencies);
?>