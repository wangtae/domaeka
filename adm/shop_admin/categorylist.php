<?php
$sub_menu = '400200';
include_once('./_common.php');

// DMK 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
include_once(G5_PATH.'/dmk/adm/lib/chain-select.lib.php');

// 도매까 권한 확인 - DMK 설정에 따른 메뉴 접근 권한 체크
include_once(G5_PATH.'/dmk/dmk_global_settings.php');
$dmk_auth = dmk_get_admin_auth();
$user_type = dmk_get_current_user_type();

// DMK main 관리자는 DMK 설정에 정의된 메뉴에 최고관리자처럼 접근 가능
if ($dmk_auth && $dmk_auth['admin_type'] === 'main' && dmk_is_menu_allowed('400200', $user_type)) {
    // DMK main 관리자는 auth_check_menu 우회
} else {
    // 일반 관리자는 기존 권한 체크 수행
    auth_check_menu($auth, $sub_menu, "r");
}

// 추가 DMK 권한 체크
if (!dmk_is_menu_allowed('400200', $user_type)) {
    alert('분류관리에 접근 권한이 없습니다.', G5_ADMIN_URL);
}

// 계층별 필터링 파라미터 처리
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$g5['title'] = '분류관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 체인 선택박스 에셋 포함
echo dmk_include_chain_select_assets();

$where = " where ";
$sql_search = "";

$sfl = in_array($sfl, array('ca_name', 'ca_id', 'ca_mb_id')) ? $sfl : '';

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if ($save_stx && ($save_stx != $stx))
        $page = 1;
}

// 계층별 필터링 추가 (새로운 필드 구조 사용)
if (!$dmk_auth['is_super']) {
    // 일반 관리자는 자신의 계층에 속한 분류만 볼 수 있음
    $member_hierarchy = array();
    if ($member['dmk_dt_id']) $member_hierarchy[] = "dmk_dt_id = '".sql_escape_string($member['dmk_dt_id'])."'";
    if ($member['dmk_ag_id']) $member_hierarchy[] = "dmk_ag_id = '".sql_escape_string($member['dmk_ag_id'])."'";
    if ($member['dmk_br_id']) $member_hierarchy[] = "dmk_br_id = '".sql_escape_string($member['dmk_br_id'])."'";
    
    if (!empty($member_hierarchy)) {
        $sql_search .= " $where (" . implode(" AND ", $member_hierarchy) . ") ";
        $where = " and ";
    }
} else {
    // 본사 관리자는 계층 선택에 따른 필터링
    if ($sbr_id) {
        // 지점이 선택된 경우 해당 지점의 분류만
        $br_info = sql_fetch("SELECT dmk_dt_id, dmk_ag_id, dmk_br_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sbr_id)."'");
        if ($br_info) {
            $sql_search .= " $where dmk_dt_id = '".sql_escape_string($br_info['dmk_dt_id'])."' 
                             AND dmk_ag_id = '".sql_escape_string($br_info['dmk_ag_id'])."' 
                             AND dmk_br_id = '".sql_escape_string($br_info['dmk_br_id'])."' ";
            $where = " and ";
        }
    } elseif ($sag_id) {
        // 대리점이 선택된 경우 해당 대리점과 산하 지점들의 분류
        $ag_info = sql_fetch("SELECT dmk_dt_id, dmk_ag_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sag_id)."'");
        if ($ag_info) {
            $sql_search .= " $where dmk_dt_id = '".sql_escape_string($ag_info['dmk_dt_id'])."' 
                             AND dmk_ag_id = '".sql_escape_string($ag_info['dmk_ag_id'])."' ";
            $where = " and ";
        }
    } elseif ($sdt_id) {
        // 총판이 선택된 경우 해당 총판의 모든 분류
        $dt_info = sql_fetch("SELECT dmk_dt_id FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($sdt_id)."'");
        if ($dt_info) {
            $sql_search .= " $where dmk_dt_id = '".sql_escape_string($dt_info['dmk_dt_id'])."' ";
            $where = " and ";
        }
    }
}

$sql_common = " from {$g5['g5_shop_category_table']} ";
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

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">생성된  분류 수</span><span class="ov_num">  <?php echo number_format($total_count); ?>개</span></span>
</div>



<form name="flist" class="local_sch01 local_sch" method="get">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
<?php if ($dmk_auth['is_super']) { ?>
<input type="hidden" name="sdt_id" value="<?php echo $sdt_id; ?>">
<input type="hidden" name="sag_id" value="<?php echo $sag_id; ?>">
<input type="hidden" name="sbr_id" value="<?php echo $sbr_id; ?>">
<?php } ?>

<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
    <input type="hidden" name="stx" value="<?php echo $stx; ?>">
    <input type="hidden" name="page" value="1">
    
    <?php 
    echo dmk_render_chain_select([
        'page_type' => DMK_CHAIN_SELECT_FULL,    
        'current_values' => [
            'sdt_id' => $sdt_id,
            'sag_id' => $sag_id, 
            'sbr_id' => $sbr_id
        ],
        'field_names' => [
            'distributor' => 'sdt_id',
            'agency' => 'sag_id',
            'branch' => 'sbr_id'
        ],
        'labels' => [
            'distributor' => '총판',
            'agency' => '대리점',
            'branch' => '지점'
        ],
        'placeholders' => [
            'distributor' => '전체 총판',
            'agency' => '전체 대리점',
            'branch' => '전체 지점'
        ],
        'form_id' => 'flist',
        'auto_submit' => true
    ]);
    ?>


<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="ca_name"<?php echo get_selected($sfl, "ca_name"); ?>>분류명</option>
    <option value="ca_id"<?php echo get_selected($sfl, "ca_id"); ?>>분류코드</option>
    <option value="ca_mb_id"<?php echo get_selected($sfl, "ca_mb_id"); ?>>회원아이디</option>
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
<?php if ($dmk_auth['is_super']) { ?>
<input type="hidden" name="sdt_id" value="<?php echo $sdt_id; ?>">
<input type="hidden" name="sag_id" value="<?php echo $sag_id; ?>">
<input type="hidden" name="sbr_id" value="<?php echo $sbr_id; ?>">
<?php } ?>

<div id="sct" class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" rowspan="2"><?php echo subject_sort_link("ca_id"); ?>분류코드</a></th>
        <th scope="col" id="sct_cate"><?php echo subject_sort_link("ca_name"); ?>분류명</a></th>
        <th scope="col" id="sct_hierarchy">소유 계층</th>
        <th scope="col" id="sct_amount">상품수</th>
        <th scope="col" id="sct_hpcert">본인인증</th>
        <th scope="col" id="sct_imgw">이미지 폭</th>
        <th scope="col" id="sct_imgcol">1행이미지수</th>
        <th scope="col" id="sct_mobileimg">모바일<br>1행이미지수</th>
        <th scope="col" id="sct_pcskin">PC스킨지정</th>
        <th scope="col" rowspan="2">관리</th>
    </tr>
    <tr>
        <th scope="col" id="sct_admin"><?php echo subject_sort_link("ca_mb_id"); ?>관리회원아이디</a></th>
        <th scope="col" id="sct_hierarchy_detail">계층 상세</th>
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
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        $level = strlen($row['ca_id']) / 2 - 1;
        $p_ca_name = '';

        if ($level > 0) {
            $class = 'class="name_lbl"'; // 2단 이상 분류의 label 에 스타일 부여 - 지운아빠 2013-04-02
            // 상위단계의 분류명
            $p_ca_id = substr($row['ca_id'], 0, $level*2);
            $sql = " select ca_name from {$g5['g5_shop_category_table']} where ca_id = '$p_ca_id' ";
            $temp = sql_fetch($sql);
            $p_ca_name = $temp['ca_name'].'의하위';
        } else {
            $class = '';
        }

        $s_level = '<div><label for="ca_name_'.$i.'" '.$class.'><span class="sound_only">'.$p_ca_name.''.($level+1).'단 분류</span></label></div>';
        $s_level_input_size = 25 - $level *2; // 하위 분류일 수록 입력칸 넓이 작아짐 - 지운아빠 2013-04-02

        if ($level+2 < 6) $s_add = '<a href="./categoryform.php?ca_id='.$row['ca_id'].'&amp;'.$qstr.'" class="btn btn_03">추가</a> '; // 분류는 5단계까지만 가능
        else $s_add = '';
        $s_upd = '<a href="./categoryform.php?w=u&amp;ca_id='.$row['ca_id'].'&amp;'.$qstr.'" class="btn btn_02"><span class="sound_only">'.get_text($row['ca_name']).' </span>수정</a> ';

        if ($is_admin == 'super')
            $s_del = '<a href="./categoryformupdate.php?w=d&amp;ca_id='.$row['ca_id'].'&amp;'.$qstr.'" onclick="return delete_confirm(this);" class="btn btn_02"><span class="sound_only">'.get_text($row['ca_name']).' </span>삭제</a> ';

        // 해당 분류에 속한 상품의 수
        $sql1 = " select COUNT(*) as cnt from {$g5['g5_shop_item_table']}
                      where ca_id = '{$row['ca_id']}'
                      or ca_id2 = '{$row['ca_id']}'
                      or ca_id3 = '{$row['ca_id']}' ";
        $row1 = sql_fetch($sql1);

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
        <td headers="sct_hierarchy" class="td_hierarchy">
            <?php
            // 계층 정보 표시
            $hierarchy_parts = [];
            if ($row['dmk_dt_id']) {
                $dt_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$row['dmk_dt_id']}'");
                $hierarchy_parts[] = ($dt_info['mb_nick'] ?? $row['dmk_dt_id']);
            }
            if ($row['dmk_ag_id']) {
                $ag_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$row['dmk_ag_id']}'");
                $hierarchy_parts[] = ($ag_info['mb_nick'] ?? $row['dmk_ag_id']);
            }
            if ($row['dmk_br_id']) {
                $br_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$row['dmk_br_id']}'");
                $hierarchy_parts[] = ($br_info['mb_nick'] ?? $row['dmk_br_id']);
            }
            
            if (!empty($hierarchy_parts)) {
                echo '<small>' . implode(' > ', $hierarchy_parts) . '</small>';
            } else {
                echo '<small style="color: #999;">미설정</small>';
            }
            ?>
        </td>
        <td headers="sct_amount" class="td_amount"><a href="./itemlist.php?sca=<?php echo $row['ca_id']; ?>"><?php echo $row1['cnt']; ?></a></td>
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
            <?php if ($is_admin == 'super') {?>
            <label for="ca_mb_id<?php echo $i; ?>" class="sound_only">관리회원아이디</label>
            <input type="text" name="ca_mb_id[<?php echo $i; ?>]" value="<?php echo $row['ca_mb_id']; ?>" id="ca_mb_id<?php echo $i; ?>" class="tbl_input full_input" size="15" maxlength="20">
            <?php } else { ?>
            <input type="hidden" name="ca_mb_id[<?php echo $i; ?>]" value="<?php echo $row['ca_mb_id']; ?>">
            <?php echo $row['ca_mb_id']; ?>
            <?php } ?>
        </td>
        <td headers="sct_hierarchy_detail" class="td_hierarchy_detail">
            <small style="color: #666;">
                <?php
                // 계층 ID 상세 정보 표시
                $hierarchy_details = [];
                if ($row['dmk_dt_id']) $hierarchy_details[] = "총판: {$row['dmk_dt_id']}";
                if ($row['dmk_ag_id']) $hierarchy_details[] = "대리점: {$row['dmk_ag_id']}";
                if ($row['dmk_br_id']) $hierarchy_details[] = "지점: {$row['dmk_br_id']}";
                
                if (!empty($hierarchy_details)) {
                    echo implode(' | ', $hierarchy_details);
                } else {
                    echo '계층 정보 없음';
                }
                ?>
            </small>
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

    <?php if ($is_admin == 'super') {
        $add_url = './categoryform.php';
        if ($dmk_auth['is_super'] && ($sdt_id || $sag_id || $sbr_id)) {
            $add_url .= '?sdt_id=' . urlencode($sdt_id) . '&sag_id=' . urlencode($sag_id) . '&sbr_id=' . urlencode($sbr_id);
        }
    ?>
    <a href="<?php echo $add_url; ?>" id="cate_add" class="btn btn_01">분류 추가</a>
    <?php } ?>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
$(function() {
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