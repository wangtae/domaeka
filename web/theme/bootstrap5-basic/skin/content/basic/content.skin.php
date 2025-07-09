<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/content/content-basic.css">', 120);
?>

<section class="content-wrap container">
    <div class="bg-white p-3 p-lg-4 border mb-2 mt-2">
        <h1 class="fs-3 fw-bolder mb-0"><?php echo $g5['title']; ?></h1>
    </div>

    <div class="bg-white p-2 p-lg-4 border">
        <?php echo $str; ?>
    </div>

</section>