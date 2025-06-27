<?php
$sub_menu = "190600";
include_once './_common.php';

// 현재 관리자 권한 정보 가져오기 (이미 _common.php에서 가져왔으므로 재사용)
// $auth = dmk_get_admin_auth(); // 이미 _common.php에서 처리됨

// 메뉴 접근 권한 확인
$w = isset($_GET['w']) ? $_GET['w'] : '';
$mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';

// 서브관리자 등록/수정 권한 확인 (이미 _common.php에서 처리됨)
// if (!$auth['is_super'] && $auth['mb_type'] != DMK_MB_TYPE_DISTRIBUTOR && $auth['mb_type'] != DMK_MB_TYPE_AGENCY) {
//     alert('서브관리자 등록/수정 권한이 없습니다.');
//     exit;
// }

$html_title = '서브관리자 ';
$member_info = array();

if ($w == 'u') {
    $html_title .= '수정';
    
    // g5_member 테이블에서 관리자 정보 조회
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "' ";
    $member_info = sql_fetch($sql);
    
    if (!$member_info) {
        alert('존재하지 않는 서브관리자입니다.');
        exit;
    }

    // 권한 확인: 본사 관리자가 아니면 자신의 하위 관리자만 수정 가능
    if (!$auth['is_super']) {
        $can_edit = false;
        
        if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            // 총판 관리자는 자신의 총판에 속한 대리점/지점 관리자만 수정 가능
            if ($member_info['dmk_mb_type'] == DMK_MB_TYPE_AGENCY) {
                $ag_sql = " SELECT dt_id FROM dmk_agency WHERE ag_id = '" . sql_escape_string($member_info['dmk_ag_id']) . "' ";
                $ag_result = sql_fetch($ag_sql);
                $can_edit = ($ag_result['dt_id'] == $auth['mb_id']);
            } else if ($member_info['dmk_mb_type'] == DMK_MB_TYPE_BRANCH) {
                $br_sql = " SELECT a.dt_id FROM dmk_branch b LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id WHERE b.br_id = '" . sql_escape_string($member_info['dmk_br_id']) . "' ";
                $br_result = sql_fetch($br_sql);
                $can_edit = ($br_result['dt_id'] == $auth['mb_id']);
            }
        } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            // 대리점 관리자는 자신의 대리점에 속한 지점 관리자만 수정 가능
            if ($member_info['dmk_mb_type'] == DMK_MB_TYPE_BRANCH) {
                $can_edit = ($member_info['dmk_ag_id'] == $auth['ag_id']);
            }
        }
        
        if (!$can_edit) {
            alert('수정 권한이 없습니다.');
            exit;
        }
    }
    
    // 소속 정보 설정 (수정 모드에서 기존 정보 불러오기)
    $dt_id = $member_info['dmk_dt_id'] ?? '';
    $ag_id = $member_info['dmk_ag_id'] ?? '';
    $br_id = $member_info['dmk_br_id'] ?? '';

} else {
    $html_title .= '등록';
    $w = '';
    
    // 신규 등록 시 기본값 설정
    $dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
    $ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
    $br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';
    
    $member_info = array(
        'mb_id' => '',
        'mb_name' => '',
        'mb_nick' => '',
        'mb_email' => '',
        'mb_hp' => '',
        'mb_memo' => '',
        'dmk_mb_type' => '',
        'dmk_dt_id' => $dt_id,
        'dmk_ag_id' => $ag_id,
        'dmk_br_id' => $br_id
    );
}

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 기존 get_query_string()의 역할을 대체합니다.
// 현재 쿼리 스트링에서 'w'와 'mb_id'를 제외한 새로운 쿼리 스트링을 생성합니다.
$current_query_string = $_SERVER['QUERY_STRING'];
parse_str($current_query_string, $params);

// 제외할 파라미터들
$exclude_params = array('w', 'mb_id');
foreach ($exclude_params as $param) {
    unset($params[$param]);
}

$qstr = http_build_query($params, '', '&amp;');

// 생성 가능한 관리자 유형 옵션
$admin_type_options = array();
if ($auth['is_super']) {
    $admin_type_options = array(
        DMK_MB_TYPE_DISTRIBUTOR => '총판 관리자',
        DMK_MB_TYPE_AGENCY => '대리점 관리자', 
        DMK_MB_TYPE_BRANCH => '지점 관리자'
    );
} elseif ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    $admin_type_options = array(
        DMK_MB_TYPE_AGENCY => '대리점 관리자', 
        DMK_MB_TYPE_BRANCH => '지점 관리자'
    );
} elseif ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    $admin_type_options = array(
        DMK_MB_TYPE_BRANCH => '지점 관리자'
    );
}

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

// 지점 목록 조회 (드롭다운에 사용) - 권한 및 선택된 대리점에 따라 필터링
$branches = array();

// 본사 관리자가 아닌 경우에만 PHP에서 지점 목록을 미리 조회
// 본사 관리자의 경우 JavaScript에서 대리점 선택에 따라 동적으로 로드
if (!$auth['is_super']) {
    $br_sql = " SELECT b.br_id, m.mb_nick AS br_name FROM dmk_branch b JOIN {$g5['member_table']} m ON b.br_id = m.mb_id WHERE b.br_status = 1 ";

    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판에 속한 지점만 선택 가능
        $br_sql .= " AND EXISTS (SELECT 1 FROM dmk_agency a WHERE a.ag_id = b.ag_id AND a.dt_id = '".sql_escape_string($auth['mb_id'])."') ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 대리점에 속한 지점만 선택 가능
        $br_sql .= " AND b.ag_id = '".sql_escape_string($auth['ag_id'])."' ";
    }

    $br_sql .= " ORDER BY m.mb_nick ASC ";
    $br_result = sql_query($br_sql);
    while($row = sql_fetch_array($br_result)) {
        $branches[] = $row;
    }
}
?>

<form name="fmember" id="fmember" action="./admin_form_update.php" onsubmit="return fmember_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">
<?php if ($w == 'u') { ?>
<input type="hidden" name="mb_id" value="<?php echo $member_info['mb_id'] ?>">
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
                    <option value="<?php echo $distributor['dt_id'] ?>" <?php echo ($member_info['dmk_dt_id'] == $distributor['dt_id']) ? 'selected' : '' ?>>
                        <?php echo get_text($distributor['dt_name']) ?> (<?php echo $distributor['dt_id'] ?>)
                    </option>
                <?php } ?>
            </select>
            <span class="frm_info">해당 관리자가 소속될 총판을 선택합니다. (대리점 선택에 영향)</span>
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
        <th scope="row"><label for="ag_id">소속 대리점</label></th>
        <td>
            <select name="ag_id" id="ag_id" class="frm_input">
                <?php if ($auth['is_super']) { ?>
                    <option value="">먼저 총판을 선택하세요</option>
                <?php } else { ?>
                    <option value="">대리점 선택</option>
                    <?php foreach ($agencies as $agency) { ?>
                        <option value="<?php echo $agency['ag_id'] ?>" <?php echo ($member_info['dmk_ag_id'] == $agency['ag_id']) ? 'selected' : '' ?>>
                            <?php echo get_text($agency['ag_name']) ?> (<?php echo $agency['ag_id'] ?>)
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>
            <span class="frm_info">해당 관리자가 소속될 대리점을 선택합니다. (지점 관리자 등록 시 필요)</span>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="br_id">소속 지점</label></th>
        <td>
            <select name="br_id" id="br_id" class="frm_input">
                <?php if ($auth['is_super']) { ?>
                    <option value="">먼저 대리점을 선택하세요</option>
                <?php } else { ?>
                    <option value="">지점 선택</option>
                    <?php foreach ($branches as $branch) { ?>
                        <option value="<?php echo $branch['br_id'] ?>" <?php echo ($member_info['dmk_br_id'] == $branch['br_id']) ? 'selected' : '' ?>>
                            <?php echo get_text($branch['br_name']) ?> (<?php echo $branch['br_id'] ?>)
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>
            <span class="frm_info">해당 관리자가 소속될 지점을 선택합니다. (지점 관리자 등록 시 필요)</span>
        </td>
    </tr>
    <?php } // 등록 모드일 때만 끝 ?>
    <?php if ($w == 'u') { // 수정 모드일 때 소속 정보 읽기 전용으로 표시 ?>
    <tr>
        <th scope="row">소속 총판</th>
        <td>
            <?php
                $current_dt_name = dmk_get_member_name($member_info['dmk_dt_id']);
            ?>
            <input type="text" value="<?php echo get_text($member_info['dmk_dt_id']) ?> (<?php echo get_text($current_dt_name) ?>)" class="frm_input" readonly>
            <input type="hidden" name="dt_id" value="<?php echo get_text($member_info['dmk_dt_id']) ?>">
            <span class="frm_info">총판은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">소속 대리점</th>
        <td>
            <?php
                $current_ag_name = $member_info['dmk_ag_id'] ? dmk_get_member_name($member_info['dmk_ag_id']) : '미지정';
            ?>
            <input type="text" name="ag_id_display" value="<?php echo get_text($member_info['dmk_ag_id']) ?> (<?php echo get_text($current_ag_name) ?>)" id="ag_id_display" class="frm_input" readonly>
            <input type="hidden" name="ag_id" value="<?php echo get_text($member_info['dmk_ag_id']) ?>">
            <span class="frm_info">대리점은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row">소속 지점</th>
        <td>
            <?php
                $current_br_name = $member_info['dmk_br_id'] ? dmk_get_member_name($member_info['dmk_br_id']) : '미지정';
            ?>
            <input type="text" name="br_id_display" value="<?php echo get_text($member_info['dmk_br_id']) ?> (<?php echo get_text($current_br_name) ?>)" id="br_id_display" class="frm_input" readonly>
            <input type="hidden" name="br_id" value="<?php echo get_text($member_info['dmk_br_id']) ?>">
            <span class="frm_info">지점은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row"><label for="mb_id">관리자 ID<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="mb_id_display" value="<?php echo $member_info['mb_id'] ?>" id="mb_id_display" class="frm_input" size="20" readonly>
                <span class="frm_info">관리자 ID는 수정할 수 없습니다.</span>
            <?php } else { ?>
                <input type="text" name="mb_id" value="<?php echo $member_info['mb_id'] ?>" id="mb_id" required class="frm_input required" size="20" maxlength="20" placeholder="예: admin001">
                <span class="frm_info">관리자를 구분하는 고유 ID입니다.</span>
            <?php } ?>
        </td>
    </tr>
    <?php if ($w != 'u') { // 신규 등록시에만 유효성 검사 ?>
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
    <?php } else { ?>
    <!-- 수정시 비밀번호 변경 체크 -->
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
        <th scope="row"><label for="mb_name">이름<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_name" value="<?php echo get_text($member_info['mb_name']) ?>" id="mb_name" required class="frm_input required" size="50" maxlength="100">
            <span class="frm_info">관리자의 실명을 입력합니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_nick">닉네임<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="text" name="mb_nick" value="<?php echo get_text($member_info['mb_nick']) ?>" id="mb_nick" required class="frm_input required" size="50" maxlength="100" placeholder="예: 관리자001">
            <span class="frm_info">관리자의 표시명 (UI 표시에 주로 사용)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_email">이메일<strong class="sound_only">필수</strong></label></th>
        <td>
            <input type="email" name="mb_email" value="<?php echo get_text($member_info['mb_email']) ?>" id="mb_email" required class="frm_input email required" size="50" maxlength="100" placeholder="example@domain.com">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td>
            <input type="text" name="mb_hp" value="<?php echo get_text($member_info['mb_hp']) ?>" id="mb_hp" class="frm_input" size="20" maxlength="20" placeholder="010-1234-5678">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="dmk_mb_type">관리자 유형<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($w == 'u') { ?>
                <input type="text" name="dmk_mb_type_display" value="<?php 
                    switch($member_info['dmk_mb_type']) {
                        case DMK_MB_TYPE_DISTRIBUTOR: echo '총판 관리자'; break;
                        case DMK_MB_TYPE_AGENCY: echo '대리점 관리자'; break;
                        case DMK_MB_TYPE_BRANCH: echo '지점 관리자'; break;
                        default: echo '미지정';
                    }
                ?>" id="dmk_mb_type_display" class="frm_input" readonly>
                <input type="hidden" name="dmk_mb_type" value="<?php echo $member_info['dmk_mb_type'] ?>">
                <span class="frm_info">관리자 유형은 수정할 수 없습니다.</span>
            <?php } else { ?>
                <select name="dmk_mb_type" id="dmk_mb_type" required class="frm_input required">
                    <option value="">관리자 유형 선택</option>
                    <?php foreach ($admin_type_options as $value => $text) { ?>
                        <option value="<?php echo $value ?>" <?php echo ($member_info['dmk_mb_type'] == $value) ? 'selected' : '' ?>>
                            <?php echo $text ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="frm_info">등록할 관리자의 유형을 선택합니다.</span>
            <?php } ?>
            <input type="hidden" name="mb_level" id="mb_level" value="<?php echo $member_info['mb_level'] ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_memo">메모</label></th>
        <td>
            <textarea name="mb_memo" id="mb_memo" rows="5" class="frm_textbox"><?php echo $member_info['mb_memo'] ?></textarea>
            <span class="frm_info">관리자에 대한 추가 정보나 메모를 입력합니다.</span>
        </td>
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
jQuery(document).ready(function() {
    jQuery('#dmk_mb_type').on('change', function() {
        var dmkType = jQuery(this).val();
        var mbLevel = jQuery('#mb_level');
        
        switch(dmkType) {
            case '<?php echo DMK_MB_TYPE_DISTRIBUTOR; ?>': // 총판
                mbLevel.val('8');
                break;
            case '<?php echo DMK_MB_TYPE_AGENCY; ?>': // 대리점
                mbLevel.val('6');
                break;
            case '<?php echo DMK_MB_TYPE_BRANCH; ?>': // 지점
                mbLevel.val('4');
                break;
            default:
                mbLevel.val('');
        }
    });
});

// jQuery를 사용하여 AJAX 요청을 처리하는 함수
function updateAgencyOptions(dt_id, selected_ag_id = '') {
    console.log("=== updateAgencyOptions 시작 ===");
    console.log("dt_id:", dt_id, "selected_ag_id:", selected_ag_id);
    
    var agencySelect = jQuery('#ag_id');
    var branchSelect = jQuery('#br_id');
    
    if (agencySelect.length === 0) {
        console.error("ag_id 선택박스를 찾을 수 없습니다.");
        return;
    }
    
    // 기존 옵션 완전히 제거하고 기본 옵션 추가
    agencySelect.empty().append('<option value="">대리점 선택</option>');
    branchSelect.empty().append('<option value="">먼저 대리점을 선택하세요</option>');

    if (dt_id) {
        var ajaxUrl = '<?php echo G5_URL; ?>/dmk/adm/_ajax/get_agencies.php';
        console.log("AJAX URL:", ajaxUrl);
        
        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                dt_id: dt_id
            },
            beforeSend: function() {
                console.log("AJAX 요청 전송 중...");
                agencySelect.find('option[value=""]').text('로딩 중...');
            },
            success: function(response) {
                console.log("=== AJAX 성공 응답 ===");
                console.log("전체 응답:", response);
                
                agencySelect.empty().append('<option value="">대리점 선택</option>');
                
                if (response.success && response.data) {
                    console.log("응답 데이터:", response.data);
                    console.log("데이터 개수:", response.data.length);
                    
                    if (response.data.length === 0) {
                        agencySelect.find('option[value=""]').text('해당 총판에 속한 대리점이 없습니다');
                        console.log("대리점 데이터가 없습니다.");
                    } else {
                        jQuery.each(response.data, function(index, agency) {
                            console.log("대리점 추가:", agency.id, agency.name);
                            var option = jQuery('<option></option>')
                                .attr('value', agency.id)
                                .text(agency.name + ' (' + agency.id + ')');
                            agencySelect.append(option);
                        });
                        
                        // 현재 선택된 대리점 값을 설정
                        if (selected_ag_id) {
                            agencySelect.val(selected_ag_id);
                            console.log("선택된 대리점 설정:", selected_ag_id);
                        }
                    }
                } else {
                    console.error("AJAX 요청 실패:", response.message);
                    agencySelect.find('option[value=""]').text('대리점 로드 실패');
                }
            },
            error: function(xhr, status, error) {
                console.log("=== AJAX 오류 ===");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
                
                agencySelect.empty().append('<option value="">네트워크 오류</option>');
            }
        });
    } else {
        console.log("dt_id가 비어있어 AJAX 요청을 하지 않습니다.");
        agencySelect.empty().append('<option value="">먼저 총판을 선택하세요</option>');
    }
}

// 대리점 선택 시 지점 목록 업데이트
function updateBranchOptions(ag_id, selected_br_id = '') {
    console.log("=== updateBranchOptions 시작 ===");
    console.log("ag_id:", ag_id, "selected_br_id:", selected_br_id);
    
    var branchSelect = jQuery('#br_id');
    
    if (branchSelect.length === 0) {
        console.error("br_id 선택박스를 찾을 수 없습니다.");
        return;
    }
    
    // 기존 옵션 완전히 제거하고 기본 옵션 추가
    branchSelect.empty().append('<option value="">지점 선택</option>');

    if (ag_id) {
        var ajaxUrl = '<?php echo G5_URL; ?>/dmk/adm/_ajax/get_branches.php';
        console.log("AJAX URL:", ajaxUrl);
        
        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ag_id: ag_id
            },
            beforeSend: function() {
                console.log("AJAX 요청 전송 중...");
                branchSelect.find('option[value=""]').text('로딩 중...');
            },
            success: function(response) {
                console.log("=== AJAX 성공 응답 ===");
                console.log("전체 응답:", response);
                
                branchSelect.empty().append('<option value="">지점 선택</option>');
                
                if (response.success && response.data) {
                    console.log("응답 데이터:", response.data);
                    console.log("데이터 개수:", response.data.length);
                    
                    if (response.data.length === 0) {
                        branchSelect.find('option[value=""]').text('해당 대리점에 속한 지점이 없습니다');
                        console.log("지점 데이터가 없습니다.");
                    } else {
                        jQuery.each(response.data, function(index, branch) {
                            console.log("지점 추가:", branch.id, branch.name);
                            var option = jQuery('<option></option>')
                                .attr('value', branch.id)
                                .text(branch.name + ' (' + branch.id + ')');
                            branchSelect.append(option);
                        });
                        
                        // 현재 선택된 지점 값을 설정
                        if (selected_br_id) {
                            branchSelect.val(selected_br_id);
                            console.log("선택된 지점 설정:", selected_br_id);
                        }
                    }
                } else {
                    console.error("AJAX 요청 실패:", response.message);
                    branchSelect.find('option[value=""]').text('지점 로드 실패');
                }
            },
            error: function(xhr, status, error) {
                console.log("=== AJAX 오류 ===");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
                
                branchSelect.empty().append('<option value="">네트워크 오류</option>');
            }
        });
    } else {
        console.log("ag_id가 비어있어 AJAX 요청을 하지 않습니다.");
        branchSelect.empty().append('<option value="">먼저 대리점을 선택하세요</option>');
    }
}

jQuery(document).ready(function() {
    <?php if ($auth['is_super']) { ?>
    // 본사 관리자의 경우: 페이지 로드 시 총판이 선택되어 있다면 대리점 목록 초기화
    var initialDtId = jQuery('#dt_id').val();
    var initialAgId = '<?php echo $member_info['dmk_ag_id'] ?? ($ag_id ?? ''); ?>';
    var initialBrId = '<?php echo $member_info['dmk_br_id'] ?? ($br_id ?? ''); ?>';

    console.log("페이지 로드 시 - initialDtId:", initialDtId, "initialAgId:", initialAgId, "initialBrId:", initialBrId);

    // 페이지 로드 시 초기화 (한 번만 실행)
    if (initialDtId) {
        console.log("초기 대리점 목록 로드 시작");
        updateAgencyOptions(initialDtId, initialAgId);
    }

    // 총판 변경 시 대리점 목록 업데이트
    jQuery('#dt_id').off('change.agencyUpdate').on('change.agencyUpdate', function() {
        var selectedDtId = jQuery(this).val();
        console.log("총판 변경됨:", selectedDtId);
        updateAgencyOptions(selectedDtId);
    });

    // 대리점 변경 시 지점 목록 업데이트
    jQuery('#ag_id').off('change.branchUpdate').on('change.branchUpdate', function() {
        var selectedAgId = jQuery(this).val();
        console.log("대리점 변경됨:", selectedAgId);
        updateBranchOptions(selectedAgId);
    });
    <?php } else { ?>
    // 총판/대리점 관리자의 경우: 대리점 변경 시 지점 목록 업데이트만 처리
    jQuery('#ag_id').off('change.branchUpdate').on('change.branchUpdate', function() {
        var selectedAgId = jQuery(this).val();
        console.log("대리점 변경됨:", selectedAgId);
        updateBranchOptions(selectedAgId);
    });
    <?php } ?>
});

function fmember_submit(f) {
    // 본사 관리자가 총판을 선택하지 않았을 경우 경고
    <?php if ($auth['is_super']) { ?>
    if (!f.dt_id.value) {
        alert("소속 총판을 선택하세요.");
        f.dt_id.focus();
        return false;
    }
    <?php } ?>

    <?php if ($w != 'u') { // 신규 등록시에만 유효성 검사 ?>
    if (!f.mb_id.value) {
        alert("관리자 ID를 입력하세요.");
        f.mb_id.focus();
        return false;
    }

    // 관리자 ID 형식 검사 (영문, 숫자, 언더스코어만 허용)
    if (!/^[a-zA-Z0-9_]{3,20}$/.test(f.mb_id.value)) {
        alert("관리자 ID는 영문, 숫자, 언더스코어만 사용 가능하며 3~20자여야 합니다.");
        f.mb_id.focus();
        return false;
    }

    if (!f.dmk_mb_type.value) {
        alert("관리자 유형을 선택하세요.");
        f.dmk_mb_type.focus();
        return false;
    }

    // 관리자 유형에 따른 소속 정보 검증
    var mbType = f.dmk_mb_type.value;
    if (mbType == '<?php echo DMK_MB_TYPE_AGENCY; ?>') {
        if (!f.ag_id.value) {
            alert("소속 대리점을 선택하세요.");
            f.ag_id.focus();
            return false;
        }
    } else if (mbType == '<?php echo DMK_MB_TYPE_BRANCH; ?>') {
        if (!f.ag_id.value) {
            alert("소속 대리점을 선택하세요.");
            f.ag_id.focus();
            return false;
        }
        if (!f.br_id.value) {
            alert("소속 지점을 선택하세요.");
            f.br_id.focus();
            return false;
        }
    }
    <?php } ?>

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
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 