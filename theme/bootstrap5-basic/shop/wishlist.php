<?php
if (!defined("_GNUBOARD_")) {
    exit;
}
$g5['title'] = "위시리스트";
include_once './_head.php';
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/mypage.css">', 200);

?>
<div id="sod_ws" class="wish-list-wrap mt-2 container">
    <div class="bg-white p-4 border mb-2">
        <h1 class="fs-4 fw-bolder mb-0"><i class="bi bi-person-circle"></i> <?php echo $g5['title'] ?></h1>
    </div>

    <div class="bg-white p-4 border">
        <form name="fwishlist" method="post" action="./cartupdate.php">
            <input type="hidden" name="act" value="multi">
            <input type="hidden" name="sw_direct" value="">
            <input type="hidden" name="prog" value="wish">

            <div class="list_02">
                <div class="row g-2">
                    <?php
                    $sql  = " SELECT a.wi_id, a.wi_time, b.* from {$g5['g5_shop_wish_table']} a left join {$g5['g5_shop_item_table']} b on ( a.it_id = b.it_id ) ";
                    $sql .= " where a.mb_id = '{$member['mb_id']}' order by a.wi_id desc ";
                    $result = sql_query($sql);
                    for ($i = 0; $row = sql_fetch_array($result); $i++) {

                        $out_cd = '';
                        $sql = " SELECT count(*) as cnt from {$g5['g5_shop_item_option_table']} where it_id = '{$row['it_id']}' and io_type = '0' ";
                        $tmp = sql_fetch($sql);
                        if (isset($tmp['cnt']) && $tmp['cnt'])
                            $out_cd = 'no';

                        $it_price = get_price($row);

                        if ($row['it_tel_inq']) $out_cd = 'tel_inq';

                        $image = get_it_image($row['it_id'], 400, 300);
                    ?>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="wish-item p-2 position-relative h-100">
                                <div class="position-relative text-center mb-2 image-wrap">
                                    <div class="position-absolute">
                                        <?php if (is_soldout($row['it_id'])) { ?>
                                            품절
                                        <?php } else { ?>
                                            <div class="chk_box">
                                                <input type="checkbox" name="chk_it_id[<?php echo $i; ?>]" value="1" id="chk_it_id_<?php echo $i; ?>" onclick="out_cd_check(this, '<?php echo $out_cd; ?>');" class="selec_chk">
                                                <label for="chk_it_id_<?php echo $i; ?>"><span></span><b class="visually-hidden"><?php echo $row['it_name']; ?></b></label>
                                            </div>
                                        <?php } ?>
                                        <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
                                        <input type="hidden" name="io_type[<?php echo $row['it_id']; ?>][0]" value="0">
                                        <input type="hidden" name="io_id[<?php echo $row['it_id']; ?>][0]" value="">
                                        <input type="hidden" name="io_value[<?php echo $row['it_id']; ?>][0]" value="<?php echo $row['it_name']; ?>">
                                        <input type="hidden" name="ct_qty[<?php echo $row['it_id']; ?>][0]" value="1">
                                    </div>
                                    <?php echo $image; ?>
                                </div>
                                <div class="wish-title fw-bolder"><a href="<?php echo shop_item_url($row['it_id']); ?>"><?php echo stripslashes($row['it_name']); ?></a></div>
                                <div class="wish-price"><?php echo display_price(get_price($row), $row['it_tel_inq']); ?></div>
                                <div class="wish-date"><?php echo $row['wi_time']; ?></div>
                                <div class="wish-delete position-absolute">
                                    <a href="./wishupdate.php?w=d&amp;wi_id=<?php echo $row['wi_id']; ?>" class="wish_del btn btn-outline-danger border-0"><i class="fa fa-trash" aria-hidden="true"></i><span class="visually-hidden">삭제</span></a>
                                </div>
                            </div>
                        </div>
                    <?php
                    }

                    if ($i == 0) {
                        echo '<div class="col-12"><div class="p-4 text-center">보관 내역이 없습니다.</div></div>';
                    }
                    ?>
                </div>
            </div>

            <div id="smb_ws_act" class="btn-group mt-4">
                <button type="submit" class="btn01 btn btn-outline-primary" onclick="return fwishlist_check(document.fwishlist,'');"><i class="bi bi-cart3"></i> 장바구니</button>
                <button type="submit" class="btn02 btn btn-outline-success" onclick="return fwishlist_check(document.fwishlist,'direct_buy');"><i class="bi bi-bag-fill"></i> 주문하기</button>
            </div>
        </form>
    </div>
</div>
<script>
    function out_cd_check(fld, out_cd) {
        if (out_cd == 'no') {
            alert("옵션이 있는 상품입니다.\n\n상품을 클릭하여 상품페이지에서 옵션을 선택한 후 주문하십시오.");
            fld.checked = false;
            return;
        }

        if (out_cd == 'tel_inq') {
            alert("이 상품은 전화로 문의해 주십시오.\n\n장바구니에 담아 구입하실 수 없습니다.");
            fld.checked = false;
            return;
        }
    }

    function fwishlist_check(f, act) {
        var k = 0;
        var length = f.elements.length;

        for (i = 0; i < length; i++) {
            if (f.elements[i].checked) {
                k++;
            }
        }

        if (k == 0) {
            alert("상품을 하나 이상 체크 하십시오");
            return false;
        }

        if (act == "direct_buy") {
            f.sw_direct.value = 1;
        } else {
            f.sw_direct.value = 0;
        }

        return true;
    }
</script>
<!-- } 위시리스트 끝 -->

<?php
include_once './_tail.php';
