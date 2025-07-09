<?php
/**
 * 카카오봇 시스템 연동 라이브러리
 * 
 * 기존 카카오봇 시스템의 API 호출 및 DB 연동을 위한 함수들을 정의합니다.
 * - 서버 상태 관리
 * - 클라이언트 봇 관리
 * - 스케줄링 관리
 * - 메시지 발송
 * - 채팅 로그 조회
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 방지

// 도매까 전역 설정 파일 포함
require_once dirname(__FILE__) . '/../../../dmk_global_settings.php';

/**
 * 카카오봇 시스템 테이블 접두사
 */
define('KB_TABLE_PREFIX', 'kb_');

/**
 * 카카오봇 서버 상태 조회
 * 
 * @return array 서버 상태 정보
 */
function kb_get_server_status() {
    $sql = "SELECT * FROM " . KB_TABLE_PREFIX . "server_status ORDER BY updated_at DESC LIMIT 1";
    $result = sql_query($sql);
    
    if ($result && sql_num_rows($result) > 0) {
        return sql_fetch_array($result);
    }
    
    return [
        'status' => 'unknown',
        'pid' => 0,
        'last_check' => '',
        'memory_usage' => 0,
        'cpu_usage' => 0
    ];
}

/**
 * 카카오봇 서버 프로세스 제어
 * 
 * @param string $action 동작 (start, stop, restart, status)
 * @return array 실행 결과
 */
function kb_control_server($action) {
    $allowed_actions = ['start', 'stop', 'restart', 'status'];
    
    if (!in_array($action, $allowed_actions)) {
        return ['success' => false, 'message' => '유효하지 않은 동작입니다.'];
    }
    
    // 실제 카카오봇 서버 제어 API 호출
    $api_url = KB_API_BASE_URL . '/server/control';
    $data = ['action' => $action];
    
    $response = kb_api_request($api_url, $data);
    
    if ($response['success']) {
        // 서버 상태 테이블 업데이트
        $status = $response['data']['status'] ?? 'unknown';
        $pid = $response['data']['pid'] ?? 0;
        
        $sql = "INSERT INTO " . KB_TABLE_PREFIX . "server_status 
                (status, pid, last_check, updated_at) 
                VALUES ('" . sql_escape_string($status) . "', 
                        '" . intval($pid) . "', 
                        NOW(), 
                        NOW())
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                pid = VALUES(pid), 
                last_check = VALUES(last_check), 
                updated_at = VALUES(updated_at)";
        
        sql_query($sql);
    }
    
    return $response;
}

/**
 * 클라이언트 봇 목록 조회
 * 
 * @param array $filters 필터 조건
 * @return array 클라이언트 봇 목록
 */
function kb_get_client_list($filters = []) {
    $where = [];
    $params = [];
    
    if (!empty($filters['br_id'])) {
        $where[] = "br_id = ?";
        $params[] = $filters['br_id'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT * FROM " . KB_TABLE_PREFIX . "bot_clients " . $where_clause . " ORDER BY created_at DESC";
    
    $result = sql_query($sql);
    $clients = [];
    
    while ($row = sql_fetch_array($result)) {
        $clients[] = $row;
    }
    
    return $clients;
}

/**
 * 클라이언트 봇 상태 업데이트
 * 
 * @param int $client_id 클라이언트 ID
 * @param string $status 상태 (active, inactive, error)
 * @return bool 성공 여부
 */
function kb_update_client_status($client_id, $status) {
    $allowed_statuses = ['active', 'inactive', 'error'];
    
    if (!in_array($status, $allowed_statuses)) {
        return false;
    }
    
    $sql = "UPDATE " . KB_TABLE_PREFIX . "bot_clients 
            SET status = '" . sql_escape_string($status) . "', 
                updated_at = NOW() 
            WHERE id = " . intval($client_id);
    
    return sql_query($sql);
}

/**
 * 스케줄 목록 조회
 * 
 * @param array $filters 필터 조건
 * @param int $page 페이지 번호
 * @param int $per_page 페이지당 항목 수
 * @return array 스케줄 목록과 페이징 정보
 */
function kb_get_schedule_list($filters = [], $page = 1, $per_page = 20) {
    $where = [];
    
    if (!empty($filters['br_id'])) {
        $where[] = "br_id = '" . sql_escape_string($filters['br_id']) . "'";
    }
    
    if (!empty($filters['status'])) {
        $where[] = "status = '" . sql_escape_string($filters['status']) . "'";
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(title LIKE '%" . sql_escape_string($filters['search']) . "%' OR 
                    message LIKE '%" . sql_escape_string($filters['search']) . "%')";
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // 전체 개수 조회
    $count_sql = "SELECT COUNT(*) as total FROM " . KB_TABLE_PREFIX . "schedule " . $where_clause;
    $count_result = sql_query($count_sql);
    $total = sql_fetch_array($count_result)['total'];
    
    // 목록 조회
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT * FROM " . KB_TABLE_PREFIX . "schedule " . $where_clause . " 
            ORDER BY created_at DESC 
            LIMIT " . intval($offset) . ", " . intval($per_page);
    
    $result = sql_query($sql);
    $schedules = [];
    
    while ($row = sql_fetch_array($result)) {
        $schedules[] = $row;
    }
    
    return [
        'schedules' => $schedules,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * 스케줄 등록/수정
 * 
 * @param array $data 스케줄 데이터
 * @return array 처리 결과
 */
function kb_save_schedule($data) {
    $required_fields = ['title', 'message', 'br_id', 'schedule_type'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => $field . ' 필드가 필요합니다.'];
        }
    }
    
    $id = isset($data['id']) ? intval($data['id']) : 0;
    
    if ($id > 0) {
        // 수정
        $sql = "UPDATE " . KB_TABLE_PREFIX . "schedule SET 
                title = '" . sql_escape_string($data['title']) . "',
                message = '" . sql_escape_string($data['message']) . "',
                image_url = '" . sql_escape_string($data['image_url'] ?? '') . "',
                schedule_type = '" . sql_escape_string($data['schedule_type']) . "',
                schedule_time = '" . sql_escape_string($data['schedule_time'] ?? '') . "',
                schedule_date = '" . sql_escape_string($data['schedule_date'] ?? '') . "',
                schedule_weekdays = '" . sql_escape_string($data['schedule_weekdays'] ?? '') . "',
                target_type = '" . sql_escape_string($data['target_type'] ?? 'live') . "',
                status = '" . sql_escape_string($data['status'] ?? 'active') . "',
                updated_at = NOW()
                WHERE id = " . $id;
    } else {
        // 신규 등록
        $sql = "INSERT INTO " . KB_TABLE_PREFIX . "schedule 
                (title, message, image_url, br_id, schedule_type, schedule_time, schedule_date, 
                 schedule_weekdays, target_type, status, created_at, updated_at) 
                VALUES (
                    '" . sql_escape_string($data['title']) . "',
                    '" . sql_escape_string($data['message']) . "',
                    '" . sql_escape_string($data['image_url'] ?? '') . "',
                    '" . sql_escape_string($data['br_id']) . "',
                    '" . sql_escape_string($data['schedule_type']) . "',
                    '" . sql_escape_string($data['schedule_time'] ?? '') . "',
                    '" . sql_escape_string($data['schedule_date'] ?? '') . "',
                    '" . sql_escape_string($data['schedule_weekdays'] ?? '') . "',
                    '" . sql_escape_string($data['target_type'] ?? 'live') . "',
                    '" . sql_escape_string($data['status'] ?? 'active') . "',
                    NOW(),
                    NOW()
                )";
    }
    
    if (sql_query($sql)) {
        return ['success' => true, 'message' => '스케줄이 저장되었습니다.'];
    } else {
        return ['success' => false, 'message' => '스케줄 저장에 실패했습니다.'];
    }
}

/**
 * 스케줄 삭제
 * 
 * @param int $id 스케줄 ID
 * @return bool 성공 여부
 */
function kb_delete_schedule($id) {
    $sql = "DELETE FROM " . KB_TABLE_PREFIX . "schedule WHERE id = " . intval($id);
    return sql_query($sql);
}

/**
 * 즉시 메시지 발송
 * 
 * @param array $data 메시지 데이터
 * @return array 발송 결과
 */
function kb_send_instant_message($data) {
    $required_fields = ['message', 'br_id'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => $field . ' 필드가 필요합니다.'];
        }
    }
    
    // 카카오봇 API를 통한 즉시 메시지 발송
    $api_url = KB_API_BASE_URL . '/message/send';
    $send_data = [
        'br_id' => $data['br_id'],
        'message' => $data['message'],
        'image_url' => $data['image_url'] ?? '',
        'target_type' => $data['target_type'] ?? 'live'
    ];
    
    $response = kb_api_request($api_url, $send_data);
    
    // 발송 로그 저장
    $log_sql = "INSERT INTO " . KB_TABLE_PREFIX . "schedule_log 
                (br_id, message, image_url, target_type, send_type, status, response_data, created_at) 
                VALUES (
                    '" . sql_escape_string($data['br_id']) . "',
                    '" . sql_escape_string($data['message']) . "',
                    '" . sql_escape_string($data['image_url'] ?? '') . "',
                    '" . sql_escape_string($data['target_type'] ?? 'live') . "',
                    'instant',
                    '" . ($response['success'] ? 'success' : 'failed') . "',
                    '" . sql_escape_string(json_encode($response)) . "',
                    NOW()
                )";
    
    sql_query($log_sql);
    
    return $response;
}

/**
 * 채팅 로그 조회
 * 
 * @param array $filters 필터 조건
 * @param int $page 페이지 번호
 * @param int $per_page 페이지당 항목 수
 * @return array 채팅 로그와 페이징 정보
 */
function kb_get_chat_log($filters = [], $page = 1, $per_page = 50) {
    $where = [];
    
    if (!empty($filters['br_id'])) {
        $where[] = "br_id = '" . sql_escape_string($filters['br_id']) . "'";
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "created_at >= '" . sql_escape_string($filters['date_from']) . " 00:00:00'";
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "created_at <= '" . sql_escape_string($filters['date_to']) . " 23:59:59'";
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(message LIKE '%" . sql_escape_string($filters['search']) . "%' OR 
                    sender_name LIKE '%" . sql_escape_string($filters['search']) . "%')";
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // 전체 개수 조회
    $count_sql = "SELECT COUNT(*) as total FROM " . KB_TABLE_PREFIX . "chat_log " . $where_clause;
    $count_result = sql_query($count_sql);
    $total = sql_fetch_array($count_result)['total'];
    
    // 목록 조회
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT * FROM " . KB_TABLE_PREFIX . "chat_log " . $where_clause . " 
            ORDER BY created_at DESC 
            LIMIT " . intval($offset) . ", " . intval($per_page);
    
    $result = sql_query($sql);
    $logs = [];
    
    while ($row = sql_fetch_array($result)) {
        $logs[] = $row;
    }
    
    return [
        'logs' => $logs,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * 카카오봇 API 요청
 * 
 * @param string $url API URL
 * @param array $data 요청 데이터
 * @param string $method HTTP 메서드
 * @return array API 응답
 */
function kb_api_request($url, $data = [], $method = 'POST') {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . KB_API_TOKEN
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API 요청 오류: ' . $error];
    }
    
    if ($http_code >= 400) {
        return ['success' => false, 'message' => 'API 오류: HTTP ' . $http_code];
    }
    
    $decoded = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'JSON 디코딩 오류'];
    }
    
    return $decoded;
}

/**
 * 지점별 권한 확인
 * 
 * @param string $br_id 지점 ID
 * @param array $auth 관리자 권한 정보
 * @return bool 권한 여부
 */
function kb_check_branch_permission($br_id, $auth) {
    // 본사 관리자는 모든 권한
    if ($auth['is_super']) {
        return true;
    }
    
    // 지점 관리자는 자신의 지점만
    if ($auth['mb_type'] === 'branch') {
        return $auth['br_id'] === $br_id;
    }
    
    // 대리점 관리자는 소속 지점들
    if ($auth['mb_type'] === 'agency') {
        $sql = "SELECT COUNT(*) as count FROM dmk_branch 
                WHERE br_id = '" . sql_escape_string($br_id) . "' 
                AND ag_id = '" . sql_escape_string($auth['ag_id']) . "'";
        $result = sql_query($sql);
        $row = sql_fetch_array($result);
        return $row['count'] > 0;
    }
    
    // 총판 관리자는 소속 대리점의 지점들
    if ($auth['mb_type'] === 'distributor') {
        $sql = "SELECT COUNT(*) as count FROM dmk_branch b 
                JOIN dmk_agency a ON b.ag_id = a.ag_id 
                WHERE b.br_id = '" . sql_escape_string($br_id) . "' 
                AND a.dt_id = '" . sql_escape_string($auth['dt_id']) . "'";
        $result = sql_query($sql);
        $row = sql_fetch_array($result);
        return $row['count'] > 0;
    }
    
    return false;
}

/**
 * 봇 기능 권한 확인
 * 
 * @param string $function 기능명 (schedule, instant_send, chat_log, server_status, client_list)
 * @param array $auth 관리자 권한 정보
 * @return bool 권한 여부
 */
function kb_check_function_permission($function, $auth) {
    // 본사 관리자는 모든 권한
    if ($auth['is_super']) {
        return true;
    }
    
    // 기능명을 메뉴 코드로 매핑
    $function_menu_mapping = [
        'server_status' => '180100',  // 서버 관리 (본사 전용)
        'client_list'   => '180200',  // 봇 관리 (본사 전용)
        'schedule'      => '180300',  // 스케줄링 발송 관리
        'instant_send'  => '180400',  // 메시지 즉시 발송
        'chat_log'      => '180500'   // 채팅 내역 조회
    ];
    
    if (!isset($function_menu_mapping[$function])) {
        return false;
    }
    
    $menu_code = $function_menu_mapping[$function];
    
    // 현재 사용자 타입 가져오기
    $user_type = dmk_get_current_user_type();
    
    if (!$user_type) {
        return false;
    }
    
    // dmk_global_settings.php의 메뉴 권한 확인
    return dmk_is_menu_allowed($menu_code, $user_type);
}

// 설정 상수들 (실제 환경에서는 config.php에서 관리)
if (!defined('KB_API_BASE_URL')) {
    define('KB_API_BASE_URL', 'http://localhost:8000/api'); // 카카오봇 API 기본 URL
}

if (!defined('KB_API_TOKEN')) {
    define('KB_API_TOKEN', 'your-api-token-here'); // 카카오봇 API 토큰
}

?>