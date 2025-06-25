<?php
$sub_menu = '400200';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

auth_check_menu($auth, $sub_menu, "r");

// 도매까 권한 확인 - 총판 관리자만 분류 관리 가능
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] > 1) {
    alert('분류관리는 총판 관리자만 접근할 수 있습니다.', G5_ADMIN_URL);
}

// JavaScript에서 사용할 PHP 상수 정의
define('DMK_MB_TYPE_SUPER_ADMIN', 1);
define('DMK_MB_TYPE_DISTRIBUTOR', 2);
define('DMK_MB_TYPE_AGENCY', 3);
define('DMK_MB_TYPE_BRANCH', 4);

// DMK_OWNER_TYPE 관련 상수는 admin.auth.lib.php에 정의되어 있을 것으로 예상되므로, 여기서는 직접 정의하지 않고 사용합니다.

$dmk_js_consts = [
    'DMK_OWNER_TYPE_DISTRIBUTOR' => DMK_OWNER_TYPE_DISTRIBUTOR,
    'DMK_OWNER_TYPE_AGENCY' => DMK_OWNER_TYPE_AGENCY,
    'DMK_OWNER_TYPE_BRANCH' => DMK_OWNER_TYPE_BRANCH,
    'DMK_MB_TYPE_SUPER_ADMIN' => DMK_MB_TYPE_SUPER_ADMIN,
    'DMK_MB_TYPE_DISTRIBUTOR' => DMK_MB_TYPE_DISTRIBUTOR,
    'DMK_MB_TYPE_AGENCY' => DMK_MB_TYPE_AGENCY,
    'DMK_MB_TYPE_BRANCH' => DMK_MB_TYPE_BRANCH,
];

$g5['title'] = '분류관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 계층 필터링을 위한 변수 초기화
$selected_dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
$selected_ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
$selected_br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';

$display_dt_select = false;
$display_ag_select = false;
$display_br_select = false;

$distributors = [];
$agencies = [];
$branches = [];

// 현재 로그인한 관리자 유형에 따른 선택 박스 표시 및 초기 데이터 설정
if ($dmk_auth['is_super']) {
    $display_dt_select = true;
    $display_ag_select = true;
    $display_br_select = true;
    $distributors = dmk_get_distributors();

    // 선택된 총판이 있으면 해당 대리점 목록 로드
    if ($selected_dt_id) {
        $agencies = dmk_get_agencies($selected_dt_id);
    }
    // 선택된 대리점이 있으면 해당 지점 목록 로드
    if ($selected_ag_id) {
        $branches = dmk_get_branches($selected_ag_id);
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    // 총판 관리자는 대리점 및 지점 선택 가능
    $display_ag_select = true;
    $display_br_select = true;
    // 자신의 총판 ID를 필터링에 기본 적용
    $selected_dt_id = $dmk_auth['mb_id']; 
    $distributors = [['mb_id' => $dmk_auth['mb_id'], 'mb_name' => $dmk_auth['mb_name']]]; // 자신의 총판만 목록에 표시

    // 자신의 총판에 속한 대리점 목록 로드 (선택된 대리점이 없거나 선택된 총판이 자신인 경우)
    $agencies = dmk_get_agencies($dmk_auth['mb_id']);

    // 선택된 대리점이 있으면 해당 지점 목록 로드
    if ($selected_ag_id) {
        $branches = dmk_get_branches($selected_ag_id);
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    // 대리점 관리자는 지점만 선택 가능
    $display_br_select = true;
    // 자신의 대리점 ID를 필터링에 기본 적용
    $selected_ag_id = $dmk_auth['ag_id'];
    $selected_dt_id = dmk_get_agency_distributor_id($dmk_auth['ag_id']); // 상위 총판 ID 설정 (조회용)
    $agencies = [['ag_id' => $dmk_auth['ag_id'], 'ag_name' => $dmk_auth['ag_name']]]; // 자신의 대리점만 목록에 표시

    // 자신의 대리점에 속한 지점 목록 로드
    $branches = dmk_get_branches($dmk_auth['ag_id']);
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
    // 지점 관리자는 선택박스 없이 자신의 정보로 고정
    $selected_br_id = $dmk_auth['br_id'];
    $selected_ag_id = dmk_get_branch_agency_id($dmk_auth['br_id']); // 상위 대리점 ID 설정 (조회용)
    $selected_dt_id = dmk_get_agency_distributor_id($selected_ag_id); // 상위 총판 ID 설정 (조회용)
}

$where = " where ";
$sql_search = "";

$sfl = in_array($sfl, array('ca_name', 'ca_id', 'ca_mb_id')) ? $sfl : '';

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if (isset($save_stx) && $save_stx && ($save_stx != $stx))
        $page = 1;
}

// 계층 필터링 조건 추가
$hierarchy_where = dmk_get_category_where_condition($selected_dt_id, $selected_ag_id, $selected_br_id);
if ($hierarchy_where) {
    $sql_search .= " $where 1=1 $hierarchy_where ";
    $where = " and ";
}

$sql_common = " from {$g5['g5_shop_category_table']} ";
if ($is_admin != 'super' && !$dmk_auth['is_super'])
    $sql_search .= " $where ca_mb_id = '{$member['mb_id']}' ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

if (!$sst)
{
    $sst  = "ca_id";
    $sod = "asc";
}
$sql_order = "order by $sst $sod";

// 출력할 레코드를 얻음
$sql  = " select *
             $sql_common
             $sql_order
             limit $from_record, $rows ";
$result = sql_query($sql);

// 결과를 $list 배열에 저장
$list = array();
while ($row = sql_fetch_array($result)) {
    $list[] = $row;
}

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">생성된  분류 수</span><span class="ov_num">  <?php echo number_format($total_count); ?>개</span></span>
</div>

<!-- 도매까 계층 필터링 추가 -->
<?php if ($display_dt_select || $display_ag_select || $display_br_select) { ?>
<form name="fhierarchy" class="local_sch01 local_sch" method="get">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">

<?php if ($display_dt_select) { ?>
<label for="dt_id" class="sound_only">총판 선택</label>
<select name="dt_id" id="dt_id">
    <option value="">- 총판 선택 -</option>
    <?php foreach ($distributors as $dt) { ?>
    <option value="<?php echo $dt['mb_id']; ?>" <?php echo get_selected($selected_dt_id, $dt['mb_id']); ?>><?php echo $dt['mb_name']; ?></option>
    <?php } ?>
</select>
<?php } ?>

<?php if ($display_ag_select) { ?>
<label for="ag_id" class="sound_only">대리점 선택</label>
<select name="ag_id" id="ag_id">
    <option value="">- 대리점 선택 -</option>
    <?php foreach ($agencies as $ag) { ?>
    <option value="<?php echo $ag['ag_id']; ?>" <?php echo get_selected($selected_ag_id, $ag['ag_id']); ?>><?php echo $ag['ag_name']; ?></option>
    <?php } ?>
</select>
<?php } ?>

<?php if ($display_br_select) { ?>
<label for="br_id" class="sound_only">지점 선택</label>
<select name="br_id" id="br_id">
    <option value="">- 지점 선택 -</option>
    <?php foreach ($branches as $br) { ?>
    <option value="<?php echo $br['br_id']; ?>" <?php echo get_selected($selected_br_id, $br['br_id']); ?>><?php echo $br['br_name']; ?></option>
    <?php } ?>
</select>
<?php } ?>


</form>
<?php } ?>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
<input type="hidden" name="dt_id" value="<?php echo $selected_dt_id; ?>">
<input type="hidden" name="ag_id" value="<?php echo $selected_ag_id; ?>">
<input type="hidden" name="br_id" value="<?php echo $selected_br_id; ?>">

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="ca_name"<?php echo get_selected($sfl, "ca_name", true); ?>>분류명</option>
    <option value="ca_id"<?php echo get_selected($sfl, "ca_id", true); ?>>분류코드</option>
    <option value="ca_mb_id"<?php echo get_selected($sfl, "ca_mb_id", true); ?>>회원아이디</option>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="required frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>

<form name="fcategorylist" method="post" action="./categorylistupdate.php" autocomplete="off">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="dt_id" value="<?php echo $selected_dt_id; ?>">
<input type="hidden" name="ag_id" value="<?php echo $selected_ag_id; ?>">
<input type="hidden" name="br_id" value="<?php echo $selected_br_id; ?>">

<div id="sct" class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" rowspan="2"><?php echo subject_sort_link("ca_id"); ?>분류코드</a></th>
        <th scope="col" id="sct_cate"><?php echo subject_sort_link("ca_name"); ?>분류명</a></th>
        <th scope="col" id="sct_amount">상품수</th>
        <th scope="col" id="sct_owner" rowspan="2">계층 정보</th>
        <th scope="col" id="sct_hpcert">본인인증</th>
        <th scope="col" id="sct_imgw">이미지 폭</th>
        <th scope="col" id="sct_imgcol">1행이미지수</th>
        <th scope="col" id="sct_mobileimg">모바일<br>1행이미지수</th>
        <th scope="col" id="sct_pcskin">PC스킨지정</th>
        <th scope="col" rowspan="2">관리</th>
    </tr>
    <tr>
        <th scope="col" id="sct_admin"><?php echo subject_sort_link("ca_mb_id"); ?>관리회원아이디</a></th>
        <th scope="col" id="sct_sell"><?php echo subject_sort_link("ca_use"); ?>판매가능</a></th>
        <th scope="col" id="sct_adultcert">성인인증</th>
        <th scope="col" id="sct_imgh">이미지 높이</th>
        <th scope="col" id="sct_imgrow">이미지 행수</th>
        <th scope="col" id="sct_mobilerow">모바일<br>이미지 행수</th>
        <th scope="col" id="sct_mskin">모바일스킨지정</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $s_add = $s_vie = $s_upd = $s_del = '';
    for ($i=0; $i<count($list); $i++)
    {
        $row = $list[$i];
        $level = strlen($row['ca_id']) / 2 - 1;
        $p_ca_name = '';

        if ($level > 0) {
            $class = 'class="name_lbl"'; // 2단 이상 분류의 label 에 스타일 부여
            // 상위단계의 분류명
            $p_ca_id = substr($row['ca_id'], 0, $level*2);
            $sql = " select ca_name from {$g5['g5_shop_category_table']} where ca_id = '$p_ca_id' ";
            $temp = sql_fetch($sql);
            $p_ca_name = $temp['ca_name'].'의하위';
        } else {
            $class = '';
        }

        $s_level = '<div><label for="ca_name_'.($i).'" '.$class.'><span class="sound_only">'.$p_ca_name.''.($level+1).'단 분류</span></label></div>';
        $s_level_input_size = 25 - $level *2; // 하위 분류일 수록 입력칸 넓이 작아짐

        if ($level+2 < 6) $s_add = '<a href="./categoryform.php?ca_id='.$row['ca_id'].'&amp;'.$qstr.'" class="btn btn_03">추가</a> '; // 분류는 5단계까지만 가능
        else $s_add = '';
        $s_upd = '<a href="./categoryform.php?w=u&amp;ca_id='.$row['ca_id'].'&amp;'.$qstr.'" class="btn btn_02"><span class="sound_only">'.get_text($row['ca_name']).' </span>수정</a> ';

        if ($dmk_auth['is_super'] || dmk_can_modify_category($row['ca_id']))
            $s_del = '<a href="./categoryformupdate.php?w=d&amp;ca_id='.$row['ca_id'].'&amp;'.$qstr.'" onclick="return delete_confirm(this);" class="btn btn_02"><span class="sound_only">'.get_text($row['ca_name']).' </span>삭제</a> ';

        // 해당 분류에 속한 상품의 수
        $sql1 = " select COUNT(*) as cnt from {$g5['g5_shop_item_table']}
                      where ca_id = '{$row['ca_id']}'
                      or ca_id2 = '{$row['ca_id']}'
                      or ca_id3 = '{$row['ca_id']}' ";
        $row1 = sql_fetch($sql1);

        // 계층 정보 표시
        $owner_info_display = '';
        if ($row['dmk_ca_owner_type'] && $row['dmk_ca_owner_id']) {
            switch ($row['dmk_ca_owner_type']) {
                case DMK_OWNER_TYPE_DISTRIBUTOR:
                    $owner_info_display = "총판<br>({$row['dmk_ca_owner_id']})";
                    break;
                case DMK_OWNER_TYPE_AGENCY:
                    $owner_info_display = "대리점<br>({$row['dmk_ca_owner_id']})";
                    break;
                case DMK_OWNER_TYPE_BRANCH:
                    $owner_info_display = "지점<br>({$row['dmk_ca_owner_id']})";
                    break;
                default:
                    $owner_info_display = "{$row['dmk_ca_owner_type']}: {$row['dmk_ca_owner_id']}";
                    break;
            }
        } else if ($row['dmk_ca_owner_type']) {
            $owner_info_display = $row['dmk_ca_owner_type'];
        } else {
            $owner_info_display = '-';
        }

        // 스킨 Path
        if(!$row['ca_skin_dir'])
            $g5_shop_skin_path = G5_SHOP_SKIN_PATH;
        else {
            if(preg_match('#^theme/(.+)$#', $row['ca_skin_dir'], $match))
                $g5_shop_skin_path = G5_THEME_PATH.'/'.G5_SKIN_DIR.'/shop/'.$match[1];
            else
                $g5_shop_skin_path  = G5_PATH.'/'.G5_SKIN_DIR.'/shop/'.$row['ca_skin_dir'];
        }

        if(!$row['ca_mobile_skin_dir'])
            $g5_mshop_skin_path = G5_MSHOP_SKIN_PATH;
        else {
            if(preg_match('#^theme/(.+)$#', $row['ca_mobile_skin_dir'], $match))
                $g5_mshop_skin_path = G5_THEME_MOBILE_PATH.'/'.G5_SKIN_DIR.'/shop/'.$match[1];
            else
                $g5_mshop_skin_path = G5_MOBILE_PATH.'/'.G5_SKIN_DIR.'/shop/'.$row['ca_mobile_skin_dir'];
        }

        $bg = 'bg'.($i%2);
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_code" rowspan="2">
            <input type="hidden" name="ca_id[<?php echo $i; ?>]" value="<?php echo $row['ca_id']; ?>">
            <a href="<?php echo shop_category_url($row['ca_id']); ?>"><?php echo $row['ca_id']; ?></a>
        </td>
        <td headers="sct_cate" class="sct_name<?php echo $level; ?>"><?php echo $s_level; ?> <input type="text" name="ca_name[<?php echo $i; ?>]" value="<?php echo get_text($row['ca_name']); ?>" id="ca_name_<?php echo $i; ?>" required class="tbl_input full_input required"></td>
        <td headers="sct_amount" class="td_amount"><a href="./itemlist.php?sca=<?php echo $row['ca_id']; ?>"><?php echo $row1['cnt']; ?></a></td>
        <td headers="sct_owner" class="td_owner" rowspan="2"><?php echo $owner_info_display; ?></td>
        <td headers="sct_hpcert" class="td_possible">
            <input type="checkbox" name="ca_cert_use[<?php echo $i; ?>]" value="1" id="ca_cert_use_yes<?php echo $i; ?>" <?php if($row['ca_cert_use']) echo 'checked="checked"'; ?>>
            <label for="ca_cert_use_yes<?php echo $i; ?>">사용</label>
        </td>
        <td headers="sct_imgw">
            <label for="ca_out_width<?php echo $i; ?>" class="sound_only">출력이미지 폭</label>
            <input type="text" name="ca_img_width[<?php echo $i; ?>]" value="<?php echo get_text($row['ca_img_width']); ?>" id="ca_out_width<?php echo $i; ?>" required class="required tbl_input" size="3" > <span class="sound_only">픽셀</span>
        </td>
        
        <td headers="sct_imgcol">
            <label for="ca_lineimg_num<?php echo $i; ?>" class="sound_only">1줄당 이미지 수</label>
            <input type="text" name="ca_list_mod[<?php echo $i; ?>]" size="3" value="<?php echo $row['ca_list_mod']; ?>" id="ca_lineimg_num<?php echo $i; ?>" required class="required tbl_input"> <span class="sound_only">개</span>
        </td>
        <td headers="sct_mobileimg">
            <label for="ca_mobileimg_num<?php echo $i; ?>" class="sound_only">모바일 1줄당 이미지 수</label>
            <input type="text" name="ca_mobile_list_mod[<?php echo $i; ?>]" size="3" value="<?php echo $row['ca_mobile_list_mod']; ?>" id="ca_mobileimg_num<?php echo $i; ?>" required class="required tbl_input"> <span class="sound_only">개</span>
        </td>
        <td headers="sct_pcskin" class="sct_pcskin">
            <label for="ca_skin_dir<?php echo $i; ?>" class="sound_only">PC스킨폴더</label>
            <?php echo get_skin_select('shop', 'ca_skin_dir'.$i, 'ca_skin_dir['.$i.']', $row['ca_skin_dir'], 'class="skin_dir"'); ?>
            <label for="ca_skin<?php echo $i; ?>" class="sound_only">PC스킨파일</label>
            <select id="ca_skin<?php echo $i; ?>" name="ca_skin[<?php echo $i; ?>]" required class="required">
                <?php echo get_list_skin_options("^list.[0-9]+\.skin\.php", $g5_shop_skin_path, $row['ca_skin']); ?>
            </select>
        </td>
        <td class="td_mng td_mng_s" rowspan="2">
            <?php echo $s_add; ?>
            <?php echo $s_vie; ?>
            <?php echo $s_upd; ?>
            <?php echo $s_del; ?>
        </td>
    </tr>
    <tr class="<?php echo $bg; ?>">
        <td headers="sct_admin">
            <?php if ($is_admin == 'super' || dmk_can_modify_category($row['ca_id'])) {?>
            <label for="ca_mb_id<?php echo $i; ?>" class="sound_only">관리회원아이디</label>
            <input type="text" name="ca_mb_id[<?php echo $i; ?>]" value="<?php echo $row['ca_mb_id']; ?>" id="ca_mb_id<?php echo $i; ?>" class="tbl_input full_input" size="15" maxlength="20">
            <?php } else { ?>
            <input type="hidden" name="ca_mb_id[<?php echo $i; ?>]" value="<?php echo $row['ca_mb_id']; ?>">
            <?php echo $row['ca_mb_id']; ?>
            <?php } ?>
        </td>
        <td headers="sct_sell" class="td_possible">
            <input type="checkbox" name="ca_use[<?php echo $i; ?>]" value="1" id="ca_use<?php echo $i; ?>" <?php echo ($row['ca_use'] ? "checked" : ""); ?>>
            <label for="ca_use<?php echo $i; ?>">판매</label>
        </td>

        <td headers="sct_adultcert" class="td_possible">
            <input type="checkbox" name="ca_adult_use[<?php echo $i; ?>]" value="1" id="ca_adult_use_yes<?php echo $i; ?>" <?php if($row['ca_adult_use']) echo 'checked="checked"'; ?>>
            <label for="ca_adult_use_yes<?php echo $i; ?>">사용</label>
        </td>
        <td headers="sct_imgh">
            <label for="ca_img_height<?php echo $i; ?>" class="sound_only">출력이미지 높이</label>
            <input type="text" name="ca_img_height[<?php echo $i; ?>]" value="<?php echo $row['ca_img_height']; ?>" id="ca_img_height<?php echo $i; ?>" required class="required tbl_input" size="3" > <span class="sound_only">픽셀</span>
        </td>
        <td headers="sct_imgrow">
            <label for="ca_imgline_num<?php echo $i; ?>" class="sound_only">이미지 줄 수</label>
            <input type="text" name="ca_list_row[<?php echo $i; ?>]" value='<?php echo $row['ca_list_row']; ?>' id="ca_imgline_num<?php echo $i; ?>" required class="required tbl_input" size="3"> <span class="sound_only">줄</span>
        </td>
        <td headers="sct_mobilerow">
            <label for="ca_mobileimg_row<?php echo $i; ?>" class="sound_only">모바일 이미지 줄 수</label>
            <input type="text" name="ca_mobile_list_row[<?php echo $i; ?>]" value='<?php echo $row['ca_mobile_list_row']; ?>' id="ca_mobileimg_row<?php echo $i; ?>" required class="required tbl_input" size="3">
        </td>
        <td headers="sct_mskin"  class="sct_mskin">
            <label for="ca_mobile_skin_dir<?php echo $i; ?>" class="sound_only">모바일스킨폴더</label>
            <?php echo get_mobile_skin_select('shop', 'ca_mobile_skin_dir'.$i, 'ca_mobile_skin_dir['.$i.']', $row['ca_mobile_skin_dir'], 'class="skin_dir"'); ?>
            <label for="ca_mobile_skin<?php echo $i; ?>" class="sound_only">모바일스킨파일</label>
            <select id="ca_mobile_skin<?php echo $i; ?>" name="ca_mobile_skin[<?php echo $i; ?>]" required class="required">
                <?php echo get_list_skin_options("^list.[0-9]+\.skin\.php", $g5_mshop_skin_path, $row['ca_mobile_skin']); ?>
            </select>
        </td>
    </tr>
    <?php }
    if ($i == 0) echo "<tr><td colspan=\"10\" class=\"empty_table\">자료가 한 건도 없습니다.</td></tr>\n";
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="일괄수정" class="btn_02 btn">

    <?php if ($is_admin == 'super' || $dmk_auth['is_super']) {?>
    <a href="./categoryform.php" id="cate_add" class="btn btn_01">분류 추가</a>
    <?php } ?>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
jQuery(document).ready(function($) {
    var dmkAuth = <?php echo json_encode($dmk_auth); ?>;
    var selectedDtId = '<?php echo $selected_dt_id; ?>';
    var selectedAgId = '<?php echo $selected_ag_id; ?>';
    var selectedBrId = '<?php echo $selected_br_id; ?>';

    var dmkJsConsts = <?php echo json_encode($dmk_js_consts); ?>;

    var $dtSelect = $('#dt_id');
    var $agSelect = $('#ag_id');
    var $brSelect = $('#br_id');

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
                        populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                        $('form[name="fhierarchy"]').submit(); // 대리점 로드 후 폼 자동 제출
                    }
                },
                error: function(xhr, status, error) {
                    alert('대리점 목록을 가져오는 중 네트워크 오류가 발생했습니다.');
                }
            });
        } else {
            $('form[name="fhierarchy"]').submit(); // 총판 선택 해제 시 폼 자동 제출
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
                        populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                        $('form[name="fhierarchy"]').submit(); // 지점 로드 후 폼 자동 제출
                    }
                },
                error: function(xhr, status, error) {
                    alert('지점 목록을 가져오는 중 네트워크 오류가 발생했습니다.');
                }
            });
        } else {
            $('form[name="fhierarchy"]').submit(); // 대리점 선택 해제 시 폼 자동 제출
        }
    });

    // 지점 선택 변경 시
    $brSelect.on('change', function() {
        $('form[name="fhierarchy"]').submit(); // 지점 선택 변경 시 폼 자동 제출
    });

    // 페이지 로드 시 초기값 설정 및 비활성화 처리
    if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_DISTRIBUTOR) {
        $dtSelect.prop('disabled', true);
        if (selectedDtId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: selectedDtId },
                success: function(data) {
                    populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                    if (selectedAgId) {
                         $.ajax({
                            url: './ajax.get_dmk_owner_ids.php',
                            type: 'GET',
                            dataType: 'json',
                            data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                            success: function(data) {
                                populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                            }
                        });
                    }
                }
            });
        }
    } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_AGENCY) {
        $dtSelect.prop('disabled', true);
        $agSelect.prop('disabled', true);
        if (selectedAgId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                success: function(data) {
                    populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                }
            });
        }
    } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_BRANCH) {
        $dtSelect.prop('disabled', true);
        $agSelect.prop('disabled', true);
        $brSelect.prop('disabled', true);
    } else { // Super Admin
        if (selectedDtId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: selectedDtId },
                success: function(data) {
                    populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                    if (selectedAgId) {
                        $.ajax({
                            url: './ajax.get_dmk_owner_ids.php',
                            type: 'GET',
                            dataType: 'json',
                            data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                            success: function(data) {
                                populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                            }
                        });
                    }
                }
            });
        }
    }

    // 스킨 선택 기능 (원본 영카트 기능 유지)
    $("select.skin_dir").on("change", function() {
        var type = "";
        var dir = $(this).val();
        if(!dir)
            return false;

        var id = $(this).attr("id");
        var $sel = $(this).siblings("select");
        var sval = $sel.find("option:selected").val();

        if(id.search("mobile") > -1)
            type = "mobile";

        $sel.load(
            "./ajax.skinfile.php",
            { dir : dir, type : type, sval: sval }
        );
    });
});
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?> 