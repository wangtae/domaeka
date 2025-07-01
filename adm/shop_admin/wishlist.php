<?php
$sub_menu = '500140';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, "r");

$g5['title'] = '보관함현황';
include_once (G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

// 계층별 필터링을 위한 GET 파라미터 처리 <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$fr_date = (isset($_GET['fr_date']) && preg_match("/[0-9]/", $_GET['fr_date'])) ? $_GET['fr_date'] : '';
$to_date = (isset($_GET['to_date']) && preg_match("/[0-9]/", $_GET['to_date'])) ? $_GET['to_date'] : '';

$doc = isset($_GET['doc']) ? clean_xss_tags($_GET['doc'], 1, 1) : '';
$sort1 = (isset($_GET['sort1']) && in_array($_GET['sort1'], array('mb_id', 'it_id', 'wi_time', 'wi_ip'))) ? $_GET['sort1'] : 'it_id_cnt';
$sort2 = (isset($_GET['sort2']) && in_array($_GET['sort2'], array('desc', 'asc'))) ? $_GET['sort2'] : 'desc';

$sel_ca_id = isset($_GET['sel_ca_id']) ? get_search_string($_GET['sel_ca_id']) : '';

// 도매까 권한별 상품 조회 조건 추가
$item_where_condition = dmk_get_item_where_condition();

$sql  = " select a.it_id,
                 b.it_name,
                 COUNT(a.it_id) as it_id_cnt
            from {$g5['g5_shop_wish_table']} a, {$g5['g5_shop_item_table']} b ";
$sql .= " where a.it_id = b.it_id " . $item_where_condition;

// 계층별 상품 필터링 추가 (GET 파라미터 기반) <i class="fa fa-sitemap dmk-new-icon" title="NEW"></i>
if ($filter_dt_id) {
    $sql .= " and b.dmk_owner_id IN (
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
    $sql .= " and b.dmk_owner_id IN (
        SELECT DISTINCT CONCAT('agency_', ag_id) FROM dmk_agency WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
        UNION
        SELECT DISTINCT CONCAT('branch_', br_id) FROM dmk_branch WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
    ) ";
}
if ($filter_br_id) {
    $sql .= " and b.dmk_owner_id = 'branch_".sql_escape_string($filter_br_id)."' ";
}

if ($fr_date && $to_date)
{
    $fr = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3", $fr_date);
    $to = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3", $to_date);
    $sql .= " and a.wi_time between '$fr 00:00:00' and '$to 23:59:59' ";
}
if ($sel_ca_id)
{
    $sql .= " and b.ca_id like '$sel_ca_id%' ";
}
$sql .= " group by a.it_id, b.it_name
          order by $sort1 $sort2 ";
$result = sql_query($sql);
$total_count = sql_num_rows($result);

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
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="doc" value="<?php echo get_sanitize_input($doc); ?>">
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
        echo "<option value='{$row1['ca_id']}'".get_selected($row1['ca_id'], $sel_ca_id).">$nbsp{$row1['ca_name']}\n";
    }
    ?>
</select>

<label for="fr_date" class="sound_only">시작일</label>
<input type="text" name="fr_date" value="<?php echo $fr_date; ?>" id="fr_date" required class="required frm_input" size="8" maxlength="8">
~
<label for="to_date" class="sound_only">종료일</label>
<input type="text" name="to_date" value="<?php echo $to_date; ?>" id="to_date" required class="required frm_input" size="8" maxlength="8">
<input type="submit" value="검색" class="btn_submit">

</form>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <thead>
    <tr>
        <th scope="col">순위</th>
        <th scope="col">상품평</th>
        <th scope="col">건수</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        // $s_mod = icon("수정", "./itemqaform.php?w=u&amp;iq_id={$row['iq_id']}&amp;$qstr");
        // $s_del = icon("삭제", "javascript:del('./itemqaupdate.php?w=d&amp;iq_id={$row['iq_id']}&amp;$qstr');");

        $href = shop_item_url($row['it_id']);
        $num = $rank + $i + 1;

        $bg = 'bg'.($i%2);
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_num"><?php echo $num; ?></td>
        <td class="td_left">
            <a href="<?php echo $href; ?>"><?php echo get_it_image($row['it_id'], 50, 50); ?> <?php echo cut_str($row['it_name'],30); ?></a>
        </td>
        <td class="td_num"><?php echo $row['it_id_cnt']; ?></td>
    </tr>
    <?php
    }

    if ($i == 0) {
        echo '<tr><td colspan="3" class="empty_table">자료가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr1&amp;page="); ?>

<div class="local_desc01 local_desc">
    <p>고객님들이 보관함에 가장 많이 넣은 순으로 순위를 출력합니다.</p>
</div>

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