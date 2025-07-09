<?php
$sub_menu = "300400";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, 'w');

// POST 데이터 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    goto_url('order_page.php');
}

$branch_id = trim($_POST['branch_id']);
$order_date = trim($_POST['order_date']);
$orderer_name = trim($_POST['orderer_name']);
$orderer_phone = trim($_POST['orderer_phone']);
$delivery_address = trim($_POST['delivery_address']);
$order_memo = trim($_POST['order_memo']);
$items = isset($_POST['items']) ? $_POST['items'] : array();

// 필수 항목 검증
if (empty($branch_id) || empty($orderer_name) || empty($orderer_phone)) {
    alert('필수 항목을 모두 입력해주세요.');
    goto_url('order_page.php');
}

// 주문 상품 검증
if (empty($items)) {
    alert('주문할 상품을 선택해주세요.');
    goto_url('order_page.php');
}

// 지점 권한 확인
if ($member['dmk_br_id'] != $branch_id) {
    alert('권한이 없습니다.');
    goto_url('order_page.php');
}

// 지점 정보 확인
$branch_info = get_branch_info($branch_id);
if (!$branch_info) {
    alert('지점 정보를 찾을 수 없습니다.');
    goto_url('order_page.php');
}

// 상품 권한 확인 및 재고 검증
$item_where_condition = dmk_get_item_where_condition($member['dmk_br_id'], $member['dmk_ag_id'], $member['dmk_dt_id']);
$order_items = array();
$total_amount = 0;

foreach ($items as $item_id => $quantity) {
    $quantity = (int)$quantity;
    if ($quantity <= 0) continue;
    
    // 상품 정보 조회
    $sql = "SELECT it_id, it_name, it_cust_price, it_stock_qty, dmk_it_owner_type, dmk_it_owner_id
            FROM {$g5['g5_shop_item_table']} 
            WHERE it_id = '$item_id' AND it_use = '1' AND it_soldout = '0' $item_where_condition";
    $item_row = sql_fetch($sql);
    
    if (!$item_row) {
        alert('주문할 수 없는 상품이 포함되어 있습니다: ' . $item_id);
        goto_url('order_page.php');
    }
    
    // 재고 확인
    if ($quantity > $item_row['it_stock_qty']) {
        alert($item_row['it_name'] . '의 재고가 부족합니다. (주문: ' . $quantity . '개, 재고: ' . $item_row['it_stock_qty'] . '개)');
        goto_url('order_page.php');
    }
    
    $order_items[] = array(
        'it_id' => $item_row['it_id'],
        'it_name' => $item_row['it_name'],
        'it_price' => $item_row['it_cust_price'],
        'ct_qty' => $quantity,
        'ct_price' => $item_row['it_cust_price'] * $quantity
    );
    
    $total_amount += $item_row['it_cust_price'] * $quantity;
}

if (empty($order_items)) {
    alert('주문할 상품이 없습니다.');
    goto_url('order_page.php');
}

// 주문번호 생성
$order_id = date('YmdHis') . '_' . $branch_id;

// 트랜잭션 시작
sql_query("BEGIN");

try {
    // 주문 정보 저장 (g5_shop_order 테이블 활용)
    $sql = "INSERT INTO {$g5['g5_shop_order_table']} SET
                od_id = '$order_id',
                mb_id = '{$member['mb_id']}',
                od_name = '$orderer_name',
                od_hp = '$orderer_phone',
                od_addr1 = '$delivery_address',
                od_memo = '$order_memo',
                od_time = NOW(),
                od_ip = '{$_SERVER['REMOTE_ADDR']}',
                od_receipt_price = '$total_amount',
                od_order_price = '$total_amount',
                od_receipt_point = '0',
                od_settle_case = '무통장',
                od_status = '주문',
                dmk_br_id = '$branch_id',
                dmk_order_type = 'branch'";
    
    sql_query($sql);
    
    // 주문 상품 저장
    foreach ($order_items as $item) {
        $sql = "INSERT INTO {$g5['g5_shop_cart_table']} SET
                    od_id = '$order_id',
                    mb_id = '{$member['mb_id']}',
                    it_id = '{$item['it_id']}',
                    it_name = '{$item['it_name']}',
                    ct_price = '{$item['it_price']}',
                    ct_qty = '{$item['ct_qty']}',
                    ct_status = '주문',
                    ct_time = NOW(),
                    dmk_br_id = '$branch_id'";
        
        sql_query($sql);
        
        // 재고 차감
        $sql = "UPDATE {$g5['g5_shop_item_table']} SET 
                    it_stock_qty = it_stock_qty - {$item['ct_qty']}
                WHERE it_id = '{$item['it_id']}'";
        sql_query($sql);
    }
    
    // 커밋
    sql_query("COMMIT");
    
    // 성공 메시지
    $success_msg = "주문이 성공적으로 접수되었습니다.\\n\\n";
    $success_msg .= "주문번호: $order_id\\n";
    $success_msg .= "주문금액: " . number_format($total_amount) . "원\\n";
    $success_msg .= "주문상품: " . count($order_items) . "개\\n\\n";
    $success_msg .= "주문 확인 후 연락드리겠습니다.";
    
    alert($success_msg);
    goto_url('orderlist.php');
    
} catch (Exception $e) {
    // 롤백
    sql_query("ROLLBACK");
    
    alert('주문 처리 중 오류가 발생했습니다. 다시 시도해주세요.');
    goto_url('order_page.php');
}
?> 