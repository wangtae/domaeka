<?php
// 사용자 유형별 기본 메뉴 설정
require_once './_common.php';

// 도매까 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

// 사용자 유형별 기본 sub_menu 설정
if ($dmk_auth && $dmk_auth['is_super']) {
    // 본사 관리자 - 환경설정 메뉴 (100100 - 기본환경설정)
    $sub_menu = '100100';
} else if ($dmk_auth && !$dmk_auth['is_super']) {
    // 총판/대리점/지점 - 프랜차이즈 관리 메뉴 (190100 - 총판관리)
    $sub_menu = '190100';
} else {
    // 기본값 (일반 관리자)
    $sub_menu = '100100';
}

@require_once './safe_check.php';

if (function_exists('social_log_file_delete')) {
    //소셜로그인 디버그 파일 24시간 지난것은 삭제
    social_log_file_delete(86400);
}

$g5['title'] = '관리자메인';
require_once './admin.head.php';

// 계층별 필터링을 위한 GET 파라미터 처리
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$new_member_rows = 5;
$new_point_rows = 5;
$new_write_rows = 5;

$addtional_content_before = run_replace('adm_index_addtional_content_before', '', $is_admin, $auth, $member);
if ($addtional_content_before) {
    echo $addtional_content_before;
}

if (!auth_check_menu($auth, '200100', 'r', true)) {
    // 도매까 권한별 회원 조회 조건 추가
    $member_where_condition = '';
    
    // 기본 권한별 필터링
    if ($dmk_auth && !$dmk_auth['is_super']) {
        switch ($dmk_auth['mb_type']) {
            case 1: // 총판 - 자신의 총판에 속한 회원만
                if (!empty($dmk_auth['dt_id'])) {
                    $member_where_condition = " AND (
                        mb_id = '{$dmk_auth['dt_id']}' OR
                        mb_id IN (SELECT ag_id FROM dmk_agency WHERE dt_id = '".sql_escape_string($dmk_auth['dt_id'])."') OR
                        mb_id IN (SELECT br_id FROM dmk_branch b JOIN dmk_agency a ON b.ag_id = a.ag_id WHERE a.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."')
                    )";
                }
                break;
            case 2: // 대리점 - 자신과 소속 지점만
                if (!empty($dmk_auth['ag_id'])) {
                    $member_where_condition = " AND (
                        mb_id = '{$dmk_auth['ag_id']}' OR
                        mb_id IN (SELECT br_id FROM dmk_branch WHERE ag_id = '".sql_escape_string($dmk_auth['ag_id'])."')
                    )";
                }
                break;
            case 3: // 지점 - 자신만
                if (!empty($dmk_auth['br_id'])) {
                    $member_where_condition = " AND mb_id = '".sql_escape_string($dmk_auth['br_id'])."'";
                }
                break;
        }
    }

    // 계층별 필터링 추가 (GET 파라미터 기반)
    if ($filter_dt_id) {
        $member_where_condition .= " AND (
            mb_id = '".sql_escape_string($filter_dt_id)."' OR
            mb_id IN (SELECT ag_id FROM dmk_agency WHERE dt_id = '".sql_escape_string($filter_dt_id)."') OR
            mb_id IN (SELECT br_id FROM dmk_branch b JOIN dmk_agency a ON b.ag_id = a.ag_id WHERE a.dt_id = '".sql_escape_string($filter_dt_id)."')
        )";
    }
    if ($filter_ag_id) {
        $member_where_condition .= " AND (
            mb_id = '".sql_escape_string($filter_ag_id)."' OR
            mb_id IN (SELECT br_id FROM dmk_branch WHERE ag_id = '".sql_escape_string($filter_ag_id)."')
        )";
    }
    if ($filter_br_id) {
        $member_where_condition .= " AND mb_id = '".sql_escape_string($filter_br_id)."'";
    }

    $sql_common = " from {$g5['member_table']} m
                    LEFT JOIN dmk_branch b ON m.mb_id = b.br_id
                    LEFT JOIN dmk_agency a ON m.mb_id = a.ag_id OR b.ag_id = a.ag_id
                    LEFT JOIN dmk_distributor d ON m.mb_id = d.dt_id OR a.dt_id = d.dt_id ";

    $sql_search = " where (1) " . $member_where_condition;

    if ($is_admin != 'super') {
        $sql_search .= " and m.mb_level <= '{$member['mb_level']}' ";
    }

    if (!$sst) {
        $sst = "m.mb_datetime";
        $sod = "desc";
    }

    $sql_order = " order by {$sst} {$sod} ";

    $sql = " SELECT count(DISTINCT m.mb_id) as cnt {$sql_common} {$sql_search} ";
    $row = sql_fetch($sql);
    $total_count = $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;

    // 탈퇴회원수
    $sql = " select count(DISTINCT m.mb_id) as cnt {$sql_common} {$sql_search} and m.mb_leave_date <> '' ";
    $row = sql_fetch($sql);
    $leave_count = $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;

    // 차단회원수
    $sql = " SELECT count(DISTINCT m.mb_id) as cnt {$sql_common} {$sql_search} and m.mb_intercept_date <> '' ";
    $row = sql_fetch($sql);
    $intercept_count = $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;

    $sql = " SELECT DISTINCT m.*, 
                    COALESCE(b.br_id, '') as branch_id,
                    COALESCE(a.ag_id, '') as agency_id, 
                    COALESCE(d.dt_id, '') as distributor_id,
                    CASE 
                        WHEN b.br_id IS NOT NULL THEN CONCAT(a.ag_name, ' > ', m.mb_nick)
                        WHEN a.ag_id IS NOT NULL THEN CONCAT(d.dt_name, ' > ', m.mb_nick)
                        WHEN d.dt_id IS NOT NULL THEN m.mb_nick
                        ELSE '일반회원'
                    END as hierarchy_info
             {$sql_common} {$sql_search} {$sql_order} limit {$new_member_rows} ";
    $result = sql_query($sql);

    $colspan = 11;
    ?>

    <section>
        <h2>신규가입회원 <?php echo $new_member_rows ?>건 목록</h2>
        
        <!-- 도매까 계층 선택박스 (NEW) -->
        <form name="fmember_filter" class="local_sch01 local_sch" method="get">
            <?php
            // 도매까 체인 선택박스 포함 (권한에 따라 표시)
            if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == 1) {
                include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                
                // 현재 선택된 계층 값들 (권한에 따라 자동 설정)
                $current_dt_id = $filter_dt_id;
                $current_ag_id = $filter_ag_id;
                $current_br_id = $filter_br_id;
                
                // 권한에 따른 페이지 타입 결정
                $page_type = DMK_CHAIN_SELECT_FULL;
                if ($dmk_auth['mb_type'] == 1) {
                    $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
                    // 총판 관리자는 자신의 총판으로 고정
                    $current_dt_id = $dmk_auth['dt_id'];
                }
                
                echo dmk_render_chain_select([
                    'page_type' => $page_type,
                    'auto_submit' => true,
                    'form_id' => 'fmember_filter',
                    'field_names' => [
                        'distributor' => 'sdt_id',
                        'agency' => 'sag_id', 
                        'branch' => 'sbr_id'
                    ],
                    'current_values' => [
                        'sdt_id' => $current_dt_id,
                        'sag_id' => $current_ag_id,
                        'sbr_id' => $current_br_id
                    ],
                    'placeholders' => [
                        'distributor' => '전체 총판',
                        'agency' => '전체 대리점',
                        'branch' => '전체 지점'
                    ]
                ]);
            } else if ($dmk_auth['mb_type'] == 2) {
                // 대리점 관리자는 소속 지점만 선택 가능
                include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                
                echo dmk_render_chain_select([
                    'page_type' => DMK_CHAIN_SELECT_FULL,
                    'auto_submit' => true,
                    'form_id' => 'fmember_filter',
                    'field_names' => [
                        'distributor' => 'sdt_id',
                        'agency' => 'sag_id', 
                        'branch' => 'sbr_id'
                    ],
                    'current_values' => [
                        'sdt_id' => $dmk_auth['dt_id'],
                        'sag_id' => $dmk_auth['ag_id'],
                        'sbr_id' => $filter_br_id
                    ],
                    'placeholders' => [
                        'distributor' => '전체 총판',
                        'agency' => '전체 대리점',
                        'branch' => '전체 지점'
                    ]
                ]);
            } else if ($dmk_auth['mb_type'] == 3) {
                // 지점 관리자는 자신의 지점만 표시 (선택박스 없음)
                echo '<div class="dmk-chain-select-container">';
                echo '<span class="dmk-hierarchy-info">현재 조회 범위: ' . htmlspecialchars($dmk_auth['br_name'] ?? '해당 지점') . ' 지점</span>';
                echo '</div>';
            }
            ?>
        </form>
        <!-- //도매까 계층 선택박스 -->
        
        <div class="local_desc02 local_desc">
            총회원수 <?php echo number_format($total_count) ?>명 중 차단 <?php echo number_format($intercept_count) ?>명, 탈퇴 : <?php echo number_format($leave_count) ?>명
        </div>

        <div class="tbl_head01 tbl_wrap">
            <table>
                <caption>신규가입회원</caption>
                <thead>
                    <tr>
                        <th scope="col">회원아이디</th>
                        <th scope="col">이름</th>
                        <th scope="col">닉네임</th>
                        <th scope="col">소속정보</th>
                        <th scope="col">권한</th>
                        <th scope="col">포인트</th>
                        <th scope="col">수신</th>
                        <th scope="col">공개</th>
                        <th scope="col">인증</th>
                        <th scope="col">차단</th>
                        <th scope="col">그룹</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($i = 0; $row = sql_fetch_array($result); $i++) {
                        // 접근가능한 그룹수
                        $sql2 = " SELECT count(*) as cnt from {$g5['group_member_table']} where mb_id = '{$row['mb_id']}' ";
                        $row2 = sql_fetch($sql2);
                        $group = "";
                        if ($row2['cnt']) {
                            $group = '<a href="./boardgroupmember_form.php?mb_id=' . $row['mb_id'] . '">' . $row2['cnt'] . '</a>';
                        }

                        if ($is_admin == 'group') {
                            $s_mod = '';
                            $s_del = '';
                        } else {
                            $s_mod = '<a href="./member_form.php?$qstr&amp;w=u&amp;mb_id=' . $row['mb_id'] . '">수정</a>';
                            $s_del = '<a href="./member_delete.php?' . $qstr . '&amp;w=d&amp;mb_id=' . $row['mb_id'] . '&amp;url=' . $_SERVER['SCRIPT_NAME'] . '" onclick="return delete_confirm(this);">삭제</a>';
                        }
                        $s_grp = '<a href="./boardgroupmember_form.php?mb_id=' . $row['mb_id'] . '">그룹</a>';

                        $leave_date = $row['mb_leave_date'] ? $row['mb_leave_date'] : date("Ymd", G5_SERVER_TIME);
                        $intercept_date = $row['mb_intercept_date'] ? $row['mb_intercept_date'] : date("Ymd", G5_SERVER_TIME);

                        $mb_nick = get_sideview($row['mb_id'], get_text($row['mb_nick']), $row['mb_email'], $row['mb_homepage']);

                        $mb_id = $row['mb_id'];
                        ?>
                        <tr>
                            <td class="td_mbid"><?php echo $mb_id ?></td>
                            <td class="td_mbname"><?php echo get_text($row['mb_name']); ?></td>
                            <td class="td_mbname sv_use">
                                <div><?php echo $mb_nick ?></div>
                            </td>
                            <td class="td_category"><?php echo htmlspecialchars($row['hierarchy_info']); ?></td>
                            <td class="td_num"><?php echo $row['mb_level'] ?></td>
                            <td><a href="./point_list.php?sfl=mb_id&amp;stx=<?php echo $row['mb_id'] ?>"><?php echo number_format($row['mb_point']) ?></a></td>
                            <td class="td_boolean"><?php echo $row['mb_mailling'] ? '예' : '아니오'; ?></td>
                            <td class="td_boolean"><?php echo $row['mb_open'] ? '예' : '아니오'; ?></td>
                            <td class="td_boolean"><?php echo ($row['mb_email_certify'] && preg_match('/[1-9]/', $row['mb_email_certify'])) ? '예' : '아니오'; ?></td>
                            <td class="td_boolean"><?php echo $row['mb_intercept_date'] ? '예' : '아니오'; ?></td>
                            <td class="td_category"><?php echo $group ?></td>
                        </tr>
                        <?php
                    }
                    if ($i == 0) {
                        echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="btn_list03 btn_list">
            <a href="./member_list.php">회원 전체보기</a>
        </div>
    </section>

    <?php
} //endif 최신 회원

/* 최근게시물 섹션 주석처리 - 나중에 사용할 수 있도록 보관
if (!auth_check_menu($auth, '300100', 'r', true)) {

    $sql_common = " from {$g5['board_new_table']} a, {$g5['board_table']} b, {$g5['group_table']} c where a.bo_table = b.bo_table and b.gr_id = c.gr_id ";

    if ($gr_id) {
        $sql_common .= " and b.gr_id = '{$gr_id}' ";
    }
    if (isset($view) && $view) {
        if ($view == 'w') {
            $sql_common .= " and a.wr_id = a.wr_parent ";
        } elseif ($view == 'c') {
            $sql_common .= " and a.wr_id <> a.wr_parent ";
        }
    }
    $sql_order = " order by a.bn_id desc ";

    $sql = " SELECT count(*) as cnt {$sql_common} ";
    $row = sql_fetch($sql);
    $total_count = $row['cnt'];

    $colspan = 5;
    ?>

    <section>
        <h2>최근게시물</h2>

        <div class="tbl_head01 tbl_wrap">
            <table>
                <caption>최근게시물</caption>
                <thead>
                    <tr>
                        <th scope="col">그룹</th>
                        <th scope="col">게시판</th>
                        <th scope="col">제목</th>
                        <th scope="col">이름</th>
                        <th scope="col">일시</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = " SELECT a.*, b.bo_subject, c.gr_subject, c.gr_id {$sql_common} {$sql_order} limit {$new_write_rows} ";
                    $result = sql_query($sql);
                    for ($i = 0; $row = sql_fetch_array($result); $i++) {
                        $tmp_write_table = $g5['write_prefix'] . $row['bo_table'];

                        if ($row['wr_id'] == $row['wr_parent']) {
                            // 원글
                            $comment = "";
                            $comment_link = "";
                            $row2 = sql_fetch(" SELECT * from {$tmp_write_table} where wr_id = '{$row['wr_id']}' ");

                            $name = get_sideview($row2['mb_id'], get_text(cut_str($row2['wr_name'], $config['cf_cut_name'])), $row2['wr_email'], $row2['wr_homepage']);
                            // 당일인 경우 시간으로 표시함
                            $datetime = substr($row2['wr_datetime'], 0, 10);
                            $datetime2 = $row2['wr_datetime'];
                            if ($datetime == G5_TIME_YMD) {
                                $datetime2 = substr($datetime2, 11, 5);
                            } else {
                                $datetime2 = substr($datetime2, 5, 5);
                            }
                        } else {
                            // 코멘트
                            $comment = '댓글. ';
                            $comment_link = '#c_' . $row['wr_id'];
                            $row2 = sql_fetch(" SELECT * from {$tmp_write_table} where wr_id = '{$row['wr_parent']}' ");
                            $row3 = sql_fetch(" SELECT mb_id, wr_name, wr_email, wr_homepage, wr_datetime from {$tmp_write_table} where wr_id = '{$row['wr_id']}' ");

                            $name = get_sideview($row3['mb_id'], get_text(cut_str($row3['wr_name'], $config['cf_cut_name'])), $row3['wr_email'], $row3['wr_homepage']);
                            // 당일인 경우 시간으로 표시함
                            $datetime = substr($row3['wr_datetime'], 0, 10);
                            $datetime2 = $row3['wr_datetime'];
                            if ($datetime == G5_TIME_YMD) {
                                $datetime2 = substr($datetime2, 11, 5);
                            } else {
                                $datetime2 = substr($datetime2, 5, 5);
                            }
                        }
                        ?>

                        <tr>
                            <td class="td_category"><a href="<?php echo G5_BBS_URL ?>/new.php?gr_id=<?php echo $row['gr_id'] ?>"><?php echo cut_str($row['gr_subject'], 10) ?></a></td>
                            <td class="td_category"><a href="<?php echo get_pretty_url($row['bo_table']) ?>"><?php echo cut_str($row['bo_subject'], 20) ?></a></td>
                            <td><a href="<?php echo get_pretty_url($row['bo_table'], $row2['wr_id']); ?><?php echo $comment_link ?>"><?php echo $comment ?><?php echo conv_subject($row2['wr_subject'], 100) ?></a></td>
                            <td class="td_mbname">
                                <div><?php echo $name ?></div>
                            </td>
                            <td class="td_datetime"><?php echo $datetime ?></td>
                        </tr>

                        <?php
                    }
                    if ($i == 0) {
                        echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="btn_list03 btn_list">
            <a href="<?php echo G5_BBS_URL ?>/new.php">최근게시물 더보기</a>
        </div>
    </section>

    <?php
} //endif 최근게시물
*/

/* 최근 포인트 발생내역 섹션 주석처리 - 나중에 사용할 수 있도록 보관
if (!auth_check_menu($auth, '200200', 'r', true)) {

    $sql_common = " from {$g5['point_table']} ";
    $sql_search = " where (1) ";
    $sql_order = " order by po_id desc ";

    $sql = " SELECT count(*) as cnt {$sql_common} {$sql_search} {$sql_order} ";
    $row = sql_fetch($sql);
    $total_count = $row['cnt'];

    $sql = " SELECT * {$sql_common} {$sql_search} {$sql_order} limit {$new_point_rows} ";
    $result = sql_query($sql);

    $colspan = 7;
    ?>

    <section>
        <h2>최근 포인트 발생내역</h2>
        <div class="local_desc02 local_desc">
            전체 <?php echo number_format($total_count) ?> 건 중 <?php echo $new_point_rows ?>건 목록
        </div>

        <div class="tbl_head01 tbl_wrap">
            <table>
                <caption>최근 포인트 발생내역</caption>
                <thead>
                    <tr>
                        <th scope="col">회원아이디</th>
                        <th scope="col">이름</th>
                        <th scope="col">닉네임</th>
                        <th scope="col">일시</th>
                        <th scope="col">포인트 내용</th>
                        <th scope="col">포인트</th>
                        <th scope="col">포인트합</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row2['mb_id'] = '';
                    for ($i = 0; $row = sql_fetch_array($result); $i++) {
                        if ($row2['mb_id'] != $row['mb_id']) {
                            $sql2 = " SELECT mb_id, mb_name, mb_nick, mb_email, mb_homepage, mb_point from {$g5['member_table']} where mb_id = '{$row['mb_id']}' ";
                            $row2 = sql_fetch($sql2);
                        }

                        $mb_nick = get_sideview($row['mb_id'], $row2['mb_nick'], $row2['mb_email'], $row2['mb_homepage']);

                        $link1 = $link2 = "";
                        if (!preg_match("/^\@/", $row['po_rel_table']) && $row['po_rel_table']) {
                            $link1 = '<a href="' . get_pretty_url($row['po_rel_table'], $row['po_rel_id']) . '" target="_blank">';
                            $link2 = '</a>';
                        }
                        ?>

                        <tr>
                            <td class="td_mbid"><a href="./point_list.php?sfl=mb_id&amp;stx=<?php echo $row['mb_id'] ?>"><?php echo $row['mb_id'] ?></a></td>
                            <td class="td_mbname"><?php echo get_text($row2['mb_name']); ?></td>
                            <td class="td_name sv_use">
                                <div><?php echo $mb_nick ?></div>
                            </td>
                            <td class="td_datetime"><?php echo $row['po_datetime'] ?></td>
                            <td><?php echo $link1 . $row['po_content'] . $link2 ?></td>
                            <td class="td_numbig"><?php echo number_format($row['po_point']) ?></td>
                            <td class="td_numbig"><?php echo number_format($row['po_mb_point']) ?></td>
                        </tr>

                        <?php
                    }

                    if ($i == 0) {
                        echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="btn_list03 btn_list">
            <a href="./point_list.php">포인트내역 전체보기</a>
        </div>
    </section>

    <?php
} //endif
*/

$addtional_content_after = run_replace('adm_index_addtional_content_after', '', $is_admin, $auth, $member);
if ($addtional_content_after) {
    echo $addtional_content_after;
}
require_once './admin.tail.php';