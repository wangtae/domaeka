<?php
// 에러 출력 억제 및 출력 버퍼 정리
error_reporting(0);
ob_clean();

include_once('./_common.php');
include_once(G5_LIB_PATH.'/json.lib.php');

// 출력 버퍼 다시 정리
ob_clean();

set_session('ss_admin_token', '');

$error = admin_referer_check(true);
if($error)
    die(json_encode(array('error'=>$error, 'url'=>G5_URL)));

$token = get_admin_token();

die(json_encode(array('error'=>'', 'token'=>$token, 'url'=>'')));