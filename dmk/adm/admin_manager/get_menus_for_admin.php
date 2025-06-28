<?php
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

header('Content-Type: application/json; charset=utf-8');

$mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';

if (!$mb_id) {
    echo json_encode(['error' => '관리자 ID가 필요합니다.']);
    exit;
}

// 관리자 정보 조회
$sql = " SELECT mb_level, dmk_mb_type, dmk_admin_type FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
$admin = sql_fetch($sql);

if (!$admin) {
    echo json_encode(['error' => '존재하지 않는 관리자입니다.']);
    exit;
}

// 관리자의 계층 타입 결정
$user_type = '';
switch ($admin['dmk_mb_type']) {
    case DMK_MB_TYPE_DISTRIBUTOR:
        $user_type = 'distributor';
        break;
    case DMK_MB_TYPE_AGENCY:
        $user_type = 'agency';
        break;
    case DMK_MB_TYPE_BRANCH:
        $user_type = 'branch';
        break;
    default:
        echo json_encode(['error' => '유효하지 않은 관리자 타입입니다.']);
        exit;
}

// 도매까 전역 설정에서 해당 계층의 허용 메뉴 가져오기
global $DMK_MENU_CONFIG;

if (!isset($DMK_MENU_CONFIG[$user_type])) {
    echo json_encode(['error' => '해당 계층의 메뉴 설정을 찾을 수 없습니다.']);
    exit;
}

$allowed_menus = $DMK_MENU_CONFIG[$user_type]['allowed_menus'];
$sub_menus = $DMK_MENU_CONFIG[$user_type]['sub_menus'];

// 도매까 메뉴만 필터링 (190xxx)
$dmk_menus = array_filter($allowed_menus, function($menu_code) {
    return substr($menu_code, 0, 3) === '190';
});

// 메뉴 정보 구성
$menus = [];
foreach ($dmk_menus as $menu_code) {
    $menu_name = '';
    
    // sub_menus에서 메뉴 이름 찾기
    foreach ($sub_menus as $parent_code => $children) {
        if (isset($children[$menu_code])) {
            $menu_name = $children[$menu_code];
            break;
        }
    }
    
    // 기본 메뉴 이름 설정 (sub_menus에 없는 경우)
    if (!$menu_name) {
        switch ($menu_code) {
            case '190000': $menu_name = '프랜차이즈 관리'; break;
            case '190100': $menu_name = '총판관리'; break;
            case '190200': $menu_name = '대리점관리'; break;
            case '190300': $menu_name = '지점관리'; break;
            case '190400': $menu_name = '통계분석'; break;
            case '190600': $menu_name = '서브관리자관리'; break;
            case '190700': $menu_name = '서브관리자권한설정'; break;
            case '190800': $menu_name = '메뉴권한설정'; break;
            default: $menu_name = '알 수 없는 메뉴'; break;
        }
    }
    
    $menus[] = [
        'code' => $menu_code,
        'name' => $menu_name
    ];
}

// 메뉴 코드 순으로 정렬
usort($menus, function($a, $b) {
    return strcmp($a['code'], $b['code']);
});

echo json_encode($menus);
?>