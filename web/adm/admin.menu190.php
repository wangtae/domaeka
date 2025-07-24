<?php
// 프랜차이즈 관리 메뉴 - 권한에 따른 메뉴 구성
// 필요한 함수들이 정의되었는지 확인
if (!function_exists('dmk_get_current_user_type')) {
    // 도매까 전역 설정 포함
    if (defined('G5_PATH') && file_exists(G5_PATH . '/dmk/dmk_global_settings.php')) {
        include_once(G5_PATH . '/dmk/dmk_global_settings.php');
    }
}

$user_type = function_exists('dmk_get_current_user_type') ? dmk_get_current_user_type() : null;

if ($user_type === 'super' || is_super_admin($member['mb_id'])) {
    // 본사 관리자 - 모든 프랜차이즈 관리 메뉴 접근 가능
    $menu['menu190'] = array(
        array('190000', '프랜차이즈 관리', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_manage'),
        array('190100', '총판관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_distributor'),
        array('190200', '대리점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
        array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
        array('190400', '통계분석 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/statistics/statistics_dashboard.php', 'dmk_statistics'),
        array('190600', '서브관리자관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/admin_list.php', 'dmk_admin'),
        array('190700', '서브관리자권한설정 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/dmk_auth_list.php', 'dmk_auth'),
        array('190800', '계층별메뉴권한설정 <i class="fa fa-star" title="설정"></i>', G5_URL . '/dmk/adm/admin_manager/menu_config.php', 'dmk_menu_config'),
        array('190900', '관리자엑션로그 <i class="fa fa-star" title="LOG"></i>', G5_URL . '/dmk/adm/logs/action_log_list.php', 'dmk_action_log'),
    );
} else if ($user_type === 'distributor') {
    // 총판 관리자 - 총판관리 제외, 대리점/지점/통계 관리 가능
    $menu['menu190'] = array(
        array('190000', '프랜차이즈 관리', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_manage'),
        array('190200', '대리점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
        array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
        array('190400', '통계분석 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/statistics/statistics_dashboard.php', 'dmk_statistics'),
        array('190600', '서브관리자관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/admin_list.php', 'dmk_admin'),
        array('190700', '서브관리자권한설정 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/dmk_auth_list.php', 'dmk_auth'),
        array('190900', '관리자엑션로그 <i class="fa fa-star" title="LOG"></i>', G5_URL . '/dmk/adm/logs/action_log_list.php', 'dmk_action_log'),
    );
} else if ($user_type === 'agency') {
    // 대리점 관리자 - 총판/대리점관리 제외, 지점 관리만 가능
    $menu['menu190'] = array(
        array('190000', '프랜차이즈 관리', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_manage'),
        array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
        array('190400', '통계분석 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/statistics/statistics_dashboard.php', 'dmk_statistics'),
        array('190600', '서브관리자관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/admin_list.php', 'dmk_admin'),
        array('190700', '서브관리자권한설정 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/dmk_auth_list.php', 'dmk_auth'),
        array('190900', '관리자엑션로그 <i class="fa fa-star" title="LOG"></i>', G5_URL . '/dmk/adm/logs/action_log_list.php', 'dmk_action_log'),
    );
} else if ($user_type === 'branch') {
    // 지점 관리자 - 지점관리, 서브관리자관리만 가능
    $menu['menu190'] = array(
        array('190000', '프랜차이즈 관리', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_manage'),
        array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
        array('190600', '서브관리자관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/admin_list.php', 'dmk_admin'),
        array('190700', '서브관리자권한설정 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/admin_manager/dmk_auth_list.php', 'dmk_auth'),
        array('190900', '관리자엑션로그 <i class="fa fa-star" title="LOG"></i>', G5_URL . '/dmk/adm/logs/action_log_list.php', 'dmk_action_log'),
    );
} else {
    // 권한이 없는 사용자
    $menu['menu190'] = array();
} 