<?php
$sub_menu = "201000";
include_once './_common.php';

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('distributor_list')) {
    alert('접근 권한이 없습니다.');
}

// 최고관리자가 아니면 도매까 자체 권한 체크 사용
if ($is_admin != 'super') {
    // 도매까 권한 체크를 이미 위에서 했으므로 여기서는 통과
}
// auth_check_menu($auth, $sub_menu, 'r'); // 도매까 자체 권한 체크로 대체

$g5['title'] = '총판 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 총판은 최고관리자 하위의 계층이므로, dmk_mb_type이 총판(1)인 회원들을 조회
// 계층 구조: admin(영카트 최고관리자) > distributor(총판) > agency(대리점) > branch(지점)
$sql_common = " FROM {$g5['member_table']} m ";
$sql_search = " WHERE (m.dmk_mb_type = 1 OR (m.dmk_mb_type = 0 AND m.mb_level >= 9 AND m.mb_level < 10)) "; // 총판 레벨

// 권한에 따른 데이터 필터링
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super']) {
    // 최고관리자가 아니면 접근 제한
    alert('최고관리자만 접근 가능합니다.');
}

// 검색 조건
if ($stx) {
    $sql_search .= " AND (m.mb_id LIKE '%".sql_escape_string($stx)."%' OR m.mb_name LIKE '%".sql_escape_string($stx)."%' OR m.mb_nick LIKE '%".sql_escape_string($stx)."%') ";
}

if (!$sst) {
    $sst = "m.mb_datetime";
    $sod = "desc";
}

$sql_order = " ORDER BY $sst $sod ";

$sql = " SELECT COUNT(*) as cnt " . $sql_common . $sql_search;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql = " SELECT m.*,
            (SELECT COUNT(*) FROM dmk_agency WHERE ag_mb_id = m.mb_id) as agency_count,
            (SELECT COUNT(*) FROM dmk_branch b JOIN dmk_agency a ON b.ag_id = a.ag_id WHERE a.ag_mb_id = m.mb_id) as branch_count
         " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" placeholder="회원ID, 이름, 닉네임">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="local_desc01 local_desc">
    <p>
        <strong>총판 관리</strong><br>
        • 계층 구조: <span style="color: #e74c3c; font-weight: bold;">admin(영카트 최고관리자)</span> → <span style="color: #3498db; font-weight: bold;">distributor(총판)</span> → <span style="color: #2ecc71; font-weight: bold;">agency(대리점)</span> → <span style="color: #f39c12; font-weight: bold;">branch(지점)</span><br>
        • 총판은 영카트 최고관리자 하위의 관리자로서 여러 대리점을 관리합니다.<br>
        • 각 총판별 관리 대리점 수와 산하 지점 수를 확인할 수 있습니다.
    </p>
</div>

<form name="fdistributorlist" id="fdistributorlist" method="post">
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
        <th scope="col"><?php echo subject_sort_link('m.mb_id') ?>회원ID</a></th>
        <th scope="col"><?php echo subject_sort_link('m.mb_name') ?>이름</a></th>
        <th scope="col">닉네임</th>
        <th scope="col">이메일</th>
        <th scope="col">전화번호</th>
        <th scope="col">관리 대리점 수</th>
        <th scope="col">관리 지점 수</th>
        <th scope="col"><?php echo subject_sort_link('m.mb_datetime') ?>가입일</a></th>
        <th scope="col"><?php echo subject_sort_link('m.mb_level') ?>권한</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 권한 레벨 표시
        $level_str = '';
        if (isset($row['dmk_mb_type']) && $row['dmk_mb_type'] == 1) {
            $level_str = '<span class="txt_true">총판</span>';
        } else if ($row['mb_level'] >= 9) {
            $level_str = '<span class="txt_blue">총판 후보</span>';
        } else {
            $level_str = '레벨 ' . $row['mb_level'];
        }
    ?>

    <tr class="<?php echo $bg; ?>">
        <td class="td_left">
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>" target="_blank">
                <?php echo $row['mb_id'] ?>
            </a>
        </td>
        <td class="td_left"><?php echo get_text($row['mb_name']) ?></td>
        <td><?php echo get_text($row['mb_nick']) ?></td>
        <td><?php echo $row['mb_email'] ?></td>
        <td><?php echo $row['mb_hp'] ?></td>
        <td class="td_num">
            <a href="../agency_admin/agency_list.php?distributor_id=<?php echo $row['mb_id'] ?>" class="btn btn_02">
                <?php echo number_format($row['agency_count']) ?>개
            </a>
        </td>
        <td class="td_num">
            <a href="../branch_admin/branch_list.php?distributor_id=<?php echo $row['mb_id'] ?>" class="btn btn_02">
                <?php echo number_format($row['branch_count']) ?>개
            </a>
        </td>
        <td class="td_datetime"><?php echo substr($row['mb_datetime'], 0, 10) ?></td>
        <td class="td_mng"><?php echo $level_str ?></td>
        <td class="td_mng td_mng_s">
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>" target="_blank" class="btn btn_03">회원수정</a>
            <a href="../agency_admin/agency_list.php?distributor_id=<?php echo $row['mb_id'] ?>" class="btn btn_02">대리점관리</a>
        </td>
    </tr>

    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="10" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 