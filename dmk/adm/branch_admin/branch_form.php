<?php
$sub_menu = "190300"; // 지점관리
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 현재 관리자 권한 정보 가져오기
$auth = dmk_get_admin_auth();

// 메뉴 접근 권한 확인
$w = isset($_GET['w']) ? $_GET['w'] : '';
$br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';

dmk_authenticate_form_access('branch_form', $w, $br_id);

$html_title = '지점 ';
$branch = array();
$member_info = array(); // g5_member 정보를 담을 배열 초기화

if ($w == 'u') {
    $html_title .= '수정';
    
    // dmk_branch 테이블에서 기본 정보 조회
    $sql = " SELECT b.*, a.dt_id FROM dmk_branch b LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id WHERE b.br_id = '" . sql_escape_string($br_id) . "' ";
    $branch = sql_fetch($sql);
    
    if (!$branch) {
        alert('존재하지 않는 지점입니다.');
        exit;
    }

    // g5_member 테이블에서 지점 관리자 상세 정보 조회
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($br_id) . "' ";
    $member_info = sql_fetch($sql);

    if (!$member_info) {
        alert('지점 관리자 회원 정보를 찾을 수 없습니다. 회원 정보를 먼저 확인해주세요.');
        exit;
    }
    
    // 총판 ID 설정 (수정 모드에서 기존 정보 불러오기)
    $dt_id = $branch['dt_id'] ?? '';

} else {
    $html_title .= '등록';
    $w = '';
    
    // 신규 등록 시 기본값 설정
    $dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
    $ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
    
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
        'br_status' => 1,
        'dt_id' => $dt_id
    );

    $member_info = array(
        'mb_id' => '',
        'mb_name' => '',
        'mb_nick' => '',
        'mb_tel' => '',
        'mb_hp' => '',
        'mb_email' => '',
        'mb_zip1' => '',
        'mb_zip2' => '',
        'mb_addr1' => '',
        'mb_addr2' => '',
        'mb_addr3' => '',
        'mb_addr_jibeon' => ''
    );
}

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 카카오 우편번호 서비스 로드
add_javascript(G5_POSTCODE_JS, 0);

// 기존 get_query_string()의 역할을 대체합니다.
// 현재 쿼리 스트링에서 'w'와 'br_id'를 제외한 새로운 쿼리 스트링을 생성합니다.
$current_query_string = $_SERVER['QUERY_STRING'];
parse_str($current_query_string, $params);

// 제외할 파라미터들
$exclude_params = array('w', 'br_id');
foreach ($exclude_params as $param) {
    unset($params[$param]);
}

$qstr = http_build_query($params, '', '&amp;');

// 총판 목록 조회 (드롭다운에 사용) - 본사 관리자만
$distributors = array();
if ($auth['is_super']) {
    $dt_sql = " SELECT dt.dt_id, m.mb_nick AS dt_name FROM dmk_distributor dt JOIN {$g5['member_table']} m ON dt.dt_id = m.mb_id WHERE dt.dt_status = 1 ORDER BY m.mb_nick ASC ";
    $dt_result = sql_query($dt_sql);
    while($row = sql_fetch_array($dt_result)) {
        $distributors[] = $row;
    }
}

// 대리점 목록 조회 (드롭다운에 사용) - 권한 및 선택된 총판에 따라 필터링
$agencies = array();
$ag_sql = " SELECT a.ag_id, m.mb_nick AS ag_name FROM dmk_agency a JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id WHERE a.ag_status = 1 ";

// 권한에 따른 대리점 목록 필터링
if (!$auth['is_super']) {
    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판에 속한 대리점만 선택 가능
        $ag_sql .= " AND a.dt_id = '".sql_escape_string($auth['mb_id'])."' ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 대리점만 선택 가능
        $ag_sql .= " AND a.ag_id = '".sql_escape_string($auth['ag_id'])."' ";
    }
} else { // 최고관리자일 경우 총판 선택에 따라 필터링
    if ($dt_id) {
        $ag_sql .= " AND a.dt_id = '".sql_escape_string($dt_id)."' ";
    }
}

$ag_sql .= " ORDER BY m.mb_nick ASC ";
$ag_result = sql_query($ag_sql);
while($row = sql_fetch_array($ag_result)) {
    $agencies[] = $row;
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
    <?php if ($w != 'u') { // 등록 모드일 때만 ?>
    <?php if ($auth['is_super']) { // 본사 관리자만 총판 선택 박스 노출 ?>
    <tr>
        <th scope="row"><label for="dt_id">소속 총판</label></th>
        <td>
            <select name="dt_id" id="dt_id" class="frm_input" onchange="updateAgencyOptions(this.value);">
                <option value="">총판 선택</option>
                <?php foreach ($distributors as $distributor) { ?>
                    <option value="<?php echo $distributor['dt_id'] ?>" <?php echo ($branch['dt_id'] == $distributor['dt_id']) ? 'selected' : '' ?>>
                        <?php echo get_text($distributor['dt_name']) ?> (<?php echo $distributor['dt_id'] ?>)
                    </option>
                <?php } ?>
            </select>
            <span class="frm_info">해당 지점이 소속될 총판을 선택합니다. (대리점 선택에 영향)</span>
        </td>
    </tr>
    <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { // 총판 관리자는 자신의 총판 ID를 hidden 필드로 전달 ?>
    <tr>
        <th scope="row">소속 총판</th>
        <td>
            <input type="text" value="<?php echo get_text($auth['mb_id']) ?> (<?php echo get_text($auth['mb_name']) ?>)" class="frm_input" readonly>
            <input type="hidden" name="dt_id" value="<?php echo get_text($auth['mb_id']) ?>">
            <span class="frm_info">총판 관리자는 소속 총판을 변경할 수 없습니다.</span>
        </td>
    </tr>
    <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) { // 대리점 관리자는 총판 정보만 표시 ?>
    <tr>
        <th scope="row">소속 총판</th>
        <td>
            <?php
                $parent_dt_id = dmk_get_agency_distributor_id($auth['ag_id']);
                $parent_dt_name = $parent_dt_id ? dmk_get_member_name($parent_dt_id) : '미지정';
            ?>
            <input type="text" value="<?php echo get_text($parent_dt_id) ?> (<?php echo get_text($parent_dt_name) ?>)" class="frm_input" readonly>
            <input type="hidden" name="dt_id" value="<?php echo get_text($parent_dt_id) ?>">
            <span class="frm_info">대리점 관리자는 소속 총판을 변경할 수 없습니다.</span>
        </td>
    </tr>
    <?php } else { // 그 외 (지점 관리자 등)는 총판 필드 숨김 ?>
    <input type="hidden" name="dt_id" value="">
    <?php } ?>

    <tr>
        <th scope="row"><label for="ag_id">소속 대리점<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="ag_id" id="ag_id" required class="frm_input required">
                <option value="">대리점 선택</option>
                <?php foreach ($agencies as $agency) { ?>
                    <option value="<?php echo $agency['ag_id'] ?>" <?php echo ($branch['ag_id'] == $agency['ag_id']) ? 'selected' : '' ?>>
                        <?php echo get_text($agency['ag_name']) ?> (<?php echo $agency['ag_id'] ?>)
                    </option>
                <?php } ?>
            </select>
            <span class="frm_info">해당 지점이 소속될 대리점을 선택합니다.</span>
        </td>
    </tr>
    <?php } // 등록 모드일 때만 끝 ?>
    <?php if ($w == 'u') { // 수정 모드일 때 총판 및 대리점 정보 읽기 전용으로 표시 ?>
    <tr>
        <th scope="row">소속 총판</th>
        <td>
            <?php
                $current_dt_name = dmk_get_member_name($branch['dt_id']);
            ?>
            <input type="text" value="<?php echo get_text($branch['dt_id']) ?> (<?php echo get_text($current_dt_name) ?>)" class="frm_input" readonly>
            <input type="hidden" name="dt_id" value="<?php echo get_text($branch['dt_id']) ?>">
            <span class="frm_info">총판은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">소속 대리점</th>
        <td>
            <?php
                $current_ag_name = dmk_get_member_name($branch['ag_id']);
            ?>
            <input type="text" name="ag_id_display" value="<?php echo get_text($branch['ag_id']) ?> (<?php echo get_text($current_ag_name) ?>)" id="ag_id_display" class="frm_input" readonly>
            <input type="hidden" name="ag_id" value="<?php echo get_text($branch['ag_id']) ?>">
            <span class="frm_info">대리점은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="br_id">지점 ID<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="br_id_display" value="<?php echo $branch['br_id'] ?>" id="br_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">지점 ID는 수정할 수 없습니다.</span>
            <?php } else { ?>
                <input type="text" name="br_id" value="<?php echo $branch['br_id'] ?>" id="br_id" required class="frm_input required" size="20" maxlength="20" placeholder="예: BR001">
                <span class="frm_info">지점을 구분하는 고유 ID입니다. 이 ID는 지점 관리자의 회원 ID로도 사용됩니다.</span>
            <?php } ?>
        </td>
    </tr>
    <?php if ($w != 'u') { // 신규 등록 시에만 비밀번호 입력 ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" required class="frm_input required" size="20" maxlength="20" placeholder="8자 이상">
            <span class="frm_info">영문, 숫자, 특수문자 조합 (8~20자)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" required class="frm_input required" size="20" maxlength="20" placeholder="비밀번호 재입력">
        </td>
    </tr>
    <?php } else { // 수정 시 비밀번호 변경 선택 ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호 변경</label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" class="frm_input" size="20" maxlength="20" placeholder="변경하려면 입력">
            <span class="frm_info">변경하려면 입력하세요. 영문, 숫자, 특수문자 조합 (8~20자)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인</label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" class="frm_input" size="20" maxlength="20" placeholder="비밀번호 변경시 재입력">
            <span class="frm_info">비밀번호 변경 시에만 입력하세요.</span>
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="mb_nick">지점명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_nick" value="<?php echo get_text($member_info['mb_nick']) ?>" id="mb_nick" required class="frm_input required" size="50" maxlength="100" placeholder="예: 강남지점">
            <span class="frm_info">지점의 공식 명칭 (UI 표시에 주로 사용)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_name">회사명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($member_info['mb_name']) ?>" id="mb_name" required class="frm_input required" size="30" maxlength="50" placeholder="(주)스도">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">이메일<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="email" name="mb_email" value="<?php echo get_text($member_info['mb_email']) ?>" id="mb_email" required class="frm_input email required" size="50" maxlength="100" placeholder="example@domain.com">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_tel">전화번호</label></th>
        <td>
            <input type="text" name="mb_tel" value="<?php echo get_text($member_info['mb_tel']) ?>" id="mb_tel" class="frm_input" size="20" maxlength="20" placeholder="02-1234-5678">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td>
            <input type="text" name="mb_hp" value="<?php echo get_text($member_info['mb_hp']) ?>" id="mb_hp" class="frm_input" size="20" maxlength="20" placeholder="010-1234-5678">
        </td>
    </tr>
    <tr>
        <th scope="row">주소</th>
        <td colspan="3" class="td_addr_line">
            <label for="mb_zip" class="sound_only">우편번호</label>
            <input type="text" name="mb_zip" value="<?php echo get_text($member_info['mb_zip1']).get_text($member_info['mb_zip2']) ?>" id="mb_zip" class="frm_input readonly" size="5" maxlength="6">
            <button type="button" class="btn_frmline" onclick="win_zip('fbranch', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');">주소 검색</button><br>
            <input type="text" name="mb_addr1" value="<?php echo get_text($member_info['mb_addr1']) ?>" id="mb_addr1" class="frm_input readonly" size="60">
            <label for="mb_addr1">기본주소</label><br>
            <input type="text" name="mb_addr2" value="<?php echo get_text($member_info['mb_addr2']) ?>" id="mb_addr2" class="frm_input" size="60">
            <label for="mb_addr2">상세주소</label>
            <br>
            <input type="text" name="mb_addr3" value="<?php echo get_text($member_info['mb_addr3']) ?>" id="mb_addr3" class="frm_input" size="60">
            <label for="mb_addr3">참고항목</label>
            <input type="hidden" name="mb_addr_jibeon" value="<?php echo get_text($member_info['mb_addr_jibeon']) ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="br_shortcut_code">단축 URL 코드</label></th>
        <td>
            <input type="text" name="br_shortcut_code" value="<?php echo get_text($branch['br_shortcut_code']) ?>" id="br_shortcut_code" class="frm_input" size="20" maxlength="20" placeholder="자동생성">
            <span class="frm_info">주문 페이지 단축 URL에 사용됩니다. 비워두면 자동 생성됩니다.</span>
        </td>
    </tr>
    <?php if ($w == 'u' && $auth['mb_type'] != DMK_MB_TYPE_BRANCH) { // 지점 관리자가 아닌 경우에만 상태 변경 가능 ?>
    <tr>
        <th scope="row"><label for="br_status">지점 상태</label></th>
        <td>
            <select name="br_status" id="br_status" class="frm_input">
                <option value="1" <?php echo ($branch['br_status'] == 1) ? 'selected' : '' ?>>활성</option>
                <option value="0" <?php echo ($branch['br_status'] == 0) ? 'selected' : '' ?>>비활성</option>
            </select>
            <span class="frm_info">비활성 상태에서는 주문 페이지 접근이 제한됩니다.</span>
        </td>
    </tr>
    <?php } ?>
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
    // 본사 관리자가 총판을 선택하지 않았을 경우 경고
    <?php if ($auth['is_super']) { ?>
    if (!f.dt_id.value) {
        alert("소속 총판을 선택하세요.");
        f.dt_id.focus();
        return false;
    }
    <?php } ?>

    <?php if ($w != 'u') { // 신규 등록시에만 유효성 검사 ?>
    if (!f.ag_id.value) {
        alert("소속 대리점을 선택하세요.");
        f.ag_id.focus();
        return false;
    }
    <?php } ?>

    if (!f.br_id.value) {
        alert("지점 ID를 입력하세요.");
        f.br_id.focus();
        return false;
    }

    // 지점 ID 형식 검사 (영문, 숫자, 언더스코어만 허용)
    if (!/^[a-zA-Z0-9_]{3,20}$/.test(f.br_id.value)) {
        alert("지점 ID는 영문, 숫자, 언더스코어만 사용 가능하며 3~20자여야 합니다.");
        f.br_id.focus();
        return false;
    }

    // 신규 등록시 비밀번호 체크
    <?php if ($w != 'u') { ?>
    if (!f.mb_password.value) {
        alert("비밀번호를 입력하세요.");
        f.mb_password.focus();
        return false;
    }

    if (f.mb_password.value.length < 8) {
        alert("비밀번호는 8자 이상이어야 합니다.");
        f.mb_password.focus();
        return false;
    }

    if (f.mb_password.value != f.mb_password_confirm.value) {
        alert("비밀번호가 일치하지 않습니다.");
        f.mb_password_confirm.focus();
        return false;
    }
    <?php } else { ?>
    // 수정시 비밀번호 변경 체크
    if (f.mb_password.value && f.mb_password.value.length < 8) {
        alert("비밀번호는 8자 이상이어야 합니다.");
        f.mb_password.focus();
        return false;
    }

    if (f.mb_password.value && f.mb_password.value != f.mb_password_confirm.value) {
        alert("비밀번호가 일치하지 않습니다.");
        f.mb_password_confirm.focus();
        return false;
    }
    <?php } ?>

    if (!f.mb_nick.value) {
        alert("지점명을 입력하세요.");
        f.mb_nick.focus();
        return false;
    }

    if (!f.mb_name.value) {
        alert("회사명을 입력하세요.");
        f.mb_name.focus();
        return false;
    }

    if (!f.mb_email.value) {
        alert("이메일을 입력하세요.");
        f.mb_email.focus();
        return false;
    }

    // 이메일 형식 검사
    var email_pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!email_pattern.test(f.mb_email.value)) {
        alert("올바른 이메일 형식이 아닙니다.");
        f.mb_email.focus();
        return false;
    }

    return true;
}

// 대리점 옵션을 업데이트하는 함수
function updateAgencyOptions(dt_id) {
    console.log("updateAgencyOptions called with dt_id:", dt_id);
    var agencySelect = document.getElementById('ag_id');
    agencySelect.innerHTML = '<option value="">대리점 선택</option>'; // 기존 옵션 초기화

    if (dt_id) {
        // AJAX 요청으로 해당 총판에 속한 대리점 목록 가져오기
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '../shop_admin/ajax_get_dmk_owner_ids.php?owner_type=agency&parent_id=' + dt_id, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                console.log("AJAX Response Text:", xhr.responseText);
                var agencies = JSON.parse(xhr.responseText);
                console.log("Parsed Agencies:", agencies);
                agencies.forEach(function(agency) {
                    var option = document.createElement('option');
                    option.value = agency.id;
                    option.textContent = agency.name + ' (' + agency.id + ')';
                    agencySelect.appendChild(option);
                    console.log("Appended option:", option);
                });
                // 현재 선택된 대리점 값을 설정 (수정 모드일 경우)
                var currentAgId = '<?php echo $branch['ag_id'] ?? $ag_id ?>';
                if (currentAgId) {
                    agencySelect.value = currentAgId;
                    console.log("Set agencySelect.value to:", agencySelect.value);
                }
            }
        };
        xhr.send();
    }
}

// 페이지 로드 시 또는 총판 선택 시 대리점 목록을 초기화하고 현재 선택된 총판에 따라 업데이트
document.addEventListener('DOMContentLoaded', function() {
    // 신규 등록 모드이고 dt_id가 설정되어 있지 않다면, 대리점 선택 박스를 비활성화
    <?php if ($w != 'u' && !$dt_id && $auth['is_super']) { ?>
        document.getElementById('ag_id').disabled = true;
        console.log("ag_id disabled.");
    <?php } ?>
});

// 총판 선택 박스가 변경될 때마다 대리점 선택 박스 활성화
document.getElementById('dt_id')?.addEventListener('change', function() {
    var selectedDtId = this.value;
    if (selectedDtId) {
        document.getElementById('ag_id').disabled = false;
        updateAgencyOptions(selectedDtId);
    } else {
        document.getElementById('ag_id').innerHTML = '<option value="">대리점 선택</option>';
        document.getElementById('ag_id').disabled = true;
    }
});

</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 