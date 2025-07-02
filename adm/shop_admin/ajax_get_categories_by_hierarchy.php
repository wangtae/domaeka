<?php
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// JSON 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// 권한 확인
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth) {
    echo json_encode(['success' => false, 'error' => '권한이 없습니다.']);
    exit;
}

// 파라미터 받기
$dt_id = isset($_POST['dt_id']) ? clean_xss_tags($_POST['dt_id']) : '';
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$br_id = isset($_POST['br_id']) ? clean_xss_tags($_POST['br_id']) : '';

try {
    $categories = [];
    
    // 계층별 분류 조회 조건 생성
    $where_conditions = [];
    
    if ($br_id) {
        // 지점이 선택된 경우 - 해당 dmk_br_id를 갖는 분류
        $where_conditions[] = "dmk_br_id = '".sql_escape_string($br_id)."'";
    } elseif ($ag_id) {
        // 대리점이 선택된 경우 - 해당 dmk_ag_id를 갖는 분류
        $where_conditions[] = "dmk_ag_id = '".sql_escape_string($ag_id)."'";
        $where_conditions[] = "dmk_br_id=''"; // 지점별 분류는 제외
    } elseif ($dt_id) {
        // 총판이 선택된 경우 - 해당 dmk_dt_id를 갖는 분류
        $where_conditions[] = "dmk_dt_id = '".sql_escape_string($dt_id)."'";
        $where_conditions[] = "dmk_ag_id=''"; // 대리점별 분류는 제외
        $where_conditions[] = "dmk_br_id=''"; // 지점별 분류는 제외
    } else {
        // 아무것도 선택되지 않은 경우 - 관리자 권한에 따라
        if (!$dmk_auth['is_super']) {
            // 일반 관리자는 자신의 계층에 속한 분류만
            global $member;
            if ($member['dmk_br_id']) {
                $where_conditions[] = "dmk_br_id = '".sql_escape_string($member['dmk_br_id'])."'";
            } elseif ($member['dmk_ag_id']) {
                $where_conditions[] = "dmk_ag_id = '".sql_escape_string($member['dmk_ag_id'])."'";
                $where_conditions[] = "dmk_br_id=''";
            } elseif ($member['dmk_dt_id']) {
                $where_conditions[] = "dmk_dt_id = '".sql_escape_string($member['dmk_dt_id'])."'";
                $where_conditions[] = "dmk_ag_id=''";
                $where_conditions[] = "dmk_br_id=''";
            }
        }
        // 최고관리자는 모든 분류 조회 (조건 없음)
    }
    
    // SQL 쿼리 생성
    $sql = "SELECT ca_id, ca_name FROM {$g5['g5_shop_category_table']} WHERE ca_use = '1'";
    
    if (!empty($where_conditions)) {
        $sql .= " AND (" . implode(" AND ", $where_conditions) . ")";
    }
    
    // 일반 관리자 권한 확인 (기존 로직)
    if ($is_admin != 'super') {
        global $member;
        $sql .= " AND ca_mb_id = '{$member['mb_id']}'";
    }
    
    $sql .= " ORDER BY ca_order, ca_id";
    
    $result = sql_query($sql);
    
    while ($row = sql_fetch_array($result)) {
        $len = strlen($row['ca_id']) / 2 - 1;
        $nbsp = "";
        for ($i = 0; $i < $len; $i++) {
            $nbsp .= "&nbsp;&nbsp;&nbsp;";
        }
        
        $categories[] = [
            'ca_id' => $row['ca_id'],
            'ca_name' => $nbsp . $row['ca_name'],
            'ca_name_plain' => $row['ca_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'debug_sql' => $sql
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => '분류 목록을 가져오는 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?> 