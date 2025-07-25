<?php
$sub_menu = '400300';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 도매까 소유자 유형 상수 정의
if (!defined('DMK_OWNER_TYPE_SUPER_ADMIN')) define('DMK_OWNER_TYPE_SUPER_ADMIN', 'super_admin');
if (!defined('DMK_OWNER_TYPE_DISTRIBUTOR')) define('DMK_OWNER_TYPE_DISTRIBUTOR', 'distributor');
if (!defined('DMK_OWNER_TYPE_AGENCY')) define('DMK_OWNER_TYPE_AGENCY', 'agency');
if (!defined('DMK_OWNER_TYPE_BRANCH')) define('DMK_OWNER_TYPE_BRANCH', 'branch');

dmk_auth_check_menu($auth, $sub_menu, 'r');

// 도매까 관리자 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','it_id','it_maker','it_brand','it_model','it_origin','it_sell_email'))) {
    $sfl = '';
}

// 계층별 필터링을 위한 GET 파라미터 처리 <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$g5['title'] = '상품관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 계층별 탭 처리 추가
$tab = isset($_GET['tab']) ? clean_xss_tags($_GET['tab']) : 'branch';
if (!in_array($tab, ['branch', 'agency', 'distributor'])) {
    $tab = 'branch';
}

// 탭별 필터링 조건 추가
$tab_where = "";
switch($tab) {
    case 'branch':
        $tab_where = " AND a.dmk_br_id != '' ";
        break;
    case 'agency':  
        $tab_where = " AND a.dmk_ag_id != '' AND a.dmk_br_id = '' ";
        break;
    case 'distributor':
        $tab_where = " AND a.dmk_dt_id != '' AND a.dmk_ag_id = '' AND a.dmk_br_id = '' ";
        break;
}

// 분류
$ca_list  = '<option value="">선택</option>'.PHP_EOL;
$sql = " select * from {$g5['g5_shop_category_table']} ";
if ($is_admin != 'super')
    $sql .= " where ca_mb_id = '{$member['mb_id']}' ";
$sql .= " order by ca_order, ca_id ";
$result = sql_query($sql);


for ($i=0; $row=sql_fetch_array($result); $i++)
{
    $len = strlen($row['ca_id']) / 2 - 1;
    $nbsp = '';
    for ($i=0; $i<$len; $i++) {
        $nbsp .= '&nbsp;&nbsp;&nbsp;';
    }
    $ca_list .= '<option value="'.$row['ca_id'].'">'.$nbsp.$row['ca_name'].'</option>'.PHP_EOL;
}

$where = " and ";
$sql_search = "";

// 계층별 상품 필터링 추가 (새로운 DMK 필드 구조 사용)
if ($filter_dt_id) {
    $sql_search .= " $where (a.dmk_dt_id = '".sql_escape_string($filter_dt_id)."') ";
    $where = " and ";
} else {
    if ( $dmk_auth['mb_level'] == 8 OR $dmk_auth['mb_level'] == 6 OR $dmk_auth['mb_level'] == 4 ) {
        $sql_search .= " $where (a.dmk_dt_id = '".$dmk_auth['dt_id']."') ";
        $where = " and ";
    }
}
if ($filter_ag_id) {
    $sql_search .= " $where (a.dmk_ag_id = '".sql_escape_string($filter_ag_id)."') ";
    $where = " and ";
} else {
    if ( $dmk_auth['mb_level'] == 6 OR $dmk_auth['mb_level'] == 4 ) {
        $sql_search .= " $where (a.dmk_ag_id = '".$dmk_auth['ag_id']."') ";
        $where = " and ";
    }
}
if ($filter_br_id) {
    $sql_search .= " $where (a.dmk_br_id = '".sql_escape_string($filter_br_id)."') ";
    $where = " and ";
} else {
    if ($dmk_auth['mb_level'] == 4 ) {
        $sql_search .= " $where (a.dmk_br_id = '".$dmk_auth['br_id']."') ";
        $where = " and ";
    }
}

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if ($save_stx != $stx)
        $page = 1;
}

if ($sca != "") {
    $sql_search .= " $where (a.ca_id like '$sca%' or a.ca_id2 like '$sca%' or a.ca_id3 like '$sca%') ";
}

if ($sfl == "")  $sfl = "it_name";

$sql_common = " from {$g5['g5_shop_item_table']} a ,\n                     {$g5['g5_shop_category_table']} b\n               where a.ca_id = b.ca_id";
$sql_common .= dmk_get_item_where_condition();
$sql_common .= $sql_search;
$sql_common .= $tab_where;

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row ? (int)$row['cnt'] : 0;
// 디버깅용: SQL 쿼리 출력
if (isset($_GET['debug'])) {
    echo "<div style='background:#f0f0f0;padding:10px;margin:10px;'>";
    echo "<strong>현재 탭:</strong> $tab<br>";
    echo "<strong>탭 WHERE 조건:</strong> " . htmlspecialchars($tab_where) . "<br>";
    echo "<strong>전체 SQL:</strong> " . htmlspecialchars($sql) . "<br>";
    echo "</div>";
}
$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

if (!$sst) {
    $sst  = "it_id";
    $sod = "desc";
}
$sql_order = "order by $sst $sod";


$sql  = " select *
           $sql_common
           $sql_order
           limit $from_record, $rows ";

$result = sql_query($sql);

// URL 쿼리 스트링 생성 (계층 필터 포함) <i class="fa fa-link dmk-new-icon" title="NEW"></i>
$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page.'&amp;save_stx='.$stx;
// 탭용 별도 쿼리 스트링 (tab 파라미터 제외)
$qstr_for_tabs = $qstr;
if ($filter_dt_id) {
    $qstr .= '&amp;sdt_id='.$filter_dt_id;
    $qstr_for_tabs .= '&amp;sdt_id='.$filter_dt_id;
}
if ($filter_ag_id) {
    $qstr .= '&amp;sag_id='.$filter_ag_id;
    $qstr_for_tabs .= '&amp;sag_id='.$filter_ag_id;
}
if ($filter_br_id) {
    $qstr .= '&amp;sbr_id='.$filter_br_id;
    $qstr_for_tabs .= '&amp;sbr_id='.$filter_br_id;
}

// 현재 탭 정보를 포함한 전체 쿼리 스트링
$qstr .= '&amp;tab='.$tab;

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

// 계층별 상품 카운트 조회
$branch_count = 0;
$agency_count = 0; 
$distributor_count = 0;

$count_sql = "SELECT 
    COUNT(CASE WHEN dmk_br_id != '' THEN 1 END) as branch_count,
    COUNT(CASE WHEN dmk_ag_id != '' AND dmk_br_id = '' THEN 1 END) as agency_count,
    COUNT(CASE WHEN dmk_dt_id != '' AND dmk_ag_id = '' AND dmk_br_id = '' THEN 1 END) as distributor_count
    FROM {$g5['g5_shop_item_table']} a, {$g5['g5_shop_category_table']} b 
    WHERE a.ca_id = b.ca_id ";
$count_sql .= dmk_get_item_where_condition();
$count_sql .= $sql_search;

$count_result = sql_fetch($count_sql);
if ($count_result) {
    $branch_count = $count_result['branch_count'];
    $agency_count = $count_result['agency_count'];
    $distributor_count = $count_result['distributor_count'];
}
?>

<!-- 계층별 탭 메뉴 (봇 스케줄링 관리 페이지와 동일한 디자인) -->
<style>
.schedule-tabs {
    display: flex;
    margin-bottom: 10px;
    border-bottom: 2px solid #ddd;
}
.schedule-tab {
    padding: 10px 20px;
    margin-bottom: -2px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-bottom: 2px solid #ddd;
    text-decoration: none;
    color: #333;
    font-weight: bold;
    margin-right: 5px;
    cursor: pointer;
}
.schedule-tab:hover {
    background: #e5e5e5;
}
.schedule-tab.active {
    background: #fff;
    border-bottom: 2px solid #fff;
    color: #000;
}
.schedule-tab .count {
    color: #666;
    font-weight: normal;
}
.schedule-tab.active .count {
    color: #000;
    font-weight: bold;
}
</style>

<div class="schedule-tabs">
    <a href="?tab=branch&<?php echo $qstr_for_tabs; ?>" 
       class="schedule-tab <?php echo $tab == 'branch' ? 'active' : ''; ?>">
        지점 <span class="count">(<?php echo number_format($branch_count); ?>)</span>
    </a>
    <a href="?tab=agency&<?php echo $qstr_for_tabs; ?>" 
       class="schedule-tab <?php echo $tab == 'agency' ? 'active' : ''; ?>">
        대리점 <span class="count">(<?php echo number_format($agency_count); ?>)</span>
    </a>
    <a href="?tab=distributor&<?php echo $qstr_for_tabs; ?>" 
       class="schedule-tab <?php echo $tab == 'distributor' ? 'active' : ''; ?>">
        총판 <span class="count">(<?php echo number_format($distributor_count); ?>)</span>
    </a>
</div>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01">
        <span class="ov_txt">
            <?php 
            switch($tab) {
                case 'branch': echo '지점 상품'; break;
                case 'agency': echo '대리점 상품'; break; 
                case 'distributor': echo '총판 상품'; break;
            }
            ?>
        </span>
        <span class="ov_num"> <?php echo $total_count; ?>건</span>
    </span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
<input type="hidden" name="tab" value="<?php echo $tab; ?>">

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

<label for="sca" class="sound_only">분류선택</label>
<select name="sca" id="sca">
    <option value="">전체분류</option>
    <?php
    $sql1 = " select ca_id, ca_name from {$g5['g5_shop_category_table']} order by ca_order, ca_id ";
    $result1 = sql_query($sql1);
    for ($i=0; $row1=sql_fetch_array($result1); $i++) {
        $len = strlen($row1['ca_id']) / 2 - 1;
        $nbsp = '';
        for ($i=0; $i<$len; $i++) $nbsp .= '&nbsp;&nbsp;&nbsp;';
        echo '<option value="'.$row1['ca_id'].'" '.get_selected($sca, $row1['ca_id']).'>'.$nbsp.$row1['ca_name'].'</option>'.PHP_EOL;
    }
    ?>
</select>

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="it_id" <?php echo get_selected($sfl, 'it_id'); ?>>상품코드</option>
    <option value="it_maker" <?php echo get_selected($sfl, 'it_maker'); ?>>제조사</option>
    <option value="it_origin" <?php echo get_selected($sfl, 'it_origin'); ?>>원산지</option>
    <option value="it_sell_email" <?php echo get_selected($sfl, 'it_sell_email'); ?>>판매자 e-mail</option>
</select>

<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" class="frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>

<form name="fitemlistupdate" method="post" action="./itemlistupdate.php" onsubmit="return fitemlist_submit(this);" autocomplete="off" id="fitemlistupdate">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>">
<input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>">
<input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>">
<input type="hidden" name="tab" value="<?php echo $tab; ?>">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" rowspan="2">
            <label for="chkall" class="sound_only">상품 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col" rowspan="2"><?php echo subject_sort_link('it_id', 'sca='.$sca.'&tab='.$tab); ?>상품코드</a></th>
        <th scope="col" rowspan="2" id="th_img">이미지</th>
        <th scope="col" rowspan="2" id="th_pc_title"><?php echo subject_sort_link('it_name', 'sca='.$sca.'&tab='.$tab); ?>상품명</a></th>
        <th scope="col" id="th_amt"><?php echo subject_sort_link('it_price', 'sca='.$sca.'&tab='.$tab); ?>판매가격</a></th>
        <th scope="col" id="th_camt"><?php echo subject_sort_link('it_cust_price', 'sca='.$sca.'&tab='.$tab); ?>시중가격</a></th>
        <th scope="col" id="th_skin">PC스킨</th>
        <th scope="col" rowspan="2"><?php echo subject_sort_link('it_order', 'sca='.$sca.'&tab='.$tab); ?>순서</a></th>
        <th scope="col" rowspan="2"><?php echo subject_sort_link('it_use', 'sca='.$sca.'&tab='.$tab, 1); ?>판매</a></th>
        <th scope="col" rowspan="2"><?php echo subject_sort_link('it_soldout', 'sca='.$sca.'&tab='.$tab, 1); ?>품절</a></th>
        <th scope="col" rowspan="2"><?php echo subject_sort_link('it_hit', 'sca='.$sca.'&tab='.$tab, 1); ?>조회</a></th>
        <th scope="col" rowspan="2">소속</th>
        <th scope="col" rowspan="2">관리</th>
    </tr>
    <tr>
        <th scope="col" id="th_pt"><?php echo subject_sort_link('it_point', 'sca='.$sca.'&tab='.$tab); ?>포인트</a></th>
        <th scope="col" id="th_qty"><?php echo subject_sort_link('it_stock_qty', 'sca='.$sca.'&tab='.$tab); ?>재고</a></th>
        <th scope="col" id="th_mskin">모바일스킨</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        $href = shop_item_url($row['it_id']);
        $bg = 'bg'.($i%2);

        $it_point = $row['it_point'];
        if($row['it_point_type'])
            $it_point .= '%';
    ?>
    <tr class="<?php echo $bg; ?>">
        <td rowspan="2" class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo get_text($row['it_name']); ?></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
        </td>
        <td rowspan="2" class="td_num">
            <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
            <?php echo $row['it_id']; ?>
        </td>
        <td rowspan="2" class="td_img"><a href="<?php echo $href; ?>"><?php echo get_it_image($row['it_id'], 50, 50); ?></a></td>
        <td headers="th_pc_title" rowspan="2" class="td_input">
            <label for="name_<?php echo $i; ?>" class="sound_only">상품명</label>
            <input type="text" name="it_name[<?php echo $i; ?>]" value="<?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?>" id="name_<?php echo $i; ?>" required class="tbl_input required" size="30">
        </td>
        <td headers="th_amt" class="td_numbig td_input">
            <label for="price_<?php echo $i; ?>" class="sound_only">판매가격</label>
            <input type="text" name="it_price[<?php echo $i; ?>]" value="<?php echo $row['it_price']; ?>" id="price_<?php echo $i; ?>" class="tbl_input sit_amt" size="7">
        </td>
        <td headers="th_camt" class="td_numbig td_input">
            <label for="cust_price_<?php echo $i; ?>" class="sound_only">시중가격</label>
            <input type="text" name="it_cust_price[<?php echo $i; ?>]" value="<?php echo $row['it_cust_price']; ?>" id="cust_price_<?php echo $i; ?>" class="tbl_input sit_camt" size="7">
        </td>
        <td headers="th_skin" class="td_numbig td_input">
            <label for="it_skin_<?php echo $i; ?>" class="sound_only">PC 스킨</label>
            <?php echo get_skin_select('shop', 'it_skin_'.$i, 'it_skin['.$i.']', $row['it_skin']); ?>
        </td>
        <td rowspan="2" class="td_num">
            <label for="order_<?php echo $i; ?>" class="sound_only">순서</label>
            <input type="text" name="it_order[<?php echo $i; ?>]" value="<?php echo $row['it_order']; ?>" id="order_<?php echo $i; ?>" class="tbl_input" size="3">
        </td>
        <td rowspan="2">
            <label for="use_<?php echo $i; ?>" class="sound_only">판매여부</label>
            <input type="checkbox" name="it_use[<?php echo $i; ?>]" <?php echo ($row['it_use'] ? 'checked' : ''); ?> value="1" id="use_<?php echo $i; ?>">
        </td>
        <td rowspan="2">
            <label for="soldout_<?php echo $i; ?>" class="sound_only">품절</label>
            <input type="checkbox" name="it_soldout[<?php echo $i; ?>]" <?php echo ($row['it_soldout'] ? 'checked' : ''); ?> value="1" id="soldout_<?php echo $i; ?>">
        </td>
        <td rowspan="2" class="td_num"><?php echo $row['it_hit']; ?></td>
        <td rowspan="2" class="td_hierarchy">
            <?php
            // 계층 정보 표시 (새로운 DMK 필드 구조 사용)
            $hierarchy_info = '';
            $hierarchy_name = '';
            $hierarchy_id = '';
            
            if ($row['dmk_br_id']) {
                // 지점 상품
                $hierarchy_info = '지점';
                $sql = "SELECT mb_name, mb_nick FROM dmk_branch JOIN g5_member ON br_id = mb_id WHERE br_id = '" . sql_escape_string($row['dmk_br_id']) . "'";
                $owner_row = sql_fetch($sql);
                $hierarchy_name = $owner_row ? $owner_row['mb_name'] : '';
                $hierarchy_id = $row['dmk_br_id'];
            } elseif ($row['dmk_ag_id']) {
                // 대리점 상품
                $hierarchy_info = '대리점';
                $sql = "SELECT mb_name, mb_nick FROM dmk_agency JOIN g5_member ON ag_id = mb_id WHERE ag_id = '" . sql_escape_string($row['dmk_ag_id']) . "'";
                $owner_row = sql_fetch($sql);
                $hierarchy_name = $owner_row ? $owner_row['mb_name'] : '';
                $hierarchy_id = $row['dmk_ag_id'];
            } elseif ($row['dmk_dt_id']) {
                // 총판 상품
                $hierarchy_info = '총판';
                $sql = "SELECT mb_name, mb_nick FROM dmk_distributor JOIN g5_member ON dt_id = mb_id WHERE dt_id = '" . sql_escape_string($row['dmk_dt_id']) . "'";
                $owner_row = sql_fetch($sql);
                $hierarchy_name = $owner_row ? $owner_row['mb_name'] : '';
                $hierarchy_id = $row['dmk_dt_id'];
            } else {
                // 본사 상품
                $hierarchy_info = '본사';
                $hierarchy_name = '최고관리자';
                $hierarchy_id = 'super';
            }
            ?>
            <div style="text-align:center; font-size:11px;">
                <strong style="color:#007bff;"><?php echo $hierarchy_info; ?></strong><br>
                <span style="color:#666;"><?php echo $hierarchy_name; ?></span><br>
                <span style="color:#999; font-size:10px;">(<?php echo $hierarchy_id; ?>)</span>
            </div>
        </td>
        <td rowspan="2" class="td_mng td_mng_s">
            <a href="./itemform.php?w=u&amp;it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>&amp;<?php echo $qstr; ?>" class="btn btn_03"><span class="sound_only"><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?> </span>수정</a>
            <!--<a href="./itemcopy.php?it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>" class="itemcopy btn btn_02" target="_blank"><span class="sound_only"><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?> </span>복사</a>-->
            <a href="<?php echo $href; ?>" class="btn btn_02"><span class="sound_only"><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?> </span>보기</a>
        </td>
    </tr>
    <tr class="<?php echo $bg; ?>">
        <td headers="th_pt" class="td_numbig td_input"><?php echo $it_point; ?></td>
        <td headers="th_qty" class="td_numbig td_input">
            <label for="stock_qty_<?php echo $i; ?>" class="sound_only">재고</label>
            <input type="text" name="it_stock_qty[<?php echo $i; ?>]" value="<?php echo $row['it_stock_qty']; ?>" id="stock_qty_<?php echo $i; ?>" class="tbl_input sit_qty" size="7">
        </td>
        <td headers="th_mskin" class="td_numbig td_input">
            <label for="it_mobile_skin_<?php echo $i; ?>" class="sound_only">모바일 스킨</label>
            <?php echo get_mobile_skin_select('shop', 'it_mobile_skin_'.$i, 'it_mobile_skin['.$i.']', $row['it_mobile_skin']); ?>
        </td>
    </tr>
    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="13" class="empty_table">자료가 한건도 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">

    <a href="./itemform.php" class="btn btn_01">상품등록</a>
    <!--<a href="./itemexcel.php" onclick="return excelform(this.href);" target="_blank" class="btn btn_02">상품일괄등록</a>-->
    <input type="submit" name="act_button" value="선택수정" onclick="document.pressed=this.value" class="btn btn_02">
    <?php if ($is_admin == 'super') { ?>
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
    <?php } ?>
</div>
<!-- <div class="btn_confirm01 btn_confirm">
    <input type="submit" value="일괄수정" class="btn_submit" accesskey="s">
</div> -->
</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
function fitemlist_submit(f)
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

$(function() {
    $(".itemcopy").click(function() {
        var href = $(this).attr("href");
        window.open(href, "copywin", "left=100, top=100, width=300, height=200, scrollbars=0");
        return false;
    });
});

function excelform(url)
{
    var opt = "width=600,height=450,left=10,top=10";
    window.open(url, "win_excel", opt);
    return false;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');