<?php
$sub_menu = "190600";
include_once './_common.php';

// 공통 체인 선택박스 라이브러리 포함
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

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

    // 권한 확인: dmk_can_modify_member 함수를 사용하여 수정 권한 체크
    // 최고관리자는 이미 dmk_can_modify_member 내부에서 처리됨
    if (!dmk_can_modify_member($mb_id)) {
        alert('수정 권한이 없습니다.');
        exit;
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
        DMK_MB_TYPE_DISTRIBUTOR => '총판 관리자',
        DMK_MB_TYPE_AGENCY => '대리점 관리자', 
        DMK_MB_TYPE_BRANCH => '지점 관리자'
    );
} elseif ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    $admin_type_options = array(
        DMK_MB_TYPE_AGENCY => '대리점 관리자',
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
    <?php if ($w != 'u' && $auth['mb_type'] != DMK_MB_TYPE_BRANCH) { // 등록 모드이고 지점 관리자가 아닌 경우에만 ?>
    <tr>
        <th scope="row">소속 기관</th>
        <td>
            <?php
            // 관리자 유형에 따른 chain select 타입 결정
            $chain_select_type = DMK_CHAIN_SELECT_FULL;
            if ($auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                $chain_select_type = DMK_CHAIN_SELECT_FULL; // 총판은 총판-대리점-지점 모두 표시
            } elseif ($auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
                $chain_select_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY; // 대리점은 대리점-지점만 표시
            }
            
            // 공통 체인 선택박스 렌더링
            echo dmk_render_chain_select([
                'page_type' => $chain_select_type,
                'auto_submit' => false, // 등록 페이지에서는 자동 제출 비활성화
                'debug' => true, // 디버그 모드 활성화
                'form_id' => 'fmember', // 폼 ID 변경
                'field_names' => [
                    'distributor' => 'dt_id',
                    'agency' => 'ag_id',
                    'branch' => 'br_id'
                ],
                'current_values' => [
                    'dt_id' => $member_info['dmk_dt_id'] ?? '',
                    'ag_id' => $member_info['dmk_ag_id'] ?? '',
                    'br_id' => $member_info['dmk_br_id'] ?? '',
                    'sdt_id' => $member_info['dmk_dt_id'] ?? '',
                    'sag_id' => $member_info['dmk_ag_id'] ?? '',
                    'sbr_id' => $member_info['dmk_br_id'] ?? ''
                ]
            ]);
            ?>
            <span class="frm_info">소속 기관을 선택하면 자동으로 관리자 유형이 결정됩니다.</span>
        </td>
    </tr>
    <?php } // 등록 모드일 때만 끝 ?>
    
    <?php if ($w != 'u' && $auth['mb_type'] == DMK_MB_TYPE_BRANCH) { ?>
    <!-- 지점 관리자의 경우 소속 기관 정보를 hidden으로만 전송 -->
    <input type="hidden" name="dt_id" value="<?php echo get_text($auth['dt_id']) ?>">
    <input type="hidden" name="ag_id" value="<?php echo get_text($auth['ag_id']) ?>">
    <input type="hidden" name="br_id" value="<?php echo get_text($auth['br_id']) ?>">
    <?php } ?>
    <?php if ($w == 'u') { // 수정 모드일 때 소속 정보 읽기 전용으로 표시 ?>
    
    <?php if ($auth['mb_type'] != DMK_MB_TYPE_AGENCY) { // 대리점 관리자가 아닌 경우에만 총판 정보 표시 ?>
    <tr>
        <th scope="row">소속 총판</th>
        <td>
            <?php
                $current_dt_name = dmk_get_member_name($member_info['dmk_dt_id']);
            ?>
            <input type="text" value="<?php 
                // 하위 계층 관리자는 상위 계층 ID 숨김
                if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                    echo get_text($member_info['dmk_dt_id']) . ' (' . get_text($current_dt_name) . ')';
                } else {
                    echo get_text($current_dt_name); // ID 숨김
                }
            ?>" class="frm_input" readonly>
            <input type="hidden" name="dt_id" value="<?php echo get_text($member_info['dmk_dt_id']) ?>">
            <span class="frm_info">총판은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <?php } else { ?>
    <!-- 대리점 관리자의 경우 총판 정보를 hidden으로만 전송 (소스보기에서도 안보이도록 서버에서 처리) -->
    <?php } ?>
    
    <?php if ($auth['mb_type'] != DMK_MB_TYPE_BRANCH) { // 지점 관리자가 아닌 경우에만 대리점 정보 표시 ?>
    <tr>
        <th scope="row">소속 대리점</th>
        <td>
            <?php
                $current_ag_name = $member_info['dmk_ag_id'] ? dmk_get_member_name($member_info['dmk_ag_id']) : '미지정';
            ?>
            <input type="text" name="ag_id_display" value="<?php 
                // 하위 계층 관리자는 상위 계층 ID 숨김
                if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                    echo get_text($member_info['dmk_ag_id']) . ' (' . get_text($current_ag_name) . ')';
                } else {
                    echo get_text($current_ag_name); // ID 숨김
                }
            ?>" id="ag_id_display" class="frm_input" readonly>
            <input type="hidden" name="ag_id" value="<?php echo get_text($member_info['dmk_ag_id']) ?>">
            <span class="frm_info">대리점은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <?php } else { ?>
    <!-- 지점 관리자의 경우 대리점 정보를 hidden으로만 전송 (소스보기에서도 안보이도록 서버에서 처리) -->
    <?php } ?>
    
    <?php if ($member_info['dmk_br_id']) { // 지점이 있는 경우에만 표시 ?>
    <tr>
        <th scope="row">소속 지점</th>
        <td>
            <?php
                $current_br_name = $member_info['dmk_br_id'] ? dmk_get_member_name($member_info['dmk_br_id']) : '미지정';
            ?>
            <input type="text" name="br_id_display" value="<?php 
                // 하위 계층 관리자는 상위 계층 ID 숨김
                if ($auth['is_super'] || $auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                    echo get_text($member_info['dmk_br_id']) . ' (' . get_text($current_br_name) . ')';
                } else {
                    echo get_text($current_br_name); // ID 숨김
                }
            ?>" id="br_id_display" class="frm_input" readonly>
            <input type="hidden" name="br_id" value="<?php echo get_text($member_info['dmk_br_id']) ?>">
            <span class="frm_info">지점은 수정할 수 없습니다.</span>
        </td>
    </tr>
    <?php } ?>
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
    <?php } else { ?>
    <!-- 수정시 비밀번호 변경 체크 -->
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
    <tr style="display: none;">
        <th scope="row"><label for="dmk_mb_type">관리자 유형</label></th>
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
                <?php if ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) { ?>
                    <!-- 지점 관리자의 경우 자동으로 지점 관리자로 설정 -->
                    <input type="text" name="dmk_mb_type_display" id="dmk_mb_type_display" class="frm_input" readonly value="지점 관리자">
                    <input type="hidden" name="dmk_mb_type" id="dmk_mb_type" value="<?php echo DMK_MB_TYPE_BRANCH; ?>">
                    <span class="frm_info">지점 관리자는 지점 서브관리자만 등록할 수 있습니다.</span>
                <?php } else { ?>
                    <input type="text" name="dmk_mb_type_display" id="dmk_mb_type_display" class="frm_input" readonly placeholder="소속 기관을 선택하면 자동으로 결정됩니다">
                    <input type="hidden" name="dmk_mb_type" id="dmk_mb_type" value="">
                    <span class="frm_info">소속 기관 선택에 따라 자동으로 관리자 유형이 결정됩니다.</span>
                <?php } ?>
            <?php } ?>
            <input type="hidden" name="mb_level" id="mb_level" value="<?php 
                if ($w != 'u' && $auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
                    echo DMK_MB_LEVEL_BRANCH;
                } else {
                    echo isset($member_info['mb_level']) ? $member_info['mb_level'] : '';
                }
            ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mb_memo">메모</label></th>
        <td>
            <textarea name="mb_memo" id="mb_memo" rows="5" class="frm_textbox"><?php echo isset($member_info['mb_memo']) ? $member_info['mb_memo'] : ''; ?></textarea>
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
// 체인 선택박스의 선택 상태에 따라 관리자 유형 자동 결정
jQuery(document).ready(function() {
    // 체인 선택박스 변경 이벤트 리스너
    jQuery('#dt_id, #ag_id, #br_id').on('change', function() {
        updateAdminType();
    });
    
    // 초기 로드 시에도 실행
    updateAdminType();
    
    function updateAdminType() {
        var dt_id = jQuery('#dt_id').val();
        var ag_id = jQuery('#ag_id').val();
        var br_id = jQuery('#br_id').val();
        
        var dmk_mb_type = '';
        var mb_level = '';
        var display_text = '';
        
        // 체인 선택 상태에 따른 관리자 유형 결정
        if (br_id) {
            // 지점이 선택된 경우: 지점 관리자
            dmk_mb_type = '<?php echo DMK_MB_TYPE_BRANCH; ?>';
            mb_level = '4';
            display_text = '지점 관리자';
        } else if (ag_id) {
            // 대리점이 선택된 경우: 대리점 관리자
            dmk_mb_type = '<?php echo DMK_MB_TYPE_AGENCY; ?>';
            mb_level = '6';
            display_text = '대리점 관리자';
        } else if (dt_id) {
            // 총판만 선택된 경우: 총판 관리자
            dmk_mb_type = '<?php echo DMK_MB_TYPE_DISTRIBUTOR; ?>';
            mb_level = '8';
            display_text = '총판 관리자';
        } else {
            // 아무것도 선택되지 않은 경우
            dmk_mb_type = '';
            mb_level = '';
            display_text = '';
        }
        
        // 관리자 유형 필드 업데이트
        jQuery('#dmk_mb_type').val(dmk_mb_type);
        jQuery('#mb_level').val(mb_level);
        jQuery('#dmk_mb_type_display').val(display_text);
        
        console.log('관리자 유형 업데이트:', {
            dt_id: dt_id,
            ag_id: ag_id,
            br_id: br_id,
            dmk_mb_type: dmk_mb_type,
            mb_level: mb_level,
            display_text: display_text
        });
    }
});

// 폼 제출 전 유효성 검사
function fmember_submit(f) {
    <?php if ($w != 'u') { // 신규 등록시에만 관리자 유형 검증 ?>
    // 관리자 유형이 설정되었는지 확인
    var dmk_mb_type = jQuery('#dmk_mb_type').val();
    
    <?php if ($auth['mb_type'] == DMK_MB_TYPE_BRANCH) { ?>
    // 지점 관리자의 경우 자동으로 지점 관리자 유형 설정
    if (!dmk_mb_type) {
        jQuery('#dmk_mb_type').val('<?php echo DMK_MB_TYPE_BRANCH; ?>');
        jQuery('#mb_level').val('<?php echo DMK_MB_LEVEL_BRANCH; ?>');
        jQuery('#dmk_mb_type_display').val('지점 관리자');
        dmk_mb_type = '<?php echo DMK_MB_TYPE_BRANCH; ?>';
    }
    <?php } else { ?>
    if (!dmk_mb_type) {
        alert("소속 기관을 선택하세요.");
        return false;
    }
    <?php } ?>
    <?php } ?>
    
    // 기존 유효성 검사 로직...
    <?php if ($w != 'u') { // 신규 등록시에만 ID 검증 ?>
    if (f.mb_id.value.length < 3) {
        alert("관리자 ID를 3글자 이상 입력하세요.");
        f.mb_id.focus();
        return false;
    }
    <?php } ?>
    
    <?php if ($w != 'u') { // 신규 등록시에만 비밀번호 검증 ?>
    var password = f.mb_password.value;
    var mb_id = f.mb_id ? f.mb_id.value : '';
    
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
    <?php } else { // 수정시 비밀번호 변경시에만 검증 ?>
    if (f.mb_password.value) {
        var password = f.mb_password.value;
        var mb_id = f.mb_id ? f.mb_id.value : '';
        
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
    }
    <?php } ?>
    
    if (f.mb_name.value.length < 2) {
        alert("이름을 2글자 이상 입력하세요.");
        f.mb_name.focus();
        return false;
    }
    
    if (f.mb_nick.value.length < 2) {
        alert("닉네임을 2글자 이상 입력하세요.");
        f.mb_nick.focus();
        return false;
    }
    
    if (f.mb_email.value.length < 5) {
        alert("이메일을 올바르게 입력하세요.");
        f.mb_email.focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 