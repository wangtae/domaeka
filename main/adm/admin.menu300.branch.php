<?php
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 지점 관리자 전용 메뉴
$dmk_auth = dmk_get_admin_auth();
if ($dmk_auth && $dmk_auth['mb_type'] == 3) { // 지점 관리자만
    $menu['menu300'] = array(
        array('300000', '지점 관리', G5_DMK_ADM_URL . '/branch_admin/order_page.php', 'branch_manage'),
        array('300400', '주문 접수', G5_DMK_ADM_URL . '/branch_admin/order_page.php', 'branch_order'),
        array('300500', '주문 내역', G5_DMK_ADM_URL . '/branch_admin/orderlist.php', 'branch_orderlist'),
    );
} else {
    $menu['menu300'] = array(); // 지점 관리자가 아니면 빈 배열
}
?> 