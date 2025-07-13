<?php
if (!defined('_GNUBOARD_')) exit;

// 봇 관리 메뉴 - 권한에 따른 메뉴 구성
$user_type = dmk_get_current_user_type();

if ($user_type === 'super' || is_super_admin($member['mb_id'])) {
    // 본사 관리자 - 모든 봇 관리 메뉴 접근 가능
    $menu['menu180'] = array(
        array('180000', '<span>봇 관리</span>', G5_DMK_ADM_URL.'/bot/server_list.php', 'bot_manage'),
        array('180100', '서버 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/server_list.php', 'server_management'),
        array('180200', '서버 프로세스 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/server_process_list.php', 'server_process'),
        array('180300', '클라이언트 봇 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_device'),
        array('180400', '봇 상태 모니터링 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/ping_monitor_list.php', 'ping_monitor'),
        array('180500', '채팅방 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/room_list.php', 'room_management'),
        array('180600', '스케줄링 발송 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/schedule_list.php', 'schedule_management'),
        array('180700', '채팅 내역 조회 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/chat_log_list.php', 'chat_log')
    );
} else if ($user_type === 'distributor') {
    // 총판 관리자 - 모든 봇 관리 메뉴 접근 가능 (dmk_global_settings.php 기준)
    $menu['menu180'] = array(
        array('180000', '<span>봇 관리</span>', G5_DMK_ADM_URL.'/bot/server_list.php', 'bot_manage'),
        array('180100', '서버 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/server_list.php', 'server_management'),
        array('180200', '서버 프로세스 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/server_process_list.php', 'server_process'),
        array('180300', '클라이언트 봇 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_device'),
        array('180400', '봇 상태 모니터링 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/ping_monitor_list.php', 'ping_monitor'),
        array('180500', '채팅방 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/room_list.php', 'room_management'),
        array('180600', '스케줄링 발송 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/schedule_list.php', 'schedule_management'),
        array('180700', '채팅 내역 조회 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/chat_log_list.php', 'chat_log')
    );
} else if ($user_type === 'agency') {
    // 대리점 관리자 - 서버/프로세스 관리 제외
    $menu['menu180'] = array(
        array('180000', '<span>봇 관리</span>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_manage'),
        array('180300', '클라이언트 봇 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_device'),
        array('180400', '봇 상태 모니터링 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/ping_monitor_list.php', 'ping_monitor'),
        array('180500', '채팅방 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/room_list.php', 'room_management'),
        array('180600', '스케줄링 발송 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/schedule_list.php', 'schedule_management'),
        array('180700', '채팅 내역 조회 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/chat_log_list.php', 'chat_log')
    );
} else if ($user_type === 'branch') {
    // 지점 관리자 - 서버/프로세스 관리 제외
    $menu['menu180'] = array(
        array('180000', '<span>봇 관리</span>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_manage'),
        array('180300', '클라이언트 봇 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/bot_device_list.php', 'bot_device'),
        array('180400', '봇 상태 모니터링 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/ping_monitor_list.php', 'ping_monitor'),
        array('180500', '채팅방 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/room_list.php', 'room_management'),
        array('180600', '스케줄링 발송 관리 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/schedule_list.php', 'schedule_management'),
        array('180700', '채팅 내역 조회 <i class="fa fa-star" title="NEW"></i>', G5_DMK_ADM_URL.'/bot/chat_log_list.php', 'chat_log')
    );
} else {
    // 권한이 없는 사용자
    $menu['menu180'] = array();
}
?>