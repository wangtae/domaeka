<?php
// 기본 디버깅 출력
echo "DEBUG: Starting debug_test.php<br>";

$sub_menu = "190900";
echo "DEBUG: sub_menu = $sub_menu<br>";

try {
    include_once './_common.php';
    echo "DEBUG: _common.php included successfully<br>";
} catch (Exception $e) {
    echo "ERROR: _common.php failed: " . $e->getMessage() . "<br>";
}

if (defined('G5_PATH')) {
    echo "DEBUG: G5_PATH is defined: " . G5_PATH . "<br>";
} else {
    echo "ERROR: G5_PATH is not defined<br>";
}

try {
    include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
    echo "DEBUG: admin.auth.lib.php included successfully<br>";
} catch (Exception $e) {
    echo "ERROR: admin.auth.lib.php failed: " . $e->getMessage() . "<br>";
}

if (function_exists('dmk_can_access_menu')) {
    echo "DEBUG: dmk_can_access_menu function exists<br>";
    $can_access = dmk_can_access_menu($sub_menu);
    echo "DEBUG: dmk_can_access_menu($sub_menu) returned: " . ($can_access ? 'true' : 'false') . "<br>";
} else {
    echo "ERROR: dmk_can_access_menu function does not exist<br>";
}

if (function_exists('dmk_get_admin_auth')) {
    echo "DEBUG: dmk_get_admin_auth function exists<br>";
    $auth = dmk_get_admin_auth();
    echo "DEBUG: dmk_get_admin_auth returned: <pre>" . print_r($auth, true) . "</pre><br>";
} else {
    echo "ERROR: dmk_get_admin_auth function does not exist<br>";
}

echo "DEBUG: End of debug_test.php<br>";
?>