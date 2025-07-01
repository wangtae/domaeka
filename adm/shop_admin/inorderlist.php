<?php
$sub_menu = '400410';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, 'r');

// 계층별 필터링을 위한 GET 파라미터 처리 <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 도매까 권한에 따른 조건 추가
$dmk_auth = dmk_get_admin_auth();
$branch_filter = '';

if ($dmk_auth && !$dmk_auth['is_super']) {
    switch ($dmk_auth['mb_type']) {
        case 2: // 대리점 - 소속 지점의 주문만
            $branch_ids = dmk_get_agency_branch_ids($dmk_auth['ag_id']);
            if (!empty($branch_ids)) {
                $branch_filter = " AND EXISTS (SELECT 1 FROM {$g5['g5_shop_cart_table']} ct WHERE ct.od_id = o.cart_id AND ct.dmk_br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "'))";
            } else {
                $branch_filter = " AND 1=0"; // 소속 지점이 없으면 조회 불가
            }
            break;
        case 3: // 지점 - 자신의 주문만
            $branch_filter = " AND EXISTS (SELECT 1 FROM {$g5['g5_shop_cart_table']} ct WHERE ct.od_id = o.cart_id AND ct.dmk_br_id = '" . sql_escape_string($dmk_auth['br_id']) . "')";
            break;
    }
}

$sql_common = " from {$g5['g5_shop_order_data_table']} o ";

$sql_search = " where cart_id <> '0' " . $branch_filter;

// 계층별 주문 필터링 추가 (GET 파라미터 기반) <i class="fa fa-sitemap dmk-new-icon" title="NEW"></i>
if ($filter_dt_id) {
    $sql_search .= " AND EXISTS (
        SELECT 1 FROM {$g5['g5_shop_cart_table']} ct 
        JOIN dmk_branch b ON ct.dmk_br_id = b.br_id 
        JOIN dmk_agency a ON b.ag_id = a.ag_id 
        JOIN dmk_distributor d ON a.dt_id = d.dt_id 
        WHERE ct.od_id = o.cart_id AND d.dt_id = '".sql_escape_string($filter_dt_id)."'
    )";
}
if ($filter_ag_id) {
    $sql_search .= " AND EXISTS (
        SELECT 1 FROM {$g5['g5_shop_cart_table']} ct 
        JOIN dmk_branch b ON ct.dmk_br_id = b.br_id 
        WHERE ct.od_id = o.cart_id AND b.ag_id = '".sql_escape_string($filter_ag_id)."'
    )";
}
if ($filter_br_id) {
    $sql_search .= " AND EXISTS (
        SELECT 1 FROM {$g5['g5_shop_cart_table']} ct 
        WHERE ct.od_id = o.cart_id AND ct.dmk_br_id = '".sql_escape_string($filter_br_id)."'
    )";
}

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        case 'od_id' :
            $sql_search .= " ({$sfl} = '{$stx}') ";
            break;
        default :
            $sql_search .= " ({$sfl} like '%{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}

if (!$sst) {
    $sst  = "od_id";
    $sod = "desc";
}
$sql_order = " order by {$sst} {$sod} ";

$sql = " select count(*) as cnt
            {$sql_common}
            {$sql_search}
            {$sql_order} ";
$row = sql_fetch($sql);
$total_count = $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql = " select *
            {$sql_common}
            {$sql_search}
            {$sql_order}
            limit {$from_record}, {$rows} ";
$result = sql_query($sql);

$g5['title'] = '미완료주문';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$colspan = 10;
?>

<div class="local_ov01 local_ov">
   <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num">  <?php echo number_format($total_count ?? 0) ?> 건 </span></span> 
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
    
    <!-- 도매까 계층 선택박스 (NEW) -->
    <?php
    // 도매까 체인 선택박스 포함
    include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
    
    // 현재 선택된 계층 값들
    $current_dt_id = $filter_dt_id;
    $current_ag_id = $filter_ag_id;
    $current_br_id = $filter_br_id;
    
    echo dmk_render_chain_select([
        'page_type' => DMK_CHAIN_SELECT_FULL,
        'auto_submit' => true,
        'form_id' => 'fsearch',
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
    ?>
    <!-- //도매까 계층 선택박스 -->

    <select name="sfl" title="검색대상">
        <option value="od_id"<?php echo get_selected($sfl, "od_id"); ?>>주문번호</option>
    </select>
    <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" required class="required frm_input">
    <input type="submit" class="btn_submit" value="검색">
</form>

<form name="finorderlist" id="finorderlist" method="post" action="./inorderlistdelete.php" onsubmit="return finorderlist_submit(this);">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>">
<input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>">
<input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>">
<input type="hidden" name="token" value="">

<div class="tbl_head01 tbl_wrap" id="inorderlist">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">미완료주문 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col"><?php echo subject_sort_link('od_id') ?>주문번호</a></th>
        <th scope="col">PG</th>
        <th scope="col">주문자</th>
        <th scope="col">주문자전화</th>
        <th scope="col">받는분</th>
        <th scope="col">주문금액</th>
        <th scope="col">결제방법</th>
        <th scope="col">주문일시</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $data = unserialize(base64_decode($row['dt_data']));

        switch($row['dt_pg']) {
            case 'inicis':
                $pg = 'KG이니시스';
                break;
            case 'lg':
                $pg = 'LGU+';
                break;
            default:
                $pg = 'KCP';
                break;
        }

        // 주문금액
        $sql = " select sum(if(io_type = '1', io_price, (ct_price + io_price)) * ct_qty) as price from {$g5['g5_shop_cart_table']} where od_id = '{$row['cart_id']}' and ct_status = '쇼핑' ";
        $ct = sql_fetch($sql);

        $bg = 'bg'.($i%2);
    ?>

    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <input type="hidden" id="od_id_<?php echo $i; ?>" name="od_id[<?php echo $i; ?>]" value="<?php echo $row['od_id']; ?>">
            <input type="checkbox" id="chk_<?php echo $i; ?>" name="chk[]" value="<?php echo $i; ?>" title="내역선택">
        </td>
        <td class="td_odrnum2"><?php echo $row['od_id']; ?></td>
        <td class="td_center"><?php echo $pg; ?></td>
        <td class="td_name"><?php echo get_text($data['od_name']); ?></td>
        <td class="td_center"><?php echo get_text($data['od_tel']); ?></td>
        <td class="td_name"><?php echo get_text($data['od_b_name']); ?></td>
        <td class="td_price"><?php echo number_format($ct['price']); ?></td>
        <td class="td_center"><?php echo $data['od_settle_case']; ?></td>
        <td class="td_time"><?php echo $row['dt_time']; ?></td>
        <td class="td_mng td_mng_m">
            <a href="./inorderform.php?od_id=<?php echo $row['od_id']; ?>&amp;<?php echo $qstr; ?>" class="btn btn_03"><span class="sound_only"><?php echo $row['od_id']; ?> </span>보기</a>
            <a href="./inorderformupdate.php?w=d&amp;od_id=<?php echo $row['od_id']; ?>&amp;<?php echo $qstr; ?>" onclick="return delete_confirm(this);" class="btn btn_02"><span class="sound_only"><?php echo $row['od_id']; ?> </span>삭제</a>
        </td>
    </tr>

    <?php
    }

    if ($i == 0)
        echo '<tr><td colspan="'.$colspan.'" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
</div>

</form>

<?php echo get_paging($config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
function finorderlist_submit(f)
{
    if (!is_checked("chk[]")) {
        alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
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