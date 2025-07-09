<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$str = '';
$exists = false;

$ca_id_len = strlen($ca_id);
$len2 = $ca_id_len + 2;
$len4 = $ca_id_len + 4;

// 최하위 분류의 경우 상단에 동일한 레벨의 분류를 출력해주는 코드
if (!$exists) {
    $str = '';

    $tmp_ca_id = substr($ca_id, 0, strlen($ca_id) - 2);
    $tmp_ca_id_len = strlen($tmp_ca_id);
    $len2 = $tmp_ca_id_len + 2;
    $len4 = $tmp_ca_id_len + 4;

    // 차차기 분류의 건수를 얻음
    $sql = " SELECT count(*) as cnt from {$g5['g5_shop_category_table']} where ca_id like '$tmp_ca_id%' and ca_use = '1' and length(ca_id) = $len4 ";
    $row = sql_fetch($sql);
    $cnt = $row['cnt'];

    $sql = " SELECT ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id like '$tmp_ca_id%' and ca_use = '1' and length(ca_id) = $len2 order by ca_order, ca_id ";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $sct_ct_here = '';
        if ($ca_id == $row['ca_id']) // 활성 분류 표시
            $sct_ct_here = 'sct_ct_here';

        $str .= '<div class="sub-category-item d-flex flex-wrap flex-fill p-2 m-1 justify-content-center">';
        if ($cnt) {
            $str .= '<a href="' . shop_category_url($row['ca_id']) . '" class="sct_ct_parent ' . $sct_ct_here . '">' . $row['ca_name'] . '</a>';
            $sql2 = " SELECT ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id like '{$row['ca_id']}%' and ca_use = '1' and length(ca_id) = $len4 order by ca_order, ca_id ";
            $result2 = sql_query($sql2);
            $k = 0;
            while ($row2 = sql_fetch_array($result2)) {
                $str .= '<a href="' . shop_category_url($row2['ca_id']) . '">' . $row2['ca_name'] . '</a>';
                $k++;
            }
        } else {
            $str .= '<a href="' . shop_category_url($row['ca_id']) . '" class="sct_ct_parent ' . $sct_ct_here . '">' . $row['ca_name'] . '</a>';
        }
        $str .= '</div>';
        $exists = true;
    }
}

if ($exists) {
?>
    <div class='container list-category-sub-wrap'>
        <div class="bg-white border p-2 p-lg-4 mt-2">
            <h2 class="fs-6 fw-bolder ff-noto">현재 상품 분류와 관련된 분류</h2>
            <div class="d-flex flex-row flex-wrap">
                <?php echo $str; ?>
            </div>
        </div>
    </div>

<?php }
