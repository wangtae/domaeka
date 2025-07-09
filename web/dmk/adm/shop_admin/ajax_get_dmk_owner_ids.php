<?php
// 올바른 그누보드 관리자 공통 파일 경로를 포함합니다.
// 이 파일은 G5_ADMIN_PATH 상수를 정의하고 admin.lib.php 등을 로드합니다.
include_once '../../adm/_common.php';

header('Content-Type: application/json');

$owner_type = isset($_GET['owner_type']) ? clean_xss_tags($_GET['owner_type']) : '';
$parent_id = isset($_GET['parent_id']) ? clean_xss_tags($_GET['parent_id']) : '';

$results = [];

if ($owner_type == 'agency' && $parent_id) {
    // 선택된 총판에 속한 대리점 목록 조회
    $sql = " SELECT a.ag_id AS id, m.mb_nick AS name 
             FROM dmk_agency a
             JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
             WHERE a.dt_id = '".sql_escape_string($parent_id)."' 
             AND a.ag_status = 1
             ORDER BY m.mb_nick ASC ";
    $query_result = sql_query($sql);
    while($row = sql_fetch_array($query_result)) {
        $results[] = $row;
    }
}

echo json_encode($results);
?> 