<?php
/**
 * 채팅방 승인/거부 처리
 */

$sub_menu = "180200";
include_once('./_common.php');

auth_check('180200', 'w');

$room_id = $_GET['room_id'];
$action = $_GET['action'];

// URL 파라미터 유지
$qstr = '';
if (isset($_REQUEST['sfl']))  $qstr .= '&amp;sfl=' . urlencode($_REQUEST['sfl']);
if (isset($_REQUEST['stx']))  $qstr .= '&amp;stx=' . urlencode($_REQUEST['stx']);
if (isset($_REQUEST['sst']))  $qstr .= '&amp;sst=' . urlencode($_REQUEST['sst']);
if (isset($_REQUEST['sod']))  $qstr .= '&amp;sod=' . urlencode($_REQUEST['sod']);
if (isset($_REQUEST['page'])) $qstr .= '&amp;page=' . urlencode($_REQUEST['page']);

if (!$room_id) {
    alert('채팅방 ID가 없습니다.');
}

if (!in_array($action, ['approve', 'deny'])) {
    alert('잘못된 요청입니다.');
}

// 채팅방 정보 확인
$sql = " SELECT * FROM kb_rooms WHERE room_id = '".sql_escape_string($room_id)."' ";
$room = sql_fetch($sql);

if (!$room['room_id']) {
    alert('존재하지 않는 채팅방입니다.');
}

// 권한 확인
$user_info = dmk_get_admin_auth();
if (!$user_info['is_super']) {
    // 지점이 배정된 방의 경우 해당 계층만 승인 가능
    if ($room['owner_id']) {
        $can_approve = false;
        
        if ($user_info['type'] == 'branch' && $room['owner_id'] == $user_info['key']) {
            $can_approve = true;
        } else if ($user_info['type'] == 'agency') {
            // 대리점은 하위 지점의 방 승인 가능
            $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['key']}' AND br_id = '".sql_escape_string($room['owner_id'])."' ";
            if (sql_fetch($sql)) $can_approve = true;
        } else if ($user_info['type'] == 'distributor') {
            // 총판은 하위 대리점의 지점 방 승인 가능
            $sql = " SELECT b.br_id 
                     FROM dmk_branch b 
                     JOIN dmk_agency a ON b.ag_id = a.ag_id 
                     WHERE a.dt_id = '{$user_info['key']}' AND b.br_id = '".sql_escape_string($room['owner_id'])."' ";
            if (sql_fetch($sql)) $can_approve = true;
        }
        
        if (!$can_approve) {
            alert('이 채팅방을 승인할 권한이 없습니다.');
        }
    }
}

// 상태 업데이트
if ($action == 'approve') {
    $new_status = 'approved';
    $approved_at = ", approved_at = NOW()";
    $message = '승인되었습니다.';
} else {
    $new_status = 'denied';
    $approved_at = "";
    $message = '거부되었습니다.';
}

$sql = " UPDATE kb_rooms SET 
         status = '{$new_status}',
         updated_at = NOW()
         {$approved_at}
         WHERE room_id = '".sql_escape_string($room_id)."' ";

if (sql_query($sql)) {
    // 로그 기록 (옵션)
    // dmk_write_log('room_status_change', "{$room['room_name']} 채팅방 {$message}", "room_id: {$room_id}");
    
    alert($message, './room_list.php?' . $qstr);
} else {
    alert('처리 중 오류가 발생했습니다.');
}
?>