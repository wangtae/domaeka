<?php
define('G5_IS_ADMIN', true);

/**
 * 그누보드5의 핵심 파일 common.php를 절대 경로로 찾아 포함합니다.
 * $_SERVER['DOCUMENT_ROOT']를 사용하여 웹 서버의 문서 루트를 기준으로 경로를 구성합니다.
 * 이 파일이 로드된 후 G5_PATH, G5_URL, G5_ADMIN_PATH 등 모든 핵심 상수가 정의됩니다.
 */
$gnuboard_main_common_path = $_SERVER['DOCUMENT_ROOT'] . '/common.php';

if (file_exists($gnuboard_main_common_path)) {
    require_once $gnuboard_main_common_path;
} else {
    die('그누보드5 common.php를 찾을 수 없습니다. 경로: ' . $gnuboard_main_common_path);
}

// 도매까 전역 설정을 포함합니다.
// 계층별 메뉴 설정을 위해 권한 라이브러리보다 먼저 로드합니다.
require_once G5_PATH . '/dmk/dmk_global_settings.php';

// 도매까 관리자 권한 라이브러리를 포함합니다.
// G5_DMK_PATH 상수가 정의된 후에 로드되어야 하며, admin.lib.php 보다 먼저 로드합니다.
require_once G5_DMK_PATH . '/adm/lib/admin.auth.lib.php';

// 도매까 관리자인 경우 $is_admin 변수를 설정
$dmk_auth = dmk_get_admin_auth();
if (!$is_admin && $dmk_auth && $dmk_auth['mb_type'] > 0) {
    $is_admin = 'dmk_admin'; // 도매까 관리자를 위한 특별한 값
}

// G5_ADMIN_PATH가 정의된 후 admin.lib.php를 포함합니다.
require_once G5_ADMIN_PATH . '/admin.lib.php';

if (isset($token)) {
    $token = @htmlspecialchars(strip_tags($token), ENT_QUOTES);
}

run_event('admin_common');
