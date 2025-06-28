<?php
/**
 * 하이픈 포함 아이디를 언더스코어로 변경하는 마이그레이션 스크립트
 * 
 * 실행 전 반드시 데이터베이스 백업을 수행하세요!
 * 
 * 사용법:
 * 1. 웹 브라우저에서 직접 실행: http://localhost:8001/dmk/migration/fix_member_id_format.php
 * 2. 또는 명령줄에서 실행: php fix_member_id_format.php
 */

// 보안을 위해 관리자만 실행 가능하도록 설정
if (!defined('_GNUBOARD_')) {
    include_once '../../_common.php';
}

// 최고관리자 권한 체크
if (!function_exists('is_super_admin') || !is_super_admin($member['mb_id'])) {
    die('⛔ 최고관리자만 실행할 수 있습니다.');
}

echo "<h1>🔧 아이디 형식 수정 도구</h1>";
echo "<p><strong>⚠️ 주의: 실행 전 반드시 데이터베이스 백업을 수행하세요!</strong></p>";

// 1. 현재 하이픈 포함 아이디 조회
$sql = "SELECT mb_id, mb_name, dmk_mb_type, dmk_admin_type 
        FROM {$g5['member_table']} 
        WHERE mb_id LIKE '%-%' 
        AND dmk_mb_type IN (1,2,3)
        ORDER BY dmk_mb_type, mb_id";
$result = sql_query($sql);

$problematic_ids = [];
while ($row = sql_fetch_array($result)) {
    $problematic_ids[] = $row;
}

if (empty($problematic_ids)) {
    echo "<p>✅ 하이픈 포함 아이디가 없습니다. 수정할 항목이 없습니다.</p>";
    exit;
}

echo "<h2>📋 수정 대상 아이디 목록</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>기존 아이디</th><th>새 아이디</th><th>이름</th><th>타입</th><th>관리자타입</th></tr>";

foreach ($problematic_ids as $row) {
    $old_id = $row['mb_id'];
    $new_id = str_replace('-', '_', $old_id);
    $type_name = '';
    
    switch ($row['dmk_mb_type']) {
        case 1: $type_name = '총판'; break;
        case 2: $type_name = '대리점'; break;
        case 3: $type_name = '지점'; break;
    }
    
    echo "<tr>";
    echo "<td>{$old_id}</td>";
    echo "<td>{$new_id}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td>{$type_name}</td>";
    echo "<td>{$row['dmk_admin_type']}</td>";
    echo "</tr>";
}
echo "</table>";

// 실제 수정 실행 여부 확인
if (!isset($_GET['execute'])) {
    echo "<p><strong>📝 위 목록을 확인하고 수정을 진행하려면 아래 버튼을 클릭하세요:</strong></p>";
    echo "<p><a href='?execute=1' style='background:#e74c3c;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🚀 수정 실행</a></p>";
    echo "<p><small>⚠️ 이 작업은 되돌릴 수 없습니다. 백업을 확인하고 진행하세요.</small></p>";
    exit;
}

echo "<h2>🔄 수정 진행 중...</h2>";

// 트랜잭션 시작
sql_query("START TRANSACTION");

try {
    foreach ($problematic_ids as $row) {
        $old_id = $row['mb_id'];
        $new_id = str_replace('-', '_', $old_id);
        
        echo "<p>📝 {$old_id} → {$new_id} 변경 중...</p>";
        
        // 1. g5_member 테이블 업데이트
        $sql = "UPDATE {$g5['member_table']} SET 
                mb_id = '" . sql_escape_string($new_id) . "',
                dmk_dt_id = CASE 
                    WHEN dmk_dt_id = '" . sql_escape_string($old_id) . "' 
                    THEN '" . sql_escape_string($new_id) . "' 
                    ELSE dmk_dt_id 
                END,
                dmk_ag_id = CASE 
                    WHEN dmk_ag_id = '" . sql_escape_string($old_id) . "' 
                    THEN '" . sql_escape_string($new_id) . "' 
                    ELSE dmk_ag_id 
                END,
                dmk_br_id = CASE 
                    WHEN dmk_br_id = '" . sql_escape_string($old_id) . "' 
                    THEN '" . sql_escape_string($new_id) . "' 
                    ELSE dmk_br_id 
                END
                WHERE mb_id = '" . sql_escape_string($old_id) . "'";
        sql_query($sql);
        
        // 2. 해당 타입별 테이블 업데이트
        switch ($row['dmk_mb_type']) {
            case 1: // 총판
                $sql = "UPDATE dmk_distributor SET dt_id = '" . sql_escape_string($new_id) . "' WHERE dt_id = '" . sql_escape_string($old_id) . "'";
                sql_query($sql);
                break;
                
            case 2: // 대리점
                $sql = "UPDATE dmk_agency SET ag_id = '" . sql_escape_string($new_id) . "' WHERE ag_id = '" . sql_escape_string($old_id) . "'";
                sql_query($sql);
                break;
                
            case 3: // 지점
                $sql = "UPDATE dmk_branch SET br_id = '" . sql_escape_string($new_id) . "' WHERE br_id = '" . sql_escape_string($old_id) . "'";
                sql_query($sql);
                break;
        }
        
        // 3. 다른 테이블의 참조 업데이트
        if ($row['dmk_mb_type'] == 1) { // 총판인 경우
            // 대리점의 dt_id 업데이트
            $sql = "UPDATE dmk_agency SET dt_id = '" . sql_escape_string($new_id) . "' WHERE dt_id = '" . sql_escape_string($old_id) . "'";
            sql_query($sql);
            
            // 회원의 dmk_dt_id 업데이트
            $sql = "UPDATE {$g5['member_table']} SET dmk_dt_id = '" . sql_escape_string($new_id) . "' WHERE dmk_dt_id = '" . sql_escape_string($old_id) . "'";
            sql_query($sql);
        }
        
        if ($row['dmk_mb_type'] == 2) { // 대리점인 경우
            // 지점의 ag_id 업데이트
            $sql = "UPDATE dmk_branch SET ag_id = '" . sql_escape_string($new_id) . "' WHERE ag_id = '" . sql_escape_string($old_id) . "'";
            sql_query($sql);
            
            // 회원의 dmk_ag_id 업데이트
            $sql = "UPDATE {$g5['member_table']} SET dmk_ag_id = '" . sql_escape_string($new_id) . "' WHERE dmk_ag_id = '" . sql_escape_string($old_id) . "'";
            sql_query($sql);
        }
        
        echo "<span style='color:green'>✅ 완료</span><br>";
    }
    
    // 트랜잭션 커밋
    sql_query("COMMIT");
    
    echo "<h2>🎉 수정 완료!</h2>";
    echo "<p>✅ 모든 아이디가 성공적으로 변경되었습니다.</p>";
    echo "<p>📝 변경된 아이디로 로그인하실 수 있습니다.</p>";
    
} catch (Exception $e) {
    // 롤백
    sql_query("ROLLBACK");
    echo "<h2>❌ 오류 발생</h2>";
    echo "<p>수정 중 오류가 발생했습니다: " . $e->getMessage() . "</p>";
    echo "<p>모든 변경사항이 롤백되었습니다.</p>";
}

?>
