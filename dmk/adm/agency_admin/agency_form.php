<?php
$sub_menu = "190200";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
// 관리자 액션 로깅 라이브러리 포함 (필요할 경우)
include_once(G5_DMK_PATH . "/adm/lib/admin.log.lib.php"); 

// 현재 관리자 권한 정보 가져오기
$auth = dmk_get_admin_auth();

// 메뉴 접근 권한 확인
// dmk_authenticate_form_access 함수를 사용하여 통합 권한 체크
$w = isset($_GET['w']) ? $_GET['w'] : '';
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';

dmk_authenticate_form_access('agency_form', $w, $ag_id);

$html_title = '대리점 ';
$agency = array();
$member_info = array(); // g5_member 정보를 담을 배열 초기화

if ($w == 'u') {
    $html_title .= '수정';
    
    // dmk_agency 테이블에서 기본 정보 조회
    $sql = " SELECT * FROM dmk_agency WHERE ag_id = '" . sql_escape_string($ag_id) . "' ";
    $agency = sql_fetch($sql);
    
    if (!$agency) {
        alert('존재하지 않는 대리점입니다.');
        exit;
    }

    // g5_member 테이블에서 대리점 관리자 상세 정보 조회
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($ag_id) . "' ";
    $member_info = sql_fetch($sql);

    if (!$member_info) {
        alert('대리점 관리자 회원 정보를 찾을 수 없습니다. 회원 정보를 먼저 확인해주세요.');
        exit;
    }
    
} else {
    $html_title .= '등록';
    $w = '';
    
    // 신규 등록 시 기본값 설정
    $agency = array(
        'ag_id' => '',
        'dt_id' => '', // 총판 ID
        'ag_status' => 1
    );
    
    // 총판 관리자의 경우 자신의 총판 ID를 기본값으로 설정
    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !$auth['is_super']) {
        $agency['dt_id'] = $auth['dt_id'];
    }

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
// 현재 쿼리 스트링에서 'w'와 'ag_id'를 제외한 새로운 쿼리 스트링을 생성합니다.
$current_query_string = $_SERVER['QUERY_STRING'];
parse_str($current_query_string, $params);

// 제외할 파라미터들
$exclude_params = array('w', 'ag_id');
foreach ($exclude_params as $param) {
    unset($params[$param]);
}

$qstr = http_build_query($params, '', '&amp;');

// 총판 목록 조회 (드롭다운에 사용) - 권한에 따라 필터링
$distributors = array();
$dt_sql = " SELECT dt.dt_id, m.mb_name, m.mb_nick FROM dmk_distributor dt JOIN {$g5['member_table']} m ON dt.dt_id = m.mb_id WHERE dt.dt_status = 1 ";

// 권한에 따른 총판 목록 필터링
if (!$auth['is_super']) {
    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판만 선택 가능
        $dt_sql .= " AND dt.dt_id = '".sql_escape_string($auth['dt_id'])."' ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신이 속한 총판만 표시 (수정 시에만 해당)
        $dt_sql .= " AND dt.dt_id = '".sql_escape_string($auth['dt_id'])."' ";
    }
}

$dt_sql .= " ORDER BY m.mb_name ASC ";
$dt_result = sql_query($dt_sql);
while($row = sql_fetch_array($dt_result)) {
    $distributors[] = $row;
}
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
    <?php if ($w != 'u') { // 등록 모드일 때만 ?>
    <tr>
        <th scope="row"><label for="dt_id">소속 총판</label></th>
        <td>
            <?php if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !$auth['is_super']) { ?>
                <!-- 총판 관리자는 자신의 총판만 선택 가능하므로 읽기 전용으로 표시 -->
                <?php 
                    $current_dt_name = dmk_get_member_name($auth['dt_id']);
                ?>
                <input type="text" value="<?php echo get_text($current_dt_name) ?>" class="frm_input" readonly>
                <input type="hidden" name="dt_id" value="<?php echo get_text($auth['dt_id']) ?>">
                <span class="frm_info">총판 관리자는 자신의 총판에만 대리점을 등록할 수 있습니다.</span>
            <?php } else { ?>
                <!-- 도매까 체인 선택박스 사용 (NEW) -->
                <?php
                include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                
                echo dmk_render_chain_select([
                    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY,
                    'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                    'auto_submit' => false,
                    'form_id' => 'fagency',
                    'field_names' => [
                        'distributor' => 'dt_id'
                    ],
                    'current_values' => [
                        'dt_id' => $agency['dt_id']
                    ],
                    'placeholders' => [
                        'distributor' => '총판 선택'
                    ],
                    'show_labels' => false,
                    'container_class' => 'dmk-form-select'
                ]);
                ?>
                <span class="frm_info">해당 대리점이 소속될 총판을 선택합니다.</span>
            <?php } ?>
        </td>
    </tr>
    <?php } // 등록 모드일 때만 끝 ?>
    <?php if ($w == 'u') { // 수정 모드일 때 총판 정보 처리 ?>
    <tr>
        <th scope="row"><label for="dt_id">소속 총판</label></th>
        <td>
            <?php
            // 수정 시 $agency['dt_id']가 비어있을 경우 처리
            // 현재 로그인한 관리자의 권한을 확인하여 적절한 총판 ID를 설정하거나 선택 UI 제공
            if (empty($agency['dt_id'])) {
                if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !$auth['is_super']) {
                    // 총판 관리자가 자신의 총판에 속하지 않은 대리점을 수정할 때, 자신의 총판 ID를 기본으로 설정
                    $agency['dt_id'] = $auth['mb_id'];
                }
            }

            if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !$auth['is_super']) { ?>
                <!-- 총판 관리자는 자신의 총판만 선택 가능하므로 읽기 전용으로 표시 -->
                <?php
                    $display_dt_id = get_text($agency['dt_id']);
                    $display_dt_name = dmk_get_member_name($agency['dt_id']);
                ?>
                <input type="text" value="<?php echo $display_dt_name ?>" class="frm_input" readonly>
                <input type="hidden" name="dt_id" value="<?php echo $display_dt_id ?>">

                <span class="frm_info">총판 관리자는 자신의 총판에만 대리점을 수정할 수 있습니다.</span>
            <?php } else { // 최고 관리자 또는 다른 유형의 관리자 ?>
                <!-- 도매까 체인 선택박스 사용 (NEW) -->
                <?php
                include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                
                echo dmk_render_chain_select([
                    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY,
                    'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
                    'auto_submit' => false,
                    'form_id' => 'fagency',
                    'field_names' => [
                        'distributor' => 'dt_id'
                    ],
                    'current_values' => [
                        'dt_id' => $agency['dt_id']
                    ],
                    'placeholders' => [
                        'distributor' => '총판 선택'
                    ],
                    'show_labels' => false,
                    'container_class' => 'dmk-form-select'
                ]);
                ?>
                <span class="frm_info">해당 대리점이 소속될 총판을 선택합니다.</span>
            <?php } ?>
        </td>
    </tr>
    <?php } // 수정 모드일 때 총판 정보 처리 끝 ?>
    <tr>
        <th scope="row"><label for="ag_id">대리점 ID<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="ag_id_display" value="<?php echo $agency['ag_id'] ?>" id="ag_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">대리점 ID는 수정할 수 없습니다.</span>
            <?php } else { ?>
                <input type="text" name="ag_id" value="<?php echo $agency['ag_id'] ?>" id="ag_id" required class="frm_input required" size="20" maxlength="20">
                <span class="frm_info">대리점을 구분하는 고유 ID입니다. (예: AG001)</span>
                <span class="frm_info">이 ID는 대리점 관리자의 회원 ID로도 사용됩니다.</span>
            <?php } ?>
        </td>
    </tr>
    <?php if ($w != 'u') { // 신규 등록 시에만 비밀번호 입력 ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" required class="frm_input required" size="20" maxlength="20">
            <span class="frm_info">6자 이상, 아이디와 동일한 비밀번호 금지</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" required class="frm_input required" size="20" maxlength="20">
        </td>
    </tr>
    <?php } else { // 수정 시 비밀번호 변경 선택 ?>
    <tr>
        <th scope="row"><label for="mb_password">비밀번호 변경</label></th>
        <td>
            <input type="password" name="mb_password" id="mb_password" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">변경하려면 입력하세요. 6자 이상, 아이디와 동일한 비밀번호 금지</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_password_confirm">비밀번호 확인</label></th>
        <td>
            <input type="password" name="mb_password_confirm" id="mb_password_confirm" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">비밀번호 변경 시에만 입력하세요.</span>
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="mb_name">대리점 이름<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($member_info['mb_name']) ?>" id="mb_name" required class="frm_input required" size="50" maxlength="100">
            <span class="frm_info">대리점의 공식 명칭 (UI 표시에 주로 사용)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_nick">회사명<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_nick" value="<?php echo get_text($member_info['mb_nick']) ?>" id="mb_nick" required class="frm_input required" size="30" maxlength="50">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">이메일<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_email" value="<?php echo get_text($member_info['mb_email']) ?>" id="mb_email" required class="frm_input email required" size="50" maxlength="100">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_tel">전화번호</label></th>
        <td>
            <input type="text" name="mb_tel" value="<?php echo get_text($member_info['mb_tel']) ?>" id="mb_tel" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 02-1234-5678</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td>
            <input type="text" name="mb_hp" value="<?php echo get_text($member_info['mb_hp']) ?>" id="mb_hp" class="frm_input" size="20" maxlength="20">
            <span class="frm_info">예: 010-1234-5678</span>
        </td>
    </tr>
    <tr>
        <th scope="row">주소</th>
        <td colspan="3" class="td_addr_line">
            <label for="mb_zip" class="sound_only">우편번호</label>
            <input type="text" name="mb_zip" value="<?php echo get_text($member_info['mb_zip1']).get_text($member_info['mb_zip2']) ?>" id="mb_zip" class="frm_input readonly" size="5" maxlength="6">
            <button type="button" class="btn_frmline" onclick="win_zip('fagency', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');">주소 검색</button><br>
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
    <?php if ($auth['is_super'] || $ag_id != $auth['ag_id']) { ?>
    <tr>
        <th scope="row"><label for="ag_status">대리점 상태</label></th>
        <td>
            <select name="ag_status" id="ag_status">
                <option value="1" <?php echo (($agency['ag_status'] ?? 1) == 1) ? 'selected' : ''; ?>>활성</option>
                <option value="0" <?php echo (($agency['ag_status'] ?? 1) == 0) ? 'selected' : ''; ?>>비활성</option>
            </select>
        </td>
    </tr>
    <?php } else { ?>
    <input type="hidden" name="ag_status" value="<?php echo ($agency['ag_status'] ?? 1); ?>">
    <?php } ?>
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
    // 대리점 ID는 신규 등록시에만 필수
    <?php if ($w == '') { ?>
    if (!f.ag_id.value) {
        alert("대리점 ID를 입력하세요.");
        f.ag_id.focus();
        return false;
    }
    
    // 총판 선택 필수 (최고 관리자의 경우에만)
    <?php if ($auth['is_super']) { ?>
    if (!f.dt_id.value) {
        alert("소속 총판을 선택하세요.");
        f.dt_id.focus();
        return false;
    }
    <?php } ?>
    <?php } ?>

    // 대리점 이름 (mb_name) 필수
    if (!f.mb_name.value) {
        alert("대리점 이름을 입력하세요.");
        f.mb_name.focus();
        return false;
    }
    
    // 회사명 (mb_nick) 필수
    if (!f.mb_nick.value) {
        alert("회사명을 입력하세요.");
        f.mb_nick.focus();
        return false;
    }
    
    // 이메일 (mb_email) 필수
    if (!f.mb_email.value) {
        alert("이메일을 입력하세요.");
        f.mb_email.focus();
        return false;
    }
    
    // 비밀번호는 신규 등록시 필수, 수정시 입력된 경우에만 유효성 검사
    <?php if ($w == '') { ?>
    if (!f.mb_password.value) {
        alert("비밀번호를 입력하세요.");
        f.mb_password.focus();
        return false;
    }
    <?php } ?>
    
    if (f.mb_password.value) { // 비밀번호가 입력된 경우에만 검사
        if (f.mb_password.value !== f.mb_password_confirm.value) {
            alert("비밀번호가 일치하지 않습니다.");
            f.mb_password_confirm.focus();
            return false;
        }
        
        var password = f.mb_password.value;
        var mb_id = f.ag_id ? f.ag_id.value : '';
        
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
    
    // 주소는 선택사항이므로 필수 검사 제거
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 