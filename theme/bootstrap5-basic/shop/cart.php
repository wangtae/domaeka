<?php

/**
 * 장바구니
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/cart.css">', 200);

$g5['title'] = '장바구니';
include_once G5_THEME_PATH . '/head.php';
?>

<script src="<?php echo G5_JS_URL; ?>/shop.js"></script>
<script src="<?php echo G5_JS_URL; ?>/shop.override.js"></script>
<div id="cart" class="new_win cart-wrap">
    <div class="point-header container mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-cart3"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class='container'>
        <div class='bg-white p-2 p-lg-4 border'>
            <div id="sod_bsk" class="od_prd_list">

                <form name="frmcartlist" id="sod_bsk_list" class="2017_renewal_itemform" method="post" action="<?php echo $cart_action_url; ?>">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col" class="align-middle">
                                        <input type="checkbox" name="ct_all" value="1" id="ct_all" checked="checked" class="form-checkbox">
                                        <label for="ct_all"><span></span><b class="visually-hidden">상품 전체</b></label>
                                    </th>
                                    <th scope="col">상품명</th>
                                    <th class="text-center d-none d-md-table-cell" scope="col">총수량</th>
                                    <th class="text-center d-none d-md-table-cell" scope="col">판매가</th>
                                    <th class="text-center d-none d-md-table-cell" scope="col">포인트</th>
                                    <th class="text-center d-none d-md-table-cell" scope="col">배송비</th>
                                    <th class="text-center d-none d-md-table-cell" scope="col">소계</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $tot_point = 0;
                                $tot_sell_price = 0;
                                $send_cost = 0;

                                // $s_cart_id 로 현재 장바구니 자료 쿼리
                                $sql = " SELECT a.ct_id,
                                                a.it_id,
                                                a.it_name,
                                                a.ct_price,
                                                a.ct_point,
                                                a.ct_qty,
                                                a.ct_status,
                                                a.ct_send_cost,
                                                a.it_sc_type,
                                                b.ca_id,
                                                b.ca_id2,
                                                b.ca_id3
                                            from {$g5['g5_shop_cart_table']} a left join {$g5['g5_shop_item_table']} b on ( a.it_id = b.it_id )
                                            where a.od_id = '{$s_cart_id}' ";
                                $sql .= " group by a.it_id ";
                                $sql .= " order by a.ct_id ";
                                $result = sql_query($sql);

                                $it_send_cost = 0;

                                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                                    // 합계금액 계산
                                    $sql = " SELECT SUM(IF(io_type = 1, (io_price * ct_qty), ((ct_price + io_price) * ct_qty))) as price,
                                                    SUM(ct_point * ct_qty) as point,
                                                    SUM(ct_qty) as qty
                                                from {$g5['g5_shop_cart_table']}
                                                where it_id = '{$row['it_id']}'
                                                and od_id = '{$s_cart_id}' ";
                                    $sum = sql_fetch($sql);

                                    if ($i == 0) { // 계속쇼핑
                                        $continue_ca_id = $row['ca_id'];
                                    }

                                    $a1 = '<a href="' . shop_item_url($row['it_id']) . '" class="product-name">';
                                    $a2 = '</a>';
                                    $image = get_it_image($row['it_id'], 80, 80);

                                    $it_name = $a1 . stripslashes($row['it_name']) . $a2;
                                    $it_options = print_item_options($row['it_id'], $s_cart_id);
                                    //옵션
                                    if ($it_options) {
                                        $mod_options = '<div class="d-flex options"><button type="button" data-bs-modal-id="option-modal' . $i . '" class="mod_options btn btn-outline-secondary btn-sm border-0" data-bs-toggle="modal" data-bs-target="#option-modal' . $i . '">선택사항수정</button></div>';
                                        $mod_options .= '<!-- Modal -->
                                        <div class="modal fade" id="option-modal' . $i . '" tabindex="-1" aria-labelledby="option-modal' . $i . 'Label" aria-hidden="true">
                                          <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                            <div class="modal-content">
                                              <div class="modal-header">
                                                <h1 class="modal-title fs-5" id="option-modal' . $i . 'Label">상품옵션수정</h1>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                              </div>
                                              <div class="modal-body option-modal-data">
 
                                              </div>
                                              <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                                              </div>
                                            </div>
                                          </div>
                                        </div>';
                                        $it_name .= '<div class="option-wrap">' . $it_options . '</div>';
                                    }

                                    // 배송비
                                    switch ($row['ct_send_cost']) {
                                        case 1:
                                            $ct_send_cost = '착불';
                                            break;
                                        case 2:
                                            $ct_send_cost = '무료';
                                            break;
                                        default:
                                            $ct_send_cost = '선불';
                                            break;
                                    }

                                    // 조건부무료
                                    if ($row['it_sc_type'] == 2) {
                                        $sendcost = get_item_sendcost($row['it_id'], $sum['price'], $sum['qty'], $s_cart_id);

                                        if ($sendcost == 0) {
                                            $ct_send_cost = '무료';
                                        }
                                    }

                                    $point      = $sum['point'];
                                    $sell_price = $sum['price'];
                                ?>

                                    <tr>
                                        <td class="checkbox">
                                            <input type="checkbox" name="ct_chk[<?php echo $i; ?>]" value="1" id="ct_chk_<?php echo $i; ?>" checked="checked" class="form-checkbox">
                                            <label for="ct_chk_<?php echo $i; ?>"><span></span><b class="visually-hidden">상품</b></label>
                                        </td>

                                        <td class="product">
                                            <div class="d-flex flex-row">
                                                <div class="goods-image me-2"><a href="<?php echo shop_item_url($row['it_id']); ?>"><?php echo $image; ?></a></div>
                                                <div class="goods-name">
                                                    <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
                                                    <input type="hidden" name="it_name[<?php echo $i; ?>]" value="<?php echo get_text($row['it_name']); ?>">
                                                    <?php echo $it_name . $mod_options; ?>
                                                </div>
                                            </div>
                                            <ul class='mobile-view list-group list-group-horizontal d-flex d-md-none justify-content-end'>
                                                <li class="list-group-item">수량<br/><span><?php echo number_format($sum['qty']); ?></span></li>
                                                <li class="list-group-item">판매가<br/><span><?php echo number_format($row['ct_price']); ?></span></li>
                                                <li class="list-group-item">포인트<br/><span><?php echo number_format($point); ?></span></li>
                                                <li class="list-group-item">배송비<br/><span><?php echo $ct_send_cost; ?></span></li>
                                                <li class="list-group-item">소계<br/><span id="sell_price_<?php echo $i; ?>" class="total_prc"><?php echo number_format($sell_price); ?></span></li>
                                            </ul>
                                        </td>
                                        <td class="text-center d-none d-md-table-cell td_num"><?php echo number_format($sum['qty']); ?></td>
                                        <td class="text-center d-none d-md-table-cell td_numbig"><?php echo number_format($row['ct_price']); ?></td>
                                        <td class="text-center d-none d-md-table-cell td_numbig"><?php echo number_format($point); ?></td>
                                        <td class="text-center d-none d-md-table-cell td_dvr"><?php echo $ct_send_cost; ?></td>
                                        <td class="text-center d-none d-md-table-cell td_numbig text_right"><span id="sell_price_<?php echo $i; ?>" class="total_prc"><?php echo number_format($sell_price); ?></span></td>
                                    </tr>

                                <?php
                                    $tot_point      += $point;
                                    $tot_sell_price += $sell_price;
                                } // for 끝

                                if ($i === 0) {
                                    echo '<tr><td colspan="7" class="empt-item"><div class="p-5 text-center">장바구니에 담긴 상품이 없습니다.</td></tr>';
                                } else {
                                    // 배송비 계산
                                    $send_cost = get_sendcost($s_cart_id, 0);
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="btn btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-danger me-1" onclick="return form_check('seldelete');"><i class="bi bi-credit-card"></i> 선택삭제</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="return form_check('alldelete');"><i class="bi bi-x-circle"></i> 비우기</button>
                        </div>
                    </div>

                    <?php
                    $tot_price = $tot_sell_price + $send_cost; // 총계 = 주문상품금액합계 + 배송비
                    if ($tot_price > 0 || $send_cost > 0) {
                    ?>
                        <div id="sod_bsk_tot" class="mt-2 mb-5 d-flex justify-content-end total-info">
                            <ul class="list-group list-group-horizontal">
                                <li class="list-group-item">
                                    <span>배송비</span>
                                    <strong><?php echo number_format($send_cost); ?></strong> 원
                                </li>

                                <li class="list-group-item">
                                    <span>포인트</span>
                                    <strong><?php echo number_format($tot_point); ?></strong> 점
                                </li>

                                <li class="list-group-item">
                                    <span>총계 가격</span>
                                    <strong><?php echo number_format($tot_price); ?></strong> 원
                                </li>
                            </ul>
                        </div>
                    <?php } ?>

                    <div id="sod_bsk_act" class="d-flex justify-content-between">
                        <?php if ($i == 0) { ?>
                            <a href="<?php echo G5_SHOP_URL; ?>/" class="btn btn-outline-secondary me-auto mt-5"><i class="bi bi-reply"></i> 쇼핑 계속하기</a>
                        <?php } else { ?>
                            <input type="hidden" name="url" value="./orderform.php">
                            <input type="hidden" name="records" value="<?php echo $i; ?>">
                            <input type="hidden" name="act" value="">
                            <a href="<?php echo shop_category_url($continue_ca_id); ?>" class="btn btn-outline-secondary"><i class="bi bi-reply"></i> 쇼핑 계속하기</a>
                            <button type="button" onclick="return form_check('buy');" class="btn btn-outline-primary"><i class="bi bi-credit-card"></i> 주문하기</button>

                            <?php if ($naverpay_button_js) { ?>
                                <div class="cart-naverpay"><?php echo $naverpay_request_js . $naverpay_button_js; ?></div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <script>
                $(function() {
                    var close_btn_idx;

                    // 선택사항수정
                    $(".mod_options").on('click', function() {
                        var it_id = $(this).closest("tr").find("input[name^=it_id]").val();
                        var $this = $(this);
                        var targetModal = $(this).data('bs-modal-id');
                        close_btn_idx = $(".mod_options").index($(this));

                        $.post(
                            "./cartoption.php", {
                                it_id: it_id
                            },
                            function(data) {
                                $("#mod_option_frm").remove();
                                //$this.after("<div id=\"mod_option_frm\"></div><div class=\"mod_option_bg\"></div>");

                                $("#" + targetModal).find('.option-modal-data').html(data);
                                price_calculate();
                            }
                        );
                    });

                    // 모두선택
                    $("input[name=ct_all]").on('click', function() {
                        if ($(this).is(":checked"))
                            $("input[name^=ct_chk]").attr("checked", true);
                        else
                            $("input[name^=ct_chk]").attr("checked", false);
                    });

                });

                function fsubmit_check(f) {
                    if ($("input[name^=ct_chk]:checked").length < 1) {
                        alert("구매하실 상품을 하나이상 선택해 주십시오.");
                        return false;
                    }

                    return true;
                }

                function form_check(act) {
                    var f = document.frmcartlist;
                    var cnt = f.records.value;

                    if (act == "buy") {
                        if ($("input[name^=ct_chk]:checked").length < 1) {
                            alert("주문하실 상품을 하나이상 선택해 주십시오.");
                            return false;
                        }

                        f.act.value = act;
                        f.submit();
                    } else if (act == "alldelete") {
                        f.act.value = act;
                        f.submit();
                    } else if (act == "seldelete") {
                        if ($("input[name^=ct_chk]:checked").length < 1) {
                            alert("삭제하실 상품을 하나이상 선택해 주십시오.");
                            return false;
                        }

                        f.act.value = act;
                        f.submit();
                    }

                    return true;
                }
            </script>
        </div>
    </div>
</div>
<?php
include_once G5_THEME_PATH . '/tail.php';
