<?php
$sub_menu = "200100";
require_once "./_common.php";
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

check_demo();

auth_check_menu($auth, $sub_menu, "d");

$mb = isset($_POST['mb_id']) ? get_member($_POST['mb_id']) : array();

if (!(isset($mb['mb_id']) && $mb['mb_id'])) {
    alert("회원자료가 존재하지 않습니다.");
} elseif ($member['mb_id'] == $mb['mb_id']) {
    alert("로그인 중인 관리자는 삭제 할 수 없습니다.");
} elseif (is_admin($mb['mb_id']) == "super") {
    alert("최고 관리자는 삭제할 수 없습니다.");
} elseif (!dmk_can_modify_member($mb['mb_id'])) {
    alert("삭제 할 권한이 없는 회원입니다.");
}

check_admin_token();

// 회원자료 삭제
member_delete($mb['mb_id']);

if (isset($url)) {
    goto_url("{$url}?$qstr&amp;w=u&amp;mb_id=" . $mb['mb_id']);
} else {
    goto_url("./member_list.php?$qstr");
}
