<?php
$sub_menu = "180600";
include_once('./_common.php');

auth_check('180600', 'r');

$g5['title'] = 'UTF-8 인코딩 테스트';
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<h2>UTF-8 인코딩 테스트</h2>

<div style="margin: 20px; padding: 20px; border: 1px solid #ddd;">
    <?php
    // 현재 설정 확인
    echo "<h3>현재 설정:</h3>";
    echo "<p>PHP 내부 인코딩: " . mb_internal_encoding() . "</p>";
    echo "<p>HTTP 출력 인코딩: " . mb_http_output() . "</p>";
    echo "<p>정규표현식 인코딩: " . mb_regex_encoding() . "</p>";
    echo "<p>기본 언어: " . mb_language() . "</p>";
    
    // MySQL 설정 확인
    echo "<h3>MySQL 설정:</h3>";
    $result = sql_query("SHOW VARIABLES LIKE 'character_set%'");
    echo "<table border='1' style='margin: 10px 0;'>";
    while ($row = sql_fetch_array($result)) {
        echo "<tr><td>{$row['Variable_name']}</td><td>{$row['Value']}</td></tr>";
    }
    echo "</table>";
    
    // 테스트 문자열
    $test_string = "🎉 이모티콘 테스트 🎊 😊 💕 한글 English 123";
    echo "<h3>테스트 문자열:</h3>";
    echo "<p>원본: " . $test_string . "</p>";
    echo "<p>HEX: " . bin2hex($test_string) . "</p>";
    echo "<p>인코딩: " . mb_detect_encoding($test_string) . "</p>";
    ?>
    
    <h3>폼 전송 테스트:</h3>
    <form method="post" accept-charset="UTF-8">
        <textarea name="test_text" rows="5" cols="50" style="font-family: 'Noto Sans KR', sans-serif;">🎉 이모티콘 테스트 🎊 😊 💕</textarea><br>
        <input type="submit" value="전송">
    </form>
    
    <?php
    if ($_POST['test_text']) {
        echo "<h3>POST 데이터 분석:</h3>";
        echo "<p>받은 데이터: " . $_POST['test_text'] . "</p>";
        echo "<p>HEX: " . bin2hex(substr($_POST['test_text'], 0, 50)) . "</p>";
        echo "<p>인코딩: " . mb_detect_encoding($_POST['test_text']) . "</p>";
        
        // DB 저장 테스트
        $test_text = sql_escape_string($_POST['test_text']);
        $sql = "INSERT INTO kb_schedule (title, message_text, created_by_type, created_by_id, created_by_mb_id, target_bot_name, target_room_id, schedule_type, schedule_time, valid_from, valid_until) 
                VALUES ('인코딩 테스트', '$test_text', 'branch', 'BR001', 'admin', 'LOA.i', 'test_room', 'once', '10:00:00', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))";
        
        if (sql_query($sql)) {
            $insert_id = sql_insert_id();
            
            // 다시 읽어오기
            $sql = "SELECT message_text FROM kb_schedule WHERE id = $insert_id";
            $row = sql_fetch($sql);
            
            echo "<h3>DB 저장 결과:</h3>";
            echo "<p>저장된 데이터: " . $row['message_text'] . "</p>";
            echo "<p>HEX: " . bin2hex(substr($row['message_text'], 0, 50)) . "</p>";
            
            // 테스트 데이터 삭제
            sql_query("DELETE FROM kb_schedule WHERE id = $insert_id");
        }
    }
    ?>
</div>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>