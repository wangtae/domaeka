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

// 본사 관리자가 아닌 경우에만 PHP에서 대리점 목록을 미리 조회
// 본사 관리자의 경우 JavaScript에서 총판 선택에 따라 동적으로 로드
if (!$auth['is_super']) {
    $ag_sql = " SELECT a.ag_id, m.mb_nick AS ag_name FROM dmk_agency a JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id WHERE a.ag_status = 1 ";

    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판에 속한 대리점만 선택 가능
        $ag_sql .= " AND a.dt_id = '".sql_escape_string($auth['mb_id'])."' ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 대리점만 선택 가능
        $ag_sql .= " AND a.ag_id = '".sql_escape_string($auth['ag_id'])."' ";
    }

    $ag_sql .= " ORDER BY m.mb_nick ASC ";
    $ag_result = sql_query($ag_sql);
    while($row = sql_fetch_array($ag_result)) {
        $agencies[] = $row;
    }
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
    <!-- 도매까 계층 선택박스 (NEW) -->
    <tr>
        <th scope="row"><label for="hierarchy_select">소속 계층</label></th>
        <td>
            <?php
            // 도매까 체인 선택박스 포함
            include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
            
            echo dmk_render_chain_select([
                'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY,
                'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                'auto_submit' => false,
                'form_id' => 'fbranch',
                'field_names' => [
                    'distributor' => 'dt_id',
                    'agency' => 'ag_id'
                ],
                'current_values' => [
                    'dt_id' => $branch['dt_id'] ?? $dt_id,
                    'ag_id' => $branch['ag_id'] ?? $ag_id
                ],
                'placeholders' => [
                    'distributor' => '총판 선택',
                    'agency' => '대리점 선택'
                ],
                'show_labels' => false,
                'container_class' => 'dmk-form-select'
            ]);
            ?>
            <span class="frm_info">해당 지점이 소속될 총판과 대리점을 선택합니다.</span>
        </td>
    </tr>
    <?php } // 등록 모드일 때만 끝 ?>
    <?php if ($w == 'u' && $auth['mb_type'] != DMK_MB_TYPE_BRANCH) { // 수정 모드이고 지점 관리자가 아닌 경우만 표시 ?>
    <tr>
        <th scope="row">소속 계층</th>
        <td>
            <?php
            // 도매까 체인 선택박스 포함 (수정 모드)
            include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
            
            echo dmk_render_chain_select([
                'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY,
                'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
                'auto_submit' => false,
                'form_id' => 'fbranch',
                'field_names' => [
                    'distributor' => 'dt_id',
                    'agency' => 'ag_id'
                ],
                'current_values' => [
                    'dt_id' => $branch['dt_id'],
                    'ag_id' => $branch['ag_id']
                ],
                'placeholders' => [
                    'distributor' => '총판 선택',
                    'agency' => '대리점 선택'
                ],
                'show_labels' => false,
                'container_class' => 'dmk-form-select'
            ]);
            ?>
            <span class="frm_info">수정 모드에서는 계층 정보가 읽기 전용으로 표시됩니다.</span>
        </td>
    </tr>
    <?php } ?>
    
    <?php if ($w == 'u' && $auth['mb_type'] == DMK_MB_TYPE_BRANCH) { ?>
    <!-- 지점 관리자의 경우 hidden 필드로만 전송 -->
    <input type="hidden" name="dt_id" value="<?php echo get_text($branch['dt_id']) ?>">
    <input type="hidden" name="ag_id" value="<?php echo get_text($branch['ag_id']) ?>">
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
            <input type="password" name="mb_password" id="mb_password" required class="frm_input required" size="20" maxlength="20" placeholder="6자 이상">
            <span class="frm_info">6자 이상, 아이디와 동일한 비밀번호 금지</span>
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
            <span class="frm_info">변경하려면 입력하세요. 6자 이상, 아이디와 동일한 비밀번호 금지</span>
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
            <input type="text" name="mb_nick" value="<?php echo get_text($member_info['mb_nick']) ?>" id="mb_nick" required class="frm_input required" size="50" maxlength="100" placeholder="예: 강남지점" <?php echo ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) ? 'readonly' : ''; ?>>
            <span class="frm_info">지점의 공식 명칭 (UI 표시에 주로 사용)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_name">회사명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($member_info['mb_name']) ?>" id="mb_name" required class="frm_input required" size="50" maxlength="100">
            <span class="frm_info">회사명을 입력합니다.</span>
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
            <input type="text" name="br_shortcut_code" value="<?php echo get_text($branch['br_shortcut_code']) ?>" id="br_shortcut_code" class="frm_input" size="20" maxlength="20" placeholder="자동생성" <?php echo ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) ? 'readonly' : ''; ?>>
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

    var password = f.mb_password.value;
    var mb_id = f.br_id ? f.br_id.value : '';
    
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

    if (f.mb_password.value != f.mb_password_confirm.value) {
        alert("비밀번호가 일치하지 않습니다.");
        f.mb_password_confirm.focus();
        return false;
    }
    <?php } else { ?>
    // 수정시 비밀번호 변경 체크
    if (f.mb_password.value) {
        var password = f.mb_password.value;
        var mb_id = f.br_id ? f.br_id.value : '';
        
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

// chained-select 라이브러리가 모든 계층 선택 로직을 처리하므로 
// 기존 복잡한 AJAX 코드를 제거했습니다.

</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>