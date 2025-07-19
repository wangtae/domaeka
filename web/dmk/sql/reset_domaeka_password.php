<?php
/**
 * domaeka 관리자 비밀번호 재설정 스크립트
 * 
 * 실행 방법: 브라우저에서 이 파일에 직접 접근
 * 예: http://도메인/dmk/sql/reset_domaeka_password.php
 */

// 그누보드5 설정 로드
define('G5_SET_TIME_LIMIT', 0);
include_once('../../_common.php');

// 관리자 권한 확인
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>domaeka 관리자 패스워드 재설정</h2>\n";

// 현재 domaeka 계정 정보 확인
$sql = "SELECT mb_id, mb_name, mb_level, dmk_mb_type, mb_password 
        FROM {$g5['member_table']} 
        WHERE mb_id = 'domaeka'";
$mb = sql_fetch($sql);

if (!$mb) {
    die("<p style='color:red'>domaeka 계정을 찾을 수 없습니다.</p>");
}

echo "<h3>현재 계정 정보:</h3>\n";
echo "<ul>\n";
echo "<li>아이디: <strong>{$mb['mb_id']}</strong></li>\n";
echo "<li>이름: <strong>{$mb['mb_name']}</strong></li>\n";
echo "<li>권한 레벨: <strong>{$mb['mb_level']}</strong></li>\n";
echo "<li>도매까 타입: <strong>{$mb['dmk_mb_type']}</strong></li>\n";
echo "<li>현재 비밀번호 형식: <strong>" . substr($mb['mb_password'], 0, 10) . "...</strong></li>\n";
echo "</ul>\n";

// 새 비밀번호 설정
$new_password = '1234';  // 테스트용 비밀번호

try {
    // 그누보드5 표준 암호화 함수 사용
    $encrypted_password = get_encrypt_string($new_password);
    
    // 패스워드 업데이트
    $sql = "UPDATE {$g5['member_table']} 
            SET mb_password = '" . sql_escape_string($encrypted_password) . "' 
            WHERE mb_id = 'domaeka'";
    
    $result = sql_query($sql);
    
    if ($result) {
        echo "<div style='background:#d4edda; border:1px solid #c3e6cb; padding:10px; margin:10px 0;'>\n";
        echo "<strong>✓ 패스워드 재설정 완료!</strong><br>\n";
        echo "아이디: <code>domaeka</code><br>\n";
        echo "새 비밀번호: <code>{$new_password}</code><br>\n";
        echo "암호화된 비밀번호: <code>" . substr($encrypted_password, 0, 30) . "...</code>\n";
        echo "</div>\n";
        
        // 업데이트 확인
        $check_sql = "SELECT mb_password FROM {$g5['member_table']} WHERE mb_id = 'domaeka'";
        $check = sql_fetch($check_sql);
        
        echo "<h3>업데이트 확인:</h3>\n";
        echo "<p>저장된 비밀번호: <code>" . substr($check['mb_password'], 0, 50) . "...</code></p>\n";
        
    } else {
        echo "<div style='background:#f8d7da; border:1px solid #f5c6cb; padding:10px; margin:10px 0;'>\n";
        echo "<strong>⚠ 패스워드 업데이트 실패</strong><br>\n";
        echo "데이터베이스 오류가 발생했습니다.\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da; border:1px solid #f5c6cb; padding:10px; margin:10px 0;'>\n";
    echo "<strong>⚠ 오류 발생</strong><br>\n";
    echo $e->getMessage() . "\n";
    echo "</div>\n";
}

echo "<hr>\n";
echo "<p><small>실행 시간: " . date('Y-m-d H:i:s') . "</small></p>\n";
echo "<p><strong>보안 경고:</strong> 이 스크립트는 실행 후 즉시 삭제하세요!</p>\n";
?>