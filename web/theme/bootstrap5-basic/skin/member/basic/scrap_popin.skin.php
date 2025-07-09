<?php
if (!defined('_GNUBOARD_')) {
	exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/scrap.css">', 120);
?>
<div class="new_win scrap-wrap">
	<div class="point-header container-fluid mt-2 mb-2">
		<div class="bg-white border p-3 p-lg-4">
			<h1 class="fs-3 fw-bolder"><i class="bi bi-bookmark-plus-fill"></i> 스크랩하기</h1>
		</div>
	</div>
	<div class="container-fluid">
		<div class="bg-white p-2 p-lg-4 border">
			<form name="f_scrap_popin" action="./scrap_popin_update.php" method="post">
				<input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
				<input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
				<div class="new_win_con">
					<h2 class="visually-hidden">제목 확인 및 댓글 쓰기</h2>
					<ul class="list-group list-group-flush">
						<li class="list-group-item">
							<span class="visually-hidden">제목</span>
							<?php echo get_text(cut_str($write['wr_subject'], 255)) ?>
						</li>
						<li class="list-group-item">
							<label for="wr_content">댓글작성</label>
							<textarea name="wr_content" id="wr_content" class="form-control" rows="6"></textarea>
						</li>
					</ul>
				</div>
				<div class="alert alert-info"><i class="bi bi-info-circle"></i> 스크랩을 하시면서 감사 혹은 격려의 댓글을 남기실 수 있습니다.</div>

				<div class="d-flex justify-content-center p-2">
					<button type="submit" class="btn btn-outline-primary">스크랩 확인</button>
					<?php if ($is_member && (defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP == true)) { ?>
						<button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close ms-1">창닫기</button>
					<?php } ?>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
	//iframe 높이 맞추기
	$(function() {
		var parentHeight = $(window.parent.document).find('.scrap-modal-body').height();
		$(window.parent.document).find('.scrap-modal-body iframe').height(parentHeight + 'px');
	});
</script>