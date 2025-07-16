<?php
/**
 * 스케줄링 발송 등록/수정
 */

$sub_menu = "180600";
include_once('./_common.php');

// UTF-8 인코딩 설정
header('Content-Type: text/html; charset=utf-8');

auth_check('180600', 'w');

$w = $_GET['w'];
$id = $_GET['id'];

if ($w == 'u' && $id) {
    $sql = " SELECT * FROM kb_schedule WHERE id = '$id' ";
    $schedule = sql_fetch($sql);
    if (!$schedule['id']) {
        alert('등록된 스케줄이 아닙니다.');
    }
    
    // 권한 체크
    $user_info = dmk_get_admin_auth();
    if (!$user_info['is_super']) {
        // 스케줄 소유권 확인
        $can_edit = false;
        
        // created_by_type이 비어있으면 수정 불가
        if (empty($schedule['created_by_type'])) {
            $can_edit = false;
        } else if ($user_info['mb_type'] == 'distributor') {
            // 총판은 자신과 하위 스케줄 수정 가능
            if ($schedule['created_by_type'] == 'distributor' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'agency') {
                $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['key']}' AND ag_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT b.br_id FROM dmk_branch b 
                         JOIN dmk_agency a ON b.ag_id = a.ag_id 
                         WHERE a.dt_id = '{$user_info['key']}' AND b.br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['mb_type'] == 'agency') {
            // 대리점은 자신과 하위 스케줄 수정 가능
            if ($schedule['created_by_type'] == 'agency' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            } else if ($schedule['created_by_type'] == 'branch') {
                $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['key']}' AND br_id = '{$schedule['created_by_id']}' ";
                if (sql_fetch($sql)) $can_edit = true;
            }
        } else if ($user_info['mb_type'] == 'branch') {
            // 지점은 자신의 스케줄만 수정 가능
            if ($schedule['created_by_type'] == 'branch' && $schedule['created_by_id'] == $user_info['key']) {
                $can_edit = true;
            }
        }
        
        if (!$can_edit) {
            alert('이 스케줄을 수정할 권한이 없습니다.');
        }
    }
    
    // JSON 디코드
    $schedule['message_images_1'] = $schedule['message_images_1'] ? json_decode($schedule['message_images_1'], true) : [];
    $schedule['message_images_2'] = $schedule['message_images_2'] ? json_decode($schedule['message_images_2'], true) : [];
} else {
    $schedule = array();
}

$g5['title'] = '스케줄링 발송 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');

// GD 라이브러리 체크
if (!extension_loaded('gd')) {
    echo '<div class="local_desc01 local_desc" style="background-color: #fff3cd; border-color: #ffeaa7; color: #856404;">';
    echo '<p><strong>⚠️ PHP GD 라이브러리가 설치되어 있지 않습니다.</strong></p>';
    echo '<p>이미지 업로드 기능을 사용하려면 GD 라이브러리를 설치해야 합니다.</p>';
    echo '<p>설치 방법:</p>';
    echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px;">';
    echo '# Ubuntu/Debian 계열' . PHP_EOL;
    echo 'sudo apt-get update' . PHP_EOL;
    echo 'sudo apt-get install php-gd' . PHP_EOL;
    echo 'sudo service apache2 restart' . PHP_EOL . PHP_EOL;
    echo '# CentOS/RHEL 계열' . PHP_EOL;
    echo 'sudo yum install php-gd' . PHP_EOL;
    echo 'sudo service httpd restart';
    echo '</pre>';
    echo '<p>설치 후 이 페이지를 새로고침하세요.</p>';
    echo '</div>';
}

// 사용자 권한에 따른 지점 배정된 톡방과 봇 목록 조회
$user_info = dmk_get_admin_auth();

// status가 approved이고 지점이 배정된(owner_id가 있는) 톡방만 조회
$room_where = " WHERE r.status = 'approved' AND r.owner_id IS NOT NULL ";

if (!$user_info['is_super']) {
    // 계층별 필터링
    if ($user_info['mb_type'] == 'branch') {
        // 지점은 자신의 톡방만
        $room_where .= " AND r.owner_id = '{$user_info['key']}' ";
    } else if ($user_info['mb_type'] == 'agency') {
        // 대리점은 하위 지점의 톡방
        $br_list = [];
        $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '{$user_info['key']}' ";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $br_list[] = "'" . $row['br_id'] . "'";
        }
        
        if (count($br_list) > 0) {
            $room_where .= " AND r.owner_id IN (" . implode(',', $br_list) . ") ";
        } else {
            $room_where .= " AND 1=0 "; // 하위 지점이 없으면 조회 결과 없음
        }
    } else if ($user_info['mb_type'] == 'distributor') {
        // 총판은 하위 대리점의 지점 톡방
        $ag_list = [];
        $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['key']}' ";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $ag_list[] = "'" . $row['ag_id'] . "'";
        }
        
        $br_list = [];
        if (count($ag_list) > 0) {
            $ag_in = implode(',', $ag_list);
            $sql = " SELECT br_id FROM dmk_branch WHERE ag_id IN ($ag_in) ";
            $result = sql_query($sql);
            while($row = sql_fetch_array($result)) {
                $br_list[] = "'" . $row['br_id'] . "'";
            }
        }
        
        if (count($br_list) > 0) {
            $room_where .= " AND r.owner_id IN (" . implode(',', $br_list) . ") ";
        } else {
            $room_where .= " AND 1=0 "; // 하위 지점이 없으면 조회 결과 없음
        }
    }
}

// 지점이 배정된 톡방 목록 조회
$room_list = [];
$room_bot_map = []; // 톡방별 봇 디바이스 맵핑

$sql = " SELECT r.room_id, r.room_name, r.bot_name, r.device_id, r.owner_id, m.mb_name as branch_name
         FROM kb_rooms r
         LEFT JOIN g5_member m ON r.owner_id = m.mb_id
         $room_where
         ORDER BY r.room_name, r.room_id, r.bot_name ";
$result_rooms = sql_query($sql);
while($row = sql_fetch_array($result_rooms)) {
    // 고유 키 생성 (room_id + bot_name + device_id)
    $unique_key = $row['room_id'] . '|' . $row['bot_name'] . '|' . $row['device_id'];
    
    $room_list[] = array_merge($row, ['unique_key' => $unique_key]);
    
    // 봇 디바이스 맵핑 - 이미 room에 device_id가 있음
    $room_bot_map[$unique_key] = [[
        'bot_name' => $row['bot_name'],
        'device_id' => $row['device_id'],
        'display_name' => $row['bot_name'] . ' (' . substr($row['device_id'], 0, 8) . '...)'
    ]];
}
?>

<form name="fschedule" id="fschedule" action="./bot_schedule_form_update.php" onsubmit="return fschedule_submit(this);" method="post" enctype="multipart/form-data" accept-charset="UTF-8">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="id" value="<?php echo $id ?>">
<input type="hidden" name="token" value="<?php echo get_admin_token() ?>">

<section id="anc_basic">
<h2 class="h2_frm">기본 정보</h2>

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption>스케줄 기본 정보</caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="target_room_id">대상 톡방<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="target_room_key" id="target_room_key" required class="required frm_input" onchange="updateBotDeviceList()">
                <option value="">톡방을 선택하세요</option>
                <?php foreach($room_list as $room): ?>
                <option value="<?php echo $room['unique_key']?>" 
                        data-bot="<?php echo $room['bot_name']?>"
                        data-device="<?php echo $room['device_id']?>"
                        data-room-id="<?php echo $room['room_id']?>"
                        <?php echo ($schedule['target_room_id']==$room['room_id'] && $schedule['target_bot_name']==$room['bot_name'] && $schedule['target_device_id']==$room['device_id'])?'selected':'';?>>
                    <?php echo $room['room_name']?> - <?php echo $room['bot_name']?> (<?php echo substr($room['device_id'], 0, 8)?>...) - <?php echo $room['branch_name']?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if(count($room_list) == 0): ?>
            <div class="frm_info fc_red">지점이 배정된 승인된 톡방이 없습니다.</div>
            <?php else: ?>
            <div class="frm_info">각 톡방은 지정된 봇과 디바이스로 메시지를 발송합니다.</div>
            <?php endif; ?>
            <div id="selected_bot_info" style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px; display: none;">
                <strong>선택된 봇:</strong> <span id="bot_info_text"></span>
            </div>
        </td>
    </tr>
    <!-- 대상 정보는 톡방 선택 시 자동으로 결정됨 -->
    <input type="hidden" name="target_room_id" id="target_room_id" value="<?php echo $schedule['target_room_id']?>">
    <input type="hidden" name="target_bot_name" id="target_bot_name" value="<?php echo $schedule['target_bot_name']?>">
    <input type="hidden" name="target_device_id" id="target_device_id" value="<?php echo $schedule['target_device_id']?>">
    
    <script>
    // 톡방별 봇 디바이스 맵핑 데이터
    var roomBotMap = <?php echo json_encode($room_bot_map); ?>;
    </script>
    <tr>
        <th scope="row"><label for="title">제목<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="title" value="<?php echo get_text($schedule['title'])?>" id="title" required class="required frm_input" size="50" maxlength="255"></td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td><textarea name="description" id="description" class="frm_input" rows="3" cols="50"><?php echo get_text($schedule['description'])?></textarea></td>
    </tr>
    
    </tbody>
    </table>
</div>
</section>

<section id="anc_message">
<h2 class="h2_frm">메시지 내용</h2>

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption>메시지 내용 설정</caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="message_text">텍스트 메시지</label></th>
        <td>
            <textarea name="message_text" id="message_text" class="frm_input" rows="10" cols="70" style="font-family: 'Noto Sans KR', sans-serif;"><?php echo get_text($schedule['message_text'])?></textarea>
            <div class="frm_info">발송할 텍스트 메시지를 입력하세요. 이모티콘 사용 가능합니다. (선택사항)</div>
        </td>
    </tr>
    <tr>
        <th scope="row">이미지 그룹 1</th>
        <td>
            <div class="image_upload_area" id="image_group_1">
                <div class="dropzone" id="dropzone1">
                    <p>이미지를 드래그앤드롭하거나 클릭하여 업로드하세요</p>
                    <input type="file" id="file_input_1" multiple accept="image/*" style="display:none;">
                </div>
                <div class="image_preview sortable" id="preview_1">
                    <?php if (!empty($schedule['message_images_1'])): ?>
                        <?php foreach($schedule['message_images_1'] as $idx => $img): ?>
                        <div class="preview_item" data-file="<?php echo $img['file']?>">
                            <img src="<?php echo G5_DATA_URL?>/schedule/<?php echo $img['file']?>" alt="">
                            <button type="button" class="btn_delete" onclick="removeImage(this, 1)">×</button>
                            <input type="hidden" name="existing_images_1[]" value="<?php echo $img['file']?>">
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="frm_info">
                첫 번째 이미지 그룹 (최대 30개, 개별 최대 10MB<?php if (extension_loaded('gd')): ?>, 가로 900px 이상은 자동 리사이징<?php endif; ?>)
                <?php if (!extension_loaded('gd')): ?>
                <br><span class="fc_red">※ PHP GD 라이브러리가 설치되어 있지 않아 이미지 리사이징이 작동하지 않습니다.</span>
                <?php endif; ?>
                <br><small>현재 PHP 설정: upload_max_filesize=<?php echo ini_get('upload_max_filesize')?>, post_max_size=<?php echo ini_get('post_max_size')?></small>
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row">이미지 그룹 2</th>
        <td>
            <div class="image_upload_area" id="image_group_2">
                <div class="dropzone" id="dropzone2">
                    <p>이미지를 드래그앤드롭하거나 클릭하여 업로드하세요</p>
                    <input type="file" id="file_input_2" multiple accept="image/*" style="display:none;">
                </div>
                <div class="image_preview sortable" id="preview_2">
                    <?php if (!empty($schedule['message_images_2'])): ?>
                        <?php foreach($schedule['message_images_2'] as $idx => $img): ?>
                        <div class="preview_item" data-file="<?php echo $img['file']?>">
                            <img src="<?php echo G5_DATA_URL?>/schedule/<?php echo $img['file']?>" alt="">
                            <button type="button" class="btn_delete" onclick="removeImage(this, 2)">×</button>
                            <input type="hidden" name="existing_images_2[]" value="<?php echo $img['file']?>">
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="frm_info">
                두 번째 이미지 그룹 (최대 30개, 개별 최대 10MB<?php if (extension_loaded('gd')): ?>, 가로 900px 이상은 자동 리사이징<?php endif; ?>)
                <?php if (!extension_loaded('gd')): ?>
                <br><span class="fc_red">※ PHP GD 라이브러리가 설치되어 있지 않아 이미지 리사이징이 작동하지 않습니다.</span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="send_interval_seconds">메시지간 발송 간격</label></th>
        <td>
            <input type="number" name="send_interval_seconds" value="<?php echo $schedule['send_interval_seconds'] ?? 1?>" id="send_interval_seconds" class="frm_input" min="0" max="60"> 초
            <div class="frm_info">텍스트와 이미지 그룹 사이의 발송 간격 (0~60초)</div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="media_wait_time_1">이미지 그룹 1 대기시간</label></th>
        <td>
            <input type="number" name="media_wait_time_1" value="<?php echo $schedule['media_wait_time_1'] ?? 0?>" id="media_wait_time_1" class="frm_input" min="0" max="30000"> ms
            <div class="frm_info">이미지 그룹 1 발송 후 대기시간 (0은 클라이언트 설정 사용)</div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="media_wait_time_2">이미지 그룹 2 대기시간</label></th>
        <td>
            <input type="number" name="media_wait_time_2" value="<?php echo $schedule['media_wait_time_2'] ?? 0?>" id="media_wait_time_2" class="frm_input" min="0" max="30000"> ms
            <div class="frm_info">이미지 그룹 2 발송 후 대기시간 (0은 클라이언트 설정 사용)</div>
        </td>
    </tr>
    </tbody>
    </table>
</div>
</section>

<section id="anc_schedule">
<h2 class="h2_frm">스케줄 설정</h2>

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption>스케줄링 설정</caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row">스케줄 타입<strong class="sound_only">필수</strong></th>
        <td>
            <input type="radio" name="schedule_type" value="once" id="schedule_type_once" <?php echo ($schedule['schedule_type']=='once' || !$schedule['schedule_type'])?'checked':'';?> onclick="toggleScheduleType()">
            <label for="schedule_type_once">1회성</label>
            
            <input type="radio" name="schedule_type" value="daily" id="schedule_type_daily" <?php echo ($schedule['schedule_type']=='daily')?'checked':'';?> onclick="toggleScheduleType()">
            <label for="schedule_type_daily">매일 반복</label>
            
            <input type="radio" name="schedule_type" value="weekly" id="schedule_type_weekly" <?php echo ($schedule['schedule_type']=='weekly')?'checked':'';?> onclick="toggleScheduleType()">
            <label for="schedule_type_weekly">주간 반복</label>
        </td>
    </tr>
    <tr id="tr_schedule_date">
        <th scope="row"><label for="schedule_date">발송 날짜<strong class="sound_only">필수</strong></label></th>
        <td><input type="date" name="schedule_date" value="<?php echo $schedule['schedule_date']?>" id="schedule_date" class="frm_input"></td>
    </tr>
    <tr>
        <th scope="row">발송 시간<strong class="sound_only">필수</strong></th>
        <td>
            <div id="schedule_times_container">
                <?php
                // 기존 데이터 처리 - schedule_times가 있으면 사용, 없으면 schedule_time을 배열로 변환
                $schedule_times = [];
                if (!empty($schedule['schedule_times'])) {
                    $schedule_times = json_decode($schedule['schedule_times'], true);
                } else if (!empty($schedule['schedule_time'])) {
                    $schedule_times = [$schedule['schedule_time']];
                }
                
                if (empty($schedule_times)) {
                    $schedule_times = [''];  // 최소 1개는 표시
                }
                
                foreach ($schedule_times as $idx => $time):
                ?>
                <div class="schedule_time_item">
                    <input type="time" name="schedule_times[]" value="<?php echo $time?>" class="frm_input schedule_time_input" required>
                    <?php if ($idx > 0): ?>
                    <button type="button" class="btn btn_02" onclick="removeScheduleTime(this)">삭제</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn_03" onclick="addScheduleTime()">시간 추가</button>
            <div class="frm_info">발송할 시간을 추가할 수 있습니다. 각 시간마다 메시지가 발송됩니다.</div>
        </td>
    </tr>
    <tr id="tr_schedule_weekdays" style="display:none;">
        <th scope="row">반복 요일<strong class="sound_only">필수</strong></th>
        <td>
            <?php
            $weekdays = $schedule['schedule_weekdays'] ? explode(',', $schedule['schedule_weekdays']) : [];
            ?>
            <input type="checkbox" name="schedule_weekdays[]" value="monday" id="weekday_mon" <?php echo in_array('monday', $weekdays)?'checked':'';?>>
            <label for="weekday_mon">월요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="tuesday" id="weekday_tue" <?php echo in_array('tuesday', $weekdays)?'checked':'';?>>
            <label for="weekday_tue">화요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="wednesday" id="weekday_wed" <?php echo in_array('wednesday', $weekdays)?'checked':'';?>>
            <label for="weekday_wed">수요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="thursday" id="weekday_thu" <?php echo in_array('thursday', $weekdays)?'checked':'';?>>
            <label for="weekday_thu">목요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="friday" id="weekday_fri" <?php echo in_array('friday', $weekdays)?'checked':'';?>>
            <label for="weekday_fri">금요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="saturday" id="weekday_sat" <?php echo in_array('saturday', $weekdays)?'checked':'';?>>
            <label for="weekday_sat">토요일</label>
            
            <input type="checkbox" name="schedule_weekdays[]" value="sunday" id="weekday_sun" <?php echo in_array('sunday', $weekdays)?'checked':'';?>>
            <label for="weekday_sun">일요일</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="valid_from">유효기간 시작<strong class="sound_only">필수</strong></label></th>
        <td><input type="datetime-local" name="valid_from" value="<?php echo $schedule['valid_from'] ? date('Y-m-d\TH:i', strtotime($schedule['valid_from'])) : date('Y-m-d\TH:i')?>" id="valid_from" required class="required frm_input"></td>
    </tr>
    <tr>
        <th scope="row"><label for="valid_until">유효기간 종료<strong class="sound_only">필수</strong></label></th>
        <td><input type="datetime-local" name="valid_until" value="<?php echo $schedule['valid_until'] ? date('Y-m-d\TH:i', strtotime($schedule['valid_until'])) : ''?>" id="valid_until" required class="required frm_input"></td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status" class="frm_input">
                <option value="active" <?php echo ($schedule['status']=='active' || !$schedule['status'])?'selected':'';?>>활성</option>
                <option value="inactive" <?php echo ($schedule['status']=='inactive')?'selected':'';?>>비활성</option>
            </select>
        </td>
    </tr>
    </tbody>
    </table>
</div>
</section>

<div class="btn_fixed_top">
    <a href="./bot_schedule_list.php?<?php echo $qstr ?>" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
</div>

</form>

<style>
.image_upload_area {
    border: 1px solid #ddd;
    padding: 10px;
    margin-bottom: 10px;
}

.dropzone {
    border: 2px dashed #ccc;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.dropzone:hover {
    background: #f5f5f5;
}

.dropzone.drag-over {
    background: #e3f2fd;
    border-color: #2196f3;
}

.image_preview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.preview_item {
    position: relative;
    width: 100px;
    height: 100px;
    border: 1px solid #ddd;
    cursor: move;
}

.preview_item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preview_item .btn_delete {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 20px;
    height: 20px;
    background: #f44336;
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    line-height: 20px;
    padding: 0;
}

.sortable-ghost {
    opacity: 0.5;
}

.schedule_time_item {
    margin-bottom: 5px;
}

.schedule_time_item input {
    margin-right: 10px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// 드래그앤드롭 및 정렬 기능
let uploadedFiles1 = [];
let uploadedFiles2 = [];

document.addEventListener('DOMContentLoaded', function() {
    // 드래그앤드롭 설정
    setupDropzone('dropzone1', 'file_input_1', 'preview_1', 1);
    setupDropzone('dropzone2', 'file_input_2', 'preview_2', 2);
    
    // 정렬 기능 설정
    new Sortable(document.getElementById('preview_1'), {
        animation: 150,
        ghostClass: 'sortable-ghost'
    });
    
    new Sortable(document.getElementById('preview_2'), {
        animation: 150,
        ghostClass: 'sortable-ghost'
    });
    
    // 초기 스케줄 타입 표시
    toggleScheduleType();
});

function setupDropzone(dropzoneId, fileInputId, previewId, groupNum) {
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(fileInputId);
    const preview = document.getElementById(previewId);
    
    // 클릭으로 파일 선택
    dropzone.addEventListener('click', () => fileInput.click());
    
    // 드래그 이벤트
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });
    
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('drag-over');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files, groupNum);
    });
    
    // 파일 선택
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files, groupNum);
    });
}

function handleFiles(files, groupNum) {
    const preview = document.getElementById('preview_' + groupNum);
    const maxFiles = 30;
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    // 현재 업로드된 파일 수 확인
    const currentCount = preview.querySelectorAll('.preview_item').length;
    
    for (let i = 0; i < files.length; i++) {
        if (currentCount + i >= maxFiles) {
            alert('최대 ' + maxFiles + '개까지 업로드 가능합니다.');
            break;
        }
        
        const file = files[i];
        
        // 파일 타입 체크
        if (!file.type.startsWith('image/')) {
            alert('이미지 파일만 업로드 가능합니다.');
            continue;
        }
        
        // 파일 크기 체크
        if (file.size > maxSize) {
            alert('파일 크기는 10MB를 초과할 수 없습니다.');
            continue;
        }
        
        // 이미지 리사이징 및 미리보기 생성
        processImage(file, groupNum, preview);
    }
}

function processImage(file, groupNum, preview) {
    const maxWidth = 900;
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            let width = img.width;
            let height = img.height;
            let needResize = false;
            
            // 가로 사이즈가 900px를 초과하는 경우 리사이징 필요
            if (width > maxWidth) {
                needResize = true;
                height = (height / width) * maxWidth;
                width = maxWidth;
            }
            
            if (needResize) {
                // Canvas를 사용하여 리사이징
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = width;
                canvas.height = height;
                
                // 이미지를 캔버스에 그리기
                ctx.drawImage(img, 0, 0, width, height);
                
                // Canvas를 Blob으로 변환
                canvas.toBlob(function(blob) {
                    // 새로운 File 객체 생성
                    const resizedFile = new File([blob], file.name, {
                        type: file.type,
                        lastModified: Date.now()
                    });
                    
                    // 리사이징된 이미지로 미리보기 생성
                    createPreviewItem(resizedFile, groupNum, preview, canvas.toDataURL());
                }, file.type, 0.9); // JPEG 품질 90%
            } else {
                // 리사이징이 필요없는 경우 원본 사용
                createPreviewItem(file, groupNum, preview, e.target.result);
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function createPreviewItem(file, groupNum, preview, dataUrl) {
    const previewItem = document.createElement('div');
    previewItem.className = 'preview_item';
    previewItem.innerHTML = `
        <img src="${dataUrl}" alt="">
        <button type="button" class="btn_delete" onclick="removeImage(this, ${groupNum})">×</button>
        <input type="file" name="new_images_${groupNum}[]" style="display:none;">
    `;
    
    // File 객체를 숨겨진 input에 저장
    const hiddenInput = previewItem.querySelector('input[type="file"]');
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    hiddenInput.files = dataTransfer.files;
    
    preview.appendChild(previewItem);
}

function removeImage(btn, groupNum) {
    btn.parentElement.remove();
}

function updateBotDeviceList() {
    const roomSelect = document.getElementById('target_room_key');
    const roomIdInput = document.getElementById('target_room_id');
    const deviceIdInput = document.getElementById('target_device_id');
    const botNameInput = document.getElementById('target_bot_name');
    const botInfoDiv = document.getElementById('selected_bot_info');
    const botInfoText = document.getElementById('bot_info_text');
    const selectedOption = roomSelect.options[roomSelect.selectedIndex];
    
    if (!roomSelect.value) {
        roomIdInput.value = '';
        deviceIdInput.value = '';
        botNameInput.value = '';
        botInfoDiv.style.display = 'none';
        return;
    }
    
    // 선택된 옵션에서 데이터 추출
    const roomId = selectedOption.getAttribute('data-room-id');
    const botName = selectedOption.getAttribute('data-bot');
    const deviceId = selectedOption.getAttribute('data-device');
    
    // hidden 필드에 값 설정
    roomIdInput.value = roomId;
    botNameInput.value = botName;
    deviceIdInput.value = deviceId;
    
    // 봇 정보 표시
    botInfoDiv.style.display = 'block';
    botInfoText.textContent = botName + ' (' + deviceId.substr(0, 8) + '...)';
    botInfoText.style.color = '#4CAF50';
}

function toggleScheduleType() {
    const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
    const dateRow = document.getElementById('tr_schedule_date');
    const weekdaysRow = document.getElementById('tr_schedule_weekdays');
    const dateInput = document.getElementById('schedule_date');
    
    if (scheduleType === 'once') {
        dateRow.style.display = '';
        weekdaysRow.style.display = 'none';
        dateInput.required = true;
    } else if (scheduleType === 'daily') {
        dateRow.style.display = 'none';
        weekdaysRow.style.display = 'none';
        dateInput.required = false;
    } else if (scheduleType === 'weekly') {
        dateRow.style.display = 'none';
        weekdaysRow.style.display = '';
        dateInput.required = false;
    }
}

function addScheduleTime() {
    const container = document.getElementById('schedule_times_container');
    const div = document.createElement('div');
    div.className = 'schedule_time_item';
    div.innerHTML = `
        <input type="time" name="schedule_times[]" value="" class="frm_input schedule_time_input" required>
        <button type="button" class="btn btn_02" onclick="removeScheduleTime(this)">삭제</button>
    `;
    container.appendChild(div);
}

function removeScheduleTime(btn) {
    const container = document.getElementById('schedule_times_container');
    const items = container.querySelectorAll('.schedule_time_item');
    
    // 최소 1개는 남겨둠
    if (items.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('최소 1개의 발송 시간은 필요합니다.');
    }
}

function fschedule_submit(f) {
    // UTF-8 인코딩 보장
    f.acceptCharset = "UTF-8";
    
    // 기본 유효성 검사
    if (!f.title.value) {
        alert("제목을 입력해주세요.");
        f.title.focus();
        return false;
    }
    
    if (!f.target_room_key.value) {
        alert("대상 톡방을 선택해주세요.");
        f.target_room_key.focus();
        return false;
    }
    
    // target_room_id, target_device_id와 target_bot_name은 톡방 선택 시 자동 설정됨
    if (!f.target_room_id.value || !f.target_device_id.value || !f.target_bot_name.value) {
        alert("선택한 톡방에 연결된 봇 정보를 찾을 수 없습니다.");
        f.target_room_key.focus();
        return false;
    }
    
    // 메시지 내용 확인
    const hasText = f.message_text.value.trim() !== '';
    const hasImages1 = document.querySelectorAll('#preview_1 .preview_item').length > 0;
    const hasImages2 = document.querySelectorAll('#preview_2 .preview_item').length > 0;
    
    if (!hasText && !hasImages1 && !hasImages2) {
        alert("텍스트 메시지 또는 이미지를 하나 이상 입력해주세요.");
        return false;
    }
    
    // 스케줄 타입별 검증
    const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
    
    if (scheduleType === 'once' && !f.schedule_date.value) {
        alert("발송 날짜를 선택해주세요.");
        f.schedule_date.focus();
        return false;
    }
    
    if (scheduleType === 'weekly') {
        const weekdays = document.querySelectorAll('input[name="schedule_weekdays[]"]:checked');
        if (weekdays.length === 0) {
            alert("반복할 요일을 하나 이상 선택해주세요.");
            return false;
        }
    }
    
    // 발송 시간 검증
    const scheduleTimes = document.querySelectorAll('input[name="schedule_times[]"]');
    let hasEmptyTime = false;
    let hasValidTime = false;
    
    scheduleTimes.forEach(function(input) {
        if (input.value.trim() === '') {
            hasEmptyTime = true;
        } else {
            hasValidTime = true;
        }
    });
    
    if (hasEmptyTime) {
        alert("모든 발송 시간을 입력해주세요.");
        return false;
    }
    
    if (!hasValidTime) {
        alert("최소 1개의 발송 시간을 입력해주세요.");
        return false;
    }
    
    // 유효기간 검증
    if (!f.valid_from.value) {
        alert("유효기간 시작일을 입력해주세요.");
        f.valid_from.focus();
        return false;
    }
    
    if (!f.valid_until.value) {
        alert("유효기간 종료일을 입력해주세요.");
        f.valid_until.focus();
        return false;
    }
    
    const validFrom = new Date(f.valid_from.value);
    const validUntil = new Date(f.valid_until.value);
    
    if (validFrom >= validUntil) {
        alert("유효기간 종료일은 시작일보다 이후여야 합니다.");
        f.valid_until.focus();
        return false;
    }
    
    return true;
}

// 초기화 - 페이지 로드 시 봇 정보 표시
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($w == 'u' && $schedule['target_room_id']): ?>
    // 수정 모드 - 선택된 톡방의 봇 정보 표시
    setTimeout(function() {
        updateBotDeviceList();
    }, 100);
    <?php endif; ?>
});
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>