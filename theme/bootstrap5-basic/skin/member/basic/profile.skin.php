<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/profile.css">', 120);
?>

<div id="formmail" class="new_win formmail-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-envelope-at-fill"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class='container-fluid'>
        <div class='bg-white p-2 p-lg-4 border'>
            <div class="profile-name p-2">
                <div class="dropdown align-content-center writer z-index">
                    <span class="img-fluid">
                        <?php echo get_member_profile_img($mb['mb_id']); ?>
                    </span>
                    <a href="#name" class="badge text-bg-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?php echo get_text(strip_tags($mb['mb_nick'])); ?></a>
                    <div class="dropdown-menu p-2 shadow">
                        <?php echo $mb_nick ?>
                    </div>
                </div>
            </div>
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th scope="row"><i class="fa fa-star-o" aria-hidden="true"></i> 회원권한</th>
                        <td><?php echo $mb['mb_level'] ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><i class="fa fa-database" aria-hidden="true"></i> 포인트</th>
                        <td><?php echo number_format($mb['mb_point']) ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><i class="fa fa-clock-o" aria-hidden="true"></i> 회원가입일</th>
                        <td><?php echo ($member['mb_level'] >= $mb['mb_level']) ?  substr($mb['mb_datetime'], 0, 10) . " (" . number_format($mb_reg_after) . " 일)" : "알 수 없음";  ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><i class="fa fa-clock-o" aria-hidden="true"></i> 최종접속일</th>
                        <td><?php echo ($member['mb_level'] >= $mb['mb_level']) ? $mb['mb_today_login'] : "알 수 없음"; ?></td>
                    </tr>
                    <?php if ($mb_homepage) {  ?>
                        <tr>
                            <th scope="row"><i class="fa fa-home" aria-hidden="true"></i> 홈페이지</th>
                            <td colspan="3"><a href="<?php echo $mb_homepage ?>" target="_blank"><?php echo $mb_homepage ?></a></td>
                        </tr>
                    <?php }  ?>

                </tbody>
            </table>
            <section class="alert alert-success p-2 p-lg-4">
                <h2 class="fs-4 mb-3">인사말</h2>
                <hr />
                <p><?php echo $mb_profile ?></p>
            </section>
            <?php if ($is_member && (defined('BB_PROFILE_POPUP') && BB_PROFILE_POPUP == true)) { ?>
                <div class='d-flex justify-content-center p-5'>
                    <button type="button" onclick="javascript:window.close();" class="btn btn-danger btn_close">창닫기</button>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
    //iframe 높이 맞추기
    $(function() {
        var parentHeight = $(window.parent.document).find('.profile-modal-body').height();
        $(window.parent.document).find('.profile-modal-body iframe').height(parentHeight + 'px');
    });
</script>