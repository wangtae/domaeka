<?php
/**
 * 클라이언트 봇 수정 (상태, 설명만)
 */

$sub_menu = "180300";
include_once('./_common.php');

auth_check('180300', 'w');

$w = $_GET['w'];
$device_id = $_GET['device_id'];

if ($w == 'u' && $device_id) {
    $sql = " SELECT * FROM kb_bot_devices WHERE id = '$device_id' ";
    $bd = sql_fetch($sql);
    if (!$bd['id']) {
        alert('등록된 디바이스가 아닙니다.');
    }
} else {
    alert('잘못된 접근입니다.');
}

$g5['title'] = '클라이언트 봇 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<form name="fbotdevice" id="fbotdevice" action="./bot_device_form_update.php" onsubmit="return fbotdevice_submit(this);" method="post">
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
    </tr>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
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
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./bot_device_list.php" class="btn btn_02">목록</a>
</div>
</form>

<script>
function fbotdevice_submit(f)
{
    if (!f.status.value) {
        alert("상태를 선택하세요.");
        f.status.focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>