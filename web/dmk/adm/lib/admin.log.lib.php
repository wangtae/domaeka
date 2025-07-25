<?php
/**
 * Domaeka 관리자 액션 로깅 라이브러리
 *
 * 관리자의 모든 중요한 액션을 dmk_action_logs 테이블에 기록합니다.
 *
 * @author Domaeka Development Team
 * @version 1.0
 * @since 2024-05-27
 */

if (!defined('_GNUBOARD_')) exit; // 개별 접근 차단

// 도매까 전역 설정 포함 (필요한 경우)
// include_once(G5_PATH . '/dmk/dmk_global_settings.php');

/**
 * 관리자 액션을 로그에 기록합니다.
 *
 * @param string $action_type 수행된 액션의 유형 (예: 'insert', 'edit', 'delete', 'login')
 * @param string $action_detail 액션에 대한 상세 설명 (예: '총판 등록', '총판 정보 수정')
 * @param string|null $target_id 액션 대상의 ID (예: '총판ID: domaeka', '회원ID: user1')
 * @param string|null $new_data 액션 후 변경된 데이터 (JSON 문자열)
 * @param string|null $old_data 액션 전 원본 데이터 (JSON 문자열)
 * @param string|null $menu_code 관련 메뉴 코드 (예: 'distributor_form', 'item_list')
 * @param string|null $target_table 액션이 발생한 테이블 (예: 'g5_member', 'dmk_distributor')
 * @return bool 로그 기록 성공 여부
 */
function dmk_log_admin_action($action_type, $action_detail, $target_id = null, $new_data = null, $old_data = null, $menu_code = null, $target_table = null) {
    global $g5, $member, $REMOTE_ADDR;

    // 로그인된 관리자 ID가 없으면 로그 기록 안함
    if (!$member || empty($member['mb_id'])) {
        return false;
    }

    $mb_id = sql_escape_string($member['mb_id']);
    $action_type = sql_escape_string($action_type);
    $action_detail = sql_escape_string($action_detail);
    $target_id = $target_id ? sql_escape_string($target_id) : '';
    $action_ip = sql_escape_string($REMOTE_ADDR);

    // new_data, old_data는 text 타입이므로, 문자열로 저장
    $new_data_escaped = $new_data ? sql_escape_string($new_data) : '';
    $old_data_escaped = $old_data ? sql_escape_string($old_data) : '';

    $menu_code_escaped = $menu_code ? sql_escape_string($menu_code) : '';
    $target_table_escaped = $target_table ? sql_escape_string($target_table) : '';

    $sql = " INSERT INTO dmk_action_logs SET
                mb_id = '{$mb_id}',
                action_type = '{$action_type}',
                menu_code = '{$menu_code_escaped}',
                target_table = '{$target_table_escaped}',
                target_id = '{$target_id}',
                old_data = '{$old_data_escaped}',
                new_data = '{$new_data_escaped}',
                action_detail = '{$action_detail}',
                action_ip = '{$action_ip}',
                log_datetime = now() ";

    return sql_query($sql);
}

/**
 * 기존 데이터와 새로운 데이터를 비교하여 변경된 필드를 추출합니다.
 *
 * @param array $old_data 기존 데이터 배열
 * @param array $new_data 새로운 데이터 배열
 * @param array $ignore_fields 비교에서 제외할 필드명 배열 (기본값: 시간, 암호 등)
 * @return array 변경된 필드 정보 배열
 */
function dmk_compare_data_changes($old_data, $new_data, $ignore_fields = []) {
    // 기본 제외 필드 (시간, 암호 관련)
    $default_ignore_fields = [
        'mb_datetime', 'mb_password', 'mb_password_q', 'mb_password_a',
        'mb_access_time', 'mb_modify_time', 'created_at', 'updated_at',
        'log_datetime', 'token', 'session_id'
    ];
    
    $ignore_fields = array_merge($default_ignore_fields, $ignore_fields);
    $changes = [];
    
    // 배열이 아닌 경우 처리
    if (!is_array($old_data)) $old_data = [];
    if (!is_array($new_data)) $new_data = [];
    
    // 모든 필드 체크 (기존 + 새로운)
    $all_fields = array_unique(array_merge(array_keys($old_data), array_keys($new_data)));
    
    foreach ($all_fields as $field) {
        // 제외 필드는 건너뛰기
        if (in_array($field, $ignore_fields)) {
            continue;
        }
        
        $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
        $new_value = isset($new_data[$field]) ? $new_data[$field] : '';
        
        // 값이 다른 경우만 변경사항으로 기록
        if ((string)$old_value !== (string)$new_value) {
            $changes[$field] = [
                'old' => $old_value,
                'new' => $new_value
            ];
        }
    }
    
    return $changes;
}

/**
 * 변경된 내용을 사용자 친화적인 형태로 포맷팅합니다.
 *
 * @param array $changes dmk_compare_data_changes()에서 반환된 변경사항 배열
 * @param array $field_labels 필드명을 한글 라벨로 변환하는 배열
 * @return string 포맷팅된 변경 내용 문자열
 */
function dmk_format_data_changes($changes, $field_labels = []) {
    if (empty($changes)) {
        return '변경사항 없음';
    }
    
    // 기본 필드 라벨 맵핑
    $default_labels = [
        // 회원 정보
        'mb_id' => '아이디',
        'mb_name' => '이름',
        'mb_nick' => '닉네임',
        'mb_email' => '이메일',
        'mb_hp' => '휴대폰',
        'mb_tel' => '전화번호',
        'mb_zip1' => '우편번호1',
        'mb_zip2' => '우편번호2', 
        'mb_addr1' => '주소1',
        'mb_addr2' => '주소2',
        'mb_addr3' => '주소3',
        'mb_point' => '포인트',
        'mb_level' => '레벨',
        'mb_mailling' => '메일링 서비스',
        'mb_sms' => 'SMS 수신',
        'mb_open' => '정보공개',
        'mb_profile' => '프로필',
        'mb_memo' => '메모',
        
        // 도매까 관련
        'dmk_mb_type' => '관리자 유형',
        'dmk_distributor_id' => '총판 ID',
        'dmk_agency_id' => '대리점 ID',
        'dmk_branch_id' => '지점 ID',
        'dmk_company_name' => '업체명',
        'dmk_business_number' => '사업자번호',
        'dmk_ceo_name' => 'CEO 이름',
        'dmk_manager_name' => '담당자명',
        'dmk_manager_hp' => '담당자 연락처',
        'dmk_status' => '상태',
        'dmk_commission_rate' => '수수료율',
        'dmk_memo' => '메모',
        
        // 상품 관련
        'it_name' => '상품명',
        'it_price' => '판매가',
        'it_cust_price' => '시중가',
        'it_point' => '적립금',
        'it_use' => '진열여부',
        'it_sell_use' => '판매여부',
        'it_stock_qty' => '재고수량',
        'ca_id' => '분류',
        'it_maker' => '제조사',
        'it_origin' => '원산지',
        'it_brand' => '브랜드',
        'it_model' => '모델',
        'it_weight' => '무게',
        'it_info_value' => '상품정보',
        'dmk_owner_type' => '소유권 유형',
        'dmk_owner_id' => '소유자 ID'
    ];
    
    $field_labels = array_merge($default_labels, $field_labels);
    $formatted_changes = [];
    
    foreach ($changes as $field => $change) {
        $label = isset($field_labels[$field]) ? $field_labels[$field] : $field;
        $old_display = dmk_format_field_value($field, $change['old']);
        $new_display = dmk_format_field_value($field, $change['new']);
        
        $formatted_changes[] = "{$label}: '{$old_display}' → '{$new_display}'";
    }
    
    return implode(', ', $formatted_changes);
}

/**
 * 필드 값을 표시용으로 포맷팅합니다.
 *
 * @param string $field_name 필드명
 * @param mixed $value 필드 값
 * @return string 포맷팅된 값
 */
function dmk_format_field_value($field_name, $value) {
    // 빈 값 처리
    if ($value === '' || $value === null) {
        return '(공백)';
    }
    
    // 불리언 값 처리
    if (in_array($field_name, ['mb_mailling', 'mb_sms', 'mb_open', 'it_use', 'it_sell_use'])) {
        return $value ? '사용' : '미사용';
    }
    
    // 도매까 타입 처리
    if ($field_name === 'dmk_mb_type') {
        $types = ['', '총판', '대리점', '지점'];
        return isset($types[$value]) ? $types[$value] : $value;
    }
    
    // 상태 처리
    if ($field_name === 'dmk_status') {
        $statuses = ['', '대기', '승인', '중지', '해지'];
        return isset($statuses[$value]) ? $statuses[$value] : $value;
    }
    
    // 가격/포인트 포맷팅
    if (in_array($field_name, ['it_price', 'it_cust_price', 'it_point', 'mb_point']) && is_numeric($value)) {
        return number_format($value) . '원';
    }
    
    // 수수료율 포맷팅
    if ($field_name === 'dmk_commission_rate' && is_numeric($value)) {
        return $value . '%';
    }
    
    // 긴 텍스트 자르기 (50자 제한)
    if (strlen($value) > 50) {
        return mb_substr($value, 0, 50) . '...';
    }
    
    return $value;
}

/**
 * UPDATE 액션에 대해 향상된 로그를 기록합니다.
 *
 * @param string $action_detail 기본 액션 설명
 * @param string|null $target_id 대상 ID
 * @param array $new_data 변경 후 데이터
 * @param array $old_data 변경 전 데이터
 * @param string|null $menu_code 메뉴 코드
 * @param string|null $target_table 대상 테이블
 * @param array $field_labels 필드 라벨 맵핑 (선택사항)
 * @return bool 로그 기록 성공 여부
 */
function dmk_log_update_action($action_detail, $target_id = null, $new_data = [], $old_data = [], $menu_code = null, $target_table = null, $field_labels = []) {
    // 데이터 변경사항 비교
    $changes = dmk_compare_data_changes($old_data, $new_data);
    
    // 변경사항이 없으면 로그 기록하지 않음
    if (empty($changes)) {
        return true; // 변경사항이 없어도 성공으로 처리
    }
    
    // 변경사항을 포함한 상세 설명 생성
    $change_description = dmk_format_data_changes($changes, $field_labels);
    $enhanced_detail = $action_detail . ' [변경내용: ' . $change_description . ']';
    
    // 기존 로그 함수 호출
    return dmk_log_admin_action(
        'UPDATE',
        $enhanced_detail,
        $target_id,
        json_encode($new_data, JSON_UNESCAPED_UNICODE),
        json_encode($old_data, JSON_UNESCAPED_UNICODE),
        $menu_code,
        $target_table
    );
} 