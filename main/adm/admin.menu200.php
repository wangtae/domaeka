<?php
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

$menu['menu200'] = array(
    array('200000', '회원관리', G5_ADMIN_URL . '/member_list.php', 'member'),
    array('200100', '회원관리', G5_ADMIN_URL . '/member_list.php', 'mb_list'),
    array('200300', '회원메일발송', G5_ADMIN_URL . '/mail_list.php', 'mb_mail'),
    array('200800', '접속자집계', G5_ADMIN_URL . '/visit_list.php', 'mb_visit', 1),
    array('200810', '접속자검색', G5_ADMIN_URL . '/visit_search.php', 'mb_search', 1),
    array('200820', '접속자로그삭제', G5_ADMIN_URL . '/visit_delete.php', 'mb_delete', 1),
    array('200200', '포인트관리', G5_ADMIN_URL . '/point_list.php', 'mb_point'),
    array('200900', '투표관리', G5_ADMIN_URL . '/poll_list.php', 'mb_poll'),
);

// 최고관리자 또는 총판 관리자만 '대리점관리', '지점관리' 메뉴에 접근 가능
if (is_super_admin($member['mb_id']) || dmk_is_distributor($member['mb_id'])) {
    $menu['menu200'][] = array('201000', '대리점관리', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency');
    $menu['menu200'][] = array('201100', '지점관리', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch');
}
