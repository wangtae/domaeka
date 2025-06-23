<?php
/**
 * 도매까 관리자 패스워드 업데이트 스크립트
 * 
 * MySQL PASSWORD() 함수로 생성된 패스워드를 
 * 그누보드5 표준 get_encrypt_string() 방식으로 변경
 * 
 * 실행 방법: 브라우저에서 이 파일에 직접 접근
 * 예: http://도메인/main/dmk/sql/004_update_admin_passwords.php
 */

// 그누보드5 설정 로드
define('G5_SET_TIME_LIMIT', 0);
include_once('../../_common.php');

// 관리자 권한 확인
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>도매까 관리자 패스워드 업데이트</h2>\n";
echo "<p>MySQL PASSWORD() 방식 → 그누보드5 get_encrypt_string() 방식</p>\n";

// 업데이트할 관리자 목록과 패스워드
$admin_accounts = array(
    'agency_admin1' => '1234',  // 서울중앙대리점 관리자
    'agency_admin2' => '1234',  // 부산남부대리점 관리자  
    'branch_admin1' => '1234',  // 강남1호점 관리자
    'branch_admin2' => '1234',  // 강남2호점 관리자
    'branch_admin3' => '1234'   // 해운대점 관리자
);

echo "<h3>패스워드 업데이트 진행:</h3>\n";
echo "<ul>\n";

$success_count = 0;
$error_count = 0;

foreach ($admin_accounts as $mb_id => $password) {
    try {
        // 그누보드5 표준 암호화 함수 사용
        $encrypted_password = get_encrypt_string($password);
        
        // 패스워드 업데이트
        $sql = "UPDATE {$g5['member_table']} 
                SET mb_password = '" . sql_escape_string($encrypted_password) . "' 
                WHERE mb_id = '" . sql_escape_string($mb_id) . "'";
        
        $result = sql_query($sql);
        
        if ($result) {
            echo "<li><strong>{$mb_id}</strong>: 패스워드 업데이트 성공</li>\n";
            $success_count++;
        } else {
            echo "<li><strong>{$mb_id}</strong>: <span style='color:red'>업데이트 실패</span></li>\n";
            $error_count++;
        }
        
    } catch (Exception $e) {
        echo "<li><strong>{$mb_id}</strong>: <span style='color:red'>오류 - " . $e->getMessage() . "</span></li>\n";
        $error_count++;
    }
}

echo "</ul>\n";

echo "<h3>업데이트 결과:</h3>\n";
echo "<p>성공: <strong>{$success_count}개</strong>, 실패: <strong>{$error_count}개</strong></p>\n";

if ($success_count > 0) {
    echo "<div style='background:#d4edda; border:1px solid #c3e6cb; padding:10px; margin:10px 0;'>\n";
    echo "<strong>✓ 패스워드 업데이트 완료!</strong><br>\n";
    echo "모든 도매까 관리자 계정의 패스워드가 그누보드5 표준 방식으로 암호화되었습니다.<br>\n";
    echo "로그인 패스워드: <code>1234</code>\n";
    echo "</div>\n";
}

if ($error_count > 0) {
    echo "<div style='background:#f8d7da; border:1px solid #f5c6cb; padding:10px; margin:10px 0;'>\n";
    echo "<strong>⚠ 일부 계정 업데이트 실패</strong><br>\n";
    echo "실패한 계정은 수동으로 패스워드를 재설정해 주세요.\n";
    echo "</div>\n";
}

// 업데이트 후 패스워드 형태 확인
echo "<h3>업데이트 후 패스워드 형태 확인:</h3>\n";
$sql = "SELECT mb_id, mb_name, LEFT(mb_password, 30) as password_preview, LENGTH(mb_password) as pwd_length
        FROM {$g5['member_table']} 
        WHERE mb_id IN ('agency_admin1', 'agency_admin2', 'branch_admin1', 'branch_admin2', 'branch_admin3')
        ORDER BY mb_id";

$result = sql_query($sql);
echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>\n";
echo "<tr style='background:#f8f9fa;'><th>관리자ID</th><th>이름</th><th>패스워드 미리보기</th><th>길이</th></tr>\n";

while ($row = sql_fetch_array($result)) {
    $bg_color = (strpos($row['password_preview'], 'sha256:') === 0) ? '#d4edda' : '#f8d7da';
    echo "<tr style='background:{$bg_color};'>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td><code>{$row['password_preview']}...</code></td>";
    echo "<td>{$row['pwd_length']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<div style='background:#e2e3e5; border:1px solid #d6d8db; padding:10px; margin:10px 0;'>\n";
echo "<strong>참고:</strong><br>\n";
echo "• 올바른 형태: <code>sha256:...</code> (연한 초록색)<br>\n";
echo "• 잘못된 형태: <code>*A4B...</code> (연한 빨간색)<br>\n";
echo "• 이 스크립트 실행 후에는 파일을 삭제하는 것을 권장합니다.\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><small>실행 시간: " . date('Y-m-d H:i:s') . "</small></p>\n";
?> 