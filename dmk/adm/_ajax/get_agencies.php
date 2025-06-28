<?php
// 최상위 common.php 파일을 포함하여 그누보드 환경을 올바르게 설정합니다.
include_once('../../../common.php');
// 도매까 전용 관리자 권한 라이브러리를 포함합니다.
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

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

    // 권한별 데이터 접근 제어
    if ($dmk_auth['is_super']) {
        // 본사 관리자: 선택된 총판의 대리점 조회
        $ag_sql_where = '';
        if (!empty($dt_id)) {
            $ag_sql_where = " WHERE a.dt_id = '".sql_escape_string($dt_id)."' AND a.ag_status = 1 ";
        } else {
            // dt_id가 없으면 빈 목록을 반환하는 것이 안전합니다.
            // $ag_sql_where = " WHERE a.ag_status = 1 ";
        }

        if ($ag_sql_where) {
            $ag_sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
                       FROM dmk_agency a
                       JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
                       ". $ag_sql_where ." ORDER BY m.mb_nick ASC";
            
            $response['debug']['sql'] = $ag_sql;
            
            $ag_res = sql_query($ag_sql);
            while($ag_row = sql_fetch_array($ag_res)) {
                $agencies[] = array(
                    'id' => $ag_row['ag_id'],
                    'name' => $ag_row['ag_name']
                );
            }
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

    $response['success'] = true;
    $response['data'] = $agencies;
    $response['message'] = '대리점 목록을 성공적으로 조회했습니다.';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = '대리점 목록 조회 중 오류가 발생했습니다: ' . $e->getMessage();
    error_log('get_agencies.php 오류: ' . $e->getMessage());
}

echo json_encode($response);
exit;
?>