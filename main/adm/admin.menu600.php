<?php
// 도매까 관리 메뉴 (menu600)
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 도매까 권한 확인
$dmk_auth = dmk_get_admin_auth();

// 도매까 관리 메뉴는 모든 도매까 관련 권한자가 볼 수 있음
// 최고관리자, 총판, 대리점, 지점 모두 접근 가능 (단, 기능별 권한은 별도 체크)
if (is_super_admin($member['mb_id']) || 
    ($dmk_auth && in_array($dmk_auth['mb_type'], [DMK_MB_TYPE_DISTRIBUTOR, DMK_MB_TYPE_AGENCY, DMK_MB_TYPE_BRANCH]))) {
    
    $menu['menu600'] = array(
        array('600000', '도매까 관리', G5_DMK_URL.'/adm', 'dmk_admin'),
        array('600100', '총판 관리', G5_DMK_URL.'/adm/distributor_admin/distributor_list.php', 'distributor_list'),
        array('600200', '대리점 관리', G5_DMK_URL.'/adm/agency_admin/agency_list.php', 'agency_list'),
        array('600300', '지점 관리', G5_DMK_URL.'/adm/branch_admin/branch_list.php', 'branch_list'),
        array('600400', '회원 관리', G5_DMK_URL.'/adm/member_admin/member_list.php', 'member_list'),
        array('600500', '상품 관리', G5_DMK_URL.'/adm/item_admin/item_list.php', 'item_list'),
        array('600600', '주문 관리', G5_DMK_URL.'/adm/order_admin/order_list.php', 'order_list'),
        array('600700', '정산 관리', G5_DMK_URL.'/adm/settlement_admin/settlement_list.php', 'settlement_list'),
        array('600800', '통계 관리', G5_DMK_URL.'/adm/stats_admin/stats_dashboard.php', 'stats_dashboard'),
    );
    
    // 지점 관리자 전용 메뉴 추가
    if ($dmk_auth && $dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        $branch_menus = array(
            array('600900', '지점 주문', G5_DMK_URL.'/adm/branch_admin/order_page.php', 'branch_order'),
            array('600910', '주문 내역', G5_DMK_URL.'/adm/branch_admin/orderlist.php', 'branch_orderlist'),
            array('600920', 'URL 관리', G5_DMK_URL.'/adm/branch_admin/url_management.php', 'branch_url'),
            array('600930', '지점 정보', G5_DMK_URL.'/adm/branch_admin/branch_info.php', 'branch_info'),
        );
        
        // 지점 전용 메뉴를 기존 메뉴에 추가
        $menu['menu600'] = array_merge($menu['menu600'], $branch_menus);
    }
    
} else {
    $menu['menu600'] = array(); // 권한이 없으면 빈 배열
}
?>