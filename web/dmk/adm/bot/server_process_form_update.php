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
             WHERE process_id = '$process_id' ";
    
    sql_query($sql);
    
    // 로그 기록
    dmk_admin_log('서버 프로세스 수정', "프로세스ID: $process_id, 프로세스명: $process_name");
    
    goto_url('./server_process_list.php');
}
?>