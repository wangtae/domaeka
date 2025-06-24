<?php

/**
 * 인기검색어 
 */
if (!defined("_GNUBOARD_")) {
    exit; // 개별 페이지 접근 불가
}
?>
<section class='pop-text-wrap'>
    <span class="visually-hidden">인기검색어</span>
    <div class="pop-text">
        <?php
        if (isset($list) && is_array($list)) {
            for ($i = 0; $i < count($list); $i++) {
        ?>
                <span class='badge bg-light'><a href="<?php echo G5_BBS_URL ?>/search.php?sfl=wr_subject&amp;sop=and&amp;stx=<?php echo urlencode($list[$i]['pp_word']) ?>"><?php echo get_text($list[$i]['pp_word']); ?></a></span>
        <?php
            }   //end for
        }   //end if
        ?>
    </div>
</section>