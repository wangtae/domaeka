<?php
include_once('../common.php');

$br_id = 'domaeka11';

echo "<h3>domaeka11 지점의 봇 조회 테스트</h3>";

// 1. kb_rooms에서 owner_id로 직접 조회
echo "<h4>1. kb_rooms에서 owner_id = 'domaeka11' 조회:</h4>";
$sql1 = "SELECT * FROM kb_rooms WHERE owner_id = '".sql_real_escape_string($br_id)."'";
$result1 = sql_query($sql1);
echo "<pre>";
while ($row = sql_fetch_array($result1)) {
    print_r($row);
}
echo "</pre>";

// 2. 승인된 것만 조회
echo "<h4>2. kb_rooms에서 owner_id = 'domaeka11' AND status = 'approved' 조회:</h4>";
$sql2 = "SELECT * FROM kb_rooms WHERE owner_id = '".sql_real_escape_string($br_id)."' AND status = 'approved'";
$result2 = sql_query($sql2);
echo "<pre>";
while ($row = sql_fetch_array($result2)) {
    print_r($row);
}
echo "</pre>";

// 3. branch_id로도 조회해보기 (혹시 branch_id 필드가 있을 경우)
echo "<h4>3. kb_rooms의 모든 필드 확인 (LIMIT 1):</h4>";
$sql3 = "SELECT * FROM kb_rooms LIMIT 1";
$result3 = sql_query($sql3);
echo "<pre>";
$row = sql_fetch_array($result3);
if ($row) {
    echo "kb_rooms 필드 목록:\n";
    foreach ($row as $key => $value) {
        if (!is_numeric($key)) {
            echo "- $key\n";
        }
    }
}
echo "</pre>";

// 4. kb_bot_devices 테이블 확인
echo "<h4>4. kb_bot_devices 테이블에서 해당 봇 확인:</h4>";
$sql4 = "SELECT * FROM kb_bot_devices WHERE bot_name = 'LOA.i' AND device_id IN ('ccbd8eee1012327e', 'db5d9f5e52abdc47')";
echo "쿼리: <pre>$sql4</pre>";
$result4 = sql_query($sql4);
echo "<pre>";
while ($row = sql_fetch_array($result4)) {
    print_r($row);
}
echo "</pre>";

// 5. 지점 설정 폼에서 사용하는 쿼리 그대로 실행 (수정된 버전)
echo "<h4>5. 지점 설정 폼의 쿼리 실행 (수정된 버전):</h4>";
$bot_list_sql = "SELECT DISTINCT r.bot_name, r.device_id, r.room_name,
                        COALESCE(d.client_type, 'Unknown') as client_type
                 FROM kb_rooms r
                 LEFT JOIN kb_bot_devices d ON r.bot_name = d.bot_name AND r.device_id = d.device_id
                 WHERE r.owner_id = '".sql_real_escape_string($br_id)."'
                 AND r.status = 'approved'
                 ORDER BY r.bot_name, r.device_id";
echo "쿼리: <pre>$bot_list_sql</pre>";
$result5 = sql_query($bot_list_sql);
if (!$result5) {
    echo "쿼리 오류 발생<br>";
} else {
    echo "결과 개수: " . sql_num_rows($result5) . "<br>";
    echo "<pre>";
    while ($row = sql_fetch_array($result5)) {
        print_r($row);
    }
    echo "</pre>";
}

// 6. device_model 없이 조회
echo "<h4>6. device_model 없이 조회:</h4>";
$sql6 = "SELECT DISTINCT r.bot_name, r.device_id, r.room_name
         FROM kb_rooms r
         WHERE r.owner_id = '".sql_real_escape_string($br_id)."'
         AND r.status = 'approved'
         ORDER BY r.bot_name, r.device_id";
echo "쿼리: <pre>$sql6</pre>";
$result6 = sql_query($sql6);
echo "<pre>";
while ($row = sql_fetch_array($result6)) {
    print_r($row);
}
echo "</pre>";

// 7. kb_bot_devices 테이블 구조 확인
echo "<h4>7. kb_bot_devices 테이블 구조 확인:</h4>";
$sql7 = "SHOW COLUMNS FROM kb_bot_devices";
$result7 = sql_query($sql7);
echo "<pre>";
while ($row = sql_fetch_array($result7)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";

// 8. 디버깅을 위한 추가 쿼리
echo "<h4>8. 디버깅: JOIN 조건 확인</h4>";
$sql8 = "SELECT r.bot_name as r_bot_name, r.device_id as r_device_id, 
                d.bot_name as d_bot_name, d.device_id as d_device_id,
                d.client_type
         FROM kb_rooms r
         LEFT JOIN kb_bot_devices d ON r.bot_name = d.bot_name AND r.device_id = d.device_id
         WHERE r.owner_id = '".sql_real_escape_string($br_id)."'
         AND r.status = 'approved'
         LIMIT 5";
echo "쿼리: <pre>$sql8</pre>";
$result8 = sql_query($sql8);
echo "<pre>";
while ($row = sql_fetch_array($result8)) {
    print_r($row);
}
echo "</pre>";

// 9. 지점 정보 확인
echo "<h4>9. 지점 정보 확인:</h4>";
$sql9 = "SELECT * FROM dmk_branch WHERE br_id = '".sql_real_escape_string($br_id)."'";
$result9 = sql_query($sql9);
echo "<pre>";
$branch = sql_fetch_array($result9);
if ($branch) {
    echo "지점 ID: " . $branch['br_id'] . "\n";
    echo "메시지 봇 이름: " . $branch['br_message_bot_name'] . "\n";
    echo "메시지 디바이스 ID: " . $branch['br_message_device_id'] . "\n";
} else {
    echo "지점 정보를 찾을 수 없습니다.";
}
echo "</pre>";

// 10. branch_form.php에서 사용하는 정확한 쿼리
echo "<h4>10. branch_form.php의 정확한 쿼리 테스트:</h4>";
$br = ['br_id' => 'domaeka11'];  // branch_form.php와 동일한 변수명 사용
$bot_list_sql = "SELECT DISTINCT r.bot_name, r.device_id, r.room_name,
                        COALESCE(d.client_type, 'Unknown') as client_type
                 FROM kb_rooms r
                 LEFT JOIN kb_bot_devices d ON r.bot_name = d.bot_name AND r.device_id = d.device_id
                 WHERE r.owner_id = '".sql_real_escape_string($br['br_id'])."'
                 AND r.status = 'approved'
                 ORDER BY r.bot_name, r.device_id";
echo "쿼리: <pre>$bot_list_sql</pre>";
$bot_list_result = sql_query($bot_list_sql);
$bot_list = [];
while ($bot = sql_fetch_array($bot_list_result)) {
    $bot_list[] = $bot;
}
echo "bot_list 배열 개수: " . count($bot_list) . "<br>";
echo "<pre>";
print_r($bot_list);
echo "</pre>";
?>