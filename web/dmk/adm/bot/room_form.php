<?php
/**
<<<<<<< HEAD
 * 채팅방 수정 (상태, 설명만)
 */

$sub_menu = "180500";
=======
 * 채팅방 수정
 * 상태와 설명만 수정 가능
 */

>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
include_once('./_common.php');

auth_check('180500', 'w');

$w = $_GET['w'];
$room_id = $_GET['room_id'];

<<<<<<< HEAD
if ($w == 'u' && $room_id) {
    $sql = " SELECT * FROM kb_rooms WHERE room_id = '".sql_real_escape_string($room_id)."' ";
    $rm = sql_fetch($sql);
    if (!$rm['room_id']) {
        alert('등록된 채팅방이 아닙니다.');
    }
} else {
    alert('잘못된 접근입니다.');
=======
if (!$room_id) {
    alert('방 ID가 없습니다.');
}

$sql = " SELECT * FROM kb_rooms WHERE room_id = '$room_id' ";
$room = sql_fetch($sql);
if (!$room['room_id']) {
    alert('등록된 채팅방이 아닙니다.');
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
}

$g5['title'] = '채팅방 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');
<<<<<<< HEAD
=======

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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
?>

<form name="froom" id="froom" action="./room_form_update.php" onsubmit="return froom_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w ?>">
<<<<<<< HEAD
<input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id) ?>">
=======
<input type="hidden" name="room_id" value="<?php echo $room_id ?>">
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
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
<<<<<<< HEAD
        <td>
            <input type="text" value="<?php echo htmlspecialchars($rm['room_id']) ?>" class="frm_input" readonly>
            <span class="frm_info">방 ID는 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">채팅방명</th>
        <td>
            <input type="text" value="<?php echo get_text($rm['room_name']) ?>" class="frm_input" readonly>
            <span class="frm_info">채팅방명은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">봇명</th>
        <td>
            <input type="text" value="<?php echo get_text($rm['bot_name']) ?>" class="frm_input" readonly>
            <span class="frm_info">봇명은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">방장 정보</th>
        <td>
            <?php if($rm['owner_info']): ?>
                <?php 
                $owner_info = json_decode($rm['owner_info'], true);
                if($owner_info && isset($owner_info['name'])): 
                ?>
                    <input type="text" value="<?php echo get_text($owner_info['name']) ?>" class="frm_input" readonly>
                <?php else: ?>
                    <input type="text" value="정보 없음" class="frm_input" readonly>
                <?php endif; ?>
            <?php else: ?>
                <input type="text" value="정보 없음" class="frm_input" readonly>
            <?php endif; ?>
            <span class="frm_info">방장 정보는 자동으로 관리됩니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">동시실행수</th>
        <td>
            <input type="text" value="<?php echo $rm['concurrent_limit'] ? $rm['concurrent_limit'] : '제한 없음' ?>" class="frm_input" readonly>
            <span class="frm_info">동시실행수는 시스템에서 관리됩니다.</span>
        </td>
=======
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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    </tr>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
<<<<<<< HEAD
            <select name="status" id="status" required class="frm_input required">
                <option value="pending" <?php echo ($rm['status']=='pending')?'selected':'';?>>승인 대기</option>
                <option value="approved" <?php echo ($rm['status']=='approved')?'selected':'';?>>승인됨</option>
                <option value="denied" <?php echo ($rm['status']=='denied')?'selected':'';?>>거부됨</option>
                <option value="revoked" <?php echo ($rm['status']=='revoked')?'selected':'';?>>취소됨</option>
                <option value="blocked" <?php echo ($rm['status']=='blocked')?'selected':'';?>>차단됨</option>
            </select>
            <span class="frm_info">채팅방의 봇 승인 상태를 설정하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td>
            <textarea name="description" id="description" rows="4" class="frm_input" style="width:100%;"><?php echo get_text($rm['description']) ?></textarea>
            <span class="frm_info">이 채팅방에 대한 관리용 설명을 입력하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">등록일</th>
        <td>
            <input type="text" value="<?php echo $rm['created_at'] ?>" class="frm_input" readonly>
        </td>
    </tr>
    <tr>
        <th scope="row">마지막 업데이트</th>
        <td>
            <input type="text" value="<?php echo $rm['updated_at'] ?>" class="frm_input" readonly>
=======
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
            <textarea name="description" id="description" class="frm_input" rows="5" cols="50" placeholder="채팅방에 대한 설명이나 메모를 입력하세요"><?php echo get_text($room['description'])?></textarea>
            <div class="frm_info">
                관리자용 메모입니다. 채팅방 관리에 필요한 정보를 기록하세요.
            </div>
        </td>
    </tr>
    <?php if($room['status'] == 'denied' || $room['status'] == 'blocked'): ?>
    <tr>
        <th scope="row"><label for="rejection_reason">거부/차단 사유</label></th>
        <td>
            <textarea name="rejection_reason" id="rejection_reason" class="frm_input" rows="3" cols="50" placeholder="거부 또는 차단 사유를 입력하세요"><?php echo get_text($room['rejection_reason'])?></textarea>
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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
<<<<<<< HEAD
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./room_list.php" class="btn btn_02">목록</a>
</div>
=======
    <a href="./room_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
    <?php if($room['status'] != 'blocked'): ?>
    <a href="./room_block.php?room_id=<?php echo urlencode($room_id)?>" class="btn btn_01" onclick="return confirm('이 채팅방을 차단하시겠습니까?')">차단</a>
    <?php endif; ?>
</div>

>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
</form>

<script>
function froom_submit(f)
{
    if (!f.status.value) {
<<<<<<< HEAD
        alert("상태를 선택하세요.");
=======
        alert("상태를 선택해주세요.");
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        f.status.focus();
        return false;
    }
    
<<<<<<< HEAD
=======
    // 거부나 차단으로 변경하는 경우 확인
    if ((f.status.value == 'denied' || f.status.value == 'blocked') && 
        ('<?php echo $room['status']?>' != 'denied' && '<?php echo $room['status']?>' != 'blocked')) {
        if (!confirm('채팅방을 ' + (f.status.value == 'denied' ? '거부' : '차단') + ' 상태로 변경하시겠습니까?')) {
            return false;
        }
    }
    
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>