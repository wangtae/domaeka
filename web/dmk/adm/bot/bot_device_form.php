<?php
/**
<<<<<<< HEAD
 * 클라이언트 봇 수정 (상태, 설명만)
 */

$sub_menu = "180300";
=======
 * 클라이언트 봇 수정
 * 상태와 설명만 수정 가능
 */

>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
include_once('./_common.php');

auth_check('180300', 'w');

$w = $_GET['w'];
$device_id = $_GET['device_id'];

<<<<<<< HEAD
if ($w == 'u' && $device_id) {
    $sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
    $bd = sql_fetch($sql);
    if (!$bd['id']) {
        alert('등록된 디바이스가 아닙니다.');
    }
} else {
    alert('잘못된 접근입니다.');
=======
if (!$device_id) {
    alert('디바이스 ID가 없습니다.');
}

$sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
$device = sql_fetch($sql);
if (!$device['id']) {
    alert('등록된 디바이스가 아닙니다.');
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
}

$g5['title'] = '클라이언트 봇 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<<<<<<< HEAD
<form name="fbotdevice" id="fbotdevice" action="./bot_device_form_update.php" onsubmit="return fbotdevice_submit(this);" method="post">
=======
<form name="fdevice" id="fdevice" action="./bot_device_form_update.php" onsubmit="return fdevice_submit(this);" method="post">
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
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
<<<<<<< HEAD
        <th scope="row">봇명</th>
        <td>
            <input type="text" value="<?php echo get_text($bd['bot_name']) ?>" class="frm_input" readonly>
            <span class="frm_info">봇명은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">디바이스 ID</th>
        <td>
            <input type="text" value="<?php echo get_text($bd['device_id']) ?>" class="frm_input" readonly>
            <span class="frm_info">디바이스 ID는 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">IP 주소</th>
        <td>
            <input type="text" value="<?php echo $bd['ip_address'] ?>" class="frm_input" readonly>
            <span class="frm_info">IP 주소는 자동으로 관리됩니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">클라이언트</th>
        <td>
            <input type="text" value="<?php echo $bd['client_type'] . ($bd['client_version'] ? ' v'.$bd['client_version'] : '') ?>" class="frm_input" readonly>
            <span class="frm_info">클라이언트 정보는 자동으로 감지됩니다.</span>
        </td>
=======
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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    </tr>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
<<<<<<< HEAD
            <select name="status" id="status" required class="frm_input required">
                <option value="pending" <?php echo ($bd['status']=='pending')?'selected':'';?>>승인 대기</option>
                <option value="approved" <?php echo ($bd['status']=='approved')?'selected':'';?>>승인됨</option>
                <option value="denied" <?php echo ($bd['status']=='denied')?'selected':'';?>>거부됨</option>
                <option value="revoked" <?php echo ($bd['status']=='revoked')?'selected':'';?>>취소됨</option>
                <option value="blocked" <?php echo ($bd['status']=='blocked')?'selected':'';?>>차단됨</option>
            </select>
            <span class="frm_info">봇 디바이스의 승인 상태를 설정하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td>
            <textarea name="description" id="description" rows="4" class="frm_input" style="width:100%;"><?php echo get_text($bd['description']) ?></textarea>
            <span class="frm_info">이 봇 디바이스에 대한 관리용 설명을 입력하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">등록일</th>
        <td>
            <input type="text" value="<?php echo $bd['created_at'] ?>" class="frm_input" readonly>
        </td>
    </tr>
    <tr>
        <th scope="row">마지막 업데이트</th>
        <td>
            <input type="text" value="<?php echo $bd['updated_at'] ?>" class="frm_input" readonly>
        </td>
    </tr>
=======
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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
<<<<<<< HEAD
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./bot_device_list.php" class="btn btn_02">목록</a>
</div>
</form>

<script>
function fbotdevice_submit(f)
{
    if (!f.status.value) {
        alert("상태를 선택하세요.");
=======
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
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        f.status.focus();
        return false;
    }
    
<<<<<<< HEAD
=======
    // 거부나 차단으로 변경하는 경우 확인
    if ((f.status.value == 'rejected' || f.status.value == 'blocked') && 
        ('<?php echo $device['status']?>' != 'rejected' && '<?php echo $device['status']?>' != 'blocked')) {
        if (!confirm('디바이스를 ' + (f.status.value == 'rejected' ? '거부' : '차단') + ' 상태로 변경하시겠습니까?')) {
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