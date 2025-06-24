<?php
if (!defined('_GNUBOARD_')) {
	exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/iteminfo.css">', 200);

?>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>
<div class="container item-info-wrap">
	<div class="bg-white border">
		<?php if ($default['de_rel_list_use']) { ?>
			<section id="sit_rel" class="relation-items p-3 p-lg-4">
				<h2 class="fs-5 fw-bolder">관련상품</h2>
				<?php
				$rel_skin_file = $skin_dir . '/' . $default['de_rel_list_skin'];
				if (!is_file($rel_skin_file)) {
					$rel_skin_file = G5_SHOP_SKIN_PATH . '/' . $default['de_rel_list_skin'];
				}
				$sql = " SELECT b.* from {$g5['g5_shop_item_relation_table']} a left join {$g5['g5_shop_item_table']} b on (a.it_id2=b.it_id) where a.it_id = '{$it['it_id']}' and b.it_use='1' ";
				$list = new item_list($rel_skin_file, $default['de_rel_list_mod'], 0, $default['de_rel_img_width'], $default['de_rel_img_height']);
				$list->set_query($sql);
				echo $list->run();
				?>
			</section>
		<?php } ?>

		<section class="item-infomation">
			<div class="mb-5">
				<ul class="item-nav nav nav-tabs nav-justified mb-3 item-tab-nav sticky-top" id='tabs'>
					<li class="nav-item"><button type="button" id="goods-info" class="show active goods-info nav-link  fw-bolder active h-100" data-bs-toggle='tab' data-bs-target='#tab-goods-info' role="tab" aria-controls="tab-goods-info" aria-selected="true">상품정보</button></li>
					<li class="nav-item"><button type="button" id="btn_sit_use" class="goods-review nav-link fw-bolder h-100" data-bs-toggle='tab' data-bs-target='#tab-goods-review' role="tab" aria-controls="tab-goods-review" aria-selected="false"><span class="d-none d-md-inline">사용</span>후기(<?php echo $item_use_count; ?>)</span></button></li>
					<li class="nav-item"><button type="button" id="btn_sit_qa" class="goods-qa nav-link fw-bolder h-100" data-bs-toggle='tab' data-bs-target='#tab-goods-qa' role="tab" aria-controls="tab-goods-qa" aria-selected="false"><span class="d-none d-md-inline">상품</span>문의(<?php echo $item_qa_count; ?>)</span></button></li>
					<li class="nav-item"><button type="button" id="btn_sit_dvex" class="goods-delivery nav-link fw-bolder h-100" data-bs-toggle='tab' data-bs-target='#tab-goods-delivery' role="tab" aria-controls="tab-goods-delivery" aria-selected="false">배송/교환</button></li>
				</ul>
				<div class="tab-content" id='tabs-content'>
					<div class="tab-pane active p-2 p-lg-4" id="tab-goods-info" role="tabpanel" aria-labelledby="goods-info" tabindex="0">
						<h2 class="visually-hidden"><span>상품 정보</span></h2>
						<?php
						if ($it['it_info_value']) {
							$info_data = unserialize(stripslashes($it['it_info_value']));
							if (is_array($info_data)) {
								$gubun = $it['it_info_gubun'];
								$info_array = $item_info[$gubun]['article'];
						?>
								<table class="table table-bordered item-infor-policy">
									<caption>상품 정보 고시</caption>
									<tbody>
										<?php
										foreach ($info_data as $key => $val) {
											$ii_title = $info_array[$key][0];
											$ii_value = $val;
										?>
											<tr>
												<th scope="row"><?php echo $ii_title; ?></th>
												<td><?php echo $ii_value; ?></td>
											</tr>
										<?php } //foreach
										?>
									</tbody>
								</table>
						<?php
							} else {
								if ($is_admin) {
									echo '<div class="alert alert-danger">상품 정보 고시 정보가 올바르게 저장되지 않았습니다.<br>config.php 파일의 G5_ESCAPE_FUNCTION 설정을 addslashes 로<br>변경하신 후 관리자 &gt; 상품정보 수정에서 상품 정보를 다시 저장해주세요. </div>';
								}
							}
						} //if
						?>
						<?php if ($it['it_explan']) { ?>
							<h3 class="fs-5 fw-bolder mt-5 border-bottom pb-2">상품 상세설명</h3>
							<div id="sit_inf_explan">
								<?php echo conv_content($it['it_explan'], 1); ?>
							</div>
						<?php } ?>



					</div>
					<div class="tab-pane" id="tab-goods-review" role="tabpanel" aria-labelledby="goods-review" tabindex="0">
						<h2 class="visually-hidden">사용후기</h2>
						<div id="itemuse"><?php include_once(G5_SHOP_PATH . '/itemuse.php'); ?></div>
					</div>

					<div class="tab-pane" id="tab-goods-qa" role="tabpanel" aria-labelledby="goods-qa" tabindex="0">
						<h2 class="visually-hidden">상품문의</h2>
						<div id="itemqa"><?php include_once(G5_SHOP_PATH . '/itemqa.php'); ?></div>
					</div>

					<div class="tab-pane" id="tab-goods-delivery" role="tabpanel" aria-labelledby="goods-delivery" tabindex="0">
						<h2 class="visually-hidden">배송/교환정보</h2>
						<div class="p-2 p-lg-4">
							<?php if ($default['de_baesong_content']) { ?>
								<!-- 배송 시작 { -->
								<div class="delivery-content">
									<h3 class="fs-5 fw-bolder ff-noto">배송</h3>
									<?php echo conv_content($default['de_baesong_content'], 1); ?>
								</div>
								<!-- } 배송 끝 -->
							<?php } ?>

							<?php if ($default['de_change_content']) { ?>
								<!-- 교환 시작 { -->
								<div class='change-content'>
									<h3 class="fs-5 fw-bolder ff-noto">교환</h3>
									<?php echo conv_content($default['de_change_content'], 1); ?>
								</div>
								<!-- } 교환 끝 -->
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
			<script>
				$(function() {
					$(".tab_con>li").hide();
					$(".tab_con>li:first").show();
					$(".tab_tit li button").click(function() {
						$(".tab_tit li button").removeClass("selected");
						$(this).addClass("selected");
						$(".tab_con>li").hide();
						$($(this).attr("rel")).show();
					});
				});
			</script>
		</section>

		<script>
			jQuery(function($) {
				var change_name = "ct_copy_qty";

				$(document).on("select_it_option_change", "select.it_option", function(e, $othis) {
					var value = $othis.val(),
						change_id = $othis.attr("id").replace("it_option_", "it_side_option_");

					if ($("#" + change_id).length) {
						$("#" + change_id).val(value).attr("selected", "selected");
					}
				});

				$(document).on("select_it_option_post", "select.it_option", function(e, $othis, idx, sel_count, data) {
					var value = $othis.val(),
						change_id = $othis.attr("id").replace("it_option_", "it_side_option_");

					$("select.it_side_option").eq(idx + 1).empty().html(data).attr("disabled", false);

					// select의 옵션이 변경됐을 경우 하위 옵션 disabled
					if ((idx + 1) < sel_count) {
						$("select.it_side_option:gt(" + (idx + 1) + ")").val("").attr("disabled", true);
					}
				});

				$(document).on("add_sit_sel_option", "#sit_sel_option", function(e, opt) {

					opt = opt.replace('name="ct_qty[', 'name="' + change_name + '[');

					var $opt = $(opt);
					$opt.removeClass("sit_opt_list");
					$("input[type=hidden]", $opt).remove();

					$(".sit_sel_option .sit_opt_added").append($opt);

				});

				$(document).on("price_calculate", "#sit_tot_price", function(e, total) {

					$(".sum_section .sit_tot_price").empty().html("<span>총 금액 </span><strong>" + number_format(String(total)) + "</strong> 원");

				});

				$(".sit_side_option").on("change", "select.it_side_option", function(e) {
					var idx = $("select.it_side_option").index($(this)),
						value = $(this).val();

					if (value) {
						if (typeof(option_add) != "undefined") {
							option_add = true;
						}

						$("select.it_option").eq(idx).val(value).attr("selected", "selected").trigger("change");
					}
				});

				$(".sit_side_option").on("change", "select.it_side_supply", function(e) {
					var value = $(this).val();

					if (value) {
						if (typeof(supply_add) != "undefined") {
							supply_add = true;
						}

						$("select.it_supply").val(value).attr("selected", "selected").trigger("change");
					}
				});

				$(".sit_opt_added").on("click", "button", function(e) {
					e.preventDefault();

					var $this = $(this),
						mode = $this.text(),
						$sit_sel_el = $("#sit_sel_option"),
						li_parent_index = $this.closest('li').index();

					if (!$sit_sel_el.length) {
						alert("el 에러");
						return false;
					}

					switch (mode) {
						case "증가":
							$sit_sel_el.find("li").eq(li_parent_index).find(".sit_qty_plus").trigger("click");
							break;
						case "감소":
							$sit_sel_el.find("li").eq(li_parent_index).find(".sit_qty_minus").trigger("click");
							break;
						case "삭제":
							$sit_sel_el.find("li").eq(li_parent_index).find(".sit_opt_del").trigger("click");
							break;
					}

				});

				$(document).on("sit_sel_option_success", "#sit_sel_option li button", function(e, $othis, mode, this_qty) {
					var ori_index = $othis.closest('li').index();

					switch (mode) {
						case "증가":
						case "감소":
							$(".sit_opt_added li").eq(ori_index).find("input[name^=ct_copy_qty]").val(this_qty);
							break;
						case "삭제":
							$(".sit_opt_added li").eq(ori_index).remove();
							break;
					}
				});

				$(document).on("change_option_qty", "input[name^=ct_qty]", function(e, $othis, val, force_val) {
					var $this = $(this),
						ori_index = $othis.closest('li').index(),
						this_val = force_val ? force_val : val;

					$(".sit_opt_added").find("li").eq(ori_index).find("input[name^=" + change_name + "]").val(this_val);
				});

				$(".sit_opt_added").on("keyup paste", "input[name^=" + change_name + "]", function(e) {
					var $this = $(this),
						val = $this.val(),
						this_index = $("input[name^=" + change_name + "]").index(this);

					$("input[name^=ct_qty]").eq(this_index).val(val).trigger("keyup");
				});

				$(".sit_order_btn").on("click", "button", function(e) {
					e.preventDefault();

					var $this = $(this);

					if ($this.hasClass("sit_btn_cart")) {
						$("#sit_ov_btn .sit_btn_cart").trigger("click");
					} else if ($this.hasClass("sit_btn_buy")) {
						$("#sit_ov_btn .sit_btn_buy").trigger("click");
					}
				});

				if (window.location.href.split("#").length > 1) {
					let id = window.location.href.split("#")[1];
					$("#btn_" + id).trigger("click");
				};
			});
		</script>
	</div>
</div>