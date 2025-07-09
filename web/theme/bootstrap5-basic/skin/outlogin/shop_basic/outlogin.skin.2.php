<?php
/**
 * Shop Basic outlogin 2
 */
if (!defined("_GNUBOARD_")) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/outlogin/outlogin-basic.css">', 120);
?>

<div class="dropdown">
    <button class="btn btn-outline-info me-1 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="profile_img"><?php echo get_member_profile_img($member['mb_id'], 30, 30); ?></span>
        <span class="profile_name"><?php echo $nick ?></span>님
    </button>

    <ul class="dropdown-menu">
        <li class="dropdown-item"><a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=register_form.php">정보수정</a></li>
        <li class="dropdown-item"><a href="<?php echo G5_BBS_URL ?>/point.php" target="_blank" class="win_point">포인트<strong><?php echo $point ?></strong></a></li>
        <li class="dropdown-item"><a href="<?php echo G5_SHOP_URL ?>/coupon.php" target="_blank" class="win_coupon">쿠폰<strong><?php echo number_format(get_shop_member_coupon_count($member['mb_id'], true)); ?></strong></a></li>
        <li class="dropdown-item"><a href="<?php echo G5_BBS_URL ?>/memo.php" target="_blank" class="win_memo"><span class="visually-hidden">안 읽은</span>쪽지<strong><?php echo $memo_not_read ?></strong></a></li>
        <li class="dropdown-item"><a href="<?php echo G5_SHOP_URL; ?>/mypage.php">마이페이지</a></li>
        <?php if ($is_admin == 'super' || $is_auth) {  ?>
            <li class="dropdown-item"><a href="<?php echo G5_ADMIN_URL ?>">관리자</a></li>
        <?php }  ?>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-item"><a href="<?php echo G5_BBS_URL; ?>/logout.php" id="ol_after_logout">로그아웃</a></li>
    </ul>
</div>
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
    });
    */
    // 탈퇴의 경우 아래 코드를 연동하시면 됩니다.
    function member_leave() {
        if (confirm("정말 회원에서 탈퇴 하시겠습니까?"))
            location.href = "<?php echo G5_BBS_URL ?>/member_confirm.php?url=member_leave.php";
    }
</script>
<!-- } 로그인 후 아웃로그인 끝 -->