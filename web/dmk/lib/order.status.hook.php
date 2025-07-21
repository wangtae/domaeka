<?php
/**
 * 주문 상태 변경 훅
 * 주문 상태가 '완료'로 변경될 때 카카오톡 메시지 발송 예약
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 주문 상태 변경 시 호출되는 함수
 * 
 * @param string $od_id 주문번호
 * @param string $old_status 이전 상태
 * @param string $new_status 새로운 상태
 */
function dmk_order_status_changed($od_id, $old_status, $new_status) {
    global $g5;
    
    // 주문 완료 상태로 변경된 경우
    if ($new_status === '완료' && $old_status !== '완료') {
        // 주문 정보 조회
        $order_sql = "SELECT o.*, b.br_id 
                      FROM g5_shop_order o
                      LEFT JOIN dmk_branch b ON o.dmk_od_br_id = b.br_id
                      WHERE o.od_id = '".sql_real_escape_string($od_id)."'";
        $order = sql_fetch($order_sql);
        
        if ($order && $order['br_id']) {
            // 통합 메시지 스케줄 라이브러리 포함
            include_once(G5_DMK_PATH . '/lib/message.schedule.lib.php');
            
            // 주문 완료 메시지 등록
            $schedule_id = dmk_register_order_complete_message($od_id, $order['br_id'], $order['mb_id']);
            
            if ($schedule_id) {
                // 로그 기록
                if (function_exists('dmk_log_admin_action')) {
                    dmk_log_admin_action('order_message', $od_id, '주문 완료 메시지 예약 등록', 
                        json_encode(['schedule_id' => $schedule_id, 'branch_id' => $order['br_id']]),
                        null, 'order_status_hook', 'kb_schedule');
                }
            }
        }
    }
}

/**
 * 주문 상태 일괄 변경 시 호출되는 함수
 * 
 * @param array $od_ids 주문번호 배열
 * @param string $new_status 새로운 상태
 */
function dmk_order_status_batch_changed($od_ids, $new_status) {
    if (!is_array($od_ids) || empty($od_ids)) {
        return;
    }
    
    // 주문 완료 상태로 변경된 경우
    if ($new_status === '완료') {
        foreach ($od_ids as $od_id) {
            // 이전 상태 확인
            $prev_sql = "SELECT od_status FROM g5_shop_order WHERE od_id = '".sql_real_escape_string($od_id)."'";
            $prev = sql_fetch($prev_sql);
            
            if ($prev && $prev['od_status'] !== '완료') {
                dmk_order_status_changed($od_id, $prev['od_status'], $new_status);
            }
        }
    }
}

/**
 * 재고 변경 시 호출되는 함수 (재고 증가 시)
 * 
 * @param string $it_id 상품 ID
 * @param int $old_stock 이전 재고
 * @param int $new_stock 새로운 재고
 */
function dmk_item_stock_increased($it_id, $old_stock, $new_stock) {
    global $g5;
    
    // 품절이었다가 재입고된 경우
    if ($old_stock <= 0 && $new_stock > 0) {
        // 해당 상품과 관련된 활성 품절 메시지 취소
        include_once(G5_DMK_PATH . '/lib/message.schedule.lib.php');
        
        $sql = "SELECT id FROM kb_schedule 
                WHERE reference_type = 'item' 
                AND reference_id = '".sql_real_escape_string($it_id)."'
                AND message_type = 'stock_out'
                AND status = 'active'";
        $result = sql_query($sql);
        
        while ($row = sql_fetch_array($result)) {
            dmk_cancel_message_schedule($row['id']);
        }
    }
}

/**
 * 재고 차감 후 품절임박/품절 체크 함수
 * 주문 상태가 '준비'에서 '배송'으로 변경될 때 호출
 * 
 * @param string $od_id 주문번호
 */
function dmk_check_stock_after_delivery($od_id) {
    global $g5;
    
    // 통합 메시지 스케줄 라이브러리 포함
    include_once(G5_DMK_PATH . '/lib/message.schedule.lib.php');
    
    // 주문의 지점 정보 확인
    $order_sql = "SELECT dmk_od_br_id FROM g5_shop_order 
                  WHERE od_id = '".sql_real_escape_string($od_id)."'";
    $order_info = sql_fetch($order_sql);
    
    if (!$order_info || !$order_info['dmk_od_br_id']) {
        return; // 지점 정보가 없으면 종료
    }
    
    // 주문 상품 목록 조회
    $cart_sql = "SELECT DISTINCT it_id FROM g5_shop_cart 
                 WHERE od_id = '".sql_real_escape_string($od_id)."'";
    $cart_result = sql_query($cart_sql);
    
    while ($cart = sql_fetch_array($cart_result)) {
        // 상품 재고 확인
        $stock_sql = "SELECT it_stock_qty, it_name, dmk_owner_id, dmk_owner_type 
                      FROM g5_shop_item 
                      WHERE it_id = '".sql_real_escape_string($cart['it_id'])."'";
        $stock_info = sql_fetch($stock_sql);
        
        if ($stock_info) {
            $new_stock = $stock_info['it_stock_qty'];
            $item_branch_id = ($stock_info['dmk_owner_type'] == 'branch') ? $stock_info['dmk_owner_id'] : $order_info['dmk_od_br_id'];
            
            // 지점의 품절임박 기준 조회
            $branch_sql = "SELECT br_stock_warning_qty, br_stock_warning_msg_enabled, br_stock_out_msg_enabled 
                           FROM dmk_branch 
                           WHERE br_id = '".sql_real_escape_string($item_branch_id)."'";
            $branch_info = sql_fetch($branch_sql);
            
            if (!$branch_info) continue;
            
            $warning_qty = $branch_info['br_stock_warning_qty'] ?: 10;
            
            // 품절 체크 (재고가 0 이하)
            if ($new_stock <= 0 && $branch_info['br_stock_out_msg_enabled']) {
                if (!dmk_has_active_schedule('item', $cart['it_id'], 'stock_out')) {
                    dmk_register_stock_out_message($cart['it_id'], $item_branch_id);
                }
            }
            // 품절임박 체크 (재고가 경고 수량 이하)
            else if ($new_stock > 0 && $new_stock <= $warning_qty && $branch_info['br_stock_warning_msg_enabled']) {
                if (!dmk_has_active_schedule('item', $cart['it_id'], 'stock_warning')) {
                    dmk_register_stock_warning_message($cart['it_id'], $item_branch_id, $new_stock, $warning_qty);
                }
            }
        }
    }
}

/**
 * 재고 변경 후 품절임박/품절 체크 함수 (간소화 버전)
 * 
 * @param string $it_id 상품 ID
 * @param string $od_id 주문번호 (지점 정보 확인용)
 */
function dmk_check_stock_warning_simple($it_id, $od_id) {
    global $g5;
    
    // 통합 메시지 스케줄 라이브러리 포함
    include_once(G5_DMK_PATH . '/lib/message.schedule.lib.php');
    
    // 상품 재고 확인
    $stock_sql = "SELECT it_stock_qty, it_name, dmk_owner_id, dmk_owner_type 
                  FROM g5_shop_item 
                  WHERE it_id = '".sql_real_escape_string($it_id)."'";
    $stock_info = sql_fetch($stock_sql);
    
    if (!$stock_info) return;
    
    $new_stock = $stock_info['it_stock_qty'];
    
    // 재고가 0 초과로 회복된 경우 품절 스케줄 취소
    if ($new_stock > 0) {
        $cancel_sql = "SELECT id FROM kb_schedule 
                       WHERE reference_type = 'item' 
                       AND reference_id = '".sql_real_escape_string($it_id)."'
                       AND message_type = 'stock_out'
                       AND status = 'active'";
        $cancel_result = sql_query($cancel_sql);
        
        while ($cancel_row = sql_fetch_array($cancel_result)) {
            dmk_cancel_message_schedule($cancel_row['id']);
        }
    }
    
    // 주문의 지점 정보 확인
    $order_sql = "SELECT dmk_od_br_id FROM g5_shop_order 
                  WHERE od_id = '".sql_real_escape_string($od_id)."'";
    $order_info = sql_fetch($order_sql);
    
    if (!$order_info || !$order_info['dmk_od_br_id']) return;
    
    $item_branch_id = ($stock_info['dmk_owner_type'] == 'branch') ? $stock_info['dmk_owner_id'] : $order_info['dmk_od_br_id'];
    
    // 지점의 품절임박 기준 조회
    $branch_sql = "SELECT br_stock_warning_qty, br_stock_warning_msg_enabled, br_stock_out_msg_enabled 
                   FROM dmk_branch 
                   WHERE br_id = '".sql_real_escape_string($item_branch_id)."'";
    $branch_info = sql_fetch($branch_sql);
    
    if (!$branch_info) return;
    
    $warning_qty = $branch_info['br_stock_warning_qty'] ?: 10;
    
    // 재고가 경고 수량을 초과한 경우 품절임박 스케줄 취소
    if ($new_stock > $warning_qty) {
        $cancel_sql = "SELECT id FROM kb_schedule 
                       WHERE reference_type = 'item' 
                       AND reference_id = '".sql_real_escape_string($it_id)."'
                       AND message_type = 'stock_warning'
                       AND status = 'active'";
        $cancel_result = sql_query($cancel_sql);
        
        while ($cancel_row = sql_fetch_array($cancel_result)) {
            dmk_cancel_message_schedule($cancel_row['id']);
        }
    }
    
    // 품절 체크 (재고가 0 이하)
    if ($new_stock <= 0 && $branch_info['br_stock_out_msg_enabled']) {
        if (!dmk_has_active_schedule('item', $it_id, 'stock_out')) {
            dmk_register_stock_out_message($it_id, $item_branch_id);
        }
    }
    // 품절임박 체크 (재고가 경고 수량 이하)
    else if ($new_stock > 0 && $new_stock <= $warning_qty && $branch_info['br_stock_warning_msg_enabled']) {
        if (!dmk_has_active_schedule('item', $it_id, 'stock_warning')) {
            dmk_register_stock_warning_message($it_id, $item_branch_id, $new_stock, $warning_qty);
        }
    }
}

/**
 * 영카트 주문 상태 변경 시 자동 호출을 위한 액션 훅 등록
 * 이 부분은 영카트의 주문 처리 파일에 추가해야 합니다.
 * 
 * 예시:
 * // shop/orderformupdate.php 또는 adm/shop_admin/orderformupdate.php 에서
 * if (function_exists('dmk_order_status_changed')) {
 *     dmk_order_status_changed($od_id, $old_status, $new_status);
 * }
 */
?>