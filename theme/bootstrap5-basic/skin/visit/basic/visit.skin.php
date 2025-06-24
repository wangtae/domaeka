<?php

/**
 *  방문자 출력
 */
if (!defined("_GNUBOARD_")) {
    exit;
}

global $is_admin;
?>

<div class="tail-visit">
    <span class="visually-hidden">접속자집계</span>
    <ul class="list-group list-group-horizontal">
        <li class="list-group-item">
            오늘 : <?php echo number_format($visit['1']) ?>
        </li>
        <li class="list-group-item">
            어제 : <?php echo number_format($visit['2']) ?>
        </li>
        <li class="list-group-item">
            최대 : <?php echo number_format($visit['3']) ?>
        </li>
        <li class="list-group-item">
            전체 : <?php echo number_format($visit['4']) ?>
        </li>
        <?php if ($is_admin == "super") {  ?>
            <li class="list-group-item">
                <a href="<?php echo G5_ADMIN_URL ?>/visit_list.php" class=""><i class="bi bi-gear-fill"></i><span class="visually-hidden">관리자</span></a>
            </li>
        <?php } ?>
    </ul>
</div>