<?php
include_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

$dmk_auth = dmk_get_admin_auth();
$dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';

$agencies = [];

// 권한별 데이터 접근 제어
if ($dmk_auth['is_super']) {
    // 본사 관리자: 선택된 총판의 대리점 조회
    $ag_sql_where = '';
    if (!empty($dt_id)) {
        $ag_sql_where = " WHERE a.dt_id = '".sql_escape_string($dt_id)."' AND a.ag_status = 1 ";
    } else {
        $ag_sql_where = " WHERE a.ag_status = 1 ";
    }

    $ag_sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
               FROM dmk_agency a
               JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
               ". $ag_sql_where ." ORDER BY m.mb_nick ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[] = array(
            'id' => $ag_row['ag_id'],
            'name' => $ag_row['ag_name']
        );
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    // 총판 관리자: 자신의 총판에 속한 대리점만 조회
    $ag_sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
               FROM dmk_agency a
               JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
               WHERE a.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."' AND a.ag_status = 1 
               ORDER BY m.mb_nick ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[] = array(
            'id' => $ag_row['ag_id'],
            'name' => $ag_row['ag_name']
        );
    }
}
// 대리점 관리자와 지점 관리자는 대리점 목록을 조회할 권한이 없음

echo json_encode($agencies);
?> 