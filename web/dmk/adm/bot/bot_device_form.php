<?php
/**
 * 클라이언트 봇 수정
 * 상태와 설명만 수정 가능
 */

include_once('./_common.php');

auth_check('180300', 'w');

$w = $_GET['w'];
$device_id = $_GET['device_id'];

if (!$device_id) {
    alert('디바이스 ID가 없습니다.');
}

$sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
$device = sql_fetch($sql);
if (!$device['id']) {
    alert('등록된 디바이스가 아닙니다.');
}

$g5['title'] = '클라이언트 봇 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<form name="fdevice" id="fdevice" action="./bot_device_form_update.php" onsubmit="return fdevice_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="device_id" value="<?php echo $device_id ?>">
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
        <th scope="row">봇 이름</th>
        <td><?php echo get_text($device['bot_name'])?></td>
    </tr>
    <tr>
        <th scope="row">디바이스 ID</th>
        <td><?php echo substr($device['device_id'], 0, 8) . '...'?></td>
    </tr>
    <tr>
        <th scope="row">IP 주소</th>
        <td><?php echo $device['ip_address']?></td>
    </tr>
    <tr>
        <th scope="row">클라이언트 정보</th>
        <td><?php echo $device['client_type'] . ' ' . $device['client_version']?></td>
    </tr>
    <tr>
        <th scope="row">등록일시</th>
        <td><?php echo $device['created_at']?></td>
    </tr>
    <tr>
        <th scope="row">최종 접속</th>
        <td><?php echo $device['last_connected_at'] ? $device['last_connected_at'] : '접속 이력 없음'?></td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="status" id="status" required class="frm_input">
                <option value="pending" <?php echo ($device['status']=='pending')?'selected':'';?>>승인 대기</option>
                <option value="approved" <?php echo ($device['status']=='approved')?'selected':'';?>>승인됨</option>
                <option value="rejected" <?php echo ($device['status']=='rejected')?'selected':'';?>>거부됨</option>
                <option value="suspended" <?php echo ($device['status']=='suspended')?'selected':'';?>>일시정지</option>
                <option value="blocked" <?php echo ($device['status']=='blocked')?'selected':'';?>>차단됨</option>
            </select>
            <div class="frm_info">
                디바이스의 승인 상태를 변경할 수 있습니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명/메모</label></th>
        <td>
            <textarea name="description" id="description" class="frm_input" rows="5" cols="50" placeholder="디바이스에 대한 설명이나 메모를 입력하세요"><?php echo get_text($device['description'])?></textarea>
            <div class="frm_info">
                관리자용 메모입니다. 디바이스 관리에 필요한 정보를 기록하세요.
            </div>
        </td>
    </tr>
    <?php if($device['status'] == 'rejected' || $device['status'] == 'blocked'): ?>
    <tr>
        <th scope="row"><label for="rejection_reason">거부/차단 사유</label></th>
        <td>
            <textarea name="rejection_reason" id="rejection_reason" class="frm_input" rows="3" cols="50" placeholder="거부 또는 차단 사유를 입력하세요"><?php echo get_text($device['rejection_reason'])?></textarea>
        </td>
    </tr>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./bot_device_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
    <?php if($device['status'] != 'blocked'): ?>
    <a href="./bot_device_block.php?device_id=<?php echo $device_id?>" class="btn btn_01" onclick="return confirm('이 디바이스를 차단하시겠습니까?')">차단</a>
    <?php endif; ?>
</div>

</form>

<script>
function fdevice_submit(f)
{
    if (!f.status.value) {
        alert("상태를 선택해주세요.");
        f.status.focus();
        return false;
    }
    
    // 거부나 차단으로 변경하는 경우 확인
    if ((f.status.value == 'rejected' || f.status.value == 'blocked') && 
        ('<?php echo $device['status']?>' != 'rejected' && '<?php echo $device['status']?>' != 'blocked')) {
        if (!confirm('디바이스를 ' + (f.status.value == 'rejected' ? '거부' : '차단') + ' 상태로 변경하시겠습니까?')) {
            return false;
        }
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>