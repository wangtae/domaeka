<?php
/**
 * Base64 원본 이미지 데이터 가져오기 AJAX 엔드포인트
 */

$sub_menu = "180600";
include_once('./_common.php');

header('Content-Type: application/json');

// 권한 체크
auth_check('180600', 'r');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$group = isset($_POST['group']) ? (int)$_POST['group'] : 0;
$index = isset($_POST['index']) ? (int)$_POST['index'] : 0;

if (!$id || !in_array($group, [1, 2])) {
    die(json_encode(['success' => false, 'error' => 'Invalid parameters']));
}

// 스케줄 정보 조회
$sql = "SELECT message_images_{$group}, image_storage_mode FROM kb_schedule WHERE id = '$id'";
$schedule = sql_fetch($sql);

if (!$schedule) {
    die(json_encode(['success' => false, 'error' => 'Schedule not found']));
}

// 권한 체크 (본인이 만든 스케줄인지 확인)
$user_info = dmk_get_admin_auth();
if (!$user_info['is_super']) {
    $sql_auth = "SELECT created_by_type, created_by_id FROM kb_schedule WHERE id = '$id'";
    $auth_check = sql_fetch($sql_auth);
    
    $has_permission = false;
    
    if ($user_info['mb_level'] == 8 && $auth_check['created_by_type'] == 'distributor' && $auth_check['created_by_id'] == $user_info['dt_id']) {
        $has_permission = true;
    } elseif ($user_info['mb_level'] == 6 && $auth_check['created_by_type'] == 'agency' && $auth_check['created_by_id'] == $user_info['ag_id']) {
        $has_permission = true;
    } elseif ($user_info['mb_level'] == 4 && $auth_check['created_by_type'] == 'branch' && $auth_check['created_by_id'] == $user_info['br_id']) {
        $has_permission = true;
    }
    
    if (!$has_permission) {
        die(json_encode(['success' => false, 'error' => 'Permission denied']));
    }
}

// Base64 방식인지 확인
if ($schedule['image_storage_mode'] !== 'base64') {
    die(json_encode(['success' => false, 'error' => 'Not base64 storage mode']));
}

// 이미지 데이터 가져오기
$images_json = $schedule["message_images_{$group}"];
if (!$images_json) {
    die(json_encode(['success' => false, 'error' => 'No images found']));
}

$images = json_decode($images_json, true);
if (!$images || !isset($images[$index])) {
    die(json_encode(['success' => false, 'error' => 'Image not found at index']));
}

$image = $images[$index];
if (!isset($image['base64'])) {
    die(json_encode(['success' => false, 'error' => 'No base64 data found']));
}

// 응답
echo json_encode([
    'success' => true,
    'base64' => $image['base64'],
    'name' => $image['name'] ?? 'image.jpg',
    'size' => $image['size'] ?? 0
]);
?>