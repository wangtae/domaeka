<?php
// 필요한 함수들이 정의되었는지 확인
if (defined('G5_DMK_PATH') && file_exists(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php')) {
    include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
}
if (defined('G5_PATH') && file_exists(G5_PATH . '/dmk/dmk_global_settings.php')) {
    include_once(G5_PATH . '/dmk/dmk_global_settings.php');
}

// 사용자 타입 확인
$user_type = function_exists('dmk_get_current_user_type') ? dmk_get_current_user_type() : null;

// 최고관리자만 SMS 관리 메뉴에 접근 가능 (DMK 설정에 따르면 총판도 SMS 접근 불가)
if ($user_type === 'super' || is_super_admin($member['mb_id'])) {
    $menu["menu900"] = array(
        array('900000', 'SMS 관리', '' . G5_SMS5_ADMIN_URL . '/config.php', 'sms5'),
        array('900100', 'SMS 기본설정', '' . G5_SMS5_ADMIN_URL . '/config.php', 'sms5_config'),
        array('900200', '회원정보업데이트', '' . G5_SMS5_ADMIN_URL . '/member_update.php', 'sms5_mb_update'),
        array('900300', '문자 보내기', '' . G5_SMS5_ADMIN_URL . '/sms_write.php', 'sms_write'),
        array('900400', '전송내역-건별', '' . G5_SMS5_ADMIN_URL . '/history_list.php', 'sms_history', 1),
        array('900410', '전송내역-번호별', '' . G5_SMS5_ADMIN_URL . '/history_num.php', 'sms_history_num', 1),
        array('900500', '이모티콘 그룹', '' . G5_SMS5_ADMIN_URL . '/form_group.php', 'emoticon_group'),
        array('900600', '이모티콘 관리', '' . G5_SMS5_ADMIN_URL . '/form_list.php', 'emoticon_list'),
        array('900700', '휴대폰번호 그룹', '' . G5_SMS5_ADMIN_URL . '/num_group.php', 'hp_group', 1),
        array('900800', '휴대폰번호 관리', '' . G5_SMS5_ADMIN_URL . '/num_book.php', 'hp_manage', 1),
        array('900900', '휴대폰번호 파일', '' . G5_SMS5_ADMIN_URL . '/num_book_file.php', 'hp_file', 1)
    );
} else {
    $menu["menu900"] = array(); // 권한이 없으면 빈 배열로 설정하여 메뉴를 숨김
}
