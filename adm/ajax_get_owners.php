<?php
$sub_menu = "200100";
require_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// 권한 체크
auth_check_menu($auth, $sub_menu, 'r');

$owner_type = isset($_POST['owner_type']) ? (int)$_POST['owner_type'] : 0;

$response = array(
    'success' => false,
    'data' => array(),
    'message' => ''
);

try {
    switch ($owner_type) {
        case 1: // 총판
            $sql = "SELECT dt_id as id, dt_name as name FROM dmk_distributor ORDER BY dt_name";
            break;
            
        case 2: // 대리점
            $sql = "SELECT ag_id as id, ag_name as name FROM dmk_agency ORDER BY ag_name";
            break;
            
        case 3: // 지점
            $sql = "SELECT br_id as id, br_name as name FROM dmk_branch ORDER BY br_name";
            break;
            
        default:
            throw new Exception('잘못된 소속 구분입니다.');
    }
    
    $result = sql_query($sql);
    $data = array();
    
    while ($row = sql_fetch_array($result)) {
        $data[] = array(
            'id' => $row['id'],
            'name' => $row['name']
        );
    }
    
    $response['success'] = true;
    $response['data'] = $data;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?> 