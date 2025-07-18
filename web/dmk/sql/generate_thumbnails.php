<?php
/**
 * 기존 스케줄 데이터의 썸네일 생성 스크립트
 * 
 * 사용법: php generate_thumbnails.php
 */

// 그누보드 설정 로드
include_once('../../common.php');
include_once(G5_PATH.'/dmk/lib/thumbnail.lib.php');

// CLI에서만 실행 가능
if (php_sapi_name() !== 'cli') {
    die("이 스크립트는 CLI에서만 실행할 수 있습니다.\n");
}

echo "=== 스케줄 썸네일 생성 시작 ===\n";
echo "시작 시간: " . date('Y-m-d H:i:s') . "\n\n";

// 통계 변수
$total_schedules = 0;
$processed_schedules = 0;
$skipped_schedules = 0;
$failed_schedules = 0;
$total_thumbnails_created = 0;

// 모든 스케줄 조회
$sql = "SELECT id, message_images_1, message_images_2, message_thumbnails_1, message_thumbnails_2 
        FROM kb_schedule 
        WHERE (message_images_1 IS NOT NULL OR message_images_2 IS NOT NULL)
        ORDER BY id";

$result = sql_query($sql);

while ($row = sql_fetch_array($result)) {
    $total_schedules++;
    $schedule_id = $row['id'];
    
    echo "스케줄 ID $schedule_id 처리 중... ";
    
    // 이미 썸네일이 있는 경우 건너뛰기 옵션
    $has_thumbnails_1 = !empty($row['message_thumbnails_1']);
    $has_thumbnails_2 = !empty($row['message_thumbnails_2']);
    
    if ($has_thumbnails_1 && $has_thumbnails_2) {
        echo "이미 모든 썸네일이 존재합니다. 건너뜁니다.\n";
        $skipped_schedules++;
        continue;
    }
    
    $update_fields = array();
    $thumbnails_created = 0;
    
    // 이미지 그룹 1 처리
    if (!empty($row['message_images_1']) && !$has_thumbnails_1) {
        $images_1 = json_decode($row['message_images_1'], true);
        if ($images_1) {
            echo "\n  - 이미지 그룹 1: " . count($images_1) . "개 이미지 발견... ";
            $thumbnails_1 = create_thumbnails_from_array($images_1);
            
            if (!empty($thumbnails_1)) {
                $update_fields[] = "message_thumbnails_1 = '" . sql_escape_string(json_encode($thumbnails_1)) . "'";
                $thumbnails_created += count($thumbnails_1);
                echo count($thumbnails_1) . "개 썸네일 생성 완료";
            } else {
                echo "썸네일 생성 실패";
            }
        }
    }
    
    // 이미지 그룹 2 처리
    if (!empty($row['message_images_2']) && !$has_thumbnails_2) {
        $images_2 = json_decode($row['message_images_2'], true);
        if ($images_2) {
            echo "\n  - 이미지 그룹 2: " . count($images_2) . "개 이미지 발견... ";
            $thumbnails_2 = create_thumbnails_from_array($images_2);
            
            if (!empty($thumbnails_2)) {
                $update_fields[] = "message_thumbnails_2 = '" . sql_escape_string(json_encode($thumbnails_2)) . "'";
                $thumbnails_created += count($thumbnails_2);
                echo count($thumbnails_2) . "개 썸네일 생성 완료";
            } else {
                echo "썸네일 생성 실패";
            }
        }
    }
    
    // 업데이트 실행
    if (!empty($update_fields)) {
        $update_sql = "UPDATE kb_schedule SET " . implode(', ', $update_fields) . " WHERE id = '$schedule_id'";
        if (sql_query($update_sql)) {
            echo "\n  ✓ 데이터베이스 업데이트 완료 ($thumbnails_created 개 썸네일)\n";
            $processed_schedules++;
            $total_thumbnails_created += $thumbnails_created;
        } else {
            echo "\n  ✗ 데이터베이스 업데이트 실패\n";
            $failed_schedules++;
        }
    } else {
        echo " - 생성할 썸네일 없음\n";
        $skipped_schedules++;
    }
}

echo "\n=== 썸네일 생성 완료 ===\n";
echo "종료 시간: " . date('Y-m-d H:i:s') . "\n";
echo "전체 스케줄: $total_schedules 개\n";
echo "처리된 스케줄: $processed_schedules 개\n";
echo "건너뛴 스케줄: $skipped_schedules 개\n";
echo "실패한 스케줄: $failed_schedules 개\n";
echo "생성된 썸네일: $total_thumbnails_created 개\n";

// 선택적: 썸네일이 없는 스케줄 확인
$check_sql = "SELECT COUNT(*) as cnt FROM kb_schedule 
              WHERE (message_images_1 IS NOT NULL AND message_thumbnails_1 IS NULL)
                 OR (message_images_2 IS NOT NULL AND message_thumbnails_2 IS NULL)";
$check_result = sql_fetch($check_sql);

if ($check_result['cnt'] > 0) {
    echo "\n⚠️  경고: 아직 썸네일이 생성되지 않은 스케줄이 " . $check_result['cnt'] . "개 있습니다.\n";
}

echo "\n완료!\n";