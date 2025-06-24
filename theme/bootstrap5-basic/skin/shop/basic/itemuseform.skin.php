<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

?>

<!-- 사용후기 쓰기 시작 { -->
<div id="sit_use_write" class="new_win container-fluid">
    <div class="bg-white border p-3 p-lg-4 mt-2 mb-2">
        <h1 id="win_title" class="fs-5 fw-bolder ff-noto mb-0"><i class="bi bi-pencil-fill"></i>사용후기 쓰기</h1>
    </div>
    <form name="fitemuse" method="post" action="<?php echo G5_SHOP_URL; ?>/itemuseformupdate.php" onsubmit="return fitemuse_submit(this);" autocomplete="off">
        <input type="hidden" name="w" value="<?php echo $w; ?>">
        <input type="hidden" name="it_id" value="<?php echo $it_id; ?>">
        <input type="hidden" name="is_id" value="<?php echo $is_id; ?>">

        <div class="new_win_con form_01 bg-white border p-2 p-lg-4 mb-5">
            <ul class="list-group border-0">
                <li class="list-group-item border-0">
                    <label for="is_subject" class="visually-hidden">제목<strong> 필수</strong></label>
                    <input type="text" name="is_subject" value="<?php echo get_text($use['is_subject']); ?>" id="is_subject" required class="required form-control" maxlength="250" placeholder="제목">
                </li>
                <li class="list-group-item border-0">
                    <strong class="visually-hidden">내용</strong>
                    <?php echo $editor_html; ?>
                </li>
                <li class="list-group-item border-0">
                    <span class="visually-hidden">평점</span>
                    <ul id="sit_use_write_star" class="list-group">
                        <li class="list-group-item border-0 d-flex align-content-center flex-wrap">
                            <input type="radio" name="is_score" value="5" id="is_score5" <?php echo ($is_score == 5) ? 'checked="checked"' : ''; ?>>
                            <label for="is_score5" class="d-flex align-content-center flex-wrap">&nbsp; 매우만족</label>
                            <img src="<?php echo G5_URL; ?>/shop/img/s_star5.png" alt="매우만족">
                        </li>
                        <li class="list-group-item border-0 d-flex align-content-center flex-wrap">
                            <input type="radio" name="is_score" value="4" id="is_score4" <?php echo ($is_score == 4) ? 'checked="checked"' : ''; ?>>
                            <label for="is_score4" class="d-flex align-content-center flex-wrap">&nbsp; 만족</label>
                            <img src="<?php echo G5_URL; ?>/shop/img/s_star4.png" alt="만족">
                        </li>
                        <li class="list-group-item border-0 d-flex align-content-center flex-wrap">
                            <input type="radio" name="is_score" value="3" id="is_score3" <?php echo ($is_score == 3) ? 'checked="checked"' : ''; ?>>
                            <label for="is_score3" class="d-flex align-content-center flex-wrap">&nbsp; 보통</label>
                            <img src="<?php echo G5_URL; ?>/shop/img/s_star3.png" alt="보통">
                        </li>
                        <li class="list-group-item border-0 d-flex align-content-center flex-wrap">
                            <input type="radio" name="is_score" value="2" id="is_score2" <?php echo ($is_score == 2) ? 'checked="checked"' : ''; ?>>
                            <label for="is_score2" class="d-flex align-content-center flex-wrap">&nbsp; 불만</label>
                            <img src="<?php echo G5_URL; ?>/shop/img/s_star2.png" alt="불만">
                        </li>
                        <li class="list-group-item border-0 d-flex align-content-center flex-wrap">
                            <input type="radio" name="is_score" value="1" id="is_score1" <?php echo ($is_score == 1) ? 'checked="checked"' : ''; ?>>
                            <label for="is_score1" class="d-flex align-content-center flex-wrap">&nbsp; 매우불만</label>
                            <img src="<?php echo G5_URL; ?>/shop/img/s_star1.png" alt="매우불만">
                        </li>
                    </ul>
                </li>
            </ul>

            <div class="d-flex justify-content-between mt-3">
                <button type="submit" class="btn_submit btn btn-outline-primary me-auto"><i class="bi bi-pen"></i> 작성완료</button>
                <button type="button" onclick="self.close();" class="btn_close btn btn-outline-danger">닫기</button>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    function fitemuse_submit(f) {
        <?php echo $editor_js; ?>

        return true;
    }
</script>
<!-- } 사용후기 쓰기 끝 -->