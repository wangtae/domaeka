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
    global $is_admin, $member, $g5;

    $auth_info = [
        'is_super' => false,
        'mb_type' => DMK_MB_TYPE_NONE, // 0: 일반, 1: 총판, 2: 대리점, 3: 지점
        'dt_id' => '',
        'ag_id' => '',
        'br_id' => '',
        'mb_level' => (int)($member['mb_level'] ?? 1), // 기본값 1 (비회원)
    ];

    if (!isset($member['mb_id']) || !$member['mb_id']) {
        return $auth_info; // 로그인하지 않았거나 회원 ID가 없으면 기본 정보 반환
    }

    $mb_id = sql_escape_string($member['mb_id']);
    $auth_info['mb_id'] = $member['mb_id'];
    $mb_level = (int)$member['mb_level'];

    // 1. 최고 관리자 (mb_level 10)
    if ($mb_level == DMK_MB_LEVEL_SUPER) {
        $auth_info['is_super'] = true;
        $auth_info['mb_type'] = DMK_MB_TYPE_NONE; // 최고관리자는 도매까 유형에 속하지 않음
        return $auth_info;
    }
    
    // 2. 총판 관리자 (mb_level 8)
    if ($mb_level == DMK_MB_LEVEL_DISTRIBUTOR) {
        $sql = " SELECT dt_id, dt_name FROM dmk_distributor WHERE dt_mb_id = '" . $mb_id . "' ";
        $row = sql_fetch($sql);
        if ($row) {
            $auth_info['mb_type'] = DMK_MB_TYPE_DISTRIBUTOR;
            $auth_info['dt_id'] = $row['dt_id'];
        }
        return $auth_info;
    }

    // 3. 대리점 관리자 (mb_level 6)
    if ($mb_level == DMK_MB_LEVEL_AGENCY) {
        $sql = " SELECT ag_id, ag_name, dt_id FROM dmk_agency WHERE ag_mb_id = '" . $mb_id . "' ";
        $row = sql_fetch($sql);
        if ($row) {
            $auth_info['mb_type'] = DMK_MB_TYPE_AGENCY;
            $auth_info['ag_id'] = $row['ag_id'];
            $auth_info['dt_id'] = $row['dt_id'];
        }
        return $auth_info;
    }

    // 4. 지점 관리자 (mb_level 4)
    if ($mb_level == DMK_MB_LEVEL_BRANCH) {
        $sql = " SELECT br_id, br_name, ag_id FROM dmk_branch WHERE br_mb_id = '" . $mb_id . "' ";
        $row = sql_fetch($sql);
        if ($row) {
            $auth_info['mb_type'] = DMK_MB_TYPE_BRANCH;
            $auth_info['br_id'] = $row['br_id'];
            $auth_info['ag_id'] = $row['ag_id'];
        }
        return $auth_info;
    }
    
    // 그 외 (mb_level 2 이하)는 일반 회원 또는 비회원
    return $auth_info;
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
    
    $menu_permissions = array(
        'distributor_list' => DMK_MB_LEVEL_DISTRIBUTOR,
        'agency_list' => DMK_MB_LEVEL_DISTRIBUTOR,
        'agency_form' => DMK_MB_LEVEL_DISTRIBUTOR,
        'branch_list' => DMK_MB_LEVEL_AGENCY,
        'branch_form' => DMK_MB_LEVEL_AGENCY,
        'item_list' => DMK_MB_LEVEL_BRANCH,
        'item_form' => DMK_MB_LEVEL_BRANCH,
        'order_list' => DMK_MB_LEVEL_BRANCH,
        'stock_list' => DMK_MB_LEVEL_BRANCH,
        'dmk_url' => DMK_MB_LEVEL_DISTRIBUTOR // URL 관리는 총판 이상만 접근 가능
    );
    
    if (!isset($menu_permissions[$menu_code])) {
        return false;
    }
    
    // 사용자의 레벨이 메뉴에 필요한 최소 레벨보다 높거나 같으면 접근 허용
    return $auth['mb_level'] >= $menu_permissions[$menu_code];
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
    $auth = dmk_get_admin_auth();
    
    if (!$auth) {
        return false;
    }
    
    // 최고 관리자는 모든 총판 수정 가능
    if ($auth['is_super']) {
        return true;
    }
    
    // 총판 관리자는 자신만 수정 가능
    if ($auth['mb_type'] === DMK_MB_TYPE_DISTRIBUTOR) {
        return $auth['mb_id'] === $distributor_mb_id;
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

// 실제 메뉴 권한을 체크하는 함수 (admin.head.php에서 사용될 것)
function dmk_auth_check_menu_display($menu_code, $menu_key) {
    global $is_admin, $auth;

    $dmk_auth = dmk_get_admin_auth();

    // 1. 최고관리자는 모든 메뉴 접근 가능
    if ($is_admin == 'super') {
        return true;
    }

    // 3. 도매까 메뉴 (190xxx) - 기존 메뉴 구조
    if (substr($menu_code, 0, 3) == '190') {
        return dmk_can_access_menu($menu_key);
    }

    // 4. 일반 그누보드/영카트 메뉴 (200xxx 등)
    // 도매까 관리자에게 허용된 일반 메뉴인지 확인
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

    if (!strstr($auth_str, $attr)) {
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
    
    // 기본값: 총판 소유
    $owner_info = [
        'owner_type' => DMK_OWNER_TYPE_DISTRIBUTOR,
        'owner_id' => ''
    ];
    
    // 최고 관리자나 총판 관리자인 경우 총판 소유로 설정
    if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        $owner_info['owner_type'] = DMK_OWNER_TYPE_DISTRIBUTOR;
        $owner_info['owner_id'] = $auth['dt_id'] ?: '';
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

?> 