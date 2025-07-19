<?php
/**
 * 통합 메시지 스케줄 라이브러리
 * kb_schedule을 활용한 주문완료, 품절임박, 품절 메시지 발송 관리
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 메시지 스케줄 등록
 * 
 * @param string $message_type 메시지 타입 (order_placed, order_complete, stock_warning, stock_out)
 * @param array $params 메시지 파라미터
 * @return int|false 등록된 스케줄 ID 또는 실패 시 false
 */
function dmk_register_message_schedule($message_type, $params) {
    global $g5;
    
    // 필수 파라미터 검증
    if (!in_array($message_type, ['order_placed', 'order_complete', 'stock_warning', 'stock_out'])) {
        return false;
    }
    
    if (empty($params['branch_id'])) {
        return false;
    }
    
    // 지점 정보 및 메시지 템플릿 조회
    $branch_sql = "SELECT b.*, m.mb_name as br_name, r.room_name, r.room_id
                   FROM dmk_branch b
                   JOIN {$g5['member_table']} m ON b.br_id = m.mb_id
                   LEFT JOIN kb_rooms r ON r.owner_id = b.br_id AND r.owner_type = 'branch' AND r.status = 'approved'
                   WHERE b.br_id = '".sql_real_escape_string($params['branch_id'])."'
                   AND b.br_status = 1";
    $branch = sql_fetch($branch_sql);
    
    if (!$branch) {
        error_log("DMK: Branch not found for ID: " . $params['branch_id']);
        return false;
    }
    
    if (empty($branch['room_id'])) {
        error_log("DMK: No approved room found for branch: " . $params['branch_id']);
        return false;
    }
    
    // 메시지 타입별 처리
    $template = '';
    $delay_minutes = 0;
    $enabled = false;
    
    switch ($message_type) {
        case 'order_placed':
            $template = $branch['br_order_placed_msg_template'];
            $delay_minutes = $branch['br_order_placed_msg_delay'] ?: 0;
            $enabled = $branch['br_order_placed_msg_enabled'];
            break;
            
        case 'order_complete':
            $template = $branch['br_order_msg_template'];
            $delay_minutes = $branch['br_order_msg_delay'] ?: 5;
            $enabled = $branch['br_order_msg_enabled'];
            break;
            
        case 'stock_warning':
            $template = $branch['br_stock_warning_msg_template'];
            $delay_minutes = $branch['br_stock_warning_msg_delay'] ?: 10;
            $enabled = $branch['br_stock_warning_msg_enabled'];
            break;
            
        case 'stock_out':
            $template = $branch['br_stock_out_msg_template'];
            $delay_minutes = $branch['br_stock_out_msg_delay'] ?: 5;
            $enabled = $branch['br_stock_out_msg_enabled'];
            break;
    }
    
    // 메시지 발송이 비활성화되어 있으면 종료
    if (!$enabled) {
        error_log("DMK: Message type '$message_type' is not enabled for branch: " . $params['branch_id']);
        return false;
    }
    
    if (empty($template)) {
        error_log("DMK: Empty template for message type '$message_type' in branch: " . $params['branch_id']);
        return false;
    }
    
    // 발송할 톡방이 없으면 종료
    if (empty($branch['room_id'])) {
        error_log("DMK: No room_id found for branch: " . $params['branch_id']);
        return false;
    }
    
    // 메시지 발송 봇이 설정되어 있지 않으면 종료
    if (empty($branch['br_message_bot_name']) || empty($branch['br_message_device_id'])) {
        error_log("Message not sent - No bot configured for branch: {$branch['br_id']}");
        return false;
    }
    
    // 템플릿 변수 준비
    $template_variables = dmk_prepare_template_variables($message_type, $params);
    
    // 메시지 파싱 (PHP에서 처리)
    $processed_message = dmk_parse_message_template($template, $template_variables);
    
    // 발송 시간 계산 (초는 00으로 설정)
    $schedule_datetime = date('Y-m-d H:i:00', strtotime("+{$delay_minutes} minutes"));
    
    // 봇 정보 확인 (지점에 설정된 봇 사용)
    $target_bot_name = $branch['br_message_bot_name'];
    $target_device_id = $branch['br_message_device_id'];
    
    // kb_schedule에 등록
    $sql = "INSERT INTO kb_schedule SET
            title = '".sql_real_escape_string($params['title'] ?? dmk_get_message_title($message_type, $params))."',
            description = '".sql_real_escape_string($params['description'] ?? '')."',
            message_type = '".sql_real_escape_string($message_type)."',
            reference_type = '".sql_real_escape_string($params['reference_type'] ?? '')."',
            reference_id = '".sql_real_escape_string($params['reference_id'] ?? '')."',
            created_by_type = 'branch',
            created_by_id = '".sql_real_escape_string($branch['br_id'])."',
            created_by_mb_id = '".sql_real_escape_string($params['mb_id'] ?? 'system')."',
            target_bot_name = '".sql_real_escape_string($target_bot_name)."',
            target_device_id = ".($target_device_id ? "'".sql_real_escape_string($target_device_id)."'" : "NULL").",
            target_room_id = '".sql_real_escape_string($branch['room_id'])."',
            message_text = '".sql_real_escape_string($processed_message)."',
            send_interval_seconds = 1,
            schedule_type = 'once',
            schedule_date = '".date('Y-m-d', strtotime($schedule_datetime))."',
            schedule_time = '".date('H:i:00', strtotime($schedule_datetime))."',
            next_send_at = '".sql_real_escape_string($schedule_datetime)."',
            valid_from = NOW(),
            valid_until = DATE_ADD(NOW(), INTERVAL 7 DAY),
            status = 'active',
            created_at = NOW()";
    
    $result = sql_query($sql);
    
    if ($result) {
        return sql_insert_id();
    }
    
    return false;
}

/**
 * 템플릿 변수 준비
 */
function dmk_prepare_template_variables($message_type, $params) {
    $variables = [];
    
    switch ($message_type) {
        case 'order_placed':
        case 'order_complete':
            // 주문 정보에서 변수 추출
            if (!empty($params['order_id'])) {
                $order = dmk_get_order_info($params['order_id']);
                if ($order) {
                    $variables['핸드폰뒷자리'] = substr($order['od_hp'], -4);
                    $variables['주문자명'] = $order['od_name'];
                    $variables['주문번호'] = $order['od_id'];
                    $variables['주문일시'] = date('Y-m-d H:i', strtotime($order['od_time']));
                    $variables['총금액'] = number_format($order['od_cart_price']).'원';
                    $variables['배송예정일'] = $order['od_hope_date'] ?: date('Y-m-d', strtotime('+1 day'));
                    $variables['상품목록'] = dmk_get_order_items_text($params['order_id']);
                }
            }
            break;
            
        case 'stock_warning':
        case 'stock_out':
            // 상품 정보에서 변수 추출
            if (!empty($params['item_id'])) {
                $item = dmk_get_item_info($params['item_id']);
                if ($item) {
                    $variables['상품명'] = $item['it_name'];
                    $variables['상품코드'] = $item['it_id'];
                    $variables['현재재고'] = $item['it_stock_qty'];
                    $variables['품절임박기준'] = $params['warning_qty'] ?? 10;
                    $variables['입고예정일'] = $params['restock_date'] ?? '미정';
                    $variables['대체상품'] = $params['alternative_items'] ?? '문의 바랍니다';
                    $variables['지점명'] = $params['branch_name'] ?? '';
                }
            }
            break;
    }
    
    return $variables;
}

/**
 * 메시지 템플릿 파싱 (PHP 버전)
 */
function dmk_parse_message_template($template, $variables) {
    $parsed = $template;
    
    foreach ($variables as $key => $value) {
        $parsed = str_replace('{'.$key.'}', $value, $parsed);
    }
    
    return $parsed;
}

/**
 * 메시지 제목 생성
 */
function dmk_get_message_title($message_type, $params) {
    switch ($message_type) {
        case 'order_placed':
            return '상품주문 메시지 - ' . ($params['order_id'] ?? '');
        case 'order_complete':
            return '주문완료 메시지 - ' . ($params['order_id'] ?? '');
        case 'stock_warning':
            return '품절임박 알림 - ' . ($params['item_name'] ?? '');
        case 'stock_out':
            return '품절 알림 - ' . ($params['item_name'] ?? '');
        default:
            return '자동 메시지';
    }
}

/**
 * 주문 정보 조회
 */
function dmk_get_order_info($order_id) {
    global $g5;
    
    $sql = "SELECT * FROM g5_shop_order WHERE od_id = '".sql_real_escape_string($order_id)."'";
    return sql_fetch($sql);
}

/**
 * 주문 상품 목록 텍스트 생성
 */
function dmk_get_order_items_text($order_id) {
    global $g5;
    
    $sql = "SELECT it_name, ct_qty FROM g5_shop_cart 
            WHERE od_id = '".sql_real_escape_string($order_id)."' 
            ORDER BY ct_id";
    $result = sql_query($sql);
    
    $items = [];
    while ($row = sql_fetch_array($result)) {
        $items[] = $row['it_name'] . ' ' . $row['ct_qty'] . '개';
    }
    
    return implode("\n", $items);
}

/**
 * 상품 정보 조회
 */
function dmk_get_item_info($item_id) {
    global $g5;
    
    $sql = "SELECT * FROM g5_shop_item WHERE it_id = '".sql_real_escape_string($item_id)."'";
    return sql_fetch($sql);
}

/**
 * 상품주문 메시지 등록
 */
function dmk_register_order_placed_message($order_id, $branch_id, $mb_id = 'system') {
    return dmk_register_message_schedule('order_placed', [
        'order_id' => $order_id,
        'branch_id' => $branch_id,
        'mb_id' => $mb_id,
        'reference_type' => 'order',
        'reference_id' => $order_id
    ]);
}

/**
 * 주문 완료 메시지 등록
 */
function dmk_register_order_complete_message($order_id, $branch_id, $mb_id = 'system') {
    return dmk_register_message_schedule('order_complete', [
        'order_id' => $order_id,
        'branch_id' => $branch_id,
        'mb_id' => $mb_id,
        'reference_type' => 'order',
        'reference_id' => $order_id
    ]);
}

/**
 * 품절 임박 메시지 등록
 */
function dmk_register_stock_warning_message($item_id, $branch_id, $current_stock, $warning_qty) {
    $item = dmk_get_item_info($item_id);
    if (!$item) return false;
    
    return dmk_register_message_schedule('stock_warning', [
        'item_id' => $item_id,
        'item_name' => $item['it_name'],
        'branch_id' => $branch_id,
        'reference_type' => 'item',
        'reference_id' => $item_id,
        'current_stock' => $current_stock,
        'warning_qty' => $warning_qty
    ]);
}

/**
 * 품절 메시지 등록
 */
function dmk_register_stock_out_message($item_id, $branch_id) {
    $item = dmk_get_item_info($item_id);
    if (!$item) return false;
    
    return dmk_register_message_schedule('stock_out', [
        'item_id' => $item_id,
        'item_name' => $item['it_name'],
        'branch_id' => $branch_id,
        'reference_type' => 'item',
        'reference_id' => $item_id
    ]);
}

/**
 * 메시지 스케줄 취소
 */
function dmk_cancel_message_schedule($schedule_id) {
    $sql = "UPDATE kb_schedule SET status = 'cancelled' 
            WHERE id = ".intval($schedule_id)." 
            AND status = 'active'";
    return sql_query($sql);
}

/**
 * 특정 참조에 대한 활성 스케줄 확인
 */
function dmk_has_active_schedule($reference_type, $reference_id, $message_type = null) {
    $sql = "SELECT COUNT(*) as cnt FROM kb_schedule 
            WHERE reference_type = '".sql_real_escape_string($reference_type)."'
            AND reference_id = '".sql_real_escape_string($reference_id)."'
            AND status = 'active'";
    
    if ($message_type) {
        $sql .= " AND message_type = '".sql_real_escape_string($message_type)."'";
    }
    
    $row = sql_fetch($sql);
    return $row['cnt'] > 0;
}
?>