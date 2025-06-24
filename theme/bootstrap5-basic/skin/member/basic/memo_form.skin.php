<?php

/**
 * 쪽지 쓰기
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/memo.css">', 120);
?>
<div class="new_win memo-wrap">
    <div class="point-header container-fluid mt-2 mb-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-envelope"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
    <div class='container-fluid'>
        <div class='bg-white p-2 p-lg-4 border'>
            <!-- Nav tabs -->
            <ul class="nav nav-tabs sticky-top bg-white" id="memo-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true"><a href="./memo.php?kind=recv">받은쪽지</a></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false"><a href="./memo.php?kind=send">보낸쪽지</a></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab" aria-controls="messages" aria-selected="false"><a href="./memo_form.php">쪽지쓰기</a></button>
                </li>
            </ul>

            <form name="fmemoform" action="<?php echo $memo_action_url; ?>" onsubmit="return fmemoform_submit(this);" method="post" autocomplete="off">
                <div class="memo-form mt-2">
                    <h2 class="visually-hidden">쪽지쓰기</h2>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 여러 회원에게 보낼때는 컴마(,)로 구분하세요.
                                <?php if ($config['cf_memo_send_point']) { ?>
                                    <br /><i class="bi bi-info-circle"></i> 쪽지 보낼때 회원당 <?php echo number_format($config['cf_memo_send_point']); ?>점의 포인트를 차감합니다.
                                <?php } ?>
                            </div>
                            <label for="me_recv_mb_id" class="visually-hidden">받는 회원아이디<strong>필수</strong></label>
                            <input type="text" name="me_recv_mb_id" value="<?php echo $me_recv_mb_id; ?>" id="me_recv_mb_id" required class="form-control required" size="47" placeholder="받는 회원아이디">
                        </li>
                        <li class="list-group-item">
                            <label for="me_memo" class="visually-hidden">내용</label>
                            <textarea name="me_memo" id="me_memo" required rows="8" class="form-control required"><?php echo $content ?></textarea>
                        </li>
                        <li class="list-group-item">
                            <span class="visually-hidden">자동등록방지</span>
                            <?php
                            $captcha_html = captcha_html();
                            $captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
                            $captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
                            $captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);
                            echo $captcha_html;
                            ?>
                        </li>
                    </ul>
                </div>

                <div class="d-flex p-2 justify-content-end">
                    <button type="submit" id="btn_submit" class="btn btn-primary">보내기</button>
                    <?php if ($is_member && (defined('BB_MEMO_POPUP') && BB_MEMO_POPUP == true)) { ?>
                        <button type="button" onclick="window.close();" class="btn btn-danger ms-1">창닫기</button>
                    <?php } ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        function fmemoform_submit(f) {
            <?php echo chk_captcha_js();  ?>

            return true;
        }
        //iframe 높이 맞추기
        $(function() {
            var parentHeight = $(window.parent.document).find('.memo-modal-body').height();
            $(window.parent.document).find('.memo-modal-body iframe').height(parentHeight + 'px');
        });
    </script>