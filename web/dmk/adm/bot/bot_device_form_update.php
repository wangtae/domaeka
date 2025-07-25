<?php
/**
 * 클라이언트 봇 수정 처리
 */

$sub_menu = "180300";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.log.lib.php');

auth_check('180300', 'w');

check_admin_token();

$w = $_POST['w'];
$device_id = $_POST['device_id'];

if (!$device_id) {
    alert('디바이스 ID가 없습니다.');
}

// 기존 디바이스 정보 확인 (수정 전 데이터를 로그용으로 보관)
$sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
$device = sql_fetch($sql);
if (!$device['id']) {
    alert('등록된 디바이스가 아닙니다.');
}

// 로그용 기존 데이터
$old_data = [
    'id' => $device['id'],
    'bot_name' => $device['bot_name'],
    'device_id' => $device['device_id'],
    'status' => $device['status'],
    'descryption' => $device['descryption'],
    'image_resize_enabled' => $device['image_resize_enabled'],
    'image_resize_width' => $device['image_resize_width']
];

// 입력값 검증
if (!$_POST['status']) {
    alert('상태를 선택해주세요.');
}

// 데이터 준비
$status = sql_escape_string($_POST['status']);
$description = sql_escape_string($_POST['description']);
$image_resize_enabled = isset($_POST['image_resize_enabled']) ? 1 : 0;
$image_resize_width = (int)$_POST['image_resize_width'];

// 이미지 리사이징 크기 검증
if ($image_resize_width < 100 || $image_resize_width > 2000) {
    $image_resize_width = 900; // 기본값
}

// 디버깅 - POST 값 확인
error_log("POST image_resize_width: " . $_POST['image_resize_width']);
error_log("Processed image_resize_width: " . $image_resize_width);
error_log("image_resize_enabled: " . $image_resize_enabled);

// 상태 변경 로그용 데이터
$old_status = $device['status'];
$status_changed = ($old_status != $status);

// 디바이스 정보 업데이트
$sql = " UPDATE kb_bot_devices SET 
         status = '$status',
         descryption = '$description',
         image_resize_enabled = '$image_resize_enabled',
         image_resize_width = '$image_resize_width',
         updated_at = NOW() ";

// 상태가 승인으로 변경되는 경우 승인 시간 기록
if ($status == 'approved' && $old_status != 'approved') {
    $sql .= ", approved_at = NOW() ";
}

$sql .= " WHERE id = '$device_id' ";

// 디버깅 - 실행될 SQL 쿼리 확인
error_log("SQL Query: " . $sql);

sql_query($sql);

// 업데이트 후 값 확인
$check_sql = "SELECT image_resize_width, image_resize_enabled FROM kb_bot_devices WHERE id = '$device_id'";
$check_result = sql_fetch($check_sql);
error_log("After update - image_resize_width: " . $check_result['image_resize_width'] . ", image_resize_enabled: " . $check_result['image_resize_enabled']);

// 새로운 데이터 구성
$new_data = [
    'id' => $device_id,
    'bot_name' => $device['bot_name'], // 변경되지 않는 데이터
    'device_id' => $device['device_id'], // 변경되지 않는 데이터
    'status' => $status,
    'descryption' => $description,
    'image_resize_enabled' => $image_resize_enabled,
    'image_resize_width' => $image_resize_width
];

// 관리자 액션 로그 (향상된 변경 내용 추적)
dmk_log_update_action('봇 디바이스 수정', '봇명: '.$device['bot_name'].' (ID: '.$device_id.')', $new_data, $old_data, '180300', 'kb_bot_devices');

// 승인된 경우 추가 처리
if ($status == 'approved' && $old_status != 'approved') {
    // TODO: 필요시 승인 알림 등 추가 처리
}

// 차단된 경우 추가 처리
if ($status == 'blocked') {
    // TODO: 필요시 연결 강제 해제 등 추가 처리
}

goto_url('./bot_device_list.php');
?>