<?php
$sub_menu = "190100";
include_once './_common.php';

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('distributor_form')) {
    alert('접근 권한이 없습니다.');
}

$g5['title'] = '총판 등록/수정 <i class="fa fa-star dmk-new-icon" title="NEW"></i>';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$is_add = false;
$mb_id = isset($_GET['mb_id']) ? sql_escape_string(trim($_GET['mb_id'])) : '';

if ($mb_id) {
    $distributor = sql_fetch(" SELECT m.*, d.dt_id FROM {$g5['member_table']} m JOIN dmk_distributor d ON m.mb_id = d.dt_id WHERE m.mb_id = '$mb_id' AND m.dmk_mb_type = 1 AND m.dmk_admin_type = 'main' ");

    if (!$distributor) {
        alert('해당 총판 정보를 찾을 수 없습니다.', G5_ADMIN_URL.'/dmk/adm/distributor_admin/distributor_list.php');
    }
} else {
    $is_add = true;
}

?>

<form name="fdistributorform" id="fdistributorform" action="./distributor_form_update.php" onsubmit="return fdistributorform_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w; ?>">
<input type="hidden" name="mb_id" value="<?php echo $mb_id; ?>">

<div class="tbl_frm01 tbl_wrap">
    <table>
        <caption><?php echo $g5['title']; ?></caption>
        <colgroup>
            <col class="frm_th">
            <col>
        </colgroup>
        <tbody>
            <tr>
                <th scope="row"><label for="mb_id">총판 ID</label></th>
                <td>
                    <?php if ($is_add) { ?>
                        <input type="text" name="mb_id" value="" id="mb_id" required class="frm_input required" size="20" maxlength="20">
                    <?php } else { ?>
                        <?php echo $distributor['mb_id']; ?>
                        <input type="hidden" name="mb_id" value="<?php echo $distributor['mb_id']; ?>">
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_name">총판명</label></th>
                <td>
                    <input type="text" name="mb_name" value="<?php echo get_text($distributor['mb_name']); ?>" id="mb_name" required class="frm_input required" size="30">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_password">비밀번호</label></th>
                <td>
                    <input type="password" name="mb_password" id="mb_password" <?php echo $is_add ? 'required' : ''; ?> class="frm_input <?php echo $is_add ? 'required' : ''; ?>" size="20" maxlength="20">
                    <?php if (!$is_add) { ?><p class="frm_info">수정하지 않으려면 공란으로 두세요.</p><?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_password_re">비밀번호 확인</label></th>
                <td>
                    <input type="password" name="mb_password_re" id="mb_password_re" <?php echo $is_add ? 'required' : ''; ?> class="frm_input <?php echo $is_add ? 'required' : ''; ?>" size="20" maxlength="20">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_hp">휴대폰 번호</label></th>
                <td>
                    <input type="text" name="mb_hp" value="<?php echo get_text($distributor['mb_hp']); ?>" id="mb_hp" class="frm_input" size="20">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_email">이메일</label></th>
                <td>
                    <input type="text" name="mb_email" value="<?php echo get_text($distributor['mb_email']); ?>" id="mb_email" class="frm_input" size="30">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_zip">주소</label></th>
                <td>
                    <input type="text" name="mb_zip" value="<?php echo get_text($distributor['mb_zip']); ?>" id="mb_zip" class="frm_input" size="5">
                    <button type="button" class="btn_frmline" onclick="win_zip('fdistributorform', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3');">주소 검색</button><br>
                    <input type="text" name="mb_addr1" value="<?php echo get_text($distributor['mb_addr1']); ?>" id="mb_addr1" class="frm_input" size="60">
                    <input type="text" name="mb_addr2" value="<?php echo get_text($distributor['mb_addr2']); ?>" id="mb_addr2" class="frm_input" size="60">
                    <input type="text" name="mb_addr3" value="<?php echo get_text($distributor['mb_addr3']); ?>" id="mb_addr3" class="frm_input" size="60">
                    <input type="text" name="mb_addr_jibeon" value="<?php echo get_text($distributor['mb_addr_jibeon']); ?>" id="mb_addr_jibeon" class="frm_input" size="60" readonly="readonly">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dt_status">총판 상태</label></th>
                <td>
                    <select name="dt_status" id="dt_status">
                        <option value="1" <?php echo ($distributor['dt_status'] == 1) ? 'selected' : ''; ?>>활성</option>
                        <option value="0" <?php echo ($distributor['dt_status'] == 0) ? 'selected' : ''; ?>>비활성</option>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="btn_confirm">
    <input type="submit" value="<?php echo $is_add ? '총판 등록' : '총판 정보 수정'; ?>" class="btn_submit" accesskey="s">
    <a href="./distributor_list.php" class="btn_cancel">목록</a>
</div>

</form>

<script>
function fdistributorform_submit(f)
{
    if (f.mb_password.value) {
        if (f.mb_password.value.length < 3) {
            alert("비밀번호를 3글자 이상 입력하십시오.");
            f.mb_password.focus();
            return false;
        }
    }

    if (f.mb_password.value != f.mb_password_re.value) {
        alert("비밀번호가 일치하지 않습니다.");
        f.mb_password_re.focus();
        return false;
    }

    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 