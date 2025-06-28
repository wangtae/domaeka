<?php
// 출력 버퍼링 시작 및 오류 설정
ob_start();
error_reporting(0); // AJAX 파일에서는 모든 오류/경고 비활성화
ini_set('display_errors', 0);

include_once './_common.php';

// 출력 버퍼 정리 (혹시 _common.php에서 의도치 않은 출력이 있을 경우)
ob_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$dmk_auth = dmk_get_admin_auth();

// POST 또는 GET 방식으로 전송된 ag_id 처리
$ag_id = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
} else {
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
}

$response = array(
    'success' => false,
    'message' => '',
    'data' => array()
);

try {
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
        $br_sql_where = " WHERE a.dt_id = '".sql_escape_string($dmk_auth['mb_id'])."' AND b.br_status = 1 ";
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
        // 대리점 관리자: 자신의 대리점에 속한 지점만 조회 (ag_id 파라미터 무시)
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

    $response['success'] = true;
    $response['data'] = $branches;
    $response['message'] = '지점 목록을 성공적으로 조회했습니다.';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = '지점 목록 조회 중 오류가 발생했습니다: ' . $e->getMessage();
}

// 출력 버퍼 정리 후 JSON 출력
ob_clean();
echo json_encode($response);
exit;
?> 