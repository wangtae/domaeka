<?php
/**
 * 도매까 통합 권한 시스템
 * 기존 영카트 권한 체크 함수를 확장하여 도매까 4단계 권한 시스템과 통합
 */

if (!defined('_GNUBOARD_')) exit;

// 도매까 전역 설정 로드
require_once G5_PATH . '/dmk/dmk_global_settings.php';

/**
 * 도매까 메뉴인지 판별
 * dmk_global_settings.php에 정의된 메뉴들을 도매까 메뉴로 간주
 * 
 * @param string $menu_code 메뉴 코드 (예: '190100', '180200')
 * @return bool 도매까 메뉴 여부
 */
function dmk_is_dmk_menu($menu_code) {
    global $DMK_MENU_CONFIG;
    
    if (!$menu_code) return false;
    
    // 메뉴 코드에서 앞 3자리 추출 (190100 -> 190)
    $main_menu = substr($menu_code, 0, 3);
    
    // 도매까 전용 메뉴 코드들 (dmk_global_settings.php 기반)
    $dmk_menu_codes = ['180', '190']; // 봇 관리, 프랜차이즈 관리
    
    // 또는 DMK_MENU_CONFIG에서 사용되는 모든 메뉴 코드 확인
    if (isset($DMK_MENU_CONFIG)) {
        foreach ($DMK_MENU_CONFIG as $user_type => $config) {
            if (isset($config['allowed_menus'])) {
                foreach ($config['allowed_menus'] as $allowed_menu) {
                    $allowed_main_menu = substr($allowed_menu, 0, 3);
                    if ($allowed_main_menu === $main_menu) {
                        return true;
                    }
                }
            }
        }
    }
    
    return in_array($main_menu, $dmk_menu_codes);
}

/**
 * 현재 사용자의 도매까 계층 타입 조회 (main/sub 관리자 구분 포함)
 * 
 * @return array 사용자 권한 정보
 */
function dmk_get_user_hierarchy() {
    global $member, $is_admin;
    
    if ($is_admin == 'super') {
        return [
            'user_type' => 'super',
            'is_super' => true,
            'is_dmk_admin' => true,
            'is_main_admin' => true,
            'is_sub_admin' => false
        ];
    }
    
    if (!$member['mb_id']) {
        return [
            'user_type' => null,
            'is_super' => false,
            'is_dmk_admin' => false,
            'is_main_admin' => false,
            'is_sub_admin' => false
        ];
    }
    
    // 도매까 관리자 정보 조회
    $sql = "SELECT dmk_mb_type, dmk_owner_id, dmk_is_main FROM {$GLOBALS['g5']['member_table']} WHERE mb_id = '{$member['mb_id']}'";
    $user_info = sql_fetch($sql);
    
    $user_type = $user_info['dmk_mb_type'] ?? null;
    $is_main = ($user_info['dmk_is_main'] ?? 'N') === 'Y';
    
    $is_dmk_admin = in_array($user_type, ['distributor', 'agency', 'branch']);
    
    return [
        'user_type' => $user_type,
        'owner_id' => $user_info['dmk_owner_id'] ?? null,
        'is_super' => false,
        'is_dmk_admin' => $is_dmk_admin,
        'is_main_admin' => $is_main && $is_dmk_admin,
        'is_sub_admin' => !$is_main && $is_dmk_admin
    ];
}

/**
 * 도매까 메뉴 접근 권한 체크 (main/sub 관리자 구분 적용)
 * 
 * @param string $menu_code 메뉴 코드
 * @param string $attr 권한 속성 ('r', 'w', 'd')
 * @return bool 권한 있음 여부
 */
function dmk_check_menu_access($menu_code, $attr = 'r') {
    global $DMK_MENU_CONFIG;
    
    $user_hierarchy = dmk_get_user_hierarchy();
    
    // 본사(super)는 모든 권한
    if ($user_hierarchy['is_super']) {
        return true;
    }
    
    // 도매까 관리자가 아니면 접근 불가
    if (!$user_hierarchy['is_dmk_admin']) {
        return false;
    }
    
    $user_type = $user_hierarchy['user_type'];
    
    // main 관리자의 경우: dmk_global_settings.php 기반 권한 체크
    if ($user_hierarchy['is_main_admin']) {
        // 사용자 타입별 허용 메뉴 확인
        if (!isset($DMK_MENU_CONFIG[$user_type]['allowed_menus'])) {
            return false;
        }
        
        $allowed_menus = $DMK_MENU_CONFIG[$user_type]['allowed_menus'];
        
        // 메뉴 코드가 허용 목록에 있으면 해당 계층의 최고관리자 역할
        return in_array($menu_code, $allowed_menus);
    }
    
    // sub 관리자의 경우: g5_auth 테이블 기반 세부 권한 체크
    if ($user_hierarchy['is_sub_admin']) {
        return dmk_check_sub_admin_auth($menu_code, $attr);
    }
    
    return false;
}

/**
 * sub 관리자의 g5_auth 테이블 기반 권한 체크
 * 
 * @param string $menu_code 메뉴 코드
 * @param string $attr 권한 속성 ('r', 'w', 'd')
 * @return bool 권한 있음 여부
 */
function dmk_check_sub_admin_auth($menu_code, $attr = 'r') {
    global $member;
    
    if (!$member['mb_id']) {
        return false;
    }
    
    // g5_auth 테이블에서 해당 사용자의 메뉴별 권한 조회
    $sql = "SELECT au_auth FROM {$GLOBALS['g5']['auth_table']} 
            WHERE mb_id = '{$member['mb_id']}' AND au_menu = '{$menu_code}'";
    $auth_info = sql_fetch($sql);
    
    if (!$auth_info || !$auth_info['au_auth']) {
        return false;
    }
    
    $auth = $auth_info['au_auth'];
    $attr = strtolower($attr);
    
    // 권한 문자열에서 요청한 권한이 있는지 확인
    return strpos($auth, $attr) !== false;
}

/**
 * 통합 권한 체크 함수 (main/sub 관리자 구분 적용)
 * 기존 auth_check() 함수를 확장하여 도매까 권한 시스템과 통합
 * 
 * @param mixed $auth 권한 정보 (기존 영카트) 또는 메뉴 코드 (도매까)
 * @param string $attr 권한 속성 ('r', 'w', 'd')
 * @param string $menu_code 메뉴 코드 (옵션, 도매까 메뉴 판별용)
 * @param bool $return 에러 메시지 반환 여부
 * @return mixed 권한 없으면 에러 메시지 또는 alert, 있으면 null
 */
function dmk_unified_auth_check($auth, $attr, $menu_code = null, $return = false) {
    global $is_admin;
    
    // 최고관리자는 모든 권한
    if ($is_admin == 'super') {
        return null;
    }
    
    $user_hierarchy = dmk_get_user_hierarchy();
    
    // 메뉴 코드가 제공된 경우 도매까 메뉴인지 확인
    $is_dmk_menu = false;
    if ($menu_code) {
        $is_dmk_menu = dmk_is_dmk_menu($menu_code);
    } elseif (is_string($auth) && preg_match('/^\d{6}$/', $auth)) {
        // auth가 6자리 숫자인 경우 메뉴 코드로 간주
        $menu_code = $auth;
        $is_dmk_menu = dmk_is_dmk_menu($menu_code);
    }
    
    if ($is_dmk_menu) {
        // 도매까 메뉴 권한 체크 (main/sub 관리자 구분 적용)
        if (!dmk_check_menu_access($menu_code, $attr)) {
            $msg = dmk_get_auth_error_message_for_dmk($attr, $user_hierarchy);
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        }
    } else {
        // 기존 영카트 권한 체크 로직 (dmk_admin 추가 고려)
        if ($is_admin == 'dmk_admin') {
            // dmk_admin인 경우 도매까 main 관리자로 간주
            if ($user_hierarchy['is_main_admin']) {
                return null; // main 관리자는 접근 가능한 영카트 메뉴에서 최고권한
            }
        }
        
        if (!trim($auth)) {
            $msg = '이 메뉴에는 접근 권한이 없습니다.\\n\\n접근 권한은 최고관리자만 부여할 수 있습니다.';
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        }
        
        $attr = strtolower($attr);
        
        if (!strstr($auth, $attr)) {
            $msg = dmk_get_auth_error_message($attr);
            if ($return) {
                return $msg;
            } else {
                alert($msg);
            }
        }
    }
    
    return null;
}

/**
 * 권한 에러 메시지 생성
 * 
 * @param string $attr 권한 속성
 * @return string 에러 메시지
 */
function dmk_get_auth_error_message($attr) {
    switch (strtolower($attr)) {
        case 'r':
            return '읽을 권한이 없습니다.';
        case 'w':
            return '입력, 추가, 생성, 수정 권한이 없습니다.';
        case 'd':
            return '삭제 권한이 없습니다.';
        default:
            return '속성이 잘못 되었습니다.';
    }
}

/**
 * 도매까 전용 권한 에러 메시지 생성 (main/sub 관리자 구분)
 * 
 * @param string $attr 권한 속성
 * @param array $user_hierarchy 사용자 계층 정보
 * @return string 에러 메시지
 */
function dmk_get_auth_error_message_for_dmk($attr, $user_hierarchy) {
    $base_msg = dmk_get_auth_error_message($attr);
    
    if ($user_hierarchy['is_sub_admin']) {
        $user_type_kr = dmk_get_user_type_korean($user_hierarchy['user_type']);
        return $base_msg . "\\n\\n서브관리자는 {$user_type_kr} 메인관리자가 부여한 메뉴별 권한이 있어야 합니다.";
    } elseif ($user_hierarchy['is_main_admin']) {
        $user_type_kr = dmk_get_user_type_korean($user_hierarchy['user_type']);
        return $base_msg . "\\n\\n{$user_type_kr} 메인관리자로서 해당 메뉴에 접근할 권한이 없습니다.";
    } else {
        return $base_msg . "\\n\\n도매까 시스템에 접근할 권한이 없습니다.";
    }
}

/**
 * 사용자 타입의 한글명 반환
 * 
 * @param string $user_type 사용자 타입
 * @return string 한글명
 */
function dmk_get_user_type_korean($user_type) {
    switch ($user_type) {
        case 'distributor':
            return '총판';
        case 'agency':
            return '대리점';
        case 'branch':
            return '지점';
        default:
            return '관리자';
    }
}

/**
 * 통합 메뉴별 권한 체크 함수
 * 기존 auth_check_menu() 함수를 확장하여 도매까 시스템과 통합
 * 
 * @param mixed $auth 권한 배열 (기존 영카트) 또는 메뉴 코드 (도매까)
 * @param string $sub_menu 서브메뉴 코드
 * @param string $attr 권한 속성
 * @param bool $return 에러 메시지 반환 여부
 * @return mixed
 */
function dmk_unified_auth_check_menu($auth, $sub_menu, $attr, $return = false) {
    // 도매까 메뉴인 경우 sub_menu를 메뉴 코드로 사용
    if (dmk_is_dmk_menu($sub_menu)) {
        return dmk_unified_auth_check($sub_menu, $attr, $sub_menu, $return);
    }
    
    // 기존 영카트 메뉴인 경우
    if (is_array($auth) && isset($auth[$sub_menu])) {
        return dmk_unified_auth_check($auth[$sub_menu], $attr, null, $return);
    }
    
    // 권한 정보가 없으면 접근 불가
    return dmk_unified_auth_check('', $attr, null, $return);
}

/**
 * 현재 메뉴 코드 자동 감지
 * 파일명이나 URL 파라미터에서 메뉴 코드 추출
 * 
 * @return string|null 메뉴 코드
 */
function dmk_detect_current_menu_code() {
    // URL 파라미터에서 메뉴 코드 확인
    if (isset($_GET['menu']) && preg_match('/^\d{6}$/', $_GET['menu'])) {
        return $_GET['menu'];
    }
    
    // 파일명에서 메뉴 코드 패턴 추출 시도
    $script_name = basename($_SERVER['SCRIPT_NAME'], '.php');
    
    // 도매까 파일명 패턴 매칭 (예: distributor_list, agency_form 등)
    $dmk_patterns = [
        '/^distributor_/' => '190100',
        '/^agency_/' => '190200', 
        '/^branch_/' => '190300',
        '/^bot_server/' => '180100',
        '/^bot_client/' => '180200',
        '/^bot_schedule/' => '180300',
        '/^bot_instant/' => '180400',
        '/^bot_chat/' => '180500'
    ];
    
    foreach ($dmk_patterns as $pattern => $menu_code) {
        if (preg_match($pattern, $script_name)) {
            return $menu_code;
        }
    }
    
    return null;
}

/**
 * 간편 권한 체크 함수
 * 현재 페이지의 메뉴 코드를 자동 감지하여 권한 체크
 * 
 * @param string $attr 권한 속성 ('r', 'w', 'd')
 * @param string $menu_code 메뉴 코드 (선택적, 없으면 자동 감지)
 */
function dmk_quick_auth_check($attr = 'r', $menu_code = null) {
    if (!$menu_code) {
        $menu_code = dmk_detect_current_menu_code();
    }
    
    if ($menu_code) {
        dmk_unified_auth_check($menu_code, $attr, $menu_code);
    }
}