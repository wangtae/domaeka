<?php
$sub_menu = '400620';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, 'r');

// 도매까 관리자 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

// 계층별 필터링을 위한 GET 파라미터 처리 <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$doc = isset($_GET['doc']) ? clean_xss_tags($_GET['doc'], 1, 1) : '';
$sort1 = (isset($_GET['sort1']) && in_array($_GET['sort1'], array('it_id', 'it_name', 'it_stock_qty', 'it_use', 'it_soldout', 'it_stock_sms'))) ? $_GET['sort1'] : '';
$sort2 = (isset($_GET['sort2']) && in_array($_GET['sort2'], array('desc', 'asc'))) ? $_GET['sort2'] : 'desc';
$sel_field = (isset($_GET['sel_field']) && in_array($_GET['sel_field'], array('it_id', 'it_name', 'it_stock_qty', 'it_use', 'it_soldout', 'it_stock_sms')) ) ? $_GET['sel_field'] : '';
$sel_ca_id = isset($_GET['sel_ca_id']) ? get_search_string($_GET['sel_ca_id']) : '';
$search = isset($_GET['search']) ? get_search_string($_GET['search']) : '';

$g5['title'] = '상품재고관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$sql_search = " where 1 ";

// 계층별 상품 필터링 추가 <i class="fa fa-sitemap dmk-new-icon" title="NEW"></i>
if ($filter_dt_id) {
    $sql_search .= " and dmk_owner_id IN (
        SELECT DISTINCT CONCAT('distributor_', dt_id) FROM dmk_distributor WHERE dt_id = '".sql_escape_string($filter_dt_id)."'
        UNION
        SELECT DISTINCT CONCAT('agency_', ag_id) FROM dmk_agency WHERE dt_id = '".sql_escape_string($filter_dt_id)."'
        UNION  
        SELECT DISTINCT CONCAT('branch_', br_id) FROM dmk_branch b 
        JOIN dmk_agency a ON b.ag_id = a.ag_id 
        WHERE a.dt_id = '".sql_escape_string($filter_dt_id)."'
    ) ";
}
if ($filter_ag_id) {
    $sql_search .= " and dmk_owner_id IN (
        SELECT DISTINCT CONCAT('agency_', ag_id) FROM dmk_agency WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
        UNION
        SELECT DISTINCT CONCAT('branch_', br_id) FROM dmk_branch WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
    ) ";
}
if ($filter_br_id) {
    $sql_search .= " and dmk_owner_id = 'branch_".sql_escape_string($filter_br_id)."' ";
}

if ($search != "") {
	if ($sel_field != "") {
    	$sql_search .= " and $sel_field like '%$search%' ";
    }
}

if ($sel_ca_id != "") {
    $sql_search .= " and ca_id like '$sel_ca_id%' ";
}

if ($sel_field == "")  $sel_field = "it_name";
if ($sort1 == "") $sort1 = "it_stock_qty";
if ($sort2 == "") $sort2 = "asc";

$sql_common = "  from {$g5['g5_shop_item_table']} ";
$sql_common .= $sql_search;
$sql_common .= dmk_get_item_where_condition();

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? $row['cnt'] : 0;

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql  = " select it_id,
                 it_name,
                 it_use,
                 it_stock_qty,
                 it_stock_sms,
                 it_noti_qty,
                 it_soldout,
                 ca_id,
                 dmk_it_owner_type,
                 dmk_it_owner_id
           $sql_common
          order by $sort1 $sort2
          limit $from_record, $rows ";
$result = sql_query($sql);

// URL 쿼리 스트링 생성 (계층 필터 포함) <i class="fa fa-link dmk-new-icon" title="NEW"></i>
$qstr1 = 'sel_ca_id='.$sel_ca_id.'&amp;sel_field='.$sel_field.'&amp;search='.$search;
if ($filter_dt_id) {
    $qstr1 .= '&amp;sdt_id='.$filter_dt_id;
}
if ($filter_ag_id) {
    $qstr1 .= '&amp;sag_id='.$filter_ag_id;
}
if ($filter_br_id) {
    $qstr1 .= '&amp;sbr_id='.$filter_br_id;
}
$qstr = $qstr1.'&amp;sort1='.$sort1.'&amp;sort2='.$sort2.'&amp;page='.$page;

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">전체 상품</span><span class="ov_num">  <?php echo $total_count; ?>개</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="doc" value="<?php echo get_sanitize_input($doc); ?>">
<input type="hidden" name="sort1" value="<?php echo get_sanitize_input($sort1); ?>">
<input type="hidden" name="sort2" value="<?php echo get_sanitize_input($sort2); ?>">
<input type="hidden" name="page" value="<?php echo get_sanitize_input($page); ?>">

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
        'form_id' => 'flist',
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

<label for="sel_ca_id" class="sound_only">분류선택</label>
<select name="sel_ca_id" id="sel_ca_id">
    <option value=''>전체분류</option>
    <?php
    $sql1 = " select ca_id, ca_name from {$g5['g5_shop_category_table']} order by ca_order, ca_id ";
    $result1 = sql_query($sql1);
    for ($i=0; $row1=sql_fetch_array($result1); $i++) {
        $len = strlen($row1['ca_id']) / 2 - 1;
        $nbsp = "";
        for ($i=0; $i<$len; $i++) $nbsp .= "&nbsp;&nbsp;&nbsp;";
        echo '<option value="'.$row1['ca_id'].'" '.get_selected($sel_ca_id, $row1['ca_id']).'>'.$nbsp.$row1['ca_name'].'</option>'.PHP_EOL;
    }
    ?>
</select>

<label for="sel_field" class="sound_only">검색대상</label>
<select name="sel_field" id="sel_field">
    <option value="it_name" <?php echo get_selected($sel_field, 'it_name'); ?>>상품명</option>
    <option value="it_id" <?php echo get_selected($sel_field, 'it_id'); ?>>상품코드</option>
</select>

<label for="search" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="search" id="search" value="<?php echo $search; ?>" required class="frm_input required">
<input type="submit" value="검색" class="btn_submit">

</form>

<div class="local_desc01 local_desc">
    <p>재고수정의 수치를 수정하시면 창고재고의 수치가 변경됩니다.</p>
</div>


<form name="fitemstocklist" action="./itemstocklistupdate.php" method="post">
<input type="hidden" name="sort1" value="<?php echo $sort1; ?>">
<input type="hidden" name="sort2" value="<?php echo $sort2; ?>">
<input type="hidden" name="sel_ca_id" value="<?php echo $sel_ca_id; ?>">
<input type="hidden" name="sel_field" value="<?php echo $sel_field; ?>">
<input type="hidden" name="search" value="<?php echo $search; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col"><a href="<?php echo title_sort("it_id") . "&amp;$qstr1"; ?>">상품코드</a></th>
        <th scope="col"><a href="<?php echo title_sort("it_name") . "&amp;$qstr1"; ?>">상품명</a></th>
        <th scope="col">소유자</th>
        <th scope="col"><a href="<?php echo title_sort("it_stock_qty") . "&amp;$qstr1"; ?>">창고재고</a></th>
        <th scope="col">주문대기</th>
        <th scope="col">가재고</th>
        <th scope="col">재고수정</th>
        <th scope="col">통보수량</th>
        <th scope="col"><a href="<?php echo title_sort("it_use") . "&amp;$qstr1"; ?>">판매</a></th>
        <th scope="col"><a href="<?php echo title_sort("it_soldout") . "&amp;$qstr1"; ?>">품절</a></th>
        <th scope="col"><a href="<?php echo title_sort("it_stock_sms") . "&amp;$qstr1"; ?>">재입고알림</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        $href = shop_item_url($row['it_id']);

        // 선택옵션이 있을 경우 주문대기 수량 계산하지 않음
        $sql2 = " select count(*) as cnt from {$g5['g5_shop_item_option_table']} where it_id = '{$row['it_id']}' and io_type = '0' and io_use = '1' ";
        $row2 = sql_fetch($sql2);
        $wait_qty = 0;

        if(! (isset($row2['cnt']) && $row2['cnt'])) {
            $sql1 = " select SUM(ct_qty) as sum_qty
                        from {$g5['g5_shop_cart_table']}
                       where it_id = '{$row['it_id']}'
                         and ct_stock_use = '0'
                         and ct_status in ('쇼핑', '주문', '입금', '준비') ";
            $row1 = sql_fetch($sql1);
            $wait_qty = $row1['sum_qty'];
        }

        // 가재고 (미래재고)
        $temporary_qty = $row['it_stock_qty'] - $wait_qty;

        // 통보수량보다 재고수량이 작을 때
        $it_stock_qty = number_format($row['it_stock_qty']);
        $it_stock_qty_st = ''; // 스타일 정의
        if($row['it_stock_qty'] <= $row['it_noti_qty']) {
            $it_stock_qty_st = ' sit_stock_qty_alert';
        }

        $bg = 'bg'.($i%2);

    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_numbig">
            <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
            <?php echo $row['it_id']; ?>
        </td>
        <td class="td_left"><a href="<?php echo $href; ?>"><?php echo get_it_image($row['it_id'], 50, 50); ?> <?php echo cut_str(stripslashes($row['it_name']), 60, "&#133"); ?></a></td>
        <td class="td_center">
            <?php 
            // 도매까 소유자 정보 표시
            $owner_display = '';
            switch($row['dmk_it_owner_type']) {
                case '1':
                    $sql_owner = "SELECT dt_name FROM dmk_distributor WHERE dt_id = '" . sql_escape_string($row['dmk_it_owner_id']) . "'";
                    $row_owner = sql_fetch($sql_owner);
                    $owner_display = '<span class="label_type1">총판</span><br>' . ($row_owner ? $row_owner['dt_name'] : $row['dmk_it_owner_id']);
                    break;
                case '2':
                    $sql_owner = "SELECT ag_name FROM dmk_agency WHERE ag_id = '" . sql_escape_string($row['dmk_it_owner_id']) . "'";
                    $row_owner = sql_fetch($sql_owner);
                    $owner_display = '<span class="label_type2">대리점</span><br>' . ($row_owner ? $row_owner['ag_name'] : $row['dmk_it_owner_id']);
                    break;
                case '3':
                    $sql_owner = "SELECT br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($row['dmk_it_owner_id']) . "'";
                    $row_owner = sql_fetch($sql_owner);
                    $owner_display = '<span class="label_type3">지점</span><br>' . ($row_owner ? $row_owner['br_name'] : $row['dmk_it_owner_id']);
                    break;
                default:
                    $owner_display = '<span class="label_type0">미지정</span>';
            }
            echo $owner_display;
            ?>
        </td>
        <td class="td_num<?php echo $it_stock_qty_st; ?>"><?php echo $it_stock_qty; ?></td>
        <td class="td_num"><?php echo number_format((float)$wait_qty); ?></td>
        <td class="td_num"><?php echo number_format((float)$temporary_qty); ?></td>
        <td class="td_num">
            <label for="stock_qty_<?php echo $i; ?>" class="sound_only">재고수정</label>
            <input type="text" name="it_stock_qty[<?php echo $i; ?>]" value="<?php echo $row['it_stock_qty']; ?>" id="stock_qty_<?php echo $i; ?>" class="frm_input" size="10" autocomplete="off">
        </td>
        <td class="td_num">
            <label for="noti_qty_<?php echo $i; ?>" class="sound_only">통보수량</label>
            <input type="text" name="it_noti_qty[<?php echo $i; ?>]" value="<?php echo $row['it_noti_qty']; ?>" id="noti_qty_<?php echo $i; ?>" class="frm_input" size="10" autocomplete="off">
        </td>
        <td class="td_chk2">
            <label for="use_<?php echo $i; ?>" class="sound_only">판매</label>
            <input type="checkbox" name="it_use[<?php echo $i; ?>]" value="1" id="use_<?php echo $i; ?>" <?php echo ($row['it_use'] ? "checked" : ""); ?>>
        </td>
        <td class="td_chk2">
            <label for="soldout_<?php echo $i; ?>" class="sound_only">품절</label>
            <input type="checkbox" name="it_soldout[<?php echo $i; ?>]" value="1" id="soldout_<?php echo $i; ?>" <?php echo ($row['it_soldout'] ? "checked" : ""); ?>>
        </td>
        <td class="td_chk2">
            <label for="stock_sms_<?php echo $i; ?>" class="sound_only">재입고 알림</label>
            <input type="checkbox" name="it_stock_sms[<?php echo $i; ?>]" value="1" id="stock_sms_<?php echo $i; ?>" <?php echo ($row['it_stock_sms'] ? "checked" : ""); ?>>
        </td>
        <td class="td_mng td_mng_s"><a href="./itemform.php?w=u&amp;it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>&amp;<?php echo $qstr; ?>" class="btn btn_03">수정</a></td>
    </tr>
    <?php
    }
    if (!$i)
        echo '<tr><td colspan="12" class="empty_table"><span>자료가 없습니다.</span></td></tr>';
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./optionstocklist.php" class="btn btn_02">상품옵션재고</a>
    <a href="./itemsellrank.php"  class="btn btn_02">상품판매순위</a>
    <input type="submit" value="일괄수정" class="btn_submit btn">
</div>
</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');