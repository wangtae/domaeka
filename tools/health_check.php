<?php
/**
 * health_check.php - AI가 시스템 상태를 확인할 수 있는 헬스체크 엔드포인트
 * URL: http://domaeka.local/health_check.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$health = [
    'timestamp' => date('c'),
    'status' => 'ok',
    'services' => [],
    'system' => [],
    'details' => []
];

try {
    // 1. 데이터베이스 연결 확인
    try {
        // 설정 파일에서 DB 정보 읽기 (실제 경로에 맞게 수정)
        $db_config = [
            'host' => 'localhost',
            'dbname' => 'domaeka',
            'username' => 'root',
            'password' => ''
        ];
        
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // 간단한 쿼리 실행
        $stmt = $pdo->query('SELECT 1');
        $health['services']['database'] = [
            'status' => 'ok',
            'response_time' => microtime(true)
        ];
        
    } catch (PDOException $e) {
        $health['services']['database'] = [
            'status' => 'error',
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ];
        $health['status'] = 'error';
    }

    // 2. 파일 시스템 쓰기 권한 확인
    $test_file = sys_get_temp_dir() . '/health_check_' . uniqid();
    if (file_put_contents($test_file, 'test') !== false) {
        unlink($test_file);
        $health['services']['filesystem'] = ['status' => 'ok'];
    } else {
        $health['services']['filesystem'] = [
            'status' => 'error',
            'error' => 'Cannot write to filesystem'
        ];
        $health['status'] = 'error';
    }

    // 3. PHP 설정 확인
    $health['system']['php'] = [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
    ];

    // 4. 확장 모듈 확인
    $required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json'];
    $health['system']['extensions'] = [];
    
    foreach ($required_extensions as $ext) {
        $health['system']['extensions'][$ext] = extension_loaded($ext) ? 'loaded' : 'missing';
        if (!extension_loaded($ext)) {
            $health['status'] = 'warning';
        }
    }

    // 5. 세션 상태 확인
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $health['services']['session'] = [
        'status' => 'ok',
        'session_id' => session_id(),
        'save_path' => session_save_path()
    ];

    // 6. 로그 파일 상태 확인
    $log_files = [
        'nginx_error' => '/var/log/nginx/domaeka.local_error.log',
        'php_fpm' => '/var/log/php8.3-fpm.log',
        'mysql_error' => '/var/log/mysql/error.log'
    ];
    
    $health['system']['logs'] = [];
    foreach ($log_files as $name => $path) {
        if (file_exists($path)) {
            $health['system']['logs'][$name] = [
                'status' => 'exists',
                'size' => filesize($path),
                'readable' => is_readable($path)
            ];
        } else {
            $health['system']['logs'][$name] = ['status' => 'not_found'];
        }
    }

    // 7. 디스크 공간 확인
    $disk_free = disk_free_space('/');
    $disk_total = disk_total_space('/');
    $disk_used_percent = round((($disk_total - $disk_free) / $disk_total) * 100, 2);
    
    $health['system']['disk'] = [
        'free_space' => round($disk_free / 1024 / 1024 / 1024, 2) . 'GB',
        'total_space' => round($disk_total / 1024 / 1024 / 1024, 2) . 'GB',
        'used_percent' => $disk_used_percent . '%'
    ];
    
    if ($disk_used_percent > 90) {
        $health['status'] = 'warning';
        $health['details'][] = 'Disk space usage is high: ' . $disk_used_percent . '%';
    }

    // 8. 환경 변수 확인 (중요한 것들만)
    $health['system']['environment'] = [
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'php_sapi' => php_sapi_name()
    ];

    // 9. 현재 시간과 타임존 확인
    $health['system']['time'] = [
        'server_time' => date('Y-m-d H:i:s'),
        'utc_time' => gmdate('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];

} catch (Exception $e) {
    $health['status'] = 'error';
    $health['error'] = $e->getMessage();
    $health['trace'] = $e->getTraceAsString();
}

// 최종 상태 설정
if ($health['status'] === 'ok') {
    http_response_code(200);
} elseif ($health['status'] === 'warning') {
    http_response_code(200); // 경고는 여전히 200
} else {
    http_response_code(503); // Service Unavailable
}

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?> 