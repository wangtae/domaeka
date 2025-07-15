<?php
/**
 * 채팅방 수정
 * 상태와 설명만 수정 가능
 */

$sub_menu = "180500";
include_once('./_common.php');

// chain-select 라이브러리 포함
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

auth_check('180500', 'w');

$w = $_GET['w'];
$room_id = $_GET['room_id'];

if (!$room_id) {
    alert('방 ID가 없습니다.');
}

$sql = " SELECT * FROM kb_rooms WHERE room_id = '$room_id' ";
$room = sql_fetch($sql);
if (!$room['room_id']) {
    alert('등록된 채팅방이 아닙니다.');
}

$g5['title'] = '채팅방 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 방장 정보 파싱
$owners_info = '';
if($room['room_owners']) {
    $owners = json_decode($room['room_owners'], true);
    if($owners && is_array($owners)) {
        $owners_info = implode(', ', $owners);
    }
}

// 로그 설정 파싱
$log_settings = [];
if($room['log_settings']) {
    $log_settings = json_decode($room['log_settings'], true);
}
?>

<form name="froom" id="froom" action="./room_form_update.php" onsubmit="return froom_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="room_id" value="<?php echo $room_id ?>">
<input type="hidden" name="token" value="<?php echo get_admin_token() ?>">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row">방 ID</th>
        <td><?php echo substr($room['room_id'], 0, 10) . '...'?></td>
    </tr>
    <tr>
        <th scope="row">채팅방명</th>
        <td><?php echo get_text($room['room_name'])?></td>
    </tr>
    <tr>
        <th scope="row">봇명</th>
        <td><?php echo get_text($room['bot_name'])?></td>
    </tr>
    <tr>
        <th scope="row">동시실행수</th>
        <td><?php echo $room['room_concurrency']?></td>
    </tr>
    <tr>
        <th scope="row">방장 정보</th>
        <td><?php echo $owners_info ? get_text($owners_info) : '정보 없음'?></td>
    </tr>
    <tr>
        <th scope="row">등록일시</th>
        <td><?php echo $room['created_at']?></td>
    </tr>
    <tr>
        <th scope="row">최종 업데이트</th>
        <td><?php echo $room['updated_at'] ? $room['updated_at'] : '수정 이력 없음'?></td>
    </tr>
    <?php
    // 관리자 권한 체크 - 본사와 총판만 지점 배정 가능
    $user_info = dmk_get_admin_auth();
    
    if ($user_info['is_super'] || $user_info['mb_type'] == 'distributor'):
    ?>
    <tr>
        <th scope="row"><label for="owner_type">배정 지점<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php
            // 현재 선택된 값 설정
            $current_values = [];
            if ($room['owner_type'] == 'distributor') {
                $current_values['dt_id'] = $room['owner_id'];
            } else if ($room['owner_type'] == 'agency') {
                // 대리점이 선택된 경우 소속 총판 찾기
                $sql = " SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($room['owner_id'])."' ";
                $ag_info = sql_fetch($sql);
                $current_values['dt_id'] = $ag_info['dt_id'];
                $current_values['ag_id'] = $room['owner_id'];
            } else if ($room['owner_type'] == 'branch') {
                // 지점이 선택된 경우 소속 대리점과 총판 찾기
                $sql = " SELECT b.ag_id, a.dt_id 
                         FROM dmk_branch b 
                         JOIN dmk_agency a ON b.ag_id = a.ag_id 
                         WHERE b.br_id = '".sql_escape_string($room['owner_id'])."' ";
                $br_info = sql_fetch($sql);
                $current_values['dt_id'] = $br_info['dt_id'];
                $current_values['ag_id'] = $br_info['ag_id'];
                $current_values['br_id'] = $room['owner_id'];
            }
            
            // chain-select 렌더링
            echo dmk_render_chain_select([
                'page_type' => DMK_CHAIN_SELECT_FULL,
                'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                'current_values' => $current_values,
                'field_names' => [
                    'distributor' => 'dt_id',
                    'agency' => 'ag_id',
                    'branch' => 'br_id'
                ],
                'placeholders' => [
                    'distributor' => '총판 선택',
                    'agency' => '대리점 선택',
                    'branch' => '지점 선택'
                ],
                'auto_submit' => false,
                'debug' => false
            ]);
            ?>
            
            <div class="frm_info">
                채팅방을 관리할 지점을 선택하세요. 지점을 선택하지 않으면 상위 레벨(대리점/총판)이 관리합니다.
            </div>
        </td>
    </tr>
    <?php endif; ?>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="status" id="status" required class="frm_input">
                <option value="pending" <?php echo ($room['status']=='pending')?'selected':'';?>>승인 대기</option>
                <option value="approved" <?php echo ($room['status']=='approved')?'selected':'';?>>승인됨</option>
                <option value="denied" <?php echo ($room['status']=='denied')?'selected':'';?>>거부됨</option>
                <option value="revoked" <?php echo ($room['status']=='revoked')?'selected':'';?>>취소됨</option>
                <option value="blocked" <?php echo ($room['status']=='blocked')?'selected':'';?>>차단됨</option>
            </select>
            <div class="frm_info">
                채팅방의 승인 상태를 변경할 수 있습니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명/메모</label></th>
        <td>
            <textarea name="description" id="description" class="frm_input" rows="5" cols="50" placeholder="채팅방에 대한 설명이나 메모를 입력하세요"><?php echo get_text($room['descryption'])?></textarea>
            <div class="frm_info">
                관리자용 메모입니다. 채팅방 관리에 필요한 정보를 기록하세요.
            </div>
        </td>
    </tr>
    <?php if($room['status'] == 'denied' || $room['status'] == 'blocked'): ?>
    <tr>
        <th scope="row"><label for="rejection_reason">거부/차단 사유</label></th>
        <td>
            <textarea name="rejection_reason" id="rejection_reason" class="frm_input" rows="3" cols="50" placeholder="거부 또는 차단 사유를 입력하세요"><?php echo isset($room['rejection_reason']) ? get_text($room['rejection_reason']) : ''?></textarea>
        </td>
    </tr>
    <?php endif; ?>
    
    <tr>
        <th scope="row" colspan="2" style="background-color: #f8f9fa; text-align: center; font-weight: bold;">로그 설정</th>
    </tr>
    <tr>
        <th scope="row"><label for="log_enabled">로그 활성화</label></th>
        <td>
            <input type="checkbox" name="log_enabled" id="log_enabled" value="1" <?php echo (isset($log_settings['enabled']) && $log_settings['enabled']) ? 'checked' : '';?>>
            <label for="log_enabled">채팅 로그를 수집합니다</label>
            <div class="frm_info">
                체크하면 이 채팅방의 메시지가 데이터베이스에 저장됩니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="log_retention_days">로그 보관일수</label></th>
        <td>
            <input type="number" name="log_retention_days" id="log_retention_days" class="frm_input" min="1" max="365" value="<?php echo $log_settings['retention_days'] ?? 30?>">
            <span>일</span>
            <div class="frm_info">
                설정한 일수가 지난 로그는 자동으로 삭제됩니다. (기본값: 30일)
            </div>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./room_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
    <?php if($room['status'] != 'blocked'): ?>
    <a href="./room_block.php?room_id=<?php echo urlencode($room_id)?>" class="btn btn_01" onclick="return confirm('이 채팅방을 차단하시겠습니까?')">차단</a>
    <?php endif; ?>
</div>

</form>

<script>
function froom_submit(f)
{
    if (!f.status.value) {
        alert("상태를 선택해주세요.");
        f.status.focus();
        return false;
    }
    
    // 거부나 차단으로 변경하는 경우 확인
    if ((f.status.value == 'denied' || f.status.value == 'blocked') && 
        ('<?php echo $room['status']?>' != 'denied' && '<?php echo $room['status']?>' != 'blocked')) {
        if (!confirm('채팅방을 ' + (f.status.value == 'denied' ? '거부' : '차단') + ' 상태로 변경하시겠습니까?')) {
            return false;
        }
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>