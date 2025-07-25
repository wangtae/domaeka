<?php
$sub_menu = '400300';
include_once('./_common.php');
include_once(G5_EDITOR_LIB);
include_once(G5_LIB_PATH.'/iteminfo.lib.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

// 도매까 권한 정보 초기화
$dmk_auth = dmk_get_admin_auth();

// 도매까 관리자 유형 상수 정의
if (!defined('DMK_MB_TYPE_SUPER_ADMIN')) define('DMK_MB_TYPE_SUPER_ADMIN', 0);
if (!defined('DMK_MB_TYPE_DISTRIBUTOR')) define('DMK_MB_TYPE_DISTRIBUTOR', 1);
if (!defined('DMK_MB_TYPE_AGENCY')) define('DMK_MB_TYPE_AGENCY', 2);
if (!defined('DMK_MB_TYPE_BRANCH')) define('DMK_MB_TYPE_BRANCH', 3);

// 도매까 소유자 유형 상수 정의
if (!defined('DMK_OWNER_TYPE_SUPER_ADMIN')) define('DMK_OWNER_TYPE_SUPER_ADMIN', 'super_admin');
if (!defined('DMK_OWNER_TYPE_DISTRIBUTOR')) define('DMK_OWNER_TYPE_DISTRIBUTOR', 'distributor');
if (!defined('DMK_OWNER_TYPE_AGENCY')) define('DMK_OWNER_TYPE_AGENCY', 'agency');
if (!defined('DMK_OWNER_TYPE_BRANCH')) define('DMK_OWNER_TYPE_BRANCH', 'branch');

// JavaScript에서 사용할 도매까 관련 상수 정의
$dmk_js_consts = [
    'DMK_OWNER_TYPE_SUPER_ADMIN' => DMK_OWNER_TYPE_SUPER_ADMIN,
    'DMK_OWNER_TYPE_DISTRIBUTOR' => DMK_OWNER_TYPE_DISTRIBUTOR,
    'DMK_OWNER_TYPE_AGENCY' => DMK_OWNER_TYPE_AGENCY,
    'DMK_OWNER_TYPE_BRANCH' => DMK_OWNER_TYPE_BRANCH,
    'DMK_MB_TYPE_SUPER_ADMIN' => DMK_MB_TYPE_SUPER_ADMIN,
    'DMK_MB_TYPE_DISTRIBUTOR' => DMK_MB_TYPE_DISTRIBUTOR,
    'DMK_MB_TYPE_AGENCY' => DMK_MB_TYPE_AGENCY,
    'DMK_MB_TYPE_BRANCH' => DMK_MB_TYPE_BRANCH,
];

// 계층별 필터링 파라미터 처리 (URL에서 전달받은 계층 정보)
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 새로운 필드 구조를 위한 기본값 설정
$selected_dt_id = '';
$selected_ag_id = '';
$selected_br_id = '';

// 현 관리자의 소유 계층 정보
$current_mb_type = $dmk_auth['mb_type'] ?? null;
$current_dt_id = $dmk_auth['dt_id'] ?? null;
$current_ag_id = $dmk_auth['ag_id'] ?? null;
$current_br_id = $dmk_auth['br_id'] ?? null;

// 슈퍼관리자가 아닌 경우 상품 소유 계층 필터링
$owner_types = [
    DMK_OWNER_TYPE_SUPER_ADMIN => '최고 관리자',
    DMK_OWNER_TYPE_DISTRIBUTOR => '총판',
    DMK_OWNER_TYPE_AGENCY      => '대리점',
    DMK_OWNER_TYPE_BRANCH      => '지점',
];

$filtered_owner_types = [];
if ($current_mb_type === DMK_MB_TYPE_SUPER_ADMIN) {
    $filtered_owner_types = $owner_types;
} elseif ($current_mb_type === DMK_MB_TYPE_DISTRIBUTOR) {
    $filtered_owner_types[DMK_OWNER_TYPE_DISTRIBUTOR] = $owner_types[DMK_OWNER_TYPE_DISTRIBUTOR];
    $filtered_owner_types[DMK_OWNER_TYPE_AGENCY] = $owner_types[DMK_OWNER_TYPE_AGENCY];
    $filtered_owner_types[DMK_OWNER_TYPE_BRANCH] = $owner_types[DMK_OWNER_TYPE_BRANCH];
} elseif ($current_mb_type === DMK_MB_TYPE_AGENCY) {
    $filtered_owner_types[DMK_OWNER_TYPE_AGENCY] = $owner_types[DMK_OWNER_TYPE_AGENCY];
    $filtered_owner_types[DMK_OWNER_TYPE_BRANCH] = $owner_types[DMK_OWNER_TYPE_BRANCH];
} elseif ($current_mb_type === DMK_MB_TYPE_BRANCH) {
    $filtered_owner_types[DMK_OWNER_TYPE_BRANCH] = $owner_types[DMK_OWNER_TYPE_BRANCH];
}

// 소유 계층 드롭다운 표시 여부 결정
$display_owner_type_select = ($current_mb_type === DMK_MB_TYPE_SUPER_ADMIN);
$display_owner_id_select = true;

// URL 파라미터로 전달된 계층 정보 처리
if ($sdt_id || $sag_id || $sbr_id) {
    if ($sbr_id) {
        // 지점까지 선택된 경우
        $br_info = sql_fetch("SELECT dmk_dt_id, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sbr_id)."'");
        if ($br_info) {
            $selected_dt_id = $br_info['dmk_dt_id'];
            $selected_ag_id = $br_info['dmk_ag_id'];
            $selected_br_id = $br_info['dmk_br_id'];
        }
    } elseif ($sag_id) {
        // 대리점까지 선택된 경우
        $ag_info = sql_fetch("SELECT dmk_dt_id, dmk_ag_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sag_id)."'");
        if ($ag_info) {
            $selected_dt_id = $ag_info['dmk_dt_id'];
            $selected_ag_id = $ag_info['dmk_ag_id'];
        }
    } elseif ($sdt_id) {
        // 총판만 선택된 경우
        $dt_info = sql_fetch("SELECT dmk_dt_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sdt_id)."'");
        if ($dt_info) {
            $selected_dt_id = $dt_info['dmk_dt_id'];
        }
    }
}

// DMK 권한 확인 - DMK 설정에 따른 메뉴 접근 권한 체크
include_once(G5_PATH.'/dmk/dmk_global_settings.php');
$user_type = dmk_get_current_user_type();

// DMK main 관리자는 DMK 설정에 정의된 메뉴에 최고관리자처럼 접근 가능
if ($dmk_auth && $dmk_auth['admin_type'] === 'main' && dmk_is_menu_allowed('400300', $user_type)) {
    // DMK main 관리자는 auth_check_menu 우회
} else {
    // 일반 관리자는 기존 권한 체크 수행
    auth_check_menu($auth, $sub_menu, "w");
}

// 추가 DMK 권한 체크
if (!dmk_is_menu_allowed('400300', $user_type)) {
    alert('상품관리에 접근 권한이 없습니다.', G5_ADMIN_URL);
}

$html_title = "상품 ";

$it = array(
'it_id'=>'',
'it_skin'=>'',
'it_mobile_skin'=>'',
'it_name'=>'',
'it_basic'=>'',
'it_order'=>0,
'it_type1'=>0,
'it_type2'=>0,
'it_type3'=>0,
'it_type4'=>0,
'it_type5'=>0,
'it_brand'=>'',
'it_model'=>'',
'it_tel_inq'=>0,
'it_use'=>0,
'it_nocoupon'=>0,
'ec_mall_pid'=>'',
'it_mobile_explan'=>'',
'it_sell_email'=>'',
'it_shop_memo'=>'',
'it_info_gubun'=>'',
'it_explan'=>'',
'it_point_type'=>0,
'it_cust_price'=>0,
'it_option_subject'=>'',
'it_price'=>0,
'it_point'=>0,
'it_supply_point'=>0,
'it_soldout'=>0,
'it_stock_sms'=>0,
'it_stock_qty'=>0,
'it_noti_qty'=>0,
'it_buy_min_qty'=>0,
'it_buy_max_qty'=>0,
'it_notax'=>0,
'it_supply_subject'=>'',
'it_sc_type'=>0,
'it_sc_method'=>0,
'it_sc_price'=>0,
'it_sc_minimum'=>0,
'it_sc_qty'=>0,
'it_img1'=>'',
'it_img2'=>'',
'it_img3'=>'',
'it_img4'=>'',
'it_img5'=>'',
'it_img6'=>'',
'it_img7'=>'',
'it_img8'=>'',
'it_img9'=>'',
'it_img10'=>'',
'it_head_html'=>'',
'it_tail_html'=>'',
'it_mobile_head_html'=>'',
'it_mobile_tail_html'=>'',
// 도매까 상품 소유 정보 추가 (새로운 필드 구조)
'dmk_dt_id' => '',
'dmk_ag_id' => '',
'dmk_br_id' => '',
// 도매까 상품 유형 추가
'dmk_it_type' => '0',
// 도매까 상품 유효 기간 추가
'dmk_it_valid_start_date' => '',
'dmk_it_valid_end_date' => '',
);

for($i=0;$i<=10;$i++){
    $it['it_'.$i.'_subj'] = '';
    $it['it_'.$i] = '';
}

if ($w == "")
{
    $html_title .= "입력";

    // 도매까 상품 소유 정보 기본 설정 (새로운 필드 구조 사용)
    $owner_info = dmk_get_item_owner_info();
    
    // 새로운 계층 필드 기본값 설정
    $it['dmk_dt_id'] = $owner_info['dmk_dt_id'] ?? '';
    $it['dmk_ag_id'] = $owner_info['dmk_ag_id'] ?? '';
    $it['dmk_br_id'] = $owner_info['dmk_br_id'] ?? '';

    // 옵션은 쿠키에 저장된 값을 보여줌. 다음 입력을 위한것임
    //$it[ca_id] = _COOKIE[ck_ca_id];
    $it['ca_id'] = get_cookie("ck_ca_id");
    $it['ca_id2'] = get_cookie("ck_ca_id2");
    $it['ca_id3'] = get_cookie("ck_ca_id3");
    if (!$it['ca_id'])
    {
        $sql = " select ca_id from {$g5['g5_shop_category_table']} order by ca_order, ca_id limit 1 ";
        $row = sql_fetch($sql);
        if (! (isset($row['ca_id']) && $row['ca_id']))
            alert("등록된 분류가 없습니다. 우선 분류를 등록하여 주십시오.", './categorylist.php');
        $it['ca_id'] = $row['ca_id'];
    }
    //$it[it_maker]  = stripslashes($_COOKIE[ck_maker]);
    //$it[it_origin] = stripslashes($_COOKIE[ck_origin]);
    $it['it_maker']  = stripslashes(get_cookie("ck_maker"));
    $it['it_origin'] = stripslashes(get_cookie("ck_origin"));
}
else if ($w == "u")
{
    $html_title .= "수정";

    $it = get_shop_item($it_id);

    if(!$it)
        alert('상품정보가 존재하지 않습니다.');
    
    // 새로운 계층 필드가 없는 경우 기본값 설정
    if (!isset($it['dmk_dt_id'])) $it['dmk_dt_id'] = '';
    if (!isset($it['dmk_ag_id'])) $it['dmk_ag_id'] = '';
    if (!isset($it['dmk_br_id'])) $it['dmk_br_id'] = '';
    
    // 도매까 권한 확인
    if (!dmk_can_modify_item($it_id)) {
        alert("수정 할 권한이 없는 상품입니다.");
    }
    
    if (function_exists('check_case_exist_title')) check_case_exist_title($it, G5_SHOP_DIR, false);

    if (! (isset($ca_id) && $ca_id))
        $ca_id = $it['ca_id'];

    $sql = " select * from {$g5['g5_shop_category_table']} where ca_id = '$ca_id' ";
    $ca = sql_fetch($sql);
}
else
{
    alert();
}

$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page;

$g5['title'] = $html_title;
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 체인 선택박스 에셋 포함
echo dmk_include_chain_select_assets();


// 분류리스트
$category_select = '';
$script = '';
$sql = " select * from {$g5['g5_shop_category_table']} ";
$sql .= " order by ca_order, ca_id ";

$result = sql_query($sql);
for ($i=0; $row=sql_fetch_array($result); $i++)
{
    $len = strlen($row['ca_id']) / 2 - 1;

    $nbsp = "";
    for ($i=0; $i<$len; $i++)
        $nbsp .= "&nbsp;&nbsp;&nbsp;";

    $category_select .= "<option value=\"{$row['ca_id']}\">$nbsp{$row['ca_name']}</option>\n";

    $script .= "ca_use['{$row['ca_id']}'] = {$row['ca_use']};\n";
    $script .= "ca_stock_qty['{$row['ca_id']}'] = {$row['ca_stock_qty']};\n";
    //$script .= "ca_explan_html['$row[ca_id]'] = $row[ca_explan_html];\n";
    $script .= "ca_sell_email['{$row['ca_id']}'] = '{$row['ca_sell_email']}';\n";
}



// 재입고알림 설정 필드 추가
if(!sql_query(" select it_stock_sms from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_stock_sms` tinyint(4) NOT NULL DEFAULT '0' AFTER `it_stock_qty` ", true);
}

// 추가옵션 포인트 설정 필드 추가
if(!sql_query(" select it_supply_point from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_supply_point` int(11) NOT NULL DEFAULT '0' AFTER `it_point_type` ", true);
}

// 상품메모 필드 추가
if(!sql_query(" select it_shop_memo from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_shop_memo` text NOT NULL AFTER `it_use_avg` ", true);
}

// 지식쇼핑 PID 필드추가
// 상품메모 필드 추가
if(!sql_query(" select ec_mall_pid from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `ec_mall_pid` varchar(255) NOT NULL AFTER `it_shop_memo` ", true);
}

$pg_anchor ='<ul class="anchor">
<li><a href="#anc_sitfrm_cate">상품분류</a></li>
<li><a href="#anc_sitfrm_skin">스킨설정</a></li>
<li><a href="#anc_sitfrm_ini">기본정보</a></li>
<li><a href="#anc_sitfrm_compact">요약정보</a></li>
<li><a href="#anc_sitfrm_cost">가격 및 재고</a></li>
<li><a href="#anc_sitfrm_sendcost">배송비</a></li>
<li><a href="#anc_sitfrm_img">상품이미지</a></li>
<li><a href="#anc_sitfrm_relation">관련상품</a></li>
<li><a href="#anc_sitfrm_event">관련이벤트</a></li>
<li><a href="#anc_sitfrm_optional">상세설명설정</a></li>
<li><a href="#anc_sitfrm_extra">여분필드</a></li>
</ul>
';


// 쿠폰적용안함 설정 필드 추가
if(!sql_query(" select it_nocoupon from {$g5['g5_shop_item_table']} limit 1", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_nocoupon` tinyint(4) NOT NULL DEFAULT '0' AFTER `it_use` ", true);
}

// 스킨필드 추가
if(!sql_query(" select it_skin from {$g5['g5_shop_item_table']} limit 1", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_skin` varchar(255) NOT NULL DEFAULT '' AFTER `ca_id3`,
                    ADD `it_mobile_skin` varchar(255) NOT NULL DEFAULT '' AFTER `it_skin` ", true);
}
?>

<form name="fitemform" action="./itemformupdate.php" method="post" enctype="MULTIPART/FORM-DATA" autocomplete="off" onsubmit="return fitemformcheck(this)">

<input type="hidden" name="w" value="<?php echo $w; ?>">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod"  value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx"  value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">


<section id="anc_sitfrm_owner">
    <h2 class="h2_frm">상품 소유 계층</h2>
    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>상품 소유 계층 설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <?php
        // 현재 관리자 정보 가져오기  
        $dmk_auth = dmk_get_admin_auth();
        $is_super_admin = $dmk_auth['is_super'];
        $current_mb_type = $dmk_auth['mb_type'];
        $current_ag_id = $dmk_auth['ag_id'];
        $current_br_id = $dmk_auth['br_id'];

        $owner_types = [
            DMK_OWNER_TYPE_DISTRIBUTOR => '총판',
            DMK_OWNER_TYPE_AGENCY => '대리점', 
            DMK_OWNER_TYPE_BRANCH => '지점',
        ];

        // 새로운 필드 구조를 기반으로 소유자 정보 계산
        $owner_info = dmk_get_owner_info_from_fields($it);
        $selected_owner_type = $owner_info['owner_type'] ?: ($is_super_admin ? DMK_OWNER_TYPE_DISTRIBUTOR : '');
        $selected_owner_id = $owner_info['owner_id'] ?: '';

        // 기본값 설정 (새로운 상품 추가 시)
        if ($w == "") {
            $owner_info = dmk_get_item_owner_info();
            $selected_owner_type = $owner_info['owner_type'];
            $selected_owner_id = $owner_info['owner_id'];
        }

        // 선택 가능한 계층 필터링
        $display_owner_type_select = false;
        $display_owner_id_select = false;
        $filtered_owner_types = [];

        if ($is_super_admin) {
            $display_owner_type_select = true;
            $display_owner_id_select = true;
            $filtered_owner_types = $owner_types; // 최고관리자는 모든 계층 선택 가능
        } else if ($current_mb_type == DMK_MB_TYPE_DISTRIBUTOR) {
            $display_owner_type_select = true;
            $display_owner_id_select = true;
            $filtered_owner_types = [
                DMK_OWNER_TYPE_DISTRIBUTOR => '총판',
                DMK_OWNER_TYPE_AGENCY => '대리점',
                DMK_OWNER_TYPE_BRANCH => '지점',
            ];
            // 총판인 경우 기본적으로 자신의 총판으로 설정
            if ($w == "" && empty($selected_owner_type)) {
                $selected_owner_type = DMK_OWNER_TYPE_DISTRIBUTOR;
                $selected_owner_id = $dmk_auth['mb_id']; // 총판 ID
            }
        } else if ($current_mb_type == DMK_MB_TYPE_AGENCY) {
            $display_owner_type_select = true;
            $display_owner_id_select = true;
            $filtered_owner_types = [
                DMK_OWNER_TYPE_AGENCY => '대리점',
                DMK_OWNER_TYPE_BRANCH => '지점',
            ];
            // 대리점인 경우 기본적으로 자신의 대리점으로 설정
            if ($w == "" && empty($selected_owner_type)) {
                $selected_owner_type = DMK_OWNER_TYPE_AGENCY;
                $selected_owner_id = $current_ag_id; // 대리점 ID
            }
        } else if ($current_mb_type == DMK_MB_TYPE_BRANCH) {
            // 지점은 자신의 지점만 소유 가능
            $selected_owner_type = DMK_OWNER_TYPE_BRANCH;
            $selected_owner_id = $current_br_id;
        }
        ?>

        <?php if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] <= DMK_MB_TYPE_AGENCY) { ?>
        <tr>
            <th scope="row">상품 소유 계층</th>
            <td>
                <?php if ($dmk_auth['is_super'] && $w == '') { ?>
                    <!-- 본사 관리자 신규 등록 시 체인 선택박스 -->
                    <?php 
                    echo dmk_render_chain_select([
                        'page_type' => DMK_CHAIN_SELECT_FULL,
                        'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                        'current_values' => [
                            'sdt_id' => $sdt_id ?: $selected_dt_id,
                            'sag_id' => $sag_id ?: $selected_ag_id, 
                            'sbr_id' => $sbr_id ?: $selected_br_id
                        ],
                        'field_names' => [
                            'distributor' => 'dmk_dt_id',
                            'agency' => 'dmk_ag_id',
                            'branch' => 'dmk_br_id'
                        ],
                        'labels' => [
                            'distributor' => '총판',
                            'agency' => '대리점',
                            'branch' => '지점'
                        ],
                        'placeholders' => [
                            'distributor' => '총판을 선택하세요',
                            'agency' => '대리점을 선택하세요',
                            'branch' => '지점을 선택하세요'
                        ],
                        'form_id' => 'fitem',
                        'auto_submit' => false,
                        'show_labels' => false,
                        'container_class' => 'dmk-owner-select'
                    ]);
                    ?>
                    <div class="hierarchy_desc" style="margin-top: 10px; font-size: 11px; color: #666;">
                        • 총판까지만 선택 시 총판 상품이 됩니다.<br>
                        • 대리점까지 선택 시 대리점 상품이 됩니다.<br>
                        • 지점까지 선택 시 지점 상품이 됩니다.
                    </div>
                <?php } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && $w == '') { ?>
                    <!-- 총판 관리자 신규 등록 시 - 총판 + 대리점 + 지점 선택 -->
                    <?php 
                    echo dmk_render_chain_select([
                        'page_type' => DMK_CHAIN_SELECT_FULL,
                        'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                        'current_values' => [
                            'sdt_id' => $current_dt_id,
                            'sag_id' => $sag_id ?: $selected_ag_id,
                            'sbr_id' => $sbr_id ?: $selected_br_id
                        ],
                        'field_names' => [
                            'distributor' => 'dmk_dt_id',
                            'agency' => 'dmk_ag_id',
                            'branch' => 'dmk_br_id'
                        ],
                        'labels' => [
                            'distributor' => '총판',
                            'agency' => '대리점',
                            'branch' => '지점'
                        ],
                        'placeholders' => [
                            'distributor' => '총판을 선택하세요',
                            'agency' => '대리점을 선택하세요',
                            'branch' => '지점을 선택하세요'
                        ],
                        'form_id' => 'fitem',
                        'auto_submit' => false,
                        'show_labels' => false,
                        'container_class' => 'dmk-owner-select'
                    ]);
                    ?>
                    <div class="hierarchy_desc" style="margin-top: 10px; font-size: 11px; color: #666;">
                        • 총판까지만 선택 시 총판 상품이 됩니다.<br>
                        • 대리점까지 선택 시 대리점 상품이 됩니다.<br>
                        • 지점까지 선택 시 지점 상품이 됩니다.
                    </div>
                <?php } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && $w == '') { ?>
                    <!-- 대리점 관리자 신규 등록 시 - 대리점 + 지점 선택 (총판 정보는 서버에서 처리) -->
                    <?php 
                    echo dmk_render_chain_select([
                        'page_type' => DMK_CHAIN_SELECT_FULL,
                        'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
                        'current_values' => [
                            'sag_id' => $current_ag_id,
                            'sbr_id' => $sbr_id ?: $selected_br_id
                        ],
                        'field_names' => [
                            'agency' => 'dmk_ag_id',
                            'branch' => 'dmk_br_id'
                        ],
                        'labels' => [
                            'agency' => '대리점',
                            'branch' => '지점'
                        ],
                        'placeholders' => [
                            'agency' => '대리점을 선택하세요',
                            'branch' => '지점을 선택하세요'
                        ],
                        'form_id' => 'fitem',
                        'auto_submit' => false,
                        'show_labels' => false,
                        'container_class' => 'dmk-owner-select'
                    ]);
                    ?>
                    <div class="hierarchy_desc" style="margin-top: 10px; font-size: 11px; color: #666;">
                        • 대리점을 선택하면 대리점 상품이 됩니다.<br>
                        • 지점을 선택하면 지점 상품이 됩니다.<br>
                        • 총판 정보는 자동으로 설정됩니다.
                    </div>
                <?php } else { ?>
                    <!-- 기존 상품 수정이거나 지점 관리자인 경우 -->
                    <div style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px;">
                        <strong>현재 상품 소유:</strong><br>
                        <?php
                        $hierarchy_info = [];
                        if (isset($it['dmk_dt_id']) && $it['dmk_dt_id']) {
                            $dt_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$it['dmk_dt_id']}'");
                            $hierarchy_info[] = "총판: " . ($dt_info['mb_nick'] ?? $it['dmk_dt_id']);
                        }
                        if (isset($it['dmk_ag_id']) && $it['dmk_ag_id']) {
                            $ag_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$it['dmk_ag_id']}'");
                            $hierarchy_info[] = "대리점: " . ($ag_info['mb_nick'] ?? $it['dmk_ag_id']);
                        }
                        if (isset($it['dmk_br_id']) && $it['dmk_br_id']) {
                            $br_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$it['dmk_br_id']}'");
                            $hierarchy_info[] = "지점: " . ($br_info['mb_nick'] ?? $it['dmk_br_id']);
                        }
                        
                        if (empty($hierarchy_info)) {
                            echo "소유 정보 없음";
                        } else {
                            echo implode(" > ", $hierarchy_info);
                        }
                        ?>
                    </div>
                    <!-- 수정 시에도 계층별로 적절한 선택 UI 제공 -->
                    <?php if ($dmk_auth['is_super']) { ?>
                        <!-- 본사 관리자는 모든 계층 선택 가능 -->
                        <?php 
                        echo dmk_render_chain_select([
                            'page_type' => DMK_CHAIN_SELECT_FULL,
                            'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
                            'current_values' => [
                                'dmk_dt_id' => isset($it['dmk_dt_id']) ? $it['dmk_dt_id'] : '',
                                'dmk_ag_id' => isset($it['dmk_ag_id']) ? $it['dmk_ag_id'] : '',
                                'dmk_br_id' => isset($it['dmk_br_id']) ? $it['dmk_br_id'] : ''
                            ],
                            'field_names' => [
                                'distributor' => 'dmk_dt_id',
                                'agency' => 'dmk_ag_id',
                                'branch' => 'dmk_br_id'
                            ],
                            'labels' => [
                                'distributor' => '총판',
                                'agency' => '대리점',
                                'branch' => '지점'
                            ],
                            'placeholders' => [
                                'distributor' => '총판을 선택하세요',
                                'agency' => '대리점을 선택하세요',
                                'branch' => '지점을 선택하세요'
                            ],
                            'form_id' => 'fitem',
                            'auto_submit' => false,
                            'show_labels' => false,
                            'container_class' => 'dmk-owner-select'
                        ]);
                        ?>
                    <?php } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) { ?>
                        <!-- 총판 관리자는 총판-대리점-지점 선택 가능 -->
                        <?php 
                        echo dmk_render_chain_select([
                            'page_type' => DMK_CHAIN_SELECT_FULL,
                            'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
                            'current_values' => [
                                'dmk_dt_id' => isset($it['dmk_dt_id']) ? $it['dmk_dt_id'] : $current_dt_id,
                                'dmk_ag_id' => isset($it['dmk_ag_id']) ? $it['dmk_ag_id'] : '',
                                'dmk_br_id' => isset($it['dmk_br_id']) ? $it['dmk_br_id'] : ''
                            ],
                            'field_names' => [
                                'distributor' => 'dmk_dt_id',
                                'agency' => 'dmk_ag_id',
                                'branch' => 'dmk_br_id'
                            ],
                            'labels' => [
                                'distributor' => '총판',
                                'agency' => '대리점',
                                'branch' => '지점'
                            ],
                            'placeholders' => [
                                'distributor' => '총판을 선택하세요',
                                'agency' => '대리점을 선택하세요',
                                'branch' => '지점을 선택하세요'
                            ],
                            'form_id' => 'fitem',
                            'auto_submit' => false,
                            'show_labels' => false,
                            'container_class' => 'dmk-owner-select'
                        ]);
                        ?>
                    <?php } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) { ?>
                        <!-- 대리점 관리자는 대리점-지점 선택 가능 -->
                        <?php 
                        echo dmk_render_chain_select([
                            'page_type' => DMK_CHAIN_SELECT_FULL,
                            'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
                            'current_values' => [
                                'dmk_ag_id' => isset($it['dmk_ag_id']) ? $it['dmk_ag_id'] : $current_ag_id,
                                'dmk_br_id' => isset($it['dmk_br_id']) ? $it['dmk_br_id'] : ''
                            ],
                            'field_names' => [
                                'agency' => 'dmk_ag_id',
                                'branch' => 'dmk_br_id'
                            ],
                            'labels' => [
                                'agency' => '대리점',
                                'branch' => '지점'
                            ],
                            'placeholders' => [
                                'agency' => '대리점을 선택하세요',
                                'branch' => '지점을 선택하세요'
                            ],
                            'form_id' => 'fitem',
                            'auto_submit' => false,
                            'show_labels' => false,
                            'container_class' => 'dmk-owner-select'
                        ]);
                        ?>
                        <!-- 총판 정보는 hidden으로 전달 -->
                        <input type="hidden" name="dmk_dt_id" value="<?php echo isset($it['dmk_dt_id']) ? $it['dmk_dt_id'] : $current_dt_id; ?>">
                    <?php } else { ?>
                        <!-- 지점 관리자는 수정 불가 -->
                        <input type="hidden" name="dmk_dt_id" value="<?php echo isset($it['dmk_dt_id']) ? $it['dmk_dt_id'] : ''; ?>">
                        <input type="hidden" name="dmk_ag_id" value="<?php echo isset($it['dmk_ag_id']) ? $it['dmk_ag_id'] : ''; ?>">
                        <input type="hidden" name="dmk_br_id" value="<?php echo isset($it['dmk_br_id']) ? $it['dmk_br_id'] : ''; ?>">
                    <?php } ?>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_cate">
    <h2 class="h2_frm">상품분류</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p>기본분류는 반드시 선택하셔야 합니다. 하나의 상품에 최대 3개의 다른 분류를 지정할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>상품분류 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="ca_id">기본분류</label></th>
            <td>
                <?php if ($w == "") echo help("기본분류를 선택하면, 판매/재고/HTML사용/판매자 E-mail 등을, 선택한 분류의 기본값으로 설정합니다."); ?>
                <select name="ca_id" id="ca_id" onchange="categorychange(this.form)">
                    <option value="">선택하세요</option>
                    <?php echo conv_selected_option($category_select, $it['ca_id']); ?>
                </select>
                <script>
                    var ca_use = new Array();
                    var ca_stock_qty = new Array();
                    //var ca_explan_html = new Array();
                    var ca_sell_email = new Array();
                    var ca_opt1_subject = new Array();
                    var ca_opt2_subject = new Array();
                    var ca_opt3_subject = new Array();
                    var ca_opt4_subject = new Array();
                    var ca_opt5_subject = new Array();
                    var ca_opt6_subject = new Array();
                    <?php echo "\n$script"; ?>
                </script>
            </td>
        </tr>
        <?php for ($i=2; $i<=3; $i++) { ?>
        <tr>
            <th scope="row"><label for="ca_id<?php echo $i; ?>"><?php echo $i; ?>차 분류</label></th>
            <td>
                <?php echo help($i.'차 분류는 기본 분류의 하위 분류 개념이 아니므로 기본 분류 선택시 해당 상품이 포함될 최하위 분류만 선택하시면 됩니다.'); ?>
                <select name="ca_id<?php echo $i; ?>" id="ca_id<?php echo $i; ?>">
                    <option value="">선택하세요</option>
                    <?php echo conv_selected_option($category_select, $it['ca_id'.$i]); ?>
                </select>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_skin">
    <h2 class="h2_frm">스킨설정</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p>상품상세보기에서 사용할 스킨을 설정합니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>스킨설정</caption>
      
        <tbody>
        <tr>
            <th scope="row"><label for="it_skin">PC용 스킨</label></th>
            <td>
                <?php echo get_skin_select('shop', 'it_skin', 'it_skin', $it['it_skin']); ?>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_skin" value="1" id="chk_ca_it_skin">
                <label for="chk_ca_it_skin">분류적용</label>
                <input type="checkbox" name="chk_all_it_skin" value="1" id="chk_all_it_skin">
                <label for="chk_all_it_skin">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_mobile_skin">모바일용 스킨</label></th>
            <td>
                <?php echo get_mobile_skin_select('shop', 'it_mobile_skin', 'it_mobile_skin', $it['it_mobile_skin']); ?>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_mobile_skin" value="1" id="chk_ca_it_mobile_skin">
                <label for="chk_ca_it_mobile_skin">분류적용</label>
                <input type="checkbox" name="chk_all_it_mobile_skin" value="1" id="chk_all_it_mobile_skin">
                <label for="chk_all_it_mobile_skin">전체적용</label>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>



<section id="anc_sitfrm_ini">
    <h2 class="h2_frm">기본정보</h2>
    <?php echo $pg_anchor; ?>
    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>기본정보 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">상품코드</th>
            <td colspan="2">
                <?php if ($w == '') { // 추가 ?>
                    <?php echo help("상품의 코드는 10자리 숫자로 자동생성합니다. <b>직접 상품코드를 입력할 수도 있습니다.</b>\n상품코드는 영문자, 숫자, - 만 입력 가능합니다."); ?>
                    <input type="text" name="it_id" value="<?php echo time(); ?>" id="it_id" required class="frm_input required" size="20" maxlength="20">
                <?php } else { ?>
                    <input type="hidden" name="it_id" value="<?php echo $it['it_id']; ?>">
                    <span class="frm_ca_id"><?php echo $it['it_id']; ?></span>
                    <a href="<?php echo shop_item_url($it_id); ?>" class="btn_frmline">상품확인</a>
                    <a href="<?php echo G5_ADMIN_URL; ?>/shop_admin/itemuselist.php?sfl=a.it_id&amp;stx=<?php echo $it_id; ?>" class="btn_frmline">사용후기</a>
                    <a href="<?php echo G5_ADMIN_URL; ?>/shop_admin/itemqalist.php?sfl=a.it_id&amp;stx=<?php echo $it_id; ?>" class="btn_frmline">상품문의</a>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_name">상품명</label></th>
            <td colspan="2">
                <?php echo help("HTML 입력이 불가합니다."); ?>
                <input type="text" name="it_name" value="<?php echo get_text(cut_str($it['it_name'], 250, "")); ?>" id="it_name" required class="frm_input required" size="95">
            </td>
        </tr>

        
        <tr>
            <th scope="row"><label for="it_basic">기본설명</label></th>
            <td>
                <?php echo help("상품명 하단에 상품에 대한 추가적인 설명이 필요한 경우에 입력합니다. HTML 입력도 가능합니다."); ?>
                <input type="text" name="it_basic" value="<?php echo get_text(html_purifier($it['it_basic'])); ?>" id="it_basic" class="frm_input" size="95">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_basic" value="1" id="chk_ca_it_basic">
                <label for="chk_ca_it_basic">분류적용</label>
                <input type="checkbox" name="chk_all_it_basic" value="1" id="chk_all_it_basic">
                <label for="chk_all_it_basic">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_order">출력순서</label></th>
            <td>
                <?php echo help("숫자가 작을 수록 상위에 출력됩니다. 음수 입력도 가능하며 입력 가능 범위는 -2147483648 부터 2147443647 까지입니다.\n<b>입력하지 않으면 자동으로 출력됩니다.</b>"); ?>
                <input type="text" name="it_order" value="<?php echo $it['it_order']; ?>" id="it_order" class="frm_input" size="12">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_order" value="1" id="chk_ca_it_order">
                <label for="chk_ca_it_order">분류적용</label>
                <input type="checkbox" name="chk_all_it_order" value="1" id="chk_all_it_order">
                <label for="chk_all_it_order">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row">상품유형</th>
            <td>
                <?php echo help("메인화면에 유형별로 출력할때 사용합니다.\n이곳에 체크하게되면 상품리스트에서 유형별로 정렬할때 체크된 상품이 가장 먼저 출력됩니다."); ?>
                <input type="checkbox" name="it_type1" value="1" <?php echo ($it['it_type1'] ? "checked" : ""); ?> id="it_type1">
                <label for="it_type1">히트 <img src="<?php echo G5_SHOP_URL; ?>/img/icon_hit.gif" alt=""></label>
                <input type="checkbox" name="it_type2" value="1" <?php echo ($it['it_type2'] ? "checked" : ""); ?> id="it_type2">
                <label for="it_type2">추천 <img src="<?php echo G5_SHOP_URL; ?>/img/icon_rec.gif" alt=""></label>
                <input type="checkbox" name="it_type3" value="1" <?php echo ($it['it_type3'] ? "checked" : ""); ?> id="it_type3">
                <label for="it_type3">신상품 <img src="<?php echo G5_SHOP_URL; ?>/img/icon_new.gif" alt=""></label>
                <input type="checkbox" name="it_type4" value="1" <?php echo ($it['it_type4'] ? "checked" : ""); ?> id="it_type4">
                <label for="it_type4">인기 <img src="<?php echo G5_SHOP_URL; ?>/img/icon_best.gif" alt=""></label>
                <input type="checkbox" name="it_type5" value="1" <?php echo ($it['it_type5'] ? "checked" : ""); ?> id="it_type5">
                <label for="it_type5">할인 <img src="<?php echo G5_SHOP_URL; ?>/img/icon_discount.gif" alt=""></label>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_type" value="1" id="chk_ca_it_type">
                <label for="chk_ca_it_type">분류적용</label>
                <input type="checkbox" name="chk_all_it_type" value="1" id="chk_all_it_type">
                <label for="chk_all_it_type">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="dmk_it_type">상품 유형</label></th>
            <td>
                <?php echo help("상품의 판매 유형을 설정합니다. (일별 공구, 예약 공구)"); ?>
                <select name="dmk_it_type" id="dmk_it_type">
                    <option value="0" <?php echo get_selected('0', $it['dmk_it_type']); ?>>일반 상품</option>
                    <option value="1" <?php echo get_selected('1', $it['dmk_it_type']); ?>>일별 공구</option>
                    <option value="2" <?php echo get_selected('2', $it['dmk_it_type']); ?>>예약 공구</option>
                </select>
            </td>
            <td class="td_grpset"></td>
        </tr>
        <tr>
            <th scope="row"><label for="dmk_it_valid_start_date">유효 시작일</label></th>
            <td>
                <?php echo help("상품의 유효 시작일을 설정합니다."); ?>
                <input type="text" name="dmk_it_valid_start_date" value="<?php echo ($it['dmk_it_valid_start_date'] !== '0000-00-00 00:00:00') ? substr($it['dmk_it_valid_start_date'], 0, 10) : ''; ?>" id="dmk_it_valid_start_date" class="frm_input calendar-input" size="10" maxlength="10">
                <button type="button" class="frm_input_button" id="dmk_it_valid_start_date_btn" style="padding:1px 2px"><i class="fa fa-calendar"></i></button>
            </td>
            <td class="td_grpset"></td>
        </tr>
        <tr>
            <th scope="row"><label for="dmk_it_valid_end_date">유효 종료일</label></th>
            <td>
                <?php echo help("상품의 유효 종료일을 설정합니다."); ?>
                <input type="text" name="dmk_it_valid_end_date" value="<?php echo ($it['dmk_it_valid_end_date'] !== '0000-00-00 00:00:00') ? substr($it['dmk_it_valid_end_date'], 0, 10) : ''; ?>" id="dmk_it_valid_end_date" class="frm_input calendar-input" size="10" maxlength="10">
                <button type="button" class="frm_input_button" id="dmk_it_valid_end_date_btn" style="padding:1px 2px"><i class="fa fa-calendar"></i></button>
            </td>
            <td class="td_grpset"></td>
        </tr>
        <script>
        jQuery(function($){

            var $dtSelect = $('#dt_id');
            var $agSelect = $('#ag_id');
            var $brSelect = $('#br_id');
            var dmkJsConsts = <?php echo json_encode($dmk_js_consts); ?>;

            function populateDropdown(targetSelect, items, selectedValue, emptyOptionText) {
                targetSelect.empty();
                targetSelect.append('<option value="">' + emptyOptionText + '</option>');
                
                if (Array.isArray(items)) {
                    $.each(items, function(index, item) {
                        var id = item.id || item.mb_id || item.ag_id || item.br_id;
                        var name = item.name || item.mb_name || item.ag_name || item.br_name;
                        
                        if (id && name) {
                            var selectedAttr = (selectedValue === id) ? 'selected' : '';
                            targetSelect.append('<option value="' + id + '" ' + selectedAttr + '>' + name + ' (' + id + ')</option>');
                        }
                    });
                }
            }

            // 총판 선택 변경 시
            $dtSelect.on('change', function() {
                var dt_id = $(this).val();
                $agSelect.empty().append('<option value="">- 대리점 선택 -</option>');
                $brSelect.empty().append('<option value="">- 지점 선택 -</option>');

                if (dt_id) {
                    $.ajax({
                        url: './ajax.get_dmk_owner_ids.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: dt_id },
                        success: function(data) {
                            if (data.error) {
                                alert('대리점 목록을 가져오는 중 오류가 발생했습니다: ' + data.error);
                            } else {
                                populateDropdown($agSelect, data, '<?php echo $selected_ag_id; ?>', '- 대리점 선택 -');
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('대리점 목록을 가져오는 중 네트워크 오류가 발생했습니다.');
                        }
                    });
                }
            });

            // 대리점 선택 변경 시
            $agSelect.on('change', function() {
                var ag_id = $(this).val();
                $brSelect.empty().append('<option value="">- 지점 선택 -</option>');

                if (ag_id) {
                    $.ajax({
                        url: './ajax.get_dmk_owner_ids.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: ag_id },
                        success: function(data) {
                            if (data.error) {
                                alert('지점 목록을 가져오는 중 오류가 발생했습니다: ' + data.error);
                            } else {
                                populateDropdown($brSelect, data, '<?php echo $selected_br_id; ?>', '- 지점 선택 -');
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('지점 목록을 가져오는 중 네트워크 오류가 발생했습니다.');
                        }
                    });
                }
            });

            // 페이지 로드 시 초기값 설정 및 비활성화 처리
            var dmkAuth = <?php echo json_encode($dmk_auth); ?>;
            if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_DISTRIBUTOR) {
                $dtSelect.prop('disabled', true);
            } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_AGENCY) {
                $dtSelect.prop('disabled', true);
                $agSelect.prop('disabled', true);
            } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_BRANCH) {
                $dtSelect.prop('disabled', true);
                $agSelect.prop('disabled', true);
                $brSelect.prop('disabled', true);
            }
        });
        </script>
        
        <tr>
            <th scope="row"><label for="it_maker">제조사</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_maker" value="<?php echo get_text($it['it_maker']); ?>" id="it_maker" class="frm_input" size="40">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_maker" value="1" id="chk_ca_it_maker">
                <label for="chk_ca_it_maker">분류적용</label>
                <input type="checkbox" name="chk_all_it_maker" value="1" id="chk_all_it_maker">
                <label for="chk_all_it_maker">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_origin">원산지</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_origin" value="<?php echo get_text($it['it_origin']); ?>" id="it_origin" class="frm_input" size="40">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_origin" value="1" id="chk_ca_it_origin">
                <label for="chk_ca_it_origin">분류적용</label>
                <input type="checkbox" name="chk_all_it_origin" value="1" id="chk_all_it_origin">
                <label for="chk_all_it_origin">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_brand">브랜드</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_brand" value="<?php echo get_text($it['it_brand']); ?>" id="it_brand" class="frm_input" size="40">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_brand" value="1" id="chk_ca_it_brand">
                <label for="chk_ca_it_brand">분류적용</label>
                <input type="checkbox" name="chk_all_it_brand" value="1" id="chk_all_it_brand">
                <label for="chk_all_it_brand">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_model">모델</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_model" value="<?php echo get_text($it['it_model']); ?>" id="it_model" class="frm_input" size="40">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_model" value="1" id="chk_ca_it_model">
                <label for="chk_ca_it_model">분류적용</label>
                <input type="checkbox" name="chk_all_it_model" value="1" id="chk_all_it_model">
                <label for="chk_all_it_model">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_tel_inq">전화문의</label></th>
            <td>
                <?php echo help("상품 금액 대신 전화문의로 표시됩니다."); ?>
                <input type="checkbox" name="it_tel_inq" value="1" id="it_tel_inq" <?php echo ($it['it_tel_inq']) ? "checked" : ""; ?>> 예
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_tel_inq" value="1" id="chk_ca_it_tel_inq">
                <label for="chk_ca_it_tel_inq">분류적용</label>
                <input type="checkbox" name="chk_all_it_tel_inq" value="1" id="chk_all_it_tel_inq">
                <label for="chk_all_it_tel_inq">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_use">판매가능</label></th>
            <td>
                <?php echo help("잠시 판매를 중단하거나 재고가 없을 경우에 체크를 해제해 놓으면 출력되지 않으며, 주문도 받지 않습니다."); ?>
                <input type="checkbox" name="it_use" value="1" id="it_use" <?php echo ($it['it_use']) ? "checked" : ""; ?>> 예
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_use" value="1" id="chk_ca_it_use">
                <label for="chk_ca_it_use">분류적용</label>
                <input type="checkbox" name="chk_all_it_use" value="1" id="chk_all_it_use">
                <label for="chk_all_it_use">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_nocoupon">쿠폰적용안함</label></th>
            <td>
                <?php echo help("설정에 체크하시면 쿠폰 생성 때 상품 검색 결과에 노출되지 않습니다."); ?>
                <input type="checkbox" name="it_nocoupon" value="1" id="it_nocoupon" <?php echo ($it['it_nocoupon']) ? "checked" : ""; ?>> 예
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_nocoupon" value="1" id="chk_ca_it_nocoupon">
                <label for="chk_ca_it_nocoupon">분류적용</label>
                <input type="checkbox" name="chk_all_it_nocoupon" value="1" id="chk_all_it_nocoupon">
                <label for="chk_all_it_nocoupon">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ec_mall_pid">네이버쇼핑 상품ID</label></th>
            <td colspan="2">
                <?php echo help("네이버쇼핑에 입점한 경우 네이버쇼핑 상품ID를 입력하시면 네이버페이와 연동됩니다.<br>일부 쇼핑몰의 경우 네이버쇼핑 상품ID 대신 쇼핑몰 상품ID를 입력해야 하는 경우가 있습니다.<br>네이버페이 연동과정에서 이 부분에 대한 안내가 이뤄지니 안내받은 대로 값을 입력하시면 됩니다."); ?>
                <input type="text" name="ec_mall_pid" value="<?php echo get_text($it['ec_mall_pid']); ?>" id="ec_mall_pid" class="frm_input" size="20">
            </td>
        </tr>
        <tr>
            <th scope="row">상품설명</th>
            <td colspan="2"> <?php echo editor_html('it_explan', get_text(html_purifier($it['it_explan']), 0)); ?></td>
        </tr>
        <tr>
            <th scope="row">모바일 상품설명</th>
            <td colspan="2"> <?php echo editor_html('it_mobile_explan', get_text(html_purifier($it['it_mobile_explan']), 0)); ?></td>
        </tr>
        <tr>
            <th scope="row"><label for="it_sell_email">판매자 e-mail</label></th>
            <td>
                <?php echo help("운영자와 실제 판매자가 다른 경우 실제 판매자의 e-mail을 입력하면, 상품 주문 시점을 기준으로 실제 판매자에게도 주문서를 발송합니다."); ?>
                <input type="text" name="it_sell_email" value="<?php echo get_sanitize_input($it['it_sell_email']); ?>" id="it_sell_email" class="frm_input" size="40">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_sell_email" value="1" id="chk_ca_it_sell_email">
                <label for="chk_ca_it_sell_email">분류적용</label>
                <input type="checkbox" name="chk_all_it_sell_email" value="1" id="chk_all_it_sell_email">
                <label for="chk_all_it_sell_email">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_shop_memo">상점메모</label></th>
            <td><textarea name="it_shop_memo" id="it_shop_memo"><?php echo html_purifier($it['it_shop_memo']); ?></textarea></td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_shop_memo" value="1" id="chk_ca_it_shop_memo">
                <label for="chk_ca_it_shop_memo">분류적용</label>
                <input type="checkbox" name="chk_all_it_shop_memo" value="1" id="chk_all_it_shop_memo">
                <label for="chk_all_it_shop_memo">전체적용</label>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_compact">
    <h2 class="h2_frm">상품요약정보</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p><strong>전자상거래 등에서의 상품 등의 정보제공에 관한 고시</strong>에 따라 총 35개 상품군에 대해 상품 특성 등을 양식에 따라 입력할 수 있습니다.</p>
    </div>

    <div id="sit_compact">
        <?php echo help("상품군을 선택하면 자동으로 항목이 변환됩니다."); ?>
        <select id="it_info_gubun" name="it_info_gubun">
            <option value="">상품군을 선택하세요.</option>
            <?php
            if(!$it['it_info_gubun']) $it['it_info_gubun'] = 'wear';
            foreach($item_info as $key=>$value) {
                $opt_value = $key;
                $opt_text  = $value['title'];
                echo '<option value="'.$opt_value.'" '.get_selected($opt_value, $it['it_info_gubun']).'>'.$opt_text.'</option>'.PHP_EOL;
            }
            ?>
        </select>
    </div>
    <div id="sit_compact_fields"><?php include_once(G5_ADMIN_PATH.'/shop_admin/iteminfo.php'); ?></div>
</section>


<script>
$(function(){
    $(document).on("change", "#it_info_gubun", function() {
        var gubun = $(this).val();
        $.post(
            "<?php echo G5_ADMIN_URL; ?>/shop_admin/iteminfo.php",
            { it_id: "<?php echo $it['it_id']; ?>", gubun: gubun },
            function(data) {
                $("#sit_compact_fields").empty().html(data);
            }
        );
    });
});
</script>

<section id="anc_sitfrm_cost">
    <h2 class="h2_frm">가격 및 재고</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>가격 및 재고 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="it_price">판매가격</label></th>
            <td>
                <input type="text" name="it_price" value="<?php echo $it['it_price']; ?>" id="it_price" class="frm_input" size="8"> 원
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_price" value="1" id="chk_ca_it_price">
                <label for="chk_ca_it_price">분류적용</label>
                <input type="checkbox" name="chk_all_it_price" value="1" id="chk_all_it_price">
                <label for="chk_all_it_price">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_cust_price">시중가격</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_cust_price" value="<?php echo $it['it_cust_price']; ?>" id="it_cust_price" class="frm_input" size="8"> 원
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_cust_price" value="1" id="chk_ca_it_cust_price">
                <label for="chk_ca_it_cust_price">분류적용</label>
                <input type="checkbox" name="chk_all_it_cust_price" value="1" id="chk_all_it_cust_price">
                <label for="chk_all_it_cust_price">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_point_type">포인트 유형</label></th>
            <td>
                <?php echo help("포인트 유형을 설정할 수 있습니다. 비율로 설정했을 경우 설정 기준금액의 %비율로 포인트가 지급됩니다."); ?>
                <select name="it_point_type" id="it_point_type">
                    <option value="0"<?php echo get_selected('0', $it['it_point_type']); ?>>설정금액</option>
                    <option value="1"<?php echo get_selected('1', $it['it_point_type']); ?>>판매가기준 설정비율</option>
                    <option value="2"<?php echo get_selected('2', $it['it_point_type']); ?>>구매가기준 설정비율</option>
                </select>
                <script>
                $(function() {
                    $("#it_point_type").change(function() {
                        if(parseInt($(this).val()) > 0)
                            $("#it_point_unit").text("%");
                        else
                            $("#it_point_unit").text("점");
                    });
                });
                </script>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_point_type" value="1" id="chk_ca_it_point_type">
                <label for="chk_ca_it_point_type">분류적용</label>
                <input type="checkbox" name="chk_all_it_point_type" value="1" id="chk_all_it_point_type">
                <label for="chk_all_it_point_type">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_point">포인트</label></th>
            <td>
                <?php echo help("주문완료후 환경설정에서 설정한 주문완료 설정일 후 회원에게 부여하는 포인트입니다.\n또, 포인트부여를 '아니오'로 설정한 경우 신용카드, 계좌이체로 주문하는 회원께는 부여하지 않습니다."); ?>
                <input type="text" name="it_point" value="<?php echo $it['it_point']; ?>" id="it_point" class="frm_input" size="8"> <span id="it_point_unit"><?php if($it['it_point_type']) echo '%'; else echo '점'; ?></span>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_point" value="1" id="chk_ca_it_point">
                <label for="chk_ca_it_point">분류적용</label>
                <input type="checkbox" name="chk_all_it_point" value="1" id="chk_all_it_point">
                <label for="chk_all_it_point">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_supply_point">추가옵션상품 포인트</label></th>
            <td>
                <?php echo help("상품의 추가옵션상품 구매에 일괄적으로 지급하는 포인트입니다. 0으로 설정하시면 구매포인트를 지급하지 않습니다.\n주문완료후 환경설정에서 설정한 주문완료 설정일 후 회원에게 부여하는 포인트입니다.\n또, 포인트부여를 '아니오'로 설정한 경우 신용카드, 계좌이체로 주문하는 회원께는 부여하지 않습니다."); ?>
                <input type="text" name="it_supply_point" value="<?php echo $it['it_supply_point']; ?>" id="it_supply_point" class="frm_input" size="8"> 점
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_supply_point" value="1" id="chk_ca_it_supply_point">
                <label for="chk_ca_it_supply_point">분류적용</label>
                <input type="checkbox" name="chk_all_it_supply_point" value="1" id="chk_all_it_supply_point">
                <label for="chk_all_it_supply_point">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_soldout">상품품절</label></th>
            <td>
                <?php echo help("잠시 판매를 중단하거나 재고가 없을 경우에 체크해 놓으면 품절상품으로 표시됩니다."); ?>
                <input type="checkbox" name="it_soldout" value="1" id="it_soldout" <?php echo ($it['it_soldout']) ? "checked" : ""; ?>> 예
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_soldout" value="1" id="chk_ca_it_soldout">
                <label for="chk_ca_it_soldout">분류적용</label>
                <input type="checkbox" name="chk_all_it_soldout" value="1" id="chk_all_it_soldout">
                <label for="chk_all_it_soldout">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_stock_sms">재입고SMS 알림</label></th>
            <td colspan="2">
                <?php echo help("상품이 품절인 경우에 체크해 놓으면 상품상세보기에서 고객이 재입고SMS 알림을 신청할 수 있게 됩니다."); ?>
                <input type="checkbox" name="it_stock_sms" value="1" id="it_stock_sms" <?php echo ($it['it_stock_sms']) ? "checked" : ""; ?>> 예
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_stock_qty">재고수량</label></th>
            <td>
                <?php echo help("<b>주문관리에서 상품별 상태 변경에 따라 자동으로 재고를 가감합니다.</b> 재고는 규격/색상별이 아닌, 상품별로만 관리됩니다.<br>재고수량을 0으로 설정하시면 품절상품으로 표시됩니다."); ?>
                <input type="text" name="it_stock_qty" value="<?php echo $it['it_stock_qty']; ?>" id="it_stock_qty" class="frm_input" size="8"> 개
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_stock_qty" value="1" id="chk_ca_it_stock_qty">
                <label for="chk_ca_it_stock_qty">분류적용</label>
                <input type="checkbox" name="chk_all_it_stock_qty" value="1" id="chk_all_it_stock_qty">
                <label for="chk_all_it_stock_qty">전체적용</label>
            </td>
        </tr>

        

        <tr>
            <th scope="row"><label for="it_noti_qty">재고 통보수량</label></th>
            <td>
                <?php echo help("상품의 재고가 통보수량보다 작을 때 쇼핑몰관리 메인화면의 재고현황에 재고부족 상품으로 표시됩니다.<br>옵션이 있는 상품은 개별 옵션의 통보수량이 적용됩니다."); ?>
                <input type="text" name="it_noti_qty" value="<?php echo $it['it_noti_qty']; ?>" id="it_noti_qty" class="frm_input" size="8"> 개
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_noti_qty" value="1" id="chk_ca_it_noti_qty">
                <label for="chk_ca_it_noti_qty">분류적용</label>
                <input type="checkbox" name="chk_all_it_noti_qty" value="1" id="chk_all_it_noti_qty">
                <label for="chk_all_it_noti_qty">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_buy_min_qty">최소구매수량</label></th>
            <td>
                <?php echo help("상품 구매시 최소 구매 수량을 설정합니다."); ?>
                <input type="text" name="it_buy_min_qty" value="<?php echo $it['it_buy_min_qty']; ?>" id="it_buy_min_qty" class="frm_input" size="8"> 개
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_buy_min_qty" value="1" id="chk_ca_it_buy_min_qty">
                <label for="chk_ca_it_buy_min_qty">분류적용</label>
                <input type="checkbox" name="chk_all_it_buy_min_qty" value="1" id="chk_all_it_buy_min_qty">
                <label for="chk_all_it_buy_min_qty">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_buy_max_qty">최대구매수량</label></th>
            <td>
                <?php echo help("상품 구매시 최대 구매 수량을 설정합니다."); ?>
                <input type="text" name="it_buy_max_qty" value="<?php echo $it['it_buy_max_qty']; ?>" id="it_buy_max_qty" class="frm_input" size="8"> 개
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_buy_max_qty" value="1" id="chk_ca_it_buy_max_qty">
                <label for="chk_ca_it_buy_max_qty">분류적용</label>
                <input type="checkbox" name="chk_all_it_buy_max_qty" value="1" id="chk_all_it_buy_max_qty">
                <label for="chk_all_it_buy_max_qty">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_notax">상품과세 유형</label></th>
            <td>
                <?php echo help("상품의 과세유형(과세, 비과세)을 설정합니다."); ?>
                <select name="it_notax" id="it_notax">
                    <option value="0"<?php echo get_selected('0', $it['it_notax']); ?>>과세</option>
                    <option value="1"<?php echo get_selected('1', $it['it_notax']); ?>>비과세</option>
                </select>
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_notax" value="1" id="chk_ca_it_notax">
                <label for="chk_ca_it_notax">분류적용</label>
                <input type="checkbox" name="chk_all_it_notax" value="1" id="chk_all_it_notax">
                <label for="chk_all_it_notax">전체적용</label>
            </td>
        </tr>
        <?php
        $opt_subject = explode(',', $it['it_option_subject']);
        ?>
        <tr>
            <th scope="row">상품선택옵션</th>
            <td colspan="2">
                <div class="sit_option tbl_frm01">
                    <?php echo help('옵션항목은 콤마(,) 로 구분하여 여러개를 입력할 수 있습니다. 옷을 예로 들어 [옵션1 : 사이즈 , 옵션1 항목 : XXL,XL,L,M,S] , [옵션2 : 색상 , 옵션2 항목 : 빨,파,노]<br><strong>옵션명과 옵션항목에 따옴표(\', ")는 입력할 수 없습니다.</strong>'); ?>
                    <table>
                    <caption>상품선택옵션 입력</caption>
                    <colgroup>
                        <col class="grid_4">
                        <col>
                    </colgroup>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="opt1_subject">옵션1</label>
                            <input type="text" name="opt1_subject" value="<?php echo isset($opt_subject[0]) ? $opt_subject[0] : ''; ?>" id="opt1_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt1"><b>옵션1 항목</b></label>
                            <input type="text" name="opt1" value="" id="opt1" class="frm_input" size="50">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="opt2_subject">옵션2</label>
                            <input type="text" name="opt2_subject" value="<?php echo isset($opt_subject[1]) ? $opt_subject[1] : ''; ?>" id="opt2_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt2"><b>옵션2 항목</b></label>
                            <input type="text" name="opt2" value="" id="opt2" class="frm_input" size="50">
                        </td>
                    </tr>
                     <tr>
                        <th scope="row">
                            <label for="opt3_subject">옵션3</label>
                            <input type="text" name="opt3_subject" value="<?php echo isset($opt_subject[2]) ? $opt_subject[2] : ''; ?>" id="opt3_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt3"><b>옵션3 항목</b></label>
                            <input type="text" name="opt3" value="" id="opt3" class="frm_input" size="50">
                        </td>
                    </tr>
                    </tbody>
                    </table>
                    <div class="btn_confirm02 btn_confirm">
                        <button type="button" id="option_table_create" class="btn_frmline">옵션목록생성</button>
                    </div>
                </div>
                <div id="sit_option_frm"><?php include_once(G5_ADMIN_PATH.'/shop_admin/itemoption.php'); ?></div>

                <script>
                $(function() {
                    <?php if($it['it_id'] && $po_run) { ?>
                    //옵션항목설정
                    var arr_opt1 = new Array();
                    var arr_opt2 = new Array();
                    var arr_opt3 = new Array();
                    var opt1 = opt2 = opt3 = '';
                    var opt_val;

                    $(".opt-cell").each(function() {
                        opt_val = $(this).text().split(" > ");
                        opt1 = opt_val[0];
                        opt2 = opt_val[1];
                        opt3 = opt_val[2];

                        if(opt1 && $.inArray(opt1, arr_opt1) == -1)
                            arr_opt1.push(opt1);

                        if(opt2 && $.inArray(opt2, arr_opt2) == -1)
                            arr_opt2.push(opt2);

                        if(opt3 && $.inArray(opt3, arr_opt3) == -1)
                            arr_opt3.push(opt3);
                    });


                    $("input[name=opt1]").val(arr_opt1.join());
                    $("input[name=opt2]").val(arr_opt2.join());
                    $("input[name=opt3]").val(arr_opt3.join());
                    <?php } ?>
                    // 옵션목록생성
                    $("#option_table_create").click(function() {
                        var it_id = $.trim($("input[name=it_id]").val());
                        var opt1_subject = $.trim($("#opt1_subject").val());
                        var opt2_subject = $.trim($("#opt2_subject").val());
                        var opt3_subject = $.trim($("#opt3_subject").val());
                        var opt1 = $.trim($("#opt1").val());
                        var opt2 = $.trim($("#opt2").val());
                        var opt3 = $.trim($("#opt3").val());
                        var $option_table = $("#sit_option_frm");

                        if(!opt1_subject || !opt1) {
                            alert("옵션명과 옵션항목을 입력해 주십시오.");
                            return false;
                        }

                        $.post(
                            "<?php echo G5_ADMIN_URL; ?>/shop_admin/itemoption.php",
                            { it_id: it_id, w: "<?php echo $w; ?>", opt1_subject: opt1_subject, opt2_subject: opt2_subject, opt3_subject: opt3_subject, opt1: opt1, opt2: opt2, opt3: opt3 },
                            function(data) {
                                $option_table.empty().html(data);
                            }
                        );
                    });

                    // 모두선택
                    $(document).on("click", "input[name=opt_chk_all]", function() {
                        if($(this).is(":checked")) {
                            $("input[name='opt_chk[]']").attr("checked", true);
                        } else {
                            $("input[name='opt_chk[]']").attr("checked", false);
                        }
                    });

                    // 선택삭제
                    $(document).on("click", "#sel_option_delete", function() {
                        var $el = $("input[name='opt_chk[]']:checked");
                        if($el.length < 1) {
                            alert("삭제하려는 옵션을 하나 이상 선택해 주십시오.");
                            return false;
                        }

                        $el.closest("tr").remove();
                    });

                    // 일괄적용
                    $(document).on("click", "#opt_value_apply", function() {
                        if($(".opt_com_chk:checked").length < 1) {
                            alert("일괄 수정할 항목을 하나이상 체크해 주십시오.");
                            return false;
                        }

                        var opt_price = $.trim($("#opt_com_price").val());
                        var opt_stock = $.trim($("#opt_com_stock").val());
                        var opt_noti = $.trim($("#opt_com_noti").val());
                        var opt_use = $("#opt_com_use").val();
                        var $el = $("input[name='opt_chk[]']:checked");

                        // 체크된 옵션이 있으면 체크된 것만 적용
                        if($el.length > 0) {
                            var $tr;
                            $el.each(function() {
                                $tr = $(this).closest("tr");

                                if($("#opt_com_price_chk").is(":checked"))
                                    $tr.find("input[name='opt_price[]']").val(opt_price);

                                if($("#opt_com_stock_chk").is(":checked"))
                                    $tr.find("input[name='opt_stock_qty[]']").val(opt_stock);

                                if($("#opt_com_noti_chk").is(":checked"))
                                    $tr.find("input[name='opt_noti_qty[]']").val(opt_noti);

                                if($("#opt_com_use_chk").is(":checked"))
                                    $tr.find("select[name='opt_use[]']").val(opt_use);
                            });
                        } else {
                            if($("#opt_com_price_chk").is(":checked"))
                                $("input[name='opt_price[]']").val(opt_price);

                            if($("#opt_com_stock_chk").is(":checked"))
                                $("input[name='opt_stock_qty[]']").val(opt_stock);

                            if($("#opt_com_noti_chk").is(":checked"))
                                $("input[name='opt_noti_qty[]']").val(opt_noti);

                            if($("#opt_com_use_chk").is(":checked"))
                                $("select[name='opt_use[]']").val(opt_use);
                        }
                    });
                });
                </script>
            </td>
        </tr>
        <?php
        $spl_subject = explode(',', $it['it_supply_subject']);
        $spl_count = count($spl_subject);
        ?>
        <tr>
            <th scope="row">상품추가옵션</th>
            <td colspan="2">
                <div id="sit_supply_frm" class="sit_option tbl_frm01">
                    <?php echo help('옵션항목은 콤마(,) 로 구분하여 여러개를 입력할 수 있습니다. 스마트폰을 예로 들어 [추가1 : 추가구성상품 , 추가1 항목 : 액정보호필름,케이스,충전기]<br><strong>옵션명과 옵션항목에 따옴표(\', ")는 입력할 수 없습니다.</strong>'); ?>
                    <table>
                    <caption>상품추가옵션 입력</caption>
                    <colgroup>
                        <col class="grid_4">
                        <col>
                    </colgroup>
                    <tbody>
                    <?php
                    $i = 0;
                    do {
                        $seq = $i + 1;
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="spl_subject_<?php echo $seq; ?>">추가<?php echo $seq; ?></label>
                            <input type="text" name="spl_subject[]" id="spl_subject_<?php echo $seq; ?>" value="<?php echo $spl_subject[$i]; ?>" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="spl_item_<?php echo $seq; ?>"><b>추가<?php echo $seq; ?> 항목</b></label>
                            <input type="text" name="spl[]" id="spl_item_<?php echo $seq; ?>" value="" class="frm_input" size="40">
                            <?php
                            if($i > 0)
                                echo '<button type="button" id="del_supply_row" class="btn_frmline">삭제</button>';
                            ?>
                        </td>
                    </tr>
                    <?php
                        $i++;
                    } while($i < $spl_count);
                    ?>
                    </tbody>
                    </table>
                    <div id="sit_option_addfrm_btn"><button type="button" id="add_supply_row" class="btn_frmline">옵션추가</button></div>
                    <div class="btn_confirm02 btn_confirm">
                        <button type="button" id="supply_table_create">옵션목록생성</button>
                    </div>
                </div>
                <div id="sit_option_addfrm"><?php include_once(G5_ADMIN_PATH.'/shop_admin/itemsupply.php'); ?></div>

                <script>
                $(function() {
                    <?php if($it['it_id'] && $ps_run) { ?>
                    // 추가옵션의 항목 설정
                    var arr_subj = new Array();
                    var subj, spl;

                    $("input[name='spl_subject[]']").each(function() {
                        subj = $.trim($(this).val());
                        if(subj && $.inArray(subj, arr_subj) == -1)
                            arr_subj.push(subj);
                    });

                    for(i=0; i<arr_subj.length; i++) {
                        var arr_spl = new Array();
                        $(".spl-subject-cell").each(function(index) {
                            subj = $(this).text();
                            if(subj == arr_subj[i]) {
                                spl = $(".spl-cell:eq("+index+")").text();
                                arr_spl.push(spl);
                            }
                        });

                        $("input[name='spl[]']:eq("+i+")").val(arr_spl.join());
                    }
                    <?php } ?>
                    // 입력필드추가
                    $("#add_supply_row").click(function() {
                        var $el = $("#sit_supply_frm tr:last");
                        var fld = "<tr>\n";
                        fld += "<th scope=\"row\">\n";
                        fld += "<label for=\"\">추가</label>\n";
                        fld += "<input type=\"text\" name=\"spl_subject[]\" value=\"\" class=\"frm_input\" size=\"15\">\n";
                        fld += "</th>\n";
                        fld += "<td>\n";
                        fld += "<label for=\"\"><b>추가 항목</b></label>\n";
                        fld += "<input type=\"text\" name=\"spl[]\" value=\"\" class=\"frm_input\" size=\"40\">\n";
                        fld += "<button type=\"button\" id=\"del_supply_row\" class=\"btn_frmline\">삭제</button>\n";
                        fld += "</td>\n";
                        fld += "</tr>";

                        $el.after(fld);

                        supply_sequence();
                    });

                    // 입력필드삭제
                    $(document).on("click", "#del_supply_row", function() {
                        $(this).closest("tr").remove();

                        supply_sequence();
                    });

                    // 옵션목록생성
                    $("#supply_table_create").click(function() {
                        var it_id = $.trim($("input[name=it_id]").val());
                        var subject = new Array();
                        var supply = new Array();
                        var subj, spl;
                        var count = 0;
                        var $el_subj = $("input[name='spl_subject[]']");
                        var $el_spl = $("input[name='spl[]']");
                        var $supply_table = $("#sit_option_addfrm");

                        $el_subj.each(function(index) {
                            subj = $.trim($(this).val());
                            spl = $.trim($el_spl.eq(index).val());

                            if(subj && spl) {
                                subject.push(subj);
                                supply.push(spl);
                                count++;
                            }
                        });

                        if(!count) {
                            alert("추가옵션명과 추가옵션항목을 입력해 주십시오.");
                            return false;
                        }

                        $.post(
                            "<?php echo G5_ADMIN_URL; ?>/shop_admin/itemsupply.php",
                            { it_id: it_id, w: "<?php echo $w; ?>", 'subject[]': subject, 'supply[]': supply },
                            function(data) {
                                $supply_table.empty().html(data);
                            }
                        );
                    });

                    // 모두선택
                    $(document).on("click", "input[name=spl_chk_all]", function() {
                        if($(this).is(":checked")) {
                            $("input[name='spl_chk[]']").attr("checked", true);
                        } else {
                            $("input[name='spl_chk[]']").attr("checked", false);
                        }
                    });

                    // 선택삭제
                    $(document).on("click", "#sel_supply_delete", function() {
                        var $el = $("input[name='spl_chk[]']:checked");
                        if($el.length < 1) {
                            alert("삭제하려는 옵션을 하나 이상 선택해 주십시오.");
                            return false;
                        }

                        $el.closest("tr").remove();
                    });

                    // 일괄적용
                    $(document).on("click", "#spl_value_apply", function() {
                        if($(".spl_com_chk:checked").length < 1) {
                            alert("일괄 수정할 항목을 하나이상 체크해 주십시오.");
                            return false;
                        }

                        var spl_price = $.trim($("#spl_com_price").val());
                        var spl_stock = $.trim($("#spl_com_stock").val());
                        var spl_noti = $.trim($("#spl_com_noti").val());
                        var spl_use = $("#spl_com_use").val();
                        var $el = $("input[name='spl_chk[]']:checked");

                        // 체크된 옵션이 있으면 체크된 것만 적용
                        if($el.length > 0) {
                            var $tr;
                            $el.each(function() {
                                $tr = $(this).closest("tr");

                                if($("#spl_com_price_chk").is(":checked"))
                                    $tr.find("input[name='spl_price[]']").val(spl_price);

                                if($("#spl_com_stock_chk").is(":checked"))
                                    $tr.find("input[name='spl_stock_qty[]']").val(spl_stock);

                                if($("#spl_com_noti_chk").is(":checked"))
                                    $tr.find("input[name='spl_noti_qty[]']").val(spl_noti);

                                if($("#spl_com_use_chk").is(":checked"))
                                    $tr.find("select[name='spl_use[]']").val(spl_use);
                            });
                        } else {
                            if($("#spl_com_price_chk").is(":checked"))
                                $("input[name='spl_price[]']").val(spl_price);

                            if($("#spl_com_stock_chk").is(":checked"))
                                $("input[name='spl_stock_qty[]']").val(spl_stock);

                            if($("#spl_com_noti_chk").is(":checked"))
                                $("input[name='spl_noti_qty[]']").val(spl_noti);

                            if($("#spl_com_use_chk").is(":checked"))
                                $("select[name='spl_use[]']").val(spl_use);
                        }
                    });
                });

                function supply_sequence()
                {
                    var $tr = $("#sit_supply_frm tr");
                    var seq;
                    var th_label, td_label;

                    $tr.each(function(index) {
                        seq = index + 1;
                        $(this).find("th label").attr("for", "spl_subject_"+seq).text("추가"+seq);
                        $(this).find("th input").attr("id", "spl_subject_"+seq);
                        $(this).find("td label").attr("for", "spl_item_"+seq);
                        $(this).find("td label b").text("추가"+seq+" 항목");
                        $(this).find("td input").attr("id", "spl_item_"+seq);
                    });
                }
                </script>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_sendcost">
    <h2 class="h2_frm">배송비</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p>쇼핑몰설정 &gt; 배송비유형 설정보다 <strong>개별상품 배송비설정이 우선</strong> 적용됩니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>배송비 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
            <tr>
                <th scope="row"><label for="it_sc_type">배송비 유형</label></th>
                <td>
                    <?php echo help("배송비 유형을 선택하면 자동으로 항목이 변환됩니다."); ?>
                    <select name="it_sc_type" id="it_sc_type">
                        <option value="0"<?php echo get_selected('0', $it['it_sc_type']); ?>>쇼핑몰 기본설정 사용</option>
                        <option value="1"<?php echo get_selected('1', $it['it_sc_type']); ?>>무료배송</option>
                        <option value="2"<?php echo get_selected('2', $it['it_sc_type']); ?>>조건부 무료배송</option>
                        <option value="3"<?php echo get_selected('3', $it['it_sc_type']); ?>>유료배송</option>
                        <option value="4"<?php echo get_selected('4', $it['it_sc_type']); ?>>수량별 부과</option>
                    </select>
                </td>
                <td rowspan="4" id="sc_grp" class="td_grpset">
                    <input type="checkbox" name="chk_ca_it_sendcost" value="1" id="chk_ca_it_sendcost">
                    <label for="chk_ca_it_sendcost">분류적용</label>
                    <input type="checkbox" name="chk_all_it_sendcost" value="1" id="chk_all_it_sendcost">
                    <label for="chk_all_it_sendcost">전체적용</label>
                </td>
            </tr>
            <tr id="sc_con_method">
                <th scope="row"><label for="it_sc_method">배송비 결제</label></th>
                <td>
                    <select name="it_sc_method" id="it_sc_method">
                        <option value="0"<?php echo get_selected('0', $it['it_sc_method']); ?>>선불</option>
                        <option value="1"<?php echo get_selected('1', $it['it_sc_method']); ?>>착불</option>
                        <option value="2"<?php echo get_selected('2', $it['it_sc_method']); ?>>사용자선택</option>
                    </select>
                </td>
            </tr>
            <tr id="sc_con_basic">
                <th scope="row"><label for="it_sc_price">기본배송비</label></th>
                <td>
                    <?php echo help("무료배송 이외의 설정에 적용되는 배송비 금액입니다."); ?>
                    <input type="text" name="it_sc_price" value="<?php echo $it['it_sc_price']; ?>" id="it_sc_price" class="frm_input" size="8"> 원
                </td>
            </tr>
            <tr id="sc_con_minimum">
                <th scope="row"><label for="it_sc_minimum">배송비 상세조건</label></th>
                <td>
                    주문금액 <input type="text" name="it_sc_minimum" value="<?php echo $it['it_sc_minimum']; ?>" id="it_sc_minimum" class="frm_input" size="8"> 이상 무료 배송
                </td>
            </tr>
            <tr id="sc_con_qty">
                <th scope="row"><label for="it_sc_qty">배송비 상세조건</label></th>
                <td>
                    <?php echo help("상품의 주문 수량에 따라 배송비가 부과됩니다. 예를 들어 기본배송비가 3,000원 수량을 3으로 설정했을 경우 상품의 주문수량이 5개이면 6,000원 배송비가 부과됩니다."); ?>
                    주문수량 <input type="text" name="it_sc_qty" value="<?php echo $it['it_sc_qty']; ?>" id="it_sc_qty" class="frm_input" size="8"> 마다 배송비 부과
                </td>
            </tr>
        </tbody>
        </table>
    </div>

    <script>
    $(function() {
        <?php
        switch($it['it_sc_type']) {
            case 1:
                echo '$("#sc_con_method").hide();'.PHP_EOL;
                echo '$("#sc_con_basic").hide();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","1");'.PHP_EOL;
                break;
            case 2:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").show();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","4");'.PHP_EOL;
                break;
            case 3:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","3");'.PHP_EOL;
                break;
            case 4:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").show();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","4");'.PHP_EOL;
                break;
            default:
                echo '$("#sc_con_method").hide();'.PHP_EOL;
                echo '$("#sc_con_basic").hide();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","2");'.PHP_EOL;
                break;
        }
        ?>
        $("#it_sc_type").change(function() {
            var type = $(this).val();

            switch(type) {
                case "1":
                    $("#sc_con_method").hide();
                    $("#sc_con_basic").hide();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","1");
                    break;
                case "2":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").show();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","4");
                    break;
                case "3":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","3");
                    break;
                case "4":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").show();
                    $("#sc_grp").attr("rowspan","4");
                    break;
                default:
                    $("#sc_con_method").hide();
                    $("#sc_con_basic").hide();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","1");
                    break;
            }
        });
    });
    </script>
</section>


<section id="anc_sitfrm_img">
    <h2 class="h2_frm">이미지</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>이미지 업로드</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <?php for($i=1; $i<=10; $i++) { ?>
        <tr>
            <th scope="row"><label for="it_img<?php echo $i; ?>">이미지 <?php echo $i; ?></label></th>
            <td>
                <input type="file" name="it_img<?php echo $i; ?>" id="it_img<?php echo $i; ?>">
                <?php
                $it_img = G5_DATA_PATH.'/item/'.$it['it_img'.$i];
                $it_img_exists = run_replace('shop_item_image_exists', (is_file($it_img) && file_exists($it_img)), $it, $i);

                if($it_img_exists) {
                    $thumb = get_it_thumbnail($it['it_img'.$i], 25, 25);
                    $img_tag = run_replace('shop_item_image_tag', '<img src="'.G5_DATA_URL.'/item/'.$it['it_img'.$i].'" class="shop_item_preview_image" >', $it, $i);
                ?>
                <label for="it_img<?php echo $i; ?>_del"><span class="sound_only">이미지 <?php echo $i; ?> </span>파일삭제</label>
                <input type="checkbox" name="it_img<?php echo $i; ?>_del" id="it_img<?php echo $i; ?>_del" value="1">
                <span class="sit_wimg_limg<?php echo $i; ?>"><?php echo $thumb; ?></span>
                <div id="limg<?php echo $i; ?>" class="banner_or_img">
                    <?php echo $img_tag; ?>
                    <button type="button" class="sit_wimg_close">닫기</button>
                </div>
                <script>
                $('<button type="button" id="it_limg<?php echo $i; ?>_view" class="btn_frmline sit_wimg_view">이미지<?php echo $i; ?> 확인</button>').appendTo('.sit_wimg_limg<?php echo $i; ?>');
                </script>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_relation" class="srel">
    <h2 class="h2_frm">관련상품</h2>
    <?php echo $pg_anchor; ?>

    <div class="local_desc02 local_desc">
        <p>
            등록된 전체상품 목록에서 상품분류를 선택하면 해당 상품 리스트가 연이어 나타납니다.<br>
            상품리스트에서 관련 상품으로 추가하시면 선택된 관련상품 목록에 <strong>함께</strong> 추가됩니다.<br>
            예를 들어, A 상품에 B 상품을 관련상품으로 등록하면 B 상품에도 A 상품이 관련상품으로 자동 추가되며, <strong>확인 버튼을 누르셔야 정상 반영됩니다.</strong>
        </p>
    </div>

    <div class="compare_wrap">
        <section class="compare_left">
            <h3>등록된 전체상품 목록</h3>
            <label for="sch_relation" class="sound_only">상품분류</label>
            <span class="srel_pad">
                <select id="sch_relation">
                    <option value=''>분류별 상품</option>
                    <?php
                        $sql = " select * from {$g5['g5_shop_category_table']} ";
                        if ($is_admin != 'super')
                            $sql .= " where ca_mb_id = '{$member['mb_id']}' ";
                        $sql .= " order by ca_order, ca_id ";
                        $result = sql_query($sql);
                        for ($i=0; $row=sql_fetch_array($result); $i++)
                        {
                            $len = strlen($row['ca_id']) / 2 - 1;

                            $nbsp = "";
                            for ($i=0; $i<$len; $i++)
                                $nbsp .= "&nbsp;&nbsp;&nbsp;";

                            echo "<option value=\"{$row['ca_id']}\">$nbsp{$row['ca_name']}</option>\n";
                        }
                    ?>
                </select>
                <label for="sch_name" class="sound_only">상품명</label>
                <input type="text" name="sch_name" id="sch_name" class="frm_input" size="15">
                <button type="button" id="btn_search_item" class="btn_frmline">검색</button>
            </span>
            <div id="relation" class="srel_list">
                <p>상품의 분류를 선택하시거나 상품명을 입력하신 후 검색하여 주십시오.</p>
            </div>
            <script>
            $(function() {
                $("#btn_search_item").click(function() {
                    var ca_id = $("#sch_relation").val();
                    var it_name = $.trim($("#sch_name").val());
                    var $relation = $("#relation");

                    if(ca_id == "" && it_name == "") {
                        $relation.html("<p>상품의 분류를 선택하시거나 상품명을 입력하신 후 검색하여 주십시오.</p>");
                        return false;
                    }

                    $("#relation").load(
                        "./itemformrelation.php",
                        { it_id: "<?php echo $it_id; ?>", ca_id: ca_id, it_name: it_name }
                    );
                });

                $(document).on("click", "#relation .add_item", function() {
                    // 이미 등록된 상품인지 체크
                    var $li = $(this).closest("li");
                    var it_id = $li.find("input:hidden").val();
                    var it_id2;
                    var dup = false;
                    $("#reg_relation input[name='re_it_id[]']").each(function() {
                        it_id2 = $(this).val();
                        if(it_id == it_id2) {
                            dup = true;
                            return false;
                        }
                    });

                    if(dup) {
                        alert("이미 선택된 상품입니다.");
                        return false;
                    }

                    var cont = "<li>"+$li.html().replace("add_item", "del_item").replace("추가", "삭제")+"</li>";
                    var count = $("#reg_relation li").length;

                    if(count > 0) {
                        $("#reg_relation li:last").after(cont);
                    } else {
                        $("#reg_relation").html("<ul>"+cont+"</ul>");
                    }

                    $li.remove();
                });

                $(document).on("click", "#reg_relation .del_item", function() {
                    if(!confirm("상품을 삭제하시겠습니까?"))
                        return false;

                    $(this).closest("li").remove();

                    var count = $("#reg_relation li").length;
                    if(count < 1)
                        $("#reg_relation").html("<p>선택된 상품이 없습니다.</p>");
                });
            });
            </script>
        </section>

        <section class="compare_right">
            <h3>선택된 관련상품 목록</h3>
            <span class="srel_pad"></span>
            <div id="reg_relation" class="srel_sel">
                <?php
                $str = array();
                $sql = " select b.ca_id, b.it_id, b.it_name, b.it_price
                           from {$g5['g5_shop_item_relation_table']} a
                           left join {$g5['g5_shop_item_table']} b on (a.it_id2=b.it_id)
                          where a.it_id = '$it_id'
                          order by ir_no asc ";
                $result = sql_query($sql);
                for($g=0; $row=sql_fetch_array($result); $g++)
                {
                    $it_name = get_it_image($row['it_id'], 50, 50).' '.$row['it_name'];

                    if($g==0)
                        echo '<ul>';
                ?>
                    <li>
                        <input type="hidden" name="re_it_id[]" value="<?php echo $row['it_id']; ?>">
                        <div class="list_item"><?php echo $it_name; ?></div>
                        <div class="list_item_btn"><button type="button" class="del_item btn_frmline">삭제</button></div>
                    </li>
                <?php
                    $str[] = $row['it_id'];
                }
                $str = implode(",", $str);

                if($g > 0)
                    echo '</ul>';
                else
                    echo '<p>선택된 상품이 없습니다.</p>';
                ?>
            </div>
            <input type="hidden" name="it_list" value="<?php echo $str; ?>">
        </section>

    </div>

</section>


<section id="anc_sitfrm_event" class="srel">
    <h2 class="h2_frm">관련이벤트</h2>
    <?php echo $pg_anchor; ?>

    <div class="compare_wrap">
        <section class="compare_left">
            <h3>등록된 전체이벤트 목록</h3>
            <div id="event_list" class="srel_list srel_noneimg">
                <?php
                $sql = " select ev_id, ev_subject from {$g5['g5_shop_event_table']} order by ev_id desc ";
                $result = sql_query($sql);
                for ($g=0; $row=sql_fetch_array($result); $g++) {
                    if($g == 0)
                        echo '<ul>';
                ?>
                    <li>
                        <input type="hidden" name="ev_id[]" value="<?php echo $row['ev_id']; ?>">
                        <div class="list_item"><?php echo get_text($row['ev_subject']); ?></div>
                        <div class="list_item_btn"><button type="button" class="add_event btn_frmline">추가</button></div>
                    </li>
                <?php
                }

                if($g > 0)
                    echo '</ul>';
                else
                    echo '<p>등록된 이벤트가 없습니다.</p>';
                ?>
            </div>
            <script>
            $(function() {
                $(document).on("click", "#event_list .add_event", function() {
                    // 이미 등록된 이벤트인지 체크
                    var $li = $(this).closest("li");
                    var ev_id = $li.find("input:hidden").val();
                    var ev_id2;
                    var dup = false;
                    $("#reg_event_list input[name='ev_id[]']").each(function() {
                        ev_id2 = $(this).val();
                        if(ev_id == ev_id2) {
                            dup = true;
                            return false;
                        }
                    });

                    if(dup) {
                        alert("이미 선택된 이벤트입니다.");
                        return false;
                    }

                    var cont = "<li>"+$li.html().replace("add_event", "del_event").replace("추가", "삭제")+"</li>";
                    var count = $("#reg_event_list li").length;

                    if(count > 0) {
                        $("#reg_event_list li:last").after(cont);
                    } else {
                        $("#reg_event_list").html("<ul>"+cont+"</ul>");
                    }
                });

                $(document).on("click", "#reg_event_list .del_event", function() {
                    if(!confirm("상품을 삭제하시겠습니까?"))
                        return false;

                    $(this).closest("li").remove();

                    var count = $("#reg_event_list li").length;
                    if(count < 1)
                        $("#reg_event_list").html("<p>선택된 이벤트가 없습니다.</p>");
                });
            });
            </script>
        </section>

        <section class="compare_right">
            <h3>선택된 관련이벤트 목록</h3>
            <div id="reg_event_list" class="srel_sel srel_noneimg">
                <?php
                $str = "";
                $comma = "";
                $sql = " select b.ev_id, b.ev_subject
                           from {$g5['g5_shop_event_item_table']} a
                           left join {$g5['g5_shop_event_table']} b on (a.ev_id=b.ev_id)
                          where a.it_id = '$it_id'
                          order by b.ev_id desc ";
                $result = sql_query($sql);
                for ($g=0; $row=sql_fetch_array($result); $g++) {
                    $str .= $comma . $row['ev_id'];
                    $comma = ",";

                    if($g == 0)
                        echo '<ul>';
                ?>
                    <li>
                        <input type="hidden" name="ev_id[]" value="<?php echo $row['ev_id']; ?>">
                        <div class="list_item"><?php echo get_text($row['ev_subject']); ?></div>
                        <div class="list_item_btn"><button type="button" class="del_event btn_frmline">삭제</button></div>
                    </li>
                <?php
                }

                if($g > 0)
                    echo '</ul>';
                else
                    echo '<p>선택된 이벤트가 없습니다.</p>';
                ?>
            </div>
            <input type="hidden" name="ev_list" value="<?php echo $str; ?>">
        </section>
    </div>

</section>


<section id="anc_sitfrm_optional">
    <h2 class="h2_frm">상세설명설정</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>상세설명설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">상품상단내용</th>
            <td><?php echo help("상품상세설명 페이지 상단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_head_html', get_text(html_purifier($it['it_head_html']), 0)); ?></td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_head_html" value="1" id="chk_ca_it_head_html">
                <label for="chk_ca_it_head_html">분류적용</label>
                <input type="checkbox" name="chk_all_it_head_html" value="1" id="chk_all_it_head_html">
                <label for="chk_all_it_head_html">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row">상품하단내용</th>
            <td><?php echo help("상품상세설명 페이지 하단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_tail_html', get_text(html_purifier($it['it_tail_html']), 0)); ?></td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_tail_html" value="1" id="chk_ca_it_tail_html">
                <label for="chk_ca_it_tail_html">분류적용</label>
                <input type="checkbox" name="chk_all_it_tail_html" value="1" id="chk_all_it_tail_html">
                <label for="chk_all_it_tail_html">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row">모바일 상품상단내용</th>
            <td><?php echo help("모바일 상품상세설명 페이지 상단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_mobile_head_html', get_text(html_purifier($it['it_mobile_head_html']), 0)); ?></td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_mobile_head_html" value="1" id="chk_ca_it_mobile_head_html">
                <label for="chk_ca_it_mobile_head_html">분류적용</label>
                <input type="checkbox" name="chk_all_it_mobile_head_html" value="1" id="chk_all_it_mobile_head_html">
                <label for="chk_all_it_mobile_head_html">전체적용</label>
            </td>
        </tr>
        <tr>
            <th scope="row">모바일 상품하단내용</th>
            <td><?php echo help("모바일 상품상세설명 페이지 하단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_mobile_tail_html', get_text(html_purifier($it['it_mobile_tail_html']), 0)); ?></td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_it_mobile_tail_html" value="1" id="chk_ca_it_mobile_tail_html">
                <label for="chk_ca_it_mobile_tail_html">분류적용</label>
                <input type="checkbox" name="chk_all_it_mobile_tail_html" value="1" id="chk_all_it_mobile_tail_html">
                <label for="chk_all_it_mobile_tail_html">전체적용</label>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_extra">
    <h2>여분필드 설정</h2>
    <?php echo $pg_anchor ?>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <?php for ($i=1; $i<=10; $i++) { ?>
        <tr>
            <th scope="row">여분필드<?php echo $i ?></th>
            <td class="td_extra">
                <label for="it_<?php echo $i ?>_subj">여분필드 <?php echo $i ?> 제목</label>
                <input type="text" name="it_<?php echo $i ?>_subj" id="it_<?php echo $i ?>_subj" value="<?php echo get_text($it['it_'.$i.'_subj']) ?>" class="frm_input">
                <label for="it_<?php echo $i ?>">여분필드 <?php echo $i ?> 값</label>
                <input type="text" name="it_<?php echo $i ?>" value="<?php echo get_text($it['it_'.$i]) ?>" id="it_<?php echo $i ?>" class="frm_input">
            </td>
            <td class="td_grpset">
                <input type="checkbox" name="chk_ca_<?php echo $i ?>" value="1" id="chk_ca_<?php echo $i ?>">
                <label for="chk_ca_<?php echo $i ?>">분류적용</label>
                <input type="checkbox" name="chk_all_<?php echo $i ?>" value="1" id="chk_all_<?php echo $i ?>">
                <label for="chk_all_<?php echo $i ?>">전체적용</label>
            </td>
        </tr>
        <?php } ?>
        <?php if ($w == "u") { ?>
        <tr>
            <th scope="row">입력일시</th>
            <td colspan="2">
                <?php echo help("상품을 처음 입력(등록)한 시간입니다."); ?>
                <?php echo $it['it_time']; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">수정일시</th>
            <td colspan="2">
                <?php echo help("상품을 최종 수정한 시간입니다."); ?>
                <?php echo $it['it_update_time']; ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        </table>
    </div>
</section>

<div class="btn_fixed_top">
    <a href="./itemlist.php?<?php echo $qstr; ?>" class="btn btn_02">목록</a>
    <a href="<?php echo shop_item_url($it_id); ?>" class="btn_02  btn">상품보기</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
</div>
</form>


<script>
var f = document.fitemform;

<?php if ($w == 'u') { ?>
$(".banner_or_img").addClass("sit_wimg");
$(function() {
    $(".sit_wimg_view").bind("click", function() {
        var sit_wimg_id = $(this).attr("id").split("_");
        var $img_display = $("#"+sit_wimg_id[1]);

        $img_display.toggle();

        if($img_display.is(":visible")) {
            $(this).text($(this).text().replace("확인", "닫기"));
        } else {
            $(this).text($(this).text().replace("닫기", "확인"));
        }

        var $img = $("#"+sit_wimg_id[1]).children("img");
        var width = $img.width();
        var height = $img.height();
        if(width > 700) {
            var img_width = 700;
            var img_height = Math.round((img_width * height) / width);

            $img.width(img_width).height(img_height);
        }
    });
    $(".sit_wimg_close").bind("click", function() {
        var $img_display = $(this).parents(".banner_or_img");
        var id = $img_display.attr("id");
        $img_display.toggle();
        var $button = $("#it_"+id+"_view");
        $button.text($button.text().replace("닫기", "확인"));
    });
});
<?php } ?>

function fitemformcheck(f)
{
    // 본사 관리자의 경우 상품 소유 계층 선택 필수 검증
    <?php if ($dmk_auth['mb_type'] == DMK_MB_TYPE_SUPER_ADMIN) { ?>
    var dmkDtId = f.dmk_dt_id ? f.dmk_dt_id.value : '';
    if (!dmkDtId) {
        alert("상품 소유 계층을 선택해주세요. 상품이 총판에 속하는 경우 총판을 선택해야 하며, 상품이 대리점 또는 지점에 속하는 경우 해당 대리점 또는 지점을 선택해주어야 합니다.");
        // 총판 선택박스에 포커스
        var dtSelect = document.querySelector('select[name="dmk_dt_id"]');
        if (dtSelect) {
            dtSelect.focus();
        }
        return false;
    }
    <?php } ?>

    if (!f.ca_id.value) {
        alert("기본분류를 선택하십시오.");
        f.ca_id.focus();
        return false;
    }

    if (f.w.value == "") {
        var error = "";
        $.ajax({
            url: "./ajax.it_id.php",
            type: "POST",
            data: {
                "it_id": f.it_id.value
            },
            dataType: "json",
            async: false,
            cache: false,
            success: function(data, textStatus) {
                error = data.error;
            }
        });

        if (error) {
            alert(error);
            return false;
        }
    }

    if(f.it_point_type.value == "1" || f.it_point_type.value == "2") {
        var point = parseInt(f.it_point.value);
        if(point < 0 || point > 99) {
            alert("포인트 비율을 0과 99 사이의 값으로 입력해 주십시오.");
            f.it_point.focus();
            f.it_point.select();
            return false;
        }
    }

    if(parseInt(f.it_sc_type.value) > 1) {
        if(!f.it_sc_price.value || f.it_sc_price.value == "0") {
            alert("기본배송비를 입력해 주십시오.");
            return false;
        }

        if(f.it_sc_type.value == "2" && (!f.it_sc_minimum.value || f.it_sc_minimum.value == "0")) {
            alert("배송비 상세조건의 주문금액을 입력해 주십시오.");
            return false;
        }

        if(f.it_sc_type.value == "4" && (!f.it_sc_qty.value || f.it_sc_qty.value == "0")) {
            alert("배송비 상세조건의 주문수량을 입력해 주십시오.");
            return false;
        }
    }

    // 관련상품처리
    var item = new Array();
    var re_item = it_id = "";

    $("#reg_relation input[name='re_it_id[]']").each(function() {
        it_id = $(this).val();
        if(it_id == "")
            return true;

        item.push(it_id);
    });

    if(item.length > 0)
        re_item = item.join();

    $("input[name=it_list]").val(re_item);

    // 이벤트처리
    var evnt = new Array();
    var ev = ev_id = "";

    $("#reg_event_list input[name='ev_id[]']").each(function() {
        ev_id = $(this).val();
        if(ev_id == "")
            return true;

        evnt.push(ev_id);
    });

    if(evnt.length > 0)
        ev = evnt.join();

    $("input[name=ev_list]").val(ev);

    <?php echo get_editor_js('it_explan'); ?>
    <?php echo get_editor_js('it_mobile_explan'); ?>
    <?php echo get_editor_js('it_head_html'); ?>
    <?php echo get_editor_js('it_tail_html'); ?>
    <?php echo get_editor_js('it_mobile_head_html'); ?>
    <?php echo get_editor_js('it_mobile_tail_html'); ?>

    return true;
}

function categorychange(f)
{
    var idx = f.ca_id.value;

    if (f.w.value == "" && idx)
    {
        f.it_use.checked = ca_use[idx] ? true : false;
        f.it_stock_qty.value = ca_stock_qty[idx];
        f.it_sell_email.value = ca_sell_email[idx];
    }
}

categorychange(document.fitemform);

// 도매까 지점별 재고 관리 JavaScript 함수들
function toggleBranchStock() {
    var section = document.getElementById('branch-stock-section');
    var text = document.getElementById('branch-stock-toggle-text');
    
    if (section.style.display === 'none') {
        section.style.display = 'table-row';
        text.textContent = '접기';
        loadAvailableBranches();
    } else {
        section.style.display = 'none';
        text.textContent = '펼치기';
    }
}

function loadAvailableBranches() {
    var select = document.getElementById('new-branch-select');
    
    // AJAX로 사용 가능한 지점 목록 가져오기
    var xhr = new XMLHttpRequest();
    xhr.open('POST', './ajax_get_owners.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            
            // 기존 옵션 제거 (첫 번째 옵션 제외)
            select.innerHTML = '<option value="">지점을 선택하세요</option>';
            
            if (response.success) {
                response.data.forEach(function(branch) {
                    var option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name + ' (' + branch.id + ')';
                    select.appendChild(option);
                });
            }
        }
    };
    
    xhr.send('owner_type=3'); // 지점 타입
}

function addBranchStock() {
    var select = document.getElementById('new-branch-select');
    var branchId = select.value;
    var branchName = select.options[select.selectedIndex].text;
    
    if (!branchId) {
        alert('지점을 선택해주세요.');
        return;
    }
    
    // 이미 추가된 지점인지 확인
    var existingInputs = document.querySelectorAll('input[name*="[br_id]"]');
    for (var i = 0; i < existingInputs.length; i++) {
        if (existingInputs[i].value === branchId) {
            alert('이미 추가된 지점입니다.');
            return;
        }
    }
    
    var list = document.getElementById('branch-stock-list');
    var index = list.children.length - 1; // 헤더 제외
    
    var item = document.createElement('div');
    item.className = 'branch-stock-item';
    item.style.cssText = 'display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:10px; margin-bottom:5px; padding:10px; background:#ffffff; border:1px solid #dee2e6; border-radius:5px;';
    
    item.innerHTML = `
        <div>
            <strong>${branchName}</strong>
            <input type="hidden" name="branch_stocks[${index}][br_id]" value="${branchId}">
        </div>
        <div>
            <input type="number" name="branch_stocks[${index}][stock_qty]" 
                   value="0" min="0" class="frm_input" style="width:80px;" onchange="calculateTotalStock()"> 개
        </div>
        <div>
            <input type="number" name="branch_stocks[${index}][safe_qty]" 
                   value="0" min="0" class="frm_input" style="width:80px;"> 개
        </div>
        <div>
            <button type="button" onclick="removeBranchStock(this)" style="padding:5px 10px; background:#dc3545; color:white; border:none; border-radius:3px; cursor:pointer;">삭제</button>
        </div>
    `;
    
    list.appendChild(item);
    select.value = '';
    calculateTotalStock();
}

function removeBranchStock(button) {
    button.closest('.branch-stock-item').remove();
    calculateTotalStock();
}

function calculateTotalStock() {
    var stockInputs = document.querySelectorAll('input[name*="[stock_qty]"]');
    var total = 0;
    
    stockInputs.forEach(function(input) {
        total += parseInt(input.value) || 0;
    });
    
    var totalDisplay = document.getElementById('total-branch-stock');
    if (totalDisplay) {
        totalDisplay.textContent = total;
    }
}

function syncTotalStock() {
    var totalBranchStock = document.getElementById('total-branch-stock').textContent;
    var mainStockInput = document.getElementById('it_stock_qty');
    
    if (confirm('지점별 재고 합계(' + totalBranchStock + '개)를 상단 재고수량에 반영하시겠습니까?')) {
        mainStockInput.value = totalBranchStock;
    }
}

// 유효기간 초기화 함수
function clearValidDates() {
    document.getElementById('dmk_it_valid_start_date').value = '';
    document.getElementById('dmk_it_valid_end_date').value = '';
    alert('유효기간이 초기화되었습니다.');
}

// Legacy JavaScript for deprecated owner fields removed
// Now using dmk_render_chain_select for hierarchy selection

// jQuery UI 로드 확인 및 Datepicker 초기화
$(document).ready(function() {
    // jQuery UI가 로드되어 있는지 확인
    if (typeof $.datepicker === 'undefined') {
        console.log('jQuery UI Datepicker가 로드되지 않았습니다. CDN에서 로드합니다.');
        
        // jQuery UI CSS 로드
        $('<link>').attr({
            rel: 'stylesheet',
            type: 'text/css',
            href: 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css'
        }).appendTo('head');
        
        // jQuery UI JS 로드
        $.getScript('https://code.jquery.com/ui/1.12.1/jquery-ui.min.js', function() {
            console.log('jQuery UI가 성공적으로 로드되었습니다.');
            initializeDatepickers();
        });
    } else {
        console.log('jQuery UI Datepicker가 이미 로드되어 있습니다.');
        initializeDatepickers();
    }
});

function initializeDatepickers() {
    // 한국어 설정
    $.datepicker.setDefaults({
        closeText: '닫기',
        prevText: '이전달',
        nextText: '다음달',
        currentText: '오늘',
        monthNames: ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
        monthNamesShort: ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
        dayNames: ['일','월','화','수','목','금','토'],
        dayNamesShort: ['일','월','화','수','목','금','토'],
        dayNamesMin: ['일','월','화','수','목','금','토'],
        weekHeader: 'Wk',
        dateFormat: 'yy-mm-dd',
        firstDay: 0,
        isRTL: false,
        showMonthAfterYear: true,
        yearSuffix: '년'
    });
    
    // 시작일 datepicker
    $('#dmk_it_valid_start_date').datepicker({
        onSelect: function(selectedDate) {
            // 시작일이 선택되면 종료일의 최소값을 시작일로 설정
            $('#dmk_it_valid_end_date').datepicker('option', 'minDate', selectedDate);
        }
    });
    
    // 종료일 datepicker
    $('#dmk_it_valid_end_date').datepicker({
        onSelect: function(selectedDate) {
            // 종료일이 선택되면 시작일의 최대값을 종료일로 설정
            $('#dmk_it_valid_start_date').datepicker('option', 'maxDate', selectedDate);
        }
    });
    
    // 기존에 값이 있으면 범위 설정
    var startDate = $('#dmk_it_valid_start_date').val();
    var endDate = $('#dmk_it_valid_end_date').val();
    
    if (startDate) {
        $('#dmk_it_valid_end_date').datepicker('option', 'minDate', startDate);
    }
    if (endDate) {
        $('#dmk_it_valid_start_date').datepicker('option', 'maxDate', endDate);
    }
    
    // 유효일자 버튼 클릭 이벤트 핸들러
    $('#dmk_it_valid_start_date_btn, #dmk_it_valid_end_date_btn').click(function() {
        var targetInputId = $(this).attr('id').replace('_btn', '');
        $('#' + targetInputId).datepicker('show');
    });
    
    // 계층 선택 시 분류 업데이트 함수
    function updateCategoriesByHierarchy() {
        var dmk_dt_id = $('select[name="dmk_dt_id"]').val() || $('input[name="dmk_dt_id"]').val() || '';
        var dmk_ag_id = $('select[name="dmk_ag_id"]').val() || $('input[name="dmk_ag_id"]').val() || '';
        var dmk_br_id = $('select[name="dmk_br_id"]').val() || $('input[name="dmk_br_id"]').val() || '';
        
        // 사용안함
    }
    
    // 계층 선택박스 변경 이벤트 핸들러
    $(document).on('change', 'select[name="dmk_dt_id"], select[name="dmk_ag_id"], select[name="dmk_br_id"]', function() {
        updateCategoriesByHierarchy();
    });
    
    // 페이지 로드 시 초기 분류 업데이트 (값이 이미 선택되어 있는 경우)
    var initialDtId = $('select[name="dmk_dt_id"]').val() || $('input[name="dmk_dt_id"]').val();
    var initialAgId = $('select[name="dmk_ag_id"]').val() || $('input[name="dmk_ag_id"]').val();
    var initialBrId = $('select[name="dmk_br_id"]').val() || $('input[name="dmk_br_id"]').val();
    
    if (initialDtId || initialAgId || initialBrId) {
        updateCategoriesByHierarchy();
    }
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');