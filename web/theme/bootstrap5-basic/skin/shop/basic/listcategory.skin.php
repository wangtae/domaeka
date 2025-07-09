<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$str = '';
$exists = false;

$ca_id_len = strlen($ca_id);
$len2 = $ca_id_len + 2;
$len4 = $ca_id_len + 4;

$sql = " SELECT ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id like '{$ca_id}%' and length(ca_id) = {$len2} and ca_use = '1' order by ca_order, ca_id ";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {

    $row2 = sql_fetch(" SELECT count(*) as cnt from {$g5['g5_shop_item_table']} where (ca_id like '{$row['ca_id']}%' or ca_id2 like '{$row['ca_id']}%' or ca_id3 like '{$row['ca_id']}%') and it_use = '1'  ");

    $str .= '<div class="sub-category-item d-flex flex-wrap flex-fill p-2 m-1 justify-content-center"><a href="' . shop_category_url($row['ca_id']) . '">' . $row['ca_name'] . ' (' . $row2['cnt'] . ')</a></div>';
    $exists = true;
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
