<?php
if (!defined("_GNUBOARD_")) {
    exit;
}
?>

<!-- 쇼핑몰 커뮤니티 시작 { -->
<div class="community-side mt-5">
    <h5 class="fs-5 fw-bolder">커뮤니티</h5>
    <ul class="list-group list-group-flush">
        <?php
        $hsql = " SELECT bo_table, bo_subject from {$g5['board_table']} order by gr_id, bo_table ";
        $hresult = sql_query($hsql);
        for ($i = 0; $row = sql_fetch_array($hresult); $i++) {
            echo '<li class="list-group-item"><i class="fa fa-angle-right" aria-hidden="true"></i> <a href="' . get_pretty_url($row['bo_table']) . '">' . $row['bo_subject'] . '</a></li>' . PHP_EOL;
        }

        if ($i == 0) {
            echo '<li id="ist-group-item p-5">커뮤니티 준비 중</li>' . PHP_EOL;
        }
        ?>
    </ul>
</div>
<!-- } 쇼핑몰 커뮤니티 끝 -->