<?php

/**
 * Latest skin - Gallery
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
$list_count = (is_array($list) && $list) ? count($list) : 0;

include_once G5_LIB_PATH . '/thumbnail.lib.php';
$thumb_width = 400;
$thumb_height = 300;
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/latest/latest-gallery.css">', 120);

?>

<section class="latest latest-gallery shadow-sm">
    <div class='border'>
        <h3 class="latest-title border-bottom fs-5 mb-0"><a href="<?php echo get_pretty_url($bo_table); ?>" class='title-link'><?php echo $bo_subject ?></a></h3>
        <div class='p-2 lists'>
            <div class='row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4'>
                <?php
                for ($i = 0; $i < $list_count; $i++) {
                    $thumb = get_list_thumbnail($bo_table, $list[$i]['wr_id'], $thumb_width, $thumb_height, false, true);
                    $img_content = "<img src='" . G5_THEME_IMG_URL . "/noimage.png' alt='이미지없음' class='card-img img-fluid'>";
                    if (isset($thumb['src']) && $thumb['src']) {
                        $img = $thumb['src'];
                        $img_content = "<img src='{$img}' alt='{$thumb['alt']}' class='card-img img-fluid'>";
                    }
                ?>
                    <div class='items col'>
                        <div class="card">
                            <a href="<?php echo $list[$i]['href'] ?>" class="d-block position-relative">
                                <div class='ratio ratio-4x3'>
                                    <?php echo $img_content; ?>
                                </div>
                                <div class='position-absolute category opacity-75'>
                                    <?php
                                    if ($list[$i]['is_notice']) {
                                        //공지사항
                                        echo "<span class='ca-name badge text-bg-info opacity-75'>공지</span><span class='text-primary'> {$list[$i]['subject']}</span>";
                                    } else {
                                        echo "<span class='ca-name badge text-bg-info opacity-75'>{$list[$i]['ca_name']}</span> ";
                                    } ?>
                                </div>
                                <div class='position-absolute list-info opacity-75'>
                                    <?php
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
                                    echo "<span class='badge text-bg-light'>{$list[$i]['datetime2']}</span>";
                                    ?>
                                </div>
                            </a>

                            <div class="card-body">
                                <div class="card-title mb-0">
                                    <?php
                                    echo "<a href='{$list[$i]['href']}' class='subject text-link text-truncate d-block'> ";
                                    if ($list[$i]['is_notice']) {
                                        echo "<span class='text-primary'>{$list[$i]['subject']}</span>";
                                    } else {
                                        echo $list[$i]['subject'];
                                    }
                                    echo "</a>";
                                    ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php } //for
                //게시물 없음
                if ($list_count == 0) {
                    echo "<div class='col-sm-12 col-md-12 col-lg-12'><div class='no-items text-center p-5'>게시물이 없습니다.</div></div>";
                }
                ?>
            </div>
        </div>
    </div>
</section>