<?php
$sub_menu = "190300";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
// 관리자 액션 로깅 라이브러리 포함
include_once(G5_DMK_PATH . "/adm/lib/admin.log.lib.php");

// 디버그: 데이터베이스 연결 테스트
$test_db_query = "SELECT COUNT(*) AS member_count FROM {$g5['member_table']}";
$test_db_result = sql_fetch($test_db_query);
var_dump("DB Test - Member Count: ", $test_db_result['member_count'] ?? 'N/A');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

dmk_auth_check_menu($auth, $sub_menu, 'w');

// 현재 로그인한 관리자 정보 (early definition)
$current_admin = dmk_get_admin_auth();

// 메뉴 접근 권한 확인
// dmk_authenticate_form_access 함수를 사용하여 통합 권한 체크
dmk_authenticate_form_access('branch_form', $w, $br_id);

check_demo();
check_admin_token();

// 지점 관련 상수 정의
define('DMK_BRANCH_MB_LEVEL', 4); // 적절한 레벨로 설정 (예: 대리점보다 낮은 레벨)
define('DMK_BRANCH_MB_TYPE', 3); // 3은 지점을 의미
define('DMK_BRANCH_ADMIN_TYPE', 'main');

$w = $_POST['w'];
$br_id = isset($_POST['br_id']) ? clean_xss_tags($_POST['br_id']) : '';

// 디버그: br_id 값 확인
var_dump("Parsed br_id: " . $br_id);

$ag_id = isset($_POST['ag_id']) ? clean_xss_tags($_POST['ag_id']) : '';
$br_shortcut_code = isset($_POST['br_shortcut_code']) ? clean_xss_tags($_POST['br_shortcut_code']) : '';
$br_status = isset($_POST['br_status']) ? (int)$_POST['br_status'] : 1;

// 지점 관리자가 자신의 지점을 수정하는 경우 대리점 ID 자동 설정
if ($w == 'u' && $current_admin['mb_type'] == DMK_MB_TYPE_BRANCH) {
    if (empty($ag_id) && !empty($current_admin['ag_id'])) {
        $ag_id = $current_admin['ag_id'];
    }
}

// 관리자 계정 정보 (g5_member 테이블에 저장될 필드)
$mb_password = isset($_POST['mb_password']) ? $_POST['mb_password'] : '';
$mb_password_confirm = isset($_POST['mb_password_confirm']) ? $_POST['mb_password_confirm'] : '';
$mb_name = isset($_POST['mb_name']) ? clean_xss_tags($_POST['mb_name']) : '';
$mb_nick = isset($_POST['mb_nick']) ? clean_xss_tags($_POST['mb_nick']) : ''; // 지점명으로 사용
$mb_email = isset($_POST['mb_email']) ? clean_xss_tags($_POST['mb_email']) : '';
$mb_tel = isset($_POST['mb_tel']) ? clean_xss_tags($_POST['mb_tel']) : '';
$mb_hp = isset($_POST['mb_hp']) ? clean_xss_tags($_POST['mb_hp']) : '';

// DEBUG: Post 데이터에서 mb_name 값 확인
var_dump("POST mb_name: ", $_POST['mb_name'] ?? 'N/A');

// 우편번호 처리 (앞자리, 뒷자리 분리)
$mb_zip = isset($_POST['mb_zip']) ? clean_xss_tags($_POST['mb_zip']) : '';

// 변수 초기화
$mb_zip1 = isset($_POST['mb_zip1']) ? clean_xss_tags($_POST['mb_zip1']) : '';
$mb_zip2 = isset($_POST['mb_zip2']) ? clean_xss_tags($_POST['mb_zip2']) : '';

$mb_addr1 = isset($_POST['mb_addr1']) ? clean_xss_tags($_POST['mb_addr1']) : '';
$mb_addr2 = isset($_POST['mb_addr2']) ? clean_xss_tags($_POST['mb_addr2']) : '';
$mb_addr3 = isset($_POST['mb_addr3']) ? clean_xss_tags($_POST['mb_addr3']) : '';
$mb_addr_jibeon = isset($_POST['mb_addr_jibeon']) ? clean_xss_tags($_POST['mb_addr_jibeon']) : '';

// 현재 로그인한 관리자 정보
$current_admin = dmk_get_admin_auth();

// 기본 유효성 검사
if (!$br_id) {
    alert('지점 ID를 입력하세요.');
    exit;
}

// 아이디 유효성 검증
$id_validation = dmk_validate_member_id($br_id);
if ($id_validation !== true) {
    alert($id_validation);
    exit;
}

if (!$ag_id) {
    alert('소속 대리점을 선택하세요.');
    exit;
}

if (!$mb_nick) {
    alert('지점명을 입력하세요.');
    exit;
}

if (!$mb_name) {
    alert('대표자명을 입력하세요.');
    exit;
}

if (!$mb_email) {
    alert('이메일을 입력하세요.');
    exit;
}

// 이메일 유효성 검사
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert('올바른 이메일 형식이 아닙니다.');
    exit;
}

// 단축 URL 코드 유효성 검사 (입력된 경우)
if ($br_shortcut_code && !preg_match('/^[a-zA-Z0-9_-]+$/', $br_shortcut_code)) {
    alert('단축 URL 코드는 영문, 숫자, 하이픈, 언더스코어만 사용 가능합니다.');
    exit;
}

// 대리점 존재 여부 및 권한 확인
$agency_sql = " SELECT a.ag_id, a.dt_id FROM dmk_agency a WHERE a.ag_id = '" . sql_escape_string($ag_id) . "' AND a.ag_status = 1 ";
$agency = sql_fetch($agency_sql);

if (!$agency) {
    alert('선택한 대리점이 존재하지 않거나 비활성 상태입니다.');
    exit;
}

// 권한 확인: 해당 대리점에 지점을 등록/수정할 권한이 있는지 체크
if (!$current_admin['is_super']) {
    if ($current_admin['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판은 자신의 대리점에만 지점 등록/수정 가능
        if ($agency['dt_id'] != $current_admin['mb_id']) {
            alert('해당 대리점에 지점을 등록/수정할 권한이 없습니다.');
            exit;
        }
    } else if ($current_admin['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점은 자신의 대리점에만 지점 등록/수정 가능
        if ($ag_id != $current_admin['ag_id']) {
            alert('해당 대리점에 지점을 등록/수정할 권한이 없습니다.');
            exit;
        }
    } else if ($current_admin['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자는 자신의 지점 정보만 수정할 수 있습니다.
        // 등록 모드이거나, 자신의 지점이 아닌 경우 접근 거부
        if ($w != 'u' || $br_id != $current_admin['mb_id']) {
            alert('지점 관리자는 자신의 지점 정보만 수정할 수 있습니다.');
            exit;
        }
        // 자신의 지점 수정인 경우, 이 지점에서 exit 하지 않고 다음 로직으로 진행
    }
}

if ($w == 'u') {
    // 수정
    $sql = " SELECT b.br_id, b.ag_id, a.dt_id
             FROM dmk_branch b
             JOIN dmk_agency a ON b.ag_id = a.ag_id
             WHERE b.br_id = '" . sql_escape_string($br_id) . "' ";
    
    $result = sql_query($sql);
    $existing_branch = sql_fetch_array($result);
    
    if (!$existing_branch) {
        alert('존재하지 않는 지점입니다.');
    }
    
    // 수정 권한 확인
    if (!dmk_can_modify_branch($br_id)) {
        alert('해당 지점을 수정할 권한이 없습니다.');
    }
    
    // 수정 모드에서는 총판 및 대리점 ID를 변경할 수 없으므로, 기존 값을 사용
    $dt_id_for_update = $existing_branch['dt_id'];
    $ag_id_for_update = $existing_branch['ag_id'];

    // 단축 URL 코드 중복 확인 (자신 제외)
    if ($br_shortcut_code) {
        $sql = " SELECT br_id FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' AND br_id != '" . sql_escape_string($br_id) . "' ";
        $row = sql_fetch($sql);
        
        if ($row) {
            alert('이미 사용중인 단축 URL 코드입니다.');
        }
    }
    
    // 비밀번호 변경 체크
    $mb_password_update = '';
    if ($mb_password) {
        // 비밀번호 유효성 검증
        $password_validation = dmk_validate_password($br_id, $mb_password, $mb_password_confirm);
        if ($password_validation !== true) {
            alert($password_validation);
            exit;
        }
        
        $mb_password_update = ", mb_password = '" . sql_password($mb_password) . "'";
    }
    
    // 이메일 중복 확인 (자신 제외)
    $sql = " SELECT mb_id FROM {$g5['member_table']} WHERE mb_email = '" . sql_escape_string($mb_email) . "' AND mb_id != '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 이메일입니다.');
    }

    // 지점 정보 업데이트
    // 지점 관리자는 dmk_branch 테이블의 정보를 수정할 수 없습니다.
    if ($current_admin['mb_type'] != DMK_MB_TYPE_BRANCH) {
        // 메시지 봇 정보 처리
        $br_message_bot_name = '';
        $br_message_device_id = '';
        if (!empty($_POST['br_message_bot'])) {
            $bot_parts = explode('|', $_POST['br_message_bot']);
            if (count($bot_parts) == 2) {
                $br_message_bot_name = clean_xss_tags($bot_parts[0]);
                $br_message_device_id = clean_xss_tags($bot_parts[1]);
            }
        }
        
        // 체크박스 값 처리 (체크되지 않으면 0으로 저장)
        $br_order_placed_msg_enabled = isset($_POST['br_order_placed_msg_enabled']) ? 1 : 0;
        $br_order_msg_enabled = isset($_POST['br_order_msg_enabled']) ? 1 : 0;
        $br_stock_warning_msg_enabled = isset($_POST['br_stock_warning_msg_enabled']) ? 1 : 0;
        $br_stock_out_msg_enabled = isset($_POST['br_stock_out_msg_enabled']) ? 1 : 0;
        $br_stock_warning_qty = isset($_POST['br_stock_warning_qty']) ? (int)$_POST['br_stock_warning_qty'] : 10;
        
        // 발송 지연 시간 처리 (분 단위, 0~1440)
        $br_order_placed_msg_delay = isset($_POST['br_order_placed_msg_delay']) ? max(0, min(1440, (int)$_POST['br_order_placed_msg_delay'])) : 0;
        $br_order_msg_delay = isset($_POST['br_order_msg_delay']) ? max(0, min(1440, (int)$_POST['br_order_msg_delay'])) : 5;
        $br_stock_warning_msg_delay = isset($_POST['br_stock_warning_msg_delay']) ? max(0, min(1440, (int)$_POST['br_stock_warning_msg_delay'])) : 10;
        $br_stock_out_msg_delay = isset($_POST['br_stock_out_msg_delay']) ? max(0, min(1440, (int)$_POST['br_stock_out_msg_delay'])) : 5;
        
        // 주문페이지 스킨
        $br_order_page_skin = isset($_POST['br_order_page_skin']) ? clean_xss_tags($_POST['br_order_page_skin']) : 'basic_1col';
        
        // 스킨 옵션은 이제 사용하지 않음 (레이아웃이 스킨 이름에 포함됨)
        $br_order_page_skin_options = '';
        
        $sql = " UPDATE dmk_branch SET 
                    ag_id = '" . sql_escape_string($ag_id_for_update) . "',
                    br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "',
                    br_status = $br_status,
                    br_order_placed_msg_template = '" . sql_escape_string($_POST['br_order_placed_msg_template']) . "',
                    br_order_msg_template = '" . sql_escape_string($_POST['br_order_msg_template']) . "',
                    br_stock_warning_msg_template = '" . sql_escape_string($_POST['br_stock_warning_msg_template']) . "',
                    br_stock_out_msg_template = '" . sql_escape_string($_POST['br_stock_out_msg_template']) . "',
                    br_stock_warning_qty = " . $br_stock_warning_qty . ",
                    br_order_placed_msg_enabled = " . $br_order_placed_msg_enabled . ",
                    br_order_msg_enabled = " . $br_order_msg_enabled . ",
                    br_stock_warning_msg_enabled = " . $br_stock_warning_msg_enabled . ",
                    br_stock_out_msg_enabled = " . $br_stock_out_msg_enabled . ",
                    br_order_placed_msg_delay = " . $br_order_placed_msg_delay . ",
                    br_order_msg_delay = " . $br_order_msg_delay . ",
                    br_stock_warning_msg_delay = " . $br_stock_warning_msg_delay . ",
                    br_stock_out_msg_delay = " . $br_stock_out_msg_delay . ",
                    br_message_bot_name = '" . sql_escape_string($br_message_bot_name) . "',
                    br_message_device_id = '" . sql_escape_string($br_message_device_id) . "',
                    br_order_page_skin = '" . sql_escape_string($br_order_page_skin) . "',
                    br_order_page_skin_options = '" . sql_escape_string($br_order_page_skin_options) . "'
                 WHERE br_id = '" . sql_escape_string($br_id) . "' ";
        sql_query($sql);
    }
    
    // g5_member 테이블의 관리자 정보 업데이트 (br_id를 mb_id로 사용)
    $member_update_fields = array();

    // 비밀번호는 변경 요청이 있을 때만 업데이트
    if ($mb_password_update) {
        $member_update_fields[] = substr($mb_password_update, 2); // ", " 제거
    }

    // 지점 관리자는 특정 필드만 수정 가능
    $member_update_fields[] = "mb_name = '" . sql_escape_string($mb_name) . "'";
    $member_update_fields[] = "mb_email = '" . sql_escape_string($mb_email) . "'";
    $member_update_fields[] = "mb_tel = '" . sql_escape_string($mb_tel) . "'";
    $member_update_fields[] = "mb_hp = '" . sql_escape_string($mb_hp) . "'";
    $member_update_fields[] = "mb_zip1 = '" . sql_escape_string($mb_zip1) . "'";
    $member_update_fields[] = "mb_zip2 = '" . sql_escape_string($mb_zip2) . "'";
    $member_update_fields[] = "mb_addr1 = '" . sql_escape_string($mb_addr1) . "'";
    $member_update_fields[] = "mb_addr2 = '" . sql_escape_string($mb_addr2) . "'";
    $member_update_fields[] = "mb_addr3 = '" . sql_escape_string($mb_addr3) . "'";
    $member_update_fields[] = "mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "'";

    // 업데이트할 필드가 하나라도 있는 경우에만 쿼리 실행
    if (!empty($member_update_fields)) {
        $sql = " UPDATE {$g5['member_table']} SET "
             . implode(', ', $member_update_fields)
             . " WHERE mb_id = '" . sql_escape_string($br_id) . "' ";
        // DEBUG: g5_member 업데이트 쿼리 확인
        var_dump("g5_member Update SQL: ", $sql);
        sql_query($sql);
    }
    
    // 관리자 액션 로깅
    dmk_log_admin_action('branch_modify', $br_id, '지점 정보 수정: ' . $mb_nick, $sub_menu);
    
    goto_url('./branch_form.php?w=u&br_id='.$br_id);
    
    exit;
} else {
    // 등록
    
    // g5_member 테이블에 이미 존재하는 ID인지 확인 (지점 ID 사용)
    $row = sql_fetch(" SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($br_id) . "' ");
    if ($row && isset($row['mb_id'])) {
        alert('이미 존재하는 회원 아이디입니다. 다른 지점 ID를 사용해주세요.');
        exit;
    }

    if (!$mb_password) {
        alert('비밀번호를 입력하세요.');
        exit;
    }
    
    // 비밀번호 유효성 검증
    $password_validation = dmk_validate_password($br_id, $mb_password, $mb_password_confirm);
    if ($password_validation !== true) {
        alert($password_validation);
        exit;
    }
    
    // 지점 ID 중복 확인 (dmk_branch 테이블)
    $sql = " SELECT br_id FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' ";
    $row = sql_fetch($sql);
    
    if ($row) {
        alert('이미 사용중인 지점 ID입니다. 다른 지점 ID를 사용해주세요.');
        exit;
    }
    
    // 단축 URL 코드 자동 생성 또는 사용자가 입력한 값 사용
    if (empty($br_shortcut_code)) {
        // 고유한 단축 URL 코드 생성
        do {
            $br_shortcut_code = generate_unique_code(8); // 8자리 코드 생성
            $sql = " SELECT br_id FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' ";
            $row = sql_fetch($sql);
        } while ($row);
    } else {
        // 사용자가 입력한 단축 URL 코드 중복 확인
        $sql = " SELECT br_id FROM dmk_branch WHERE br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "' ";
        $row = sql_fetch($sql);
        if ($row) {
            alert('이미 사용중인 단축 URL 코드입니다.');
            exit;
        }
    }

    // g5_member 테이블에 관리자 계정 정보 추가 (br_id를 mb_id로 사용)
    // 비밀번호 암호화
    $sql_password = sql_password($mb_password);

    // 새로운 회원 정보 삽입
    $sql = " INSERT INTO {$g5['member_table']} SET 
                mb_id = '" . sql_escape_string($br_id) . "',
                mb_password = '$sql_password',
                mb_name = '" . sql_escape_string($mb_name) . "',
                mb_nick = '" . sql_escape_string($mb_nick) . "',
                mb_email = '" . sql_escape_string($mb_email) . "',
                mb_tel = '" . sql_escape_string($mb_tel) . "',
                mb_hp = '" . sql_escape_string($mb_hp) . "',
                mb_zip1 = '" . sql_escape_string($mb_zip1) . "',
                mb_zip2 = '" . sql_escape_string($mb_zip2) . "',
                mb_addr1 = '" . sql_escape_string($mb_addr1) . "',
                mb_addr2 = '" . sql_escape_string($mb_addr2) . "',
                mb_addr3 = '" . sql_escape_string($mb_addr3) . "',
                mb_addr_jibeon = '" . sql_escape_string($mb_addr_jibeon) . "',
                mb_datetime = now(),
                mb_ip = '" . sql_escape_string($REMOTE_ADDR) . "',
                mb_level = " . DMK_BRANCH_MB_LEVEL . ",
                dmk_mb_type = " . DMK_BRANCH_MB_TYPE . ",
                dmk_dt_id = '" . sql_escape_string($agency['dt_id']) . "',
                dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                dmk_br_id = '" . sql_escape_string($br_id) . "',
                dmk_admin_type = '" . DMK_BRANCH_ADMIN_TYPE . "';";
    sql_query($sql);

    // 메시지 봇 정보 처리
    $br_message_bot_name = '';
    $br_message_device_id = '';
    if (!empty($_POST['br_message_bot'])) {
        $bot_parts = explode('|', $_POST['br_message_bot']);
        if (count($bot_parts) == 2) {
            $br_message_bot_name = clean_xss_tags($bot_parts[0]);
            $br_message_device_id = clean_xss_tags($bot_parts[1]);
        }
    }
    
    // 체크박스 값 처리 (체크되지 않으면 0으로 저장)
    $br_order_placed_msg_enabled = isset($_POST['br_order_placed_msg_enabled']) ? 1 : 0;
    $br_order_msg_enabled = isset($_POST['br_order_msg_enabled']) ? 1 : 0;
    $br_stock_warning_msg_enabled = isset($_POST['br_stock_warning_msg_enabled']) ? 1 : 0;
    $br_stock_out_msg_enabled = isset($_POST['br_stock_out_msg_enabled']) ? 1 : 0;
    $br_stock_warning_qty = isset($_POST['br_stock_warning_qty']) ? (int)$_POST['br_stock_warning_qty'] : 10;
    
    // 발송 지연 시간 처리 (분 단위, 0~1440)
    $br_order_placed_msg_delay = isset($_POST['br_order_placed_msg_delay']) ? max(0, min(1440, (int)$_POST['br_order_placed_msg_delay'])) : 0;
    $br_order_msg_delay = isset($_POST['br_order_msg_delay']) ? max(0, min(1440, (int)$_POST['br_order_msg_delay'])) : 5;
    $br_stock_warning_msg_delay = isset($_POST['br_stock_warning_msg_delay']) ? max(0, min(1440, (int)$_POST['br_stock_warning_msg_delay'])) : 10;
    $br_stock_out_msg_delay = isset($_POST['br_stock_out_msg_delay']) ? max(0, min(1440, (int)$_POST['br_stock_out_msg_delay'])) : 5;
    
    // 주문페이지 스킨
    $br_order_page_skin = isset($_POST['br_order_page_skin']) ? clean_xss_tags($_POST['br_order_page_skin']) : 'basic_1col';
    
    // 스킨 옵션은 이제 사용하지 않음 (레이아웃이 스킨 이름에 포함됨)
    $br_order_page_skin_options = '';
    
    // dmk_branch 테이블에 지점 정보 등록
    $sql = " INSERT INTO dmk_branch SET 
                br_id = '" . sql_escape_string($br_id) . "',
                ag_id = '" . sql_escape_string($ag_id) . "',
                br_shortcut_code = '" . sql_escape_string($br_shortcut_code) . "',
                br_status = '" . sql_escape_string($br_status) . "',
                br_created_by = '" . sql_escape_string($current_admin['mb_id']) . "',
                br_admin_type = '" . DMK_BRANCH_ADMIN_TYPE . "',
                br_order_placed_msg_template = '" . sql_escape_string($_POST['br_order_placed_msg_template']) . "',
                br_order_msg_template = '" . sql_escape_string($_POST['br_order_msg_template']) . "',
                br_stock_warning_msg_template = '" . sql_escape_string($_POST['br_stock_warning_msg_template']) . "',
                br_stock_out_msg_template = '" . sql_escape_string($_POST['br_stock_out_msg_template']) . "',
                br_stock_warning_qty = " . $br_stock_warning_qty . ",
                br_order_placed_msg_enabled = " . $br_order_placed_msg_enabled . ",
                br_order_msg_enabled = " . $br_order_msg_enabled . ",
                br_stock_warning_msg_enabled = " . $br_stock_warning_msg_enabled . ",
                br_stock_out_msg_enabled = " . $br_stock_out_msg_enabled . ",
                br_order_placed_msg_delay = " . $br_order_placed_msg_delay . ",
                br_order_msg_delay = " . $br_order_msg_delay . ",
                br_stock_warning_msg_delay = " . $br_stock_warning_msg_delay . ",
                br_stock_out_msg_delay = " . $br_stock_out_msg_delay . ",
                br_message_bot_name = '" . sql_escape_string($br_message_bot_name) . "',
                br_message_device_id = '" . sql_escape_string($br_message_device_id) . "',
                br_order_page_skin = '" . sql_escape_string($br_order_page_skin) . "',
                br_order_page_skin_options = '" . sql_escape_string($br_order_page_skin_options) . "';";
    sql_query($sql);

    // 관리자 액션 로깅
    dmk_log_admin_action('insert', '지점 등록', '지점ID: '.$br_id, json_encode($_POST), null, 'branch_form', 'g5_member,dmk_branch');
    

    goto_url('./branch_form.php');
    exit;
}

// 고유 코드 생성 함수 (짧은 URL 등에 사용)
function generate_unique_code($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

?>