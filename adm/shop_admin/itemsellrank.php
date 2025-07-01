<?php
$sub_menu = '500100';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, "r");

$g5['title'] = '상품판매순위';
include_once (G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

// 계층별 필터링을 위한 GET 파라미터 처리 <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$fr_date = (isset($_GET['fr_date']) && preg_match("/[0-9]/", $_GET['fr_date'])) ? $_GET['fr_date'] : '';
$to_date = (isset($_GET['to_date']) && preg_match("/[0-9]/", $_GET['to_date'])) ? $_GET['to_date'] : date("Ymd", time());

$doc = isset($_GET['doc']) ? clean_xss_tags($_GET['doc'], 1, 1) : '';
$sort1 = (isset($_GET['sort1']) && in_array($_GET['sort1'], array('ct_status_1', 'ct_status_2', 'ct_status_3', 'ct_status_4', 'ct_status_5', 'ct_status_6', 'ct_status_7', 'ct_status_8', 'ct_status_9', 'ct_status_sum'))) ? $_GET['sort1'] : 'ct_status_sum';
$sort2 = (isset($_GET['sort2']) && in_array($_GET['sort2'], array('desc', 'asc'))) ? $_GET['sort2'] : 'desc';

$sel_ca_id = isset($_GET['sel_ca_id']) ? get_search_string($_GET['sel_ca_id']) : '';

// 도매까 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

// 도매까 권한별 상품 및 장바구니 조회 조건 추가
$cart_where_condition = '';
$item_where_condition = dmk_get_item_where_condition();

// 대리점/지점 관리자의 경우 권한에 따른 장바구니 필터링 추가
if ($dmk_auth && !$dmk_auth['is_super']) {
    switch ($dmk_auth['mb_type']) {
        case 1: // DMK_MB_TYPE_DISTRIBUTOR
            // 총판 관리자: 자신의 총판에 속한 지점들의 주문만
            if (!empty($dmk_auth['dt_id'])) {
                $cart_where_condition = " AND a.dmk_br_id IN (
                    SELECT DISTINCT br_id FROM dmk_branch b
                    JOIN dmk_agency ag ON b.ag_id = ag.ag_id 
                    WHERE ag.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."'
                ) ";
            }
            break;
        case 2: // DMK_MB_TYPE_AGENCY
            // 대리점 관리자: 자신의 대리점에 속한 지점들의 주문만
            if (!empty($dmk_auth['ag_id'])) {
                $cart_where_condition = " AND a.dmk_br_id IN (
                    SELECT DISTINCT br_id FROM dmk_branch 
                    WHERE ag_id = '".sql_escape_string($dmk_auth['ag_id'])."'
                ) ";
            }
            break;
        case 3: // DMK_MB_TYPE_BRANCH
            // 지점 관리자: 자신의 지점 주문만
            if (!empty($dmk_auth['br_id'])) {
                $cart_where_condition = " AND a.dmk_br_id = '".sql_escape_string($dmk_auth['br_id'])."' ";
            }
            break;
    }
}

$sql  = " select a.it_id,
                 b.*,
                 SUM(IF(a.ct_status = '쇼핑',a.ct_qty, 0)) as ct_status_1,
                 SUM(IF(a.ct_status = '주문',a.ct_qty, 0)) as ct_status_2,
                 SUM(IF(a.ct_status = '입금',a.ct_qty, 0)) as ct_status_3,
                 SUM(IF(a.ct_status = '준비',a.ct_qty, 0)) as ct_status_4,
                 SUM(IF(a.ct_status = '배송',a.ct_qty, 0)) as ct_status_5,
                 SUM(IF(a.ct_status = '완료',a.ct_qty, 0)) as ct_status_6,
                 SUM(IF(a.ct_status = '취소',a.ct_qty, 0)) as ct_status_7,
                 SUM(IF(a.ct_status = '반품',a.ct_qty, 0)) as ct_status_8,
                 SUM(IF(a.ct_status = '품절',a.ct_qty, 0)) as ct_status_9,
                 SUM(a.ct_qty) as ct_status_sum
            from {$g5['g5_shop_cart_table']} a, {$g5['g5_shop_item_table']} b ";
$sql .= " where a.it_id = b.it_id " . $cart_where_condition . $item_where_condition;

// 계층별 장바구니 필터링 추가 (GET 파라미터 기반) <i class="fa fa-sitemap dmk-new-icon" title="NEW"></i>
if ($filter_dt_id) {
    $sql .= " AND a.dmk_br_id IN (
        SELECT DISTINCT br_id FROM dmk_branch b
        JOIN dmk_agency a ON b.ag_id = a.ag_id 
        WHERE a.dt_id = '".sql_escape_string($filter_dt_id)."'
    ) ";
}
if ($filter_ag_id) {
    $sql .= " AND a.dmk_br_id IN (
        SELECT DISTINCT br_id FROM dmk_branch 
        WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
    ) ";
}
if ($filter_br_id) {
    $sql .= " AND a.dmk_br_id = '".sql_escape_string($filter_br_id)."' ";
}

if ($fr_date && $to_date)
{
    $fr = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3", $fr_date);
    $to = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3", $to_date);
    $sql .= " and a.ct_time between '$fr 00:00:00' and '$to 23:59:59' ";
}
if ($sel_ca_id)
{
    $sql .= " and b.ca_id like '$sel_ca_id%' ";
}
$sql .= " group by a.it_id
          order by $sort1 $sort2 ";

// SQL 쿼리 실행 전 오류 처리 개선
$result = sql_query($sql);
if (!$result) {
    // SQL 오류 발생시 빈 결과 처리
    $total_count = 0;
    $total_page = 0;
    $result = false;
} else {
    $total_count = sql_num_rows($result);
}

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$rank = ($page - 1) * $rows;

$sql = $sql . " limit $from_record, $rows ";
$result = sql_query($sql);

// URL 쿼리 스트링 생성 (계층 필터 포함) <i class="fa fa-link dmk-new-icon" title="NEW"></i>
$qstr1 = $qstr.'&amp;fr_date='.$fr_date.'&amp;to_date='.$to_date.'&amp;sel_ca_id='.$sel_ca_id;
if ($filter_dt_id) {
    $qstr1 .= '&amp;sdt_id='.$filter_dt_id;
}
if ($filter_ag_id) {
    $qstr1 .= '&amp;sag_id='.$filter_ag_id;
}
if ($filter_br_id) {
    $qstr1 .= '&amp;sbr_id='.$filter_br_id;
}

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">등록상품 </span><span class="ov_num"> <?php echo $total_count; ?>건 </span></span> 
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="doc" value="<?php echo get_sanitize_input($doc); ?>">
<input type="hidden" name="sort1" value="<?php echo get_sanitize_input($sort1); ?>">
<input type="hidden" name="sort2" value="<?php echo get_sanitize_input($sort2); ?>">
<input type="hidden" name="page" value="<?php echo get_sanitize_input($page); ?>">

    <!-- 도매까 계층 선택박스 (NEW) -->
    <?php
    // 도매까 체인 선택박스 포함 (권한에 따라 표시)
    if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == 1) {
        include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
        
        // 현재 선택된 계층 값들 (권한에 따라 자동 설정)
        $current_dt_id = $filter_dt_id;
        $current_ag_id = $filter_ag_id;
        $current_br_id = $filter_br_id;
        
        // 권한에 따른 페이지 타입 결정
        $page_type = DMK_CHAIN_SELECT_FULL;
        if ($dmk_auth['mb_type'] == 1) {
            $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
            // 총판 관리자는 자신의 총판으로 고정
            $current_dt_id = $dmk_auth['dt_id'];
        }
        
        echo dmk_render_chain_select([
            'page_type' => $page_type,
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
    } else if ($dmk_auth['mb_type'] == 2) {
        // 대리점 관리자는 소속 지점만 선택 가능
        include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
        
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
                'sdt_id' => $dmk_auth['dt_id'],
                'sag_id' => $dmk_auth['ag_id'],
                'sbr_id' => $filter_br_id
            ],
            'placeholders' => [
                'distributor' => '전체 총판',
                'agency' => '전체 대리점',
                'branch' => '전체 지점'
            ]
        ]);
    } else if ($dmk_auth['mb_type'] == 3) {
        // 지점 관리자는 자신의 지점만 표시 (선택박스 없음)
        echo '<div class="dmk-chain-select-container">';
        echo '<span class="dmk-hierarchy-info">현재 조회 범위: ' . $dmk_auth['br_name'] . ' 지점</span>';
        echo '</div>';
    }
    ?>
    <!-- //도매까 계층 선택박스 -->

<label for="sel_ca_id" class="sound_only">검색대상</label>
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

기간설정
<label for="fr_date" class="sound_only">시작일</label>
<input type="text" name="fr_date" value="<?php echo $fr_date; ?>" id="fr_date" required class="required frm_input" size="8" maxlength="8"> 에서
<label for="to_date" class="sound_only">종료일</label>
<input type="text" name="to_date" value="<?php echo $to_date; ?>" id="to_date" required class="required frm_input" size="8" maxlength="8"> 까지
<input type="submit" value="검색" class="btn_submit">

</form>

<div class="local_desc01 local_desc">
    <p>판매량을 합산하여 상품판매순위를 집계합니다.</p>
</div>

<div class="btn_fixed_top">
    <a href="./itemstocklist.php" class="btn_02 btn">상품재고관리</a>
    <a href="./itemlist.php" class="btn_01 btn">상품등록</a>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">순위</th>
        <th scope="col">상품명</th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_1",1)."&amp;$qstr1"; ?>">쇼핑</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_2",1)."&amp;$qstr1"; ?>">주문</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_3",1)."&amp;$qstr1"; ?>">입금</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_4",1)."&amp;$qstr1"; ?>">준비</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_5",1)."&amp;$qstr1"; ?>">배송</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_6",1)."&amp;$qstr1"; ?>">완료</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_7",1)."&amp;$qstr1"; ?>">취소</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_8",1)."&amp;$qstr1"; ?>">반품</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_9",1)."&amp;$qstr1"; ?>">품절</a></th>
        <th scope="col"><a href="<?php echo title_sort("ct_status_sum",1)."&amp;$qstr1"; ?>">합계</a></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $i = 0;
    if ($result) {
        for ($i=0; $row=sql_fetch_array($result); $i++)
        {
            $href = shop_item_url($row['it_id']);

            $num = $rank + $i + 1;

            $bg = 'bg'.($i%2);
            ?>
            <tr class="<?php echo $bg; ?>">
                <td class="td_num"><?php echo $num; ?></td>
                <td class="td_left"><a href="<?php echo $href; ?>"><?php echo get_it_image($row['it_id'], 50, 50); ?> <?php echo cut_str($row['it_name'],30); ?></a></td>
                <td class="td_num"><?php echo $row['ct_status_1']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_2']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_3']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_4']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_5']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_6']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_7']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_8']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_9']; ?></td>
                <td class="td_num"><?php echo $row['ct_status_sum']; ?></td>
            </tr>
            <?php
        }
    }

    if ($i == 0) {
        echo '<tr><td colspan="12" class="empty_table">자료가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr1&amp;page="); ?>

<script>
$(function() {
    $("#fr_date, #to_date").datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: "yymmdd",
        showButtonPanel: true,
        yearRange: "c-99:c+99",
        maxDate: "+0d"
    });
});
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');