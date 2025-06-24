<?php

/**
 * 회원용 각종 모달
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
?>
<?php if ($is_member && (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == false)) { ?>
    <!-- 쪽지용 모달 -->
    <div class="modal fade" id="memo-modal" tabindex="-1" aria-labelledby="memo-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="memo-modalLabel"><i class="bi bi-chat-left-quote"></i> 쪽지</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body memo-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="memo-iframe" id='memo-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($is_member && (defined('BB_POINT_POPUP') && BB_POINT_POPUP == false)) { ?>
    <!-- 포인트 내역 모달 -->
    <div class="modal fade" id="point-modal" tabindex="-1" aria-labelledby="point-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="point-modalLabel"><i class="bi bi-chat-left-quote"></i> 포인트내역</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body point-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="point-iframe" id='point-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>


<?php if ($is_member && (defined('BB_SCRAP_POPUP') && BB_SCRAP_POPUP == false)) { ?>
    <!-- 스크랩 내역 모달 -->
    <div class="modal fade" id="scrap-modal" tabindex="-1" aria-labelledby="scrap-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="scrap-modalLabel"><i class="bi bi-chat-left-quote"></i> 포인트내역</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body scrap-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="scrap-iframe" id='scrap-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($is_member && (defined('BB_MAIL_POPUP') && BB_MAIL_POPUP == false)) { ?>
    <!-- MAIL 모달 -->
    <div class="modal fade" id="mail-modal" tabindex="-1" aria-labelledby="mail-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="mail-modalLabel"><i class="bi bi-chat-left-quote"></i> 메일보내기</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body mail-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="mail-iframe" id='mail-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($is_member && (defined('BB_PROFILE_POPUP') && BB_PROFILE_POPUP == false)) { ?>
    <!-- 자기소개 모달 -->
    <div class="modal fade" id="profile-modal" tabindex="-1" aria-labelledby="profile-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="profile-modalLabel"><i class="bi bi-chat-left-quote"></i> 자기소개</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body profile-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="profile-iframe" id='profile-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>


<?php if ($is_member && (defined('BB_COUPON_POPUP') && BB_COUPON_POPUP == false)) { ?>
    <!-- 영카트 쿠폰 모달 -->
    <div class="modal fade" id="coupon-modal" tabindex="-1" aria-labelledby="coupon-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="coupon-modalLabel"><i class="bi bi-chat-left-quote"></i> 내쿠폰</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body coupon-modal-body">
                    <div class="ratio ratio-4x3">
                        <iframe src='' class="coupon-iframe" id='coupon-iframe'></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
<script>
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
</script>