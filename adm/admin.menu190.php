<?php
// 전역 변수 선언
global $member, $is_admin;

// 도매까 권한 라이브러리 포함
if (defined('G5_DMK_PATH')) {
    include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
}

// 최고관리자 또는 총판 관리자만 도매까 관리 메뉴에 접근 가능
if (isset($member['mb_id'])) {
    $user_type = dmk_get_current_user_type();
    if ($user_type !== 'none') {
        $menu['menu190'] = array(
            array('190000', '프랜차이즈 관리', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_manage'),
            array('190100', '총판관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_distributor'),
            array('190200', '대리점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
            array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
            array('190400', '통계분석 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/statistics/statistics_dashboard.php', 'dmk_statistics'),
            array('190600', '서브관리자관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/admin_list.php', 'dmk_admin'),
            array('190700', '서브관리자권한설정 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/dmk_auth_list.php', 'dmk_auth'),
            array('190900', '관리자엑션로그 <i class="fa fa-history" title="LOG"></i>', G5_URL . '/dmk/adm/logs/action_log_list.php', 'dmk_action_log'),
            array('190800', '계층별메뉴권한설정 <i class="fa fa-cog" title="설정"></i>', G5_URL . '/dmk/adm/admin_manager/menu_config.php', 'dmk_menu_config'),
        );
    } else {
        $menu['menu190'] = array(); // 권한이 없으면 빈 배열로 설정하여 메뉴를 숨김
    }
} else {
    $menu['menu190'] = array(); // 권한이 없으면 빈 배열로 설정하여 메뉴를 숨김
} 