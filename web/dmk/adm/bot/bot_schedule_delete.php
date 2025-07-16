<?php
/**
 * 스케줄링 발송 삭제 처리
 */

$sub_menu = "180600";
include_once('./_common.php');

auth_check('180600', 'd');

$id = $_GET['id'];

if (!$id) {
    alert('스케줄 ID가 없습니다.');
}

// 기존 스케줄 정보 확인
$sql = " SELECT * FROM kb_schedule WHERE id = '$id' ";
$schedule = sql_fetch($sql);

if (!$schedule['id']) {
    alert('등록된 스케줄이 아닙니다.');
}

// 권한 체크
$user_info = dmk_get_admin_auth();
if (!$user_info['is_super']) {
    // 스케줄 소유권 확인
    $can_delete = false;
    
    // created_by_type이 비어있으면 삭제 불가
    if (empty($schedule['created_by_type'])) {
        $can_delete = false;
    } else if ($user_info['mb_type'] == 'distributor') {
        // 총판은 자신과 하위 스케줄 삭제 가능
        if ($schedule['created_by_type'] == 'distributor' && $schedule['created_by_id'] == $user_info['key']) {
            $can_delete = true;
        } else if ($schedule['created_by_type'] == 'agency') {
            $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['key']}' AND ag_id = '{$schedule['created_by_id']}' ";
            if (sql_fetch($sql)) $can_delete = true;
        } else if ($schedule['created_by_type'] == 'branch') {
            $sql = " SELECT b.br_id FROM dmk_branch b 
                     JOIN dmk_agency a ON b.ag_id = a.ag_id 
                     WHERE a.dt_id = '{$user_info['key']}' AND b.br_id = '{$schedule['created_by_id']}' ";
            if (sql_fetch($sql)) $can_delete = true;
        }
    } else if ($user_info['mb_type'] == 'agency') {
        // 대리점은 자신과 하위 스케줄 삭제 가능
        if ($schedule['created_by_type'] == 'agency' && $schedule['created_by_id'] == $user_info['key']) {
            $can_delete = true;
        } else if ($schedule['created_by_type'] == 'branch') {
            $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['key']}' AND br_id = '{$schedule['created_by_id']}' ";
            if (sql_fetch($sql)) $can_delete = true;
        }
    } else if ($user_info['mb_type'] == 'branch') {
        // 지점은 자신의 스케줄만 삭제 가능
        if ($schedule['created_by_type'] == 'branch' && $schedule['created_by_id'] == $user_info['key']) {
            $can_delete = true;
        }
    }
    
    if (!$can_delete) {
        alert('이 스케줄을 삭제할 권한이 없습니다.');
    }
}

// 이미지 파일 삭제
$upload_dir = G5_DATA_PATH.'/schedule';

if ($schedule['message_images_1']) {
    $images_1 = json_decode($schedule['message_images_1'], true);
    foreach ($images_1 as $img) {
        @unlink($upload_dir . '/' . $img['file']);
    }
}

if ($schedule['message_images_2']) {
    $images_2 = json_decode($schedule['message_images_2'], true);
    foreach ($images_2 as $img) {
        @unlink($upload_dir . '/' . $img['file']);
    }
}

// 스케줄 삭제
$sql = " DELETE FROM kb_schedule WHERE id = '$id' ";
sql_query($sql);

// 관련 로그도 삭제 (CASCADE 설정이 없는 경우)
$sql = " DELETE FROM kb_schedule_logs WHERE schedule_id = '$id' ";
sql_query($sql);

// 로그 기록
// dmk_admin_log('스케줄링 발송 삭제', "ID: $id, 제목: {$schedule['title']}");

goto_url('./bot_schedule_list.php?'.$qstr);
?>