<?php
$sub_menu = '400300';
include_once('./_common.php');

include_once(G5_DMK_PATH.'/adm/lib/admin.log.lib.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
include_once(G5_PATH.'/dmk/dmk_global_settings.php');

//check_admin_token();

$dmk_auth = dmk_get_admin_auth();
$user_type = dmk_get_current_user_type();

// 도매까 관리자 유형 상수 정의
if (!defined('DMK_MB_TYPE_SUPER_ADMIN')) define('DMK_MB_TYPE_SUPER_ADMIN', 0);
if (!defined('DMK_MB_TYPE_DISTRIBUTOR')) define('DMK_MB_TYPE_DISTRIBUTOR', 1);
if (!defined('DMK_MB_TYPE_AGENCY')) define('DMK_MB_TYPE_AGENCY', 2);
if (!defined('DMK_MB_TYPE_BRANCH')) define('DMK_MB_TYPE_BRANCH', 3);

// 도매까 소유자 유형 상수 정의
if (!defined('DMK_OWNER_TYPE_SUPER_ADMIN')) define('DMK_OWNER_TYPE_SUPER_ADMIN', 'super_admin');
if (!defined('DMK_OWNER_TYPE_DISTRIBUTOR')) define('DMK_OWNER_TYPE_DISTRIBUTOR', 'distributor');
if (!defined('DMK_OWNER_TYPE_AGENCY')) define('DMK_OWNER_TYPE_AGENCY', 'agency');
if (!defined('DMK_OWNER_TYPE_BRANCH')) define('DMK_OWNER_TYPE_BRANCH', 'branch');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

// 1. 관리자 로그인 여부 확인 및 권한 정보 가져오기
if (!$dmk_auth || empty($dmk_auth['mb_id'])) {
    alert("관리자 권한이 필요합니다.", G5_ADMIN_URL);
    exit;
}

// 2. 최고 관리자 (영카트 최고 관리자)는 모든 관리자 폼에 접근 가능
if (!$dmk_auth['is_super']) {
    // 최고 관리자가 아닌 경우 추가 권한 체크
    if ($user_type === false || $user_type > DMK_MB_TYPE_BRANCH) {
        alert("관리자 권한이 없습니다.", G5_ADMIN_URL);
    }
}


$count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;
$post_act_button = isset($_POST['act_button']) ? $_POST['act_button'] : '';

if (! $count_post_chk) {
    alert($post_act_button." 하실 항목을 하나 이상 체크하세요.");
}

if ($post_act_button == "선택수정") {


    
    //auth_check_menu($auth, $sub_menu, 'w');

    for ($i=0; $i< $count_post_chk; $i++) {



        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;

        $p_it_name = (isset($_POST['it_name']) && is_array($_POST['it_name'])) ? strip_tags(clean_xss_attributes($_POST['it_name'][$k])) : '';
        $p_it_cust_price = (isset($_POST['it_cust_price']) && is_array($_POST['it_cust_price'])) ? strip_tags($_POST['it_cust_price'][$k]) : '';
        $p_it_price = (isset($_POST['it_price']) && is_array($_POST['it_price'])) ? strip_tags($_POST['it_price'][$k]) : '';
        $p_it_stock_qty = (isset($_POST['it_stock_qty']) && is_array($_POST['it_stock_qty'])) ? strip_tags($_POST['it_stock_qty'][$k]) : '';
        $p_it_skin = (isset($_POST['it_skin']) && is_array($_POST['it_skin'])) ? strip_tags($_POST['it_skin'][$k]) : '';
        $p_it_mobile_skin = (isset($_POST['it_mobile_skin']) && is_array($_POST['it_mobile_skin'])) ? strip_tags($_POST['it_mobile_skin'][$k]) : '';
        $p_it_use       = isset($_POST['it_use'][$k])       ? clean_xss_tags($_POST['it_use'][$k], 1, 1)        : 0;
        $p_it_soldout   = isset($_POST['it_soldout'][$k])   ? clean_xss_tags($_POST['it_soldout'][$k], 1, 1)    : 0;
        $p_it_order = (isset($_POST['it_order']) && is_array($_POST['it_order'])) ? strip_tags($_POST['it_order'][$k]) : '';
        $p_it_id = isset($_POST['it_id'][$k]) ? preg_replace('/[^a-z0-9_\-]/i', '', $_POST['it_id'][$k]) : '';


        $sql = "update {$g5['g5_shop_item_table']}
                   set 
                       it_name        = '".$p_it_name."',
                       it_cust_price  = '".sql_real_escape_string($p_it_cust_price)."',
                       it_price       = '".sql_real_escape_string($p_it_price)."',
                       it_stock_qty   = '".sql_real_escape_string($p_it_stock_qty)."',
                       it_skin        = '".sql_real_escape_string($p_it_skin)."',
                       it_mobile_skin = '".sql_real_escape_string($p_it_mobile_skin)."',
                       it_use         = '".sql_real_escape_string($p_it_use)."',
                       it_soldout     = '".sql_real_escape_string($p_it_soldout)."',
                       it_order       = '".sql_real_escape_string($p_it_order)."',
                       it_update_time = '".G5_TIME_YMDHIS."'
                 where it_id   = '".$p_it_id."' ";

        sql_query($sql);

        

		if( function_exists('shop_seo_title_update') ) shop_seo_title_update($p_it_id, true);
    }
} else if ($post_act_button == "선택삭제") {

    if ($is_admin != 'super')
        alert('상품 삭제는 최고관리자만 가능합니다.');

    auth_check_menu($auth, $sub_menu, 'd');

    // _ITEM_DELETE_ 상수를 선언해야 itemdelete.inc.php 가 정상 작동함
    define('_ITEM_DELETE_', true);

    for ($i=0; $i<$count_post_chk; $i++) {
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;

        // include 전에 $it_id 값을 반드시 넘겨야 함
        $it_id = isset($_POST['it_id'][$k]) ? preg_replace('/[^a-z0-9_\-]/i', '', $_POST['it_id'][$k]) : '';
        include ('./itemdelete.inc.php');
    }
}

goto_url("./itemlist.php?sca=$sca&amp;sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");