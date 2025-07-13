<?php
/**
<<<<<<< HEAD
 * 클라이언트 봇 수정 처리 (상태, 설명만)
 */

$sub_menu = "180300";
=======
 * 클라이언트 봇 수정 처리
 */

>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
include_once('./_common.php');

auth_check('180300', 'w');

check_admin_token();

$w = $_POST['w'];
$device_id = $_POST['device_id'];
<<<<<<< HEAD
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
=======

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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
?>