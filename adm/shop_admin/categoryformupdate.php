<?php

$w = isset($_REQUEST['w']) ? trim($_REQUEST['w']) : '';

$sub_menu = '400200';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

auth_check_menu($auth, $sub_menu, "w");

// 도매까 권한 확인 - 총판 관리자만 분류 관리 가능
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] > 1) {
    // alert('분류관리는 총판 관리자만 접근할 수 있습니다.', G5_ADMIN_URL); // 임시 주석 처리
}

$ca_include_head = isset($_POST['ca_include_head']) ? trim($_POST['ca_include_head']) : '';
$ca_include_tail = isset($_POST['ca_include_tail']) ? trim($_POST['ca_include_tail']) : '';
$ca_id = isset($_REQUEST['ca_id']) ? preg_replace('/[^0-9a-z]/i', '', $_REQUEST['ca_id']) : '';

if( ! $ca_id ){
    // alert('', G5_SHOP_URL); // 임시 주석 처리
}

if ($file = $ca_include_head) {
    $file_ext = pathinfo($file, PATHINFO_EXTENSION);

    if (! $file_ext || ! in_array($file_ext, array('php', 'htm', 'html')) || !preg_match("/\.(php|htm[l]?)$/i", $file)) {
        // alert("상단 파일 경로가 php, html 파일이 아닙니다."); // 임시 주석 처리
    }
}

if ($file = $ca_include_tail) {
    $file_ext = pathinfo($file, PATHINFO_EXTENSION);

    if (! $file_ext || ! in_array($file_ext, array('php', 'htm', 'html')) || !preg_match("/\.(php|htm[l]?)$/i", $file)) {
        // alert("하단 파일 경로가 php, html 파일이 아닙니다."); // 임시 주석 처리
    }
}

if( $ca_id ){
    $sql = " select * from {$g5['g5_shop_category_table']} where ca_id = '$ca_id' ";
    $ca = sql_fetch($sql);

    if ($ca && ($ca['ca_include_head'] !== $ca_include_head || $ca['ca_include_tail'] !== $ca_include_tail) && function_exists('get_admin_captcha_by') && get_admin_captcha_by()){
        include_once(G5_CAPTCHA_PATH.'/captcha.lib.php');

        if (!chk_captcha()) {
            // alert('자동등록방지 숫자가 틀렸습니다.'); // 임시 주석 처리
        }
    }
}

$check_str_keys = array(
'ca_order'=>'int',
'ca_img_width'=>'int',
'ca_img_height'=>'int',
'ca_name'=>'str',
'ca_mb_id'=>'str',
'ca_nocoupon'=>'str',
'ca_mobile_skin_dir'=>'str',
'ca_skin'=>'str',
'ca_mobile_skin'=>'str',
'ca_list_mod'=>'int',
'ca_list_row'=>'int',
'ca_mobile_img_width'=>'int',
'ca_mobile_img_height'=>'int',
'ca_mobile_list_mod'=>'int',
'ca_mobile_list_row'=>'int',
'ca_sell_email'=>'str',
'ca_use'=>'int',
'ca_stock_qty'=>'int',
'ca_explan_html'=>'int',
'ca_cert_use'=>'int',
'ca_adult_use'=>'int',
'ca_skin_dir'=>'str'
);

for($i=0;$i<=10;$i++){
    $check_str_keys['ca_'.$i.'_subj'] = 'str';
    $check_str_keys['ca_'.($i+1)] = 'str'; // Corrected index for ca_ column
}

foreach( $check_str_keys as $key=>$val ){
    if( $val === 'int' ){
        $value = isset($_POST[$key]) ? (int) $_POST[$key] : 0;
    } else {
        $value = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : '';
    }
    $$key = $_POST[$key] = $value;
}

$dmk_ca_owner_type = isset($_POST['dmk_ca_owner_type']) ? clean_xss_tags($_POST['dmk_ca_owner_type'], 1, 1) : '';
$dmk_ca_owner_id = isset($_POST['dmk_ca_owner_id']) ? clean_xss_tags($_POST['dmk_ca_owner_id'], 1, 1) : '';

$ca_head_html = isset($_POST['ca_head_html']) ? $_POST['ca_head_html'] : '';
$ca_tail_html = isset($_POST['ca_tail_html']) ? $_POST['ca_tail_html'] : '';
$ca_mobile_head_html = isset($_POST['ca_mobile_head_html']) ? $_POST['ca_mobile_head_html'] : '';
$ca_mobile_tail_html = isset($_POST['ca_mobile_tail_html']) ? $_POST['ca_mobile_tail_html'] : '';

if(!is_include_path_check($ca_include_head, 1)) {
    // alert('상단 파일 경로에 포함시킬수 없는 문자열이 있습니다.'); // 임시 주석 처리
}

if(!is_include_path_check($ca_include_tail, 1)) {
    // alert('하단 파일 경로에 포함시킬수 없는 문자열이 있습니다.'); // 임시 주석 처리
}

$check_keys = array('ca_skin_dir', 'ca_mobile_skin_dir', 'ca_skin', 'ca_mobile_skin'); 

foreach( $check_keys as $key ){
    if( isset($$key) && preg_match('#\.+(/|\\)#', $$key) ){
        // alert('스킨명 또는 경로에 포함시킬수 없는 문자열이 있습니다.'); // 임시 주석 처리
    }
}

if( function_exists('filter_input_include_path') ){
    $ca_include_head = filter_input_include_path($ca_include_head);
    $ca_include_tail = filter_input_include_path($ca_include_tail);
}

if ($w == "u" || $w == "d")
    check_demo();

auth_check_menu($auth, $sub_menu, "d");

check_admin_token();

if ($w == 'd') { // 삭제 시 도매까 권한 확인
    if (!dmk_can_modify_category($ca_id)) {
        // alert("삭제 할 권한이 없는 카테고리입니다."); // 임시 주석 처리
    }
}

if ($w == "" || $w == "u")
{
    // 기존 ca_mb_id 확인 로직 제거 (도매까 권한 로직으로 대체)
    /*
    if ($ca_mb_id)
    {
        $sql = " select mb_id from {$g5['member_table']} where mb_id = '$ca_mb_id' ";
        $row = sql_fetch($sql);
        if (!$row['mb_id'])
            alert("'$ca_mb_id' 은(는) 존재하는 회원아이디가 아닙니다.");
    }
    */

    if ($w == "") {
        // 새로운 카테고리 생성 시 도매까 카테고리 소유 정보 기본 설정
        $owner_info = dmk_get_category_owner_info();
        $dmk_ca_owner_type = $owner_info['owner_type'];
        $dmk_ca_owner_id = $owner_info['owner_id'];
    }
}

if( $ca_skin && ! is_include_path_check($ca_skin) ){
    // alert('오류 : 데이터폴더가 포함된 path 를 포함할수 없습니다.'); // 임시 주석 처리
}

$sql_common = " ca_order                = '$ca_order',
                ca_skin_dir             = '$ca_skin_dir',
                ca_mobile_skin_dir      = '$ca_mobile_skin_dir',
                ca_skin                 = '$ca_skin',
                ca_mobile_skin          = '$ca_mobile_skin',
                ca_img_width            = '$ca_img_width',
                ca_img_height           = '$ca_img_height',
				ca_list_mod             = '$ca_list_mod',
				ca_list_row             = '$ca_list_row',
                ca_mobile_img_width     = '$ca_mobile_img_width',
                ca_mobile_img_height    = '$ca_mobile_img_height',
				ca_mobile_list_mod      = '$ca_mobile_list_mod',
                ca_mobile_list_row      = '$ca_mobile_list_row',
                ca_sell_email           = '$ca_sell_email',
                ca_use                  = '$ca_use',
                ca_stock_qty            = '$ca_stock_qty',
                ca_explan_html          = '$ca_explan_html',
                ca_head_html            = '$ca_head_html',
                ca_tail_html            = '$ca_tail_html',
                ca_mobile_head_html     = '$ca_mobile_head_html',
                ca_mobile_tail_html     = '$ca_mobile_tail_html',
                ca_include_head         = '$ca_include_head',
                ca_include_tail         = '$ca_include_tail',
                ca_mb_id                = '$ca_mb_id',
                ca_cert_use             = '$ca_cert_use',
                ca_adult_use            = '$ca_adult_use',
                ca_nocoupon             = '$ca_nocoupon',
                ca_1_subj               = '$ca_1_subj',
                ca_2_subj               = '$ca_2_subj',
                ca_3_subj               = '$ca_3_subj',
                ca_4_subj               = '$ca_4_subj',
                ca_5_subj               = '$ca_5_subj',
                ca_6_subj               = '$ca_6_subj',
                ca_7_subj               = '$ca_7_subj',
                ca_8_subj               = '$ca_8_subj',
                ca_9_subj               = '$ca_9_subj',
                ca_10_subj              = '$ca_10_subj',
                ca_1                    = '$ca_1',
                ca_2                    = '$ca_2',
                ca_3                    = '$ca_3',
                ca_4                    = '$ca_4',
                ca_5                    = '$ca_5',
                ca_6                    = '$ca_6',
                ca_7                    = '$ca_7',
                ca_8                    = '$ca_8',
                ca_9                    = '$ca_9',
                ca_10                   = '$ca_10',
                dmk_ca_owner_type       = '$dmk_ca_owner_type',
                dmk_ca_owner_id         = '$dmk_ca_owner_id' ";


if ($w == "")
{
    if (!trim($ca_id))
        alert("분류 코드가 없으므로 분류를 추가하실 수 없습니다.");

    // 소문자로 변환
    $ca_id = strtolower($ca_id);

    $sql = " insert {$g5['g5_shop_category_table']}
                set ca_id   = '$ca_id',
                    ca_name = '$ca_name',
                    $sql_common ";

    // === 디버그 코드 시작 ===
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    echo "<h2>[DEBUG] Category Insert Attempt</h2>";
    echo "<p>Generated SQL Query:</p><pre>" . htmlspecialchars($sql) . "</pre>";

    $result = sql_query($sql);

    echo "<p>SQL Query Result (true for success, false for failure): " . var_export($result, true) . "</p>";

    if (!$result) {
        echo "<p style=\"color:red;\"><b>SQL Error Detected!</b></p>";
        echo "<p>MySQL Error Code: " . mysqli_errno($g5['connect_db']) . "</p>";
        echo "<p>MySQL Error Message: " . htmlspecialchars(mysqli_error($g5['connect_db'])) . "</p>";
    } else {
        echo "<p style=\"color:green;\"><b>SQL Query Executed Successfully.</b></p>";
    }
    
    exit; // 디버그 정보 확인을 위해 스크립트 강제 중단
    // === 디버그 코드 끝 ===

    run_event('shop_admin_category_created', $ca_id);
} else if ($w == "u") {
    // 도매까 권한 확인
    if (!dmk_can_modify_category($ca_id)) {
        // alert("수정 할 권한이 없는 카테고리입니다."); // 임시 주석 처리
    }

    $sql = " update {$g5['g5_shop_category_table']}
                set ca_name = '$ca_name',
                    $sql_common
              where ca_id = '$ca_id' ";
    sql_query($sql);

    // 하위분류를 똑같은 설정으로 반영
    if (isset($_POST['sub_category']) && $_POST['sub_category']) {
        $len = strlen($ca_id);
        $sql = " update {$g5['g5_shop_category_table']}
                    set $sql_common
                  where SUBSTRING(ca_id,1,$len) = '$ca_id' ";
        if ($is_admin != 'super')
            $sql .= " and ca_mb_id = '{$member['mb_id']}' "; // 이 조건은 유지해야 하위 분류 업데이트 시 소유권 필터링 가능
        sql_query($sql);
    }
    run_event('shop_admin_category_updated', $ca_id);
}
else if ($w == "d")
{
    // 분류의 길이
    $len = strlen($ca_id);

    $sql = " select COUNT(*) as cnt from {$g5['g5_shop_category_table']}
              where SUBSTRING(ca_id,1,$len) = '$ca_id'
                and ca_id <> '$ca_id' ";
    $row = sql_fetch($sql);
    if ($row['cnt'] > 0)
        // alert("이 분류에 속한 하위 분류가 있으므로 삭제 할 수 없습니다.\n\n하위분류를 우선 삭제하여 주십시오."); // 임시 주석 처리

    $str = $comma = "";
    $sql = " select it_id from {$g5['g5_shop_item_table']} where ca_id = '$ca_id' ";
    $result = sql_query($sql);
    $i=0;
    while ($row = sql_fetch_array($result))
    {
        $i++;
        if ($i % 10 == 0) $str .= "\n";
        $str .= "$comma{$row['it_id']}";
        $comma = " , ";
    }

    if ($str)
        // alert("이 분류와 관련된 상품이 총 {$i} 건 존재하므로 상품을 삭제한 후 분류를 삭제하여 주십시오.\n\n$str"); // 임시 주석 처리

    // 분류 삭제
    $sql = " delete from {$g5['g5_shop_category_table']} where ca_id = '$ca_id' ";
    sql_query($sql);
    run_event('shop_admin_category_deleted', $ca_id);
}

if(function_exists('get_admin_captcha_by'))
    get_admin_captcha_by('remove');

// 최종 리다이렉션 로직도 임시 주석 처리
// if ($w == "")
// {
//     goto_url("./categorylist.php?$qstr");
// } else if ($w == "u") {
//     goto_url("./categoryform.php?w=u&amp;ca_id=$ca_id&amp;$qstr");
// } else {
//     goto_url("./categorylist.php?$qstr");
// }