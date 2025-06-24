<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

$w = isset($_GET['w']) ? clean_xss_tags($_GET['w']) : '';
$mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';

// 검색 파라미터
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$dmk_auth = dmk_get_admin_auth();

// 수정 모드일 때 기존 데이터 조회
$member = array();
if ($w == 'u' && $mb_id) {
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' AND mb_level >= 4 ";
    $member = sql_fetch($sql);
    
    if (!$member) {
        alert('존재하지 않는 관리자입니다.');
    }
    
    // 수정 권한 체크
    $can_modify = false;
    if ($dmk_auth['is_super']) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $member['mb_level'] <= 8) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $member['mb_level'] <= 6) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $member['mb_level'] == 4) {
        $can_modify = true;
    }
    
    if (!$can_modify) {
        alert('수정 권한이 없습니다.');
    }
    
    $g5['title'] = '관리자 수정';
} else {
    $g5['title'] = '관리자 등록';
}

// 생성 가능한 관리자 유형 옵션
$admin_type_options = array();
if ($dmk_auth['is_super']) {
    $admin_type_options = array(
        '1' => '총판 관리자 (레벨 8)',
        '2' => '대리점 관리자 (레벨 6)', 
        '3' => '지점 관리자 (레벨 4)'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) {
    $admin_type_options = array(
        '1' => '총판 관리자 (레벨 8)',
        '2' => '대리점 관리자 (레벨 6)', 
        '3' => '지점 관리자 (레벨 4)'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
    $admin_type_options = array(
        '2' => '대리점 관리자 (레벨 6)', 
        '3' => '지점 관리자 (레벨 4)'
    );
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH) {
    $admin_type_options = array(
        '3' => '지점 관리자 (레벨 4)'
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
        <th scope="row"><label for="mb_id">관리자아이디<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="hidden" name="mb_id" value="<?php echo $member['mb_id'] ?>">
                <strong><?php echo $member['mb_id'] ?></strong>
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
        <td><input type="text" name="mb_name" value="<?php echo get_text($member['mb_name']) ?>" id="mb_name" required class="required frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_nick">닉네임<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_nick" value="<?php echo get_text($member['mb_nick']) ?>" id="mb_nick" required class="required frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">E-mail<strong class="sound_only">필수</strong></label></th>
        <td><input type="email" name="mb_email" value="<?php echo $member['mb_email'] ?>" id="mb_email" required class="required frm_input" size="30" maxlength="100"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td><input type="text" name="mb_hp" value="<?php echo get_text($member['mb_hp']) ?>" id="mb_hp" class="frm_input" size="20" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="dmk_mb_type">관리자 유형<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="dmk_mb_type" id="dmk_mb_type" required class="required">
                <option value="">선택하세요</option>
                <?php foreach ($admin_type_options as $value => $text) { ?>
                <option value="<?php echo $value ?>"<?php echo get_selected($member['dmk_mb_type'], $value); ?>><?php echo $text ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="mb_level" id="mb_level" value="<?php echo $member['mb_level'] ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_memo">메모</label></th>
        <td><textarea name="mb_memo" id="mb_memo" rows="5" class="frm_textbox"><?php echo $member['mb_memo'] ?></textarea></td>
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

    if (!f.dmk_mb_type.value) {
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