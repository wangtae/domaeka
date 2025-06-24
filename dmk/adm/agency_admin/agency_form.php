<?php
$sub_menu = "190200";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('agency_form')) {
    alert('접근 권한이 없습니다.');
}

auth_check_menu($auth, $sub_menu, 'w');

$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
$w = isset($_GET['w']) ? clean_xss_tags($_GET['w']) : '';

$html_title = '대리점 ';
if ($w == 'u') {
    $html_title .= '수정';
    
    $sql = " SELECT * FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $agency = sql_fetch($sql);
    
    if (!$agency) {
        alert('존재하지 않는 대리점입니다.');
    }
    
    // 기존 관리자 정보 조회
    $admin_sql = " SELECT mb_id, mb_name, mb_email, mb_phone FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($agency['ag_mb_id']) . "' ";
    $admin_info = sql_fetch($admin_sql);
    
} else {
    $html_title .= '등록';
    $w = '';
    
    // 새 대리점 ID 생성
    $sql = " SELECT MAX(CAST(SUBSTRING(ag_id, 3) AS UNSIGNED)) as max_num FROM dmk_agency WHERE ag_id LIKE 'AG%' ";
    $row = sql_fetch($sql);
    $next_num = $row['max_num'] ? $row['max_num'] + 1 : 1;
    $agency_id = 'AG' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
    $agency = array(
        'ag_id' => $agency_id,
        'ag_name' => '',
        'ag_ceo_name' => '',
        'ag_phone' => '',
        'ag_address' => '',
        'ag_mb_id' => '',
        'ag_status' => 1
    );
    
    $admin_info = array(
        'mb_id' => '',
        'mb_name' => '',
        'mb_email' => '',
        'mb_phone' => ''
    );
}

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

$qstr = get_query_string($qstr_valid, Array('w', 'ag_id'));
?>

<form name="fagency" id="fagency" action="./agency_form_update.php" onsubmit="return fagency_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">
<?php if ($w == 'u') { ?>
<input type="hidden" name="ag_id" value="<?php echo $agency['ag_id'] ?>">
<?php } ?>

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="ag_id">대리점 ID<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="ag_id_display" value="<?php echo $agency['ag_id'] ?>" id="ag_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">대리점 ID는 수정할 수 없습니다.</span>
            <?php } else { ?>
                <input type="text" name="ag_id" value="<?php echo $agency['ag_id'] ?>" id="ag_id" required class="frm_input required" size="20" maxlength="20">
                <span class="frm_info">대리점을 구분하는 고유 ID입니다. (예: AG001)</span>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="ag_name">대리점명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="ag_name" value="<?php echo get_text($agency['ag_name']) ?>" id="ag_name" required class="frm_input required" size="50" maxlength="100">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="ag_ceo_name">대표자명</label></th>
        <td>
            <input type="text" name="ag_ceo_name" value="<?php echo get_text($agency['ag_ceo_name']) ?>" id="ag_ceo_name" class="frm_input" size="30" maxlength="50">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="ag_phone">대표 전화번호</label></th>
        <td>
            <input type="text" name="ag_phone" value="<?php echo $agency['ag_phone'] ?>" id="ag_phone" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 02-1234-5678</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="ag_address">주소</label></th>
        <td>
            <input type="text" name="ag_address" value="<?php echo get_text($agency['ag_address']) ?>" id="ag_address" class="frm_input" size="80" maxlength="255">
        </td>
    </tr>
    
    <!-- 관리자 계정 정보 섹션 -->
    <tr>
        <th scope="row" colspan="2" style="background-color: #f8f9fa; text-align: center; font-weight: bold; padding: 15px;">
            <?php echo $w == 'u' ? '관리자 계정 정보' : '신규 관리자 계정 생성' ?>
        </th>
    </tr>
    <tr>
        <th scope="row"><label for="mb_id">관리자 아이디<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="mb_id_display" value="<?php echo $admin_info['mb_id'] ?>" id="mb_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">관리자 아이디는 수정할 수 없습니다.</span>
                <input type="hidden" name="mb_id" value="<?php echo $admin_info['mb_id'] ?>">
            <?php } else { ?>
                <input type="text" name="mb_id" value="<?php echo $admin_info['mb_id'] ?>" id="mb_id" required class="frm_input required" size="20" maxlength="20">
                <span class="frm_info">영문, 숫자, 언더스코어만 사용 가능 (3~20자)</span>
            <?php } ?>
        </td>
    </tr>
    <?php if ($w != 'u') { ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" required class="frm_input required" size="20" maxlength="20">
            <span class="frm_info">영문, 숫자, 특수문자 조합 (6~20자)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" required class="frm_input required" size="20" maxlength="20">
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="mb_name">관리자 이름<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($admin_info['mb_name']) ?>" id="mb_name" required class="frm_input required" size="30" maxlength="50">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">이메일<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="email" name="mb_email" value="<?php echo $admin_info['mb_email'] ?>" id="mb_email" required class="frm_input required" size="50" maxlength="100">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_phone">휴대폰번호</label></th>
        <td>
            <input type="text" name="mb_phone" value="<?php echo $admin_info['mb_phone'] ?>" id="mb_phone" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 010-1234-5678</span>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="ag_status">상태</label></th>
        <td>
            <input type="radio" name="ag_status" value="1" id="ag_status_1" <?php echo $agency['ag_status'] ? 'checked' : '' ?>>
            <label for="ag_status_1">활성</label>
            <input type="radio" name="ag_status" value="0" id="ag_status_0" <?php echo !$agency['ag_status'] ? 'checked' : '' ?>>
            <label for="ag_status_0">비활성</label>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./agency_list.php?<?php echo $qstr ?>" class="btn btn_02">목록</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey='s'>
</div>

</form>

<script>
function fagency_submit(f)
{
    if (!f.ag_id.value) {
        alert("대리점 ID를 입력하세요.");
        f.ag_id.focus();
        return false;
    }
    
    if (!f.ag_name.value) {
        alert("대리점명을 입력하세요.");
        f.ag_name.focus();
        return false;
    }
    
    if (!f.mb_id.value) {
        alert("관리자 아이디를 입력하세요.");
        f.mb_id.focus();
        return false;
    }
    
    // 신규 등록시 비밀번호 확인
    <?php if ($w != 'u') { ?>
    if (!f.mb_password.value) {
        alert("비밀번호를 입력하세요.");
        f.mb_password.focus();
        return false;
    }
    
    if (f.mb_password.value !== f.mb_password_confirm.value) {
        alert("비밀번호가 일치하지 않습니다.");
        f.mb_password_confirm.focus();
        return false;
    }
    
    // 비밀번호 강도 체크
    var password = f.mb_password.value;
    if (password.length < 6) {
        alert("비밀번호는 6자 이상이어야 합니다.");
        f.mb_password.focus();
        return false;
    }
    <?php } ?>
    
    if (!f.mb_name.value) {
        alert("관리자 이름을 입력하세요.");
        f.mb_name.focus();
        return false;
    }
    
    if (!f.mb_email.value) {
        alert("이메일을 입력하세요.");
        f.mb_email.focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?> 