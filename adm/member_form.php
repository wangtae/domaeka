<?php
$sub_menu = "200100";
include_once('./_common.php');

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 현재 관리자 권한 정보 가져오기
$auth = dmk_get_admin_auth();

auth_check_menu($auth, $sub_menu, 'w');

// 현재 회원의 소속 정보 초기화
$current_dt_id = '';
$current_ag_id = '';
$current_br_id = '';

$mb = array(
'mb_certify' => null,
'mb_adult' => null,
'mb_sms' => null,
'mb_intercept_date' => null,
'mb_id' => null,
'mb_name' => null,
'mb_nick' => null,
'mb_point' => null,
'mb_email' => null,
'mb_homepage' => null,
'mb_hp' => null,
'mb_tel' => null,
'mb_zip1' => null,
'mb_zip2' => null,
'mb_addr1' => null,
'mb_addr2' => null,
'mb_addr3' => null,
'mb_addr_jibeon' => null,
'mb_signature' => null,
'mb_profile' => null,
'mb_memo' => null,
'mb_leave_date' => null,
'mb_1' => null,
'mb_2' => null,
'mb_3' => null,
'mb_4' => null,
'mb_5' => null,
'mb_6' => null,
'mb_7' => null,
'mb_8' => null,
'mb_9' => null,
'mb_10' => null,
);

$sound_only = '';
$required_mb_id_class = '';
$required_mb_password = '';

if ($w == '')
{
    $required_mb_id = 'required';
    $required_mb_id_class = 'required alnum_';
    $required_mb_password = 'required';
    $sound_only = '<strong class="sound_only">필수</strong>';

    $mb['mb_mailling'] = 1;
    $mb['mb_open'] = 1;
    $mb['mb_level'] = $config['cf_register_level'];
    $html_title = '추가';
}
else if ($w == 'u')
{
    $mb = get_member($mb_id);
    if (!$mb['mb_id'])
        alert('존재하지 않는 회원자료입니다.');

    if ($is_admin != 'super' && $mb['mb_level'] >= $member['mb_level'])
        alert('자신보다 권한이 높거나 같은 회원은 수정할 수 없습니다.');

    $required_mb_id = 'readonly';
    $html_title = '수정';

    $mb['mb_name'] = get_text($mb['mb_name']);
    $mb['mb_nick'] = get_text($mb['mb_nick']);
    $mb['mb_email'] = get_text($mb['mb_email']);
    $mb['mb_homepage'] = get_text($mb['mb_homepage']);
    $mb['mb_birth'] = get_text($mb['mb_birth']);
    $mb['mb_tel'] = get_text($mb['mb_tel']);
    $mb['mb_hp'] = get_text($mb['mb_hp']);
    $mb['mb_addr1'] = get_text($mb['mb_addr1']);
    $mb['mb_addr2'] = get_text($mb['mb_addr2']);
    $mb['mb_addr3'] = get_text($mb['mb_addr3']);
    $mb['mb_signature'] = get_text($mb['mb_signature']);
    $mb['mb_recommend'] = get_text($mb['mb_recommend']);
    $mb['mb_profile'] = get_text($mb['mb_profile']);
    $mb['mb_1'] = get_text($mb['mb_1']);
    $mb['mb_2'] = get_text($mb['mb_2']);
    $mb['mb_3'] = get_text($mb['mb_3']);
    $mb['mb_4'] = get_text($mb['mb_4']);
    $mb['mb_5'] = get_text($mb['mb_5']);
    $mb['mb_6'] = get_text($mb['mb_6']);
    $mb['mb_7'] = get_text($mb['mb_7']);
    $mb['mb_8'] = get_text($mb['mb_8']);
    $mb['mb_9'] = get_text($mb['mb_9']);
    $mb['mb_10'] = get_text($mb['mb_10']);
    
    // dmk_mb_owner_type과 dmk_mb_owner_id를 통해 현재 소속 파악
    if (!empty($mb['dmk_mb_owner_type']) && !empty($mb['dmk_mb_owner_id'])) {
        switch ($mb['dmk_mb_owner_type']) {
            case 'distributor':
                $current_dt_id = $mb['dmk_mb_owner_id'];
                break;
            case 'agency':
                $current_ag_id = $mb['dmk_mb_owner_id'];
                // 대리점의 총판 정보 조회
                $agency_info = sql_fetch("SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($current_ag_id)."'");
                if ($agency_info) {
                    $current_dt_id = $agency_info['dt_id'];
                }
                break;
            case 'branch':
                $current_br_id = $mb['dmk_mb_owner_id'];
                // 지점의 대리점과 총판 정보 조회
                $branch_info = sql_fetch("
                    SELECT b.ag_id, a.dt_id 
                    FROM dmk_branch b 
                    JOIN dmk_agency a ON b.ag_id = a.ag_id 
                    WHERE b.br_id = '".sql_escape_string($current_br_id)."'
                ");
                if ($branch_info) {
                    $current_ag_id = $branch_info['ag_id'];
                    $current_dt_id = $branch_info['dt_id'];
                }
                break;
        }
    }
}
else
    alert('제대로 된 값이 넘어오지 않았습니다.');

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
if (!$auth['is_super']) {
    $br_sql = " SELECT b.br_id, m.mb_nick AS br_name FROM dmk_branch b JOIN {$g5['member_table']} m ON b.br_id = m.mb_id WHERE b.br_status = 1 ";

    if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판에 속한 지점만 선택 가능
        $br_sql .= " AND b.ag_id IN (SELECT ag_id FROM dmk_agency WHERE dt_id = '".sql_escape_string($auth['mb_id'])."') ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 대리점에 속한 지점만 선택 가능
        $br_sql .= " AND b.ag_id = '".sql_escape_string($auth['ag_id'])."' ";
    } else if ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자는 자신의 지점만 선택 가능
        $br_sql .= " AND b.br_id = '".sql_escape_string($auth['br_id'])."' ";
    }

    $br_sql .= " ORDER BY m.mb_nick ASC ";
    $br_result = sql_query($br_sql);
    while($row = sql_fetch_array($br_result)) {
        $branches[] = $row;
    }
}

// 본인확인방법
switch($mb['mb_certify']) {
    case 'hp':
        $mb_certify_case = '휴대폰';
        $mb_certify_val = 'hp';
        break;
    case 'ipin':
        $mb_certify_case = '아이핀';
        $mb_certify_val = 'ipin';
        break;
    case 'admin':
        $mb_certify_case = '관리자 수정';
        $mb_certify_val = 'admin';
        break;
    default:
        $mb_certify_case = '';
        $mb_certify_val = 'admin';
        break;
}

// 본인확인
$mb_certify_yes  =  $mb['mb_certify'] ? 'checked="checked"' : '';
$mb_certify_no   = !$mb['mb_certify'] ? 'checked="checked"' : '';

// 성인인증
$mb_adult_yes       =  $mb['mb_adult']      ? 'checked="checked"' : '';
$mb_adult_no        = !$mb['mb_adult']      ? 'checked="checked"' : '';

//메일수신
$mb_mailling_yes    =  $mb['mb_mailling']   ? 'checked="checked"' : '';
$mb_mailling_no     = !$mb['mb_mailling']   ? 'checked="checked"' : '';

// SMS 수신
$mb_sms_yes         =  $mb['mb_sms']        ? 'checked="checked"' : '';
$mb_sms_no          = !$mb['mb_sms']        ? 'checked="checked"' : '';

// 정보 공개
$mb_open_yes        =  $mb['mb_open']       ? 'checked="checked"' : '';
$mb_open_no         = !$mb['mb_open']       ? 'checked="checked"' : '';

if (isset($mb['mb_certify'])) {
    // 날짜시간형이라면 drop 시킴
    if (preg_match("/-/", $mb['mb_certify'])) {
        sql_query(" ALTER TABLE `{$g5['member_table']}` DROP `mb_certify` ", false);
    }
} else {
    sql_query(" ALTER TABLE `{$g5['member_table']}` ADD `mb_certify` TINYINT(4) NOT NULL DEFAULT '0' AFTER `mb_hp` ", false);
}

if(isset($mb['mb_adult'])) {
    sql_query(" ALTER TABLE `{$g5['member_table']}` CHANGE `mb_adult` `mb_adult` TINYINT(4) NOT NULL DEFAULT '0' ", false);
} else {
    sql_query(" ALTER TABLE `{$g5['member_table']}` ADD `mb_adult` TINYINT NOT NULL DEFAULT '0' AFTER `mb_certify` ", false);
}

// 지번주소 필드추가
if(!isset($mb['mb_addr_jibeon'])) {
    sql_query(" ALTER TABLE {$g5['member_table']} ADD `mb_addr_jibeon` varchar(255) NOT NULL DEFAULT '' AFTER `mb_addr2` ", false);
}

// 건물명필드추가
if(!isset($mb['mb_addr3'])) {
    sql_query(" ALTER TABLE {$g5['member_table']} ADD `mb_addr3` varchar(255) NOT NULL DEFAULT '' AFTER `mb_addr2` ", false);
}

// 중복가입 확인필드 추가
if(!isset($mb['mb_dupinfo'])) {
    sql_query(" ALTER TABLE {$g5['member_table']} ADD `mb_dupinfo` varchar(255) NOT NULL DEFAULT '' AFTER `mb_adult` ", false);
}

// 이메일인증 체크 필드추가
if(!isset($mb['mb_email_certify2'])) {
    sql_query(" ALTER TABLE {$g5['member_table']} ADD `mb_email_certify2` varchar(255) NOT NULL DEFAULT '' AFTER `mb_email_certify` ", false);
}

if ($mb['mb_intercept_date']) $g5['title'] = "차단된 ";
else $g5['title'] .= "";
$g5['title'] .= '회원 '.$html_title;
include_once('./admin.head.php');

// add_javascript('js 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_javascript(G5_POSTCODE_JS, 0);    //다음 주소 js
?>

<style>
.hierarchy_select_group {
    margin-bottom: 10px;
    display: inline-block;
    margin-right: 20px;
    vertical-align: top;
}

.hierarchy_select_group label {
    display: inline-block;
    width: 60px;
    font-weight: bold;
    margin-right: 5px;
}

.hierarchy_select_group select {
    min-width: 200px;
}

.hierarchy_select_group .frm_info {
    font-weight: normal;
    color: #666;
}
</style>

<form name="fmember" id="fmember" action="./member_form_update.php" onsubmit="return fmember_submit(this);" method="post" enctype="multipart/form-data">
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
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="member_hierarchy">소속</label></th>
        <td colspan="3">
            <!-- 총판 선택 -->
            <?php if ($auth['is_super']) { // 본사 관리자만 총판 선택 박스 노출 ?>
                <select name="dt_id" id="dt_id" class="frm_input">
                    <option value="">총판 선택</option>
                    <?php foreach ($distributors as $distributor) { ?>
                        <option value="<?php echo $distributor['dt_id'] ?>" <?php echo ($current_dt_id == $distributor['dt_id']) ? 'selected' : '' ?>>
                            <?php echo get_text($distributor['dt_name']) ?> (<?php echo $distributor['dt_id'] ?>)
                        </option>
                    <?php } ?>
                </select>
            <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { // 총판 관리자는 자신의 총판 ID를 hidden 필드로 전달 ?>
            <div class="hierarchy_select_group">
                <label>총판:</label>
                <span class="frm_info"><?php echo get_text($auth['mb_id']) ?> (<?php echo get_text($auth['mb_name']) ?>)</span>
                <input type="hidden" name="dt_id" id="dt_id" value="<?php echo get_text($auth['mb_id']) ?>">
            </div>
            <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) { // 대리점 관리자는 총판 정보만 표시 ?>
            <div class="hierarchy_select_group">
                <label>총판:</label>
                <?php
                    $parent_dt_id = dmk_get_agency_distributor_id($auth['ag_id']);
                    $parent_dt_name = $parent_dt_id ? dmk_get_member_name($parent_dt_id) : '미지정';
                ?>
                <span class="frm_info"><?php echo get_text($parent_dt_id) ?> (<?php echo get_text($parent_dt_name) ?>)</span>
                <input type="hidden" name="dt_id" id="dt_id" value="<?php echo get_text($parent_dt_id) ?>">
            </div>
            <?php } else { // 그 외 (지점 관리자 등)는 총판 필드 숨김 ?>
            <input type="hidden" name="dt_id" id="dt_id" value="<?php echo $current_dt_id ?>">
            <?php } ?>

            <!-- 대리점 선택 -->
            <?php if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { ?>
                <select name="ag_id" id="ag_id" class="frm_input">
                    <?php if ($auth['is_super']) { ?>
                        <option value="">먼저 총판을 선택하세요</option>
                    <?php } else { ?>
                        <option value="">대리점 선택</option>
                        <?php foreach ($agencies as $agency) { ?>
                            <option value="<?php echo $agency['ag_id'] ?>" <?php echo ($current_ag_id == $agency['ag_id']) ? 'selected' : '' ?>>
                                <?php echo get_text($agency['ag_name']) ?> (<?php echo $agency['ag_id'] ?>)
                            </option>
                        <?php } ?>
                    <?php } ?>
                </select>
            <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) { // 대리점 관리자는 자신의 대리점만 표시 ?>
            <div class="hierarchy_select_group">
                <label>대리점:</label>
                <span class="frm_info"><?php echo get_text($auth['ag_id']) ?> (<?php echo get_text($auth['ag_name']) ?>)</span>
                <input type="hidden" name="ag_id" id="ag_id" value="<?php echo get_text($auth['ag_id']) ?>">
            </div>
            <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) { // 지점 관리자는 소속 대리점 표시 ?>
            <div class="hierarchy_select_group">
                <label>대리점:</label>
                <?php
                    $parent_ag_name = dmk_get_member_name($auth['ag_id']);
                ?>
                <span class="frm_info"><?php echo get_text($auth['ag_id']) ?> (<?php echo get_text($parent_ag_name) ?>)</span>
                <input type="hidden" name="ag_id" id="ag_id" value="<?php echo $current_ag_id ?>">
            </div>
            <?php } else { ?>
            <input type="hidden" name="ag_id" id="ag_id" value="<?php echo $current_ag_id ?>">
            <?php } ?>

            <!-- 지점 선택 -->
            <?php if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR || $auth['mb_type'] == DMK_MB_TYPE_AGENCY) { ?>
                <select name="br_id" id="br_id" class="frm_input">
                    <?php if ($auth['is_super']) { ?>
                        <option value="">먼저 대리점을 선택하세요</option>
                    <?php } else { ?>
                        <option value="">지점 선택</option>
                        <?php foreach ($branches as $branch) { ?>
                            <option value="<?php echo $branch['br_id'] ?>" <?php echo ($current_br_id == $branch['br_id']) ? 'selected' : '' ?>>
                                <?php echo get_text($branch['br_name']) ?> (<?php echo $branch['br_id'] ?>)
                            </option>
                        <?php } ?>
                    <?php } ?>
                </select>
            <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) { // 지점 관리자는 자신의 지점만 표시 ?>
            <div class="hierarchy_select_group">
                <label>지점:</label>
                <span class="frm_info"><?php echo get_text($auth['br_id']) ?> (<?php echo get_text($auth['br_name']) ?>)</span>
                <input type="hidden" name="br_id" id="br_id" value="<?php echo get_text($auth['br_id']) ?>">
            </div>
            <?php } else { ?>
            <input type="hidden" name="br_id" id="br_id" value="<?php echo $current_br_id ?>">
            <?php } ?>

            <!-- 소속 정보를 저장할 hidden 필드들 -->
            <input type="hidden" name="dmk_mb_owner_type" id="dmk_mb_owner_type" value="<?php echo htmlspecialchars($mb['dmk_mb_owner_type'] ?? '') ?>">
            <input type="hidden" name="dmk_mb_owner_id" id="dmk_mb_owner_id" value="<?php echo htmlspecialchars($mb['dmk_mb_owner_id'] ?? '') ?>">
            
            <div class="frm_info">
                지점을 지정해야 회원 가입이 가능합니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_id">아이디<?php echo $sound_only ?></label></th>
        <td>
            <input type="text" name="mb_id" value="<?php echo $mb['mb_id'] ?>" id="mb_id" <?php echo $required_mb_id ?> class="frm_input <?php echo $required_mb_id_class ?>" size="15"  maxlength="20">
            <?php if ($w=='u'){ ?><a href="./boardgroupmember_form.php?mb_id=<?php echo $mb['mb_id'] ?>" class="btn_frmline">접근가능그룹보기</a><?php } ?>
        </td>
        <th scope="row"><label for="mb_password">비밀번호<?php echo $sound_only ?></label></th>
        <td><input type="password" name="mb_password" id="mb_password" <?php echo $required_mb_password ?> class="frm_input <?php echo $required_mb_password ?>" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_name">이름(실명)<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_name" value="<?php echo $mb['mb_name'] ?>" id="mb_name" required class="required frm_input" size="15"  maxlength="20"></td>
        <th scope="row"><label for="mb_nick">닉네임<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_nick" value="<?php echo $mb['mb_nick'] ?>" id="mb_nick" required class="required frm_input" size="15"  maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_level">회원 권한</label></th>
        <td><?php echo get_member_level_select('mb_level', 1, $member['mb_level'], $mb['mb_level']) ?></td>
        <th scope="row">포인트</th>
        <td><a href="./point_list.php?sfl=mb_id&amp;stx=<?php echo $mb['mb_id'] ?>" target="_blank"><?php echo number_format((int)$mb['mb_point']) ?></a> 점</td>
    </tr>
    
    <tr>
        <th scope="row"><label for="mb_email">E-mail<strong class="sound_only">필수</strong></label></th>
        <td><input type="text" name="mb_email" value="<?php echo $mb['mb_email'] ?>" id="mb_email" maxlength="100" required class="required frm_input email" size="30"></td>
        <th scope="row"><label for="mb_homepage">홈페이지</label></th>
        <td><input type="text" name="mb_homepage" value="<?php echo $mb['mb_homepage'] ?>" id="mb_homepage" class="frm_input" maxlength="255" size="15"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_hp">휴대폰번호</label></th>
        <td><input type="text" name="mb_hp" value="<?php echo $mb['mb_hp'] ?>" id="mb_hp" class="frm_input" size="15" maxlength="20"></td>
        <th scope="row"><label for="mb_tel">전화번호</label></th>
        <td><input type="text" name="mb_tel" value="<?php echo $mb['mb_tel'] ?>" id="mb_tel" class="frm_input" size="15" maxlength="20"></td>
    </tr>
    <tr>
        <th scope="row">본인확인방법</th>
        <td colspan="3">
            <input type="radio" name="mb_certify_case" value="ipin" id="mb_certify_ipin" <?php if($mb['mb_certify'] == 'ipin') echo 'checked="checked"'; ?>>
            <label for="mb_certify_ipin">아이핀</label>
            <input type="radio" name="mb_certify_case" value="hp" id="mb_certify_hp" <?php if($mb['mb_certify'] == 'hp') echo 'checked="checked"'; ?>>
            <label for="mb_certify_hp">휴대폰</label>
        </td>
    </tr>
    <tr>
        <th scope="row">본인확인</th>
        <td>
            <input type="radio" name="mb_certify" value="1" id="mb_certify_yes" <?php echo $mb_certify_yes; ?>>
            <label for="mb_certify_yes">예</label>
            <input type="radio" name="mb_certify" value="" id="mb_certify_no" <?php echo $mb_certify_no; ?>>
            <label for="mb_certify_no">아니오</label>
        </td>
        <th scope="row">성인인증</th>
        <td>
            <input type="radio" name="mb_adult" value="1" id="mb_adult_yes" <?php echo $mb_adult_yes; ?>>
            <label for="mb_adult_yes">예</label>
            <input type="radio" name="mb_adult" value="0" id="mb_adult_no" <?php echo $mb_adult_no; ?>>
            <label for="mb_adult_no">아니오</label>
        </td>
    </tr>
    <tr>
        <th scope="row">주소</th>
        <td colspan="3" class="td_addr_line">
            <label for="mb_zip" class="sound_only">우편번호</label>
            <input type="text" name="mb_zip" value="<?php echo $mb['mb_zip1'].$mb['mb_zip2']; ?>" id="mb_zip" class="frm_input readonly" size="5" maxlength="6">
            <button type="button" class="btn_frmline" onclick="win_zip('fmember', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');">주소 검색</button><br>
            <input type="text" name="mb_addr1" value="<?php echo $mb['mb_addr1'] ?>" id="mb_addr1" class="frm_input readonly" size="60">
            <label for="mb_addr1">기본주소</label><br>
            <input type="text" name="mb_addr2" value="<?php echo $mb['mb_addr2'] ?>" id="mb_addr2" class="frm_input" size="60">
            <label for="mb_addr2">상세주소</label>
            <br>
            <input type="text" name="mb_addr3" value="<?php echo $mb['mb_addr3'] ?>" id="mb_addr3" class="frm_input" size="60">
            <label for="mb_addr3">참고항목</label>
            <input type="hidden" name="mb_addr_jibeon" value="<?php echo $mb['mb_addr_jibeon']; ?>"><br>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_icon">회원아이콘</label></th>
        <td colspan="3">
            <?php echo help('이미지 크기는 <strong>넓이 '.$config['cf_member_icon_width'].'픽셀 높이 '.$config['cf_member_icon_height'].'픽셀</strong>로 해주세요.') ?>
            <input type="file" name="mb_icon" id="mb_icon">
            <?php
            if ($mb['mb_id']) {
                $mb_dir = substr($mb['mb_id'],0,2);
                $icon_file = G5_DATA_PATH.'/member/'.$mb_dir.'/'.get_mb_icon_name($mb['mb_id']).'.gif';
                if (file_exists($icon_file)) {
                    $icon_url = str_replace(G5_DATA_PATH, G5_DATA_URL, $icon_file);
                    $icon_filemtile = (defined('G5_USE_MEMBER_IMAGE_FILETIME') && G5_USE_MEMBER_IMAGE_FILETIME) ? '?'.filemtime($icon_file) : '';
                    echo '<img src="'.$icon_url.$icon_filemtile.'" alt="">';
                    echo '<input type="checkbox" id="del_mb_icon" name="del_mb_icon" value="1">삭제';
                }
            }
            ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_img">회원이미지</label></th>
        <td colspan="3">
            <?php echo help('이미지 크기는 <strong>넓이 '.$config['cf_member_img_width'].'픽셀 높이 '.$config['cf_member_img_height'].'픽셀</strong>로 해주세요.') ?>
            <input type="file" name="mb_img" id="mb_img">
            <?php
            if ($mb['mb_id']) {
                $mb_dir = substr($mb['mb_id'],0,2);
                $icon_file = G5_DATA_PATH.'/member_image/'.$mb_dir.'/'.get_mb_icon_name($mb['mb_id']).'.gif';
                if (file_exists($icon_file)) {
                    echo get_member_profile_img($mb['mb_id']);
                    echo '<input type="checkbox" id="del_mb_img" name="del_mb_img" value="1">삭제';
                }
            }
            ?>
        </td>
    </tr>
    <tr>
        <th scope="row">메일 수신</th>
        <td>
            <input type="radio" name="mb_mailling" value="1" id="mb_mailling_yes" <?php echo $mb_mailling_yes; ?>>
            <label for="mb_mailling_yes">예</label>
            <input type="radio" name="mb_mailling" value="0" id="mb_mailling_no" <?php echo $mb_mailling_no; ?>>
            <label for="mb_mailling_no">아니오</label>
        </td>
        <th scope="row"><label for="mb_sms_yes">SMS 수신</label></th>
        <td>
            <input type="radio" name="mb_sms" value="1" id="mb_sms_yes" <?php echo $mb_sms_yes; ?>>
            <label for="mb_sms_yes">예</label>
            <input type="radio" name="mb_sms" value="0" id="mb_sms_no" <?php echo $mb_sms_no; ?>>
            <label for="mb_sms_no">아니오</label>
        </td>
    </tr>
    <tr>
        <th scope="row">정보 공개</th>
        <td colspan="3">
            <input type="radio" name="mb_open" value="1" id="mb_open_yes" <?php echo $mb_open_yes; ?>>
            <label for="mb_open_yes">예</label>
            <input type="radio" name="mb_open" value="0" id="mb_open_no" <?php echo $mb_open_no; ?>>
            <label for="mb_open_no">아니오</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_signature">서명</label></th>
        <td colspan="3"><textarea  name="mb_signature" id="mb_signature"><?php echo $mb['mb_signature'] ?></textarea></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_profile">자기 소개</label></th>
        <td colspan="3"><textarea name="mb_profile" id="mb_profile"><?php echo $mb['mb_profile'] ?></textarea></td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_memo">메모</label></th>
        <td colspan="3"><textarea name="mb_memo" id="mb_memo"><?php echo $mb['mb_memo'] ?></textarea></td>
    </tr>

    <?php if ($w == 'u') { ?>
    <tr>
        <th scope="row">회원가입일</th>
        <td><?php echo $mb['mb_datetime'] ?></td>
        <th scope="row">최근접속일</th>
        <td><?php echo $mb['mb_today_login'] ?></td>
    </tr>
    <tr>
        <th scope="row">IP</th>
        <td colspan="3"><?php echo $mb['mb_ip'] ?></td>
    </tr>
    <?php if ($config['cf_use_email_certify']) { ?>
    <tr>
        <th scope="row">인증일시</th>
        <td colspan="3">
            <?php if ($mb['mb_email_certify'] == '0000-00-00 00:00:00') { ?>
            <?php echo help('회원님이 메일을 수신할 수 없는 경우 등에 직접 인증처리를 하실 수 있습니다.') ?>
            <input type="checkbox" name="passive_certify" id="passive_certify">
            <label for="passive_certify">수동인증</label>
            <?php } else { ?>
            <?php echo $mb['mb_email_certify'] ?>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
    <?php } ?>

    <?php if ($config['cf_use_recommend']) { // 추천인 사용 ?>
    <tr>
        <th scope="row">추천인</th>
        <td colspan="3"><?php echo ($mb['mb_recommend'] ? get_text($mb['mb_recommend']) : '없음'); // 081022 : CSRF 보안 결함으로 인한 코드 수정 ?></td>
    </tr>
    <?php } ?>

    <tr>
        <th scope="row"><label for="mb_leave_date">탈퇴일자</label></th>
        <td>
            <input type="text" name="mb_leave_date" value="<?php echo $mb['mb_leave_date'] ?>" id="mb_leave_date" class="frm_input" maxlength="8">
            <input type="checkbox" value="<?php echo date("Ymd"); ?>" id="mb_leave_date_set_today" onclick="if (this.form.mb_leave_date.value==this.form.mb_leave_date.defaultValue) {
this.form.mb_leave_date.value=this.value; } else { this.form.mb_leave_date.value=this.form.mb_leave_date.defaultValue; }">
            <label for="mb_leave_date_set_today">탈퇴일을 오늘로 지정</label>
        </td>
        <th scope="row">접근차단일자</th>
        <td>
            <input type="text" name="mb_intercept_date" value="<?php echo $mb['mb_intercept_date'] ?>" id="mb_intercept_date" class="frm_input" maxlength="8">
            <input type="checkbox" value="<?php echo date("Ymd"); ?>" id="mb_intercept_date_set_today" onclick="if
(this.form.mb_intercept_date.value==this.form.mb_intercept_date.defaultValue) { this.form.mb_intercept_date.value=this.value; } else {
this.form.mb_intercept_date.value=this.form.mb_intercept_date.defaultValue; }">
            <label for="mb_intercept_date_set_today">접근차단일을 오늘로 지정</label>
        </td>
    </tr>

    <?php
    //소셜계정이 있다면
    if(function_exists('social_login_link_account') && $mb['mb_id'] ){
        if( $my_social_accounts = social_login_link_account($mb['mb_id'], false, 'get_data') ){ ?>

    <tr>
    <th>소셜계정목록</th>
    <td colspan="3">
        <ul class="social_link_box">
            <li class="social_login_container">
                <h4>연결된 소셜 계정 목록</h4>
                <?php foreach($my_social_accounts as $account){     //반복문
                    if( empty($account) ) continue;

                    $provider = strtolower($account['provider']);
                    $provider_name = social_get_provider_service_name($provider);
                ?>
                <div class="account_provider" data-mpno="social_<?php echo $account['mp_no'];?>" >
                    <div class="sns-wrap-32 sns-wrap-over">
                        <span class="sns-icon sns-<?php echo $provider; ?>" title="<?php echo $provider_name; ?>">
                            <span class="ico"></span>
                            <span class="txt"><?php echo $provider_name; ?></span>
                        </span>

                        <span class="provider_name"><?php echo $provider_name;   //서비스이름?> ( <?php echo $account['displayname']; ?> )</span>
                        <span class="account_hidden" style="display:none"><?php echo $account['mb_id']; ?></span>
                    </div>
                    <div class="btn_info"><a href="<?php echo G5_SOCIAL_LOGIN_URL.'/unlink.php?mp_no='.$account['mp_no'] ?>" class="social_unlink" data-provider="<?php echo $account['mp_no'];?>" >연동해제</a> <span class="sound_only"><?php echo substr($account['mp_register_day'], 2, 14); ?></span></div>
                </div>
                <?php } //end foreach ?>
            </li>
        </ul>
        <script>
        jQuery(function($){
            $(".account_provider").on("click", ".social_unlink", function(e){
                e.preventDefault();

                if (!confirm('정말 이 계정 연결을 삭제하시겠습니까?')) {
                    return false;
                }

                var ajax_url = "<?php echo G5_SOCIAL_LOGIN_URL.'/unlink.php' ?>";
                var mb_id = '',
                    mp_no = $(this).attr("data-provider"),
                    $mp_el = $(this).parents(".account_provider");

                    mb_id = $mp_el.find(".account_hidden").text();

                if( ! mp_no ){
                    alert('잘못된 요청! mp_no 값이 없습니다.');
                    return;
                }

                $.ajax({
                    url: ajax_url,
                    type: 'POST',
                    data: {
                        'mp_no': mp_no,
                        'mb_id': mb_id
                    },
                    dataType: 'json',
                    async: false,
                    success: function(data, textStatus) {
                        if (data.error) {
                            alert(data.error);
                            return false;
                        } else {
                            alert("연결이 해제 되었습니다.");
                            $mp_el.fadeOut("normal", function() {
                                $(this).remove();
                            });
                        }
                    }
                });

                return;
            });
        });
        </script>

    </td>
    </tr>

    <?php
        }   //end if
    }   //end if

    run_event('admin_member_form_add', $mb, $w, 'table');
    ?>

    <?php for ($i=1; $i<=10; $i++) { ?>
    <tr>
        <th scope="row"><label for="mb_<?php echo $i ?>">여분 필드 <?php echo $i ?></label></th>
        <td colspan="3"><input type="text" name="mb_<?php echo $i ?>" value="<?php echo $mb['mb_'.$i] ?>" id="mb_<?php echo $i ?>" class="frm_input" size="30" maxlength="255"></td>
    </tr>
    <?php } ?>

    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./member_list.php?<?php echo $qstr ?>" class="btn btn_02">목록</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey='s'>
</div>
</form>

<script>
function fmember_submit(f)
{
    if (!f.mb_icon.value.match(/\.(gif|jpe?g|png)$/i) && f.mb_icon.value) {
        alert('아이콘은 이미지 파일만 가능합니다.');
        return false;
    }

    if (!f.mb_img.value.match(/\.(gif|jpe?g|png)$/i) && f.mb_img.value) {
        alert('회원이미지는 이미지 파일만 가능합니다.');
        return false;
    }

    // 소속 정보 업데이트
    var owner_type = '';
    var owner_id = '';

    var dt_id = jQuery("#dt_id").val();
    var ag_id = jQuery("#ag_id").val();
    var br_id = jQuery("#br_id").val();

    if (br_id) {
        owner_type = 'branch';
        owner_id = br_id;
    } else if (ag_id) {
        owner_type = 'agency';
        owner_id = ag_id;
    } else if (dt_id) {
        owner_type = 'distributor';
        owner_id = dt_id;
    } else {
        owner_type = '';
        owner_id = '';
    }

    jQuery("#dmk_mb_owner_type").val(owner_type);
    jQuery("#dmk_mb_owner_id").val(owner_id);

    return true;
}

// jQuery를 사용하여 AJAX 요청을 처리하는 함수 - 대리점 목록 업데이트
function updateAgencyOptions(dt_id, selected_ag_id = '') {
    console.log("=== updateAgencyOptions 시작 ===");
    console.log("dt_id:", dt_id, "selected_ag_id:", selected_ag_id);
    
    var agencySelect = jQuery('#ag_id');
    if (agencySelect.length === 0) {
        console.error("ag_id 선택박스를 찾을 수 없습니다.");
        return;
    }
    
    // 기존 옵션 완전히 제거하고 기본 옵션 추가
    agencySelect.empty().append('<option value="">대리점 선택</option>');
    
    // 지점 선택박스도 초기화
    var branchSelect = jQuery('#br_id');
    if (branchSelect.length > 0) {
        branchSelect.empty().append('<option value="">먼저 대리점을 선택하세요</option>');
    }

    if (dt_id) {
        var ajaxUrl = '../dmk/adm/_ajax/get_agencies.php';
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
                
                // 다시 기본 옵션으로 초기화
                agencySelect.empty().append('<option value="">대리점 선택</option>');
                
                if (response.success) {
                    console.log("응답 데이터:", response.data);
                    console.log("데이터 개수:", response.data.length);
                    
                    if (response.data.length === 0) {
                        agencySelect.find('option[value=""]').text('해당 총판에 속한 대리점이 없습니다');
                        console.log("대리점 데이터가 없습니다.");
                    } else {
                        jQuery.each(response.data, function(index, agency) {
                            var optionText = agency.name + ' (' + agency.id + ')';
                            var option = new Option(optionText, agency.id);
                            agencySelect.append(jQuery(option));
                        });
                        
                        // 선택된 대리점 값을 설정
                        if (selected_ag_id) {
                            console.log("전달받은 selected_ag_id로 값 설정 시도:", selected_ag_id);
                            agencySelect.val(selected_ag_id);
                        }
                    }
                } else {
                    console.error("AJAX 요청 실패:", response.message);
                    agencySelect.find('option[value=""]').text('대리점 로드 실패');
                }
                
                if (response.debug) {
                    console.log("디버그 정보:", response.debug);
                }
            },
            error: function(xhr, status, error) {
                console.log("=== AJAX 오류 ===");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
                console.error("Status Code:", xhr.status);
                
                agencySelect.empty().append('<option value="">네트워크 오류 또는 서버 응답 오류</option>');
            },
            complete: function() {
                console.log("AJAX 요청 완료. 최종 select HTML:", agencySelect.html());
                console.log("최종 선택된 값:", agencySelect.val());
            }
        });
    } else {
        console.log("dt_id가 비어있어 AJAX 요청을 하지 않습니다.");
        agencySelect.empty().append('<option value="">먼저 총판을 선택하세요</option>');
    }
    console.log("=== updateAgencyOptions 끝 ===");
}

// jQuery를 사용하여 AJAX 요청을 처리하는 함수 - 지점 목록 업데이트
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
        var ajaxUrl = '../dmk/adm/_ajax/get_branches.php';
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
                
                // 다시 기본 옵션으로 초기화
                branchSelect.empty().append('<option value="">지점 선택</option>');
                
                if (response.success) {
                    console.log("응답 데이터:", response.data);
                    console.log("데이터 개수:", response.data.length);
                    
                    if (response.data.length === 0) {
                        branchSelect.find('option[value=""]').text('해당 대리점에 속한 지점이 없습니다');
                        console.log("지점 데이터가 없습니다.");
                    } else {
                        jQuery.each(response.data, function(index, branch) {
                            var optionText = branch.name + ' (' + branch.id + ')';
                            var option = new Option(optionText, branch.id);
                            branchSelect.append(jQuery(option));
                        });
                        
                        // 선택된 지점 값을 설정
                        if (selected_br_id) {
                            console.log("전달받은 selected_br_id로 값 설정 시도:", selected_br_id);
                            branchSelect.val(selected_br_id);
                        }
                    }
                } else {
                    console.error("AJAX 요청 실패:", response.message);
                    branchSelect.find('option[value=""]').text('지점 로드 실패');
                }
                
                if (response.debug) {
                    console.log("디버그 정보:", response.debug);
                }
            },
            error: function(xhr, status, error) {
                console.log("=== AJAX 오류 ===");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
                console.error("Status Code:", xhr.status);
                
                branchSelect.empty().append('<option value="">네트워크 오류 또는 서버 응답 오류</option>');
            },
            complete: function() {
                console.log("AJAX 요청 완료. 최종 select HTML:", branchSelect.html());
                console.log("최종 선택된 값:", branchSelect.val());
            }
        });
    } else {
        console.log("ag_id가 비어있어 AJAX 요청을 하지 않습니다.");
        branchSelect.empty().append('<option value="">먼저 대리점을 선택하세요</option>');
    }
    console.log("=== updateBranchOptions 끝 ===");
}

jQuery(document).ready(function() {
    <?php if ($auth['is_super']) { ?>
    // 본사 관리자의 경우: 페이지 로드 시 총판이 선택되어 있다면 대리점, 지점 목록 초기화
    var initialDtId = jQuery('#dt_id').val();
    var initialAgId = '<?php echo $current_ag_id; ?>';
    var initialBrId = '<?php echo $current_br_id; ?>';

    console.log("페이지 로드 시 - initialDtId:", initialDtId, "initialAgId:", initialAgId, "initialBrId:", initialBrId);

    // 페이지 로드 시 초기화 (한 번만 실행)
    if (initialDtId) {
        console.log("초기 대리점 목록 로드 시작");
        updateAgencyOptions(initialDtId, initialAgId);
        
        // 대리점이 선택되어 있다면 지점 목록도 로드
        if (initialAgId) {
            setTimeout(function() {
                updateBranchOptions(initialAgId, initialBrId);
            }, 500); // 대리점 로드 후 약간의 지연
        }
    } else {
        console.log("총판이 선택되지 않아 초기화 생략");
    }

    // 소속 총판 변경 시 대리점 목록 업데이트
    jQuery('#dt_id').off('change.agencyUpdate').on('change.agencyUpdate', function() {
        var selectedDtId = jQuery(this).val();
        console.log("총판 변경됨:", selectedDtId);
        updateAgencyOptions(selectedDtId);
    });

    // 소속 대리점 변경 시 지점 목록 업데이트
    jQuery('#ag_id').off('change.branchUpdate').on('change.branchUpdate', function() {
        var selectedAgId = jQuery(this).val();
        console.log("대리점 변경됨:", selectedAgId);
        updateBranchOptions(selectedAgId);
    });
    
    <?php } else if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { ?>
    // 총판 관리자의 경우: 대리점 변경 시 지점 목록 업데이트
    var initialAgId = '<?php echo $current_ag_id; ?>';
    var initialBrId = '<?php echo $current_br_id; ?>';

    console.log("총판 관리자 - initialAgId:", initialAgId, "initialBrId:", initialBrId);

    // 페이지 로드 시 지점 목록 초기화
    if (initialAgId) {
        console.log("초기 지점 목록 로드 시작");
        updateBranchOptions(initialAgId, initialBrId);
    }

    // 소속 대리점 변경 시 지점 목록 업데이트
    jQuery('#ag_id').off('change.branchUpdate').on('change.branchUpdate', function() {
        var selectedAgId = jQuery(this).val();
        console.log("대리점 변경됨:", selectedAgId);
        updateBranchOptions(selectedAgId);
    });
    
    <?php } else { ?>
    // 대리점 관리자나 지점 관리자의 경우: 별도 초기화 불필요 (PHP에서 이미 처리됨)
    console.log("대리점/지점 관리자 - JavaScript 초기화 생략");
    <?php } ?>
});
</script>
<?php
run_event('admin_member_form_after', $mb, $w);

include_once('./admin.tail.php');