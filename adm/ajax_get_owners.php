<?php
$sub_menu = "200100";
require_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// 권한 체크
auth_check_menu($auth, $sub_menu, 'r');

$owner_type = isset($_REQUEST['owner_type']) ? (int)$_REQUEST['owner_type'] : 0;
$parent_id = isset($_REQUEST['parent_id']) ? clean_xss_tags($_REQUEST['parent_id']) : '';

// 디버깅: 요청 파라미터 로그
error_log("AJAX Request - owner_type: $owner_type, parent_id: $parent_id");

$response = array(
    'success' => false,
    'data' => array(),
    'message' => '',
    'debug' => array(
        'owner_type' => $owner_type,
        'parent_id' => $parent_id,
        'request_method' => $_SERVER['REQUEST_METHOD']
    )
);

try {
    switch ($owner_type) {
        case 1: // 총판
            $sql = "SELECT d.dt_id as id, m.mb_nick as name 
                    FROM dmk_distributor d 
                    JOIN {$g5['member_table']} m ON d.dt_id = m.mb_id 
                    WHERE d.dt_status = 1 
                    ORDER BY m.mb_nick";
            break;
            
        case 2: // 대리점
            $sql = "SELECT a.ag_id as id, m.mb_nick as name 
                    FROM dmk_agency a 
                    JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id 
                    WHERE a.ag_status = 1 ";
            if ($parent_id) {
                $sql .= " AND a.dt_id = '" . sql_escape_string($parent_id) . "' ";
            }
            $sql .= " ORDER BY m.mb_nick";
            break;
            
        case 3: // 지점
            $sql = "SELECT b.br_id as id, m.mb_nick as name 
                    FROM dmk_branch b 
                    JOIN {$g5['member_table']} m ON b.br_id = m.mb_id 
                    WHERE b.br_status = 1 ";
            if ($parent_id) {
                $sql .= " AND b.ag_id = '" . sql_escape_string($parent_id) . "' ";
            }
            $sql .= " ORDER BY m.mb_nick";
            break;
            
        default:
            throw new Exception('잘못된 소속 구분입니다.');
    }
    
    // 디버깅: SQL 쿼리 로그
    error_log("SQL Query: $sql");
    $response['debug']['sql'] = $sql;
    
    $result = sql_query($sql);
    $data = array();
    
    if (!$result) {
        throw new Exception('데이터베이스 쿼리 실행 실패: ' . sql_error());
    }
    
    while ($row = sql_fetch_array($result)) {
        $data[] = array(
            'id' => $row['id'],
            'name' => $row['name']
        );
    }
    
    // 디버깅: 결과 데이터 로그
    error_log("Query Result Count: " . count($data));
    $response['debug']['result_count'] = count($data);
    
    $response['success'] = true;
    $response['data'] = $data;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?> 