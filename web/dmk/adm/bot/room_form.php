<?php
/**
 * 채팅방 수정 (상태, 설명만)
 */

$sub_menu = "180500";
include_once('./_common.php');

auth_check('180500', 'w');

$w = $_GET['w'];
$room_id = $_GET['room_id'];

if ($w == 'u' && $room_id) {
    $sql = " SELECT * FROM kb_rooms WHERE room_id = '".sql_real_escape_string($room_id)."' ";
    $rm = sql_fetch($sql);
    if (!$rm['room_id']) {
        alert('등록된 채팅방이 아닙니다.');
    }
} else {
    alert('잘못된 접근입니다.');
}

$g5['title'] = '채팅방 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');
?>

<form name="froom" id="froom" action="./room_form_update.php" onsubmit="return froom_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id) ?>">
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
    </tr>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
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
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./room_list.php" class="btn btn_02">목록</a>
</div>
</form>

<script>
function froom_submit(f)
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