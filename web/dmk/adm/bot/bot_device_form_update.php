<?php
/**
 * 클라이언트 봇 수정 처리
 */

include_once('./_common.php');

auth_check('180300', 'w');

check_admin_token();

$w = $_POST['w'];
$device_id = $_POST['device_id'];

if (!$device_id) {
    alert('디바이스 ID가 없습니다.');
}

// 기존 디바이스 정보 확인
$sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
$device = sql_fetch($sql);
if (!$device['id']) {
    alert('등록된 디바이스가 아닙니다.');
}

// 입력값 검증
if (!$_POST['status']) {
    alert('상태를 선택해주세요.');
}

// 데이터 준비
$status = $_POST['status'];
$description = $_POST['description'];
$rejection_reason = $_POST['rejection_reason'];

// 상태 변경 로그용 데이터
$old_status = $device['status'];
$status_changed = ($old_status != $status);

// 디바이스 정보 업데이트
$sql = " UPDATE kb_bot_devices SET 
         status = '$status',
         description = '$description',
         rejection_reason = '$rejection_reason',
         updated_at = NOW() ";

// 상태가 승인으로 변경되는 경우 승인 시간 기록
if ($status == 'approved' && $old_status != 'approved') {
    $sql .= ", approved_at = NOW() ";
}

$sql .= " WHERE id = '$device_id' ";

sql_query($sql);

// 상태 변경 로그 기록
if ($status_changed) {
    $status_names = [
        'pending' => '승인 대기',
        'approved' => '승인됨', 
        'rejected' => '거부됨',
        'suspended' => '일시정지',
        'blocked' => '차단됨'
    ];
    
    $old_status_name = $status_names[$old_status] ?? $old_status;
    $new_status_name = $status_names[$status] ?? $status;
    
    dmk_admin_log('봇 디바이스 상태 변경', 
        "디바이스ID: {$device_id}, 봇명: {$device['bot_name']}, " .
        "상태: {$old_status_name} → {$new_status_name}"
    );
    
    // 승인된 경우 추가 처리
    if ($status == 'approved') {
        // TODO: 필요시 승인 알림 등 추가 처리
    }
    
    // 차단된 경우 추가 처리
    if ($status == 'blocked') {
        // TODO: 필요시 연결 강제 해제 등 추가 처리
    }
} else {
    dmk_admin_log('봇 디바이스 정보 수정', 
        "디바이스ID: {$device_id}, 봇명: {$device['bot_name']}"
    );
}

goto_url('./bot_device_list.php');
?>