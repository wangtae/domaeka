<?php
$sub_menu = "190300";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

$g5['title'] = '지점 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 현재 관리자의 권한 정보 가져오기
$dmk_auth = dmk_get_admin_auth();

// 필터링 변수
$dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';

// SQL 공통 부분 - g5_member와 dmk_branch 테이블 조인
$sql_common = " FROM {$g5['member_table']} br_m 
                JOIN dmk_branch b ON br_m.mb_id = b.br_id 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                LEFT JOIN {$g5['member_table']} ag_m ON a.ag_id = ag_m.mb_id 
                LEFT JOIN dmk_distributor d ON a.dt_id = d.dt_id 
                LEFT JOIN {$g5['member_table']} dt_m ON d.dt_id = dt_m.mb_id ";

$sql_search = " WHERE br_m.dmk_mb_type = " . DMK_MB_TYPE_BRANCH . " AND br_m.dmk_admin_type = 'main' ";

// 권한에 따른 데이터 필터링
if (!$dmk_auth['is_super']) {
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자: 자신의 총판에 속한 지점들만 조회
        $sql_search .= " AND br_m.dmk_dt_id = '".sql_escape_string($dmk_auth['mb_id'])."' ";
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자: 자신의 대리점에 속한 지점들만 조회
        $sql_search .= " AND b.ag_id = '".sql_escape_string($dmk_auth['ag_id'])."' ";
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자: 자신의 지점만 조회
        $sql_search .= " AND b.br_id = '".sql_escape_string($dmk_auth['br_id'])."' ";
    } else {
        // 그 외의 경우 접근 불가
        alert('접근 권한이 없습니다.');
    }
}

// 총판 필터링 (선택된 경우)
if ($dt_id) {
    $sql_search .= " AND a.dt_id = '" . sql_escape_string($dt_id) . "' ";
}

// 대리점 필터링 (선택된 경우)
if ($ag_id) {
    $sql_search .= " AND b.ag_id = '" . sql_escape_string($ag_id) . "' ";
}

// 검색 조건
if ($stx) {
    $sql_search .= " AND (b.br_id LIKE '%".sql_escape_string($stx)."%' OR br_m.mb_nick LIKE '%".sql_escape_string($stx)."%' OR br_m.mb_name LIKE '%".sql_escape_string($stx)."%') ";
}

if (!$sst) {
    $sst = "br_m.mb_datetime";
    $sod = "desc";
}

$sql_order = " ORDER BY $sst $sod ";

$sql = " SELECT COUNT(*) as cnt " . $sql_common . $sql_search;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

// SELECT 문: g5_member 테이블에서 상세 정보 가져오기
$sql = " SELECT b.id, b.br_id, b.ag_id, b.br_status, b.br_created_by, b.br_admin_type, b.br_shortcut_code,
                br_m.mb_name AS br_name,
                br_m.mb_nick AS br_nick,
                br_m.mb_tel AS br_tel,
                br_m.mb_hp AS br_hp,
                br_m.mb_datetime AS br_datetime_from_member,
                ag_m.mb_nick AS ag_name,
                COALESCE(d.dt_id, '') AS dt_id,
                COALESCE(dt_m.mb_nick, '') AS dt_name
          " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

// 총판 목록 조회 (필터링용)
$distributors = [];
if ($dmk_auth['is_super']) {
    $distributor_sql = " SELECT d.dt_id, m.mb_nick AS dt_name 
                         FROM dmk_distributor d
                         JOIN {$g5['member_table']} m ON d.dt_id = m.mb_id
                         WHERE d.dt_status = 1 
                         ORDER BY m.mb_nick ASC ";
    $distributor_result = sql_query($distributor_sql);
    while($row = sql_fetch_array($distributor_result)) {
        $distributors[] = $row;
    }
}

// 대리점 목록 조회 (필터링용)
$agencies = [];
if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    $agency_sql = " SELECT a.ag_id, m.mb_nick AS ag_name 
                    FROM dmk_agency a
                    JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
                    WHERE a.ag_status = 1 ";
    
    // 총판 관리자는 자신의 총판에 속한 대리점만 선택 가능
    if (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        $agency_sql .= " AND a.dt_id = '".sql_escape_string($dmk_auth['mb_id'])."' ";
    } else if ($dmk_auth['is_super'] && $dt_id) { // 본사 관리자가 총판을 선택한 경우
        $agency_sql .= " AND a.dt_id = '".sql_escape_string($dt_id)."' ";
    }
    
    $agency_sql .= " ORDER BY m.mb_nick ASC ";
    $agency_result = sql_query($agency_sql);
    while($row = sql_fetch_array($agency_result)) {
        $agencies[] = $row;
    }
}
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<?php if ($dmk_auth['is_super']) { // 본사 관리자만 총판 선택 박스 노출 ?>
<select name="dt_id" id="dt_id" class="frm_input" onchange="this.form.ag_id.value=''; this.form.submit();">
    <option value="">전체 총판</option>
    <?php foreach ($distributors as $distributor) { ?>
    <option value="<?php echo $distributor['dt_id']; ?>" <?php echo ($dt_id == $distributor['dt_id']) ? 'selected' : ''; ?>><?php echo $distributor['dt_name']; ?> (<?php echo $distributor['dt_id']; ?>)</option>
    <?php } ?>
</select>
<?php } ?>
<?php if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { ?>
<select name="ag_id" id="ag_id" class="frm_input" onchange="this.form.submit();">
    <option value="">전체 대리점</option>
    <?php foreach ($agencies as $agency) { ?>
    <option value="<?php echo $agency['ag_id']; ?>" <?php echo ($ag_id == $agency['ag_id']) ? 'selected' : ''; ?>><?php echo $agency['ag_name']; ?> (<?php echo $agency['ag_id']; ?>)</option>
    <?php } ?>
</select>
<?php } ?>
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" placeholder="지점ID, 지점명, 대표자명">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="local_desc01 local_desc">
    <p>
        <strong>지점 관리</strong><br>
        • 지점은 대리점 하위의 관리자로서 직접 주문을 받고 처리합니다.<br>
        • 각 지점의 주문 페이지 바로가기 링크를 제공합니다.
    </p>
</div>

<?php if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR || $dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY || $dmk_auth['is_super']) { ?>
<div class="btn_add01 btn_add">
    <a href="./branch_form.php<?php echo $ag_id ? '?ag_id='.$ag_id : '' ?><?php echo ($ag_id && $dt_id) ? '&dt_id='.$dt_id : ($dt_id ? '?dt_id='.$dt_id : '') ?>" id="branch_add">지점 등록</a>
</div>
<?php } ?>

<form name="fbranchlist" id="fbranchlist" action="./branch_list_update.php" onsubmit="return fbranchlist_submit(this);" method="post">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">
<?php if ($dmk_auth['is_super']) { // 본사 관리자가 총판을 선택한 경우만 dt_id 전달 ?>
<input type="hidden" name="dt_id" value="<?php echo $dt_id ?>">
<?php } ?>
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">소속 총판</th>
        <th scope="col">소속 대리점</th>
        <th scope="col"><?php echo subject_sort_link('b.br_id') ?>지점ID</a></th>
        <th scope="col"><?php echo subject_sort_link('br_m.mb_nick') ?>지점명</a></th>
        
        <th scope="col"><?php echo subject_sort_link('br_m.mb_name') ?>회사명</a></th>
        <th scope="col">연락처</th>
        <th scope="col"><?php echo subject_sort_link('br_m.mb_datetime') ?>등록일</a></th>
        <th scope="col"><?php echo subject_sort_link('b.br_status') ?>상태</a></th>
        <th scope="col" style="width: 120px;">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);

        // dt_id와 dt_name 키가 항상 존재하도록 초기화 (Undefined array key 경고 방지)
        $dt_id_val = array_key_exists('dt_id', $row) ? $row['dt_id'] : '';
        $dt_name_val = array_key_exists('dt_name', $row) ? $row['dt_name'] : '';

        // ag_id와 ag_name 키가 항상 존재하도록 초기화 (Undefined array key 경고 방지)
        $ag_id_val = array_key_exists('ag_id', $row) ? $row['ag_id'] : '';
        $ag_name_val = array_key_exists('ag_name', $row) ? $row['ag_name'] : '';
    ?>

    <tr class="<?php echo $bg; ?>">
        <td>
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['br_nick'] ?: $row['br_name'] ?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
            <input type="hidden" name="br_id[<?php echo $i ?>]" value="<?php echo $row['br_id'] ?>">
        </td>
        <td>
            <?php 
            if ($dt_id_val && $dt_name_val) {
                echo $dt_id_val . ' (' . $dt_name_val . ')';
            } else {
                echo '<span style="color:#999;">미배정</span>';
            }
            ?>
        </td>
        <td>
            <?php 
            if ($ag_id_val && $ag_name_val) {
                echo $ag_id_val . ' (' . $ag_name_val . ')';
            } else {
                echo '<span style="color:#999;">미배정</span>';
            }
            ?>
        </td>
        <td><?php echo $row['br_id'] ?></td>
        <td>
            <a href="./branch_form.php?w=u&br_id=<?php echo $row['br_id'] ?>">
                <?php echo get_text($row['br_nick'] ?: $row['br_name']) ?>
            </a>
        </td>
        
        <td><?php echo get_text($row['br_name']) ?></td>
        <td><?php echo $row['br_tel'] ?: $row['br_hp'] ?></td>

        <td class="td_datetime"><?php echo substr($row['br_datetime_from_member'], 0, 10) ?></td>
        <td class="td_mng">
            <?php echo $row['br_status'] ? '<span class="txt_true">활성</span>' : '<span class="txt_false">비활성</span>' ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./branch_form.php?w=u&br_id=<?php echo $row['br_id'] ?>" class="btn btn_03">수정</a>
            <?php if (!empty($row['br_shortcut_code'])) { ?>
                <a href="<?php echo G5_URL; ?>/go/<?php echo $row['br_shortcut_code']; ?>" target="_blank" class="btn btn_02">주문 페이지지</a>
            <?php } else { ?>
                <span style="color:#999;">미등록</span>
            <?php } ?>
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

<script>
function fbranchlist_submit(f)
{
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
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 