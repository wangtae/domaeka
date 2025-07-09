<?php
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

echo "<h2>현재 사용자 권한 정보</h2>";
echo "<h3>기본 정보</h3>";
echo "member['mb_id']: " . (isset($member['mb_id']) ? $member['mb_id'] : '없음') . "<br>";
echo "member['mb_level']: " . (isset($member['mb_level']) ? $member['mb_level'] : '없음') . "<br>";
echo "is_admin: " . (isset($is_admin) ? ($is_admin ? 'true' : 'false') : '없음') . "<br>";

echo "<h3>DMK 권한 정보</h3>";
$dmk_auth = dmk_get_admin_auth();
echo "<pre>";
print_r($dmk_auth);
echo "</pre>";

echo "<h3>상수 정의 확인</h3>";
echo "DMK_MB_LEVEL_DISTRIBUTOR: " . (defined('DMK_MB_LEVEL_DISTRIBUTOR') ? DMK_MB_LEVEL_DISTRIBUTOR : '정의되지 않음') . "<br>";
echo "DMK_MB_LEVEL_AGENCY: " . (defined('DMK_MB_LEVEL_AGENCY') ? DMK_MB_LEVEL_AGENCY : '정의되지 않음') . "<br>";
echo "DMK_MB_LEVEL_BRANCH: " . (defined('DMK_MB_LEVEL_BRANCH') ? DMK_MB_LEVEL_BRANCH : '정의되지 않음') . "<br>";

echo "<h3>권한 체크 결과</h3>";
echo "is_super: " . ($dmk_auth['is_super'] ? 'true' : 'false') . "<br>";
echo "mb_level >= DMK_MB_LEVEL_DISTRIBUTOR: " . ($dmk_auth['mb_level'] >= DMK_MB_LEVEL_DISTRIBUTOR ? 'true' : 'false') . "<br>";
echo "접근 허용: " . ($dmk_auth['is_super'] || $dmk_auth['mb_level'] >= DMK_MB_LEVEL_DISTRIBUTOR ? 'true' : 'false') . "<br>";
?> 