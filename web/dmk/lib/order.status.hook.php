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