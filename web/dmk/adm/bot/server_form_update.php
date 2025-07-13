<?php
/**
 * 서버 등록/수정 처리
 */

include_once('./_common.php');

auth_check('180100', 'w');

check_admin_token();

$w = $_POST['w'];
$server_id = $_POST['server_id'];

if (!$_POST['server_name']) {
    alert('서버명을 입력하세요.');
}

if (!$_POST['server_host']) {
    alert('서버 호스트를 입력하세요.');
}

$server_name = trim($_POST['server_name']);
$server_host = trim($_POST['server_host']);
$priority = (int)$_POST['priority'];
$status = $_POST['status'];
$max_bots = $_POST['max_bots'] ? (int)$_POST['max_bots'] : null;
$description = trim($_POST['description']);

if (!$priority) $priority = 100;
if (!in_array($status, ['healthy', 'degraded', 'maintenance', 'failed'])) {
    $status = 'healthy';
}

if ($w == 'u' && $server_id) {
    // 수정
    $sql = " UPDATE kb_servers SET 
                server_name = '$server_name',
                server_host = '$server_host', 
                priority = '$priority',
                status = '$status',
                max_bots = " . ($max_bots ? "'$max_bots'" : "NULL") . ",
                description = '$description',
                updated_at = NOW()
             WHERE server_id = '$server_id' ";
    sql_query($sql);
    
    alert('서버 정보가 수정되었습니다.', './server_list.php');
    
} else {
    // 등록
    
    // 중복 체크
    $sql = " SELECT COUNT(*) as cnt FROM kb_servers WHERE server_name = '$server_name' OR server_host = '$server_host' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 등록된 서버명 또는 호스트입니다.');
    }
    
    $sql = " INSERT INTO kb_servers SET 
                server_name = '$server_name',
                server_host = '$server_host',
                priority = '$priority', 
                status = '$status',
                max_bots = " . ($max_bots ? "'$max_bots'" : "NULL") . ",
                current_bots = 0,
                description = '$description',
                created_at = NOW(),
                updated_at = NOW() ";
    sql_query($sql);
    
    alert('서버가 등록되었습니다.', './server_list.php');
}
?>