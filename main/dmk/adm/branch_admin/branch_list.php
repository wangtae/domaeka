<?php
$sub_menu = "200200";
include_once dirname(__FILE__) . '/../../../adm/_common.php';

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('branch_list')) {
    alert('접근 권한이 없습니다.');
}

// 최고관리자가 아니면 도매까 자체 권한 체크 사용
if ($is_admin != 'super') {
    // 도매까 권한 체크를 이미 위에서 했으므로 여기서는 통과
}
// auth_check_menu($auth, $sub_menu, 'r'); // 도매까 자체 권한 체크로 대체

// 권한 확인
$dmk_auth = dmk_get_admin_auth();
$filter_ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';

// 대리점 관리자는 자신의 대리점 지점만 조회
if ($dmk_auth['mb_type'] == 2) {
    $filter_ag_id = $dmk_auth['ag_id'];
}

$g5['title'] = '지점 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$sql_common = " FROM dmk_branch b LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id ";
$sql_search = " WHERE 1=1 ";

// 대리점 필터링
if ($filter_ag_id) {
    $sql_search .= " AND b.ag_id = '" . sql_escape_string($filter_ag_id) . "' ";
}

// 검색 조건
if ($stx) {
    $sql_search .= " AND (b.br_id LIKE '%".sql_escape_string($stx)."%' OR b.br_name LIKE '%".sql_escape_string($stx)."%' OR b.br_ceo_name LIKE '%".sql_escape_string($stx)."%') ";
}

if (!$sst) {
    $sst = "b.br_datetime";
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

$sql = " SELECT b.*, a.ag_name " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

// 대리점 목록 조회 (필터링용)
$agency_options = '<option value="">전체 대리점</option>';
if ($dmk_auth['mb_type'] == 1 || $dmk_auth['is_super']) {
    $agency_sql = " SELECT ag_id, ag_name FROM dmk_agency WHERE ag_status = 1 ORDER BY ag_name ";
    $agency_result = sql_query($agency_sql);
    while ($agency_row = sql_fetch_array($agency_result)) {
        $selected = ($filter_ag_id == $agency_row['ag_id']) ? ' selected' : '';
        $agency_options .= '<option value="' . $agency_row['ag_id'] . '"' . $selected . '>' . $agency_row['ag_name'] . ' (' . $agency_row['ag_id'] . ')</option>';
    }
}
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<?php if ($dmk_auth['mb_type'] == 1 || $dmk_auth['is_super']) { ?>
<select name="ag_id" id="ag_id" onchange="this.form.submit();">
    <?php echo $agency_options ?>
</select>
<?php } ?>
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="btn_add01 btn_add">
    <a href="./branch_form.php<?php echo $filter_ag_id ? '?ag_id='.$filter_ag_id : '' ?>" id="branch_add">지점 등록</a>
</div>

<form name="fbranchlist" id="fbranchlist" action="./branch_list_update.php" onsubmit="return fbranchlist_submit(this);" method="post">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="ag_id" value="<?php echo $filter_ag_id ?>">
<input type="hidden" name="token" value="">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col"><?php echo subject_sort_link('b.br_id') ?>지점ID</a></th>
        <th scope="col"><?php echo subject_sort_link('b.br_name') ?>지점명</a></th>
        <th scope="col"><?php echo subject_sort_link('a.ag_name') ?>소속대리점</a></th>
        <th scope="col">대표자명</th>
        <th scope="col">전화번호</th>
        <th scope="col">관리자ID</th>
        <th scope="col"><?php echo subject_sort_link('b.br_datetime') ?>등록일</a></th>
        <th scope="col"><?php echo subject_sort_link('b.br_status') ?>상태</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 관리자 정보 조회
        $admin_sql = " SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '{$row['br_mb_id']}' ";
        $admin_row = sql_fetch($admin_sql);
        $admin_name = $admin_row ? $admin_row['mb_name'] : '미지정';
    ?>

    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['br_name'] ?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
            <input type="hidden" name="br_id[<?php echo $i ?>]" value="<?php echo $row['br_id'] ?>">
        </td>
        <td class="td_left"><?php echo $row['br_id'] ?></td>
        <td class="td_left">
            <a href="./branch_form.php?w=u&br_id=<?php echo $row['br_id'] ?>"><?php echo get_text($row['br_name']) ?></a>
        </td>
        <td class="td_left">
            <a href="../agency_admin/agency_form.php?w=u&ag_id=<?php echo $row['ag_id'] ?>"><?php echo get_text($row['ag_name']) ?></a>
        </td>
        <td><?php echo get_text($row['br_ceo_name']) ?></td>
        <td><?php echo $row['br_phone'] ?></td>
        <td>
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['br_mb_id'] ?>" target="_blank">
                <?php echo $row['br_mb_id'] ?> (<?php echo $admin_name ?>)
            </a>
        </td>
        <td class="td_datetime"><?php echo substr($row['br_datetime'], 0, 10) ?></td>
        <td class="td_mng">
            <?php echo $row['br_status'] ? '<span class="txt_true">활성</span>' : '<span class="txt_false">비활성</span>' ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./branch_form.php?w=u&br_id=<?php echo $row['br_id'] ?>" class="btn btn_03">수정</a>
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

<div class="btn_list01 btn_list">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn_lsmall bx">
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<script>
function fbranchlist_submit(f)
{
    if (!is_checked("chk[]")) {
        alert("선택된 자료가 없습니다.\n\n선택된 자료가 있는지 확인하세요.");
        return false;
    }

    if(document.pressed == "선택삭제") {
        if(!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
            return false;
        }
    }

    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 