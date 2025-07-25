<?php
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.log.lib.php');

auth_check($auth[$sub_menu], "w");

$target_branch_id = strip_tags($_POST['target_branch_id']);
$message_content = strip_tags($_POST['message_content']);
$send_type = strip_tags($_POST['send_type']);

$image_url = '';
// 이미지 파일 업로드 처리 (bot_schedule_form_update.php와 동일)
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = G5_DATA_PATH . '/bot_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0707);
        chmod($upload_dir, 0707);
    }
    $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('bot_instant_') . '.' . $ext;
    $dest_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest_path)) {
        $image_url = G5_DATA_URL . '/bot_images/' . $new_file_name;
    } else {
        alert('이미지 업로드에 실패했습니다.');
    }
}

// 여기에 카카오봇 API 호출 로직 구현
// 예: call_kakao_bot_api($target_branch_id, $message_content, $image_url, $send_type);

// 관리자 액션 로그
dmk_log_admin_action(
    'send',
    '봇 즉시 발송: ' . ($target_branch_id ? '지점ID: '.$target_branch_id : '전체'),
    'kb_instant_send',
    json_encode([
        'target_branch_id' => $target_branch_id,
        'send_type' => $send_type,
        'has_image' => !empty($image_url),
        'message_length' => strlen($message_content)
    ]),
    null,
    '180500'
);

alert('메시지가 성공적으로 발송되었습니다.');
goto_url('./bot_instant_send_form.php');
?>