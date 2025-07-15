<?php
/**
 * 스케줄링 발송 등록/수정 처리
 */

$sub_menu = "180600";
include_once('./_common.php');

auth_check('180600', 'w');

check_admin_token();

$w = $_POST['w'];
$id = $_POST['id'];

// 입력값 검증
if (!$_POST['title']) {
    alert('제목을 입력해주세요.');
}

if (!$_POST['target_bot_name']) {
    alert('대상 봇을 선택해주세요.');
}

if (!$_POST['target_room_id']) {
    alert('대상 톡방을 선택해주세요.');
}

if (!$_POST['schedule_type']) {
    alert('스케줄 타입을 선택해주세요.');
}

if (empty($_POST['schedule_times']) || !is_array($_POST['schedule_times'])) {
    alert('발송 시간을 입력해주세요.');
}

// 발송 시간 유효성 검사
$valid_times = array_filter($_POST['schedule_times'], function($time) {
    return !empty(trim($time));
});

if (empty($valid_times)) {
    alert('유효한 발송 시간을 입력해주세요.');
}

// 스케줄 타입별 검증
if ($_POST['schedule_type'] == 'once' && !$_POST['schedule_date']) {
    alert('1회성 발송의 경우 발송 날짜를 입력해주세요.');
}

if ($_POST['schedule_type'] == 'weekly' && empty($_POST['schedule_weekdays'])) {
    alert('주간 반복의 경우 요일을 하나 이상 선택해주세요.');
}

// 메시지 내용 확인
$has_text = trim($_POST['message_text']) != '';
$has_images_1 = !empty($_POST['existing_images_1']) || !empty($_FILES['new_images_1']['name'][0]);
$has_images_2 = !empty($_POST['existing_images_2']) || !empty($_FILES['new_images_2']['name'][0]);

if (!$has_text && !$has_images_1 && !$has_images_2) {
    alert('텍스트 메시지 또는 이미지를 하나 이상 입력해주세요.');
}

// 유효기간 검증
if (!$_POST['valid_from']) {
    alert('유효기간 시작일을 입력해주세요.');
}

if (!$_POST['valid_until']) {
    alert('유효기간 종료일을 입력해주세요.');
}

$valid_from = $_POST['valid_from'];
$valid_until = $_POST['valid_until'];

if (strtotime($valid_from) >= strtotime($valid_until)) {
    alert('유효기간 종료일은 시작일보다 이후여야 합니다.');
}

// 데이터 준비
$title = $_POST['title'];
$description = $_POST['description'];
$target_bot_name = $_POST['target_bot_name'];
$target_room_id = $_POST['target_room_id'];
$message_text = $_POST['message_text'];
$send_interval_seconds = $_POST['send_interval_seconds'] ? $_POST['send_interval_seconds'] : 1;
$media_wait_time_1 = $_POST['media_wait_time_1'] ? $_POST['media_wait_time_1'] : 0;
$media_wait_time_2 = $_POST['media_wait_time_2'] ? $_POST['media_wait_time_2'] : 0;
$schedule_type = $_POST['schedule_type'];
$schedule_date = $_POST['schedule_date'] ? $_POST['schedule_date'] : null;
$schedule_times = json_encode(array_values($valid_times));  // 복수 시간을 JSON으로 저장
$schedule_time = $valid_times[0];  // 첫 번째 시간 (호환성을 위해 유지)
$schedule_weekdays = !empty($_POST['schedule_weekdays']) ? implode(',', $_POST['schedule_weekdays']) : null;
$status = $_POST['status'] ? $_POST['status'] : 'active';

// 사용자 정보
$user_info = dmk_get_admin_auth($member['mb_id']);
$created_by_type = $user_info['mb_type'];
$created_by_id = $user_info['key'];
$created_by_mb_id = $member['mb_id'];

// 이미지 업로드 디렉토리
$upload_dir = G5_DATA_PATH.'/schedule';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, G5_DIR_PERMISSION);
    @chmod($upload_dir, G5_DIR_PERMISSION);
}

// 이미지 처리 함수
function process_schedule_images($group_num, $existing_images, $new_files) {
    global $upload_dir;
    
    $images = [];
    
    // 기존 이미지 처리
    if (!empty($existing_images)) {
        foreach ($existing_images as $file) {
            $images[] = array('file' => $file);
        }
    }
    
    // 새 이미지 업로드
    if (!empty($new_files['name'][0])) {
        for ($i = 0; $i < count($new_files['name']); $i++) {
            if ($new_files['error'][$i] == 0) {
                $filename = time() . '_' . $group_num . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $new_files['name'][$i]);
                $filepath = $upload_dir . '/' . $filename;
                
                if (move_uploaded_file($new_files['tmp_name'][$i], $filepath)) {
                    $images[] = array('file' => $filename);
                }
            }
        }
    }
    
    return $images;
}

// 이미지 그룹 처리
$message_images_1 = process_schedule_images(1, $_POST['existing_images_1'] ?? [], $_FILES['new_images_1'] ?? []);
$message_images_2 = process_schedule_images(2, $_POST['existing_images_2'] ?? [], $_FILES['new_images_2'] ?? []);

// JSON 인코딩
$message_images_1_json = !empty($message_images_1) ? json_encode($message_images_1) : null;
$message_images_2_json = !empty($message_images_2) ? json_encode($message_images_2) : null;

// 다음 발송 시간 계산 (복수 시간 지원)
function calculate_next_send_time($schedule_type, $schedule_date, $schedule_times_json, $schedule_weekdays, $valid_from, $valid_until) {
    $now = time();
    $valid_from_ts = strtotime($valid_from);
    $valid_until_ts = strtotime($valid_until);
    
    // JSON에서 시간 배열 추출
    $times = json_decode($schedule_times_json, true);
    if (empty($times)) {
        return null;
    }
    
    // 시간 정렬
    sort($times);
    
    if ($schedule_type == 'once') {
        // 1회성 - 모든 시간 중 가장 빠른 미래 시간 찾기
        foreach ($times as $time) {
            $send_datetime = strtotime($schedule_date . ' ' . $time);
            if ($send_datetime >= $now && $send_datetime >= $valid_from_ts && $send_datetime <= $valid_until_ts) {
                return date('Y-m-d H:i:s', $send_datetime);
            }
        }
        return null;
    } else if ($schedule_type == 'daily') {
        // 매일 반복 - 오늘 또는 내일의 가장 빠른 시간
        
        // 오늘 확인
        foreach ($times as $time) {
            $today_send = strtotime(date('Y-m-d') . ' ' . $time);
            if ($today_send >= $now && $today_send >= $valid_from_ts && $today_send <= $valid_until_ts) {
                return date('Y-m-d H:i:s', $today_send);
            }
        }
        
        // 내일 확인
        foreach ($times as $time) {
            $tomorrow_send = strtotime(date('Y-m-d', strtotime('+1 day')) . ' ' . $time);
            if ($tomorrow_send >= $valid_from_ts && $tomorrow_send <= $valid_until_ts) {
                return date('Y-m-d H:i:s', $tomorrow_send);
            }
        }
    } else if ($schedule_type == 'weekly' && $schedule_weekdays) {
        // 주간 반복
        $weekdays_arr = explode(',', $schedule_weekdays);
        
        // 오늘부터 7일간 체크
        for ($i = 0; $i < 7; $i++) {
            $check_date = strtotime("+$i days");
            $weekday_name = strtolower(date('l', $check_date));
            
            if (in_array($weekday_name, $weekdays_arr)) {
                foreach ($times as $time) {
                    $send_datetime = strtotime(date('Y-m-d', $check_date) . ' ' . $time);
                    if ($send_datetime >= $now && $send_datetime >= $valid_from_ts && $send_datetime <= $valid_until_ts) {
                        return date('Y-m-d H:i:s', $send_datetime);
                    }
                }
            }
        }
    }
    
    return null;
}

$next_send_at = calculate_next_send_time($schedule_type, $schedule_date, $schedule_times, $schedule_weekdays, $valid_from, $valid_until);

if ($w == '' || $w == 'a') {
    // 등록
    $sql = " INSERT INTO kb_schedule SET 
             title = '$title',
             description = '$description',
             created_by_type = '$created_by_type',
             created_by_id = '$created_by_id',
             created_by_mb_id = '$created_by_mb_id',
             target_bot_name = '$target_bot_name',
             target_room_id = '$target_room_id',
             message_text = '$message_text',
             message_images_1 = " . ($message_images_1_json ? "'$message_images_1_json'" : "NULL") . ",
             message_images_2 = " . ($message_images_2_json ? "'$message_images_2_json'" : "NULL") . ",
             send_interval_seconds = '$send_interval_seconds',
             media_wait_time_1 = '$media_wait_time_1',
             media_wait_time_2 = '$media_wait_time_2',
             schedule_type = '$schedule_type',
             schedule_date = " . ($schedule_date ? "'$schedule_date'" : "NULL") . ",
             schedule_time = '$schedule_time',
             schedule_times = '$schedule_times',
             schedule_weekdays = " . ($schedule_weekdays ? "'$schedule_weekdays'" : "NULL") . ",
             valid_from = '$valid_from',
             valid_until = '$valid_until',
             status = '$status',
             next_send_at = " . ($next_send_at ? "'$next_send_at'" : "NULL") . ",
             created_at = NOW(),
             updated_at = NOW() ";
    
    sql_query($sql);
    
    // 로그 기록
    dmk_admin_log('스케줄링 발송 등록', "제목: $title, 톡방: $target_room_id");
    
    goto_url('./bot_schedule_list.php');
    
} else if ($w == 'u') {
    // 수정
    if (!$id) {
        alert('스케줄 ID가 없습니다.');
    }
    
    // 기존 스케줄 정보 확인
    $sql = " SELECT * FROM kb_schedule WHERE id = '$id' ";
    $schedule = sql_fetch($sql);
    if (!$schedule['id']) {
        alert('등록된 스케줄이 아닙니다.');
    }
    
    // 권한 체크
    if ($user_info['type'] != 'super') {
        // 스케줄 소유권 확인
        $can_edit = false;
        if ($user_info['type'] == 'distributor') {
            if ($schedule['created_by_type'] == 'distributor' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'agency') {
                $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['key']}' AND ag_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT b.br_id FROM dmk_branch b 
                         JOIN dmk_agency a ON b.ag_id = a.ag_id 
                         WHERE a.dt_id = '{$user_info['key']}' AND b.br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['type'] == 'agency') {
            if ($schedule['created_by_type'] == 'agency' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['key']}' AND br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['type'] == 'branch') {
            if ($schedule['created_by_type'] == 'branch' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            }
        }
        
        if (!$can_edit) {
            alert('이 스케줄을 수정할 권한이 없습니다.');
        }
    }
    
    // 기존 이미지 중 삭제된 것 처리
    $old_images_1 = $schedule['message_images_1'] ? json_decode($schedule['message_images_1'], true) : [];
    $old_images_2 = $schedule['message_images_2'] ? json_decode($schedule['message_images_2'], true) : [];
    
    // 삭제할 이미지 찾기
    $keep_images_1 = $_POST['existing_images_1'] ?? [];
    $keep_images_2 = $_POST['existing_images_2'] ?? [];
    
    foreach ($old_images_1 as $img) {
        if (!in_array($img['file'], $keep_images_1)) {
            @unlink($upload_dir . '/' . $img['file']);
        }
    }
    
    foreach ($old_images_2 as $img) {
        if (!in_array($img['file'], $keep_images_2)) {
            @unlink($upload_dir . '/' . $img['file']);
        }
    }
    
    $sql = " UPDATE kb_schedule SET 
             title = '$title',
             description = '$description',
             target_bot_name = '$target_bot_name',
             target_room_id = '$target_room_id',
             message_text = '$message_text',
             message_images_1 = " . ($message_images_1_json ? "'$message_images_1_json'" : "NULL") . ",
             message_images_2 = " . ($message_images_2_json ? "'$message_images_2_json'" : "NULL") . ",
             send_interval_seconds = '$send_interval_seconds',
             media_wait_time_1 = '$media_wait_time_1',
             media_wait_time_2 = '$media_wait_time_2',
             schedule_type = '$schedule_type',
             schedule_date = " . ($schedule_date ? "'$schedule_date'" : "NULL") . ",
             schedule_time = '$schedule_time',
             schedule_times = '$schedule_times',
             schedule_weekdays = " . ($schedule_weekdays ? "'$schedule_weekdays'" : "NULL") . ",
             valid_from = '$valid_from',
             valid_until = '$valid_until',
             status = '$status',
             next_send_at = " . ($next_send_at ? "'$next_send_at'" : "NULL") . ",
             updated_at = NOW()
             WHERE id = '$id' ";
    
    sql_query($sql);
    
    // 로그 기록
    dmk_admin_log('스케줄링 발송 수정', "ID: $id, 제목: $title");
    
    goto_url('./bot_schedule_list.php');
}
?>