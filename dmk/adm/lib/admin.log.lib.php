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