<?php
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 도매까 권한 확인
$dmk_auth = dmk_get_admin_auth();

// 게시판 관리 메뉴 (원래 그누보드 기본 메뉴)
// 최고관리자와 총판 관리자만 접근 가능
if (is_super_admin($member['mb_id']) || dmk_is_distributor($member['mb_id'])) {
    $menu['menu300'] = array(
        array('300000', '게시판관리', G5_ADMIN_URL . '/board_list.php', 'board'),
        array('300100', '게시판관리', G5_ADMIN_URL . '/board_list.php', 'bbs_board'),
        array('300200', '게시판그룹관리', G5_ADMIN_URL . '/boardgroup_list.php', 'bbs_group'),
        array('300300', '인기검색어관리', G5_ADMIN_URL . '/popular_list.php', 'bbs_poplist', 1),
        array('300400', '인기검색어순위', G5_ADMIN_URL . '/popular_rank.php', 'bbs_poprank', 1),
        array('300500', '1:1문의설정', G5_ADMIN_URL . '/qa_config.php', 'qa'),
        array('300600', '내용관리', G5_ADMIN_URL . '/contentlist.php', 'scf_contents', 1),
        array('300700', 'FAQ관리', G5_ADMIN_URL . '/faqmasterlist.php', 'scf_faq', 1),
        array('300820', '글,댓글 현황', G5_ADMIN_URL . '/write_count.php', 'scf_write_count'),
    );
} else {
    $menu['menu300'] = array(); // 권한이 없으면 빈 배열
}
