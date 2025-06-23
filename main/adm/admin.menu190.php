<?php
// 전역 변수 선언
global $member, $is_admin;

// 도매까 권한 라이브러리 포함
if (defined('G5_DMK_PATH')) {
    include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
}

// 최고관리자 또는 총판 관리자만 도매까 관리 메뉴에 접근 가능
if (isset($member['mb_id']) && 
    (function_exists('is_super_admin') && is_super_admin($member['mb_id'])) || 
    (function_exists('dmk_is_distributor') && dmk_is_distributor($member['mb_id']))) {
    
    $menu['menu190'] = array(
        array('190000', '도매까 관리', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_manage'),
        array('190100', '총판관리 <i class="fa fa-star" title="NEW"></i>', G5_URL . '/dmk/adm/distributor_admin/distributor_list.php', 'dmk_distributor'),
        array('190200', '대리점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
        array('190300', '지점관리 <i class="fa fa-star" title="NEW"></i> ', G5_URL . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
    );
} else {
    $menu['menu190'] = array(); // 권한이 없으면 빈 배열로 설정하여 메뉴를 숨김
} 