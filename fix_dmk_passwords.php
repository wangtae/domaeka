<?php
/**
 * 도매까 관리자 패스워드 수정 스크립트
 * main 폴더에서 직접 실행
 */

include_once('./_common.php');

if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>관리자 패스워드 수정</h2>\n";

// 현재 패스워드 상태 확인
echo "<h3>현재 패스워드 상태:</h3>\n";
$sql = "SELECT mb_id, mb_name, LEFT(mb_password, 30) as password_preview, LENGTH(mb_password) as pwd_length
        FROM {$g5['member_table']} 
        WHERE mb_id IN ('agency_admin1', 'agency_admin2', 'branch_admin1', 'branch_admin2', 'branch_admin3')
        ORDER BY mb_id";

$result = sql_query($sql);
if (!$result) {
    echo "<p>계정을 찾을 수 없습니다. 먼저 테스트 데이터를 생성해주세요.</p>\n";
} else {
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>\n";
    echo "<tr style='background:#f8f9fa;'><th>ID</th><th>이름</th><th>패스워드 미리보기</th><th>길이</th></tr>\n";
    while ($row = sql_fetch_array($result)) {
        $bg_color = ($row['pwd_length'] > 100) ? '#f8d7da' : '#d4edda';
        echo "<tr style='background:{$bg_color};'>";
        echo "<td>{$row['mb_id']}</td>";
        echo "<td>{$row['mb_name']}</td>";
        echo "<td><code>{$row['password_preview']}...</code></td>";
        echo "<td>{$row['pwd_length']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// 패스워드 업데이트
$admin_accounts = array(
    'agency_admin1' => '1234',
    'agency_admin2' => '1234',
    'branch_admin1' => '1234',
    'branch_admin2' => '1234',
    'branch_admin3' => '1234'
);

echo "<h3>패스워드 업데이트 진행:</h3>\n";
$success_count = 0;
$error_count = 0;

foreach ($admin_accounts as $mb_id => $password) {
    // 그누보드5 표준 암호화
    $encrypted_password = get_encrypt_string($password);
    
    $sql = "UPDATE {$g5['member_table']} 
            SET mb_password = '" . sql_escape_string($encrypted_password) . "' 
            WHERE mb_id = '" . sql_escape_string($mb_id) . "'";
    
    $result = sql_query($sql);
    
    if ($result) {
        echo "<p style='color:green;'>✓ {$mb_id}: 패스워드 업데이트 성공</p>\n";
        $success_count++;
    } else {
        echo "<p style='color:red;'>✗ {$mb_id}: 업데이트 실패</p>\n";
        $error_count++;
    }
}

if ($success_count > 0) {
    echo "<div style='background:#d4edda; border:1px solid #c3e6cb; padding:15px; margin:15px 0;'>\n";
    echo "<h3>✅ 패스워드 업데이트 완료!</h3>\n";
    echo "<p>성공: <strong>{$success_count}개</strong></p>\n";
    echo "<p>모든 계정의 패스워드: <strong>1234</strong></p>\n";
    echo "<p>이제 domaeka.com에서 로그인할 수 있습니다!</p>\n";
    echo "</div>\n";
}

if ($error_count > 0) {
    echo "<div style='background:#f8d7da; border:1px solid #f5c6cb; padding:15px; margin:15px 0;'>\n";
    echo "<h3>⚠️ 일부 업데이트 실패</h3>\n";
    echo "<p>실패: <strong>{$error_count}개</strong></p>\n";
    echo "</div>\n";
}

// 업데이트 후 상태 확인
echo "<h3>업데이트 후 패스워드 상태:</h3>\n";
$sql = "SELECT mb_id, mb_name, LEFT(mb_password, 30) as password_preview, LENGTH(mb_password) as pwd_length
        FROM {$g5['member_table']} 
        WHERE mb_id IN ('agency_admin1', 'agency_admin2', 'branch_admin1', 'branch_admin2', 'branch_admin3')
        ORDER BY mb_id";

$result = sql_query($sql);
echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>\n";
echo "<tr style='background:#f8f9fa;'><th>ID</th><th>이름</th><th>패스워드 미리보기</th><th>길이</th><th>상태</th></tr>\n";

while ($row = sql_fetch_array($result)) {
    $is_correct = (strpos($row['password_preview'], 'sha256:') === 0 && $row['pwd_length'] < 100);
    $bg_color = $is_correct ? '#d4edda' : '#f8d7da';
    $status = $is_correct ? '✅ 정상' : '❌ 비정상';
    
    echo "<tr style='background:{$bg_color};'>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td><code>{$row['password_preview']}...</code></td>";
    echo "<td>{$row['pwd_length']}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<div style='background:#e2e3e5; border:1px solid #d6d8db; padding:15px; margin:15px 0;'>\n";
echo "<h3>🔐 로그인 테스트</h3>\n";
echo "<p>다음 계정들로 <a href='http://domaeka.com/main/' target='_blank'>domaeka.com</a>에서 로그인 테스트하세요:</p>\n";
echo "<ul>\n";
foreach ($admin_accounts as $mb_id => $password) {
    echo "<li><strong>{$mb_id}</strong> / {$password}</li>\n";
}
echo "</ul>\n";
echo "</div>\n";

echo "<div style='background:#fff3cd; border:1px solid #ffeaa7; padding:15px; margin:15px 0;'>\n";
echo "<h3>⚠️ 보안 주의사항</h3>\n";
echo "<p><strong>이 스크립트 파일을 즉시 삭제하세요!</strong></p>\n";
echo "<p>파일 경로: <code>/var/www/html/main/fix_dmk_passwords.php</code></p>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><small>실행 시간: " . date('Y-m-d H:i:s') . "</small></p>\n";
?> 