<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

// 쿠폰
$cp_count = 0;
$sql = " SELECT cp_id
            from {$g5['g5_shop_coupon_table']}
            where mb_id IN ( '{$member['mb_id']}', '전체회원' )
              and cp_start <= '" . G5_TIME_YMD . "'
              and cp_end >= '" . G5_TIME_YMD . "' ";
$res = sql_query($sql);

for ($k = 0; $cp = sql_fetch_array($res); $k++) {
    if (!is_used_coupon($member['mb_id'], $cp['cp_id']))
        $cp_count++;
}
?>
<section class="outlogin-wrap mobile after bg-light border p-2">
    <h2 class="visually-hidden">나의 회원정보</h2>
    <div class='d-flex justify-content-around'>
        <div class="profile-image pe-2">
            <?php echo get_member_profile_img($member['mb_id']); ?>
        </div>
        <?php echo $nick ?>님 <br />
        안녕하세요!
    </div>
    <div class="text-center pt-2 pb-2 mt-2 border-top">
        <a href="<?php echo G5_BBS_URL ?>/point.php" id="btn-point-mobile" <?php echo defined('BB_POINT_POPUP') && BB_POINT_POPUP === true ? "target='_blank'" : "data-bs-toggle='modal' data-bs-target='#point-modal'"; ?> class="<?php echo defined('BB_POINT_POPUP') && BB_POINT_POPUP === true ? "win_point" : ""; ?>">
            <i class="fa fa-database" aria-hidden="true"></i>
            <?php echo $point; ?>
        </a>
        <a href="<?php echo G5_BBS_URL ?>/memo.php" id="btn-memo-mobile" <?php echo defined('BB_MEMO_POPUP') && BB_MEMO_POPUP === true ? "target='_blank'" : "data-bs-toggle='modal' data-bs-target='#memo-modal'"; ?> class="<?php echo defined('BB_MEMO_POPUP') && BB_MEMO_POPUP === true ? "win_memo" : ""; ?>">
            <i class="fa fa-envelope-o" aria-hidden="true"></i>
            <?php echo $memo_not_read; ?>
        </a>
        <a href="<?php echo G5_BBS_URL ?>/scrap.php" <?php echo defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP === true ? "target='_blank'" : "data-bs-toggle='modal' data-bs-target='#scrap-modal'"; ?> id="btn-scrap-mobile" class="<?php echo defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP === true ? "win_scrap" : ""; ?>">
            <i class="bi bi-bookmarks"></i>
            <?php echo $mb_scrap_cnt; ?>
        </a>
        <a href="<?php echo G5_SHOP_URL ?>/coupon.php" <?php echo defined('BB_COUPON_POPUP') && BB_COUPON_POPUP === true ? "target='_blank'" : "data-bs-toggle='modal' data-bs-target='#coupon-modal'"; ?> id="btn-coupon-mobile" class="<?php echo defined('BB_COUPON_POPUP') && BB_COUPON_POPUP === true ? "win_coupon" : ""; ?>">
            <i class="fa fa-ticket" aria-hidden="true"></i>쿠폰
            <strong><?php echo number_format($cp_count); ?></strong>
        </a>
    </div>
    <div class='btn btn-toolbar justify-content-around'>
        <div class='btn-group btn-group-sm'>
            <?php if ($is_admin == 'super' || $is_auth) {  ?>
                <a href="<?php echo correct_goto_url(G5_ADMIN_URL); ?>" class="btn btn-outline-primary" title="관리자"><span class="visually-hidden">관리자</span><i class="bi bi-gear-fill"></i></a>
            <?php } ?>
            <a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=register_form.php" title="정보수정" class='btn btn-outline-success'><i class="bi bi-person-fill-gear"></i>정보수정</a>
            <a href="<?php echo G5_BBS_URL ?>/logout.php" class='btn btn-outline-secondary'><i class="fa fa-sign-out" aria-hidden="true"></i> 로그아웃</a>
        </div>
    </div>
</section>
<script>
    /*
         //팝업을 모달로 대체할때 사용.
         $(function() {
        <?php if (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == false) { ?>
            //쪽지 모달 사용
            $('#btn-memo, #btn-memo-mobile').on('click', function() {
                var Url = $(this).attr('href');
                $('#memo-iframe').attr('src', Url);
            });
        <?php } ?>
        <?php if (defined('BB_POINT_POPUP') && BB_POINT_POPUP == false) { ?>
            //포인트내역 모달 사용
            $('#btn-point, #btn-point-mobile').on('click', function() {
                var Url = $(this).attr('href');
                $('#point-iframe').attr('src', Url);
            });
        <?php } ?>
        <?php if (defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP == false) { ?>
            //scrap 모달 사용
            $('#btn-scrap, #btn-scrap-mobile').on('click', function() {
                var Url = $(this).attr('href');
                $('#scrap-iframe').attr('src', Url);
            });
        <?php } ?>
        <?php if (defined('BB_COUPON_POPUP') && BB_COUPON_POPUP == false) { ?>
            //쿠폰 모달 사용
            $('#btn-coupon, #btn-coupon-mobile').on('click', function() {
                var Url = $(this).attr('href');
                $('#coupon-iframe').attr('src', Url);
            });
        <?php } ?>
    });
    */
    // 탈퇴의 경우 아래 코드를 연동하시면 됩니다.
    function member_leave() {
        if (confirm("정말 회원에서 탈퇴 하시겠습니까?"))
            location.href = "<?php echo G5_BBS_URL ?>/member_confirm.php?url=member_leave.php";
    }
</script>
<!-- } 로그인 후 아웃로그인 끝 -->