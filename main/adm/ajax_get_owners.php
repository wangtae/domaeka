<?php
include_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// AJAX 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 권한 확인
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] > 2) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$owner_type = isset($_POST['owner_type']) ? (int)$_POST['owner_type'] : 0;
$result = ['success' => false, 'data' => [], 'message' => ''];

try {
    switch ($owner_type) {
        case 1: // 총판
            $sql = " SELECT dt_id as id, dt_name as name 
                     FROM dmk_distributor 
                     WHERE dt_status = 1 
                     ORDER BY dt_name ";
            break;
            
        case 2: // 대리점
            $sql = " SELECT ag_id as id, ag_name as name 
                     FROM dmk_agency 
                     WHERE ag_status = 1 ";
            
            // 대리점 관리자는 자신의 대리점만 조회
            if ($dmk_auth['mb_type'] == 2) {
                $sql .= " AND ag_id = '" . sql_escape_string($dmk_auth['ag_id']) . "' ";
            }
            
            $sql .= " ORDER BY ag_name ";
            break;
            
        case 3: // 지점
            $sql = " SELECT br_id as id, br_name as name 
                     FROM dmk_branch 
                     WHERE br_status = 1 ";
            
            // 대리점 관리자는 자신의 대리점 소속 지점만 조회
            if ($dmk_auth['mb_type'] == 2) {
                $sql .= " AND ag_id = '" . sql_escape_string($dmk_auth['ag_id']) . "' ";
            }
            
            $sql .= " ORDER BY br_name ";
            break;
            
        default:
            throw new Exception('유효하지 않은 소속 구분입니다.');
    }
    
    $query_result = sql_query($sql);
    $data = [];
    
    while ($row = sql_fetch_array($query_result)) {
        $data[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    $result['success'] = true;
    $result['data'] = $data;
    
} catch (Exception $e) {
    $result['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?> 