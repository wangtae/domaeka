<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/connect/connect-basic.css">', 120);
?>

<div class="current-connect-wrap container">
    <div class="connect-header mt-2 mb-2">
        <div class="bg-white border p-2 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class="bg-white p-2 p-lg-4 border">
        <ul class="list-group list-group-flush">
            <?php
            for ($i = 0; $i < count($list); $i++) {
                //$location = conv_content($list[$i]['lo_location'], 0);
                $location = $list[$i]['lo_location'];
                // 최고관리자에게만 허용
                // 이 조건문은 가능한 변경하지 마십시오.
                if ($list[$i]['lo_url'] && $is_admin == 'super') {
                    $display_location = "<a href=\"" . $list[$i]['lo_url'] . "\">" . $location . "</a>";
                } else {
                    $display_location = $location;
                }
            ?>
                <li class="list-group-item d-flex justify-content-start">
                    <div class="d-flex flex-wrap align-content-center me-2"><?php echo $list[$i]['num'] ?></div>
                    <div class="d-flex flex-wrap align-content-center me-2"><?php echo get_member_profile_img($list[$i]['mb_id']); ?></div>
                    <div class="d-flex flex-wrap align-content-center">
                        <span class="crt_name"><?php echo $list[$i]['name'] ?></span>
                        <span class="crt_lct"><?php echo $display_location ?></span>
                    </div>
                </li>
            <?php
            }
            if ($i == 0) {
                echo "<li class='empty-item'><p class='p-2 p-lg-4 text-center'>현재 접속자가 없습니다.</p></li>";
            }            ?>
        </ul>
    </div>
</div>
<!--//.current-connect-wrap-->