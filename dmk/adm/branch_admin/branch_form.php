<?php
$sub_menu = "190300"; // 지점관리
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('branch_form')) {
    alert('접근 권한이 없습니다.');
}

dmk_auth_check_menu($auth, $sub_menu, 'w');

$br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
$w = isset($_GET['w']) ? clean_xss_tags($_GET['w']) : '';

$html_title = '지점 ';
if ($w == 'u') {
    $html_title .= '수정';
    
    $sql = " SELECT * FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $branch = sql_fetch($sql);
    
    if (!$branch) {
        alert('존재하지 않는 지점입니다.');
    }
    
    // 기존 관리자 정보 조회 (br_id가 곧 관리자 ID)
    $admin_sql = " SELECT mb_id, mb_name, mb_nick, mb_email, mb_tel, mb_hp, mb_zip, mb_addr1, mb_addr2, mb_addr3, mb_addr_jibeon FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($branch['br_id']) . "' ";
    $admin_info = sql_fetch($admin_sql);

    // 관리자 정보가 없을 경우 기본값으로 초기화
    if (!$admin_info) {
        $admin_info = array(
            'mb_id' => $branch['br_id'], // br_id를 관리자 ID로 사용
            'mb_name' => '',
            'mb_nick' => '',
            'mb_email' => '',
            'mb_tel' => '',
            'mb_hp' => '',
            'mb_zip' => '',
            'mb_addr1' => '',
            'mb_addr2' => '',
            'mb_addr3' => '',
            'mb_addr_jibeon' => ''
        );
    }
    
} else {
    $html_title .= '등록';
    $w = '';
    
    // 8~12자리 단축 코드 자동 생성 함수
    function generate_shortcut_code() {
        $length = rand(8, 12);
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $shortcut_code = '';
        for ($i = 0; $i < $length; $i++) {
            $shortcut_code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $shortcut_code;
    }
    
    // 중복되지 않는 단축 코드 생성
    do {
        $shortcut_code = generate_shortcut_code();
        $check_sql = " SELECT COUNT(*) as cnt FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($shortcut_code) . "' ";
        $check_result = sql_fetch($check_sql);
    } while ($check_result['cnt'] > 0);

    $branch = array(
        'br_id' => '',
        'ag_id' => $ag_id,
        'br_shortcut_code' => $shortcut_code,
        'br_status' => 1
    );
    
    // For new entries, also initialize admin_info
    $admin_info = array(
        'mb_id' => '',
        'mb_name' => '',
        'mb_nick' => '',
        'mb_email' => '',
        'mb_tel' => '',
        'mb_hp' => '',
        'mb_zip' => '',
        'mb_addr1' => '',
        'mb_addr2' => '',
        'mb_addr3' => '',
        'mb_addr_jibeon' => ''
    );
}

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 현재 관리자 권한 정보
$dmk_auth = dmk_get_admin_auth();

// 대리점 목록 조회 (현재 관리자 권한에 따라)
$agency_options = '<option value="">대리점 선택</option>';
if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == 1) {
    // 최고관리자 또는 총판: 모든 대리점
    $agency_sql = " SELECT ag_id, ag_name FROM dmk_agency WHERE ag_status = 1 ORDER BY ag_name ";
} else if ($dmk_auth['mb_type'] == 2) {
    // 대리점 관리자: 자신의 대리점만
    $agency_sql = " SELECT ag_id, ag_name FROM dmk_agency WHERE ag_id = '" . sql_escape_string($dmk_auth['ag_id']) . "' AND ag_status = 1 ";
} else {
    $agency_sql = " SELECT ag_id, ag_name FROM dmk_agency WHERE 1=0 "; // 접근 불가
}

$agency_result = sql_query($agency_sql);
while ($agency_row = sql_fetch_array($agency_result)) {
    $selected = ($branch['ag_id'] == $agency_row['ag_id']) ? ' selected' : '';
    $agency_options .= '<option value="' . $agency_row['ag_id'] . '"' . $selected . '>' . $agency_row['ag_name'] . ' (' . $agency_row['ag_id'] . ')</option>';
}

// 기존 get_query_string()의 역할을 대체합니다.
// 현재 쿼리 스트링에서 'w', 'br_id', 'ag_id'를 제외한 새로운 쿼리 스트링을 생성합니다.
$current_query_string = $_SERVER['QUERY_STRING'];
parse_str($current_query_string, $params);

// 제외할 파라미터들
$exclude_params = array('w', 'br_id', 'ag_id');
foreach ($exclude_params as $param) {
    unset($params[$param]);
}

$qstr = http_build_query($params, '', '&amp;');
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
<input type="hidden" name="mb_id" value="<?php echo $branch['br_id'] ?>">
<?php } else { ?>
<input type="hidden" name="mb_id" value="<?php echo $branch['br_id'] ?>">
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
    <?php if ($w != 'u') { // 신규 등록 시에만 비밀번호 입력 ?>
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
    <?php } else { // 수정 시 비밀번호는 선택 사항 ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호</label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" class="frm_input" size="20" maxlength="20">
            <p class="frm_info">수정하지 않으려면 공란으로 두세요.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인</label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" class="frm_input" size="20" maxlength="20">
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="br_shortcut_code">단축 URL 코드</label></th>
        <td>
            <input type="text" name="br_shortcut_code" value="<?php echo get_text($branch['br_shortcut_code']) ?>" id="br_shortcut_code" class="frm_input" size="50" maxlength="255">
            <span class="frm_info">이 지점으로 연결될 짧은 URL 코드입니다. (예: gangnam1, busan2) 영문, 숫자, 하이픈, 언더스코어만 사용 가능합니다.</span>
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
        <th scope="row"><label for="mb_nick">지점명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_nick" value="<?php echo get_text($admin_info['mb_nick'] ?? '') ?>" id="mb_nick" required class="frm_input required" size="50" maxlength="100">
            <input type="hidden" name="br_name" value="<?php echo get_text($admin_info['mb_nick'] ?? '') ?>"> <!-- 기존 필드 호환성을 위한 hidden -->
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_name">지점 대표자명</label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($admin_info['mb_name'] ?? '') ?>" id="mb_name" class="frm_input" size="30" maxlength="50">
            <input type="hidden" name="br_ceo_name" value="<?php echo get_text($admin_info['mb_name'] ?? '') ?>"> <!-- 기존 필드 호환성을 위한 hidden -->
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">이메일</label></th>
        <td>
            <input type="text" name="mb_email" value="<?php echo get_text($admin_info['mb_email'] ?? '') ?>" id="mb_email" class="frm_input" size="30">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_tel">전화번호</label></th>
        <td>
            <input type="text" name="mb_tel" value="<?php echo get_text($admin_info['mb_tel'] ?? '') ?>" id="mb_tel" class="frm_input" size="20">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰 번호</label></th>
        <td>
            <input type="text" name="mb_hp" value="<?php echo get_text($admin_info['mb_hp'] ?? '') ?>" id="mb_hp" class="frm_input" size="20">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_zip">주소</label></th>
        <td>
            <input type="text" name="mb_zip" value="<?php echo get_text($admin_info['mb_zip'] ?? '') ?>" id="mb_zip" class="frm_input" size="5">
            <button type="button" class="btn_frmline" onclick="win_zip('fbranch', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3');">주소 검색</button><br>
            <input type="text" name="mb_addr1" value="<?php echo get_text($admin_info['mb_addr1'] ?? '') ?>" id="mb_addr1" class="frm_input" size="60">
            <input type="text" name="mb_addr2" value="<?php echo get_text($admin_info['mb_addr2'] ?? '') ?>" id="mb_addr2" class="frm_input" size="60">
            <input type="text" name="mb_addr3" value="<?php echo get_text($admin_info['mb_addr3'] ?? '') ?>" id="mb_addr3" class="frm_input" size="60">
            <input type="text" name="mb_addr_jibeon" value="<?php echo get_text($admin_info['mb_addr_jibeon'] ?? '') ?>" id="mb_addr_jibeon" class="frm_input" size="60" readonly="readonly">
            <input type="hidden" name="br_address" value="<?php echo get_text($admin_info['mb_addr1'] ?? '') . ' ' . get_text($admin_info['mb_addr2'] ?? '') . ' ' . get_text($admin_info['mb_addr3'] ?? '') ?>"> <!-- 기존 필드 호환성을 위한 hidden -->
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_status">상태</label></th>
        <td>
            <input type="radio" name="br_status" value="1" id="br_status_1" <?php echo ($branch['br_status'] ?? 1) ? 'checked' : '' ?>>
            <label for="br_status_1">활성</label>
            <input type="radio" name="br_status" value="0" id="br_status_0" <?php echo !($branch['br_status'] ?? 1) ? 'checked' : '' ?>>
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
    // Change check to mb_nick for branch name
    if (!f.mb_nick.value) {
        alert("지점명을 입력하세요.");
        f.mb_nick.focus();
        return false;
    }

    var mb_password_field = f.mb_password;
    var mb_password_confirm_field = f.mb_password_confirm;

    // Password validation logic for both new and update (if password provided)
    if (mb_password_field && mb_password_field.value) {
        if (!mb_password_confirm_field || mb_password_field.value !== mb_password_confirm_field.value) {
            alert("비밀번호가 일치하지 않습니다.");
            if (mb_password_confirm_field) mb_password_confirm_field.focus();
            return false;
        }
        if (mb_password_field.value.length < 6) {
            alert("비밀번호는 6자 이상이어야 합니다.");
            mb_password_field.focus();
            return false;
        }
    }
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 