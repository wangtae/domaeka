<?php
define('G5_IS_ADMIN', true);

/**
 * 그누보드5의 핵심 파일 common.php를 절대 경로로 찾아 포함합니다.
 * $_SERVER['DOCUMENT_ROOT']를 사용하여 웹 서버의 문서 루트를 기준으로 경로를 구성합니다.
 * 이 파일이 로드된 후 G5_PATH, G5_URL, G5_ADMIN_PATH 등 모든 핵심 상수가 정의됩니다.
 */
// 웹서버와 명령줄 둘 다 지원하는 경로 설정
$possible_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/common.php',  // 웹서버에서 실행
    dirname(__FILE__) . '/../common.php',       // 명령줄에서 실행
    dirname(dirname(__FILE__)) . '/common.php'  // 대안 경로
];

$gnuboard_main_common_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $gnuboard_main_common_path = $path;
        break;
    }
}

if ($gnuboard_main_common_path) {
    require_once $gnuboard_main_common_path;
} else {
    die('그누보드5 common.php를 찾을 수 없습니다. 확인된 경로들: ' . implode(', ', $possible_paths));
}

// 도매까 전역 설정을 포함합니다.
// 계층별 메뉴 설정을 위해 권한 라이브러리보다 먼저 로드합니다.
require_once G5_PATH . '/dmk/dmk_global_settings.php';

// 도매까 UTF-8 인코딩 설정
require_once G5_PATH . '/dmk/dmk_common.php';

// 도매까 관리자 권한 라이브러리를 포함합니다.
// G5_DMK_PATH 상수가 정의된 후에 로드되어야 하며, admin.lib.php 보다 먼저 로드합니다.
require_once G5_DMK_PATH . '/adm/lib/admin.auth.lib.php';

// 도매까 관리자인 경우 $is_admin 변수를 설정
$dmk_auth = dmk_get_admin_auth();
if (!$is_admin && $dmk_auth && $dmk_auth['mb_type'] > 0) {
    $is_admin = 'dmk_admin'; // 도매까 관리자를 위한 특별한 값
}

if ( !$member['mb_id'] ) {
    // 비로그인 상태에서는 로그인 폼 표시
    include_once(G5_PATH.'/index.adm.php');
    exit;
}

if ( $member['mb_level'] == 2 ) {
    goto_url(G5_URL.'/index.adm.php');
    exit;
}

// 일반 회원이 관리자 페이지에 접속한 경우 로그아웃 후 메인으로 이동
if (!$is_admin && $is_member && (!$dmk_auth || $dmk_auth['mb_type'] == 0)) {
    // 로그아웃 처리
    goto_url(G5_BBS_URL.'/logout.php?url='.urlencode('/'));
}

// G5_ADMIN_PATH가 정의된 후 admin.lib.php를 포함합니다.
require_once G5_ADMIN_PATH . '/admin.lib.php';

if (isset($token)) {
    $token = @htmlspecialchars(strip_tags($token), ENT_QUOTES);
}

run_event('admin_common');
