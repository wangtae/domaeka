<?php
// 전역 변수 선언
global $member, $is_admin;

// 도매까 권한 라이브러리 포함
if (defined('G5_DMK_PATH')) {
    include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
}
if (defined('G5_PATH') && file_exists(G5_PATH . '/dmk/dmk_global_settings.php')) {
    include_once(G5_PATH . '/dmk/dmk_global_settings.php');
}

// 사용자 타입 확인
$user_type = function_exists('dmk_get_current_user_type') ? dmk_get_current_user_type() : null;

if ($user_type === 'super' || is_super_admin($member['mb_id'])) {
    // 본사 관리자 - 모든 회원관리 메뉴 접근 가능
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
} else if ($user_type === 'distributor' || $user_type === 'agency' || $user_type === 'branch') {
    // 총판/대리점/지점 - 회원목록만 접근 가능 (DMK 설정 기준)
    $menu['menu200'] = array(
        array('200000', '회원관리', G5_ADMIN_URL . '/member_list.php', 'member'),
        array('200100', '회원목록 <i class="fa fa-flag" title="개조"></i>', G5_ADMIN_URL . '/member_list.php', 'mb_list'),
    );
} else {
    // 권한이 없는 사용자
    $menu['menu200'] = array();
}

// 도매까 메뉴는 별도의 menu210으로 이동됨
