<?php
$sub_menu = '400610';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

check_demo();

auth_check_menu($auth, $sub_menu, "w");

// 도매까 권한 확인 - 상품유형관리는 총판 관리자만 접근 가능
if (!dmk_can_modify_category()) {
    alert('상품유형관리는 최고관리자 또는 총판 관리자만 접근할 수 있습니다.', G5_ADMIN_URL);
}

check_admin_token();

$count_post_it_id = (isset($_POST['it_id']) && is_array($_POST['it_id'])) ? count($_POST['it_id']) : 0;

for ($i=0; $i<$count_post_it_id; $i++)
{
    $it_type1 = isset($_POST['it_type1'][$i]) ? (int) $_POST['it_type1'][$i] : 0;
    $it_type2 = isset($_POST['it_type2'][$i]) ? (int) $_POST['it_type2'][$i] : 0;
    $it_type3 = isset($_POST['it_type3'][$i]) ? (int) $_POST['it_type3'][$i] : 0;
    $it_type4 = isset($_POST['it_type4'][$i]) ? (int) $_POST['it_type4'][$i] : 0;
    $it_type5 = isset($_POST['it_type5'][$i]) ? (int) $_POST['it_type5'][$i] : 0;

    $it_id = isset($_POST['it_id'][$i]) ? safe_replace_regex($_POST['it_id'][$i], 'it_id') : '';

    $sql = "update {$g5['g5_shop_item_table']}
               set it_type1 = '".$it_type1."',
                   it_type2 = '".$it_type2."',
                   it_type3 = '".$it_type3."',
                   it_type4 = '".$it_type4."',
                   it_type5 = '".$it_type5."'
             where it_id = '".$it_id."' ";
    sql_query($sql);
}

goto_url("itemtypelist.php?sca=$sca&amp;sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");