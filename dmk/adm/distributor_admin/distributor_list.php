<?php
$sub_menu = "190100";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
// $sub_menu 값을 사용하여 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

// 최고관리자가 아니면 도매까 자체 권한 체크 사용
if ($is_admin != 'super') {
    // 도매까 권한 체크를 이미 위에서 했으므로 여기서는 통과
}
// auth_check_menu($auth, $sub_menu, 'r'); // 도매까 자체 권한 체크로 대체

$g5['title'] = '총판 관리 <i class="fa fa-star dmk-new-icon" title="NEW"></i>';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 총판은 최고관리자 하위의 계층이므로, dmk_mb_type이 총판(1)이면서 dmk_admin_type이 'main'인 회원들을 조회
// 또한 dmk_distributor 테이블에 실제 데이터가 존재하는 총판만 표시
// 계층 구조: admin(영카트 최고관리자) > distributor(총판) > agency(대리점) > branch(지점)
$sql_common = " FROM {$g5['member_table']} m 
                INNER JOIN dmk_distributor d ON m.mb_id = d.dt_id";
$sql_search = " WHERE m.dmk_mb_type = 1 AND m.dmk_admin_type = 'main' "; // 총판이면서 메인 관리자만 조회

// 권한에 따른 데이터 필터링
$dmk_auth = dmk_get_admin_auth();

// 디버그 로그 추가
error_log("[DMK DEBUG] distributor_list.php - dmk_auth: " . print_r($dmk_auth, true));
error_log("[DMK DEBUG] distributor_list.php - DMK_MB_TYPE_DISTRIBUTOR: " . DMK_MB_TYPE_DISTRIBUTOR);

// NOTE: dmk_can_access_menu 함수에서 이미 권한을 체크하므로, 여기서는 최고관리자 여부를 별도로 체크할 필요 없음.
//       총판 관리자는 자신의 하위 대리점 및 지점 데이터를 조회할 수 있어야 함.
//       dmk_get_admin_auth() 결과의 is_super만으로 체크하면 총판 계정은 해당 페이지에 접근할 수 없음.
if (!$dmk_auth['is_super']) { // 최고 관리자가 아닌 경우
    error_log("[DMK DEBUG] distributor_list.php - Not super admin, mb_type: " . $dmk_auth['mb_type']);
    if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { // 총판 관리자인 경우
        // 자신의 mb_id와 일치하는 총판 정보만 조회
        $sql_search .= " AND m.mb_id = '".sql_escape_string($dmk_auth['mb_id'])."' ";
        error_log("[DMK DEBUG] distributor_list.php - Distributor access granted for: " . $dmk_auth['mb_id']);
    } else {
        // 그 외의 경우 (대리점, 지점 관리자 등)에는 총판 목록에 접근 불가
        error_log("[DMK DEBUG] distributor_list.php - Access denied for mb_type: " . $dmk_auth['mb_type']);
        alert('접근 권한이 없습니다.');
    }
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

$sql = " SELECT m.*, d.dt_status,
            (SELECT COUNT(*) FROM dmk_agency a JOIN dmk_distributor d ON a.dt_id COLLATE utf8_general_ci = d.dt_id COLLATE utf8_general_ci WHERE d.dt_id COLLATE utf8_general_ci = m.mb_id COLLATE utf8_general_ci) as agency_count,
            (SELECT COUNT(*) FROM dmk_branch b JOIN dmk_agency a ON b.ag_id COLLATE utf8_general_ci = a.ag_id COLLATE utf8_general_ci JOIN dmk_distributor d ON a.dt_id COLLATE utf8_general_ci = d.dt_id COLLATE utf8_general_ci WHERE d.dt_id COLLATE utf8_general_ci = m.mb_id COLLATE utf8_general_ci) as branch_count
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
        • 계층 구조: <span style="color: #e74c3c; font-weight: bold;">HEAD(본사)</span> → <span style="color: #3498db; font-weight: bold;">DISTRUBUTOR(총판)</span> → <span style="color: #2ecc71; font-weight: bold;">AGENCY(대리점)</span> → <span style="color: #f39c12; font-weight: bold;">BRANCH(지점)</span><br>
        • 총판은 본사 하위의 관리자로서 여러 대리점을 관리합니다.<br>
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
        <th scope="col"><?php echo subject_sort_link('m.mb_id') ?>총판ID</a></th>
        <th scope="col"><?php echo subject_sort_link('m.mb_name') ?>총판이름</a></th>
        <th scope="col">회사명명</th>
        <th scope="col">이메일</th>
        <th scope="col">전화번호</th>
        <th scope="col" style="width: 90px;">관리 대리점 수</th>
        <th scope="col" style="width: 80px;">관리 지점 수</th>
        <th scope="col"><?php echo subject_sort_link('m.mb_datetime') ?>생성일</a></th>
        <th scope="col"><?php echo subject_sort_link('d.dt_status') ?>상태</a></th>
        <th scope="col" style="width: 90px;">관리</th>
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
        } else {
            $level_str = '레벨 ' . $row['mb_level'];
        }
    ?>

    <tr class="<?php echo $bg; ?>">
        <td>
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>" target="_blank">
                <?php echo $row['mb_id'] ?>
            </a>
        </td>
        <td><?php echo get_text($row['mb_name']) ?></td>
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
        <td class="td_mng">
            <?php echo $row['dt_status'] ? '<span class="txt_true">활성</span>' : '<span class="txt_false">비활성</span>' ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./distributor_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>" class="btn btn_03">수정</a>
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