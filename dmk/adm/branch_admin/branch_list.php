<?php
$sub_menu = "190300";
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 공통 체인 선택박스 라이브러리 포함
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

$g5['title'] = '지점 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 체인 선택박스 에셋 포함
echo dmk_include_chain_select_assets();

// 현재 관리자의 권한 정보 가져오기
$dmk_auth = dmk_get_admin_auth();

// 검색 및 필터링 변수 처리
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$sst = isset($_GET['sst']) ? clean_xss_tags($_GET['sst']) : '';
$sod = isset($_GET['sod']) ? clean_xss_tags($_GET['sod']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 필터링 변수 (체인 선택박스와 일치하도록 수정)
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 하위 호환성을 위한 별칭 (기존 dt_id, ag_id도 지원)
$dt_id = $sdt_id ?: (isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '');
$ag_id = $sag_id ?: (isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '');

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
        // 지점 관리자: 자신의 지점만 조회 (로그인한 사용자의 mb_id를 사용)
        $sql_search .= " AND br_m.mb_id = '".sql_escape_string($member['mb_id'])."' ";
    } else {
        // 그 외의 경우 접근 불가
        alert('접근 권한이 없습니다.');
    }
}

// 총판 필터링 (선택된 경우)
if ($sdt_id) {
    $sql_search .= " AND a.dt_id = '" . sql_escape_string($sdt_id) . "' ";
}

// 대리점 필터링 (선택된 경우)
if ($sag_id) {
    $sql_search .= " AND b.ag_id = '" . sql_escape_string($sag_id) . "' ";
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

// 공통 체인 선택박스 렌더링
$page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY; // 기본값

// 관리자 권한에 따라 페이지 유형 동적 결정
if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    // 총판 관리자는 대리점만 선택 가능
    $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    // 대리점 관리자는 선택박스 표시 안함
    $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
    // 지점 관리자는 선택박스 표시 안함
    $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
}
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">

<?php
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY,
    'auto_submit' => true,
    'current_values' => [
        'sdt_id' => $sdt_id,
        'sag_id' => $sag_id,
        'sbr_id' => $sbr_id,
        'dt_id' => $dt_id,
        'ag_id' => $ag_id,
    ],
    'debug' => false
]);
?>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" placeholder="지점ID, 지점명, 대표자명">
<input type="submit" class="btn_submit" value="검색">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var distributorSelect = document.getElementById('sdt_id');
    if (distributorSelect) {
        distributorSelect.addEventListener('change', function() {
            document.getElementById('fsearch').submit();
        });
    }
});
</script>

<div class="btn_fixed_top">
    <?php
    if (dmk_can_create_admin('branch')) { // 지점 등록 권한 확인
        $form_params = [];
        if (!empty($sdt_id)) {
            $form_params[] = 'dt_id=' . urlencode($sdt_id);
        }
        if (!empty($sag_id)) {
            $form_params[] = 'ag_id=' . urlencode($sag_id);
        }
        $form_qstr = !empty($form_params) ? '?' . implode('&amp;', $form_params) : '';
    ?>
    <a href="./branch_form.php<?php echo $form_qstr; ?>" class="btn_01 btn">지점 등록</a>
    <?php
    }
    ?>
</div>

<div class="local_desc01 local_desc">
    <p>
        <strong>지점 관리</strong><br>
        • 지점은 대리점 하위의 관리자로서 직접 주문을 받고 처리합니다.<br>
        • 각 지점의 주문 페이지 바로가기 링크를 제공합니다.
    </p>
</div>

<form name="fbranchlist" id="fbranchlist" action="./branch_list_update.php" onsubmit="return fbranchlist_submit(this);" method="post">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="token" value="">
<input type="hidden" name="sdt_id" value="<?php echo $sdt_id ?>">
<input type="hidden" name="sag_id" value="<?php echo $sag_id ?>">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">지점 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">번호</th>
        <th scope="col"><?php echo subject_sort_link('br_id')?>지점ID</a></th>
        <th scope="col"><?php echo subject_sort_link('br_name')?>지점명</a></th>
        <th scope="col">대표자</th>
        <th scope="col">대리점</th>
        <th scope="col">총판</th>
        <th scope="col">연락처</th>
        <th scope="col"><?php echo subject_sort_link('br_datetime_from_member')?>가입일</a></th>
        <th scope="col">상태</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['br_id'] ?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
            <input type="hidden" name="br_id[<?php echo $i ?>]" value="<?php echo $row['br_id'] ?>">
        </td>
        <td class="td_num"><?php echo $total_count - ($page - 1) * $rows - $i; ?></td>
        <td class="td_left"><?php echo $row['br_id'] ?></td>
        <td class="td_left"><?php echo $row['br_nick'] ?></td>
        <td class="td_left"><?php echo $row['br_name'] ?></td>
        <td class="td_left"><?php echo $row['ag_name'] ?> (<?php echo $row['ag_id'] ?>)</td>
        <td class="td_left"><?php echo $row['dt_name'] ?> (<?php echo $row['dt_id'] ?>)</td>
        <td class="td_left"><?php echo $row['br_tel'] ? $row['br_tel'] : $row['br_hp'] ?></td>
        <td class="td_datetime"><?php echo substr($row['br_datetime_from_member'], 0, 10) ?></td>
        <td class="td_mng">
            <?php if ($row['br_status'] == 1) { ?>
                <span class="txt_true">활성</span>
            <?php } else { ?>
                <span class="txt_false">비활성</span>
            <?php } ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./branch_form.php?w=u&amp;br_id=<?php echo $row['br_id'] ?>&amp;<?php echo $qstr ?>" class="btn btn_03">수정</a>
            <?php if ($row['br_shortcut_code']) { ?>
            <a href="<?php echo G5_DMK_URL ?>/order/<?php echo $row['br_shortcut_code'] ?>" target="_blank" class="btn btn_02">주문페이지</a>
            <?php } ?>
        </td>
    </tr>
    <?php
    }
    if ($i == 0) {
        echo '<tr><td colspan="11" class="empty_table">자료가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_list01 btn_list">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn_02 btn">
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<script>
function fbranchlist_submit(f) {
    if (!is_checked("chk[]")) {
        alert("선택된 자료가 없습니다.\n\n선택하신 후 이용해 주십시오.");
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