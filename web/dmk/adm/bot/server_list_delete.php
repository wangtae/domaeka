<?php
/**
 * 서버 삭제 처리
 */

include_once('./_common.php');

auth_check('180100', 'd');

if (!isset($_POST['chk']) || !is_array($_POST['chk'])) {
    alert('삭제할 서버를 선택하세요.');
}

$deleted_count = 0;

foreach ($_POST['chk'] as $server_id) {
    $server_id = (int)$server_id;
    if (!$server_id) continue;
    
    // 연결된 프로세스가 있는지 확인
    $sql = " SELECT COUNT(*) as cnt FROM kb_server_processes WHERE server_id = '$server_id' ";
    $row = sql_fetch($sql);
    
    if ($row['cnt'] > 0) {
        continue; // 연결된 프로세스가 있으면 삭제하지 않음
    }
    
    // 서버 삭제
    $sql = " DELETE FROM kb_servers WHERE server_id = '$server_id' ";
    sql_query($sql);
    
    $deleted_count++;
}

if ($deleted_count > 0) {
    alert($deleted_count.'개의 서버가 삭제되었습니다.', './server_list.php');
} else {
    alert('삭제된 서버가 없습니다. 연결된 프로세스가 있는 서버는 삭제할 수 없습니다.', './server_list.php');
}
?>