<?php
$sub_menu = '400410';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

check_demo();

auth_check_menu($auth, $sub_menu, 'd');

check_admin_token();

$count = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;
if(!$count)
    alert('선택삭제 하실 항목을 하나이상 선택해 주세요.');

for ($i=0; $i<$count; $i++)
{
    // 실제 번호를 넘김
    $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
    $od_id = isset($_POST['od_id'][$k]) ? safe_replace_regex($_POST['od_id'][$k], 'od_id') : '';
    
    // 도매까 권한 체크: 해당 주문의 삭제 권한이 있는지 확인
    $check_sql = "
        SELECT COUNT(*) as cnt 
        FROM {$g5['g5_shop_order_data_table']} od
        INNER JOIN {$g5['g5_shop_cart_table']} ct ON od.od_id = ct.od_id
        WHERE od.od_id = '{$od_id}'
        " . dmk_get_cart_where_condition($member['dmk_br_id'], $member['dmk_ag_id'], $member['dmk_dt_id']) . "
    ";
    $check_result = sql_fetch($check_sql);
    
    if ($check_result['cnt'] == 0) {
        alert('삭제 권한이 없는 주문이 포함되어 있습니다.');
    }
    
    $sql = " delete from {$g5['g5_shop_order_data_table']} where od_id = '{$od_id}' ";
    sql_query($sql);
}

goto_url('./inorderlist.php');