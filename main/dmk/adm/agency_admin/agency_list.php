<?php
$sub_menu = "200100";
include_once './_common.php';

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu('agency_list')) {
    alert('접근 권한이 없습니다.');
}

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '대리점 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$sql_common = " FROM dmk_agency ";
$sql_search = " WHERE 1=1 ";

// 권한에 따른 데이터 필터링
$dmk_auth = dmk_get_admin_auth();
if ($dmk_auth['mb_type'] == 2) {
    // 대리점 관리자는 자신의 대리점 정보만 조회
    $sql_search .= " AND ag_id = '".sql_escape_string($dmk_auth['ag_id'])."' ";
}

// 검색 조건
if ($stx) {
    $sql_search .= " AND (ag_id LIKE '%".sql_escape_string($stx)."%' OR ag_name LIKE '%".sql_escape_string($stx)."%' OR ag_ceo_name LIKE '%".sql_escape_string($stx)."%') ";
}

if (!$sst) {
    $sst = "ag_datetime";
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

$sql = " SELECT * " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

$g5['title'] = '대리점 관리';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
<input type="submit" class="btn_submit" value="검색">
</form>

<?php if ($dmk_auth['mb_type'] == 1 || $dmk_auth['is_super']) { ?>
<div class="btn_add01 btn_add">
    <a href="./agency_form.php" id="agency_add">대리점 등록</a>
</div>
<?php } ?>

<form name="fagencylist" id="fagencylist" action="./agency_list_update.php" onsubmit="return fagencylist_submit(this);" method="post">
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
            <label for="chkall" class="sound_only">전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col"><?php echo subject_sort_link('ag_id') ?>대리점ID</a></th>
        <th scope="col"><?php echo subject_sort_link('ag_name') ?>대리점명</a></th>
        <th scope="col">대표자명</th>
        <th scope="col">전화번호</th>
        <th scope="col">관리자ID</th>
        <th scope="col"><?php echo subject_sort_link('ag_datetime') ?>등록일</a></th>
        <th scope="col"><?php echo subject_sort_link('ag_status') ?>상태</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 관리자 정보 조회
        $admin_sql = " SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '{$row['ag_mb_id']}' ";
        $admin_row = sql_fetch($admin_sql);
        $admin_name = $admin_row ? $admin_row['mb_name'] : '미지정';
        
        // 소속 지점 수 조회
        $branch_sql = " SELECT COUNT(*) as cnt FROM dmk_branch WHERE ag_id = '{$row['ag_id']}' ";
        $branch_row = sql_fetch($branch_sql);
        $branch_count = $branch_row['cnt'];
    ?>

    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['ag_name'] ?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
            <input type="hidden" name="ag_id[<?php echo $i ?>]" value="<?php echo $row['ag_id'] ?>">
        </td>
        <td class="td_left"><?php echo $row['ag_id'] ?></td>
        <td class="td_left">
            <a href="./agency_form.php?w=u&ag_id=<?php echo $row['ag_id'] ?>"><?php echo get_text($row['ag_name']) ?></a>
        </td>
        <td><?php echo get_text($row['ag_ceo_name']) ?></td>
        <td><?php echo $row['ag_phone'] ?></td>
        <td>
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['ag_mb_id'] ?>" target="_blank">
                <?php echo $row['ag_mb_id'] ?> (<?php echo $admin_name ?>)
            </a>
        </td>
        <td class="td_datetime"><?php echo substr($row['ag_datetime'], 0, 10) ?></td>
        <td class="td_mng">
            <?php echo $row['ag_status'] ? '<span class="txt_true">활성</span>' : '<span class="txt_false">비활성</span>' ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./agency_form.php?w=u&ag_id=<?php echo $row['ag_id'] ?>" class="btn btn_03">수정</a>
            <a href="../branch_admin/branch_list.php?ag_id=<?php echo $row['ag_id'] ?>" class="btn btn_02">지점관리(<?php echo $branch_count ?>)</a>
        </td>
    </tr>

    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="9" class="empty_table">자료가 없습니다.</td></tr>';
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
function fagencylist_submit(f)
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