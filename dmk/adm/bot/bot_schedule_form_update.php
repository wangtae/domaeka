<?php
/**
 * 스케줄 폼 데이터 처리 페이지
 * 
 * 스케줄 등록/수정 폼에서 전송된 데이터를 처리하고 데이터베이스에 저장합니다.
 * 이미지 파일 업로드 처리도 포함됩니다.
 */

require_once '../../../adm/_common.php';
require_once G5_DMK_PATH . '/adm/lib/common.lib.php';
require_once G5_DMK_PATH . '/adm/bot/lib/bot.lib.php';

// 관리자 권한 확인
$auth = dmk_get_admin_auth();

// 스케줄링 기능 권한 확인
if (!kb_check_function_permission('schedule', $auth)) {
    alert('스케줄링 발송 관리 권한이 없습니다.');
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    exit;
}

// 필수 필드 검사
$required_fields = ['title', 'message', 'br_id', 'schedule_type', 'target_type', 'status'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        alert($field . ' 필드는 필수입니다.');
        exit;
    }
}

// 데이터 받기
$id = intval($_POST['id']);
$title = trim($_POST['title']);
$message = trim($_POST['message']);
$br_id = trim($_POST['br_id']);
$schedule_type = trim($_POST['schedule_type']);
$target_type = trim($_POST['target_type']);
$status = trim($_POST['status']);

// 스케줄 타입별 시간 정보 처리
$schedule_time = '';
$schedule_date = '';
$schedule_weekdays = '';

switch ($schedule_type) {
    case 'daily':
        if (empty($_POST['daily_time'])) {
            alert('발송 시간을 입력해주세요.');
            exit;
        }
        $schedule_time = $_POST['daily_time'];
        break;
        
    case 'weekly':
        if (empty($_POST['weekly_time'])) {
            alert('발송 시간을 입력해주세요.');
            exit;
        }
        if (empty($_POST['weekdays'])) {
            alert('발송 요일을 선택해주세요.');
            exit;
        }
        $schedule_time = $_POST['weekly_time'];
        $schedule_weekdays = implode(',', $_POST['weekdays']);
        break;
        
    case 'once':
        if (empty($_POST['once_date']) || empty($_POST['once_time'])) {
            alert('발송 날짜와 시간을 입력해주세요.');
            exit;
        }
        
        // 과거 날짜 체크
        $selected_datetime = $_POST['once_date'] . ' ' . $_POST['once_time'];
        if (strtotime($selected_datetime) < time()) {
            alert('과거 날짜와 시간은 선택할 수 없습니다.');
            exit;
        }
        
        $schedule_date = $_POST['once_date'];
        $schedule_time = $_POST['once_time'];
        break;
        
    default:
        alert('올바른 스케줄 유형을 선택해주세요.');
        exit;
}

// 지점 권한 확인
if (!kb_check_branch_permission($br_id, $auth)) {
    alert('해당 지점에 대한 권한이 없습니다.');
    exit;
}

// 수정 모드일 경우 기존 데이터 확인
if ($id > 0) {
    $existing_sql = "SELECT * FROM " . KB_TABLE_PREFIX . "schedule WHERE id = " . $id;
    $existing_result = sql_query($existing_sql);
    
    if (!$existing_result || sql_num_rows($existing_result) === 0) {
        alert('수정할 스케줄을 찾을 수 없습니다.');
        exit;
    }
    
    $existing_schedule = sql_fetch_array($existing_result);
    
    // 권한 재확인
    if (!kb_check_branch_permission($existing_schedule['br_id'], $auth)) {
        alert('해당 스케줄을 수정할 권한이 없습니다.');
        exit;
    }
}

// 이미지 파일 업로드 처리
$image_url = '';
$upload_dir = G5_DATA_PATH . '/bot_images/';
$upload_url = G5_DATA_URL . '/bot_images/';

// 업로드 디렉터리 생성
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['image_file'];
    
    // 파일 크기 체크 (5MB)
    if ($uploaded_file['size'] > 5 * 1024 * 1024) {
        alert('이미지 파일 크기는 5MB를 초과할 수 없습니다.');
        exit;
    }
    
    // 파일 확장자 체크
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        alert('JPG, PNG, GIF 파일만 업로드 가능합니다.');
        exit;
    }
    
    // 파일명 생성 (시간 + 랜덤 문자열)
    $new_filename = date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // 파일 이동
    if (move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
        $image_url = $upload_url . $new_filename;
        
        // 기존 이미지 삭제 (수정 모드일 경우)
        if ($id > 0 && !empty($existing_schedule['image_url'])) {
            $old_image_path = str_replace($upload_url, $upload_dir, $existing_schedule['image_url']);
            if (file_exists($old_image_path)) {
                unlink($old_image_path);
            }
        }
    } else {
        alert('이미지 업로드에 실패했습니다.');
        exit;
    }
} else {
    // 수정 모드일 경우 기존 이미지 URL 유지
    if ($id > 0) {
        $image_url = $existing_schedule['image_url'];
    }
}

// 스케줄 데이터 배열 생성
$schedule_data = [
    'id' => $id,
    'title' => $title,
    'message' => $message,
    'image_url' => $image_url,
    'br_id' => $br_id,
    'schedule_type' => $schedule_type,
    'schedule_time' => $schedule_time,
    'schedule_date' => $schedule_date,
    'schedule_weekdays' => $schedule_weekdays,
    'target_type' => $target_type,
    'status' => $status
];

// 데이터베이스 저장
$result = kb_save_schedule($schedule_data);

if ($result['success']) {
    $message = $id > 0 ? '스케줄이 수정되었습니다.' : '스케줄이 등록되었습니다.';
    alert($message, 'bot_schedule_list.php');
} else {
    alert($result['message']);
}

exit;
?>