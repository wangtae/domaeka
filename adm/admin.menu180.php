<?php
if (!defined('_GNUBOARD_')) exit;

// 봇 관리 메뉴
$menu['menu180'] = array(
    array('180000', '<span>봇 관리</span>', G5_ADMIN_URL.'/dmk/adm/bot/bot_server_status.php', 'bot_manage'),
    array('180100', '서버 관리', G5_ADMIN_URL.'/dmk/adm/bot/bot_server_status.php', 'bot_server'),
    array('180200', '클라이언트 봇 관리', G5_ADMIN_URL.'/dmk/adm/bot/bot_client_list.php', 'bot_client'),
    array('180300', '스케줄링 발송 관리', G5_ADMIN_URL.'/dmk/adm/bot/bot_schedule_list.php', 'bot_schedule'),
    array('180400', '메시지 즉시 발송', G5_ADMIN_URL.'/dmk/adm/bot/bot_instant_send_form.php', 'bot_instant'),
    array('180500', '채팅 내역 조회', G5_ADMIN_URL.'/dmk/adm/bot/bot_chat_log_list.php', 'bot_chat_log')
);
?>