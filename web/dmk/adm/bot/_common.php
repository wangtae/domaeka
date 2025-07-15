<?php
/**
 * Admin Manager 공통 파일
 *
 * 이 파일은 서브관리자 관리 모듈에서 사용되는 공통 설정을 포함합니다.
 * 그누보드 관리자 페이지의 기본 환경 설정을 로드하고, 도매까 시스템의 권한 라이브러리를 포함합니다.
 */

// 그누보드 관리자 공통 파일을 포함하여 필요한 상수 및 함수를 로드합니다.
require_once '../../../adm/_common.php';

// 도매까 권한 라이브러리를 포함합니다.
require_once G5_DMK_PATH . '/adm/lib/admin.auth.lib.php';

// 도매까 전역 설정을 포함합니다.
require_once G5_DMK_PATH . '/dmk_global_settings.php';

// dmk_get_admin_auth 함수가 정의되었는지 확인
if (!function_exists('dmk_get_admin_auth')) {
    error_log('Error: dmk_get_admin_auth function not found in ' . __FILE__);
    alert('권한 라이브러리 로드 실패: dmk_get_admin_auth 함수를 찾을 수 없습니다.');
    exit;
}

// 현재 로그인한 관리자의 권한을 확인합니다.
$auth = dmk_get_admin_auth();

// Debugging: Dump the $auth array to browser console
//echo "<script>console.log('DMK Auth from _common.php:', " . json_encode($auth) . ");</script>";

// 서브관리자 등록/수정 권한 확인
if (!$auth['is_super'] && $auth['mb_type'] != DMK_MB_TYPE_DISTRIBUTOR && $auth['mb_type'] != DMK_MB_TYPE_AGENCY && $auth['mb_type'] != DMK_MB_TYPE_BRANCH) {
    alert('서브관리자 등록/수정 권한이 없습니다.');
    exit;
}
?> 