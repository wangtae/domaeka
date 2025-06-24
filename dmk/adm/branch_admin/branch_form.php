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
    $admin_sql = " SELECT mb_id, mb_name, mb_email, mb_phone FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($branch['br_id']) . "' ";
    $admin_info = sql_fetch($admin_sql);

    // 관리자 정보가 없을 경우 기본값으로 초기화
    if (!$admin_info) {
        $admin_info = array(
            'mb_id' => $branch['br_id'], // br_id를 관리자 ID로 사용
            'mb_name' => '',
            'mb_email' => '',
            'mb_phone' => ''
        );
    }
    
} else {
    $html_title .= '등록';
    $w = '';
    
    // 새 지점 ID 생성
    $sql = " SELECT MAX(CAST(SUBSTRING(br_id, 3) AS UNSIGNED)) as max_num FROM dmk_branch WHERE br_id LIKE 'BR%' ";
    $row = sql_fetch($sql);
    $next_num = $row['max_num'] ? $row['max_num'] + 1 : 1;
    $branch_id = 'BR' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
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
        'br_id' => $branch_id,
        'ag_id' => $ag_id,
        'br_name' => '',
        'br_ceo_name' => '',
        'br_phone' => '',
        'br_address' => '',
        'br_shortcut_code' => $shortcut_code,
        'br_status' => 1
    );
    
    $admin_info = array(
        'mb_id' => $branch_id, // br_id를 관리자 ID로 사용
        'mb_name' => '',
        'mb_email' => '',
        'mb_phone' => ''
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
        <th scope="row"><label for="br_name">지점명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="br_name" value="<?php echo get_text($branch['br_name']) ?>" id="br_name" required class="frm_input required" size="50" maxlength="100">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_ceo_name">지점 대표자명</label></th>
        <td>
            <input type="text" name="br_ceo_name" value="<?php echo get_text($branch['br_ceo_name']) ?>" id="br_ceo_name" class="frm_input" size="30" maxlength="50">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_phone">지점 전화번호</label></th>
        <td>
            <input type="text" name="br_phone" value="<?php echo $branch['br_phone'] ?>" id="br_phone" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 02-1234-5678</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_address">지점 주소</label></th>
        <td>
            <input type="text" name="br_address" value="<?php echo get_text($branch['br_address']) ?>" id="br_address" class="frm_input" size="80" maxlength="255">
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
            <input type="text" name="mb_id_display" value="<?php echo $admin_info['mb_id'] ?>" id="mb_id_display" class="frm_input" size="20" readonly>
            <span class="frm_info">관리자 아이디는 지점 ID와 동일합니다.</span>
            <input type="hidden" name="mb_id" value="<?php echo $admin_info['mb_id'] ?>">
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
// 지점 ID 변경 시 관리자 ID도 동일하게 업데이트
document.addEventListener('DOMContentLoaded', function() {
    var br_id_input = document.getElementById('br_id');
    var mb_id_display = document.getElementById('mb_id_display');
    var mb_id_hidden = document.querySelector('input[name="mb_id"]');
    
    if (br_id_input) {
        br_id_input.addEventListener('input', function() {
            mb_id_display.value = this.value;
            mb_id_hidden.value = this.value;
        });
    }
});

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
    
    if (!f.br_name.value) {
        alert("지점명을 입력하세요.");
        f.br_name.focus();
        return false;
    }
    
    // mb_id는 br_id와 동일하므로 별도 체크 불필요
    
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
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 