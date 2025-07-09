<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$str = '';
$exists = false;

$depth2_ca_id = substr($ca_id, 0, 2);

$sql = " SELECT ca_id, ca_name from {$g5['g5_shop_category_table']} where ca_id like '{$depth2_ca_id}%' and length(ca_id) = 4 and ca_use = '1' order by ca_order, ca_id ";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    if (preg_match("/^{$row['ca_id']}/", $ca_id))
        $sct_ct_here = 'sct_ct_here';
    else
        $sct_ct_here = '';
    $str .= '<div class="sub-category-item d-flex flex-wrap flex-fill p-2 m-1 justify-content-center"><a href="' . shop_category_url($row['ca_id']) . '" class="' . $sct_ct_here . '">' . $row['ca_name'] . '</a></div>';
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
