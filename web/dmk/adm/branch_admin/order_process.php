<?php
define('_GNUBOARD_', true);
$order_process_dir = dirname(__FILE__);
$web_root = dirname(dirname(dirname($order_process_dir)));
include_once($web_root . '/common.php');

// POST 데이터 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    exit;
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
    exit;
}

// 주문 상품 검증
if (empty($items)) {
    alert('주문할 상품을 선택해주세요.');
    exit;
}

// 지점 정보 확인 (대리점, 총판 정보 포함)
// 먼저 지점 정보를 가져옴
$branch_sql = "SELECT b.*, m.mb_name as br_name 
               FROM dmk_branch b 
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id 
               WHERE b.br_id = '$branch_id' AND b.br_status = 1";
$branch_info = sql_fetch($branch_sql);

if (!$branch_info) {
    alert('지점 정보를 찾을 수 없습니다.');
    exit;
}

// 대리점 정보 조회
$ag_id = $branch_info['ag_id'] ? $branch_info['ag_id'] : '';
$dt_id = '';

if ($ag_id) {
    // dmk_agency 테이블에서 직접 dt_id를 가져옴
    $agency_sql = "SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_real_escape_string($ag_id)."'";
    $agency_info = sql_fetch($agency_sql);
    
    if ($agency_info && $agency_info['dt_id']) {
        $dt_id = $agency_info['dt_id'];
    }
}

// 디버깅: 계층 ID 값 확인
error_log("Order Debug - Branch ID: $branch_id, Agency ID: $ag_id, Distributor ID: $dt_id");

// 상품 권한 확인 및 재고 검증
$order_items = array();
$total_amount = 0;

foreach ($items as $item_id => $item_data) {
    $quantity = (int)$item_data['qty'];
    if ($quantity <= 0) continue;
    
    // 상품 정보 조회
    $sql = "SELECT it_id, it_name, it_cust_price, it_stock_qty
            FROM g5_shop_item 
            WHERE it_id = '".sql_real_escape_string($item_id)."' AND it_use = '1' AND it_soldout = '0'";
    $item_row = sql_fetch($sql);
    
    if (!$item_row) {
        alert('주문할 수 없는 상품이 포함되어 있습니다: ' . $item_id);
        exit;
    }
    
    // 재고 확인
    if ($quantity > $item_row['it_stock_qty']) {
        alert($item_row['it_name'] . '의 재고가 부족합니다. (주문: ' . $quantity . '개, 재고: ' . $item_row['it_stock_qty'] . '개)');
        exit;
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
    exit;
}

// 주문번호 생성
$order_id = date('YmdHis') . '_' . $branch_id;

// 회원 정보 업데이트 (로그인한 경우에만)
if ($is_member && $member['mb_id']) {
    // 회원의 현재 정보 확인
    $mb_sql = "SELECT mb_name, mb_hp FROM {$g5['member_table']} WHERE mb_id = '{$member['mb_id']}'";
    $mb_info = sql_fetch($mb_sql);
    
    $need_update = false;
    $update_fields = array();
    
    // 이름 업데이트 필요 여부 확인
    if (empty($mb_info['mb_name']) || trim($mb_info['mb_name']) === '') {
        $need_update = true;
        $update_fields[] = "mb_name = '".sql_real_escape_string($orderer_name)."'";
    } else {
        // 카카오 기본 이름인지 확인
        if (strpos($member['mb_id'], 'kakao_') === 0) {
            $kakao_id = str_replace('kakao_', '', $member['mb_id']);
            if ($mb_info['mb_name'] === $kakao_id) {
                $need_update = true;
                $update_fields[] = "mb_name = '".sql_real_escape_string($orderer_name)."'";
            }
        }
    }
    
    // 전화번호 업데이트 필요 여부 확인  
    if (empty($mb_info['mb_hp']) || trim($mb_info['mb_hp']) === '') {
        $need_update = true;
        $update_fields[] = "mb_hp = '".sql_real_escape_string($orderer_phone)."'";
    }
    
    // 업데이트 실행
    if ($need_update && count($update_fields) > 0) {
        $update_sql = "UPDATE {$g5['member_table']} SET " . implode(', ', $update_fields) . " WHERE mb_id = '".sql_real_escape_string($member['mb_id'])."'";
        sql_query($update_sql);
    }
}

// 필드 존재 여부 확인 (디버깅용)
$columns_check = sql_query("SHOW COLUMNS FROM g5_shop_order LIKE 'dmk_od_%'");
$has_hierarchy_fields = false;
while ($col = sql_fetch_array($columns_check)) {
    if ($col['Field'] == 'dmk_od_dt_id' || $col['Field'] == 'dmk_od_ag_id') {
        $has_hierarchy_fields = true;
    }
}
if (!$has_hierarchy_fields) {
    error_log("Warning: dmk_od_dt_id or dmk_od_ag_id columns not found in g5_shop_order table. Please run 007_add_hierarchy_to_order.sql");
}

// 트랜잭션 시작
sql_query("BEGIN");

try {
    // 주문 정보 저장 (g5_shop_order 테이블 활용)
    $mb_id = ($is_member && $member['mb_id']) ? $member['mb_id'] : '';
    $sql = "INSERT INTO g5_shop_order SET
                od_id = '".sql_real_escape_string($order_id)."',
                mb_id = '".sql_real_escape_string($mb_id)."',
                od_name = '".sql_real_escape_string($orderer_name)."',
                od_email = '',
                od_tel = '',
                od_hp = '".sql_real_escape_string($orderer_phone)."',
                od_zip1 = '',
                od_zip2 = '',
                od_addr1 = '".sql_real_escape_string($delivery_address)."',
                od_addr2 = '',
                od_addr3 = '',
                od_addr_jibeon = '',
                od_deposit_name = '".sql_real_escape_string($orderer_name)."',
                od_b_name = '',
                od_b_tel = '',
                od_b_hp = '',
                od_b_zip1 = '',
                od_b_zip2 = '',
                od_b_addr1 = '',
                od_b_addr2 = '',
                od_b_addr3 = '',
                od_b_addr_jibeon = '',
                od_memo = '".sql_real_escape_string($order_memo)."',
                od_cart_count = '".count($order_items)."',
                od_cart_price = '".intval($total_amount)."',
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
                od_misu = '".intval($total_amount)."',
                od_shop_memo = '',
                od_mod_history = '',
                od_status = '주문',
                od_hope_date = '".sql_real_escape_string($order_date)."',
                od_settle_case = '무통장',
                od_other_pay_type = '',
                od_test = '0',
                `dmk_od_br_id` = '".sql_real_escape_string($branch_id)."',
                `dmk_od_ag_id` = '".sql_real_escape_string($ag_id)."',
                `dmk_od_dt_id` = '".sql_real_escape_string($dt_id)."',
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
    
    // 디버깅: SQL 쿼리 확인
    error_log("Order Insert SQL - dmk_od_dt_id: '$dt_id', dmk_od_ag_id: '$ag_id', dmk_od_br_id: '$branch_id'");
    
    $result = sql_query($sql);
    if (!$result) {
        sql_query("ROLLBACK");
        error_log("Order SQL Error: " . sql_error_info());
        alert('주문 저장 중 오류가 발생했습니다.');
        exit;
    }
    
    // 디버깅: 저장된 데이터 확인
    $check_sql = "SELECT od_id, dmk_od_dt_id, dmk_od_ag_id, dmk_od_br_id FROM g5_shop_order WHERE od_id = '".sql_real_escape_string($order_id)."'";
    $check_result = sql_fetch($check_sql);
    if ($check_result) {
        error_log("Order Save Check - Order ID: {$check_result['od_id']}, DT: {$check_result['dmk_od_dt_id']}, AG: {$check_result['dmk_od_ag_id']}, BR: {$check_result['dmk_od_br_id']}");
    } else {
        error_log("Order Save Check - Order not found with ID: $order_id");
    }
    
    // 주문 상품 저장
    foreach ($order_items as $item) {
        $sql = "INSERT INTO g5_shop_cart SET
                    od_id = '".sql_real_escape_string($order_id)."',
                    mb_id = '".sql_real_escape_string($mb_id)."',
                    it_id = '".sql_real_escape_string($item['it_id'])."',
                    it_name = '".sql_real_escape_string($item['it_name'])."',
                    it_sc_type = '0',
                    it_sc_method = '0',
                    it_sc_price = '0',
                    it_sc_minimum = '0',
                    it_sc_qty = '0',
                    ct_status = '주문',
                    ct_history = '',
                    ct_price = '".intval($item['it_price'])."',
                    ct_point = '0',
                    cp_price = '0',
                    ct_point_use = '0',
                    ct_stock_use = '0',
                    ct_option = '',
                    ct_qty = '".intval($item['ct_qty'])."',
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
        
        $result = sql_query($sql);
        if (!$result) {
            sql_query("ROLLBACK");
            alert('주문 상품 저장 중 오류가 발생했습니다.');
            exit;
        }
        
        // 재고 차감
        $sql = "UPDATE g5_shop_item SET 
                    it_stock_qty = it_stock_qty - ".intval($item['ct_qty'])."
                WHERE it_id = '".sql_real_escape_string($item['it_id'])."'";
        $result = sql_query($sql);
        if (!$result) {
            sql_query("ROLLBACK");
            alert('주문 상품 저장 중 오류가 발생했습니다.');
            exit;
        }
    }
    
    // 커밋
    sql_query("COMMIT");
    
    // 성공 메시지
    $success_msg = "주문이 성공적으로 접수되었습니다.\\n\\n";
    $success_msg .= "주문번호: $order_id\\n";
    $success_msg .= "주문금액: " . number_format($total_amount) . "원\\n";
    $success_msg .= "주문상품: " . count($order_items) . "개\\n";
    if (!empty($order_memo)) {
        $success_msg .= "요청사항: " . str_replace("\n", "\\n", $order_memo) . "\\n";
    }

    $success_msg .= "\\n주문내역 페이지로 이동합니다.";
    
    // 주문내역 페이지로 리다이렉트 (noredirect 파라미터 추가하여 자동 리다이렉트 방지)
    $return_url = '/go/orderlist.php?noredirect=1';
    
    // alert 함수 대신 직접 JavaScript 출력
    ?>
    <script>
    alert("<?php echo str_replace('"', '\"', $success_msg); ?>");
    location.href = "<?php echo $return_url; ?>";
    </script>
    <?php
    exit;
    
} catch (Exception $e) {
    // 롤백
    sql_query("ROLLBACK");
    
    // 주문내역 페이지로 리다이렉트 (noredirect 파라미터 추가하여 자동 리다이렉트 방지)
    $return_url = '/go/orderlist.php?noredirect=1';
    
    // alert 함수 대신 직접 JavaScript 출력
    ?>
    <script>
    alert("주문 처리 중 오류가 발생했습니다. 다시 시도해주세요.");
    history.back();
    </script>
    <?php
    exit;
}
?> 