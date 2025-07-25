<?php
/**
 * 서버 등록/수정 처리
 */

include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.log.lib.php');

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
    // 수정 전 기존 데이터 조회 (로그용)
    $old_sql = " SELECT server_name, server_host, priority, status, max_bots, description 
                 FROM kb_servers WHERE server_id = '$server_id' ";
    $old_row = sql_fetch($old_sql);
    
    if (!$old_row) {
        alert('존재하지 않는 서버입니다.');
    }
    
    $old_data = [
        'server_id' => $server_id,
        'server_name' => $old_row['server_name'],
        'server_host' => $old_row['server_host'],
        'priority' => $old_row['priority'],
        'status' => $old_row['status'],
        'max_bots' => $old_row['max_bots'],
        'description' => $old_row['description']
    ];
    
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
    
    // 새로운 데이터 구성
    $new_data = [
        'server_id' => $server_id,
        'server_name' => $server_name,
        'server_host' => $server_host,
        'priority' => $priority,
        'status' => $status,
        'max_bots' => $max_bots,
        'description' => $description
    ];

    // 관리자 액션 로그 (향상된 변경 내용 추적)
    dmk_log_update_action('서버 정보 수정', '서버ID: '.$server_id.', 서버명: '.$server_name, $new_data, $old_data, '180100', 'kb_servers');
    
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
    
    // 관리자 액션 로그
    dmk_log_admin_action(
        'insert',
        '서버 등록: ' . $server_name . ' (' . $server_host . ')',
        'kb_servers',
        json_encode([
            'server_name' => $server_name,
            'server_host' => $server_host,
            'priority' => $priority,
            'status' => $status,
            'max_bots' => $max_bots,
            'description' => $description
        ]),
        null,
        '180100'
    );
    
    alert('서버가 등록되었습니다.', './server_list.php');
}
?>