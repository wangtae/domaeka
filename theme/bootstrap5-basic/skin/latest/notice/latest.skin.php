<?php

/**
 * Latest skin - Notice
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/latest/latest-notice.css">', 120);

$list_count = (is_array($list) && $list) ? count($list) : 0;
?>
<section class="latest latest-notice">
    <div class="mb-3">
        <h3 class="latest-title fs-5 mb-0"><a href="<?php echo get_pretty_url($bo_table); ?>"><?php echo $bo_subject ?></a></h3>
        <div class='p-2 lists'>
            <?php for ($i = 0; $i < $list_count; $i++) { ?>
                <div class='lists-group mx-auto text-truncate pt-1 pb-1'>
                    <?php
                    echo "<span class='badge text-bg-secondary'>{$list[$i]['datetime2']}</span>";
                    echo "<a href='{$list[$i]['href']}' class='lists-link text-truncate'> ";
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