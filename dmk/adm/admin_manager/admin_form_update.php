<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

check_admin_token();

$w = isset($_POST['w']) ? clean_xss_tags($_POST['w']) : '';
$mb_id = isset($_POST['mb_id']) ? clean_xss_tags($_POST['mb_id']) : '';
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_password_re = isset($_POST['mb_password_re']) ? $_POST['mb_password_re'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_nick = isset($_POST['mb_nick']) ? clean_xss_tags($_POST['mb_nick']) : '';
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_hp = isset($_POST['mb_hp']) ? clean_xss_tags($_POST['mb_hp']) : '';
$dmk_mb_type = isset($_POST['dmk_mb_type']) ? (int)$_POST['dmk_mb_type'] : 0;
$mb_level = isset($_POST['mb_level']) ? (int)$_POST['mb_level'] : 0;
$mb_memo = isset($_POST['mb_memo']) ? clean_xss_tags($_POST['mb_memo']) : '';

$dmk_auth = dmk_get_admin_auth();

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

if (!$dmk_mb_type) {
    alert('관리자 유형을 선택하세요1.');
}

// dmk_mb_type에 따른 mb_level 설정
switch ($dmk_mb_type) {
    case 1: // 총판
        $mb_level = DMK_MB_LEVEL_DISTRIBUTOR;
        break;
    case 2: // 대리점
        $mb_level = DMK_MB_LEVEL_AGENCY;
        break;
    case 3: // 지점
        $mb_level = DMK_MB_LEVEL_BRANCH;
        break;
    default:
        alert('올바른 관리자 유형을 선택하세요.');
}

// 권한 체크
$can_create_level = false;
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

if ($w == '') {
    // 신규 등록
    
    // 비밀번호 검증
    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
    }
    
    if ($mb_password != $mb_password_re) {
        alert('비밀번호가 일치하지 않습니다.');
    }
    
    if (strlen($mb_password) < 3) {
        alert('비밀번호를 3글자 이상 입력하세요.');
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
                mb_memo = '".sql_escape_string($mb_memo)."',
                mb_datetime = '".G5_TIME_YMDHIS."',
                mb_ip = '".sql_escape_string($_SERVER['REMOTE_ADDR'])."',
                mb_email_certify = '".G5_TIME_YMDHIS."' ";
    
    sql_query($sql);
    
    alert('관리자가 등록되었습니다.', './admin_list.php');
    
} elseif ($w == 'u') {
    // 수정
    
    // 기존 데이터 조회
    $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' AND mb_level >= 4 ";
    $member = sql_fetch($sql);
    
    if (!$member) {
        alert('존재하지 않는 관리자입니다.');
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
    
    // 관리자 유형 변경 방지
    // 기존 관리자의 dmk_mb_type을 조회
    $sql_type = " SELECT dmk_mb_type FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($mb_id)."' ";
    $existing_type_row = sql_fetch($sql_type);

    if ($existing_type_row && $existing_type_row['dmk_mb_type'] != $dmk_mb_type) {
        alert('관리자 유형은 수정할 수 없습니다.');
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
        if ($mb_password != $mb_password_re) {
            alert('비밀번호가 일치하지 않습니다.');
        }
        
        if (strlen($mb_password) < 3) {
            alert('비밀번호를 3글자 이상 입력하세요.');
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
                mb_level = '".sql_escape_string($mb_level)."',
                dmk_mb_type = '".sql_escape_string($dmk_mb_type)."',
                mb_memo = '".sql_escape_string($mb_memo)."'
                {$sql_password}
             WHERE mb_id = '".sql_escape_string($mb_id)."' ";
    
    sql_query($sql);
    
    // 메시지 없이 현재 폼으로 돌아가기 (목록 정보 유지)
    goto_url('./admin_form.php?w=u&mb_id='.urlencode($mb_id).'&sfl='.urlencode($sfl).'&stx='.urlencode($stx).'&sst='.urlencode($sst).'&sod='.urlencode($sod).'&page='.urlencode($page));
}
?> 