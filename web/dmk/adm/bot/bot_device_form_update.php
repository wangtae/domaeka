<?php
/**
 * 클라이언트 봇 수정 처리 (상태, 설명만)
 */

$sub_menu = "180300";
include_once('./_common.php');

auth_check('180300', 'w');

check_admin_token();

$w = $_POST['w'];
$device_id = $_POST['device_id'];
$status = $_POST['status'];
$description = $_POST['description'];

// 필수 값 검증
if (!$device_id) {
    alert('잘못된 접근입니다.');
}

if (!$status) {
    alert('상태를 선택하세요.');
}

// 유효한 상태값 검증
$valid_statuses = array('pending', 'approved', 'denied', 'revoked', 'blocked');
if (!in_array($status, $valid_statuses)) {
    alert('올바르지 않은 상태값입니다.');
}

// 디바이스 존재 여부 확인
$device_check = sql_fetch("SELECT id FROM kb_bot_devices WHERE id = '$device_id'");
if (!$device_check) {
    alert('존재하지 않는 디바이스입니다.');
}

if ($w == 'u' && $device_id) {
    // 수정
    $sql = " UPDATE kb_bot_devices SET 
                status = '$status',
                description = '$description',
                updated_at = NOW()
             WHERE id = '$device_id' ";
    
    sql_query($sql);
    
    alert('수정되었습니다.', './bot_device_list.php');
    
} else {
    alert('잘못된 접근입니다.');
}
?>