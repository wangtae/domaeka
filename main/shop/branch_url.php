<?php
include_once('./_common.php');

// URL에서 지점 코드 추출
function extract_branch_code_from_url() {
    $branch_code = '';
    
    // 1. GET 파라미터에서 확인
    if (isset($_GET['code']) && $_GET['code']) {
        $branch_code = clean_xss_tags($_GET['code']);
    }
    
    // 2. PATH_INFO에서 확인
    if (!$branch_code && isset($_SERVER['PATH_INFO'])) {
        $branch_code = trim($_SERVER['PATH_INFO'], '/');
    }
    
    // 3. REQUEST_URI에서 직접 추출
    if (!$branch_code && isset($_SERVER['REQUEST_URI'])) {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri_parts = explode('/', trim($uri, '/'));
        
        // /shop/{code} 패턴
        if (count($uri_parts) >= 2 && $uri_parts[0] == 'shop') {
            $branch_code = $uri_parts[1];
        }
        // /order/{code} 패턴  
        elseif (count($uri_parts) >= 2 && $uri_parts[0] == 'order') {
            $branch_code = $uri_parts[1];
        }
        // /{code} 패턴 (루트 레벨)
        elseif (count($uri_parts) >= 1 && !in_array($uri_parts[0], [
            'main', 'docs', 'temp', '_tools', 'admin', 'api',
            'bbs', 'skin', 'theme', 'plugin', 'data', 'img'
        ])) {
            $branch_code = $uri_parts[0];
        }
    }
    
    return $branch_code;
}

// 친화적 URL 매핑 테이블 (데이터베이스 연동 전 임시)
function get_branch_mapping() {
    return [
        // 친화적 이름 => 실제 지점 ID
        'haeundae' => 'BR003',      // 해운대점
        'busan'    => 'BR003',      // 부산점 (해운대점과 동일)
        'gangnam'  => 'BR001',      // 강남점
        'seoul'    => 'BR001',      // 서울점 (강남점과 동일)
        'hongdae'  => 'BR002',      // 홍대점
        'mapo'     => 'BR002',      // 마포점 (홍대점과 동일)
        'suwon'    => 'BR004',      // 수원점
        'gyeonggi' => 'BR004',      // 경기점 (수원점과 동일)
        'incheon'  => 'BR005',      // 인천점
        'daegu'    => 'BR006',      // 대구점
        'gwangju'  => 'BR007',      // 광주점
        'daejeon'  => 'BR008',      // 대전점
        'ulsan'    => 'BR009',      // 울산점
        'jeju'     => 'BR010',      // 제주점
    ];
}

// 데이터베이스에서 지점 URL 매핑 조회 (향후 구현)
function get_branch_mapping_from_db() {
    global $g5;
    
    // 지점 URL 매핑 테이블이 있다면 사용
    $sql = " SELECT br_url_code, br_id 
             FROM dmk_branch_url 
             WHERE br_url_active = 1 ";
    
    $result = @sql_query($sql, false); // 테이블이 없어도 오류 방지
    $mapping = [];
    
    if ($result) {
        while ($row = sql_fetch_array($result)) {
            $mapping[strtolower($row['br_url_code'])] = $row['br_id'];
        }
    }
    
    return $mapping;
}

// 지점 코드를 실제 지점 ID로 변환
function resolve_branch_id($branch_code) {
    if (!$branch_code) {
        return false;
    }
    
    $branch_code_lower = strtolower($branch_code);
    
    // 1. 데이터베이스 매핑 확인 (우선순위)
    $db_mapping = get_branch_mapping_from_db();
    if (isset($db_mapping[$branch_code_lower])) {
        return $db_mapping[$branch_code_lower];
    }
    
    // 2. 하드코딩된 매핑 확인
    $static_mapping = get_branch_mapping();
    if (isset($static_mapping[$branch_code_lower])) {
        return $static_mapping[$branch_code_lower];
    }
    
    // 3. 직접 지점 ID 형태인지 확인 (BR001, BR002 등)
    $direct_id = strtoupper($branch_code);
    if (preg_match('/^BR[0-9]{3,}$/', $direct_id)) {
        return $direct_id;
    }
    
    return false;
}

// 지점 존재 여부 및 상태 확인
function validate_branch($br_id) {
    global $g5;
    
    if (!$br_id) {
        return false;
    }
    
    $sql = " SELECT br_id, br_name, br_status, br_phone, br_address, ag_id
             FROM dmk_branch 
             WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    
    $branch = sql_fetch($sql);
    
    if (!$branch) {
        return false;
    }
    
    // 지점 상태 확인 (1: 활성, 0: 비활성)
    if ($branch['br_status'] != 1) {
        return false;
    }
    
    return $branch;
}

// 접속 로그 기록 (선택사항)
function log_branch_access($branch_code, $br_id, $user_agent = '') {
    global $g5;
    
    // 접속 통계 테이블이 있다면 기록
    $sql = " INSERT INTO dmk_branch_access_log 
             (br_code, br_id, access_time, user_ip, user_agent) 
             VALUES (
                 '" . sql_escape_string($branch_code) . "',
                 '" . sql_escape_string($br_id) . "',
                 NOW(),
                 '" . sql_escape_string($_SERVER['REMOTE_ADDR']) . "',
                 '" . sql_escape_string($user_agent) . "'
             ) ";
    
    @sql_query($sql, false); // 테이블이 없어도 오류 방지
}

// 메인 처리 로직
try {
    // 1. URL에서 지점 코드 추출
    $branch_code = extract_branch_code_from_url();
    
    if (!$branch_code) {
        // 지점 코드가 없으면 메인 페이지로
        header('Location: ' . G5_URL);
        exit;
    }
    
    // 2. 지점 코드를 실제 지점 ID로 변환
    $br_id = resolve_branch_id($branch_code);
    
    if (!$br_id) {
        // 매핑되지 않은 코드
        alert('존재하지 않는 지점 코드입니다: ' . htmlspecialchars($branch_code), G5_URL);
    }
    
    // 3. 지점 존재 여부 및 상태 확인
    $branch = validate_branch($br_id);
    
    if (!$branch) {
        alert('현재 서비스를 이용할 수 없는 지점입니다.', G5_URL);
    }
    
    // 4. 접속 로그 기록 (선택사항)
    log_branch_access($branch_code, $br_id, $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // 5. 실제 주문 페이지로 리다이렉트
    $redirect_url = G5_URL . '/main/shop/order_page.php?br_id=' . urlencode($br_id);
    
    // 추가 파라미터가 있다면 전달
    if (!empty($_GET)) {
        $query_params = $_GET;
        unset($query_params['code']); // code 파라미터는 제거
        
        if (!empty($query_params)) {
            $redirect_url .= '&' . http_build_query($query_params);
        }
    }
    
    // 301 리다이렉트 (SEO 친화적)
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect_url);
    exit;
    
} catch (Exception $e) {
    // 오류 발생 시 로그 기록 및 사용자 친화적 메시지
    error_log('Branch URL Error: ' . $e->getMessage());
    alert('일시적인 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', G5_URL);
}

// 여기까지 도달하면 안 됨
alert('페이지를 찾을 수 없습니다.', G5_URL);
?> 