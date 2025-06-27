<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

// 최고관리자가 아니면 서브관리자 등록/수정 페이지 접근 불가
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super'] && dmk_get_current_user_type() === 'sub') {
    alert('접근 권한이 없습니다.', G5_ADMIN_URL);
}

$mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';
$w = isset($_GET['w']) ? clean_xss_tags($_GET['w']) : '';

// 검색 파라미터
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 폼에 표시될 회원 정보를 담을 변수
$form_member = [];

if ($w == 'u') {
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
    $form_member = sql_fetch($sql);
    if (!$form_member['mb_id']) {
        alert('존재하지 않는 회원자료입니다.');
    }
} else {
    // 신규 등록 시 Undefined array key 경고 방지를 위해 $form_member 배열 초기화
    $form_member = [
        'mb_id' => '',
        'mb_name' => '',
        'mb_nick' => '',
        'mb_email' => '',
        'mb_hp' => '',
        'mb_level' => '',
        'mb_memo' => '',
        'dmk_mb_type' => '',
        'dmk_dt_id' => '',
        'dmk_ag_id' => '',
        'dmk_br_id' => '',
    ];
    // 신규 등록 시에는 $w를 빈 값으로 설정 (폼 전송 시 혼동 방지)
    $w = '';
}

$g5['title'] = '서브 관리자 수정';

// 생성 가능한 관리자 유형 옵션
$admin_type_options = array();
if ($dmk_auth['is_super']) {
    $admin_type_options = array(
        '1' => '총판 관리자',
        '2' => '대리점 관리자', 
        '3' => '지점 관리자'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) {
    $admin_type_options = array(
        '1' => '총판 관리자',
        '2' => '대리점 관리자', 
        '3' => '지점 관리자'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
    $admin_type_options = array(
        '2' => '대리점 관리자', 
        '3' => '지점 관리자'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH) {
    $admin_type_options = array(
        '3' => '지점 관리자'
    );
}

// URL 쿼리 스트링 생성
$qstr = 'sfl='.$sfl.'&amp;stx='.$stx.'&amp;page='.$page;

require_once G5_ADMIN_PATH.'/admin.head.php';
?>

<form name="fmember" id="fmember" action="./admin_form_update.php" onsubmit="return fmember_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="mb_id">관리자아이디<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="hidden" name="mb_id" value="<?php echo $form_member['mb_id'] ?>">
                <strong><?php echo $form_member['mb_id'] ?></strong>
            <?php } else { ?>
                <input type="text" name="mb_id" value="" id="mb_id" required class="required frm_input" size="15" maxlength="20">
                <span class="frm_info">영문자, 숫자, _ 만 입력 가능. 최소 3자이상 입력하세요.</span>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" <?php echo $w==''?'required class="required"':''; ?> class="frm_input" size="15" maxlength="20">
            <?php if ($w == 'u') { ?>
            <span class="frm_info">비밀번호를 변경하려면 입력하세요.</span>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_re">비밀번호 확인<strong class="sound_only">필수</strong></label></th>
        <td><input type="password" name="mb_password_re" id="mb_password_re" <?php echo $w==''?'required class="required"':''; ?> class="frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_name">이름<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_name" value="<?php echo get_text($form_member['mb_name']) ?>" id="mb_name" required class="required frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_nick">닉네임<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_nick" value="<?php echo get_text($form_member['mb_nick']) ?>" id="mb_nick" required class="required frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">E-mail<strong class="sound_only">필수</strong></label></th>
        <td><input type="email" name="mb_email" value="<?php echo $form_member['mb_email'] ?>" id="mb_email" required class="required frm_input" size="30" maxlength="100"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td><input type="text" name="mb_hp" value="<?php echo get_text($form_member['mb_hp']) ?>" id="mb_hp" class="frm_input" size="20" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="dmk_mb_type">관리자 유형<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="dmk_mb_type" id="dmk_mb_type" required class="required" <?php echo ($w == 'u') ? 'disabled' : ''; ?> >
                <option value="">선택하세요</option>
                <?php foreach ($admin_type_options as $value => $text) { ?>
                <option value="<?php echo $value ?>"<?php echo get_selected($form_member['dmk_mb_type'], $value); ?>><?php echo $text ?></option>
                <?php } ?>
            </select>
            <?php if ($w == 'u') { // 수정 모드일 경우 숨겨진 필드로 dmk_mb_type 값을 전달 ?>
            <?php error_log("admin_form.php - dmk_mb_type value for hidden input: " . ($form_member['dmk_mb_type'] ?? 'NULL')); // DEBUG LOG ?>
            <input type="hidden" name="dmk_mb_type" value="<?php echo $form_member['dmk_mb_type']; ?>">
            <?php } ?>
            <input type="hidden" name="mb_level" id="mb_level" value="<?php echo $form_member['mb_level'] ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_memo">메모</label></th>
        <td><textarea name="mb_memo" id="mb_memo" rows="5" class="frm_textbox"><?php echo $form_member['mb_memo'] ?></textarea></td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./admin_list.php?<?php echo $qstr ?>" class="btn btn_02">목록</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey='s'>
</div>

</form>

<script>
// 관리자 유형에 따른 mb_level 자동 설정
document.getElementById('dmk_mb_type').addEventListener('change', function() {
    var dmkType = this.value;
    var mbLevel = document.getElementById('mb_level');
    
    switch(dmkType) {
        case '1': // 총판
            mbLevel.value = '8';
            break;
        case '2': // 대리점
            mbLevel.value = '6';
            break;
        case '3': // 지점
            mbLevel.value = '4';
            break;
        default:
            mbLevel.value = '';
    }
});

function fmember_submit(f) {
    if (!f.mb_id.value) {
        alert("아이디를 입력하세요.");
        f.mb_id.focus();
        return false;
    }

    if (f.w.value == "") {
        if (!f.mb_password.value) {
            alert("비밀번호를 입력하세요.");
            f.mb_password.focus();
            return false;
        }
    }

    if (f.mb_password.value || f.mb_password_re.value) {
        if (f.mb_password.value != f.mb_password_re.value) {
            alert("비밀번호가 일치하지 않습니다.");
            f.mb_password_re.focus();
            return false;
        }
    }

    if (f.mb_password.value.length > 0 && f.mb_password.value.length < 3) {
        alert("비밀번호를 3글자 이상 입력하세요.");
        f.mb_password.focus();
        return false;
    }

    if (!f.mb_name.value) {
        alert("이름을 입력하세요.");
        f.mb_name.focus();
        return false;
    }

    if (!f.mb_nick.value) {
        alert("닉네임을 입력하세요.");
        f.mb_nick.focus();
        return false;
    }

    if (!f.mb_email.value) {
        alert("E-mail을 입력하세요.");
        f.mb_email.focus();
        return false;
    }

    if (f.w.value == '' && !f.dmk_mb_type.value) { // 신규 등록일 때만 검사
        alert("관리자 유형을 선택하세요.");
        f.dmk_mb_type.focus();
        return false;
    }

    return true;
}
</script>

<?php
require_once G5_ADMIN_PATH.'/admin.tail.php';
?> 