<?php
/**
 * 서버 프로세스 등록/수정 처리
 */

$sub_menu = "180200";
include_once('./_common.php');

auth_check('180200', 'w');

check_admin_token();

$w = $_POST['w'];
$process_id = $_POST['process_id'];
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
             WHERE process_id = '$process_id' ";
    
    sql_query($sql);
    
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
}
?>