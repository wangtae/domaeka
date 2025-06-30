<?php
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
include_once(G5_PATH.'/dmk/dmk_global_settings.php');

if (!defined('G5_USE_SHOP') || !G5_USE_SHOP) {
    return;
}

$menu['menu400'] = array();

// 현재 사용자 타입 가져오기
$current_user_type = dmk_get_current_user_type();

// 동적 메뉴 타이틀 가져오기 (DMK 설정에서)
$menu_title = dmk_get_menu_title('400000', $current_user_type);
if (!$menu_title) {
    $menu_title = '쇼핑몰관리'; // 기본 타이틀
}

// 최고관리자 또는 총판 관리자만 접근 가능한 쇼핑몰 메뉴
if (is_super_admin($member['mb_id']) || dmk_is_distributor($member['mb_id'])) {
    $menu['menu400'][] = array('400000', $menu_title, G5_ADMIN_URL . '/shop_admin/', 'shop_config');
    $menu['menu400'][] = array('400010', '쇼핑몰현황', G5_ADMIN_URL . '/shop_admin/', 'shop_index');
    $menu['menu400'][] = array('400100', '쇼핑몰설정', G5_ADMIN_URL . '/shop_admin/configform.php', 'scf_config');
}

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('400400', $current_user_type)) {
    $menu['menu400'][] = array('400400', '주문관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/orderlist.php', 'scf_order', 1);
}

// 기존 영카트 메뉴 (수정하지 않음)
$menu['menu400'][] = array('400440', '개인결제관리', G5_ADMIN_URL . '/shop_admin/personalpaylist.php', 'scf_personalpay', 1);

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('400200', $current_user_type)) {
    $menu['menu400'][] = array('400200', '분류관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/categorylist.php', 'scf_cate');
}
if (dmk_is_menu_allowed('400300', $current_user_type)) {
    $menu['menu400'][] = array('400300', '상품관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/itemlist.php', 'scf_item');
}

// 기존 영카트 메뉴들 (수정하지 않음)
$menu['menu400'][] = array('400660', '상품문의', G5_ADMIN_URL . '/shop_admin/itemqalist.php', 'scf_item_qna');
$menu['menu400'][] = array('400650', '사용후기', G5_ADMIN_URL . '/shop_admin/itemuselist.php', 'scf_ps');

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('400620', $current_user_type)) {
    $menu['menu400'][] = array('400620', '재고관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/itemstocklist.php', 'scf_item_stock');
}
if (dmk_is_menu_allowed('400610', $current_user_type)) {
    $menu['menu400'][] = array('400610', '상품유형관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/itemtypelist.php', 'scf_item_type');
}
if (dmk_is_menu_allowed('400500', $current_user_type)) {
    $menu['menu400'][] = array('400500', '상품옵션재고관리 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/optionstocklist.php', 'scf_item_option');
}

// 기존 영카트 메뉴들 (수정하지 않음)
$menu['menu400'][] = array('400800', '쿠폰관리', G5_ADMIN_URL . '/shop_admin/couponlist.php', 'scf_coupon');
$menu['menu400'][] = array('400810', '쿠폰존관리', G5_ADMIN_URL . '/shop_admin/couponzonelist.php', 'scf_coupon_zone');
$menu['menu400'][] = array('400750', '추가배송비관리', G5_ADMIN_URL . '/shop_admin/sendcostlist.php', 'scf_sendcost', 1);

// 개조된 메뉴들 - DMK 권한 체크 적용
if (dmk_is_menu_allowed('400410', $current_user_type)) {
    $menu['menu400'][] = array('400410', '미완료주문 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/shop_admin/inorderlist.php', 'scf_inorder', 1);
}
