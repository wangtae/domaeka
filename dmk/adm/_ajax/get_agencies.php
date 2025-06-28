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

// POST 또는 GET 방식으로 전송된 dt_id 처리
$dt_id = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dt_id = isset($_POST['dt_id']) ? clean_xss_tags($_POST['dt_id']) : '';
} else {
$dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
}

$response = array(
    'success' => false,
    'message' => '',
    'data' => array(),
    'debug' => array(
        'dt_id' => $dt_id,
        'auth' => $dmk_auth,
        'request_method' => $_SERVER['REQUEST_METHOD']
    )
);

try {
$agencies = [];

// 디버깅: 요청 시작 로그
error_log('get_agencies.php 시작: dt_id=' . $dt_id . ', REQUEST_METHOD=' . $_SERVER['REQUEST_METHOD']);
error_log('get_agencies.php dmk_auth: ' . print_r($dmk_auth, true));

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
    
    // 디버깅을 위해 SQL 쿼리 추가
    $response['debug']['sql'] = $ag_sql;
    $response['debug']['sql_where'] = $ag_sql_where;
    
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
                   WHERE a.dt_id = '".sql_escape_string($dmk_auth['mb_id'])."' AND a.ag_status = 1 
               ORDER BY m.mb_nick ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[] = array(
            'id' => $ag_row['ag_id'],
            'name' => $ag_row['ag_name']
        );
    }
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자: 자신의 대리점만 조회
        $agencies[] = array(
            'id' => $dmk_auth['ag_id'],
            'name' => $dmk_auth['mb_name']
        );
    }
    // 지점 관리자는 대리점 목록을 조회할 권한이 없음

    $response['success'] = true;
    $response['data'] = $agencies;
    $response['message'] = '대리점 목록을 성공적으로 조회했습니다.';
    
    // 성공적인 요청 로그 기록 (디버깅용)
    error_log('get_agencies.php 성공: dt_id=' . $dt_id . ', 대리점 수=' . count($agencies));

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = '대리점 목록 조회 중 오류가 발생했습니다: ' . $e->getMessage();
    
    // 오류 로그 기록
    error_log('get_agencies.php 오류: ' . $e->getMessage());
    error_log('get_agencies.php 스택 트레이스: ' . $e->getTraceAsString());
}

// 출력 버퍼 정리 후 JSON 출력
ob_clean();
echo json_encode($response);
exit;
?> 