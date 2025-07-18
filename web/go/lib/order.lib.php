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
    $sql = "SELECT br_order_page_skin, br_order_page_skin_options 
            FROM dmk_branch 
            WHERE br_id = '".sql_real_escape_string($branch_id)."'";
    $branch = sql_fetch($sql);
    
    if (!$branch) {
        return array('skin' => 'basic', 'options' => array());
    }
    
    $skin_name = $branch['br_order_page_skin'] ?: 'basic';
    
    // 스킨 옵션 파싱
    $options = array();
    if ($branch['br_order_page_skin_options']) {
        $options = json_decode($branch['br_order_page_skin_options'], true);
        if (!is_array($options)) {
            $options = array();
        }
    }
    
    // 스킨 정보 가져오기
    $skin_info = get_order_skin_info($skin_name);
    if (!$skin_info) {
        return array('skin' => 'basic', 'options' => array());
    }
    
    // 기본값 설정
    if (isset($skin_info['options']) && is_array($skin_info['options'])) {
        foreach ($skin_info['options'] as $key => $option) {
            if (!isset($options[$key]) && isset($option['default'])) {
                $options[$key] = $option['default'];
            }
        }
    }
    
    return array(
        'skin' => $skin_name,
        'options' => $options,
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