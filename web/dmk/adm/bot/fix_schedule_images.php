<?php
/**
 * 스케줄 이미지 데이터 복구 스크립트
 * JSON 이중 인코딩된 데이터를 복구합니다.
 */

include_once('./_common.php');

// 관리자만 실행 가능
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

// 실행 시간 제한 해제
set_time_limit(0);

echo "<h2>스케줄 이미지 데이터 복구</h2>";
echo "<pre>";

// 문제가 있는 스케줄 조회
$sql = "SELECT id, message_images_1, message_thumbnails_1, message_images_2, message_thumbnails_2, image_storage_mode 
        FROM kb_schedule 
        WHERE (message_images_1 IS NOT NULL AND message_images_1 != '' AND message_images_1 != '[]')
           OR (message_images_2 IS NOT NULL AND message_images_2 != '' AND message_images_2 != '[]')";
$result = sql_query($sql);

$fixed_count = 0;
$error_count = 0;

while ($row = sql_fetch_array($result)) {
    $needs_update = false;
    $update_fields = [];
    
    echo "\n--- 스케줄 ID: {$row['id']} ---\n";
    
    // message_images_1 체크 및 복구
    if (!empty($row['message_images_1'])) {
        $original_1 = $row['message_images_1'];
        echo "원본 message_images_1: " . substr($original_1, 0, 100) . "...\n";
        
        // JSON 디코드 시도
        $decoded_1 = json_decode($original_1, true);
        
        if ($decoded_1 === null || !is_array($decoded_1)) {
            echo "첫 번째 디코드 실패\n";
        } else {
            // 첫 번째 요소 확인
            if (!empty($decoded_1[0])) {
                $first_elem = $decoded_1[0];
                
                // path 필드가 JSON 문자열인지 확인
                if (isset($first_elem['path']) && is_string($first_elem['path']) && 
                    (strpos($first_elem['path'], '{') === 0 || strpos($first_elem['path'], '"') === 0)) {
                    echo "이중 인코딩 감지됨!\n";
                    
                    // 복구 시도
                    $fixed_array = [];
                    foreach ($decoded_1 as $item) {
                        if (isset($item['path']) && is_string($item['path'])) {
                            // path가 JSON 문자열인 경우 디코드
                            $path_decoded = json_decode($item['path'], true);
                            if ($path_decoded && is_array($path_decoded)) {
                                $fixed_array[] = $path_decoded;
                                echo "복구됨: " . json_encode($path_decoded) . "\n";
                            } else {
                                // 일반 경로인 경우 그대로 사용
                                $fixed_array[] = $item;
                            }
                        } else {
                            $fixed_array[] = $item;
                        }
                    }
                    
                    $update_fields['message_images_1'] = json_encode($fixed_array);
                    $needs_update = true;
                    echo "message_images_1 복구 완료\n";
                }
            }
        }
    }
    
    // message_images_2 체크 및 복구
    if (!empty($row['message_images_2'])) {
        $original_2 = $row['message_images_2'];
        echo "원본 message_images_2: " . substr($original_2, 0, 100) . "...\n";
        
        // JSON 디코드 시도
        $decoded_2 = json_decode($original_2, true);
        
        if ($decoded_2 === null || !is_array($decoded_2)) {
            echo "첫 번째 디코드 실패\n";
        } else {
            // 첫 번째 요소 확인
            if (!empty($decoded_2[0])) {
                $first_elem = $decoded_2[0];
                
                // path 필드가 JSON 문자열인지 확인
                if (isset($first_elem['path']) && is_string($first_elem['path']) && 
                    (strpos($first_elem['path'], '{') === 0 || strpos($first_elem['path'], '"') === 0)) {
                    echo "이중 인코딩 감지됨!\n";
                    
                    // 복구 시도
                    $fixed_array = [];
                    foreach ($decoded_2 as $item) {
                        if (isset($item['path']) && is_string($item['path'])) {
                            // path가 JSON 문자열인 경우 디코드
                            $path_decoded = json_decode($item['path'], true);
                            if ($path_decoded && is_array($path_decoded)) {
                                $fixed_array[] = $path_decoded;
                                echo "복구됨: " . json_encode($path_decoded) . "\n";
                            } else {
                                // 일반 경로인 경우 그대로 사용
                                $fixed_array[] = $item;
                            }
                        } else {
                            $fixed_array[] = $item;
                        }
                    }
                    
                    $update_fields['message_images_2'] = json_encode($fixed_array);
                    $needs_update = true;
                    echo "message_images_2 복구 완료\n";
                }
            }
        }
    }
    
    // 썸네일 복구 (썸네일도 동일한 문제가 있을 수 있음)
    if (!empty($row['message_thumbnails_1'])) {
        $decoded_thumb_1 = json_decode($row['message_thumbnails_1'], true);
        if ($decoded_thumb_1 && !empty($decoded_thumb_1[0])) {
            $first_thumb = $decoded_thumb_1[0];
            if (isset($first_thumb['path']) && is_string($first_thumb['path']) && 
                (strpos($first_thumb['path'], '{') === 0 || strpos($first_thumb['path'], '"') === 0)) {
                // 썸네일도 복구
                $fixed_thumbs = [];
                foreach ($decoded_thumb_1 as $item) {
                    if (isset($item['path']) && is_string($item['path'])) {
                        $path_decoded = json_decode($item['path'], true);
                        if ($path_decoded && is_array($path_decoded)) {
                            $fixed_thumbs[] = $path_decoded;
                        } else {
                            $fixed_thumbs[] = $item;
                        }
                    } else {
                        $fixed_thumbs[] = $item;
                    }
                }
                $update_fields['message_thumbnails_1'] = json_encode($fixed_thumbs);
                echo "message_thumbnails_1 복구 완료\n";
            }
        }
    }
    
    if (!empty($row['message_thumbnails_2'])) {
        $decoded_thumb_2 = json_decode($row['message_thumbnails_2'], true);
        if ($decoded_thumb_2 && !empty($decoded_thumb_2[0])) {
            $first_thumb = $decoded_thumb_2[0];
            if (isset($first_thumb['path']) && is_string($first_thumb['path']) && 
                (strpos($first_thumb['path'], '{') === 0 || strpos($first_thumb['path'], '"') === 0)) {
                // 썸네일도 복구
                $fixed_thumbs = [];
                foreach ($decoded_thumb_2 as $item) {
                    if (isset($item['path']) && is_string($item['path'])) {
                        $path_decoded = json_decode($item['path'], true);
                        if ($path_decoded && is_array($path_decoded)) {
                            $fixed_thumbs[] = $path_decoded;
                        } else {
                            $fixed_thumbs[] = $item;
                        }
                    } else {
                        $fixed_thumbs[] = $item;
                    }
                }
                $update_fields['message_thumbnails_2'] = json_encode($fixed_thumbs);
                echo "message_thumbnails_2 복구 완료\n";
            }
        }
    }
    
    // 업데이트 실행
    if ($needs_update && !empty($update_fields)) {
        $set_clause = [];
        foreach ($update_fields as $field => $value) {
            $set_clause[] = "$field = '" . sql_escape_string($value) . "'";
        }
        
        $update_sql = "UPDATE kb_schedule SET " . implode(', ', $set_clause) . " WHERE id = {$row['id']}";
        
        if (sql_query($update_sql)) {
            $fixed_count++;
            echo "✓ 스케줄 ID {$row['id']} 복구 성공\n";
        } else {
            $error_count++;
            echo "✗ 스케줄 ID {$row['id']} 복구 실패\n";
        }
    }
}

echo "\n=== 복구 완료 ===\n";
echo "복구 성공: {$fixed_count}건\n";
echo "복구 실패: {$error_count}건\n";
echo "</pre>";

// 복구 후 상태 확인
echo "<h3>복구 후 데이터 샘플</h3>";
echo "<pre>";

$check_sql = "SELECT id, message_images_1, message_thumbnails_1, image_storage_mode 
              FROM kb_schedule 
              WHERE id = 10 LIMIT 1";
$check_result = sql_query($check_sql);
if ($check_row = sql_fetch_array($check_result)) {
    echo "스케줄 ID 10 확인:\n";
    echo "image_storage_mode: {$check_row['image_storage_mode']}\n";
    echo "message_images_1:\n";
    $images = json_decode($check_row['message_images_1'], true);
    print_r($images);
    echo "\nmessage_thumbnails_1:\n";
    $thumbs = json_decode($check_row['message_thumbnails_1'], true);
    print_r($thumbs);
}

echo "</pre>";
?>

<a href="./bot_schedule_list.php" class="btn btn_01">스케줄 목록으로 돌아가기</a>