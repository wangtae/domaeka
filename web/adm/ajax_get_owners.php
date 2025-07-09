<?php
$sub_menu = "200100";
require_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 확인
$owner_type = isset($_POST['owner_type']) ? (int)$_POST['owner_type'] : 0;

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

    switch ($owner_type) {
        case 1: // 총판
            $sql = " SELECT mb_id as id, mb_name as name 
                     FROM {$g5['member_table']} 
                     WHERE dmk_mb_type = 1 AND dmk_admin_type = 'main' 
                     ORDER BY mb_name ";
            break;
            
        case 2: // 대리점
            $sql = " SELECT mb_id as id, mb_name as name 
                     FROM {$g5['member_table']} 
                     WHERE dmk_mb_type = 2 AND dmk_admin_type = 'main' 
                     ORDER BY mb_name ";
            break;
            
        case 3: // 지점
            $sql = " SELECT mb_id as id, mb_name as name 
                     FROM {$g5['member_table']} 
                     WHERE dmk_mb_type = 3 AND dmk_admin_type = 'main' 
                     ORDER BY mb_name ";
            break;
            
        default:
            throw new Exception('올바르지 않은 소속 구분입니다.');
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
