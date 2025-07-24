<?php
$sub_menu = "190100";
include_once './_common.php';

// 도매까 권한 라이브러리 포함 (이미 _common.php에 포함되어 있을 수 있지만, 명시적으로 다시 포함하여 문제 방지)
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 현재 관리자 권한 정보 가져오기
$auth = dmk_get_admin_auth();

// 메뉴 접근 권한 확인
// dmk_authenticate_form_access 함수를 사용하여 통합 권한 체크
$w = isset($_GET['w']) ? $_GET['w'] : '';
$mb_id = isset($_GET['mb_id']) ? sql_escape_string(trim($_GET['mb_id'])) : '';

dmk_authenticate_form_access('distributor_form', $w, $mb_id);

$g5['title'] = '총판 '.($_GET['w']=='' ? '등록' : '수정').' <i class="fa fa-star dmk-new-icon" title="NEW"></i>';
include_once (G5_ADMIN_PATH.'/admin.head.php');

add_javascript(G5_POSTCODE_JS, 0);    //다음 주소 js

$is_add = false;

if ($mb_id) {
    $distributor = sql_fetch(" SELECT m.*, d.dt_id, d.dt_status FROM {$g5['member_table']} m JOIN dmk_distributor d ON m.mb_id = d.dt_id WHERE m.mb_id = '$mb_id' AND m.dmk_mb_type = 1 AND m.dmk_admin_type = 'main' ");

    if (!$distributor) {
        alert('해당 총판 정보를 찾을 수 없습니다.', G5_ADMIN_URL.'/dmk/adm/distributor_admin/distributor_list.php');
    }
} else {
    $is_add = true;
    // 등록 모드일 때 기본값 설정
    $distributor = array(
        'mb_id' => '',
        'mb_name' => '',
        'mb_nick' => '',
        'mb_hp' => '',
        'mb_tel' => '',
        'mb_email' => '',
        'mb_zip1' => '',
        'mb_zip2' => '',
        'mb_addr1' => '',
        'mb_addr2' => '',
        'mb_addr3' => '',
        'mb_addr_jibeon' => '',
        'dt_status' => 1
    );
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
                <th scope="row"><label for="mb_name">총판명</label></th>
                <td>
                    <input type="text" name="mb_name" value="<?php echo get_text($distributor['mb_name']); ?>" id="mb_name" required class="frm_input required" size="30">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_nick">회사명</label></th>
                <td>
                    <input type="text" name="mb_nick" value="<?php echo get_text($distributor['mb_nick']); ?>" id="mb_nick" class="frm_input" size="30">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_hp">휴대폰 번호</label></th>
                <td>
                    <input type="text" name="mb_hp" value="<?php echo get_text($distributor['mb_hp']); ?>" id="mb_hp" class="frm_input" size="20">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_tel">전화번호</label></th>
                <td>
                    <input type="text" name="mb_tel" value="<?php echo get_text($distributor['mb_tel']); ?>" id="mb_tel" class="frm_input" size="20">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mb_email">이메일</label></th>
                <td>
                    <input type="text" name="mb_email" value="<?php echo get_text($distributor['mb_email']); ?>" id="mb_email" class="frm_input" size="30">
                </td>
            </tr>
            <tr>
                <th scope="row">주소</th>
                <td colspan="3" class="td_addr_line">
                    <label for="mb_zip" class="sound_only">우편번호</label>
                    <input type="text" name="mb_zip" value="<?php echo get_text($distributor['mb_zip1']).get_text($distributor['mb_zip2']); ?>" id="mb_zip" class="frm_input readonly" size="5" maxlength="6">
                    <button type="button" class="btn_frmline" onclick="win_zip('fdistributorform', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');">주소 검색</button><br>
                    <input type="text" name="mb_addr1" value="<?php echo get_text($distributor['mb_addr1'] ?? ''); ?>" id="mb_addr1" class="frm_input readonly" size="60">
                    <label for="mb_addr1">기본주소</label><br>
                    <input type="text" name="mb_addr2" value="<?php echo get_text($distributor['mb_addr2'] ?? ''); ?>" id="mb_addr2" class="frm_input" size="60">
                    <label for="mb_addr2">상세주소</label>
                    <br>
                    <input type="text" name="mb_addr3" value="<?php echo get_text($distributor['mb_addr3'] ?? ''); ?>" id="mb_addr3" class="frm_input" size="60">
                    <label for="mb_addr3">참고항목</label>
                    <input type="hidden" name="mb_addr_jibeon" value="<?php echo get_text($distributor['mb_addr_jibeon'] ?? ''); ?>">
                </td>
            </tr>
            <?php if ($auth['is_super'] || $mb_id != $auth['mb_id']) { ?>
            <tr>
                <th scope="row"><label for="dt_status">총판 상태</label></th>
                <td>
                    <select name="dt_status" id="dt_status">
                        <option value="1" <?php echo (($distributor['dt_status'] ?? 1) == 1) ? 'selected' : ''; ?>>활성</option>
                        <option value="0" <?php echo (($distributor['dt_status'] ?? 1) == 0) ? 'selected' : ''; ?>>비활성</option>
                    </select>                    
                </td>
            </tr>
            <?php } else { ?>
            <tr style="display: none;">
                <th scope="row"><label for="dt_status">총판 상태</label></th>
                <td>
                    <input type="hidden" name="dt_status" value="<?php echo ($distributor['dt_status'] ?? 1); ?>">
                    <span class="frm_info">자신의 상태는 변경할 수 없습니다. (현재: <?php echo (($distributor['dt_status'] ?? 1) == 1) ? '활성' : '비활성'; ?>)</span>
                </td>
            </tr>
            <?php } ?>            
        </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./distributor_list.php" class="btn btn_02">목록</a>
    <input type="submit" value="<?php echo $is_add ? '등록' : '수정'; ?>" class="btn_submit btn" accesskey="s">
</div>

</form>

<script>
function fdistributorform_submit(f)
{
    // 아이디 유효성 검사
    if (f.mb_id && f.mb_id.value) {
        var mb_id = f.mb_id.value;
        
        // 길이 체크
        if (mb_id.length < 4 || mb_id.length > 20) {
            alert('아이디는 4~20자 사이여야 합니다.');
            f.mb_id.focus();
            return false;
        }
        
        // 문자 규칙 체크
        if (!/^[a-zA-Z0-9_]+$/.test(mb_id)) {
            alert('아이디는 영문자, 숫자, 언더스코어(_)만 사용 가능합니다.');
            f.mb_id.focus();
            return false;
        }
        
        // 첫 글자 체크
        if (!/^[a-zA-Z]/.test(mb_id)) {
            alert('아이디는 영문자로 시작해야 합니다.');
            f.mb_id.focus();
            return false;
        }
    }

    if (f.mb_password.value) {
        var mb_id = f.mb_id ? f.mb_id.value : '';
        var password = f.mb_password.value;
        
        // 비밀번호 길이 체크 (6자 이상)
        if (password.length < 6) {
            alert("비밀번호는 6자 이상이어야 합니다.");
            f.mb_password.focus();
            return false;
        }
        
        // 아이디와 완전히 동일한 비밀번호 금지 (대소문자 구분 없이)
        if (password.toLowerCase() === mb_id.toLowerCase()) {
            alert("비밀번호는 아이디와 달라야 합니다.");
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