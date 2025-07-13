<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sub_menu = "";
include_once(__DIR__ . '/../common.php');

echo "Step 1: Common.php loaded<br>";

// URL에서 코드 추출
$request_uri = $_SERVER['REQUEST_URI'];
$path_info = parse_url($request_uri, PHP_URL_PATH);
echo "Step 2: URL parsed - Path: $path_info<br>";

// /go/ 이후의 경로 추출
$pattern = '/\/go\/([^\/\?]+)/';
if (preg_match($pattern, $path_info, $matches)) {
    $url_code = $matches[1];
} else {
    $url_code = 'x647r8iklh'; // 테스트용 기본값
}
echo "Step 3: URL code: $url_code<br>";

// url_code 검증 및 정리
$url_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $url_code);
echo "Step 4: Cleaned URL code: $url_code<br>";

// 요일 구하는 함수
function get_yoil($date) {
    $yoil_array = array('일', '월', '화', '수', '목', '금', '토');
    return $yoil_array[date('w', strtotime($date))];
}
echo "Step 5: get_yoil function defined<br>";

// dmk_branch 테이블에서 br_shortcut_code 또는 br_id로 지점 정보 조회
$url_code_safe = sql_real_escape_string($url_code);
echo "Step 6: Escaped URL code: $url_code_safe<br>";

$branch_sql = " SELECT b.*, 
                    COALESCE(br_m.mb_name, '') AS br_name, 
                    COALESCE(br_m.mb_nick, '') AS br_nick_from_member, 
                    COALESCE(br_m.mb_tel, '') AS br_phone, 
                    COALESCE(br_m.mb_addr1, '') AS br_address, 
                    COALESCE(ag_m.mb_nick, '') AS ag_name 
                FROM dmk_branch b 
                JOIN g5_member br_m ON b.br_id = br_m.mb_id 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                LEFT JOIN g5_member ag_m ON a.ag_id = ag_m.mb_id
                WHERE (b.br_shortcut_code = '$url_code_safe' OR b.br_id = '$url_code_safe') 
                AND b.br_status = 1 
                ORDER BY 
                    CASE WHEN b.br_shortcut_code = '$url_code_safe' THEN 1 ELSE 2 END
                LIMIT 1 ";

echo "Step 7: Branch SQL prepared<br>";

$branch = sql_fetch($branch_sql);
echo "Step 8: Branch query executed<br>";

if (!$branch) {
    echo "No branch found!<br>";
} else {
    echo "Step 9: Branch found - ID: {$branch['br_id']}, Name: {$branch['br_name']}<br>";
}

$br_id = $branch['br_id'];
echo "Step 10: Branch ID set: $br_id<br>";

// 상품 조회 (권한에 따른 필터링)
$items_sql = " SELECT it_id, it_name, it_cust_price as it_price, it_img1, it_stock_qty, ca_id
               FROM g5_shop_item 
               WHERE it_use = '1' AND it_soldout != '1' 
               ORDER BY it_order, it_id DESC ";

echo "Step 11: Items SQL prepared<br>";

$items_result = sql_query($items_sql);
echo "Step 12: Items query executed<br>";

// 카테고리 조회
$categories_sql = " SELECT ca_id, ca_name 
                   FROM g5_shop_category 
                   WHERE ca_use = '1' 
                   ORDER BY ca_order, ca_id ";

echo "Step 13: Categories SQL prepared<br>";

$categories_result = sql_query($categories_sql);
echo "Step 14: Categories query executed<br>";

$g5['title'] = $branch['br_name'] . ' 주문페이지';
echo "Step 15: Title set: {$g5['title']}<br>";

echo "<br><strong>All steps completed successfully!</strong><br>";
?>