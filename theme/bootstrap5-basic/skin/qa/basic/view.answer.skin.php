<?php
if (!defined("_GNUBOARD_")) {
	exit;
}
//답변 스킨
?>

<section id="bo_v_ans" class='qa-reply-wrap mt-5'>
	<h2 class="page-title p-2"><span class="bo_v_reply"><i class="bi bi-reply" aria-hidden="true"></i></span> <?php echo get_text($answer['qa_subject']); ?></h2>
	<header>
		<div id="ans_datetime" class='d-flex justify-content-end'>
			<i class="bi bi-calendar2-event-fill"></i> &nbsp; <?php echo $answer['qa_datetime']; ?>
		</div>

		<?php if ($answer_update_href || $answer_delete_href) { ?>
			<div class="reply-button btn-navbar mb-5 border-top pt-5 pb-5 d-flex justify-content-end">
				<?php if ($answer_update_href) { ?>
					<a href="<?php echo $answer_update_href; ?>" class="btn btn-primary" title="답변수정"> <i class="fa fa-pencil" aria-hidden="true"></i> 답변수정</a>
				<?php } ?>
				<?php if ($answer_delete_href) { ?>
					<a href="<?php echo $answer_delete_href; ?>" class="btn btn-danger" onclick="del(this.href); return false;" title="답변삭제"> <i class="fa fa-trash" aria-hidden="true"></i> 답변삭제</a>
				<?php } ?>
			</div>
		<?php } ?>
	</header>

	<div id="ans_con" class='qa-reply-contents'>
		<?php
		// 파일 출력
		if (isset($answer['img_count']) && $answer['img_count']) {
			echo "<div class='img-attach-wrap'>\n";

			for ($i = 0; $i < $answer['img_count']; $i++) {
				echo "<p class='img-fluid'>".get_view_thumbnail($answer['img_file'][$i], $qaconfig['qa_image_width']) . '</div>';
			}

			echo "</div>\n";
		}
		?>
		<?php echo get_view_thumbnail(conv_content($answer['qa_content'], $answer['qa_html']), $qaconfig['qa_image_width']); ?>
		<?php if (isset($answer['download_count']) && $answer['download_count']) { ?>
			<!-- 첨부파일 시작 { -->
			<section id="bo_v_file">
				<h2>첨부파일</h2>
				<ul>
					<?php
					// 가변 파일
					for ($i = 0; $i < $answer['download_count']; $i++) {
					?>
						<li>
							<i class="fa fa-download" aria-hidden="true"></i>
							<a href="<?php echo $answer['download_href'][$i];  ?>" class="view_file_download" download>
								<strong><?php echo $answer['download_source'][$i] ?></strong>
							</a>
						</li>
					<?php
					}
					?>
				</ul>
			</section>
			<!-- } 첨부파일 끝 -->
		<?php } ?>
	</div>

</section>
<div class="bo_v_btn btn-navbar d-flex justify-content-end mt-2 mb-2">
	<a href="<?php echo $rewrite_href; ?>" class="btn btn-primary" title="추가질문"><i class="fa fa-question" aria-hidden="true"></i> 추가질문</a>
</div>