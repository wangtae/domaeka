<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

check_admin_token();

$w = isset($_POST['w']) ? clean_xss_tags($_POST['w']) : '';

// 디버깅: 요청 타입과 주요 변수 확인
error_log("Admin form update - w: " . ($w ?? 'empty') . ", Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data keys: " . implode(', ', array_keys($_POST)));
$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_password_confirm = isset($_POST['mb_password_confirm']) ? $_POST['mb_password_confirm'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_nick = isset($_POST['mb_nick']) ? clean_xss_tags($_POST['mb_nick']) : '';
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_hp = isset($_POST['mb_hp']) ? clean_xss_tags($_POST['mb_hp']) : '';
$dmk_mb_type = isset($_POST['dmk_mb_type']) ? (int)$_POST['dmk_mb_type'] : 0;
$mb_level = isset($_POST['mb_level']) ? (int)$_POST['mb_level'] : 0;
$mb_memo = isset($_POST['mb_memo']) ? clean_xss_tags($_POST['mb_memo']) : '';

// URL 파라미터 캡처 (목록 페이지로 돌아갈 때 사용)
$sfl = isset($_POST['sfl']) ? clean_xss_tags($_POST['sfl']) : '';
$stx = isset($_POST['stx']) ? clean_xss_tags($_POST['stx']) : '';
$sst = isset($_POST['sst']) ? clean_xss_tags($_POST['sst']) : '';
$sod = isset($_POST['sod']) ? clean_xss_tags($_POST['sod']) : '';
$page = isset($_POST['page']) ? clean_xss_tags($_POST['page']) : '';

// 체인 선택박스에서 전송되는 소속 기관 정보
$dt_id = isset($_POST['dt_id']) ? clean_xss_tags($_POST['dt_id']) : '';
$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$br_id = isset($_POST['br_id']) ? clean_xss_tags($_POST['br_id']) : '';

$dmk_auth = dmk_get_admin_auth();

// 대리점 관리자가 서브 관리자를 등록하는 경우 자동으로 계층 정보 설정
if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && $w == '') {
    if (empty($dt_id) && !empty($dmk_auth['dt_id'])) {
        $dt_id = $dmk_auth['dt_id'];
    }
    if (empty($ag_id) && !empty($dmk_auth['ag_id'])) {
        $ag_id = $dmk_auth['ag_id'];
    }
}

// 지점 관리자가 서브 관리자를 등록하는 경우 자동으로 계층 정보 설정
if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH && $w == '') {
    if (empty($dt_id) && !empty($dmk_auth['dt_id'])) {
        $dt_id = $dmk_auth['dt_id'];
    }
    if (empty($ag_id) && !empty($dmk_auth['ag_id'])) {
        $ag_id = $dmk_auth['ag_id'];
    }
    if (empty($br_id) && !empty($dmk_auth['br_id'])) {
        $br_id = $dmk_auth['br_id'];
    }
}

// 입력값 검증
if (!$mb_id) {
    alert('아이디를 입력하세요.');
}

if (!$mb_name) {
    alert('이름을 입력하세요.');
}

if (!$mb_nick) {
    alert('닉네임을 입력하세요.');
}

if (!$mb_email) {
    alert('이메일을 입력하세요.');
}

if ($w == '' && !$dmk_mb_type) { // 신규 등록일 때만 관리자 유형 검사
    alert('관리자 유형을 선택하세요.');
}

// 체인 선택박스의 소속 기관 정보에 따른 관리자 유형 자동 결정 (신규 등록 시에만)
if ($w == '' && !$dmk_mb_type) {
    if ($br_id) {
        // 지점이 선택된 경우: 지점 관리자
        $dmk_mb_type = DMK_MB_TYPE_BRANCH;
        $mb_level = DMK_MB_LEVEL_BRANCH;
    } elseif ($ag_id) {
        // 대리점이 선택된 경우: 대리점 관리자
        $dmk_mb_type = DMK_MB_TYPE_AGENCY;
        $mb_level = DMK_MB_LEVEL_AGENCY;
    } elseif ($dt_id) {
        // 총판만 선택된 경우: 총판 관리자
        $dmk_mb_type = DMK_MB_TYPE_DISTRIBUTOR;
        $mb_level = DMK_MB_LEVEL_DISTRIBUTOR;
    } else {
        alert('소속 기관을 선택하세요.');
    }
}

// dmk_mb_type에 따른 mb_level 설정 (신규 등록 시에만)
if ($w == '') {
switch ($dmk_mb_type) {
        case DMK_MB_TYPE_DISTRIBUTOR: // 총판
        $mb_level = DMK_MB_LEVEL_DISTRIBUTOR;
        break;
        case DMK_MB_TYPE_AGENCY: // 대리점
        $mb_level = DMK_MB_LEVEL_AGENCY;
        break;
        case DMK_MB_TYPE_BRANCH: // 지점
        $mb_level = DMK_MB_LEVEL_BRANCH;
        break;
    default:
        alert('올바른 관리자 유형을 선택하세요.');
    }
}

// 권한 체크 (신규 등록 시에만)
if ($w == '') {
$can_create_level = false;

    // Debugging: 권한 체크 전 주요 변수 값 확인
    error_log("DMK_AUTH_UPDATE: 로그인 사용자 mb_level: " . ($dmk_auth['mb_level'] ?? 'N/A'));
    error_log("DMK_AUTH_UPDATE: 생성하려는 관리자 mb_level: " . ($mb_level ?? 'N/A'));
    error_log("DMK_AUTH_UPDATE: 생성하려는 관리자 dmk_mb_type: " . ($dmk_mb_type ?? 'N/A'));
    error_log("DMK_AUTH_UPDATE: 로그인 사용자 is_super: " . ($dmk_auth['is_super'] ? 'true' : 'false'));

if ($dmk_auth['is_super']) {
    $can_create_level = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $mb_level <= DMK_MB_LEVEL_DISTRIBUTOR) {
    $can_create_level = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $mb_level <= DMK_MB_LEVEL_AGENCY) {
    $can_create_level = true;
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $mb_level == DMK_MB_LEVEL_BRANCH) {
    $can_create_level = true;
}

if (!$can_create_level) {
    alert('해당 레벨의 관리자를 생성할 권한이 없습니다.');
    }
}

if ($w == '') {
    // 신규 등록
    
    // 비밀번호 검증
    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
    }
    
    // 비밀번호 유효성 검증
    $password_validation = dmk_validate_password($mb_id, $mb_password, $mb_password_confirm);
    if ($password_validation !== true) {
        alert($password_validation);
        exit;
    }
    
    // 아이디 중복 체크
    $sql = " SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 사용중인 아이디입니다.');
    }
    
    // 닉네임 중복 체크
    $sql = " SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_nick = '".sql_escape_string($mb_nick)."' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 사용중인 닉네임입니다.');
    }
    
    // 이메일 중복 체크
    $sql = " SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_email = '".sql_escape_string($mb_email)."' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 사용중인 이메일입니다.');
    }
    
    // 비밀번호 암호화
    $mb_password_hash = password_hash($mb_password, PASSWORD_DEFAULT);
    
    // 디버깅: 최종 계층 정보 확인
    error_log("Final hierarchy info - dt_id: " . ($dt_id ?? 'empty') . ", ag_id: " . ($ag_id ?? 'empty') . ", br_id: " . ($br_id ?? 'empty'));
    
    // 회원 등록
    $sql = " INSERT INTO {$g5['member_table']} SET
                mb_id = '".sql_escape_string($mb_id)."',
                mb_password = '".sql_escape_string($mb_password_hash)."',
                mb_name = '".sql_escape_string($mb_name)."',
                mb_nick = '".sql_escape_string($mb_nick)."',
                mb_email = '".sql_escape_string($mb_email)."',
                mb_hp = '".sql_escape_string($mb_hp)."',
                mb_level = '".sql_escape_string($mb_level)."',
                dmk_mb_type = '".sql_escape_string($dmk_mb_type)."',
                dmk_dt_id = '".sql_escape_string($dt_id)."',
                dmk_ag_id = '".sql_escape_string($ag_id)."',
                dmk_br_id = '".sql_escape_string($br_id)."',
                dmk_admin_type = 'sub',
                mb_memo = '".sql_escape_string($mb_memo)."',
                mb_datetime = '".G5_TIME_YMDHIS."',
                mb_ip = '".sql_escape_string($_SERVER['REMOTE_ADDR'])."',
                mb_email_certify = '".G5_TIME_YMDHIS."' ";
    
    $result = sql_query($sql);
    
    if (!$result) {
        alert('서브관리자 등록에 실패했습니다. 다시 시도해주세요.');
        exit;
    }
    
    // 관리자 액션 로깅
    dmk_log_admin_action(
        'insert',
        '서브관리자 등록: ' . $mb_id,
        'g5_member',
        json_encode([
            'mb_id' => $mb_id,
            'mb_name' => $mb_name,
            'mb_nick' => $mb_nick,
            'dmk_mb_type' => $dmk_mb_type,
            'mb_level' => $mb_level,
            'dmk_dt_id' => $dt_id,
            'dmk_ag_id' => $ag_id,
            'dmk_br_id' => $br_id
        ]),
        null,
        '190600'
    );
    
    goto_url('./admin_list.php');
    exit;
    
} elseif ($w == 'u') {
    // 수정
    
    // 기존 데이터 조회
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' AND mb_level >= 4 ";
    $member = sql_fetch($sql);
    
    if (!$member) {
        alert('존재하지 않는 관리자입니다.');
    }
    
    // 수정 모드에서는 기존 데이터 사용
    $dmk_mb_type = $member['dmk_mb_type'];
    $mb_level = $member['mb_level'];
    $dt_id = $member['dmk_dt_id'];
    $ag_id = $member['dmk_ag_id'];
    $br_id = $member['dmk_br_id'];
    
    // 대리점 관리자가 서브 관리자를 수정하는 경우 계층 정보 보안 처리
    // 폼에서 dt_id가 전송되지 않았지만 현재 관리자의 정보를 사용해야 함
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 총판 정보만 사용 가능
        $dt_id = $dmk_auth['dt_id'];
    }
    
    // 지점 관리자가 서브 관리자를 수정하는 경우 계층 정보 보안 처리
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자는 자신의 총판/대리점 정보만 사용 가능
        $dt_id = $dmk_auth['dt_id'];
        $ag_id = $dmk_auth['ag_id'];
    }
    
    // 수정 권한 체크
    $can_modify = false;
    if ($dmk_auth['is_super']) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $member['mb_level'] <= DMK_MB_LEVEL_DISTRIBUTOR) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $member['mb_level'] <= DMK_MB_LEVEL_AGENCY) {
        $can_modify = true;
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $member['mb_level'] == DMK_MB_LEVEL_BRANCH) {
        $can_modify = true;
    }
    
    if (!$can_modify) {
        alert('수정 권한이 없습니다.');
    }

    // 닉네임 중복 체크 (자신 제외)
    $sql = " SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_nick = '".sql_escape_string($mb_nick)."' AND mb_id != '".sql_escape_string($mb_id)."' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 사용중인 닉네임입니다.');
    }
    
    // 이메일 중복 체크 (자신 제외)
    $sql = " SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_email = '".sql_escape_string($mb_email)."' AND mb_id != '".sql_escape_string($mb_id)."' ";
    $row = sql_fetch($sql);
    if ($row['cnt']) {
        alert('이미 사용중인 이메일입니다.');
    }
    
    // 업데이트 쿼리 구성
    $sql_password = '';
    if ($mb_password) {
        // 비밀번호 유효성 검증
        $password_validation = dmk_validate_password($mb_id, $mb_password, $mb_password_confirm);
        if ($password_validation !== true) {
            alert($password_validation);
            exit;
        }
        
        $mb_password_hash = password_hash($mb_password, PASSWORD_DEFAULT);
        $sql_password = ", mb_password = '".sql_escape_string($mb_password_hash)."' ";
    }
    
    // 회원 정보 수정
    $sql = " UPDATE {$g5['member_table']} SET
                mb_name = '".sql_escape_string($mb_name)."',
                mb_nick = '".sql_escape_string($mb_nick)."',
                mb_email = '".sql_escape_string($mb_email)."',
                mb_hp = '".sql_escape_string($mb_hp)."',
                mb_memo = '".sql_escape_string($mb_memo)."'
                {$sql_password}
             WHERE mb_id = '".sql_escape_string($mb_id)."' ";
    
    error_log("Update SQL: " . $sql);
    $result = sql_query($sql);
    
    if (!$result) {
        error_log("SQL update failed for member: " . $mb_id);
        alert('서브관리자 수정에 실패했습니다. 다시 시도해주세요.');
        exit;
    }
    
    error_log("Successfully updated member: " . $mb_id);
    
    // 관리자 액션 로깅
    dmk_log_admin_action(
        'update',
        '서브관리자 수정: ' . $mb_id,
        'g5_member',
        json_encode([
            'mb_id' => $mb_id,
            'mb_name' => $mb_name,
            'mb_nick' => $mb_nick,
            'dmk_mb_type' => $dmk_mb_type,
            'mb_level' => $mb_level,
            'dmk_dt_id' => $dt_id,
            'dmk_ag_id' => $ag_id,
            'dmk_br_id' => $br_id
        ]),
        null,
        '190600'
    );
    
    // 수정 완료 후 목록으로 이동
    $redirect_url = './admin_list.php?sfl='.urlencode($sfl).'&stx='.urlencode($stx).'&sst='.urlencode($sst).'&sod='.urlencode($sod).'&page='.urlencode($page);
    error_log("Redirecting to: " . $redirect_url);
    goto_url($redirect_url);
    exit;
} else {
    alert('잘못된 접근입니다.');
    exit;
}
?> 