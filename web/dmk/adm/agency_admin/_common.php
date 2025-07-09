<?php
/**
 * Agency Admin 공통 파일
 *
 * 이 파일은 대리점 관리자 모듈에서 사용되는 공통 설정을 포함합니다.
 * 그누보드 관리자 페이지의 기본 환경 설정을 로드하고, 도매까 시스템의 권한 라이브러리를 포함합니다.
 * DMK_MB_TYPE_AGENCY 상수를 사용하여 대리점 관리자 권한을 설정하고, 접근 권한을 확인합니다.
 */

// 그누보드 관리자 공통 파일을 포함하여 필요한 상수 및 함수를 로드합니다.
// main/adm/_common.php는 이제 main/common.php를 절대 경로로 로드합니다.
require_once dirname(__FILE__) . '/../../../adm/_common.php';

// 도매까 권한 라이브러리를 포함합니다.
require_once G5_DMK_PATH . '/adm/lib/admin.auth.lib.php';

// 이 페이지의 관리자 유형을 대리점으로 설정합니다.
define('DMK_CURRENT_ADMIN_TYPE', DMK_MB_TYPE_AGENCY);

// 현재 로그인한 관리자의 권한을 확인하고 메뉴 접근 권한을 검증합니다.
dmk_authenticate_admin(DMK_CURRENT_ADMIN_TYPE);

// URL 매개변수 'dmk_agency_id'가 유효한지 확인하고 전역 변수로 설정합니다.
// 이 ID는 대리점 관리자가 특정 대리점의 정보를 조회/수정할 때 사용됩니다.
$dmk_agency_id = isset($_GET['dmk_agency_id']) ? (int)$_GET['dmk_agency_id'] : 0;

// dmk_agency_id가 유효하고, 현재 관리자가 대리점 관리자이며 해당 대리점에 대한
// 수정 권한이 있는지 확인합니다. (본사 관리자는 모든 대리점에 접근 가능)
if ($dmk_agency_id > 0 && DMK_CURRENT_ADMIN_TYPE === DMK_MB_TYPE_AGENCY) {
    if (!dmk_can_modify_agency($dmk_agency_id)) {
        alert('해당 대리점에 대한 접근 권한이 없습니다.');
    }
}
?> 