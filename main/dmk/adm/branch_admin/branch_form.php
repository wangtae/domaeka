<?php
$sub_menu = "190300"; // 지점관리
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('branch_form')) {
    alert('접근 권한이 없습니다.');
}

auth_check_menu($auth, $sub_menu, 'w');

$br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';
$w = isset($_GET['w']) ? clean_xss_tags($_GET['w']) : '';

$html_title = '지점 ';
if ($w == 'u') {
    $html_title .= '수정';
    
    $sql = " SELECT * FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $branch = sql_fetch($sql);
    
    if (!$branch) {
        alert('존재하지 않는 지점입니다.');
    }
} else {
    $html_title .= '등록';
    $w = '';
    
    // 새 지점 ID 생성
    $sql = " SELECT MAX(CAST(SUBSTRING(br_id, 3) AS UNSIGNED)) as max_num FROM dmk_branch WHERE br_id LIKE 'BR%' ";
    $row = sql_fetch($sql);
    $next_num = $row['max_num'] ? $row['max_num'] + 1 : 1;
    $branch['br_id'] = 'BR' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
    $branch = array(
        'br_id' => $branch['br_id'],
        'br_name' => '',
        'br_ceo_name' => '',
        'br_phone' => '',
        'br_address' => '',
        'br_mb_id' => '',
        'ag_id' => '',
        'br_status' => 1
    );
}

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 지점 관리자로 지정 가능한 회원 목록 조회
$member_options = '<option value="">관리자 선택</option>';
$member_sql = " SELECT mb_id, mb_name FROM {$g5['member_table']} WHERE dmk_mb_type = 0 OR dmk_mb_type = 2 ORDER BY mb_name ";
$member_result = sql_query($member_sql);
while ($member_row = sql_fetch_array($member_result)) {
    $selected = ($branch['br_mb_id'] == $member_row['mb_id']) ? ' selected' : '';
    $member_options .= '<option value="' . $member_row['mb_id'] . '"' . $selected . '>' . $member_row['mb_name'] . ' (' . $member_row['mb_id'] . ')</option>';
}

// 대리점 목록 조회 (지점이 소속될 대리점 선택)
$agency_options = '<option value="">대리점 선택</option>';
$agency_sql = " SELECT ag_id, ag_name FROM dmk_agency ORDER BY ag_name ";
$agency_result = sql_query($agency_sql);
while ($agency_row = sql_fetch_array($agency_result)) {
    $selected = ($branch['ag_id'] == $agency_row['ag_id']) ? ' selected' : '';
    $agency_options .= '<option value="' . $agency_row['ag_id'] . '"' . $selected . '>' . $agency_row['ag_name'] . ' (' . $agency_row['ag_id'] . ')</option>';
}

?>

<form name="fbranch" id="fbranch" action="./branch_form_update.php" onsubmit="return fbranch_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">
<?php if ($w == 'u') { ?>
<input type="hidden" name="br_id" value="<?php echo $branch['br_id'] ?>">
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
        <th scope="row"><label for="br_id">지점 ID<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="br_id_display" value="<?php echo $branch['br_id'] ?>" id="br_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">지점 ID는 수정할 수 없습니다.</span>
            <?php } else { ?>
                <input type="text" name="br_id" value="<?php echo $branch['br_id'] ?>" id="br_id" required class="frm_input required" size="20" maxlength="20">
                <span class="frm_info">지점을 구분하는 고유 ID입니다. (예: BR001)</span>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="ag_id">소속 대리점<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="ag_id" id="ag_id" required class="frm_input required">
                <?php echo $agency_options ?>
            </select>
            <span class="frm_info">이 지점이 소속될 대리점을 선택하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_name">지점명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="br_name" value="<?php echo get_text($branch['br_name']) ?>" id="br_name" required class="frm_input required" size="50" maxlength="100">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_ceo_name">대표자명</label></th>
        <td>
            <input type="text" name="br_ceo_name" value="<?php echo get_text($branch['br_ceo_name']) ?>" id="br_ceo_name" class="frm_input" size="30" maxlength="50">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_phone">대표 전화번호</label></th>
        <td>
            <input type="text" name="br_phone" value="<?php echo $branch['br_phone'] ?>" id="br_phone" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 02-1234-5678</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_address">주소</label></th>
        <td>
            <input type="text" name="br_address" value="<?php echo get_text($branch['br_address']) ?>" id="br_address" class="frm_input" size="80" maxlength="255">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_mb_id">지점 관리자<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="br_mb_id" id="br_mb_id" required class="frm_input required">
                <?php echo $member_options ?>
            </select>
            <span class="frm_info">이 지점을 관리할 회원을 선택하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_status">상태</label></th>
        <td>
            <input type="radio" name="br_status" value="1" id="br_status_1" <?php echo $branch['br_status'] ? 'checked' : '' ?>>
            <label for="br_status_1">활성</label>
            <input type="radio" name="br_status" value="0" id="br_status_0" <?php echo !$branch['br_status'] ? 'checked' : '' ?>>
            <label for="br_status_0">비활성</label>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./branch_list.php?<?php echo $qstr ?>" class="btn btn_02">목록</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey='s'>
</div>

</form>

<script>
function fbranch_submit(f)
{
    <?php echo get_editor_js('br_content'); ?>
    
    if (!f.br_id.value) {
        alert("지점 ID를 입력하세요.");
        f.br_id.focus();
        return false;
    }

    if (!f.ag_id.value) {
        alert("소속 대리점을 선택하세요.");
        f.ag_id.focus();
        return false;
    }
    
    if (!f.br_name.value) {
        alert("지점명을 입력하세요.");
        f.br_name.focus();
        return false;
    }
    
    if (!f.br_mb_id.value) {
        alert("지점 관리자를 선택하세요.");
        f.br_mb_id.focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 