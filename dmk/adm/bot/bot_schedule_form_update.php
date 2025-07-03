<?php
include_once('../../_common.php');

auth_check($auth[$sub_menu], "w");

$sch_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$w = isset($_POST['w']) ? $_POST['w'] : '';

if ($w == 'd') {
    // 삭제
    sql_query("DELETE FROM kb_schedule WHERE id = '$sch_id'");
    goto_url('./bot_schedule_list.php');
}

$target_branch_id = strip_tags($_POST['target_branch_id']);
$message_content = strip_tags($_POST['message_content']);
$send_time = strip_tags($_POST['send_time']);
$send_days = strip_tags($_POST['send_days']);
$send_date = strip_tags($_POST['send_date']);
$status = strip_tags($_POST['status']);

$image_url = '';
// 이미지 파일 업로드 처리
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = G5_DATA_PATH . '/bot_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0707);
        chmod($upload_dir, 0707);
    }
    $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('bot_') . '.' . $ext;
    $dest_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest_path)) {
        $image_url = G5_DATA_URL . '/bot_images/' . $new_file_name;
    } else {
        alert('이미지 업로드에 실패했습니다.');
    }
}

// 기존 이미지 삭제 요청 처리
if (isset($_POST['image_file_del']) && $_POST['image_file_del'] == '1') {
    $old_schedule = sql_fetch("SELECT image_url FROM kb_schedule WHERE id = '$sch_id'");
    if ($old_schedule && $old_schedule['image_url']) {
        $old_file_path = str_replace(G5_DATA_URL, G5_DATA_PATH, $old_schedule['image_url']);
        if (file_exists($old_file_path)) {
            unlink($old_file_path);
        }
    }
    $image_url = ''; // 이미지 URL 초기화
}

if ($w == 'c') {
    // 생성
    $sql = " INSERT INTO kb_schedule
                SET target_branch_id = '$target_branch_id',
                    message_content = '$message_content',
                    send_time = '$send_time',
                    send_days = '$send_days',
                    send_date = '$send_date',
                    status = '$status',
                    image_url = '$image_url',
                    reg_datetime = NOW() ";
    sql_query($sql);
} else if ($w == 'u') {
    // 수정
    $sql = " UPDATE kb_schedule
                SET target_branch_id = '$target_branch_id',
                    message_content = '$message_content',
                    send_time = '$send_time',
                    send_days = '$send_days',
                    send_date = '$send_date',
                    status = '$status',
                    image_url = '$image_url',
                    upd_datetime = NOW()
                WHERE id = '$sch_id' ";
    sql_query($sql);
}

goto_url('./bot_schedule_list.php');
?>