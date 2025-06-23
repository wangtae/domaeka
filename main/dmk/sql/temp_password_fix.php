<?php
/**
 * 임시 패스워드 수정 스크립트
 * main 폴더에서 직접 실행
 */

// 그누보드5 설정 로드
include_once('../../_common.php');

// 관리자 권한 확인
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>도매까 관리자 패스워드 수정</h2>\n";

// 현재 패스워드 상태 확인
echo "<h3>현재 패스워드 상태:</h3>\n";
$sql = "SELECT mb_id, mb_name, LEFT(mb_password, 30) as password_preview, LENGTH(mb_password) as pwd_length
        FROM {$g5['member_table']} 
        WHERE mb_id IN ('agency_admin1', 'agency_admin2', 'branch_admin1', 'branch_admin2', 'branch_admin3')
        ORDER BY mb_id";

$result = sql_query($sql);
echo "<table border='1' style='border-collapse:collapse;'>\n";
echo "<tr><th>ID</th><th>이름</th><th>패스워드 미리보기</th><th>길이</th></tr>\n";
while ($row = sql_fetch_array($result)) {
    echo "<tr>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td><code>{$row['password_preview']}...</code></td>";
    echo "<td>{$row['pwd_length']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// 패스워드 업데이트
$admin_accounts = array(
    'agency_admin1' => '1234',
    'agency_admin2' => '1234',
    'branch_admin1' => '1234',
    'branch_admin2' => '1234',
    'branch_admin3' => '1234'
);

echo "<h3>패스워드 업데이트:</h3>\n";
$success_count = 0;

foreach ($admin_accounts as $mb_id => $password) {
    // 그누보드5 표준 암호화
    $encrypted_password = get_encrypt_string($password);
    
    $sql = "UPDATE {$g5['member_table']} 
            SET mb_password = '" . sql_escape_string($encrypted_password) . "' 
            WHERE mb_id = '" . sql_escape_string($mb_id) . "'";
    
    $result = sql_query($sql);
    
    if ($result) {
        echo "<p>✓ {$mb_id}: 패스워드 업데이트 성공</p>\n";
        $success_count++;
    } else {
        echo "<p>✗ {$mb_id}: 업데이트 실패</p>\n";
    }
}

echo "<h3>업데이트 완료!</h3>\n";
echo "<p>성공: {$success_count}개</p>\n";
echo "<p>모든 계정의 패스워드: <strong>1234</strong></p>\n";

// 업데이트 후 상태 확인
echo "<h3>업데이트 후 패스워드 상태:</h3>\n";
$result = sql_query($sql);
echo "<table border='1' style='border-collapse:collapse;'>\n";
echo "<tr><th>ID</th><th>이름</th><th>패스워드 미리보기</th><th>길이</th></tr>\n";
while ($row = sql_fetch_array($result)) {
    $bg_color = (strpos($row['password_preview'], 'sha256:') === 0 && $row['pwd_length'] < 100) ? '#d4edda' : '#f8d7da';
    echo "<tr style='background:{$bg_color};'>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td><code>{$row['password_preview']}...</code></td>";
    echo "<td>{$row['pwd_length']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<p><strong>이 파일을 삭제하세요!</strong></p>\n";
?> 