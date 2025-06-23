<?php
// 도매까 관리 메뉴 정의
$menu['menu250'] = array(
    array('250000', '도매까관리', G5_URL . '/dmk/test_menu.php', 'dmk'),
    array('250100', '대리점관리', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
    array('250200', '지점관리', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
    array('250300', '상품관리', G5_ADMIN_URL . '/shop_admin/itemlist.php', 'dmk_item'),
    array('250400', '주문관리', G5_ADMIN_URL . '/shop_admin/orderlist.php', 'dmk_order'),
    array('250500', '재고관리', G5_ADMIN_URL . '/shop_admin/itemstock.php', 'dmk_stock')
);

// 도매까 권한 라이브러리 로드 (안전하게 체크)
$dmk_lib_path = G5_PATH . '/main/dmk/adm/lib/admin.auth.lib.php';
if (file_exists($dmk_lib_path)) {
    include_once $dmk_lib_path;
    
    // 도매까 권한에 따른 메뉴 필터링
    if (function_exists('dmk_get_admin_auth')) {
        $dmk_auth = dmk_get_admin_auth();
        
        if ($dmk_auth && isset($dmk_auth['mb_type'])) {
            $filtered_menu = array();
            $filtered_menu[] = $menu['menu250'][0]; // 도매까관리 메인
            
            // 권한에 따른 메뉴 추가
            switch ($dmk_auth['mb_type']) {
                case 1: // 본사 (최고 관리자 또는 본사 관리자)
                    $filtered_menu[] = $menu['menu250'][1]; // 대리점관리
                    $filtered_menu[] = $menu['menu250'][2]; // 지점관리
                    $filtered_menu[] = $menu['menu250'][3]; // 상품관리
                    $filtered_menu[] = $menu['menu250'][4]; // 주문관리
                    $filtered_menu[] = $menu['menu250'][5]; // 재고관리
                    break;
                    
                case 2: // 대리점
                    $filtered_menu[] = $menu['menu250'][2]; // 지점관리
                    $filtered_menu[] = $menu['menu250'][3]; // 상품관리
                    $filtered_menu[] = $menu['menu250'][4]; // 주문관리
                    $filtered_menu[] = $menu['menu250'][5]; // 재고관리
                    break;
                    
                case 3: // 지점
                    $filtered_menu[] = $menu['menu250'][3]; // 상품관리
                    $filtered_menu[] = $menu['menu250'][4]; // 주문관리
                    $filtered_menu[] = $menu['menu250'][5]; // 재고관리
                    break;
            }
            
            $menu['menu250'] = $filtered_menu;
        }
    }
}
?> 