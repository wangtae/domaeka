<?php
/**
 * 서버 프로세스 등록/수정 처리
 */

<<<<<<< HEAD
$sub_menu = "180200";
=======
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
include_once('./_common.php');

auth_check('180200', 'w');

check_admin_token();

$w = $_POST['w'];
$process_id = $_POST['process_id'];
<<<<<<< HEAD
$server_id = $_POST['server_id'];
$process_name = $_POST['process_name'];
$process_type = $_POST['process_type'];
$port = $_POST['port'];
$auto_restart = $_POST['auto_restart'];
$max_memory_mb = $_POST['max_memory_mb'];
$config_json = $_POST['config_json'];
$status = $_POST['status'];
$description = $_POST['description'];

// 필수 값 검증
if (!$server_id) {
    alert('서버를 선택하세요.');
}

if (!$process_name) {
    alert('프로세스명을 입력하세요.');
}

if (!$process_type) {
    alert('프로세스 타입을 선택하세요.');
}

// 서버 존재 여부 확인
$server_check = sql_fetch("SELECT server_id FROM kb_servers WHERE server_id = '$server_id'");
if (!$server_check) {
    alert('존재하지 않는 서버입니다.');
}

// JSON 검증
if ($config_json) {
    $json_test = json_decode($config_json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        alert('설정 JSON 형식이 올바르지 않습니다.');
    }
}

// 포트 범위 검증
if ($port && ($port < 1024 || $port > 65535)) {
    alert('포트는 1024-65535 범위여야 합니다.');
}

// 메모리 범위 검증
if ($max_memory_mb && ($max_memory_mb < 128 || $max_memory_mb > 8192)) {
    alert('최대 메모리는 128-8192 MB 범위여야 합니다.');
}

// 기본값 설정
$auto_restart = $auto_restart ? 1 : 0;
$port = $port ? $port : null;
$max_memory_mb = $max_memory_mb ? $max_memory_mb : null;
$status = $status ? $status : 'stopped';

if ($w == 'u' && $process_id) {
    // 수정
    $sql = " UPDATE kb_server_processes SET 
                server_id = '$server_id',
                process_name = '$process_name',
                process_type = '$process_type',
                port = " . ($port ? "'$port'" : "NULL") . ",
                auto_restart = '$auto_restart',
                max_memory_mb = " . ($max_memory_mb ? "'$max_memory_mb'" : "NULL") . ",
                config_json = '$config_json',
                status = '$status',
                description = '$description',
                updated_at = NOW()
=======

// 입력값 검증
if (!$_POST['server_id']) {
    alert('서버를 선택해주세요.');
}

if (!$_POST['process_name']) {
    alert('프로세스명을 입력해주세요.');
}

if (!$_POST['process_type']) {
    alert('프로세스 유형을 선택해주세요.');
}

// 포트 중복 체크
if ($_POST['port']) {
    $port_check_sql = " SELECT process_id FROM kb_server_processes 
                       WHERE server_id = '{$_POST['server_id']}' 
                       AND port = '{$_POST['port']}' ";
    if ($w == 'u') {
        $port_check_sql .= " AND process_id != '$process_id' ";
    }
    $port_check = sql_fetch($port_check_sql);
    if ($port_check['process_id']) {
        alert('해당 서버에서 이미 사용 중인 포트입니다.');
    }
}

// 프로세스명 중복 체크
$name_check_sql = " SELECT process_id FROM kb_server_processes 
                   WHERE process_name = '{$_POST['process_name']}' ";
if ($w == 'u') {
    $name_check_sql .= " AND process_id != '$process_id' ";
}
$name_check = sql_fetch($name_check_sql);
if ($name_check['process_id']) {
    alert('이미 사용 중인 프로세스명입니다.');
}

// 데이터 준비
$server_id = $_POST['server_id'];
$process_name = $_POST['process_name'];
$process_type = $_POST['process_type'];
$port = $_POST['port'] ? $_POST['port'] : null;
$mode = $_POST['mode'] ? $_POST['mode'] : 'test';
$log_level = $_POST['log_level'] ? $_POST['log_level'] : 'INFO';
$auto_restart = $_POST['auto_restart'] ? $_POST['auto_restart'] : 'N';
$status = $_POST['status'] ? $_POST['status'] : 'inactive';
$description = $_POST['description'];

if ($w == '' || $w == 'a') {
    // 등록
    $sql = " INSERT INTO kb_server_processes SET 
             server_id = '$server_id',
             process_name = '$process_name',
             process_type = '$process_type',
             port = " . ($port ? "'$port'" : "NULL") . ",
             mode = '$mode',
             log_level = '$log_level',
             auto_restart = '$auto_restart',
             status = '$status',
             description = '$description',
             created_at = NOW(),
             updated_at = NOW() ";
    
    sql_query($sql);
    
    // 로그 기록
    dmk_admin_log('서버 프로세스 등록', "프로세스명: $process_name, 서버ID: $server_id");
    
    goto_url('./server_process_list.php');
    
} else if ($w == 'u') {
    // 수정
    if (!$process_id) {
        alert('프로세스 ID가 없습니다.');
    }
    
    // 기존 프로세스 정보 확인
    $sql = " SELECT * FROM kb_server_processes WHERE process_id = '$process_id' ";
    $pr = sql_fetch($sql);
    if (!$pr['process_id']) {
        alert('등록된 프로세스가 아닙니다.');
    }
    
    $sql = " UPDATE kb_server_processes SET 
             server_id = '$server_id',
             process_name = '$process_name',
             process_type = '$process_type',
             port = " . ($port ? "'$port'" : "NULL") . ",
             mode = '$mode',
             log_level = '$log_level',
             auto_restart = '$auto_restart',
             status = '$status',
             description = '$description',
             updated_at = NOW()
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
             WHERE process_id = '$process_id' ";
    
    sql_query($sql);
    
<<<<<<< HEAD
    alert('수정되었습니다.', './server_process_list.php');
    
} else {
    // 등록
    
    // 프로세스명 중복 검사
    $name_check = sql_fetch("SELECT process_id FROM kb_server_processes WHERE process_name = '$process_name'");
    if ($name_check) {
        alert('이미 사용중인 프로세스명입니다.');
    }
    
    // 포트 중복 검사 (포트가 지정된 경우)
    if ($port) {
        $port_check = sql_fetch("SELECT process_id FROM kb_server_processes WHERE server_id = '$server_id' AND port = '$port'");
        if ($port_check) {
            alert('해당 서버에서 이미 사용중인 포트입니다.');
        }
    }
    
    $sql = " INSERT INTO kb_server_processes SET 
                server_id = '$server_id',
                process_name = '$process_name',
                process_type = '$process_type',
                port = " . ($port ? "'$port'" : "NULL") . ",
                auto_restart = '$auto_restart',
                max_memory_mb = " . ($max_memory_mb ? "'$max_memory_mb'" : "NULL") . ",
                config_json = '$config_json',
                status = '$status',
                description = '$description',
                created_at = NOW(),
                updated_at = NOW() ";
    
    sql_query($sql);
    
    alert('등록되었습니다.', './server_process_list.php');
=======
    // 로그 기록
    dmk_admin_log('서버 프로세스 수정', "프로세스ID: $process_id, 프로세스명: $process_name");
    
    goto_url('./server_process_list.php');
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
}
?>