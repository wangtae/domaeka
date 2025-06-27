<?php
include_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

$dmk_auth = dmk_get_admin_auth();
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';

$branches = [];

// 권한별 데이터 접근 제어
if ($dmk_auth['is_super']) {
    // 본사 관리자: 선택된 대리점의 지점 조회
    $br_sql_where = '';
    if (!empty($ag_id)) {
        $br_sql_where = " WHERE b.ag_id = '".sql_escape_string($ag_id)."' AND b.br_status = 1 ";
    } else {
        $br_sql_where = " WHERE b.br_status = 1 ";
    }

    $br_sql = "SELECT b.br_id, m.mb_nick AS br_name 
               FROM dmk_branch b
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id
               ". $br_sql_where ." ORDER BY m.mb_nick ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[] = array(
            'id' => $br_row['br_id'],
            'name' => $br_row['br_name']
        );
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    // 총판 관리자: 자신의 총판에 속한 지점들 중 선택된 대리점의 지점만 조회
    $br_sql_where = " WHERE a.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."' AND b.br_status = 1 ";
    if (!empty($ag_id)) {
        $br_sql_where .= " AND b.ag_id = '".sql_escape_string($ag_id)."' ";
    }

    $br_sql = "SELECT b.br_id, m.mb_nick AS br_name 
               FROM dmk_branch b
               JOIN dmk_agency a ON b.ag_id = a.ag_id
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id
               ". $br_sql_where ." ORDER BY m.mb_nick ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[] = array(
            'id' => $br_row['br_id'],
            'name' => $br_row['br_name']
        );
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    // 대리점 관리자: 자신의 대리점에 속한 지점만 조회
    $br_sql = "SELECT b.br_id, m.mb_nick AS br_name 
               FROM dmk_branch b
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id
               WHERE b.ag_id = '".sql_escape_string($dmk_auth['ag_id'])."' AND b.br_status = 1 
               ORDER BY m.mb_nick ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[] = array(
            'id' => $br_row['br_id'],
            'name' => $br_row['br_name']
        );
    }
}
// 지점 관리자는 지점 목록을 조회할 권한이 없음

echo json_encode($branches);
?> 