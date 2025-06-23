<?php
// 전역 변수 선언
global $member, $is_admin;

// 도매까 권한 라이브러리 포함
if (defined('G5_DMK_PATH')) {
    include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
}

$menu['menu200'] = array(
    array('200000', '회원관리', G5_ADMIN_URL . '/member_list.php', 'member'),
    array('200100', '회원목록 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/member_list.php', 'mb_list'),
    array('200300', '회원메일발송', G5_ADMIN_URL . '/mail_list.php', 'mb_mail'),
    array('200200', '포인트관리', G5_ADMIN_URL . '/point_list.php', 'mb_point'),
    array('200800', '접속자집계', G5_ADMIN_URL . '/visit_list.php', 'mb_visit', 1),
    array('200810', '접속자검색', G5_ADMIN_URL . '/visit_search.php', 'mb_search', 1),
    array('200820', '접속자로그삭제', G5_ADMIN_URL . '/visit_delete.php', 'mb_delete', 1),
    array('200900', '투표관리', G5_ADMIN_URL . '/poll_list.php', 'mb_poll'),
);

// 도매까 메뉴는 별도의 menu210으로 이동됨
