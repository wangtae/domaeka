<?php
/**
 * 서버 등록/수정
 */

include_once('./_common.php');

auth_check('180100', 'w');

$w = $_GET['w'];
$server_id = $_GET['server_id'];

if ($w == 'u' && $server_id) {
    $sql = " SELECT * FROM kb_servers WHERE server_id = '$server_id' ";
    $sv = sql_fetch($sql);
    if (!$sv['server_id']) {
        alert('등록된 서버가 아닙니다.');
    }
} else {
    $sv = array();
}

$g5['title'] = '서버 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<form name="fserver" id="fserver" action="./server_form_update.php" onsubmit="return fserver_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="server_id" value="<?php echo $server_id ?>">
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
        <th scope="row"><label for="server_name">서버명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="server_name" value="<?php echo get_text($sv['server_name']) ?>" id="server_name" required class="frm_input required" size="40" maxlength="100">
            <span class="frm_info">카카오봇 서버의 이름을 입력하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="server_host">서버 호스트<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="server_host" value="<?php echo get_text($sv['server_host']) ?>" id="server_host" required class="frm_input required" size="40" maxlength="45">
            <span class="frm_info">서버의 IP 주소 또는 도메인을 입력하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="priority">우선순위</label></th>
        <td>
            <input type="number" name="priority" value="<?php echo $sv['priority'] ? $sv['priority'] : 100 ?>" id="priority" class="frm_input" min="1" max="999">
            <span class="frm_info">낮은 숫자일수록 높은 우선순위입니다. (기본값: 100)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status" class="frm_input">
                <option value="healthy" <?php echo $sv['status']=='healthy'?'selected':'';?>>정상 (Healthy)</option>
                <option value="degraded" <?php echo $sv['status']=='degraded'?'selected':'';?>>저하됨 (Degraded)</option>
                <option value="maintenance" <?php echo $sv['status']=='maintenance'?'selected':'';?>>점검중 (Maintenance)</option>
                <option value="failed" <?php echo $sv['status']=='failed'?'selected':'';?>>실패 (Failed)</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="max_bots">최대 봇 수</label></th>
        <td>
            <input type="number" name="max_bots" value="<?php echo $sv['max_bots'] ?>" id="max_bots" class="frm_input" min="0">
            <span class="frm_info">이 서버에서 처리할 수 있는 최대 봇 수를 설정합니다. (0 = 무제한)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td>
            <textarea name="description" id="description" rows="5" class="frm_textbox"><?php echo get_text($sv['description']) ?></textarea>
            <span class="frm_info">서버에 대한 설명을 입력하세요.</span>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./server_list.php" class="btn btn_02">목록</a>
    <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn">확인</button>
</div>
</form>

<script>
function fserver_submit(f)
{
    if (!f.server_name.value) {
        alert("서버명을 입력하세요.");
        f.server_name.focus();
        return false;
    }

    if (!f.server_host.value) {
        alert("서버 호스트를 입력하세요.");
        f.server_host.focus();
        return false;
    }

    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>