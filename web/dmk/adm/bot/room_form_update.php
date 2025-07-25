<?php
/**
 * 채팅방 수정 처리
 */

$sub_menu = "180500";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.log.lib.php');

auth_check('180500', 'w');

check_admin_token();

$w = $_POST['w'];
$room_id = $_POST['room_id'];
$bot_name = $_POST['bot_name'];
$device_id = $_POST['device_id'];

if (!$room_id) {
    alert('방 ID가 없습니다.');
}

// 기존 채팅방 정보 확인 - 새로운 PRIMARY KEY 구조
$room_id_esc = sql_real_escape_string($room_id);
if ($bot_name && $device_id) {
    $bot_name_esc = sql_real_escape_string($bot_name);
    $device_id_esc = sql_real_escape_string($device_id);
    $sql = " SELECT * FROM kb_rooms WHERE room_id = '{$room_id_esc}' 
             AND bot_name = '{$bot_name_esc}' AND device_id = '{$device_id_esc}' ";
} else {
    // 기존 방식 (마이그레이션 전 호환성)
    $sql = " SELECT * FROM kb_rooms WHERE room_id = '{$room_id_esc}' ";
}
$room = sql_fetch($sql);
if (!$room['room_id']) {
    alert('등록된 채팅방이 아닙니다.');
}

// 로그용 기존 데이터
$old_data = [
    'room_id' => $room['room_id'],
    'bot_name' => $room['bot_name'],
    'device_id' => $room['device_id'],
    'room_name' => $room['room_name'],
    'status' => $room['status'],
    'description' => $room['description'],
    'owner_id' => $room['owner_id'],
    'log_settings' => $room['log_settings']
];

// 입력값 검증
if (!$_POST['status']) {
    alert('상태를 선택해주세요.');
}

// 데이터 준비
$status = sql_real_escape_string($_POST['status']);
$description = sql_real_escape_string($_POST['description']);
// rejection_reason 필드는 현재 테이블에 없으므로 제외
// $rejection_reason = isset($_POST['rejection_reason']) ? sql_real_escape_string($_POST['rejection_reason']) : '';

// 로그 설정 준비
$log_settings = [];
$log_settings['enabled'] = $_POST['log_enabled'] ? true : false;
$log_settings['retention_days'] = $_POST['log_retention_days'] ? (int)$_POST['log_retention_days'] : 30;
$log_settings_json = sql_real_escape_string(json_encode($log_settings, JSON_UNESCAPED_UNICODE));

// 배정 정보 처리 - 지점만 저장
$owner_id = null;

$user_info = dmk_get_admin_auth();
if ($user_info['is_super'] || $user_info['mb_type'] == 'distributor' || $user_info['mb_type'] == 'agency') {
    // 지점이 선택된 경우에만 owner_id 설정
    if (!empty($_POST['br_id'])) {
        $owner_id = $_POST['br_id'];
    }
    // 지점이 선택되지 않은 경우 owner_id는 null (빈값으로 저장됨)
}

// 상태 변경 로그용 데이터
$old_status = $room['status'];
$status_changed = ($old_status != $status);

// 채팅방 정보 업데이트
$sql = " UPDATE kb_rooms SET 
         status = '{$status}',
         description = '{$description}',
         log_settings = '{$log_settings_json}',
         updated_at = NOW() ";

// owner_id 업데이트 (지점이 선택된 경우에만, 아니면 NULL로 설정)
if ($owner_id) {
    $owner_id_esc = sql_real_escape_string($owner_id);
    $sql .= ", owner_id = '{$owner_id_esc}' ";
} else {
    $sql .= ", owner_id = NULL ";
}

// rejection_reason과 approved_at 필드는 현재 테이블에 없으므로 제외
// 나중에 006_fix_kb_rooms_table.sql 실행 후 활성화 필요

// WHERE 절 - 새로운 PRIMARY KEY 구조
if ($bot_name && $device_id) {
    $sql .= " WHERE room_id = '{$room_id_esc}' AND bot_name = '{$bot_name_esc}' AND device_id = '{$device_id_esc}' ";
} else {
    // 기존 방식 (마이그레이션 전 호환성)
    $sql .= " WHERE room_id = '{$room_id_esc}' ";
}

$result = sql_query($sql);

if (!$result) {
    alert('채팅방 정보 수정 중 오류가 발생했습니다.');
}

// 새로운 데이터 구성
$new_data = [
    'room_id' => $room_id,
    'bot_name' => $room['bot_name'], // 변경되지 않는 데이터
    'device_id' => $room['device_id'], // 변경되지 않는 데이터
    'room_name' => $room['room_name'], // 변경되지 않는 데이터
    'status' => $status,
    'description' => $description,
    'owner_id' => $owner_id,
    'log_settings' => $log_settings_json
];

// 관리자 액션 로그 (향상된 변경 내용 추적)
dmk_log_update_action('채팅방 수정', '방명: '.$room['room_name'].' (ID: '.$room_id.')', $new_data, $old_data, '180500', 'kb_rooms');

// 승인된 경우 추가 처리
if ($status == 'approved' && $old_status != 'approved') {
    // TODO: 필요시 승인 알림 등 추가 처리
}

// 차단된 경우 추가 처리
if ($status == 'blocked') {
    // TODO: 필요시 해당 채팅방 봇 기능 비활성화 등 추가 처리
}

// 목록 페이지로 이동
goto_url('./room_list.php');
?>