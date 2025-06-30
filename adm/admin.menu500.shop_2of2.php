<?php
include_once(G5_PATH.'/dmk/dmk_global_settings.php');

if (!defined('G5_USE_SHOP') || !G5_USE_SHOP) {
    return;
}

// 현재 사용자 타입 가져오기
$current_user_type = dmk_get_current_user_type();

// 동적 메뉴 타이틀 가져오기 (DMK 설정에서)
$menu_title = dmk_get_menu_title('500000', $current_user_type);
if (!$menu_title) {
    $menu_title = '쇼핑몰현황/기타'; // 기본 타이틀
}

$menu['menu500'] = array();

// 메인 메뉴
$menu['menu500'][] = array('500000', $menu_title, G5_ADMIN_URL . '/shop_admin/itemsellrank.php', 'shop_stats');

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('500110', $current_user_type)) {
    $menu['menu500'][] = array('500110', '매출현황 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/sale1.php', 'sst_order_stats');
}
if (dmk_is_menu_allowed('500100', $current_user_type)) {
    $menu['menu500'][] = array('500100', '상품판매순위 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/itemsellrank.php', 'sst_rank');
}
if (dmk_is_menu_allowed('500120', $current_user_type)) {
    $menu['menu500'][] = array('500120', '주문내역출력 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/orderprint.php', 'sst_print_order', 1);
}

// 기존 영카트 메뉴들 (수정하지 않음)
$menu['menu500'][] = array('500400', '재입고SMS알림', G5_ADMIN_URL . '/shop_admin/itemstocksms.php', 'sst_stock_sms', 1);
$menu['menu500'][] = array('500300', '이벤트관리', G5_ADMIN_URL . '/shop_admin/itemevent.php', 'scf_event');
$menu['menu500'][] = array('500310', '이벤트일괄처리', G5_ADMIN_URL . '/shop_admin/itemeventlist.php', 'scf_event_mng');
$menu['menu500'][] = array('500500', '배너관리', G5_ADMIN_URL . '/shop_admin/bannerlist.php', 'scf_banner', 1);

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('500140', $current_user_type)) {
    $menu['menu500'][] = array('500140', '보관함현황 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/wishlist.php', 'sst_wish');
}

// 기존 영카트 메뉴 (수정하지 않음)
$menu['menu500'][] = array('500210', '가격비교사이트', G5_ADMIN_URL . '/shop_admin/price.php', 'sst_compare', 1);
