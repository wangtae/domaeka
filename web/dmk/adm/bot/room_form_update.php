<?php
/**
 * 채팅방 수정 처리
 */

include_once('./_common.php');

auth_check('180500', 'w');

check_admin_token();

$w = $_POST['w'];
$room_id = $_POST['room_id'];

if (!$room_id) {
    alert('방 ID가 없습니다.');
}

// 기존 채팅방 정보 확인
$sql = " SELECT * FROM kb_rooms WHERE room_id = '$room_id' ";
$room = sql_fetch($sql);
if (!$room['room_id']) {
    alert('등록된 채팅방이 아닙니다.');
}

// 입력값 검증
if (!$_POST['status']) {
    alert('상태를 선택해주세요.');
}

// 데이터 준비
$status = $_POST['status'];
$description = $_POST['description'];
$rejection_reason = $_POST['rejection_reason'];

// 로그 설정 준비
$log_settings = [];
$log_settings['enabled'] = $_POST['log_enabled'] ? true : false;
$log_settings['retention_days'] = $_POST['log_retention_days'] ? (int)$_POST['log_retention_days'] : 30;
$log_settings_json = json_encode($log_settings, JSON_UNESCAPED_UNICODE);

// 상태 변경 로그용 데이터
$old_status = $room['status'];
$status_changed = ($old_status != $status);

// 채팅방 정보 업데이트
$sql = " UPDATE kb_rooms SET 
         status = '$status',
         description = '$description',
         rejection_reason = '$rejection_reason',
         log_settings = '$log_settings_json',
         updated_at = NOW() ";

// 상태가 승인으로 변경되는 경우 승인 시간 기록
if ($status == 'approved' && $old_status != 'approved') {
    $sql .= ", approved_at = NOW() ";
}

$sql .= " WHERE room_id = '$room_id' ";

sql_query($sql);

// 상태 변경 로그 기록
if ($status_changed) {
    $status_names = [
        'pending' => '승인 대기',
        'approved' => '승인됨', 
        'denied' => '거부됨',
        'revoked' => '취소됨',
        'blocked' => '차단됨'
    ];
    
    $old_status_name = $status_names[$old_status] ?? $old_status;
    $new_status_name = $status_names[$status] ?? $status;
    
    dmk_admin_log('채팅방 상태 변경', 
        "방ID: {$room_id}, 방명: {$room['room_name']}, " .
        "상태: {$old_status_name} → {$new_status_name}"
    );
    
    // 승인된 경우 추가 처리
    if ($status == 'approved') {
        // TODO: 필요시 승인 알림 등 추가 처리
    }
    
    // 차단된 경우 추가 처리
    if ($status == 'blocked') {
        // TODO: 필요시 해당 채팅방 봇 기능 비활성화 등 추가 처리
    }
} else {
    dmk_admin_log('채팅방 정보 수정', 
        "방ID: {$room_id}, 방명: {$room['room_name']}"
    );
}

goto_url('./room_list.php');
?>