<?php
/**
 * 채팅방 수정 처리 (상태, 설명만)
 */

$sub_menu = "180500";
include_once('./_common.php');

auth_check('180500', 'w');

check_admin_token();

$w = $_POST['w'];
$room_id = $_POST['room_id'];
$status = $_POST['status'];
$description = $_POST['description'];

// 필수 값 검증
if (!$room_id) {
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

// 채팅방 존재 여부 확인
$room_check = sql_fetch("SELECT room_id FROM kb_rooms WHERE room_id = '".sql_real_escape_string($room_id)."'");
if (!$room_check) {
    alert('존재하지 않는 채팅방입니다.');
}

if ($w == 'u' && $room_id) {
    // 수정
    $sql = " UPDATE kb_rooms SET 
                status = '$status',
                description = '$description',
                updated_at = NOW()
             WHERE room_id = '".sql_real_escape_string($room_id)."' ";
    
    sql_query($sql);
    
    alert('수정되었습니다.', './room_list.php');
    
} else {
    alert('잘못된 접근입니다.');
}
?>