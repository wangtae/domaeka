<?php
/**
 * 주문 페이지 관련 라이브러리
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 주문 페이지 스킨 정보 가져오기
 */
function get_order_skin_info($skin_name) {
    $skin_path = G5_PATH . '/go/skin/order/' . $skin_name;
    
    if (!is_dir($skin_path)) {
        return false;
    }
    
    $config_file = $skin_path . '/skin.config.php';
    if (!file_exists($config_file)) {
        return false;
    }
    
    include($config_file);
    
    if (!isset($skin_info)) {
        return false;
    }
    
    return $skin_info;
}

/**
 * 주문 페이지 스킨 옵션 가져오기
 */
function get_order_skin_config($branch_id) {
    global $g5;
    
    // 지점 정보에서 스킨 설정 가져오기
    $sql = "SELECT br_order_page_skin 
            FROM dmk_branch 
            WHERE br_id = '".sql_real_escape_string($branch_id)."'";
    $branch = sql_fetch($sql);
    
    if (!$branch || !$branch['br_order_page_skin']) {
        return array('skin' => 'basic_1col', 'options' => array());
    }
    
    $skin_name = $branch['br_order_page_skin'];
    
    // 기존 basic 스킨이면 basic_1col로 변경
    if ($skin_name == 'basic') {
        $skin_name = 'basic_1col';
    }
    
    // 스킨 정보 가져오기
    $skin_info = get_order_skin_info($skin_name);
    if (!$skin_info) {
        return array('skin' => 'basic_1col', 'options' => array());
    }
    
    return array(
        'skin' => $skin_name,
        'options' => array(), // 옵션은 이제 사용하지 않음
        'info' => $skin_info
    );
}

/**
 * 주문 페이지 스킨 경로 가져오기
 */
function get_order_skin_path($skin_name, $is_url = false) {
    if ($is_url) {
        return G5_URL . '/go/skin/order/' . $skin_name;
    } else {
        return G5_PATH . '/go/skin/order/' . $skin_name;
    }
}
?>