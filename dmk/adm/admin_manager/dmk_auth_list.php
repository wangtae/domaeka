<?php
$sub_menu = "190800";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

$dmk_auth = dmk_get_admin_auth();

// 권한이 없거나 로그인하지 않은 경우 처리
if (!is_array($dmk_auth)) {
    alert('접근 권한이 없습니다.', G5_ADMIN_URL);
}

// 도매까 관련 메뉴만 필터링
$dmk_menu_codes = array('190000', '190100', '190200', '190300', '190400', '190500', '190600', '190700');

// 현재 관리자가 권한을 부여할 수 있는 관리자 목록 조회 (sub 관리자만)
$available_admins = array();
$admin_sql = "SELECT mb_id, mb_nick, mb_level, dmk_mb_type, dmk_admin_type, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_level >= 4 AND dmk_admin_type = 'sub' ";

if (!$dmk_auth['is_super']) {
    // main 관리자만 sub 관리자 권한 설정 가능
    if ($dmk_auth['admin_type'] !== 'main') {
        $admin_sql .= " AND 1=0 "; // sub 관리자는 권한 설정 불가
    } else {
        // 같은 계층 또는 하위 계층의 sub 관리자만 권한 설정 가능
        if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            $admin_sql .= " AND dmk_mb_type IN (1, 2, 3) "; // 총판, 대리점, 지점
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            $admin_sql .= " AND dmk_mb_type IN (2, 3) AND (dmk_ag_id = '" . sql_escape_string($dmk_auth['ag_id']) . "' OR dmk_mb_type = 2) ";
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
            $admin_sql .= " AND dmk_mb_type = 3 AND dmk_br_id = '" . sql_escape_string($dmk_auth['br_id']) . "' ";
        }
    }
} else {
    $admin_sql .= " AND dmk_mb_type > 0 "; // 최고관리자는 모든 sub 관리자 권한 설정 가능
}

$admin_sql .= " ORDER BY dmk_mb_type ASC, mb_id ASC ";
$admin_result = sql_query($admin_sql);
while ($admin_row = sql_fetch_array($admin_result)) {
    $level_name = '';
    switch ($admin_row['dmk_mb_type']) {
        case 1: $level_name = '총판'; break;
        case 2: $level_name = '대리점'; break;
        case 3: $level_name = '지점'; break;
        default: $level_name = '관리자'; break;
    }
    $admin_type_name = $admin_row['dmk_admin_type'] === 'sub' ? 'SUB' : 'MAIN';
    
    $available_admins[] = array(
        'mb_id' => $admin_row['mb_id'],
        'mb_nick' => $admin_row['mb_nick'],
        'mb_level' => $admin_row['mb_level'],
        'dmk_mb_type' => $admin_row['dmk_mb_type'],
        'admin_type' => $admin_row['dmk_admin_type'],
        'level_name' => $level_name . '(' . $admin_type_name . ')'
    );
}

$sql_common = " from {$g5['auth_table']} a left join {$g5['member_table']} b on (a.mb_id=b.mb_id) ";

$sql_search = " where a.au_menu IN ('".implode("','", $dmk_menu_codes)."') AND b.dmk_admin_type = 'sub' ";

// 권한별 관리자 필터링 (sub 관리자만)
if (!$dmk_auth['is_super']) {
    $sql_search .= " AND b.mb_level >= 4 "; // 관리자만
    
    // main 관리자만 sub 관리자 권한 조회 가능
    if ($dmk_auth['admin_type'] !== 'main') {
        $sql_search .= " AND 1=0 "; // sub 관리자는 권한 조회 불가
    } else {
        // 같은 계층 또는 하위 계층의 sub 관리자만 조회 가능
        if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            $sql_search .= " AND b.dmk_mb_type IN (1, 2, 3) "; // 총판, 대리점, 지점
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            $sql_search .= " AND b.dmk_mb_type IN (2, 3) AND (b.dmk_ag_id = '" . sql_escape_string($dmk_auth['ag_id']) . "' OR b.dmk_mb_type = 2) ";
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
            $sql_search .= " AND b.dmk_mb_type = 3 AND b.dmk_br_id = '" . sql_escape_string($dmk_auth['br_id']) . "' ";
        }
    }
} else {
    $sql_search .= " AND b.dmk_mb_type > 0 "; // 최고관리자는 모든 sub 관리자 권한 조회 가능
}

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        default:
            $sql_search .= " ({$sfl} like '%{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}

if (!$sst) {
    $sst  = "a.mb_id, au_menu";
    $sod = "";
}
$sql_order = " order by $sst $sod ";

$sql = " select count(*) as cnt {$sql_common} {$sql_search} {$sql_order} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) {
    $page = 1;
}
$from_record = ($page - 1) * $rows;

$sql = " select * {$sql_common} {$sql_search} {$sql_order} limit {$from_record}, {$rows} ";
$result = sql_query($sql);

// URL 쿼리 스트링 생성
$qstr = 'sfl='.$sfl.'&amp;stx='.$stx;

$listall = '<a href="' . $_SERVER['SCRIPT_NAME'] . '" class="ov_listall btn_ov02">전체목록</a>';

$g5['title'] = "서브관리자 권한설정";
require_once G5_ADMIN_PATH.'/admin.head.php';

$colspan = 6;

// 도매까 메뉴 정의
$dmk_auth_menu = array(
    '190000' => '프랜차이즈 관리',
    '190100' => '총판관리',
    '190200' => '대리점관리', 
    '190300' => '지점관리',
    '190400' => '통계분석',
    '190500' => 'URL관리',
    '190600' => '서브관리자관리',
    '190700' => '관리자권한설정'
);
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">설정된 도매까 권한</span><span class="ov_num"><?php echo number_format($total_count) ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
    <input type="hidden" name="sfl" value="a.mb_id" id="sfl">
    <label for="stx" class="sound_only">회원아이디<strong class="sound_only"> 필수</strong></label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" required class="required frm_input">
    <input type="submit" value="검색" id="fsearch_submit" class="btn_submit">
</form>

<form name="fauthlist" id="fauthlist" method="post" action="./dmk_auth_list_delete.php" onsubmit="return fauthlist_submit(this);">
    <input type="hidden" name="sst" value="<?php echo $sst ?>">
    <input type="hidden" name="sod" value="<?php echo $sod ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="token" value="">

    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?> 목록</caption>
            <thead>
                <tr>
                    <th scope="col">
                        <label for="chkall" class="sound_only">현재 페이지 회원 전체</label>
                        <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                    </th>
                    <th scope="col"><?php echo subject_sort_link('a.mb_id') ?>회원아이디</a></th>
                    <th scope="col"><?php echo subject_sort_link('mb_nick') ?>닉네임</a></th>
                    <th scope="col">관리자타입</th>
                    <th scope="col">메뉴</th>
                    <th scope="col">권한</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 0;
                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                    $is_continue = false;
                    
                    // 회원아이디가 없는 메뉴는 삭제함
                    if ($row['mb_id'] == '' && $row['mb_nick'] == '') {
                        sql_query(" delete from {$g5['auth_table']} where au_menu = '{$row['au_menu']}' ");
                        $is_continue = true;
                    }

                    // 도매까 메뉴가 아닌 경우 스킵
                    if (!in_array($row['au_menu'], $dmk_menu_codes)) {
                        $is_continue = true;
                    }

                    if ($is_continue) {
                        continue;
                    }

                    $mb_nick = get_sideview($row['mb_id'], $row['mb_nick'], $row['mb_email'], $row['mb_homepage']);
                    $bg = 'bg' . ($i % 2);
                    
                    // 관리자 타입 표시
                    $admin_type_display = '';
                    switch ($row['dmk_mb_type']) {
                        case 1: $admin_type_display = '총판'; break;
                        case 2: $admin_type_display = '대리점'; break;
                        case 3: $admin_type_display = '지점'; break;
                        default: $admin_type_display = '관리자'; break;
                    }
                    $admin_type_display .= '(' . ($row['dmk_admin_type'] === 'sub' ? 'SUB' : 'MAIN') . ')';
                ?>
                    <tr class="<?php echo $bg; ?>">
                        <td class="td_chk">
                            <input type="hidden" name="au_menu[<?php echo $i ?>]" value="<?php echo $row['au_menu'] ?>">
                            <input type="hidden" name="mb_id[<?php echo $i ?>]" value="<?php echo $row['mb_id'] ?>">
                            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['mb_nick'] ?>님 권한</label>
                            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
                        </td>
                        <td class="td_mbid"><a href="?sfl=a.mb_id&amp;stx=<?php echo $row['mb_id'] ?>"><?php echo $row['mb_id'] ?></a></td>
                        <td class="td_auth_mbnick"><?php echo $mb_nick ?></td>
                        <td class="td_admin_type"><?php echo $admin_type_display ?></td>
                        <td class="td_menu">
                            <?php echo $row['au_menu'] ?>
                            <?php echo isset($dmk_auth_menu[$row['au_menu']]) ? ' - ' . $dmk_auth_menu[$row['au_menu']] : '' ?>
                        </td>
                        <td class="td_auth"><?php echo $row['au_auth'] ?></td>
                    </tr>
                <?php
                    $count++;
                }

                if ($count == 0) {
                    echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="btn_list01 btn_list">
        <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
    </div>

    <?php
    if (strstr($sfl, 'mb_id')) {
        $mb_id = $stx;
    } else {
        $mb_id = '';
    }
    ?>
</form>

<?php
$pagelist = get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'] . '?' . $qstr . '&amp;page=');
echo $pagelist;
?>

<form name="fauthlist2" id="fauthlist2" action="./dmk_auth_update.php" method="post" autocomplete="off" onsubmit="return fauth_add_submit(this);">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="sst" value="<?php echo $sst ?>">
    <input type="hidden" name="sod" value="<?php echo $sod ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="token" value="">

    <section id="add_admin">
        <h2 class="h2_frm">도매까 권한 추가</h2>

        <div class="local_desc01 local_desc">
            <p>
                <strong>SUB 관리자</strong>에게만 메뉴별 권한을 부여할 수 있습니다. MAIN 관리자는 해당 계층의 모든 권한을 자동으로 가집니다.<br>
                권한 <strong>r</strong>은 읽기권한, <strong>w</strong>는 쓰기권한, <strong>d</strong>는 삭제권한입니다.<br>
                <span style="color: #666;">※ MAIN 관리자만 SUB 관리자의 권한을 설정할 수 있습니다.</span>
            </p>
        </div>

        <div class="tbl_frm01 tbl_wrap">
            <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                    <tr>
                        <th scope="row"><label for="mb_id">관리자아이디<strong class="sound_only">필수</strong></label></th>
                        <td>
                            <strong id="msg_mb_id" class="msg_sound_only"></strong>
                            <select name="mb_id" id="mb_id" required class="required frm_input">
                                <option value="">관리자를 선택하세요</option>
                                <?php foreach ($available_admins as $admin) { ?>
                                <option value="<?php echo $admin['mb_id'] ?>" <?php echo ($mb_id == $admin['mb_id']) ? 'selected' : '' ?>>
                                    <?php echo $admin['mb_id'] ?> (<?php echo $admin['mb_nick'] ?>) - <?php echo $admin['level_name'] ?>
                                </option>
                                <?php } ?>
                            </select>
                            <span class="frm_info">
                                현재 권한으로 관리할 수 있는 관리자만 표시됩니다.
                                <?php if (!$dmk_auth['is_super']) { ?>
                                    <?php if ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) { ?>
                                        (총판: 총판~지점 관리자)
                                    <?php } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) { ?>
                                        (대리점: 대리점~지점 관리자)
                                    <?php } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH) { ?>
                                        (지점: 지점 관리자만)
                                    <?php } ?>
                                <?php } ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="au_menu">접근가능메뉴<strong class="sound_only">필수</strong></label></th>
                        <td>
                            <select name="au_menu" id="au_menu" required class="required">
                                <option value="">선택하세요</option>
                                <?php foreach ($dmk_auth_menu as $menu_code => $menu_name) { ?>
                                <option value="<?php echo $menu_code ?>"><?php echo $menu_code ?> <?php echo $menu_name ?></option>
                                <?php } ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="au_auth">권한<strong class="sound_only">필수</strong></label></th>
                        <td>
                            <input type="text" name="au_auth" value="r" id="au_auth" required class="required frm_input" size="10" maxlength="10">
                            <span class="frm_info">r(읽기), w(쓰기), d(삭제) 조합으로 입력하세요. 예: rw, rwd</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="btn_confirm01 btn_confirm">
            <input type="submit" value="권한추가" id="btn_submit" class="btn_submit btn" accesskey="s">
        </div>

    </section>
</form>

<script>
function fauthlist_submit(f) {
    var chk_count = 0;

    for (var i=0; i<f.length; i++) {
        if (f.elements[i].name == "chk[]" && f.elements[i].checked)
            chk_count++;
    }

    if (!chk_count) {
        alert(document.pressed + "할 항목을 하나 이상 선택하세요.");
        return false;
    }

    if(document.pressed == "선택삭제") {
        if(!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
            return false;
        }
    }

    return true;
}

function fauth_add_submit(f) {
    if (!f.mb_id.value) {
        alert("관리자를 선택하세요.");
        f.mb_id.focus();
        return false;
    }

    if (!f.au_menu.value) {
        alert("접근가능메뉴를 선택하세요.");
        f.au_menu.focus();
        return false;
    }

    if (!f.au_auth.value) {
        alert("권한을 입력하세요.");
        f.au_auth.focus();
        return false;
    }

    // 권한 문자열 유효성 검사
    var auth_pattern = /^[rwd]+$/;
    if (!auth_pattern.test(f.au_auth.value)) {
        alert("권한은 r(읽기), w(쓰기), d(삭제)의 조합으로만 입력 가능합니다.\n예: r, rw, rwd");
        f.au_auth.focus();
        return false;
    }

    return true;
}
</script>

<?php
require_once G5_ADMIN_PATH.'/admin.tail.php';
?> 