<?php
/**
 * 스케줄링 발송 등록/수정 처리
 */

$sub_menu = "180600";
include_once('./_common.php');

// 썸네일 생성 라이브러리 포함
include_once(G5_PATH.'/dmk/lib/thumbnail.lib.php');

// PHP 업로드 설정 (가능한 경우)
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '100M');
@ini_set('max_file_uploads', '30');
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

// 에러 표시 활성화 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// UTF-8 인코딩 설정
header('Content-Type: text/html; charset=utf-8');

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

if (!$_POST['target_device_id']) {
    alert('대상 디바이스를 선택해주세요.');
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
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'schedule';
$target_bot_name = trim($_POST['target_bot_name']);
$target_device_id = trim($_POST['target_device_id']);
$target_room_id = trim($_POST['target_room_id']);
$message_text = trim($_POST['message_text']);
$image_storage_mode = isset($_POST['image_storage_mode']) && $_POST['image_storage_mode'] == 'base64' ? 'base64' : 'file';

// 메시지 텍스트 인코딩 처리
if ($message_text) {
    // 디버깅 정보 출력
    echo "<!-- DEBUG: Original message_text: " . bin2hex(substr($message_text, 0, 50)) . " -->\n";
    echo "<!-- DEBUG: Detected encoding: " . mb_detect_encoding($message_text, mb_detect_order(), true) . " -->\n";
    
    // 현재 인코딩 확인
    $encoding = mb_detect_encoding($message_text, array('UTF-8', 'EUC-KR', 'ISO-8859-1'), true);
    
    // UTF-8이 아닌 경우 변환
    if ($encoding && $encoding !== 'UTF-8') {
        $message_text = mb_convert_encoding($message_text, 'UTF-8', $encoding);
    }
    
    // 잘못된 UTF-8 시퀀스 제거
    $message_text = mb_convert_encoding($message_text, 'UTF-8', 'UTF-8');
    
    echo "<!-- DEBUG: After conversion: " . bin2hex(substr($message_text, 0, 50)) . " -->\n";
}
$send_interval_seconds = $_POST['send_interval_seconds'] ? $_POST['send_interval_seconds'] : 1;
$media_wait_time_1 = $_POST['media_wait_time_1'] ? $_POST['media_wait_time_1'] : 0;
$media_wait_time_2 = $_POST['media_wait_time_2'] ? $_POST['media_wait_time_2'] : 0;
$schedule_type = $_POST['schedule_type'];
$schedule_date = $_POST['schedule_date'] ? $_POST['schedule_date'] : null;
$schedule_times = json_encode(array_values($valid_times));  // 복수 시간을 JSON으로 저장
$schedule_time = $valid_times[0];  // 첫 번째 시간 (호환성을 위해 유지)
$schedule_weekdays = !empty($_POST['schedule_weekdays']) ? implode(',', $_POST['schedule_weekdays']) : null;
$status = $_POST['status'] ? $_POST['status'] : 'active';

// 디버깅: 발송 시간 정보
echo "<!-- DEBUG: Valid times: " . print_r($valid_times, true) . " -->\n";
echo "<!-- DEBUG: Schedule times JSON: " . $schedule_times . " -->\n";
echo "<!-- DEBUG: Schedule type: $schedule_type, Schedule date: $schedule_date -->\n";

// 사용자 정보
$user_info = dmk_get_admin_auth();

// 디버깅: 권한 정보 출력
echo "<!-- DEBUG: user_info: " . print_r($user_info, true) . " -->\n";

if (!$user_info) {
    alert('권한 정보를 가져올 수 없습니다.');
}

// mb_type을 문자열로 변환
$mb_type_map = array(
    10 => 'super',  // 최고관리자
    8 => 'distributor',  // 총판
    6 => 'agency',  // 대리점
    4 => 'branch'   // 지점
);

$created_by_type = isset($mb_type_map[$user_info['mb_level']]) ? $mb_type_map[$user_info['mb_level']] : '';
if ($user_info['is_super']) {
    $created_by_type = 'super';
}

// created_by_id 설정
switch($created_by_type) {
    case 'distributor':
        $created_by_id = $user_info['dt_id'];
        break;
    case 'agency':
        $created_by_id = $user_info['ag_id'];
        break;
    case 'branch':
        $created_by_id = $user_info['br_id'];
        break;
    default:
        $created_by_id = $user_info['mb_id'];
}

$created_by_mb_id = $member['mb_id'];

echo "<!-- DEBUG: created_by_type: $created_by_type, created_by_id: $created_by_id -->\n";

// 업로드 디렉토리 설정
$upload_dir = G5_DATA_PATH.'/schedule';
$upload_url = G5_DATA_URL.'/schedule';

// 업로드 디렉토리 생성
$year_month = date('Y/m');
$upload_path = $upload_dir.'/'.$year_month;
$upload_url_path = $upload_url.'/'.$year_month;

if (!is_dir($upload_path)) {
    @mkdir($upload_path, 0755, true);
    // index.html 파일 생성
    $f = fopen($upload_path.'/index.html', 'w');
    @fclose($f);
}

echo "<!-- DEBUG: Upload directory: $upload_path -->\n";

// 이미지 처리 함수 (파일시스템 저장 방식)
function process_schedule_images($group_num, $existing_images, $new_files, $bot_name = null) {
    global $upload_path, $upload_url_path;
    
    $images = [];
    $max_images = 30; // 최대 이미지 개수
    
    // 봇 설정에서 리사이징 크기 가져오기
    $max_width = 900; // 기본값
    $resize_enabled = true; // 기본값
    
    if ($bot_name) {
        $sql = "SELECT image_resize_width, image_resize_enabled FROM kb_bot_devices WHERE bot_name = '".sql_escape_string($bot_name)."' AND status = 'approved' LIMIT 1";
        $bot_config = sql_fetch($sql);
        if ($bot_config) {
            $resize_enabled = $bot_config['image_resize_enabled'];
            if ($bot_config['image_resize_width'] > 0) {
                $max_width = $bot_config['image_resize_width'];
            }
        }
    }
    
    // 기존 이미지 처리 (이미 clean_existing_images로 정리됨)
    if (!empty($existing_images)) {
        foreach ($existing_images as $image_data) {
            if (count($images) >= $max_images) break;
            
            // 이미 정리된 배열 데이터 - path가 있는 경우만 처리
            if (is_array($image_data) && isset($image_data['path'])) {
                $images[] = $image_data;
            }
        }
    }
    
    // 새 이미지 업로드
    if (!empty($new_files['name'][0])) {
        echo "<!-- DEBUG: Processing " . count($new_files['name']) . " new files for group $group_num -->\n";
        
        for ($i = 0; $i < count($new_files['name']); $i++) {
            if (count($images) >= $max_images) break;
            
            echo "<!-- DEBUG: Processing file $i: " . $new_files['name'][$i] . " -->\n";
            
            if ($new_files['error'][$i] == 0) {
                $original_name = $new_files['name'][$i];
                $tmp_path = $new_files['tmp_name'][$i];
                
                // 이미지인지 확인
                $is_image = preg_match('/\.(jpg|jpeg|png|gif)$/i', $original_name);
                
                // 고유한 파일명 생성
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $unique_name = 'schedule_'.date('YmdHis').'_'.uniqid().'.'.$ext;
                $dest_path = $upload_path.'/'.$unique_name;
                
                if ($is_image && $resize_enabled) {
                    // 이미지 리사이징 후 저장
                    echo "<!-- DEBUG: Attempting to resize image (max_width: $max_width) -->\n";
                    $saved = resize_and_save_image($tmp_path, $dest_path, $max_width);
                } else {
                    // 원본 파일 저장
                    echo "<!-- DEBUG: Saving original file -->\n";
                    $saved = move_uploaded_file($tmp_path, $dest_path);
                }
                
                if ($saved) {
                    // 상대 경로로 저장 (data/schedule/2024/01/filename.jpg)
                    $relative_path = str_replace(G5_DATA_PATH.'/', '', $dest_path);
                    $images[] = array(
                        'path' => $relative_path,
                        'name' => $original_name,
                        'size' => filesize($dest_path)
                    );
                    echo "<!-- DEBUG: File successfully saved: $unique_name (path: $relative_path) -->\n";
                } else {
                    echo "<!-- DEBUG: Failed to save file -->\n";
                }
            } else {
                echo "<!-- DEBUG: File upload error: " . $new_files['error'][$i] . " -->\n";
            }
        }
    }
    
    echo "<!-- DEBUG: Total images for group $group_num: " . count($images) . " -->\n";
    return $images;
}

// 이미지 리사이징 및 파일 저장 함수
function resize_and_save_image($source_path, $dest_path, $max_width) {
    // GD 라이브러리 체크
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
        // GD 라이브러리가 없으면 원본 파일 복사
        return copy($source_path, $dest_path);
    }
    
    // 메모리 제한 임시 증가
    ini_set('memory_limit', '512M');
    
    // 이미지 정보 가져오기
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        // 이미지가 아닌 경우 원본 파일 복사
        return copy($source_path, $dest_path);
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];
    
    // 리사이징이 필요 없는 경우
    if ($width <= $max_width) {
        return copy($source_path, $dest_path);
    }
    
    // 새로운 크기 계산
    $new_width = $max_width;
    $new_height = ($height / $width) * $new_width;
    
    // 원본 이미지 로드
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            // 지원하지 않는 형식은 원본 파일 복사
            return copy($source_path, $dest_path);
    }
    
    if (!$source) {
        return copy($source_path, $dest_path);
    }
    
    // 새 이미지 생성
    $dest = imagecreatetruecolor($new_width, $new_height);
    
    // PNG/GIF 투명도 처리
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($dest, imagecolorallocatealpha($dest, 0, 0, 0, 127));
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
    }
    
    // 리사이징
    imagecopyresampled($dest, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // 파일로 저장
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($dest, $dest_path, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($dest, $dest_path, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($dest, $dest_path);
            break;
    }
    
    // 메모리 해제
    imagedestroy($source);
    imagedestroy($dest);
    
    return $result;
}

// Base64 방식의 이미지 처리 함수
function process_schedule_images_base64($group_num, $existing_images, $new_files, $bot_name = null) {
    $images = [];
    $max_images = 30; // 최대 이미지 개수
    
    // 봇 설정에서 리사이징 크기 가져오기
    $max_width = 900; // 기본값
    $resize_enabled = true; // 기본값
    
    if ($bot_name) {
        $sql = "SELECT image_resize_width, image_resize_enabled FROM kb_bot_devices WHERE bot_name = '".sql_escape_string($bot_name)."' AND status = 'approved' LIMIT 1";
        $bot_config = sql_fetch($sql);
        if ($bot_config) {
            $resize_enabled = $bot_config['image_resize_enabled'];
            if ($bot_config['image_resize_width'] > 0) {
                $max_width = $bot_config['image_resize_width'];
            }
        }
    }
    
    // 기존 이미지 처리 (이미 clean_existing_images로 정리됨)
    if (!empty($existing_images)) {
        foreach ($existing_images as $image_data) {
            if (count($images) >= $max_images) break;
            
            // 이미 정리된 배열 데이터
            if (is_array($image_data) && isset($image_data['base64'])) {
                $images[] = $image_data;
            }
        }
    }
    
    // 새 이미지 업로드 및 Base64 변환
    if (!empty($new_files['name'][0])) {
        for ($i = 0; $i < count($new_files['name']); $i++) {
            if (count($images) >= $max_images) break;
            
            if ($new_files['error'][$i] == 0) {
                $original_name = $new_files['name'][$i];
                $tmp_path = $new_files['tmp_name'][$i];
                
                // 이미지인지 확인
                $is_image = preg_match('/\.(jpg|jpeg|png|gif)$/i', $original_name);
                
                if ($is_image) {
                    // 리사이징이 필요한 경우 임시 파일로 처리
                    if ($resize_enabled) {
                        $temp_resized = sys_get_temp_dir() . '/' . uniqid('resize_') . '.' . pathinfo($original_name, PATHINFO_EXTENSION);
                        if (resize_and_save_image($tmp_path, $temp_resized, $max_width)) {
                            $image_data = file_get_contents($temp_resized);
                            unlink($temp_resized);
                        } else {
                            $image_data = file_get_contents($tmp_path);
                        }
                    } else {
                        $image_data = file_get_contents($tmp_path);
                    }
                    
                    // Base64 인코딩
                    $base64_data = base64_encode($image_data);
                    
                    $images[] = array(
                        'base64' => $base64_data,
                        'name' => $original_name,
                        'size' => strlen($image_data)
                    );
                }
            }
        }
    }
    
    return $images;
}

// 이미지 그룹 처리
echo "<!-- DEBUG: Processing images -->\n";
echo "<!-- DEBUG: Storage mode: $image_storage_mode -->\n";
echo "<!-- DEBUG: FILES data: " . print_r($_FILES, true) . " -->\n";
echo "<!-- DEBUG: POST existing_images_1: " . print_r($_POST['existing_images_1'] ?? [], true) . " -->\n";
echo "<!-- DEBUG: POST existing_images_2: " . print_r($_POST['existing_images_2'] ?? [], true) . " -->\n";

// 디버깅: 첫 번째 existing_images 값 확인
if (!empty($_POST['existing_images_1'][0])) {
    echo "<!-- DEBUG: First existing_image_1 raw: " . substr($_POST['existing_images_1'][0], 0, 200) . "... -->\n";
    $test_decode = json_decode($_POST['existing_images_1'][0], true);
    echo "<!-- DEBUG: First decode result: " . print_r($test_decode, true) . " -->\n";
    if (isset($test_decode['path']) && is_string($test_decode['path'])) {
        echo "<!-- DEBUG: Path is string, checking if it's JSON: " . substr($test_decode['path'], 0, 100) . "... -->\n";
        $double_decode = json_decode($test_decode['path'], true);
        if ($double_decode !== null) {
            echo "<!-- DEBUG: DOUBLE ENCODING DETECTED! -->\n";
        }
    }
}

// 기존 이미지 데이터 정리 함수
function clean_existing_images($existing_images) {
    $cleaned = [];
    if (!empty($existing_images)) {
        foreach ($existing_images as $image_data) {
            if (is_string($image_data)) {
                $decoded = json_decode($image_data, true);
                if ($decoded && is_array($decoded)) {
                    // path나 base64가 다시 JSON 문자열인지 확인
                    if (isset($decoded['path']) && is_string($decoded['path'])) {
                        // path가 JSON처럼 보이는 경우
                        if (strpos($decoded['path'], '{') === 0 || strpos($decoded['path'], '"') === 0) {
                            $path_decoded = json_decode($decoded['path'], true);
                            if ($path_decoded !== null) {
                                $cleaned[] = $path_decoded;
                                continue;
                            }
                        }
                    }
                    $cleaned[] = $decoded;
                }
            } else if (is_array($image_data)) {
                $cleaned[] = $image_data;
            }
        }
    }
    return $cleaned;
}

// existing_images 정리
$_POST['existing_images_1'] = clean_existing_images($_POST['existing_images_1'] ?? []);
$_POST['existing_images_2'] = clean_existing_images($_POST['existing_images_2'] ?? []);

// 저장 방식에 따라 다른 함수 호출
if ($image_storage_mode == 'base64') {
    // Base64 방식
    $message_images_1 = process_schedule_images_base64(1, $_POST['existing_images_1'] ?? [], $_FILES['new_images_1'] ?? [], $target_bot_name);
    $message_images_2 = process_schedule_images_base64(2, $_POST['existing_images_2'] ?? [], $_FILES['new_images_2'] ?? [], $target_bot_name);
} else {
    // 파일 시스템 방식 (기본)
    $message_images_1 = process_schedule_images(1, $_POST['existing_images_1'] ?? [], $_FILES['new_images_1'] ?? [], $target_bot_name);
    $message_images_2 = process_schedule_images(2, $_POST['existing_images_2'] ?? [], $_FILES['new_images_2'] ?? [], $target_bot_name);
}

// 디버깅: 처리 결과 확인
echo "<!-- DEBUG: After processing - message_images_1 count: " . count($message_images_1) . " -->\n";
echo "<!-- DEBUG: After processing - message_images_2 count: " . count($message_images_2) . " -->\n";

echo "<!-- DEBUG: message_images_1 result: " . print_r($message_images_1, true) . " -->\n";
echo "<!-- DEBUG: message_images_2 result: " . print_r($message_images_2, true) . " -->\n";

// JSON 인코딩
$message_images_1_json = !empty($message_images_1) ? json_encode($message_images_1) : null;
$message_images_2_json = !empty($message_images_2) ? json_encode($message_images_2) : null;

// 썸네일 생성 함수 정의
function create_thumbnails_from_path_array($images_array) {
    global $upload_path, $upload_url_path;
    
    $thumbnails = [];
    $thumb_width = 300; // 썸네일 너비
    
    foreach ($images_array as $image) {
        if (empty($image['path'])) continue;
        
        // 원본 파일 경로
        $original_path = G5_DATA_PATH.'/'.$image['path'];
        
        // 썸네일 파일명 생성
        $path_info = pathinfo($image['path']);
        $thumb_filename = 'thumb_'.basename($path_info['basename']);
        $thumb_relative_path = $path_info['dirname'].'/'.$thumb_filename;
        $thumb_full_path = G5_DATA_PATH.'/'.$thumb_relative_path;
        
        // 썸네일이 이미 존재하는지 확인
        if (!file_exists($thumb_full_path)) {
            // 원본 파일이 존재하면 썸네일 생성
            if (file_exists($original_path)) {
                $saved = resize_and_save_image($original_path, $thumb_full_path, $thumb_width);
                if ($saved) {
                    $thumbnails[] = array(
                        'path' => $thumb_relative_path,
                        'name' => 'thumb_'.$image['name'],
                        'size' => filesize($thumb_full_path)
                    );
                }
            }
        } else {
            // 이미 존재하는 썸네일 정보 추가
            $thumbnails[] = array(
                'path' => $thumb_relative_path,
                'name' => 'thumb_'.$image['name'],
                'size' => filesize($thumb_full_path)
            );
        }
    }
    
    return $thumbnails;
}

// Base64 썸네일 생성 함수
function create_thumbnails_from_base64_array($images_array) {
    $thumbnails = [];
    $thumb_width = 300; // 썸네일 너비
    
    foreach ($images_array as $image) {
        if (empty($image['base64'])) continue;
        
        // Base64를 이미지로 디코드
        $image_data = base64_decode($image['base64']);
        
        // 임시 파일로 저장
        $temp_path = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.jpg';
        file_put_contents($temp_path, $image_data);
        
        // 썸네일용 임시 파일
        $thumb_path = sys_get_temp_dir() . '/' . uniqid('thumb_resized_') . '.jpg';
        
        // 썸네일 생성
        if (resize_and_save_image($temp_path, $thumb_path, $thumb_width)) {
            $thumb_data = file_get_contents($thumb_path);
            $thumb_base64 = base64_encode($thumb_data);
            
            $thumbnails[] = array(
                'base64' => $thumb_base64,
                'name' => 'thumb_' . ($image['name'] ?? 'image.jpg'),
                'size' => strlen($thumb_data)
            );
            
            unlink($thumb_path);
        }
        
        unlink($temp_path);
    }
    
    return $thumbnails;
}

// 초기화
$message_thumbnails_1 = null;
$message_thumbnails_2 = null;

// 다음 발송 시간 계산 (복수 시간 지원)
function calculate_next_send_time($schedule_type, $schedule_date, $schedule_times_json, $schedule_weekdays, $valid_from, $valid_until) {
    $now = time();
    $valid_from_ts = strtotime($valid_from);
    $valid_until_ts = strtotime($valid_until);
    
    // JSON에서 시간 배열 추출
    $times = json_decode($schedule_times_json, true);
    if (empty($times)) {
        echo "<!-- DEBUG: No times found in JSON: $schedule_times_json -->\n";
        return null;
    }
    
    // 시간 정렬
    sort($times);
    echo "<!-- DEBUG: Times: " . implode(', ', $times) . " -->\n";
    echo "<!-- DEBUG: Schedule type: $schedule_type, Date: $schedule_date, Weekdays: $schedule_weekdays -->\n";
    
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
             title = '".sql_escape_string($title)."',
             description = '".sql_escape_string($description)."',
             message_type = '".sql_escape_string($message_type)."',
             created_by_type = '".sql_escape_string($created_by_type)."',
             created_by_id = '".sql_escape_string($created_by_id)."',
             created_by_mb_id = '".sql_escape_string($created_by_mb_id)."',
             target_bot_name = '".sql_escape_string($target_bot_name)."',
             target_device_id = '".sql_escape_string($target_device_id)."',
             target_room_id = '".sql_escape_string($target_room_id)."',
             message_text = '".sql_escape_string($message_text)."',
             message_images_1 = " . ($message_images_1_json ? "'".sql_escape_string($message_images_1_json)."'" : "NULL") . ",
             message_images_2 = " . ($message_images_2_json ? "'".sql_escape_string($message_images_2_json)."'" : "NULL") . ",
             message_thumbnails_1 = " . ($message_thumbnails_1 ? "'".sql_escape_string($message_thumbnails_1)."'" : "NULL") . ",
             message_thumbnails_2 = " . ($message_thumbnails_2 ? "'".sql_escape_string($message_thumbnails_2)."'" : "NULL") . ",
             image_storage_mode = '".sql_escape_string($image_storage_mode)."',
             send_interval_seconds = '".sql_escape_string($send_interval_seconds)."',
             media_wait_time_1 = '".sql_escape_string($media_wait_time_1)."',
             media_wait_time_2 = '".sql_escape_string($media_wait_time_2)."',
             schedule_type = '".sql_escape_string($schedule_type)."',
             schedule_date = " . ($schedule_date ? "'".sql_escape_string($schedule_date)."'" : "NULL") . ",
             schedule_time = '".sql_escape_string($schedule_time)."',
             schedule_times = '".sql_escape_string($schedule_times)."',
             schedule_weekdays = " . ($schedule_weekdays ? "'".sql_escape_string($schedule_weekdays)."'" : "NULL") . ",
             valid_from = '".sql_escape_string($valid_from)."',
             valid_until = '".sql_escape_string($valid_until)."',
             status = '".sql_escape_string($status)."',
             next_send_at = " . ($next_send_at ? "'".sql_escape_string($next_send_at)."'" : "NULL") . ",
             created_at = NOW(),
             updated_at = NOW() ";
    
    sql_query($sql);
    
    // 로그 기록
    // dmk_admin_log('스케줄링 발송 등록', "제목: $title, 톡방: $target_room_id");
    
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
    if (!$user_info['is_super']) {
        // 스케줄 소유권 확인
        $can_edit = false;
        
        // 사용자의 mb_level을 기반으로 권한 체크
        if ($user_info['mb_level'] == 8) { // 총판
            if ($schedule['created_by_type'] == 'distributor' && $schedule['created_by_id'] == $user_info['dt_id']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'agency') {
                $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['dt_id']}' AND ag_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT b.br_id FROM dmk_branch b 
                         JOIN dmk_agency a ON b.ag_id = a.ag_id 
                         WHERE a.dt_id = '{$user_info['dt_id']}' AND b.br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['mb_level'] == 6) { // 대리점
            if ($schedule['created_by_type'] == 'agency' && $schedule['created_by_id'] == $user_info['ag_id']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['ag_id']}' AND br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['mb_level'] == 4) { // 지점
            if ($schedule['created_by_type'] == 'branch' && $schedule['created_by_id'] == $user_info['br_id']) {
                $can_edit = true;
            }
        }
        
        if (!$can_edit) {
            alert('이 스케줄을 수정할 권한이 없습니다.');
        }
    }
    
    // 저장 방식 변경 시 데이터 변환 처리
    $old_storage_mode = $schedule['image_storage_mode'] ?? 'file';
    $new_storage_mode = $image_storage_mode;
    
    echo "<!-- DEBUG: Storage mode change: $old_storage_mode -> $new_storage_mode -->\n";
    
    // 저장 방식이 변경된 경우만 변환 처리
    if ($old_storage_mode != $new_storage_mode) {
        echo "<!-- DEBUG: Storage mode changed, converting data -->\n";
        
        // 기존 이미지 데이터 변환
        $converted_images_1 = [];
        $converted_images_2 = [];
        
        if ($old_storage_mode == 'file' && $new_storage_mode == 'base64') {
            // 파일 -> Base64 변환
            echo "<!-- DEBUG: Converting from file to base64 -->\n";
            
            // 기존 이미지 1 변환
            if ($schedule['message_images_1']) {
                $old_images_1 = json_decode($schedule['message_images_1'], true);
                if ($old_images_1) {
                    foreach ($old_images_1 as $img) {
                        if (isset($img['path'])) {
                            $file_path = G5_DATA_PATH.'/'.$img['path'];
                            if (file_exists($file_path)) {
                                $image_data = file_get_contents($file_path);
                                $base64_data = base64_encode($image_data);
                                $converted_images_1[] = array(
                                    'base64' => $base64_data,
                                    'name' => $img['name'] ?? basename($img['path']),
                                    'size' => filesize($file_path)
                                );
                                echo "<!-- DEBUG: Converted image 1: {$img['path']} -->\n";
                            }
                        }
                    }
                }
            }
            
            // 기존 이미지 2 변환
            if ($schedule['message_images_2']) {
                $old_images_2 = json_decode($schedule['message_images_2'], true);
                if ($old_images_2) {
                    foreach ($old_images_2 as $img) {
                        if (isset($img['path'])) {
                            $file_path = G5_DATA_PATH.'/'.$img['path'];
                            if (file_exists($file_path)) {
                                $image_data = file_get_contents($file_path);
                                $base64_data = base64_encode($image_data);
                                $converted_images_2[] = array(
                                    'base64' => $base64_data,
                                    'name' => $img['name'] ?? basename($img['path']),
                                    'size' => filesize($file_path)
                                );
                                echo "<!-- DEBUG: Converted image 2: {$img['path']} -->\n";
                            }
                        }
                    }
                }
            }
            
            // 새로운 이미지와 병합
            $message_images_1 = array_merge($converted_images_1, $message_images_1);
            $message_images_2 = array_merge($converted_images_2, $message_images_2);
            
        } else if ($old_storage_mode == 'base64' && $new_storage_mode == 'file') {
            // Base64 -> 파일 변환
            echo "<!-- DEBUG: Converting from base64 to file -->\n";
            
            // 기존 이미지 1 변환
            if ($schedule['message_images_1']) {
                $old_images_1 = json_decode($schedule['message_images_1'], true);
                if ($old_images_1) {
                    foreach ($old_images_1 as $img) {
                        if (isset($img['base64'])) {
                            // Base64를 파일로 저장
                            $image_data = base64_decode($img['base64']);
                            $ext = 'jpg'; // 기본 확장자
                            if (isset($img['name'])) {
                                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                            }
                            
                            $unique_name = 'schedule_'.date('YmdHis').'_'.uniqid().'.'.$ext;
                            $dest_path = $upload_path.'/'.$unique_name;
                            
                            if (file_put_contents($dest_path, $image_data)) {
                                $relative_path = str_replace(G5_DATA_PATH.'/', '', $dest_path);
                                $converted_images_1[] = array(
                                    'path' => $relative_path,
                                    'name' => $img['name'] ?? $unique_name,
                                    'size' => strlen($image_data)
                                );
                                echo "<!-- DEBUG: Saved base64 to file: $relative_path -->\n";
                            }
                        }
                    }
                }
            }
            
            // 기존 이미지 2 변환
            if ($schedule['message_images_2']) {
                $old_images_2 = json_decode($schedule['message_images_2'], true);
                if ($old_images_2) {
                    foreach ($old_images_2 as $img) {
                        if (isset($img['base64'])) {
                            // Base64를 파일로 저장
                            $image_data = base64_decode($img['base64']);
                            $ext = 'jpg'; // 기본 확장자
                            if (isset($img['name'])) {
                                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                            }
                            
                            $unique_name = 'schedule_'.date('YmdHis').'_'.uniqid().'.'.$ext;
                            $dest_path = $upload_path.'/'.$unique_name;
                            
                            if (file_put_contents($dest_path, $image_data)) {
                                $relative_path = str_replace(G5_DATA_PATH.'/', '', $dest_path);
                                $converted_images_2[] = array(
                                    'path' => $relative_path,
                                    'name' => $img['name'] ?? $unique_name,
                                    'size' => strlen($image_data)
                                );
                                echo "<!-- DEBUG: Saved base64 to file: $relative_path -->\n";
                            }
                        }
                    }
                }
            }
            
            // Base64 -> 파일 변환 시에는 변환된 이미지만 사용 (기존 Base64 데이터는 제거)
            $message_images_1 = $converted_images_1;
            $message_images_2 = $converted_images_2;
        }
        
        // JSON 인코딩 다시 (저장 방식 변환이 없었던 경우를 위해)
        if (!isset($message_images_1_json)) {
            $message_images_1_json = !empty($message_images_1) ? json_encode($message_images_1) : null;
        }
        if (!isset($message_images_2_json)) {
            $message_images_2_json = !empty($message_images_2) ? json_encode($message_images_2) : null;
        }
        
        echo "<!-- DEBUG: Converted images 1: " . count($message_images_1) . " -->\n";
        echo "<!-- DEBUG: Converted images 2: " . count($message_images_2) . " -->\n";
        
        // 변환 후 기존 데이터 정리
        if ($old_storage_mode == 'file' && $new_storage_mode == 'base64') {
            // 파일에서 Base64로 변환했으므로 기존 파일 삭제
            if ($schedule['message_images_1']) {
                $old_images_1 = json_decode($schedule['message_images_1'], true);
                if ($old_images_1) {
                    foreach ($old_images_1 as $img) {
                        if (isset($img['path'])) {
                            $file_path = G5_DATA_PATH.'/'.$img['path'];
                            if (file_exists($file_path)) {
                                @unlink($file_path);
                                echo "<!-- DEBUG: Deleted old file: {$img['path']} -->\n";
                            }
                            // 썸네일도 삭제
                            $thumb_info = pathinfo($img['path']);
                            $thumb_path = G5_DATA_PATH.'/'.$thumb_info['dirname'].'/thumb_'.$thumb_info['basename'];
                            if (file_exists($thumb_path)) {
                                @unlink($thumb_path);
                            }
                        }
                    }
                }
            }
            
            if ($schedule['message_images_2']) {
                $old_images_2 = json_decode($schedule['message_images_2'], true);
                if ($old_images_2) {
                    foreach ($old_images_2 as $img) {
                        if (isset($img['path'])) {
                            $file_path = G5_DATA_PATH.'/'.$img['path'];
                            if (file_exists($file_path)) {
                                @unlink($file_path);
                                echo "<!-- DEBUG: Deleted old file: {$img['path']} -->\n";
                            }
                            // 썸네일도 삭제
                            $thumb_info = pathinfo($img['path']);
                            $thumb_path = G5_DATA_PATH.'/'.$thumb_info['dirname'].'/thumb_'.$thumb_info['basename'];
                            if (file_exists($thumb_path)) {
                                @unlink($thumb_path);
                            }
                        }
                    }
                }
            }
        }
    } else {
        // 저장 방식이 변경되지 않은 경우 - 기존처럼 처리하되 기존 이미지 보존 확실히
        echo "<!-- DEBUG: Storage mode not changed, preserving existing data -->\n";
        
        // 이미 처리된 이미지가 없는 경우에만 기존 데이터 사용
        if (empty($message_images_1) && $schedule['message_images_1']) {
            $existing_images_1 = json_decode($schedule['message_images_1'], true);
            if ($existing_images_1) {
                echo "<!-- DEBUG: Preserving existing images 1: " . count($existing_images_1) . " images -->\n";
                $message_images_1 = $existing_images_1;
            }
        }
        
        if (empty($message_images_2) && $schedule['message_images_2']) {
            $existing_images_2 = json_decode($schedule['message_images_2'], true);
            if ($existing_images_2) {
                echo "<!-- DEBUG: Preserving existing images 2: " . count($existing_images_2) . " images -->\n";
                $message_images_2 = $existing_images_2;
            }
        }
        
        // JSON 재인코딩
        $message_images_1_json = !empty($message_images_1) ? json_encode($message_images_1) : null;
        $message_images_2_json = !empty($message_images_2) ? json_encode($message_images_2) : null;
    }
    
    // 최종 썸네일 생성 (저장 방식 변환 여부와 관계없이)
    if (!isset($message_thumbnails_1) || $old_storage_mode != $new_storage_mode) {
        // 썸네일이 없거나 저장 방식이 변경된 경우 재생성
        if ($image_storage_mode == 'file') {
            // 파일 시스템 방식
            if (!empty($message_images_1)) {
                $thumbnails_1 = create_thumbnails_from_path_array($message_images_1);
                if (!empty($thumbnails_1)) {
                    $message_thumbnails_1 = json_encode($thumbnails_1);
                    echo "<!-- DEBUG: Created " . count($thumbnails_1) . " file thumbnails for group 1 (final) -->\n";
                }
            }
            
            if (!empty($message_images_2)) {
                $thumbnails_2 = create_thumbnails_from_path_array($message_images_2);
                if (!empty($thumbnails_2)) {
                    $message_thumbnails_2 = json_encode($thumbnails_2);
                    echo "<!-- DEBUG: Created " . count($thumbnails_2) . " file thumbnails for group 2 (final) -->\n";
                }
            }
        } else if ($image_storage_mode == 'base64') {
            // Base64 방식
            if (!empty($message_images_1)) {
                $thumbnails_1 = create_thumbnails_from_base64_array($message_images_1);
                if (!empty($thumbnails_1)) {
                    $message_thumbnails_1 = json_encode($thumbnails_1);
                    echo "<!-- DEBUG: Created " . count($thumbnails_1) . " base64 thumbnails for group 1 (final) -->\n";
                }
            }
            
            if (!empty($message_images_2)) {
                $thumbnails_2 = create_thumbnails_from_base64_array($message_images_2);
                if (!empty($thumbnails_2)) {
                    $message_thumbnails_2 = json_encode($thumbnails_2);
                    echo "<!-- DEBUG: Created " . count($thumbnails_2) . " base64 thumbnails for group 2 (final) -->\n";
                }
            }
        }
    }
    
    // Base64 방식에서는 파일 삭제가 필요 없음 - DB에서만 제거됨
    
    $sql = " UPDATE kb_schedule SET 
             title = '".sql_escape_string($title)."',
             description = '".sql_escape_string($description)."',
             message_type = '".sql_escape_string($message_type)."',
             target_bot_name = '".sql_escape_string($target_bot_name)."',
             target_device_id = '".sql_escape_string($target_device_id)."',
             target_room_id = '".sql_escape_string($target_room_id)."',
             message_text = '".sql_escape_string($message_text)."',
             message_images_1 = " . ($message_images_1_json ? "'".sql_escape_string($message_images_1_json)."'" : "NULL") . ",
             message_images_2 = " . ($message_images_2_json ? "'".sql_escape_string($message_images_2_json)."'" : "NULL") . ",
             message_thumbnails_1 = " . ($message_thumbnails_1 ? "'".sql_escape_string($message_thumbnails_1)."'" : "NULL") . ",
             message_thumbnails_2 = " . ($message_thumbnails_2 ? "'".sql_escape_string($message_thumbnails_2)."'" : "NULL") . ",
             image_storage_mode = '".sql_escape_string($image_storage_mode)."',
             send_interval_seconds = '".sql_escape_string($send_interval_seconds)."',
             media_wait_time_1 = '".sql_escape_string($media_wait_time_1)."',
             media_wait_time_2 = '".sql_escape_string($media_wait_time_2)."',
             schedule_type = '".sql_escape_string($schedule_type)."',
             schedule_date = " . ($schedule_date ? "'".sql_escape_string($schedule_date)."'" : "NULL") . ",
             schedule_time = '".sql_escape_string($schedule_time)."',
             schedule_times = '".sql_escape_string($schedule_times)."',
             schedule_weekdays = " . ($schedule_weekdays ? "'".sql_escape_string($schedule_weekdays)."'" : "NULL") . ",
             valid_from = '".sql_escape_string($valid_from)."',
             valid_until = '".sql_escape_string($valid_until)."',
             status = '".sql_escape_string($status)."',
             next_send_at = " . ($next_send_at ? "'".sql_escape_string($next_send_at)."'" : "NULL") . ",
             updated_at = NOW()
             WHERE id = '".sql_escape_string($id)."' ";
    
    // 디버깅: SQL 쿼리 출력
    echo "<!-- DEBUG: UPDATE SQL: " . $sql . " -->\n";
    
    $result = sql_query($sql);
    
    // 디버깅: 쿼리 실행 결과
    if ($result) {
        echo "<!-- DEBUG: UPDATE successful -->\n";
    } else {
        echo "<!-- DEBUG: UPDATE failed -->\n";
    }
    
    // 로그 기록
    // dmk_admin_log('스케줄링 발송 수정', "ID: $id, 제목: $title");
    
    goto_url('./bot_schedule_list.php');
}
?>