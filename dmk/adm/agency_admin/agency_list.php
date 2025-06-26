<?php
$sub_menu = "190200";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

// 최고관리자가 아니면 도매까 자체 권한 체크 사용
if ($is_admin != 'super') {
    // 도매까 권한 체크를 이미 위에서 했으므로 여기서는 통과
}
// auth_check_menu($auth, $sub_menu, 'r'); // 도매까 자체 권한 체크로 대체

$g5['title'] = '대리점 관리 <i class="fa fa-building-o dmk-updated-icon" title="개조"></i>';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// SQL common 조인 수정: dmk_agency와 g5_member (대리점 관리자, 총판 관리자) 조인
$sql_common = " FROM dmk_agency a
                LEFT JOIN dmk_distributor d ON a.dt_id = d.dt_id
                LEFT JOIN {$g5['member_table']} ag_m ON a.ag_id = ag_m.mb_id
                LEFT JOIN {$g5['member_table']} dt_m ON a.dt_id = dt_m.mb_id ";
$sql_search = " WHERE 1=1 ";

// 권한에 따른 데이터 필터링
$dmk_auth = dmk_get_admin_auth();

// 디버그 로그 추가: dmk_auth 배열 내용 확인
error_log('DEBUG: dmk_auth content: ' . print_r($dmk_auth, true));

if (!$dmk_auth['is_super']) { // 최고관리자가 아닌 경우
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        // 대리점 관리자는 자신의 대리점 정보만 조회
        $sql_search .= " AND a.ag_id = '".sql_escape_string($dmk_auth['ag_id'])."' ";
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        // 총판 관리자는 자신의 총판에 소속된 대리점 정보만 조회
        // error_log('DEBUG: Distributor dmk_auth[dt_id] at agency_list: ' . (isset($dmk_auth['dt_id']) ? $dmk_auth['dt_id'] : '[EMPTY]')); // 이전 디버그 로그 제거
        $sql_search .= " AND a.dt_id = '".sql_escape_string(isset($dmk_auth['dt_id']) ? $dmk_auth['dt_id'] : '')."' ";
    } else {
        // 그 외의 경우 (지점 관리자 등)에는 대리점 목록에 접근 불가
        alert('접근 권한이 없습니다.');
    }
}

// 검색 조건
// 총판 ID 필터링 추가
$dt_id = isset($_GET['dt_id']) ? sql_escape_string(trim($_GET['dt_id'])) : '';

// error_log('DEBUG: Filter dt_id from GET: ' . $dt_id); // 이전 디버그 로그 제거

if ($dt_id) {
    $sql_search .= " AND a.dt_id = '".sql_escape_string($dt_id)."' ";
}

// error_log('DEBUG: Final sql_search: ' . $sql_search); // 이전 디버그 로그 제거

// HTML 주석으로 디버그 정보 출력
echo '<!-- DEBUG: dmk_auth content: ' . print_r($dmk_auth, true) . ' -->';
echo '<!-- DEBUG: Filter dt_id from GET: ' . $dt_id . ' -->';
echo '<!-- DEBUG: Final sql_search: ' . $sql_search . ' -->';

if ($stx) {
    // mb_name과 mb_nick을 함께 검색
    $sql_search .= " AND (
                        ag_m.mb_id LIKE '%".sql_escape_string($stx)."%' OR
                        ag_m.mb_name LIKE '%".sql_escape_string($stx)."%' OR
                        ag_m.mb_nick LIKE '%".sql_escape_string($stx)."%'
                    ) ";
}

if (!$sst) {
    $sst = "ag_m.mb_datetime";
    $sod = "desc";
}

$sql_order = " ORDER BY $sst $sod ";

// 총판 목록 조회 (본사 관리자용)
$distributors = [];
if ($dmk_auth['is_super']) {
    $distributor_sql = " SELECT d.dt_id, m.mb_name AS dt_name 
                            FROM dmk_distributor d
                            JOIN {$g5['member_table']} m ON d.dt_id = m.mb_id
                            ORDER BY m.mb_name ASC ";
    $distributor_result = sql_query($distributor_sql);
    while($row = sql_fetch_array($distributor_result)) {
        $distributors[] = $row;
    }
}

$sql = " SELECT COUNT(*) as cnt " . $sql_common . $sql_search;


$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

// SELECT 문 수정: g5_member 테이블에서 상세 정보 가져오기
$sql = " SELECT a.id, a.ag_id, a.dt_id, a.ag_status, a.ag_created_by, a.ag_admin_type,
                    ag_m.mb_name AS ag_name,
                    ag_m.mb_nick AS ag_nick,
                    ag_m.mb_tel AS ag_tel,
                    ag_m.mb_hp AS ag_hp,
                    ag_m.mb_datetime AS ag_datetime_from_member, 
                    dt_m.mb_name AS distributor_name
          " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<?php if ($dmk_auth['is_super']) { // 본사 관리자만 총판 선택박스 표시 ?>
<select name="dt_id" id="dt_id" class="frm_input" onchange="this.form.submit();">
    <option value="">총판 전체</option>
    <?php foreach ($distributors as $distributor) { ?>
    <option value="<?php echo $distributor['dt_id']; ?>" <?php echo ($dt_id == $distributor['dt_id']) ? 'selected' : ''; ?>><?php echo $distributor['dt_name']; ?> (<?php echo $distributor['dt_id']; ?>)</option>
    <?php } ?>
</select>
<?php } ?>
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" placeholder="대리점ID, 대리점명, 대표자명">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="local_desc01 local_desc">
    <p>
        <strong>대리점 관리</strong><br>
        • 계층 구조: <span style="color: #e74c3c; font-weight: bold;">HEAD(본사)</span> → <span style="color: #3498db; font-weight: bold;">DISTRUBUTOR(총판)</span> → <span style="color: #2ecc71; font-weight: bold;">AGENCY(대리점)</span> → <span style="color: #f39c12; font-weight: bold;">BRANCH(지점)</span><br>
        • 대리점은 총판 하위의 관리자로서 여러 지점을 관리합니다.<br>
        • 각 대리점별 관리 지점 수를 확인할 수 있습니다.
    </p>
</div>

<?php if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR || $dmk_auth['is_super']) { ?>
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
        <th scope="col">소속 총판</th>
        <th scope="col"><?php echo subject_sort_link('a.ag_id') ?>대리점ID</a></th>
        <th scope="col"><?php echo subject_sort_link('ag_m.mb_nick') ?>대리점명</a></th>        
        <th scope="col"><?php echo subject_sort_link('ag_m.mb_name') ?>회사명</a></th>
        <th scope="col">전화번호</th>
        <th scope="col">관리자ID</th>
        <th scope="col" style="width: 80px;">관리 지점수</th>
        <th scope="col"><?php echo subject_sort_link('ag_m.mb_datetime') ?>등록일</a></th>
        <th scope="col"><?php echo subject_sort_link('a.ag_status') ?>상태</a></th>
        <th scope="col" style="width: 80px;">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 소속 지점 수 조회는 기존 로직 유지 (dmk_branch의 ag_id 사용)
        $branch_sql = " SELECT COUNT(*) as cnt FROM dmk_branch WHERE ag_id = '{$row['ag_id']}' ";
        $branch_row = sql_fetch($branch_sql);
        $branch_count = $branch_row['cnt'];
    ?>

    <tr class="<?php echo $bg; ?>">
        <td>
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['ag_nick'] ?: $row['ag_name'] ?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
            <input type="hidden" name="ag_id[<?php echo $i ?>]" value="<?php echo $row['ag_id'] ?>">
        </td>
        <td>
            <?php echo $row['distributor_name'] ? $row['dt_id'] . ' (' . $row['distributor_name'] . ')' : '<span style="color:#999;">미배정</span>' ?>
        </td>
        <td><?php echo $row['ag_id'] ?></td>
        <td>
            <a href="./agency_form.php?w=u&ag_id=<?php echo $row['ag_id'] ?>">
                <?php echo get_text($row['ag_nick'] ?: $row['ag_name']) ?>
            </a>
        </td>        
        <td><?php echo get_text($row['ag_name']) ?></td>
        <td><?php echo $row['ag_tel'] ?: $row['ag_hp'] ?></td>
        <td>
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['ag_id'] ?>" target="_blank">
                <?php echo $row['ag_id'] ?> (<?php echo $row['ag_name'] ?>)
            </a>
        </td>
        <td class="td_num">
            <a href="../branch_admin/branch_list.php?ag_id=<?php echo $row['ag_id'] ?>" class="btn btn_02">
                <?php echo number_format($branch_count) ?>개
            </a>
        </td>
        <td class="td_datetime"><?php echo substr($row['ag_datetime_from_member'], 0, 10) ?></td>
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
        echo '<tr><td colspan="11" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>
<?php /*
<div class="btn_list01 btn_list">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn_lsmall bx">
</div>
*/?>
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