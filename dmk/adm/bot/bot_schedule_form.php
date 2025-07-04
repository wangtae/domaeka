<?php
$sub_menu = '180300';
include_once('./_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '스케줄 등록/수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$sch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$schedule = array();

if ($sch_id) {
    $schedule = sql_fetch("SELECT * FROM kb_schedule WHERE id = '$sch_id'");
    if (!$schedule) {
        alert('스케줄 정보를 찾을 수 없습니다.');
    }
}

?>

<form name="fscheduleform" id="fscheduleform" action="./bot_schedule_form_update.php" onsubmit="return fscheduleform_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $sch_id ? 'u' : 'c'; ?>">
<input type="hidden" name="id" value="<?php echo $sch_id; ?>">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="frm_th">
        <col class="frm_td">
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="target_branch_id">대상 지점 ID</label></th>
        <td>
            <input type="text" name="target_branch_id" value="<?php echo isset($schedule['target_branch_id']) ? $schedule['target_branch_id'] : ''; ?>" id="target_branch_id" required class="frm_input required" size="30">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="message_content">메시지 내용</label></th>
        <td>
            <textarea name="message_content" id="message_content" required class="frm_input required" style="width:100%; height:150px;"><?php echo isset($schedule['message_content']) ? $schedule['message_content'] : ''; ?></textarea>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="send_time">발송 시간</label></th>
        <td>
            <input type="time" name="send_time" value="<?php echo isset($schedule['send_time']) ? $schedule['send_time'] : ''; ?>" id="send_time" class="frm_input" required>
            <span class="frm_info">매일 발송될 시간을 지정합니다. (예: 14:30)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="send_days">발송 요일</label></th>
        <td>
            <input type="text" name="send_days" value="<?php echo isset($schedule['send_days']) ? $schedule['send_days'] : ''; ?>" id="send_days" class="frm_input" size="30">
            <span class="frm_info">발송할 요일을 쉼표(,)로 구분하여 입력합니다. (예: Mon,Tue,Fri) 비워두면 매일 발송됩니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="send_date">발송 날짜</label></th>
        <td>
            <input type="date" name="send_date" value="<?php echo isset($schedule['send_date']) ? $schedule['send_date'] : ''; ?>" id="send_date" class="frm_input">
            <span class="frm_info">특정 날짜에 1회만 발송하려면 날짜를 지정합니다. 요일과 함께 지정하면 해당 날짜의 해당 요일에 발송됩니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status">
                <option value="active" <?php echo (isset($schedule['status']) && $schedule['status'] == 'active') ? 'selected' : ''; ?>>활성</option>
                <option value="inactive" <?php echo (isset($schedule['status']) && $schedule['status'] == 'inactive') ? 'selected' : ''; ?>>비활성</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="image_file">이미지 파일</label></th>
        <td>
            <input type="file" name="image_file" id="image_file" class="frm_file">
            <?php if (isset($schedule['image_url']) && $schedule['image_url']) { ?>
                <p class="frm_info">현재 파일: <a href="<?php echo $schedule['image_url']; ?>" target="_blank"><?php echo basename($schedule['image_url']); ?></a></p>
                <input type="checkbox" name="image_file_del" value="1" id="image_file_del"> <label for="image_file_del">파일 삭제</label>
            <?php } ?>
            <span class="frm_info">메시지와 함께 발송할 이미지를 업로드합니다.</span>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_confirm">
    <input type="submit" value="확인" class="btn_submit">
    <a href="./bot_schedule_list.php" class="btn_frmline">목록</a>
</div>

</form>

<script>
function fscheduleform_submit(f) {
    // 유효성 검사 추가
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>