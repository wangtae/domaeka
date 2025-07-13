<?php
// 기본 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Debug Information</h3>";
echo "<pre>";

// 1. PHP 환경 체크
echo "PHP Version: " . phpversion() . "\n";

// 2. 경로 체크
echo "\nPath Information:\n";
echo "Current Dir: " . __DIR__ . "\n";
echo "Common.php Path: " . realpath(__DIR__ . '/../common.php') . "\n";

// 3. common.php 포함 테스트
try {
    include_once(__DIR__ . '/../common.php');
    echo "\ncommon.php loaded successfully\n";
    
    // 4. 데이터베이스 연결 체크
    if(isset($g5)) {
        echo "\n\$g5 array is set\n";
        echo "G5_URL: " . (defined('G5_URL') ? G5_URL : 'Not defined') . "\n";
        echo "G5_DATA_URL: " . (defined('G5_DATA_URL') ? G5_DATA_URL : 'Not defined') . "\n";
    }
    
    // 5. 데이터베이스 쿼리 테스트
    $test_sql = "SELECT COUNT(*) as cnt FROM dmk_branch";
    $result = sql_fetch($test_sql);
    echo "\nBranch count: " . $result['cnt'] . "\n";
    
    // 6. URL 코드 테스트
    $url_code = 'x647r8iklh';
    $branch_sql = "SELECT * FROM dmk_branch WHERE br_shortcut_code = '$url_code'";
    $branch = sql_fetch($branch_sql);
    echo "\nBranch found: " . ($branch ? 'Yes' : 'No') . "\n";
    if($branch) {
        echo "Branch ID: " . $branch['br_id'] . "\n";
        echo "Branch Status: " . $branch['br_status'] . "\n";
    }
    
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>