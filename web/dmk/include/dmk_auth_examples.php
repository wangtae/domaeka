<?php
/**
 * 도매까 통합 권한 시스템 사용 예시
 * 개발자를 위한 실제 사용 사례들
 */

if (!defined('_GNUBOARD_')) exit;

// 통합 권한 시스템 로드
require_once G5_PATH . '/dmk/include/dmk_unified_auth.php';

/**
 * 예시 1: 기본 권한 체크 (모든 파일 상단에서 사용)
 */
function example_basic_auth_check() {
    // 가장 간단한 방법: 현재 파일의 메뉴 코드 자동 감지하여 읽기 권한 체크
    dmk_quick_auth_check('r');
    
    // 또는 명시적으로 메뉴 코드 지정
    dmk_quick_auth_check('r', '190100'); // 총판관리 읽기 권한
}

/**
 * 예시 2: 상세 권한 체크
 */
function example_detailed_auth_check() {
    // 도매까 메뉴 권한 체크
    dmk_unified_auth_check('190100', 'r'); // 총판관리 읽기 권한
    dmk_unified_auth_check('190200', 'w'); // 대리점관리 쓰기 권한
    dmk_unified_auth_check('180300', 'd'); // 봇 스케줄링 삭제 권한
    
    // 기존 영카트 메뉴 권한 체크 (기존 방식과 동일)
    global $auth;
    dmk_unified_auth_check($auth['400100'] ?? '', 'r'); // 쇼핑몰설정 읽기 권한
}

/**
 * 예시 3: 조건부 권한 체크 (에러 메시지 반환)
 */
function example_conditional_auth_check() {
    // 권한 체크 후 에러 메시지 받기
    $error = dmk_unified_auth_check('190100', 'w', '190100', true);
    
    if ($error) {
        // 권한이 없는 경우 처리
        echo "<script>alert('{$error}'); history.back();</script>";
        exit;
    }
    
    // 권한이 있는 경우 계속 진행
    echo "총판관리 수정 권한이 있습니다.";
}

/**
 * 예시 4: 메뉴별 권한 체크
 */
function example_menu_auth_check() {
    global $auth;
    
    // 도매까 메뉴
    dmk_unified_auth_check_menu(null, '190100', 'r'); // 총판관리
    dmk_unified_auth_check_menu(null, '180200', 'w'); // 봇 클라이언트 관리
    
    // 기존 영카트 메뉴
    dmk_unified_auth_check_menu($auth, '400100', 'r'); // 쇼핑몰설정
}

/**
 * 예시 5: 실제 파일에서의 사용 패턴
 */

/*
=== 총판관리 목록 페이지 (distributor_list.php) ===

<?php
include_once './_common.php';
include_once G5_PATH . '/dmk/include/dmk_unified_auth.php';

// 권한 체크 (메뉴 코드 자동 감지 또는 명시적 지정)
dmk_quick_auth_check('r', '190100');

// 또는 상세 체크
dmk_unified_auth_check('190100', 'r', '190100');

// 페이지 로직 계속...
?>
*/

/*
=== 대리점 등록 폼 처리 (agency_form_update.php) ===

<?php
include_once './_common.php';
include_once G5_PATH . '/dmk/include/dmk_unified_auth.php';

// 쓰기 권한 체크
dmk_quick_auth_check('w', '190200');

// 삭제 작업인 경우 삭제 권한 추가 체크
if ($_POST['mode'] == 'delete') {
    dmk_quick_auth_check('d', '190200');
}

// 폼 처리 로직...
?>
*/

/*
=== AJAX 요청 처리 (ajax_get_agencies.php) ===

<?php
include_once './_common.php';
include_once G5_PATH . '/dmk/include/dmk_unified_auth.php';

// AJAX 요청이므로 에러 메시지 반환 방식 사용
$error = dmk_unified_auth_check('190200', 'r', '190200', true);
if ($error) {
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

// AJAX 처리 로직...
?>
*/

/*
=== 기존 영카트 메뉴 개조 (itemlist.php) ===

<?php
include_once './_common.php';
include_once G5_PATH . '/dmk/include/dmk_unified_auth.php';

// 기존 영카트 권한 체크 (기존 방식과 동일하게 작동)
global $auth;
dmk_unified_auth_check_menu($auth, '400300', 'r');

// 도매까 확장 로직이 있는 경우 추가 체크
if (function_exists('dmk_is_dmk_admin') && dmk_is_dmk_admin()) {
    // 도매까 관리자 전용 기능
}

// 상품 목록 로직...
?>
*/

/**
 * 예시 6: 사용자 계층 정보 활용 (main/sub 관리자 구분)
 */
function example_user_hierarchy() {
    $user_hierarchy = dmk_get_user_hierarchy();
    
    if ($user_hierarchy['is_super']) {
        echo "본사 관리자입니다. 모든 권한이 있습니다.";
    } elseif ($user_hierarchy['is_main_admin']) {
        $user_type_kr = dmk_get_user_type_korean($user_hierarchy['user_type']);
        echo "{$user_type_kr} 메인관리자입니다. 접근 가능한 메뉴에서 최고 권한을 가집니다.";
    } elseif ($user_hierarchy['is_sub_admin']) {
        $user_type_kr = dmk_get_user_type_korean($user_hierarchy['user_type']);
        echo "{$user_type_kr} 서브관리자입니다. g5_auth 테이블에 정의된 메뉴별 권한만 사용할 수 있습니다.";
    } else {
        echo "일반 사용자입니다.";
    }
}

/**
 * 예시 7: main/sub 관리자별 권한 체크 차이
 */
function example_main_sub_admin_auth() {
    $user_hierarchy = dmk_get_user_hierarchy();
    $menu_code = '190100'; // 총판관리
    
    if ($user_hierarchy['is_main_admin']) {
        // main 관리자: dmk_global_settings.php 기반 권한 체크
        echo "main 관리자 - dmk_global_settings.php에서 허용된 메뉴인지 확인<br>";
        dmk_quick_auth_check('r', $menu_code);
        
    } elseif ($user_hierarchy['is_sub_admin']) {
        // sub 관리자: g5_auth 테이블 기반 세부 권한 체크
        echo "sub 관리자 - g5_auth 테이블에서 해당 메뉴의 r/w/d 권한 확인<br>";
        dmk_quick_auth_check('r', $menu_code); // 이 함수 내부에서 자동으로 sub 관리자 로직 적용
        
        // sub 관리자는 세부 권한별로 체크 가능
        $error_read = dmk_unified_auth_check($menu_code, 'r', $menu_code, true);
        $error_write = dmk_unified_auth_check($menu_code, 'w', $menu_code, true);
        $error_delete = dmk_unified_auth_check($menu_code, 'd', $menu_code, true);
        
        echo "읽기 권한: " . ($error_read ? "없음" : "있음") . "<br>";
        echo "쓰기 권한: " . ($error_write ? "없음" : "있음") . "<br>";
        echo "삭제 권한: " . ($error_delete ? "없음" : "있음") . "<br>";
    }
}

/**
 * 예시 7: 메뉴 표시 권한 체크 (admin.head.php용)
 */
function example_menu_display_check() {
    $menus_to_check = ['190100', '190200', '190300', '180100', '180200'];
    
    foreach ($menus_to_check as $menu_code) {
        $error = dmk_unified_auth_check($menu_code, 'r', $menu_code, true);
        
        if (!$error) {
            // 권한이 있으면 메뉴 표시
            echo "<li><a href='menu_{$menu_code}.php'>메뉴 {$menu_code}</a></li>";
        }
        // 권한이 없으면 메뉴 숨김
    }
}