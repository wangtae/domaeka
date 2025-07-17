<?php
/**
 * 스케줄 이미지를 파일에서 Base64로 마이그레이션
 * 
 * 이 스크립트는 기존 파일 시스템에 저장된 이미지를
 * Base64로 인코딩하여 데이터베이스에 저장합니다.
 */

$sub_menu = "";
include_once('./_common.php');

if ($member['mb_id'] != 'admin') {
    die('최고관리자만 실행 가능합니다.');
}

echo "<h1>스케줄 이미지 Base64 마이그레이션</h1>";

// 업로드 디렉토리
$upload_dir = G5_DATA_PATH.'/schedule';

// 모든 스케줄 조회
$sql = "SELECT id, message_images_1, message_images_2 FROM kb_schedule WHERE (message_images_1 IS NOT NULL OR message_images_2 IS NOT NULL)";
$result = sql_query($sql);

$total_count = 0;
$success_count = 0;
$fail_count = 0;

while ($row = sql_fetch_array($result)) {
    $total_count++;
    $schedule_id = $row['id'];
    
    echo "<hr>";
    echo "<h3>스케줄 ID: {$schedule_id}</h3>";
    
    $update_needed = false;
    $new_images_1 = null;
    $new_images_2 = null;
    
    // message_images_1 처리
    if ($row['message_images_1']) {
        $images_1 = json_decode($row['message_images_1'], true);
        if ($images_1 && is_array($images_1)) {
            $converted_images_1 = [];
            
            foreach ($images_1 as $img) {
                // 이미 base64인 경우 스킵
                if (isset($img['base64'])) {
                    echo "- 이미지 그룹 1: 이미 Base64로 변환됨<br>";
                    $converted_images_1[] = $img;
                    continue;
                }
                
                // 파일에서 base64로 변환
                if (isset($img['file'])) {
                    $file_path = $upload_dir . '/' . $img['file'];
                    if (file_exists($file_path)) {
                        $content = file_get_contents($file_path);
                        if ($content !== false) {
                            $base64_data = base64_encode($content);
                            $converted_images_1[] = [
                                'base64' => $base64_data,
                                'name' => $img['file'],
                                'size' => strlen($base64_data)
                            ];
                            echo "- 이미지 변환 성공: {$img['file']} (크기: " . strlen($base64_data) . " bytes)<br>";
                            $update_needed = true;
                        } else {
                            echo "<span style='color:red'>- 이미지 읽기 실패: {$img['file']}</span><br>";
                        }
                    } else {
                        echo "<span style='color:red'>- 이미지 파일 없음: {$img['file']}</span><br>";
                    }
                }
            }
            
            if (!empty($converted_images_1)) {
                $new_images_1 = json_encode($converted_images_1);
            }
        }
    }
    
    // message_images_2 처리
    if ($row['message_images_2']) {
        $images_2 = json_decode($row['message_images_2'], true);
        if ($images_2 && is_array($images_2)) {
            $converted_images_2 = [];
            
            foreach ($images_2 as $img) {
                // 이미 base64인 경우 스킵
                if (isset($img['base64'])) {
                    echo "- 이미지 그룹 2: 이미 Base64로 변환됨<br>";
                    $converted_images_2[] = $img;
                    continue;
                }
                
                // 파일에서 base64로 변환
                if (isset($img['file'])) {
                    $file_path = $upload_dir . '/' . $img['file'];
                    if (file_exists($file_path)) {
                        $content = file_get_contents($file_path);
                        if ($content !== false) {
                            $base64_data = base64_encode($content);
                            $converted_images_2[] = [
                                'base64' => $base64_data,
                                'name' => $img['file'],
                                'size' => strlen($base64_data)
                            ];
                            echo "- 이미지 변환 성공: {$img['file']} (크기: " . strlen($base64_data) . " bytes)<br>";
                            $update_needed = true;
                        } else {
                            echo "<span style='color:red'>- 이미지 읽기 실패: {$img['file']}</span><br>";
                        }
                    } else {
                        echo "<span style='color:red'>- 이미지 파일 없음: {$img['file']}</span><br>";
                    }
                }
            }
            
            if (!empty($converted_images_2)) {
                $new_images_2 = json_encode($converted_images_2);
            }
        }
    }
    
    // 업데이트 필요한 경우
    if ($update_needed) {
        $update_sql = "UPDATE kb_schedule SET ";
        $updates = [];
        
        if ($new_images_1 !== null) {
            $updates[] = "message_images_1 = '".sql_escape_string($new_images_1)."'";
        }
        if ($new_images_2 !== null) {
            $updates[] = "message_images_2 = '".sql_escape_string($new_images_2)."'";
        }
        
        if (!empty($updates)) {
            $update_sql .= implode(', ', $updates);
            $update_sql .= " WHERE id = '$schedule_id'";
            
            if (sql_query($update_sql)) {
                echo "<span style='color:green'><b>업데이트 성공!</b></span><br>";
                $success_count++;
            } else {
                echo "<span style='color:red'><b>업데이트 실패: " . sql_error() . "</b></span><br>";
                $fail_count++;
            }
        }
    } else {
        echo "업데이트 필요 없음<br>";
        $success_count++;
    }
}

echo "<hr>";
echo "<h2>마이그레이션 완료</h2>";
echo "총 스케줄: {$total_count}개<br>";
echo "성공: {$success_count}개<br>";
echo "실패: {$fail_count}개<br>";

echo "<br><br>";
echo "<p><strong>주의사항:</strong></p>";
echo "<ul>";
echo "<li>Base64 인코딩으로 인해 데이터베이스 용량이 증가합니다 (약 33%)</li>";
echo "<li>기존 이미지 파일은 수동으로 삭제해야 합니다</li>";
echo "<li>백업을 먼저 수행하는 것을 권장합니다</li>";
echo "</ul>";
?>