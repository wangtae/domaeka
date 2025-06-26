<?php
/**
 * Domaeka 관리자 인증 및 권한 관리 라이브러리
 * 
 * 영카트 최고관리자 - 총판 - 대리점 - 지사 계층 구조에 따른 관리자 권한 처리
 * 
 * @author Domaeka Development Team
 * @version 1.0
 * @since 2024-01-20
 */

if (!defined('_GNUBOARD_')) exit; // 개별 접근 차단

// 도매까 전역 설정 포함
include_once(G5_PATH . '/dmk/dmk_global_settings.php');

/**
 * 도매까 관리자 유형 상수 정의 (mb_level 기반)
 */
define('DMK_MB_LEVEL_SUPER',        10); // 최고관리자
define('DMK_MB_LEVEL_DISTRIBUTOR',  8);  // 총판 관리자
define('DMK_MB_LEVEL_AGENCY',       6);  // 대리점 관리자
define('DMK_MB_LEVEL_BRANCH',       4);  // 지점 관리자
define('DMK_MB_LEVEL_MEMBER',       2);  // 일반 회원
define('DMK_MB_LEVEL_GUEST',        1);  // 비회원

/**
 * 도매까 관리자 유형 상수 정의 (기존)
 */
define('DMK_MB_TYPE_NONE',          0); // 일반
define('DMK_MB_TYPE_DISTRIBUTOR',   1); // 총판
define('DMK_MB_TYPE_AGENCY',        2); // 대리점
define('DMK_MB_TYPE_BRANCH',        3); // 지점

/**
 * 도매까 상품 소유 계층 상수 정의
 */
define('DMK_OWNER_TYPE_DISTRIBUTOR', 'DISTRIBUTOR'); // 총판 (구 본사)
define('DMK_OWNER_TYPE_AGENCY',      'AGENCY');           // 대리점
define('DMK_OWNER_TYPE_BRANCH',      'BRANCH');           // 지점

/**
 * 현재 로그인한 관리자의 도매까 권한 정보를 가져옵니다.
 * 
 * @return array|false 권한 정보 배열 또는 false (권한 없음)
 */
function dmk_get_admin_auth() {
    global $member, $is_admin, $g5;
    
    if (!$member || !$member['mb_id']) {
        return false;
    }
    
    // 영카트 최고관리자 확인
    $is_super = false;
    if (function_exists('is_super_admin') && is_super_admin($member['mb_id'])) {
        $is_super = true;
    }
    
    // 도매까 관리자 정보 조회 (admin_type 포함)
    $sql = " SELECT m.mb_id, m.mb_name, m.mb_level, m.dmk_mb_type, m.dmk_dt_id, m.dmk_ag_id, m.dmk_br_id, m.dmk_admin_type,\n                    ag_m.mb_name AS ag_name, br_m.mb_name AS br_name\n             FROM {$g5['member_table']} m\n             LEFT JOIN {$g5['member_table']} ag_m ON m.dmk_ag_id = ag_m.mb_id AND ag_m.dmk_mb_type = 2 AND ag_m.dmk_admin_type = 'main'\n             LEFT JOIN {$g5['member_table']} br_m ON m.dmk_br_id = br_m.mb_id AND br_m.dmk_mb_type = 3 AND br_m.dmk_admin_type = 'main'\n             WHERE m.mb_id = '" . sql_escape_string($member['mb_id']) . "' ";
    $admin_info = sql_fetch($sql);
    
    if (!$admin_info) {
        return false;
    }
    
    return array(
        'mb_id' => $admin_info['mb_id'],
        'mb_name' => $admin_info['mb_name'],
        'mb_level' => (int)$admin_info['mb_level'],
        'mb_type' => (int)$admin_info['dmk_mb_type'],
        'admin_type' => $admin_info['dmk_admin_type'] ?: 'main', // 기본값 main
        'ag_id' => $admin_info['dmk_ag_id'],
        'br_id' => $admin_info['dmk_br_id'],
        'ag_name' => $admin_info['ag_name'],
        'br_name' => $admin_info['br_name'],
        'is_super' => $is_super
    );
}

/**
 * 특정 권한을 가지고 있는지 확인합니다.
 * 
 * @param string $permission 확인할 권한
 * @return bool 권한 보유 여부
 */
function dmk_has_permission($permission) {
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고 관리자는 모든 권한 보유
    if ($auth['is_super']) {
        return true;
    }
    
    return in_array($permission, $auth['permissions']);
}

/**
 * 상품 조회를 위한 WHERE 조건을 생성합니다.
 * 
 * @return string SQL WHERE 조건
 */
function dmk_get_item_where_condition() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super'] || $auth['mb_level'] >= DMK_MB_LEVEL_DISTRIBUTOR) {
        return ''; // 최고 관리자 및 총판 관리자는 모든 상품 조회 가능
    }
    
    switch ($auth['mb_level']) {
        case DMK_MB_LEVEL_AGENCY:
            return " AND (dmk_it_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "' OR (dmk_it_owner_type = 'AGENCY' AND dmk_it_owner_id = '" . sql_escape_string($auth['ag_id']) . "'))";
            
        case DMK_MB_LEVEL_BRANCH:
            // 지점은 총판, 소속 대리점, 자신의 상품만 조회 가능
            $ag_id = dmk_get_branch_agency_id($auth['br_id']);
            $condition = " AND (dmk_it_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "'";
            if ($ag_id) {
                $condition .= " OR (dmk_it_owner_type = 'AGENCY' AND dmk_it_owner_id = '" . sql_escape_string($ag_id) . "')";
            }
            $condition .= " OR (dmk_it_owner_type = 'BRANCH' AND dmk_it_owner_id = '" . sql_escape_string($auth['br_id']) . "'))";
            return $condition;
            
        default:
            return ' AND 1=0'; // 접근 차단 (일반 회원 또는 비회원)
    }
}

/**
 * 주문 조회를 위한 WHERE 조건을 생성합니다.
 * 
 * @param string|null $br_id 지점 ID (선택적)
 * @param string|null $ag_id 대리점 ID (선택적)
 * @param string|null $dt_id 총판 ID (선택적)
 * @return string SQL WHERE 조건
 */
function dmk_get_order_where_condition($br_id = null, $ag_id = null, $dt_id = null) {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super'] || $auth['mb_level'] >= DMK_MB_LEVEL_DISTRIBUTOR) {
        return ''; // 최고 관리자 및 총판 관리자는 모든 주문 조회 가능
    }
    
    // 매개변수가 전달된 경우 해당 값을 사용, 그렇지 않으면 인증 정보 사용
    $user_br_id = $br_id ?: (isset($auth['br_id']) ? $auth['br_id'] : '');
    $user_ag_id = $ag_id ?: (isset($auth['ag_id']) ? $auth['ag_id'] : '');
    $user_dt_id = $dt_id ?: (isset($auth['dt_id']) ? $auth['dt_id'] : '');
    
    switch ($auth['mb_level']) {
        case DMK_MB_LEVEL_AGENCY:
            // 대리점은 소속 지점들의 주문만 조회 가능
            $branch_ids = dmk_get_agency_branch_ids($user_ag_id);
            if (empty($branch_ids)) {
                return ' AND 1=0'; // 소속 지점이 없으면 조회 불가
            }
            return " AND dmk_od_br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "')";
            
        case DMK_MB_LEVEL_BRANCH:
            return " AND dmk_od_br_id = '" . sql_escape_string($user_br_id) . "'";
            
        default:
            return ' AND 1=0'; // 접근 차단 (일반 회원 또는 비회원)
    }
}

/**
 * 지점의 소속 대리점 ID를 가져옵니다.
 * 
 * @param string $br_id 지점 ID
 * @return string|null 대리점 ID
 */
function dmk_get_branch_agency_id($br_id) {
    global $g5;
    
    if (!$br_id) return null;
    
    $sql = " SELECT ag_id FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    return $row ? $row['ag_id'] : null;
}

/**
 * 대리점의 소속 지점 ID 목록을 가져옵니다.
 * 
 * @param string $ag_id 대리점 ID
 * @return array 지점 ID 목록
 */
function dmk_get_agency_branch_ids($ag_id) {
    global $g5;
    
    if (!$ag_id) return array();
    
    $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '" . sql_escape_string($ag_id) . "' AND br_status = 1 ";
    $result = sql_query($sql);
    
    $branch_ids = array();
    while ($row = sql_fetch_array($result)) {
        $branch_ids[] = $row['br_id'];
    }
    
    return $branch_ids;
}

/**
 * 상품 수정/삭제 권한을 확인합니다.
 * 
 * @param string $it_id 상품 ID
 * @return bool 권한 보유 여부
 */
function dmk_can_modify_item($it_id) {
    global $g5;
    
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고 관리자는 모든 권한 보유 (영카트 최고 관리자)
    if ($auth['is_super']) {
        return true;
    }
    
    // 상품 정보 조회
    $sql = " SELECT dmk_it_owner_type, dmk_it_owner_id FROM {$g5['g5_shop_item_table']} WHERE it_id = '" . sql_escape_string($it_id) . "' ";
    $item = sql_fetch($sql);
    
    if (!$item) {
        return false;
    }
    
    // 소유권 확인
    switch ($auth['mb_level']) {
        case DMK_MB_LEVEL_DISTRIBUTOR:
            // 총판 관리자는 총판 소유 상품만 수정 가능
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_DISTRIBUTOR;
            
        case DMK_MB_LEVEL_AGENCY:
            // 대리점 관리자는 대리점 소유 상품만 수정 가능
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_AGENCY && 
                   $item['dmk_it_owner_id'] === $auth['ag_id'];
            
        case DMK_MB_LEVEL_BRANCH:
            // 지점 관리자는 지점 소유 상품만 수정 가능
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_BRANCH && 
                   $item['dmk_it_owner_id'] === $auth['br_id'];
            
        default:
            return false; // 일반 회원 또는 비회원은 상품 수정 불가
    }
}

/**
 * 관리자 유형에 따른 상품 소유 정보를 설정합니다.
 * 
 * @return array 소유 정보 배열 (owner_type, owner_id)
 */
function dmk_get_item_owner_info() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return array('owner_type' => '', 'owner_id' => '');
    }
    
    switch ($auth['mb_level']) {
        case DMK_MB_LEVEL_DISTRIBUTOR:
            return array('owner_type' => DMK_OWNER_TYPE_DISTRIBUTOR, 'owner_id' => null);
            
        case DMK_MB_LEVEL_AGENCY:
            return array('owner_type' => DMK_OWNER_TYPE_AGENCY, 'owner_id' => $auth['ag_id']);
            
        case DMK_MB_LEVEL_BRANCH:
            return array('owner_type' => DMK_OWNER_TYPE_BRANCH, 'owner_id' => $auth['br_id']);
            
        default:
            return array('owner_type' => '', 'owner_id' => '');
    }
}

/**
 * 관리자 메뉴 접근 권한을 확인합니다.
 *
 * @param string $menu_code 메뉴 코드
 * @return bool 접근 권한 여부
 */
function dmk_can_access_menu($menu_code) {
    $auth = dmk_get_admin_auth();

    if (!$auth) {
        return false;
    }

    // 최고 관리자는 모든 메뉴 접근 가능 (영카트 최고 관리자)
    if ($auth['is_super']) {
        return true;
    }
    
    // 현재 사용자의 계층 타입 가져오기
    $user_type = dmk_get_current_user_type();

    // dmk_global_settings.php 에 정의된 dmk_is_menu_allowed 함수를 사용하여 권한 확인
    if (function_exists('dmk_is_menu_allowed') && dmk_is_menu_allowed($menu_code, $user_type)) {
        return true;
    }

    return false;
}

/**
 * 관리자의 접근 권한을 확인하고, 권한이 없는 경우 접근을 차단합니다.
 *
 * @param int $required_level 이 페이지에 접근하기 위해 필요한 관리자 레벨 (DMK_MB_LEVEL_DISTRIBUTOR, DMK_MB_LEVEL_AGENCY, DMK_MB_LEVEL_BRANCH)
 */
function dmk_authenticate_admin($required_level) {
    global $g5; // 그누보드 전역 변수 $g5를 사용합니다.

    $auth = dmk_get_admin_auth();

    // 1. 관리자 로그인 여부 확인 및 권한 정보 가져오기
    if (!$auth || empty($auth['mb_id'])) {
        // 관리자가 아니거나 로그인하지 않은 경우, 로그인 페이지로 리디렉션
        alert("관리자 권한이 필요합니다.", G5_ADMIN_URL);
        exit;
    }

    // 2. 최고 관리자 (영카트 최고 관리자)는 모든 관리자 페이지에 접근 가능
    if ($auth['is_super']) {
        return true;
    }

    // 3. 필요한 관리자 레벨에 따른 접근 권한 확인
    // 현재 사용자의 mb_level이 required_level보다 크거나 같아야 접근 허용
    if ($auth['mb_level'] >= $required_level) {
        return true;
    }

    alert("접근 권한이 없습니다.", G5_ADMIN_URL);
    exit;
}

/**
 * 현재 로그인한 관리자가 총판 관리자인지 확인합니다.
 * 
 * @param string $mb_id 회원 ID (선택사항, 기본값은 현재 로그인한 관리자)
 * @return bool 총판 관리자 여부
 */
function dmk_is_distributor($mb_id = null) {
    global $member, $is_admin;
    
    // 관리자가 아니면 false
    if (!$is_admin) {
        return false;
    }
    
    // mb_id가 지정되지 않으면 현재 로그인한 관리자 사용
    if (!$mb_id) {
        $mb_id = $member['mb_id'];
    }
    
    // 영카트 최고 관리자는 총판 관리자 권한을 가집니다.
    if (is_super_admin($mb_id)) {
        return true;
    }
    
    // 현재 로그인한 관리자가 아닌 다른 관리자를 확인하는 경우
    if ($mb_id !== $member['mb_id']) {
        // 다른 관리자 정보를 데이터베이스에서 조회
        global $g5;
        $sql = " SELECT mb_level FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
        $row = sql_fetch($sql);
        
        if ($row) {
            return (int)$row['mb_level'] === DMK_MB_LEVEL_DISTRIBUTOR;
        } else {
            return false;
        }
    }
    
    // 현재 로그인한 관리자의 경우
    return (int)$member['mb_level'] === DMK_MB_LEVEL_DISTRIBUTOR;
}

/**
 * 현재 로그인한 관리자가 특정 총판을 수정할 권한이 있는지 확인합니다.
 * 
 * @param string $distributor_mb_id 총판 회원 ID
 * @return bool 수정 권한 여부
 */
function dmk_can_modify_distributor($distributor_mb_id) {
    global $g5;

    $auth = dmk_get_admin_auth();

    if (!$auth) {
        return false;
    }

    // 1. 최고 관리자는 모든 총판 정보 수정 가능
    if ($auth['is_super']) {
        return true;
    }

    // 2. 총판 관리자는 자신의 총판 정보만 수정 가능
    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && $auth['mb_id'] === $distributor_mb_id) {
        return true;
    }

    return false;
}

/**
 * 회원 목록 조회를 위한 WHERE 조건을 생성합니다.
 * 
 * @return string SQL WHERE 조건
 */
function dmk_get_member_where_condition() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super']) {
        return ''; // 모든 회원 조회 가능 (영카트 최고 관리자)
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return ''; // 총판 관리자는 모든 회원 조회 가능
            
        case DMK_MB_TYPE_AGENCY:
            // 대리점 관리자는 소속 지점 관리자들만 조회 가능
            $branch_ids = dmk_get_agency_branch_ids($auth['ag_id']);
            if (empty($branch_ids)) {
                return ' AND 1=0'; // 소속 지점이 없으면 조회 불가
            }
            return " AND (dmk_mb_type = 0 OR (dmk_mb_type = 3 AND dmk_br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "')))";
            
        case DMK_MB_TYPE_BRANCH:
            // 지점 관리자는 일반 회원만 조회 가능
            return " AND dmk_mb_type = 0";
            
        default:
            return ' AND 1=0'; // 접근 차단
    }
}

/**
 * 현재 로그인한 관리자가 특정 회원을 수정할 권한이 있는지 확인합니다.
 * 
 * @param string $mb_id 회원 ID
 * @return bool 수정 권한 여부
 */
function dmk_can_modify_member($mb_id) {
    global $g5;
    
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고 관리자는 모든 회원 수정 가능
    if ($auth['is_super']) {
        return true;
    }
    
    // 회원 정보 조회
    $sql = " SELECT mb_level, dmk_mb_type, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    $member_to_modify = sql_fetch($sql);
    
    if (!$member_to_modify) {
        return false;
    }
    
    // 사용자의 mb_level에 따라 수정 권한 확인
    switch ($auth['mb_level']) {
        case DMK_MB_LEVEL_DISTRIBUTOR:
            // 총판 관리자는 자신보다 레벨이 낮거나 같은 총판 (즉, 자신) 그리고 그 이하의 모든 회원을 수정할 수 있습니다.
            // 최고 관리자는 제외 (is_super로 이미 처리됨)
            return ($member_to_modify['mb_level'] <= DMK_MB_LEVEL_DISTRIBUTOR && $member_to_modify['mb_level'] != DMK_MB_LEVEL_SUPER);
            
        case DMK_MB_LEVEL_AGENCY:
            // 대리점 관리자는 자신보다 레벨이 낮거나 같은 대리점 (즉, 자신) 그리고 지점, 일반 회원을 수정할 수 있습니다.
            // 또한, 자신이 속한 총판의 아래에 있는 대리점 (즉, 자신)과 그 아래의 지점, 일반 회원을 수정할 수 있습니다.
            // mb_level이 6(대리점) 이하인 회원만 수정 가능
            if ($member_to_modify['mb_level'] <= DMK_MB_LEVEL_AGENCY) {
                // 수정하려는 회원이 대리점일 경우, 자신의 ag_id와 일치해야 함 (혹은 자기 자신)
                if ($member_to_modify['mb_level'] == DMK_MB_LEVEL_AGENCY) {
                    return $member_to_modify['dmk_ag_id'] === $auth['ag_id'] || $member_to_modify['mb_id'] === $auth['mb_id'];
                }
                // 수정하려는 회원이 지점일 경우, 해당 지점의 ag_id가 자신의 ag_id와 일치해야 함
                if ($member_to_modify['mb_level'] == DMK_MB_LEVEL_BRANCH) {
                    return dmk_get_branch_agency_id($member_to_modify['dmk_br_id']) === $auth['ag_id'];
                }
                // 일반 회원인 경우 (mb_level 2)
                if ($member_to_modify['mb_level'] == DMK_MB_LEVEL_MEMBER) {
                    return true;
                }
            }
            return false;
            
        case DMK_MB_LEVEL_BRANCH:
            // 지점 관리자는 일반 회원만 수정 가능 (mb_level 2)
            return $member_to_modify['mb_level'] == DMK_MB_LEVEL_MEMBER;
            
        default:
            return false; // 일반 회원 이하 레벨은 수정 권한 없음
    }
}

/**
 * 회원 소유 정보를 가져옵니다.
 * 
 * @return array 소유 정보 배열 (owner_type, owner_id)
 */
function dmk_get_member_owner_info() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return array('owner_type' => '', 'owner_id' => '');
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return array('owner_type' => 'DISTRIBUTOR', 'owner_id' => $auth['mb_id']);
            
        case DMK_MB_TYPE_AGENCY:
            return array('owner_type' => 'AGENCY', 'owner_id' => $auth['ag_id']);
            
        case DMK_MB_TYPE_BRANCH:
            return array('owner_type' => 'BRANCH', 'owner_id' => $auth['br_id']);
            
        default:
            return array('owner_type' => '', 'owner_id' => '');
    }
}

// Domaeka 관리자가 일반 그누보드/영카트 메뉴에 접근할 수 있는지 확인하는 함수
function dmk_has_general_menu_access($menu_code) {
    $dmk_auth = dmk_get_admin_auth();

    // 최고관리자는 모든 일반 메뉴 접근 가능
    if ($dmk_auth['is_super']) {
        return true;
    }

    // Domaeka 관리자 (본사, 총판, 대리점, 지점)는 특정 일반 메뉴에 접근 가능하도록 허용
    if ($menu_code === '200100') { // 회원목록
        // 지점 관리자 (mb_level 4) 이상은 회원목록 접근 가능
        return $dmk_auth['mb_level'] >= DMK_MB_LEVEL_BRANCH;
    }

    return false;
}

/**
 * 메뉴 표시 권한을 체크하는 함수 (admin.head.php에서 사용)
 * 전역 설정(dmk_global_settings.php)을 기반으로 계층별 메뉴 권한을 확인합니다.
 * 
 * @param string $menu_code 메뉴 코드
 * @param string $menu_key 메뉴 키
 * @return bool 메뉴 표시 여부
 */
function dmk_auth_check_menu_display($menu_code, $menu_key) {
    global $is_admin, $auth, $member;

    // 1. 최고관리자는 모든 메뉴 접근 가능
    if ($is_admin == 'super' || (function_exists('is_super_admin') && is_super_admin($member['mb_id']))) {
        return true;
    }

    // 2. DMK 관리자 권한 정보 가져오기
    $dmk_auth = dmk_get_admin_auth();
    if (!$dmk_auth) {
        // DMK 관리자가 아닌 경우 기존 권한 체크
        return (array_key_exists($menu_code, $auth) && strstr($auth[$menu_code], 'r'));
    }

    // 3. 현재 사용자의 계층 타입 가져오기
    $user_type = dmk_get_current_user_type();

    // 4. Main 관리자와 Sub 관리자 구분 처리
    if ($dmk_auth['admin_type'] === 'main') {
        // Main 관리자는 전역 설정을 사용한 메뉴 권한 체크
        if (function_exists('dmk_is_menu_allowed')) {
            $is_allowed = dmk_is_menu_allowed($menu_code, $user_type);
            return $is_allowed;
        }
    } else {
        // Sub 관리자는 개별 권한 설정 확인
        // 도매까 메뉴 (190xxx)에 대해서는 개별 권한 확인
        if (substr($menu_code, 0, 3) == '190') {
            return dmk_check_individual_menu_permission($dmk_auth['mb_id'], $menu_code);
        }
        
        // 일반 그누보드/영카트 메뉴에 대해서는 기존 권한 배열 확인
        return (array_key_exists($menu_code, $auth) && strstr($auth[$menu_code], 'r'));
    }

    // 5. 기존 방식 fallback
    // 도매까 메뉴 (190xxx) - 기존 메뉴 구조
    if (substr($menu_code, 0, 3) == '190') {
        return dmk_can_access_menu($menu_key);
    }

    // 일반 그누보드/영카트 메뉴
    if (dmk_has_general_menu_access($menu_code)) {
        return true;
    }

    // 그 외 일반 메뉴는 기존 권한 배열 ($auth)에 따름
    return (array_key_exists($menu_code, $auth) && strstr($auth[$menu_code], 'r'));
}

/**
 * 도매까 관리자를 위한 권한 체크 함수 (기존 auth_check 대체)
 * 
 * @param string $auth_str 권한 문자열
 * @param string $attr 요구 권한 (r, w, d)
 * @param bool $return 오류 시 메시지 반환 여부
 * @return string|void 오류 메시지 또는 void
 */
function dmk_auth_check($auth_str, $attr, $return = false) {
    global $is_admin;

    // 최고 관리자는 모든 권한 허용
    if ($is_admin == 'super') {
        return;
    }

    // 도매까 관리자 체크 (mb_level 2 초과, 즉 일반 회원 이상 레벨부터 적용)
    $dmk_auth = dmk_get_admin_auth();
    if ($dmk_auth['mb_level'] > DMK_MB_LEVEL_MEMBER) {
        // 도매까 관리자는 기본적으로 읽기 권한 허용
        if (strtolower($attr) == 'r') {
            return;
        }
        // 쓰기/삭제 권한은 메뉴별로 별도 체크 (향후 확장 가능). 현재는 임시 허용.
        if (strtolower($attr) == 'w' || strtolower($attr) == 'd') {
            return; 
        }
    }

    // 기존 권한 체크 로직
    if (!trim($auth_str)) {
        $msg = '이 메뉴에는 접근 권한이 없습니다.\\n\\n접근 권한은 최고관리자만 부여할 수 있습니다.';
        if ($return) {
            return $msg;
        } else {
            alert($msg);
        }
    }

    $attr = strtolower($attr);

    // SET 타입 권한 문자열 처리
    $has_auth = false;
    if (is_string($auth_str)) {
        // 일반 문자열 형태 (예: "rwd")
        $has_auth = strstr($auth_str, $attr) !== false;
    } else {
        // 배열이나 다른 형태로 전달된 경우 문자열로 변환
        $auth_str = (string)$auth_str;
        $has_auth = strstr($auth_str, $attr) !== false;
    }

    if (!$has_auth) {
        if ($attr == 'r') {
            $msg = '읽을 권한이 없습니다.';
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        } else if ($attr == 'w') {
            $msg = '입력, 추가, 생성, 수정 권한이 없습니다.';
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        } else if ($attr == 'd') {
            $msg = '삭제 권한이 없습니다.';
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        } else {
            $msg = '속성이 잘못 되었습니다.';
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        }
    }
}

/**
 * 도매까 관리자를 위한 메뉴 권한 체크 함수 (기존 auth_check_menu 대체)
 * 
 * @param array $auth 권한 배열
 * @param string $sub_menu 서브메뉴 코드
 * @param string $attr 요구 권한 (r, w, d)
 * @param bool $return 오류 시 메시지 반환 여부
 * @return string|void 오류 메시지 또는 void
 */
function dmk_auth_check_menu($auth, $sub_menu, $attr, $return = false) {
    global $is_admin;

    // 최고 관리자는 모든 권한 허용
    if ($is_admin == 'super') {
        return;
    }

    // DMK 관리자 권한 확인
    $dmk_auth = dmk_get_admin_auth();
    if ($dmk_auth && $dmk_auth['mb_level'] > DMK_MB_LEVEL_MEMBER) {
        // Main 관리자는 전역 설정에 따른 권한 체크
        if ($dmk_auth['admin_type'] === 'main') {
            $user_type = dmk_get_current_user_type();
            if ($user_type !== 'none' && function_exists('dmk_is_menu_allowed')) {
                $is_allowed = dmk_is_menu_allowed($sub_menu, $user_type);
                if ($is_allowed) {
                    return; // 권한 허용
                }
            }
        } else {
            // Sub 관리자는 개별 권한 설정 확인
            if (dmk_check_individual_menu_permission($dmk_auth['mb_id'], $sub_menu)) {
                return; // 권한 허용
            }
        }
        
        // DMK 관리자지만 권한이 없는 경우
        $msg = '이 메뉴에는 접근 권한이 없습니다.\\n\\n접근 권한은 상위 관리자가 부여할 수 있습니다.';
        if ($return) {
            return $msg;
        } else {
            alert($msg);
        }
    }

    // 기존 권한 체크 로직 (DMK 관리자가 아닌 경우)
    $check_auth = isset($auth[$sub_menu]) ? $auth[$sub_menu] : '';
    return dmk_auth_check($check_auth, $attr, $return);
}

/**
 * 카테고리 수정 권한을 확인합니다.
 * 
 * @param string $ca_id 카테고리 ID
 * @return bool 수정 권한 여부
 */
function dmk_can_modify_category($ca_id) {
    $auth = dmk_get_admin_auth();
    
    // 최고 관리자는 모든 카테고리 수정 가능
    if ($auth['is_super']) {
        return true;
    }
    
    // 총판 관리자만 카테고리 수정 가능
    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        return true;
    }
    
    // 대리점, 지점 관리자는 카테고리 수정 불가
    return false;
}

/**
 * 카테고리 소유 정보를 가져옵니다.
 * 
 * @return array 카테고리 소유 정보
 */
function dmk_get_category_owner_info() {
    $auth = dmk_get_admin_auth();
    
    $owner_info = [
        'owner_type' => '',
        'owner_id' => ''
    ];
    
    if (!$auth) {
        return $owner_info; // 권한 정보가 없으면 빈 값 반환
    }

    if ($auth['is_super']) {
        // 최고 관리자는 총판 소유가 기본
        $owner_info['owner_type'] = DMK_OWNER_TYPE_DISTRIBUTOR;
        // 최고관리자는 특정 총판 ID를 가지지 않으므로 빈 값 유지. 필요시 명시적으로 선택.
        $owner_info['owner_id'] = '';
    } else {
        switch ($auth['mb_type']) {
            case DMK_MB_TYPE_DISTRIBUTOR:
                $owner_info['owner_type'] = DMK_OWNER_TYPE_DISTRIBUTOR;
                $owner_info['owner_id'] = $auth['mb_id']; // 총판 ID
                break;
            case DMK_MB_TYPE_AGENCY:
                $owner_info['owner_type'] = DMK_OWNER_TYPE_AGENCY;
                $owner_info['owner_id'] = $auth['ag_id']; // 대리점 ID
                break;
            case DMK_MB_TYPE_BRANCH:
                $owner_info['owner_type'] = DMK_OWNER_TYPE_BRANCH;
                $owner_info['owner_id'] = $auth['br_id']; // 지점 ID
                break;
            default:
                // 그 외 계층은 기본적으로 소유권 없음 (또는 제한적)
                $owner_info['owner_type'] = ''; 
                $owner_info['owner_id'] = '';
                break;
        }
    }
    
    return $owner_info;
}

/**
 * 지점 정보를 가져옵니다.
 * 
 * @param string $br_id 지점 ID
 * @return array|false 지점 정보 또는 false
 */
function get_branch_info($br_id) {
    global $g5;
    
    if (!$br_id) {
        return false;
    }
    
    $sql = "SELECT b.*, a.ag_name 
            FROM dmk_branch b 
            LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
            WHERE b.br_id = '" . sql_escape_string($br_id) . "'";
    
    return sql_fetch($sql);
}

/**
 * 장바구니 WHERE 조건을 반환합니다.
 * 
 * @param string $br_id 지점 ID
 * @param string $ag_id 대리점 ID  
 * @param string $dt_id 총판 ID
 * @return string WHERE 조건
 */
function dmk_get_cart_where_condition($br_id = null, $ag_id = null, $dt_id = null) {
    $auth = dmk_get_admin_auth();
    
    // 최고 관리자는 모든 데이터 조회 가능
    if ($auth['is_super']) {
        return '';
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            // 총판: 모든 데이터 조회 가능
            return '';
            
        case DMK_MB_TYPE_AGENCY:
            // 대리점: 소속 지점들의 데이터만
            $branch_ids = dmk_get_agency_branch_ids($auth['ag_id']);
            if (empty($branch_ids)) {
                return " AND 1=0 ";
            }
            return " AND dmk_br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "') ";
            
        case DMK_MB_TYPE_BRANCH:
            // 지점: 자신의 데이터만
            return " AND dmk_br_id = '" . sql_escape_string($auth['br_id']) . "' ";
            
        default:
            return " AND 1=0 ";
    }
}

/**
 * 현재 관리자가 main 관리자인지 확인합니다.
 * 
 * @return bool main 관리자 여부
 */
function dmk_is_main_admin() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super']) {
        return true; // 최고관리자는 항상 main으로 간주
    }
    
    return $auth['admin_type'] === 'main';
}

/**
 * 현재 관리자가 sub 관리자인지 확인합니다.
 * 
 * @return bool sub 관리자 여부
 */
function dmk_is_sub_admin() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super']) {
        return false; // 최고관리자는 sub가 아님
    }
    
    return $auth['admin_type'] === 'sub';
}

/**
 * 관리자가 특정 계층에서 추가 관리자를 생성할 권한이 있는지 확인합니다.
 * 
 * @param string $target_type 생성하려는 관리자 타입 ('distributor', 'agency', 'branch')
 * @return bool 생성 권한 여부
 */
function dmk_can_create_admin($target_type) {
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고관리자는 모든 권한
    if ($auth['is_super']) {
        return true;
    }
    
    // main 관리자만 하위 계층 관리자 생성 가능
    if ($auth['admin_type'] !== 'main') {
        return false;
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            // 총판은 대리점, 지점 관리자 생성 가능
            return in_array($target_type, ['agency', 'branch']);
            
        case DMK_MB_TYPE_AGENCY:
            // 대리점은 지점 관리자만 생성 가능
            return $target_type === 'branch';
            
        case DMK_MB_TYPE_BRANCH:
            // 지점은 관리자 생성 불가
            return false;
            
        default:
            return false;
    }
}

/**
 * 관리자가 특정 메뉴에 접근할 권한이 있는지 확인합니다.
 * main 관리자는 모든 권한을 가지고, sub 관리자는 영카트의 기존 권한 시스템을 확인합니다.
 * 
 * @param string $menu_id 메뉴 ID 또는 메뉴 키
 * @return bool 접근 권한 여부
 */
function dmk_can_access_menu_new($menu_id) {
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고관리자는 모든 권한
    if ($auth['is_super']) {
        return true;
    }
    
    // main 관리자는 해당 계층의 모든 메뉴 접근 가능
    if ($auth['admin_type'] === 'main') {
        return dmk_check_hierarchy_access($menu_id, $auth['mb_type']);
    }
    
    // sub 관리자는 영카트의 기존 권한 시스템으로 개별 권한 확인
    // 메뉴 키를 메뉴 코드로 변환
    $menu_code = dmk_convert_menu_key_to_code($menu_id);
    return dmk_check_individual_menu_permission($auth['mb_id'], $menu_code);
}

/**
 * 계층별 기본 메뉴 접근 권한을 확인합니다.
 * 
 * @param string $menu_id 메뉴 ID
 * @param int $mb_type 관리자 타입
 * @return bool 접근 권한 여부
 */
function dmk_check_hierarchy_access($menu_id, $mb_type) {
    // 계층별 접근 가능한 메뉴 정의
    $hierarchy_menus = array(
        DMK_MB_TYPE_DISTRIBUTOR => array(
            'distributor_list', 'distributor_form', 'agency_list', 'agency_form', 
            'branch_list', 'branch_form', 'admin_list', 'admin_form', 'statistics'
        ),
        DMK_MB_TYPE_AGENCY => array(
            'branch_list', 'branch_form', 'admin_list', 'admin_form', 'order_list', 'member_list'
        ),
        DMK_MB_TYPE_BRANCH => array(
            'order_list', 'member_list', 'item_list'
        )
    );
    
    return isset($hierarchy_menus[$mb_type]) && in_array($menu_id, $hierarchy_menus[$mb_type]);
}

/**
 * 메뉴 키를 메뉴 코드로 변환합니다.
 * 
 * @param string $menu_key 메뉴 키
 * @return string 메뉴 코드
 */
function dmk_convert_menu_key_to_code($menu_key) {
    // 메뉴 키와 메뉴 코드 매핑
    $menu_mapping = array(
        'dmk_manage' => '190000',
        'dmk_distributor' => '190100',
        'dmk_agency' => '190200',
        'dmk_branch' => '190300',
        'dmk_statistics' => '190400',
        'dmk_url' => '190500',
        'dmk_admin' => '190600',
        'dmk_auth' => '190700',
        'agency_form' => '190200',
        'branch_form' => '190300',
        'distributor_list' => '190100',
        'distributor_form' => '190100',
        'agency_list' => '190200',
        'branch_list' => '190300',
        'admin_list' => '190600',
        'admin_form' => '190600',
        'statistics' => '190400'
    );
    
    // 이미 메뉴 코드 형식이면 그대로 반환
    if (preg_match('/^\d{6}$/', $menu_key)) {
        return $menu_key;
    }
    
    return isset($menu_mapping[$menu_key]) ? $menu_mapping[$menu_key] : $menu_key;
}

/**
 * sub 관리자의 개별 메뉴 권한을 확인합니다.
 * 영카트의 기존 권한 시스템(g5_auth 테이블)을 활용합니다.
 * 
 * @param string $mb_id 관리자 ID
 * @param string $menu_id 메뉴 ID
 * @return bool 접근 권한 여부
 */
function dmk_check_individual_menu_permission($mb_id, $menu_id) {
    global $g5;
    
    // 영카트의 기존 권한 테이블에서 권한 확인 (SET 타입 처리)
    $sql = " SELECT au_auth FROM {$g5['auth_table']} 
             WHERE mb_id = '" . sql_escape_string($mb_id) . "' 
             AND au_menu = '" . sql_escape_string($menu_id) . "'
             AND FIND_IN_SET('r', au_auth) > 0 ";
    $row = sql_fetch($sql);
    
    // 권한이 설정되어 있고, 읽기 권한(r)이 있으면 접근 허용
    return $row !== false;
}

/**
 * 모든 총판 목록을 가져옵니다.
 *
 * @return array 총판 목록 (mb_id, mb_name)
 */
function dmk_get_distributors() {
    global $g5;

    $sql = " SELECT mb_id, mb_name FROM {$g5['member_table']} WHERE dmk_mb_type = " . DMK_MB_TYPE_DISTRIBUTOR . " ORDER BY mb_name ";
    $result = sql_query($sql);

    $distributors = [];
    while ($row = sql_fetch_array($result)) {
        $distributors[] = $row;
    }
    return $distributors;
}

/**
 * 특정 총판에 속한 대리점 목록을 가져옵니다.
 *
 * @param string|null $dt_id 총판 ID (null인 경우 모든 대리점)
 * @return array 대리점 목록 (ag_id, ag_name)
 */
function dmk_get_agencies($dt_id = null) {
    global $g5;

    $where = '';
    if ($dt_id) {
        $where = ' WHERE dt_id = ' . sql_escape_string($dt_id) . ' ';
    }

    $sql = ' SELECT ag_id, ag_name FROM dmk_agency ' . $where . ' ORDER BY ag_name ';
    $result = sql_query($sql);

    $agencies = array();
    while ($row = sql_fetch_array($result)) {
        $agencies[] = $row;
    }

    return $agencies;
}

/**
 * 특정 대리점에 속한 지점 목록을 가져옵니다.
 *
 * @param string|null $ag_id 대리점 ID (null인 경우 모든 지점)
 * @return array 지점 목록 (br_id, br_name)
 */
function dmk_get_branches($ag_id = null) {
    global $g5;

    $where = "";
    if ($ag_id) {
        $where = " WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    }
    $sql = " SELECT br_id, br_name FROM dmk_branch {$where} ORDER BY br_name ";
    $result = sql_query($sql);

    $branches = [];
    while ($row = sql_fetch_array($result)) {
        $branches[] = $row;
    }
    return $branches;
}

/**
 * 카테고리 조회를 위한 WHERE 조건을 생성합니다.
 *
 * @param string|null $dt_id 필터링할 총판 ID
 * @param string|null $ag_id 필터링할 대리점 ID
 * @param string|null $br_id 필터링할 지점 ID
 * @return string SQL WHERE 조건 (앞에 AND 포함)
 */
function dmk_get_category_where_condition($dt_id = null, $ag_id = null, $br_id = null) {
    global $g5;
    $auth = dmk_get_admin_auth();

    if (!$auth) {
        return ' AND 1=0'; // 권한 정보 없으면 접근 차단
    }

    $conditions = [];
    $hierarchy_filter = '';

    // 1. 명시적 필터 (사용자 선택) 적용
    if ($br_id) { // 지점 ID가 명시적으로 선택된 경우
        $conditions[] = " dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id = '" . sql_escape_string($br_id) . "'";
    } elseif ($ag_id) { // 대리점 ID가 명시적으로 선택된 경우 (지점 ID는 선택 안 됨)
        // 해당 대리점 소유 카테고리 또는 해당 대리점 산하 지점 소유 카테고리
        $agency_branches = dmk_get_agency_branch_ids($ag_id);
        $agency_condition = "(dmk_ca_owner_type = '" . DMK_OWNER_TYPE_AGENCY . "' AND dmk_ca_owner_id = '" . sql_escape_string($ag_id) . "')";
        if (!empty($agency_branches)) {
            $agency_condition .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $agency_branches)) . "'))";
        }
        $conditions[] = "($agency_condition)";
    } elseif ($dt_id) { // 총판 ID가 명시적으로 선택된 경우 (대리점, 지점 ID는 선택 안 됨)
        // 해당 총판 소유 카테고리 또는 해당 총판 산하 대리점/지점 소유 카테고리
        $distributor_agencies = dmk_get_agencies($dt_id); // 이 함수는 dmk_dt_id를 받으므로 여기서 바로 사용 가능
        $distributor_branches = dmk_get_branches_for_distributor($dt_id); // 특정 총판의 모든 지점 가져오기

        $distributor_condition = "(dmk_ca_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "' AND dmk_ca_owner_id = '" . sql_escape_string($dt_id) . "')";
        if (!empty($distributor_agencies)) {
            $agency_ids = array_column($distributor_agencies, 'ag_id');
            $distributor_condition .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_AGENCY . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $agency_ids)) . "'))";
        }
        if (!empty($distributor_branches)) {
            $distributor_condition .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $distributor_branches)) . "'))";
        }
        $conditions[] = "($distributor_condition)";
    }

    // 2. 로그인한 관리자 권한에 따른 기본 필터 (명시적 필터가 없는 경우에만 적용)
    if (empty($conditions)) { // 명시적 필터가 없는 경우에만 적용
        if ($auth['is_super']) {
            $hierarchy_filter = ''; // 최고 관리자는 모든 데이터 접근
        } else {
            switch ($auth['mb_type']) {
                case DMK_MB_TYPE_DISTRIBUTOR:
                    // 총판 관리자는 자신 소유의 총판 카테고리 및 하위 계층(대리점, 지점)의 카테고리 조회 가능
                    $hierarchy_filter = " (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "' AND dmk_ca_owner_id = '" . sql_escape_string($auth['mb_id']) . "') ";
                    $all_agencies = dmk_get_agencies($auth['mb_id']); // 본인 총판의 대리점
                    if (!empty($all_agencies)) {
                        $agency_ids_under_dist = array_column($all_agencies, 'ag_id');
                        $hierarchy_filter .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_AGENCY . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $agency_ids_under_dist)) . "'))";
                    }
                    $all_branches_under_dist = dmk_get_branches_for_distributor($auth['mb_id']); // 본인 총판의 모든 지점
                    if (!empty($all_branches_under_dist)) {
                        $hierarchy_filter .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $all_branches_under_dist)) . "'))";
                    }
                    break;
                case DMK_MB_TYPE_AGENCY:
                    // 대리점 관리자는 자신 소유의 대리점 카테고리, 그리고 자신 소속 지점의 카테고리 조회 가능
                    $branch_ids = dmk_get_agency_branch_ids($auth['ag_id']);
                    $hierarchy_filter = " (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_AGENCY . "' AND dmk_ca_owner_id = '" . sql_escape_string($auth['ag_id']) . "')";
                    if (!empty($branch_ids)) {
                        $hierarchy_filter .= " OR (dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "'))";
                    }
                    break;
                case DMK_MB_TYPE_BRANCH:
                    // 지점 관리자는 자신 소유의 지점 카테고리만 조회 가능
                    $hierarchy_filter = "dmk_ca_owner_type = '" . DMK_OWNER_TYPE_BRANCH . "' AND dmk_ca_owner_id = '" . sql_escape_string($auth['br_id']) . "'";
                    break;
                default:
                    $hierarchy_filter = '1=0'; // 접근 차단
                    break;
            }
            if ($hierarchy_filter) {
                $conditions[] = $hierarchy_filter;
            }
        }
    }

    // 최종 WHERE 조건 결합
    if (empty($conditions)) {
        return '';
    } else {
        return ' AND (' . implode(' AND ', $conditions) . ')';
    }
}

/**
 * 특정 총판에 속한 모든 지점 ID 목록을 가져옵니다.
 *
 * @param string $dt_id 총판 ID
 * @return array 지점 ID 목록
 */
function dmk_get_branches_for_distributor($dt_id) {
    global $g5;

    if (!$dt_id) return array();

    $sql = " SELECT br.br_id FROM dmk_branch br
             LEFT JOIN dmk_agency ag ON br.ag_id = ag.ag_id
             WHERE ag.dt_id = '" . sql_escape_string($dt_id) . "' AND br.br_status = 1 ";
    $result = sql_query($sql);

    $branch_ids = array();
    while ($row = sql_fetch_array($result)) {
        $branch_ids[] = $row['br_id'];
    }

    return $branch_ids;
}

/**
 * 대리점의 소속 총판 ID를 가져옵니다.
 * 
 * @param string $ag_id 대리점 ID
 * @return string|null 총판 ID
 */
function dmk_get_agency_distributor_id($ag_id) {
    global $g5;
    
    if (!$ag_id) return null;
    
    $sql = " SELECT dt_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    return $row ? $row['dt_id'] : null;
}

/**
 * 회원 이름을 가져옵니다.
 * 
 * @param string $mb_id 회원 ID
 * @return string 회원 이름
 */
function dmk_get_member_name($mb_id) {
    global $g5;
    
    if (!$mb_id) return '';
    
    $sql = " SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    $row = sql_fetch($sql);
    
    return $row ? $row['mb_name'] : '';
}

/**
 * 대리점 이름을 가져옵니다.
 * 
 * @param string $ag_id 대리점 ID
 * @return string 대리점 이름
 */
function dmk_get_agency_name($ag_id) {
    global $g5;
    
    if (!$ag_id) return '';
    
    $sql = " SELECT ag_name FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $row = sql_fetch($sql);
    
    return $row ? $row['ag_name'] : '';
}

/**
 * 지점 이름을 가져옵니다.
 * 
 * @param string $br_id 지점 ID
 * @return string 지점 이름
 */
function dmk_get_branch_name($br_id) {
    global $g5;
    
    if (!$br_id) return '';
    
    $sql = " SELECT br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    return $row ? $row['br_name'] : '';
}

/**
 * 관리자 폼 페이지(등록/수정) 접근 권한을 확인하고, 권한이 없는 경우 접근을 차단합니다.
 * 이 함수는 특정 엔티티(총판, 대리점, 지점 등)의 등록 및 수정 폼 페이지에 대한 권한을 통합 관리합니다.
 *
 * @param string $menu_code 해당 폼 페이지의 메뉴 코드 (예: 'distributor_form', 'agency_form', 'branch_form')
 * @param string $w 'u' (수정) 또는 'c' (등록/생성) 또는 빈 문자열 (등록/생성으로 간주)
 * @param string $target_id 수정 대상 엔티티의 ID (수정 모드일 경우 필수)
 */
function dmk_authenticate_form_access($menu_code, $w = '', $target_id = '') {
    global $g5;

    $auth = dmk_get_admin_auth();

    // 1. 관리자 로그인 여부 확인 및 권한 정보 가져오기
    if (!$auth || empty($auth['mb_id'])) {
        alert("관리자 권한이 필요합니다.", G5_ADMIN_URL);
        exit;
    }

    // 2. 최고 관리자 (영카트 최고 관리자)는 모든 관리자 폼에 접근 가능
    if ($auth['is_super']) {
        return; // 접근 허용, 함수 종료
    }

    // 3. 수정 모드일 경우 특정 엔티티 수정 권한 확인
    if ($w === 'u' && !empty($target_id)) {
        // 특정 엔티티(총판, 대리점, 지점) 수정 권한 함수 호출
        // 이 부분은 menu_code에 따라 동적으로 호출되어야 함 (예: dmk_can_modify_distributor, dmk_can_modify_agency 등)
        $can_modify_func = 'dmk_can_modify_' . str_replace('_form', '', $menu_code);

        if (function_exists($can_modify_func) && $can_modify_func($target_id)) {
            return; // 접근 허용
        }
    } else { // 등록 모드일 경우 메뉴 접근 권한 확인
        // 메뉴 접근 권한 확인
        if (dmk_can_access_menu($menu_code)) {
            return; // 접근 허용
        }
    }

    // 모든 검사를 통과하지 못하면 접근 차단
    alert('접근 권한이 없습니다.');
}

?> 