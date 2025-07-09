<?php
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// DMK 권한 확인
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth) {
    echo json_encode(['error' => '권한이 없습니다.']);
    exit;
}

// 파라미터 받기
$dmk_dt_id = isset($_POST['dmk_dt_id']) ? clean_xss_tags($_POST['dmk_dt_id']) : '';
$dmk_ag_id = isset($_POST['dmk_ag_id']) ? clean_xss_tags($_POST['dmk_ag_id']) : '';
$dmk_br_id = isset($_POST['dmk_br_id']) ? clean_xss_tags($_POST['dmk_br_id']) : '';

// 분류 조회 조건 생성
$sql = "SELECT * FROM {$g5['g5_shop_category_table']} WHERE 1=1 ";

// 계층별 필터링
if ($dmk_br_id) {
    // 지점이 선택된 경우: 해당 지점의 분류만
    $sql .= " AND dmk_br_id = '" . sql_escape_string($dmk_br_id) . "'";
} elseif ($dmk_ag_id) {
    // 대리점이 선택된 경우: 해당 대리점의 분류만  
    $sql .= " AND dmk_ag_id = '" . sql_escape_string($dmk_ag_id) . "' AND (dmk_br_id IS NULL OR dmk_br_id = '')";
} elseif ($dmk_dt_id) {
    // 총판이 선택된 경우: 해당 총판의 분류만
    $sql .= " AND dmk_dt_id = '" . sql_escape_string($dmk_dt_id) . "' AND (dmk_ag_id IS NULL OR dmk_ag_id = '') AND (dmk_br_id IS NULL OR dmk_br_id = '')";
} else {
    // 아무것도 선택되지 않은 경우: 본사 분류만
    $sql .= " AND (dmk_dt_id IS NULL OR dmk_dt_id = '') AND (dmk_ag_id IS NULL OR dmk_ag_id = '') AND (dmk_br_id IS NULL OR dmk_br_id = '')";
}

// DMK 권한에 따른 추가 제한
if (!$dmk_auth['is_super']) {
    $hierarchy_condition = dmk_get_category_where_condition($dmk_dt_id, $dmk_ag_id, $dmk_br_id);
    if ($hierarchy_condition) {
        $sql .= " AND " . ltrim($hierarchy_condition, ' AND ');
    }
}

$sql .= " ORDER BY ca_order, ca_id";

$result = sql_query($sql);
$categories = [];

while ($row = sql_fetch_array($result)) {
    $len = strlen($row['ca_id']) / 2 - 1;
    $nbsp = str_repeat("&nbsp;&nbsp;&nbsp;", $len);
    
    $categories[] = [
        'ca_id' => $row['ca_id'],
        'ca_name' => $nbsp . $row['ca_name'],
        'ca_use' => $row['ca_use'],
        'ca_stock_qty' => $row['ca_stock_qty'],
        'ca_sell_email' => $row['ca_sell_email']
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($categories);
?>