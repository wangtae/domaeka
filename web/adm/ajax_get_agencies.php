<?php
$sub_menu = "200100";
require_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 확인
$dt_id = isset($_POST['dt_id']) ? trim($_POST['dt_id']) : '';

$response = array(
    'success' => false,
    'data' => array(),
    'message' => ''
);

try {
    // 권한 확인
    $auth = dmk_get_admin_auth();
    if (!$auth) {
        throw new Exception('권한이 없습니다.');
    }
    
    if (empty($dt_id)) {
        throw new Exception('총판 ID가 없습니다.');
    }
    
    // 해당 총판의 대리점 목록 조회
    $sql = " SELECT mb_id, mb_name 
             FROM {$g5['member_table']} 
             WHERE dmk_mb_type = 2 AND dmk_admin_type = 'main' AND dmk_dt_id = '" . sql_escape_string($dt_id) . "' 
             ORDER BY mb_name ";
    $result = sql_query($sql);
    
    $data = array();
    while ($row = sql_fetch_array($result)) {
        $data[] = array(
            'ag_id' => $row['mb_id'],
            'ag_name' => $row['mb_name']
        );
    }
    
    $response['success'] = true;
    $response['data'] = $data;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
