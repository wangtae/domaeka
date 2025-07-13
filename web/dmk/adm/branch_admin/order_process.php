<?php
include_once('./_common.php');

// POST 데이터 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    goto_url(G5_URL);
}

$branch_id = trim($_POST['br_id']);
$order_date = trim($_POST['order_date']);
$orderer_name = trim($_POST['customer_name']);
$orderer_phone = trim($_POST['customer_phone']);
$delivery_address = trim($_POST['customer_address']);
$order_memo = trim($_POST['customer_message']);
$delivery_type = trim($_POST['delivery_type']);
$items = isset($_POST['items']) ? $_POST['items'] : array();

// 필수 항목 검증
if (empty($branch_id) || empty($orderer_name) || empty($orderer_phone)) {
    alert('필수 항목을 모두 입력해주세요.');
    goto_url(G5_URL);
}

// 주문 상품 검증
if (empty($items)) {
    alert('주문할 상품을 선택해주세요.');
    goto_url(G5_URL);
}

// 지점 정보 확인
$branch_sql = "SELECT b.*, m.mb_name as br_name 
               FROM dmk_branch b 
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id 
               WHERE b.br_id = '$branch_id' AND b.br_status = 1";
$branch_info = sql_fetch($branch_sql);

if (!$branch_info) {
    alert('지점 정보를 찾을 수 없습니다.');
    goto_url(G5_URL);
}

// 상품 권한 확인 및 재고 검증
$order_items = array();
$total_amount = 0;

foreach ($items as $item_id => $item_data) {
    $quantity = (int)$item_data['qty'];
    if ($quantity <= 0) continue;
    
    // 상품 정보 조회
    $sql = "SELECT it_id, it_name, it_cust_price, it_stock_qty
            FROM g5_shop_item 
            WHERE it_id = '$item_id' AND it_use = '1' AND it_soldout = '0'";
    $item_row = sql_fetch($sql);
    
    if (!$item_row) {
        alert('주문할 수 없는 상품이 포함되어 있습니다: ' . $item_id);
        goto_url(G5_URL);
    }
    
    // 재고 확인
    if ($quantity > $item_row['it_stock_qty']) {
        alert($item_row['it_name'] . '의 재고가 부족합니다. (주문: ' . $quantity . '개, 재고: ' . $item_row['it_stock_qty'] . '개)');
        goto_url(G5_URL);
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
    goto_url(G5_URL);
}

// 주문번호 생성
$order_id = date('YmdHis') . '_' . $branch_id;

// 트랜잭션 시작
sql_query("BEGIN");

try {
    // 주문 정보 저장 (g5_shop_order 테이블 활용)
    $sql = "INSERT INTO g5_shop_order SET
                od_id = '$order_id',
                mb_id = '',
                od_name = '$orderer_name',
                od_email = '',
                od_tel = '',
                od_hp = '$orderer_phone',
                od_zip1 = '',
                od_zip2 = '',
                od_addr1 = '$delivery_address',
                od_addr2 = '',
                od_addr3 = '',
                od_addr_jibeon = '',
                od_deposit_name = '$orderer_name',
                od_b_name = '',
                od_b_tel = '',
                od_b_hp = '',
                od_b_zip1 = '',
                od_b_zip2 = '',
                od_b_addr1 = '',
                od_b_addr2 = '',
                od_b_addr3 = '',
                od_b_addr_jibeon = '',
                od_memo = '$order_memo',
                od_cart_count = '".count($order_items)."',
                od_cart_price = '$total_amount',
                od_cart_coupon = '0',
                od_send_cost = '0',
                od_send_cost2 = '0',
                od_send_coupon = '0',
                od_receipt_price = '0',
                od_cancel_price = '0',
                od_receipt_point = '0',
                od_refund_price = '0',
                od_bank_account = '',
                od_receipt_time = '0000-00-00 00:00:00',
                od_coupon = '0',
                od_misu = '$total_amount',
                od_shop_memo = '',
                od_mod_history = '',
                od_status = '주문',
                od_hope_date = '$order_date',
                od_settle_case = '무통장',
                od_other_pay_type = '',
                od_test = '0',
                dmk_od_br_id = '$branch_id',
                od_mobile = '0',
                od_pg = '',
                od_tno = '',
                od_app_no = '',
                od_escrow = '0',
                od_casseqno = '',
                od_tax_flag = '0',
                od_tax_mny = '0',
                od_vat_mny = '0',
                od_free_mny = '0',
                od_delivery_company = '0',
                od_invoice = '',
                od_invoice_time = '0000-00-00 00:00:00',
                od_cash = '0',
                od_cash_no = '',
                od_cash_info = '',
                od_time = NOW(),
                od_pwd = '',
                od_ip = '{$_SERVER['REMOTE_ADDR']}'";
    
    sql_query($sql);
    
    // 주문 상품 저장
    foreach ($order_items as $item) {
        $sql = "INSERT INTO g5_shop_cart SET
                    od_id = '$order_id',
                    mb_id = '',
                    it_id = '{$item['it_id']}',
                    it_name = '{$item['it_name']}',
                    it_sc_type = '0',
                    it_sc_method = '0',
                    it_sc_price = '0',
                    it_sc_minimum = '0',
                    it_sc_qty = '0',
                    ct_status = '주문',
                    ct_history = '',
                    ct_price = '{$item['it_price']}',
                    ct_point = '0',
                    cp_price = '0',
                    ct_point_use = '0',
                    ct_stock_use = '0',
                    ct_option = '',
                    ct_qty = '{$item['ct_qty']}',
                    ct_notax = '0',
                    io_id = '',
                    io_type = '0',
                    io_price = '0',
                    ct_time = NOW(),
                    ct_ip = '{$_SERVER['REMOTE_ADDR']}',
                    ct_send_cost = '0',
                    ct_direct = '0',
                    ct_select = '0',
                    ct_select_time = '0000-00-00 00:00:00'";
        
        sql_query($sql);
        
        // 재고 차감
        $sql = "UPDATE g5_shop_item SET 
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
    goto_url(G5_URL);
    
} catch (Exception $e) {
    // 롤백
    sql_query("ROLLBACK");
    
    alert('주문 처리 중 오류가 발생했습니다. 다시 시도해주세요.');
    goto_url(G5_URL);
}
?> 