<?php

/**
 * 내 장바구니 내역
 */
if (!defined("_GNUBOARD_")) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/box.css">', 200);

$cart_action_url = G5_SHOP_URL . '/cartupdate.php';
?>

<!-- 장바구니 간략 보기 시작 { -->
<div class="my-cart-list">
    <h5 class="today-view-header mb-2 border-bottom p-2">장바구니 <span class="cart-count"><?php echo get_boxcart_datas_count(); ?></span></h5>
    <form name="skin_frmcartlist" id="skin_sod_bsk_list" method="post" action="<?php echo G5_SHOP_URL . '/cartupdate.php'; ?>">
        <?php
        $cart_datas = get_boxcart_datas(true);
        $i = 0;
        foreach ($cart_datas as $row) {
            if (!$row['it_id']) continue;

            echo '<div class="d-flex flex-row mb-4">';
            $it_name = get_text($row['it_name']);
            // 이미지로 할 경우
            $it_img = get_it_image($row['it_id'], 65, 65, true);
            echo '<div class="product-image me-2">' . $it_img . '</div>';
            echo '<div class="product-name d-flex flex-column">';
            echo '<a href="' . G5_SHOP_URL . '/cart.php" class="prd_name">' . $it_name . '</a>';
            echo '<span class="product-cost">';
            echo number_format($row['ct_price']) . PHP_EOL;
            echo '</span>' . PHP_EOL;
            echo '</div>';
            echo '<button class=" btn btn-outline-danger border-0 delete-cart-item ms-auto " type="button" data-it_id="' . $row['it_id'] . '"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">삭제</span></button>' . PHP_EOL;
            echo '</div>';

            echo '<input type="hidden" name="act" value="buy">';
            echo '<input type="hidden" name="ct_chk[' . $i . ']" value="1">';
            echo '<input type="hidden" name="it_id[' . $i . ']" value="' . $row['it_id'] . '">';
            echo '<input type="hidden" name="it_name[' . $i . ']" value="' . $it_name . '">';

            $i++;
        }   //end foreach

        if ($i == 0) {
            echo '<div class="empty-item"><div class="text-center m-5 p-5">장바구니 상품 없음</div></div>' . PHP_EOL;
        }
        ?>
        <div class="btn-group d-flex justify-content-between">
            <?php if ($i) { ?><div class="btn_buy"><button type="submit" class="btn btn-outline-primary">구매하기</button></div><?php } ?>
            <a href="<?php echo G5_SHOP_URL; ?>/cart.php" class="btn btn-outline-secondary">전체보기</a>
        </div>
    </form>
</div>
<script>
    jQuery(function($) {
        $(".delete-cart-item").on("click", function(e) {
            e.preventDefault();

            var it_id = $(this).data("it_id");
            var $wrap = $(this).closest("div");

            $.ajax({
                url: g5_theme_shop_url + "/ajax.action.php",
                type: "POST",
                data: {
                    "it_id": it_id,
                    "action": "cart_delete"
                },
                dataType: "json",
                async: true,
                cache: false,
                success: function(data, textStatus) {
                    if (data.error != "") {
                        alert(data.error);
                        return false;
                    }

                    $wrap.remove();
                }
            });
        });
    });
</script>
<!-- } 장바구니 간략 보기 끝 -->