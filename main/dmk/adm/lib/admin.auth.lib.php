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
 * 도매까 관리자 유형 상수 정의
 */
define('DMK_MB_TYPE_NORMAL', 0);     // 일반 회원
define('DMK_MB_TYPE_DISTRIBUTOR', 1); // 총판 관리자 (구 본사 관리자)
define('DMK_MB_TYPE_AGENCY', 2);     // 대리점 관리자
define('DMK_MB_TYPE_BRANCH', 3);     // 지점 관리자

/**
 * 도매까 상품 소유 계층 상수 정의
 */
define('DMK_OWNER_TYPE_DISTRIBUTOR', 'DISTRIBUTOR'); // 총판 (구 본사)
define('DMK_OWNER_TYPE_AGENCY', 'AGENCY');           // 대리점
define('DMK_OWNER_TYPE_BRANCH', 'BRANCH');           // 지점

/**
 * 현재 로그인한 관리자의 도매까 권한 정보를 가져옵니다.
 * 
 * @return array|false 권한 정보 배열 또는 false (권한 없음)
 */
function dmk_get_admin_auth() {
    global $member, $is_admin;
    
    if (!$is_admin || !$member['mb_id']) {
        return false;
    }
    
    // 그누보드 최고 관리자 (영카트 최고 관리자)는 모든 권한 보유
    if (is_super_admin($member['mb_id'])) {
        return array(
            'mb_id' => $member['mb_id'],
            'mb_type' => DMK_MB_TYPE_DISTRIBUTOR, // 영카트 최고 관리자는 총판 관리자 권한을 가집니다.
            'ag_id' => null,
            'br_id' => null,
            'is_super' => true,
            'permissions' => array('all')
        );
    }
    
    return array(
        'mb_id' => $member['mb_id'],
        'mb_type' => (int)$member['dmk_mb_type'],
        'ag_id' => $member['dmk_ag_id'],
        'br_id' => $member['dmk_br_id'],
        'is_super' => false,
        'permissions' => dmk_get_permissions_by_type((int)$member['dmk_mb_type'])
    );
}

/**
 * 관리자 유형에 따른 권한 목록을 반환합니다.
 * 
 * @param int $mb_type 관리자 유형
 * @return array 권한 목록
 */
function dmk_get_permissions_by_type($mb_type) {
    switch ($mb_type) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return array('agency_manage', 'branch_manage', 'item_all', 'order_all', 'stock_all');
            
        case DMK_MB_TYPE_AGENCY:
            return array('branch_manage', 'item_agency', 'order_agency', 'stock_agency');
            
        case DMK_MB_TYPE_BRANCH:
            return array('item_branch', 'order_branch', 'stock_branch');
            
        default:
            return array();
    }
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
    
    if (!$auth || $auth['is_super']) {
        return ''; // 모든 상품 조회 가능 (영카트 최고 관리자)
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return ''; // 총판 관리자는 모든 상품 조회 가능
            
        case DMK_MB_TYPE_AGENCY:
            return " AND (dmk_it_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "' OR (dmk_it_owner_type = 'AGENCY' AND dmk_it_owner_id = '" . sql_escape_string($auth['ag_id']) . "'))";
            
        case DMK_MB_TYPE_BRANCH:
            // 지점은 총판, 소속 대리점, 자신의 상품만 조회 가능
            $ag_id = dmk_get_branch_agency_id($auth['br_id']);
            $condition = " AND (dmk_it_owner_type = '" . DMK_OWNER_TYPE_DISTRIBUTOR . "'";
            if ($ag_id) {
                $condition .= " OR (dmk_it_owner_type = 'AGENCY' AND dmk_it_owner_id = '" . sql_escape_string($ag_id) . "')";
            }
            $condition .= " OR (dmk_it_owner_type = 'BRANCH' AND dmk_it_owner_id = '" . sql_escape_string($auth['br_id']) . "'))";
            return $condition;
            
        default:
            return ' AND 1=0'; // 접근 차단
    }
}

/**
 * 주문 조회를 위한 WHERE 조건을 생성합니다.
 * 
 * @return string SQL WHERE 조건
 */
function dmk_get_order_where_condition() {
    $auth = dmk_get_admin_auth();
    
    if (!$auth || $auth['is_super']) {
        return ''; // 모든 주문 조회 가능 (영카트 최고 관리자)
    }
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return ''; // 총판 관리자는 모든 주문 조회 가능
            
        case DMK_MB_TYPE_AGENCY:
            // 대리점은 소속 지점들의 주문만 조회 가능
            $branch_ids = dmk_get_agency_branch_ids($auth['ag_id']);
            if (empty($branch_ids)) {
                return ' AND 1=0'; // 소속 지점이 없으면 조회 불가
            }
            return " AND dmk_od_br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "')";
            
        case DMK_MB_TYPE_BRANCH:
            return " AND dmk_od_br_id = '" . sql_escape_string($auth['br_id']) . "'";
            
        default:
            return ' AND 1=0'; // 접근 차단
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
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_DISTRIBUTOR;
            
        case DMK_MB_TYPE_AGENCY:
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_AGENCY && 
                   $item['dmk_it_owner_id'] === $auth['ag_id'];
            
        case DMK_MB_TYPE_BRANCH:
            return $item['dmk_it_owner_type'] === DMK_OWNER_TYPE_BRANCH && 
                   $item['dmk_it_owner_id'] === $auth['br_id'];
            
        default:
            return false;
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
    
    switch ($auth['mb_type']) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            return array('owner_type' => DMK_OWNER_TYPE_DISTRIBUTOR, 'owner_id' => null);
            
        case DMK_MB_TYPE_AGENCY:
            return array('owner_type' => DMK_OWNER_TYPE_AGENCY, 'owner_id' => $auth['ag_id']);
            
        case DMK_MB_TYPE_BRANCH:
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
        'agency_list' => array(DMK_MB_TYPE_DISTRIBUTOR),
        'agency_form' => array(DMK_MB_TYPE_DISTRIBUTOR),
        'branch_list' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY),
        'branch_form' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY),
        'item_list' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH),
        'item_form' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH),
        'order_list' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH),
        'stock_list' => array(DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH)
    );
    
    if (!isset($menu_permissions[$menu_code])) {
        return false;
    }
    
    return in_array($auth['mb_type'], $menu_permissions[$menu_code]);
}

/**
 * 관리자의 접근 권한을 확인하고, 권한이 없는 경우 접근을 차단합니다.
 *
 * @param int $required_type 이 페이지에 접근하기 위해 필요한 관리자 유형 (DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH)
 */
function dmk_authenticate_admin($required_type) {
    global $g5; // 그누보드 전역 변수 $g5를 사용합니다.

    $auth = dmk_get_admin_auth();

    // 1. 관리자 로그인 여부 확인 및 권한 정보 가져오기
    if (!$auth) {
        // 관리자가 아니거나 로그인하지 않은 경우, 로그인 페이지로 리디렉션
        alert("관리자 권한이 필요합니다.", G5_ADMIN_URL);
        exit;
    }

    // 2. 최고 관리자 (영카트 최고 관리자)는 모든 관리자 페이지에 접근 가능
    if ($auth['is_super']) {
        return true;
    }

    // 3. 필요한 관리자 유형에 따른 접근 권한 확인
    $access_granted = false;

    switch ($required_type) {
        case DMK_MB_TYPE_DISTRIBUTOR:
            // 총판 관리자 페이지는 총판 관리자만 접근 가능 (슈퍼 관리자는 위에서 처리됨)
            if ($auth['mb_type'] === DMK_MB_TYPE_DISTRIBUTOR) {
                $access_granted = true;
            }
            break;
        case DMK_MB_TYPE_AGENCY:
            // 대리점 관리자 페이지는 총판 또는 대리점 관리자만 접근 가능
            if ($auth['mb_type'] === DMK_MB_TYPE_DISTRIBUTOR || $auth['mb_type'] === DMK_MB_TYPE_AGENCY) {
                $access_granted = true;
            }
            break;
        case DMK_MB_TYPE_BRANCH:
            // 지점 관리자 페이지는 총판, 대리점 또는 지점 관리자만 접근 가능
            if ($auth['mb_type'] === DMK_MB_TYPE_DISTRIBUTOR || $auth['mb_type'] === DMK_MB_TYPE_AGENCY || $auth['mb_type'] === DMK_MB_TYPE_BRANCH) {
                $access_granted = true;
            }
            break;
        default:
            // 정의되지 않은 유형은 접근 차단
            $access_granted = false;
            break;
    }

    if (!$access_granted) {
        alert("접근 권한이 없습니다.", G5_ADMIN_URL);
        exit;
    }

    return true;
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
        $sql = " SELECT dmk_mb_type FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
        $row = sql_fetch($sql);
        
        if ($row) {
            return (int)$row['dmk_mb_type'] === DMK_MB_TYPE_DISTRIBUTOR;
        } else {
            return false;
        }
    }
    
    // 현재 로그인한 관리자의 경우
    return (int)$member['dmk_mb_type'] === DMK_MB_TYPE_DISTRIBUTOR;
}

?> 