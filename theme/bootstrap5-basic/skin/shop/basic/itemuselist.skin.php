<?php
if (!defined("_GNUBOARD_")) {
	exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/shop/itemuselist.css">', 200);
?>
<div class="itemuselist-wrap container mt-2">
	<div class="bg-white p-4 border mb-2">
		<h1 class="fs-4 fw-bolder mb-0"><i class="bi bi-chat-text-fill"></i> <?php echo $g5['title'] ?></h1>
	</div>
	<div class="bg-white border border-box">
		<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

		<form method="get" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
			<div class="d-flex justify-content-center m-5">
				<div class="d-flex col-sm-12 col-md-6">
					<label for="sfl" class="visually-hidden">검색항목 필수</label>
					<div class="input-group">
						<select name="sfl" id="sfl" required class="form-select">
							<option value="">선택</option>
							<option value="b.it_name" <?php echo get_selected($sfl, "b.it_name"); ?>>상품명</option>
							<option value="a.it_id" <?php echo get_selected($sfl, "a.it_id"); ?>>상품코드</option>
							<option value="a.is_subject" <?php echo get_selected($sfl, "a.is_subject"); ?>>후기제목</option>
							<option value="a.is_content" <?php echo get_selected($sfl, "a.is_content"); ?>>후기내용</option>
							<option value="a.is_name" <?php echo get_selected($sfl, "a.is_name"); ?>>작성자명</option>
							<option value="a.mb_id" <?php echo get_selected($sfl, "a.mb_id"); ?>>작성자아이디</option>
						</select>

						<label for="stx" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
						<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="form-control" placeholder="검색어 입력">
						<button type="submit" value="검색" class="btn btn-outline-info"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">검색</span></button>
						<?php echo (isset($stx) && $stx != '') ? "<a href='{$_SERVER['SCRIPT_NAME']}' class='input-group-text'>전체</a>" : ""; ?>
					</div>
				</div>
			</div>
		</form>

		<div class="itemuselist">
			<?php
			$thumbnail_width = 400;

			for ($i = 0; $row = sql_fetch_array($result); $i++) {
				$num = $total_count - ($page - 1) * $rows - $i;
				$star = get_star($row['is_score']);

				$is_content = get_view_thumbnail(conv_content($row['is_content'], 1), $thumbnail_width);

				$row2 = get_shop_item($row['it_id'], true);
				$it_href = shop_item_url($row['it_id']);

				if ($i == 0) {
					echo '<div class="d-flex flex-column justify-content-start">';
				}
			?>
				<div class="item-wrap mt-5 mb-5">
					<ul class="list-group border-0 list-group-horizontal mb-2 border-bottom border-top justify-content-between">
						<li class="list-group-item border-0 p-1"><i class="bi bi-person" aria-hidden="true"></i> <?php echo $row['is_name']; ?></li>
						<li class="list-group-item border-0 p-1"><i class="bi bi-clock" aria-hidden="true"></i> <?php echo substr($row['is_time'], 0, 10); ?></li>
						<li class="list-group-item border-0 p-1 d-flex flex-fill justify-content-end">
							<div class="item-star d-flex text-end align-content-center flex-wrap"><img src="<?php echo G5_URL; ?>/shop/img/s_star<?php echo $star; ?>.png" alt="별<?php echo $star; ?>개" width="80"></div>
						</li>
					</ul>
					<div class="item-image d-flex flex-row flex-wrap justify-content-center justify-content-md-start">
						<a href="<?php echo $it_href; ?>" class="item-image-link d-flex flex-column justify-content-center ms-2">
							<?php echo get_it_image($row['it_id'], 400, 300); ?>
							<button class="prd_detail btn btn-outline-secondary mt-1 border-0" data-url="<?php echo G5_SHOP_URL . '/largeimage.php?it_id=' . $row['it_id']; ?>"><i class="bi bi-camera" aria-hidden="true"></i><span class="visually-hidden">상품 이미지보기</span></button>
						</a>
						<div class="item-info ms-2">
							<a href="<?php echo $it_href; ?>" class="item-image-link item-title mb-2 d-flex">
								<?php echo $row2['it_name']; ?>
							</a>
							<div class="item-text">
								<div class="product-thumbnail d-flex flex-row">
									<?php echo get_itemuse_thumb($row['is_content'], 60, 60); ?>
									<div class="product ms-2">
										<div class="product-name"><?php echo get_text($row2['it_name']); ?></div>
										<div class="product-subject"><?php echo get_text($row['is_subject']); ?></div>
									</div>
								</div>
							</div>
						</div>

					</div>
					<div class="review-detail">
						<div class="accordion" id="detail-review<?php echo $i ?>">
							<div class="accordion-item border-0 border-top">
								<h2 class="accordion-header" id="heading<?php echo $i ?>">
									<button class="accordion-button collapsed fw-bolder" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i ?>" aria-expanded="true" aria-controls="collapse<?php echo $i ?>">
										<i class="bi bi-camera2"></i> &nbsp; 후기 상세보기
									</button>
								</h2>
								<div id="collapse<?php echo $i ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo $i ?>" data-bs-parent="#detail-review<?php echo $i ?>">
									<div class="accordion-body">
										<h3 class="visually-hidden">사용후기</h3>
										<?php echo $is_content; ?>
										<?php
										//사용후기 답변이 있다면
										if (!empty($row['is_reply_subject'])) {
											$is_reply_content = get_view_thumbnail(conv_content($row['is_reply_content'], 1), $thumbnail_width);
										?>
											<div class="review-reply-wrap border-top pt-2 mt-2 d-flex flex-column">
												<h2 class="fs-6 fw-bolder"><?php echo get_text($row['is_reply_subject']); ?></h2>
												<div class="reply-name ms-auto">
													<i class="bi bi-person" aria-hidden="true"></i> <?php echo $row['is_reply_name']; ?>
												</div>
												<div class='reply-content'>
													<?php echo $is_reply_content; ?>
												</div>
											</div>
										<?php } ?>
									</div>
								</div>
							</div>

						</div>

					</div>

				</div>
			<?php }
			if ($i > 0) {
				echo '</div>';
			}
			if ($i == 0) {
				echo '<p class="empty-item p-4 text-center">자료가 없습니다.</p>';
			}
			?>
		</div>
		<div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
			<?php
			//반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
			$write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,  "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page=");
			$paging = str_replace("sound_only", "visually-hidden", $write_pages);
			$paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
			$paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
			$paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
			$paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
			echo $paging;
			?>
		</div>
		<script>
			jQuery(function($) {

				// 상품이미지 크게보기
				$(".prd_detail").click(function() {
					var url = $(this).attr("data-url");
					var top = 10;
					var left = 10;
					var opt = 'scrollbars=yes,top=' + top + ',left=' + left;
					popup_window(url, "largeimage", opt);

					return false;
				});
			});
		</script>
	</div>
</div>