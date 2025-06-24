<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 도매까 관리자 권한 체크
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 관리자관리는 총판 이상만 접근 가능
$dmk_auth = dmk_get_admin_auth();
// 임시로 권한 체크 비활성화 - 디버깅용
/*
if (!$dmk_auth['is_super'] && $dmk_auth['mb_level'] < DMK_MB_LEVEL_DISTRIBUTOR) {
    alert('접근 권한이 없습니다.', G5_ADMIN_URL);
}
*/ 