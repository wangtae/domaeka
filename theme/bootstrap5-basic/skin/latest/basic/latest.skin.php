<?php

/**
 * Latest skin - Basic
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/latest/latest-basic.css">', 120);
$list_count = (is_array($list) && $list) ? count($list) : 0;
?>
<section class="latest latest-basic shadow-sm">
    <div class="border mb-3">
        <h3 class="latest-title border-bottom fs-5 mb-0"><a href="<?php echo get_pretty_url($bo_table); ?>"><?php echo $bo_subject ?></a></h3>
        <div class='p-2 lists'>
            <?php for ($i = 0; $i < $list_count; $i++) { ?>
                <div class='lists-group mx-auto text-truncate pt-1 pb-1'>
                    <?php
                    if ($list[$i]['is_notice']) {
                        //공지사항
                        echo "<span class='ca-name badge text-bg-info opacity-75'>공지</span><span class='text-primary'> {$list[$i]['subject']}</span>";
                    } else {
                        echo "<span class='ca-name badge text-bg-info opacity-75'>{$list[$i]['ca_name']}</span> ";
                    }
                    echo "<span class='badge text-bg-light'>{$list[$i]['datetime2']}</span>";
                    //비밀글
                    if (isset($list[$i]['icon_secret']) && $list[$i]['icon_secret'] != '') {
                        echo "<span class='badge text-bg-light'><i class='bi bi-lock-fill'></i></span><span class='visually-hidden'>비밀글</span>";
                    }

                    //인기게시물
                    if (isset($list[$i]['icon_hot']) && $list[$i]['icon_hot'] != '') {
                        echo "<span class='latest-info badge text-bg-light'><i class='bi bi-star-fill'></i></span><span class='visually-hidden'>인기게시물</span>";
                    }
                    //새게시물
                    if (isset($list[$i]['icon_new']) && $list[$i]['icon_new'] != '') {
                        echo "<span class='latest-info badge text-bg-light'>N</i></span><span class='visually-hidden'>새게시물</span>";
                    }

                    //댓글수
                    if (isset($list[$i]['comment_cnt']) && $list[$i]['comment_cnt']) {
                        echo "<span class='badge text-bg-light'><i class='bi bi-chat'></i> {$list[$i]['comment_cnt']}</span>";
                    }

                    echo "<a href='{$list[$i]['href']}' class='lists-link'> ";
                    echo $list[$i]['subject'];
                    echo "</a>";
                    ?>
                </div>
            <?php } ?>
            <?php if ($list_count == 0) { ?>
                <div class="empty-item text-center align-middle">
                    <div class="p-5"><i class="bi bi-exclamation-circle"></i> 게시물이 없습니다.</div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>