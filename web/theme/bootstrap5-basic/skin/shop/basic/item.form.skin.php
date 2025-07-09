<?php

/**
 * 상품 보기
 * 
 */
if (!defined('_GNUBOARD_')) {
	exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/itemform.css">', 200);
?>
<div class="item-view-wrap container mt-2 2017_renewal_itemform mb-2"><!--class 2017_renewal_itemform 항목 삭제 금지 -->
	<div class="bg-white border p-2 p-lg-4">
		<form name="fitem" method="post" action="<?php echo $action_url; ?>" onsubmit="return fitem_submit(this);">
			<input type="hidden" name="it_id[]" value="<?php echo $it_id; ?>">
			<input type="hidden" name="sw_direct">
			<input type="hidden" name="url">

			<div id="sit_ov_wrap" class="d-flex flex-column flex-lg-row">
				<div id="sit_pvi" class="item-image-wrap justify-content-center flex-row-reverse d-none d-lg-flex">
					<div id="sit_pvi_big" class="item-image-view">
						<?php
						$big_img_count = 0;
						$thumbnails = array();
						$item_image = array();
						for ($i = 1; $i <= 10; $i++) {
							if (!$it['it_img' . $i]) {
								continue;
							}

							                                                        $img = get_it_thumbnail($it['it_img' . $i], $default['de_mimg_width'], $default['de_mimg_height']);

                                                        if ($img) {
                                                                $item_image[$i] = $img;
                                                                // 썸네일
                                                                $thumb = get_it_thumbnail($it['it_img' . $i], 40, 30);
                                                                $thumbnails[] = $thumb;
                                                                $big_img_count++;

								echo '<a href="' . G5_SHOP_URL . '/largeimage.php?it_id=' . $it['it_id'] . '#slide' . $i . '" target="_blank" class="popup_item_image big-image flex-wrap justify-content-center mb-1">' . $img . '</a>';
							}
						}

						if ($big_img_count == 0) {
							echo '<img src="' . G5_SHOP_URL . '/img/no_image.gif" alt="">';
						}
						?>
					</div>
					<?php
					// 썸네일
					$thumb1 = true;
					$thumb_count = 0;
					$total_count = count($thumbnails);
					if ($total_count > 0) {
						echo '<ul id="sit_pvi_thumb" class="list-group flex-wrap">';
						foreach ($thumbnails as $val) {
							$thumb_count++;
							$sit_pvi_last = '';
							if ($thumb_count % 5 == 0) {
								$sit_pvi_last = 'li_last';
							}
							echo '<li class="' . $sit_pvi_last . ' list-group-item p-0">';
							echo '<a href="' . G5_SHOP_URL . '/largeimage.php?it_id=' . $it['it_id'] . '#slide' . $thumb_count . '" target="_blank" class="popup_item_image img_thumb">' . $val . '<span class="visually-hidden"> ' . $thumb_count . '번째 이미지 새창</span></a>';
							echo '</li>';
						}
						echo '</ul>';
					}
					?>
				</div><!--//.item-image-wrap-->
				<!-- 모바일용 상품 이미지 출력 -->
				<div class="mobile-item-image-wrap justify-content-center flex-row-reverse d-flex d-lg-none mb-3">
					<div id="carousel-image" class="carousel slide" data-bs-ride="carousel">
						<div class="carousel-inner">
							<?php
							$i = 0;
							foreach ($item_image as $_img) {
								echo ($i == 0) ? "<div class='carousel-item active'>" : "<div class='carousel-item'>";
								echo '<a href="' . G5_SHOP_URL . '/largeimage.php?it_id=' . $it['it_id'] . '#slide' . $i . '" target="_blank" class="popup_item_image big-image flex-wrap justify-content-center d-block w-100">' . $_img . '</a>';
								echo '</div>';
								$i++;
							} ?>
						</div>
						<button class="carousel-control-prev" type="button" data-bs-target="#carousel-image" data-bs-slide="prev">
							<span class="carousel-control-prev-icon" aria-hidden="true"></span>
							<span class="visually-hidden">Previous</span>
						</button>
						<button class="carousel-control-next" type="button" data-bs-target="#carousel-image" data-bs-slide="next">
							<span class="carousel-control-next-icon" aria-hidden="true"></span>
							<span class="visually-hidden">Next</span>
						</button>
					</div>
				</div>

				<!-- 상품 요약정보 및 구매 시작 { -->
				<section id="sit_ov" class="item-buy-info d-flex flex-column flex-fill">
					<h2 id="sit_title" class="fs-5 fw-bold"><?php echo stripslashes($it['it_name']); ?> <span class="visually-hidden">요약정보 및 구매</span></h2>
					<div id="sit_desc" class="item-description border-bottom position-relative">
						<?php echo $it['it_basic']; ?>
						<div class="item-star align-content-center d-flex flex-wrap pb-2 mt-1">
							<?php if ($star_score) { ?>
								<span class="visually-hidden">고객평점</span>
								<img src="<?php echo G5_SHOP_URL; ?>/img/s_star<?php echo $star_score ?>.png" alt="" class="sit_star" width="100">
								<span class="visually-hidden">별<?php echo $star_score ?>개</span>
							<?php } ?>
							<span class="d-flex"> 상품평 <?php echo $it['it_use_cnt']; ?> 개</span>
						</div>
						<div id="sit_btn_opt" class="share-icons position-absolute">
							<div class="dropdown">
								<a onclick="javascript:item_wish(document.fitem, '<?php echo $it['it_id']; ?>');" class="btn btn-outline-secondary border-0 position-relative">
									<i class="bi bi-heart" aria-hidden="true"></i>
									<span class="wish-count text-primary">
										<?php echo get_wishlist_count_by_item($it['it_id']); ?>
										<span class="visually-hidden">위시리스트</span>
									</span>
								</a>
								<button type="button" class="btn_sns_share btn btn-outline-secondary border-0" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-share"></i> <span class="visually-hidden">sns 공유</span></button>
								<div class="sns_area dropdown-menu p-2 text-center shadow">
									<?php
									$sns_icons = str_replace('<img src="' . G5_SHOP_SKIN_URL . '/img/facebook.png' . '" alt="페이스북에 공유">', '<i class="bi bi-facebook"></i>', $sns_share_links);
									$sns_icons = str_replace('<img src="' . G5_SHOP_SKIN_URL . '/img/twitter.png' . '" alt="트위터에 공유">', '<i class="bi bi-twitter"></i>', $sns_icons);
									if (!$config['cf_kakao_js_apikey']) {
										$sns_icons .= '<a href="javascript:kakaolink_send(\'' . str_replace('+', ' ', urlencode($sns_title)) . '\', \'' . urlencode($sns_url) . '\');" class="share-kakaotalk"><i class="bi bi-chat-fill"></i></a>';
									}
									echo $sns_icons;
									?>
									<a href="javascript:popup_item_recommend('<?php echo $it['it_id']; ?>');" id="sit_btn_rec"><i class="bi bi-envelope-at-fill"></i><span class="visually-hidden">추천하기</span></a>
								</div>
							</div>
						</div>
					</div><!--//.item-description-->




					<div class="sit_info">
						<table class="sit_ov_tbl table table-borderless">
							<colgroup>
								<col class="grid_3">
								<col>
							</colgroup>
							<tbody>

								<?php if (!$it['it_use']) { // 판매가능이 아닐 경우 
								?>
									<tr>
										<th scope="row">판매가격</th>
										<td>판매중지</td>
									</tr>
								<?php } else if ($it['it_tel_inq']) {
									// 전화문의일 경우 
								?>
									<tr>
										<th scope="row">판매가격</th>
										<td>전화문의</td>
									</tr>
								<?php } else { // 전화문의가 아닐 경우
								?>
									<?php if ($it['it_cust_price']) { ?>
										<tr>
											<th scope="row">시중가격</th>
											<td><?php echo display_price($it['it_cust_price']); ?></td>
										</tr>
									<?php } // 시중가격 끝 
									?>

									<tr class="tr_price">
										<th scope="row">판매가격</th>
										<td>
											<strong class="text-danger"><?php echo display_price(get_price($it)); ?></strong>
											<input type="hidden" id="it_price" value="<?php echo get_price($it); ?>">
										</td>
									</tr>
								<?php } ?>

								<?php if ($it['it_maker']) { ?>
									<tr>
										<th scope="row">제조사</th>
										<td><?php echo $it['it_maker']; ?></td>
									</tr>
								<?php } ?>

								<?php if ($it['it_origin']) { ?>
									<tr>
										<th scope="row">원산지</th>
										<td><?php echo $it['it_origin']; ?></td>
									</tr>
								<?php } ?>

								<?php if ($it['it_brand']) { ?>
									<tr>
										<th scope="row">브랜드</th>
										<td><?php echo $it['it_brand']; ?></td>
									</tr>
								<?php } ?>

								<?php if ($it['it_model']) { ?>
									<tr>
										<th scope="row">모델</th>
										<td><?php echo $it['it_model']; ?></td>
									</tr>
								<?php } ?>

								<?php
								/* 재고 표시하는 경우 주석 해제
								<tr>
									<th scope="row">재고수량</th>
									<td><?php echo number_format(get_it_stock_qty($it_id)); ?> 개</td>
								</tr>*/
								?>

								<?php if ($config['cf_use_point']) { ?>
									<tr>
										<th scope="row">포인트</th>
										<td>
											<?php
											if ($it['it_point_type'] == 2) {
												echo '구매금액(추가옵션 제외)의 ' . $it['it_point'] . '%';
											} else {
												$it_point = get_item_point($it);
												echo number_format($it_point) . '점';
											}
											?>
										</td>
									</tr>
								<?php } ?>
								<?php
								$ct_send_cost_label = '배송비결제';

								if ($it['it_sc_type'] == 1) {
									$sc_method = '무료배송';
								} else {
									if ($it['it_sc_method'] == 1) {
										$sc_method = '수령후 지불';
									} else if ($it['it_sc_method'] == 2) {
										$ct_send_cost_label = '<label for="ct_send_cost">배송비결제</label>';
										$sc_method = '<select name="ct_send_cost" id="ct_send_cost" class="form-select">
	                                      <option value="0">주문시 결제</option>
	                                      <option value="1">수령후 지불</option>
	                                  </select>';
									} else {
										$sc_method = '주문시 결제';
									}
								}
								?>
								<tr>
									<th><?php echo $ct_send_cost_label; ?></th>
									<td><?php echo $sc_method; ?></td>
								</tr>
								<?php if ($it['it_buy_min_qty']) { ?>
									<tr>
										<th>최소구매수량</th>
										<td><?php echo number_format($it['it_buy_min_qty']); ?> 개</td>
									</tr>
								<?php } ?>
								<?php if ($it['it_buy_max_qty']) { ?>
									<tr>
										<th>최대구매수량</th>
										<td><?php echo number_format($it['it_buy_max_qty']); ?> 개</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
					<?php if ($is_orderable) { ?>
						<div id="sit_opt_info" class="alert alert-secondary alert-sm p-2">
							<i class="bi bi-info-circle"></i> 상품 선택옵션 <?php echo $option_count; ?> 개, 추가옵션 <?php echo $supply_count; ?> 개
						</div>
					<?php } ?>
					<?php if ($option_item) { ?>
						<!-- 선택옵션 시작 { -->
						<section class="sit_option mb-2">
							<h3 class="fs-6 fw-bold">선택옵션</h3>
							<?php echo str_replace('class="it_option"', 'class="it_option form-select"', $option_item); ?>
						</section>
						<!-- } 선택옵션 끝 -->
					<?php } ?>

					<?php if ($supply_item) { ?>
						<!-- 추가옵션 시작 { -->
						<section class="sit_option mb-2">
							<h3 class="fs-6 fw-bold">추가옵션</h3>
							<?php echo str_replace('class="it_supply"', 'class="it_supply form-select"', $supply_item); ?>

						</section>
						<!-- } 추가옵션 끝 -->
					<?php } ?>

					<?php if ($is_orderable) { ?>
						<!-- 선택된 옵션 시작 { -->
						<section id="sit_sel_option">
							<h3 class="visually-hidden">선택된 옵션</h3>
							<?php
							if (!$option_item) {
								if (!$it['it_buy_min_qty']) {
									$it['it_buy_min_qty'] = 1;
								}
							?>
								<ul id="sit_opt_added">
									<li class="sit_opt_list">
										<input type="hidden" name="io_type[<?php echo $it_id; ?>][]" value="0">
										<input type="hidden" name="io_id[<?php echo $it_id; ?>][]" value="">
										<input type="hidden" name="io_value[<?php echo $it_id; ?>][]" value="<?php echo $it['it_name']; ?>">
										<input type="hidden" class="io_price" value="0">
										<input type="hidden" class="io_stock" value="<?php echo $it['it_stock_qty']; ?>">
										<div class="opt_name">
											<span class="sit_opt_subj"><?php echo $it['it_name']; ?></span>
										</div>
										<div class="opt_count">
											<label for="ct_qty_<?php echo $i; ?>" class="visually-hidden">수량</label>
											<button type="button" class="sit_qty_minus"><i class="fa fa-minus" aria-hidden="true"></i><span class="visually-hidden">감소</span></button>
											<input type="text" name="ct_qty[<?php echo $it_id; ?>][]" value="<?php echo $it['it_buy_min_qty']; ?>" id="ct_qty_<?php echo $i; ?>" class="num_input" size="5">
											<button type="button" class="sit_qty_plus"><i class="fa fa-plus" aria-hidden="true"></i><span class="visually-hidden">증가</span></button>
											<span class="sit_opt_prc">+0원</span>
										</div>
									</li>
								</ul>
								<script>
									$(function() {
										price_calculate();
									});
								</script>
							<?php } ?>
						</section>
						<!-- } 선택된 옵션 끝 -->

						<!-- 총 구매액 -->
						<div id="sit_tot_price" class="d-flex flex-fill justify-content-between"></div>
					<?php } ?>

					<?php if ($is_soldout) { ?>
						<div id="sit_ov_soldout" class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> 상품의 재고가 부족하여 구매할 수 없습니다.</div>
					<?php } ?>

					<div id="sit_ov_btn" class="btn-group buy-button">
						<?php if ($is_orderable) { ?>
							<button type="submit" onclick="document.pressed=this.value;" value="장바구니" class="btn btn-secondary me-1"><i class="bi bi-cart3"></i> 장바구니</button>
							<button type="submit" onclick="document.pressed=this.value;" value="바로구매" class="btn btn-primary"><i class="bi bi-check"></i> 바로구매</button>
						<?php } ?>

						<?php if (!$is_orderable && $it['it_soldout'] && $it['it_stock_sms']) { ?>
							<a href="javascript:popup_stocksms('<?php echo $it['it_id']; ?>');" id="sit_btn_alm">재입고알림</a>
						<?php } ?>
						<?php if ($naverpay_button_js) { ?>
							<div class="itemform-naverpay"><?php echo $naverpay_request_js . $naverpay_button_js; ?></div>
						<?php } ?>
					</div>

					<script>
						// 상품보관
						function item_wish(f, it_id) {
							f.url.value = "<?php echo G5_SHOP_URL; ?>/wishupdate.php?it_id=" + it_id;
							f.action = "<?php echo G5_SHOP_URL; ?>/wishupdate.php";
							f.submit();
						}

						// 추천메일
						function popup_item_recommend(it_id) {
							if (!g5_is_member) {
								if (confirm("회원만 추천하실 수 있습니다."))
									document.location.href = "<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode(shop_item_url($it_id)); ?>";
							} else {
								url = "./itemrecommend.php?it_id=" + it_id;
								opt = "scrollbars=yes,width=616,height=420,top=10,left=10";
								popup_window(url, "itemrecommend", opt);
							}
						}

						// 재입고SMS 알림
						function popup_stocksms(it_id) {
							url = "<?php echo G5_SHOP_URL; ?>/itemstocksms.php?it_id=" + it_id;
							opt = "scrollbars=yes,width=616,height=420,top=10,left=10";
							popup_window(url, "itemstocksms", opt);
						}
					</script>
				</section>
				<!-- } 상품 요약정보 및 구매 끝 -->
			</div>
			<!-- 다른 상품 보기 시작 { -->
			<div class="order-item-button p-2 mt-2 d-flex justify-content-end">
				<?php
				if ($prev_href || $next_href) {
					echo $prev_href . $prev_title . $prev_href2;
					echo $next_href . $next_title . $next_href2;
				} else {
					echo '<span class="visually-hidden">이 분류에 등록된 다른 상품이 없습니다.</span>';
				}
				?>
			</div>
			<!-- } 다른 상품 보기 끝 -->
		</form>
	</div>
</div>

<script>
	$(function() {
		// 상품이미지 첫번째 링크
		$("#sit_pvi_big a:first").addClass("show-item");

		// 상품이미지 미리보기 (썸네일에 마우스 오버시)
		$("#sit_pvi .img_thumb").bind("mouseover focus", function() {
			var idx = $("#sit_pvi .img_thumb").index($(this));
			$("#sit_pvi_big a.show-item").removeClass("show-item");
			$("#sit_pvi_big a:eq(" + idx + ")").addClass("show-item");
		});

		// 상품이미지 크게보기
		$(".popup_item_image").click(function() {
			var url = $(this).attr("href");
			var top = 10;
			var left = 10;
			var opt = 'scrollbars=yes,top=' + top + ',left=' + left;
			popup_window(url, "largeimage", opt);

			return false;
		});
	});

	function fsubmit_check(f) {
		// 판매가격이 0 보다 작다면
		if (document.getElementById("it_price").value < 0) {
			alert("전화로 문의해 주시면 감사하겠습니다.");
			return false;
		}

		if ($(".sit_opt_list").length < 1) {
			alert("상품의 선택옵션을 선택해 주십시오.");
			return false;
		}

		var val, io_type, result = true;
		var sum_qty = 0;
		var min_qty = parseInt(<?php echo $it['it_buy_min_qty']; ?>);
		var max_qty = parseInt(<?php echo $it['it_buy_max_qty']; ?>);
		var $el_type = $("input[name^=io_type]");

		$("input[name^=ct_qty]").each(function(index) {
			val = $(this).val();

			if (val.length < 1) {
				alert("수량을 입력해 주십시오.");
				result = false;
				return false;
			}

			if (val.replace(/[0-9]/g, "").length > 0) {
				alert("수량은 숫자로 입력해 주십시오.");
				result = false;
				return false;
			}

			if (parseInt(val.replace(/[^0-9]/g, "")) < 1) {
				alert("수량은 1이상 입력해 주십시오.");
				result = false;
				return false;
			}

			io_type = $el_type.eq(index).val();
			if (io_type == "0")
				sum_qty += parseInt(val);
		});

		if (!result) {
			return false;
		}

		if (min_qty > 0 && sum_qty < min_qty) {
			alert("선택옵션 개수 총합 " + number_format(String(min_qty)) + "개 이상 주문해 주십시오.");
			return false;
		}

		if (max_qty > 0 && sum_qty > max_qty) {
			alert("선택옵션 개수 총합 " + number_format(String(max_qty)) + "개 이하로 주문해 주십시오.");
			return false;
		}

		return true;
	}

	// 바로구매, 장바구니 폼 전송
	function fitem_submit(f) {
		f.action = "<?php echo $action_url; ?>";
		f.target = "";

		if (document.pressed == "장바구니") {
			f.sw_direct.value = 0;
		} else { // 바로구매
			f.sw_direct.value = 1;
		}

		// 판매가격이 0 보다 작다면
		if (document.getElementById("it_price").value < 0) {
			alert("전화로 문의해 주시면 감사하겠습니다.");
			return false;
		}

		if ($(".sit_opt_list").length < 1) {
			alert("상품의 선택옵션을 선택해 주십시오.");
			return false;
		}

		var val, io_type, result = true;
		var sum_qty = 0;
		var min_qty = parseInt(<?php echo $it['it_buy_min_qty']; ?>);
		var max_qty = parseInt(<?php echo $it['it_buy_max_qty']; ?>);
		var $el_type = $("input[name^=io_type]");

		$("input[name^=ct_qty]").each(function(index) {
			val = $(this).val();

			if (val.length < 1) {
				alert("수량을 입력해 주십시오.");
				result = false;
				return false;
			}

			if (val.replace(/[0-9]/g, "").length > 0) {
				alert("수량은 숫자로 입력해 주십시오.");
				result = false;
				return false;
			}

			if (parseInt(val.replace(/[^0-9]/g, "")) < 1) {
				alert("수량은 1이상 입력해 주십시오.");
				result = false;
				return false;
			}

			io_type = $el_type.eq(index).val();
			if (io_type == "0")
				sum_qty += parseInt(val);
		});

		if (!result) {
			return false;
		}

		if (min_qty > 0 && sum_qty < min_qty) {
			alert("선택옵션 개수 총합 " + number_format(String(min_qty)) + "개 이상 주문해 주십시오.");
			return false;
		}

		if (max_qty > 0 && sum_qty > max_qty) {
			alert("선택옵션 개수 총합 " + number_format(String(max_qty)) + "개 이하로 주문해 주십시오.");
			return false;
		}

		return true;
	}
</script>
<?php /* 2017 리뉴얼한 테마 적용 스크립트입니다. 기존 스크립트를 오버라이드 합니다. */ ?>
<?php add_javascript('<script src="' . G5_JS_URL . '/shop.override.js"></script>', 300);
