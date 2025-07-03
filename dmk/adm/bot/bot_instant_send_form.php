<?php
$sub_menu = '180400';
include_once('../../_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '메시지 즉시 발송';
include_once (G5_ADMIN_PATH.'/admin.head.php');

?>

<form name="finstantsend" id="finstantsend" action="./bot_instant_send_update.php" onsubmit="return finstantsend_submit(this);" method="post" enctype="multipart/form-data">

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
            <input type="text" name="target_branch_id" id="target_branch_id" required class="frm_input required" size="30">
            <span class="frm_info">메시지를 발송할 지점 ID를 입력합니다. (예: branch_001)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="message_content">메시지 내용</label></th>
        <td>
            <textarea name="message_content" id="message_content" required class="frm_input required" style="width:100%; height:150px;"></textarea>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="image_file">이미지 파일</label></th>
        <td>
            <input type="file" name="image_file" id="image_file" class="frm_file">
            <span class="frm_info">메시지와 함께 발송할 이미지를 업로드합니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="send_type">발송 유형</label></th>
        <td>
            <select name="send_type" id="send_type">
                <option value="operation">운영 톡방</option>
                <option value="test">테스트 톡방</option>
            </select>
            <span class="frm_info">메시지를 발송할 톡방 유형을 선택합니다.</span>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_confirm">
    <input type="submit" value="발송" class="btn_submit">
</div>

</form>

<script>
function finstantsend_submit(f) {
    // 유효성 검사 추가
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>