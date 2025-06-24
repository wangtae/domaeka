<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
$delete_str = "";
if ($w == 'x') {
    $delete_str = "댓";
}
if ($w == 'u') {
    $g5['title'] = $delete_str . "글 수정";
} else if ($w == 'd' || $w == 'x') {
    $g5['title'] = $delete_str . "글 삭제";
} else {
    $g5['title'] = $g5['title'];
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/password.css">', 120);
?>

<div class="password-wrap">
    <div class="container">
        <div class='d-flex justify-content-center'>
            <div class='row'>
                <div class="p-4 p-lg-4 bg-white border mt-5 shadow">
                    <h1 class="fs-4 fw-bolder"><?php echo $g5['title'] ?></h1>
                    <hr />
                    <div class="alert alert-info">
                        <?php if ($w == 'u') { ?>
                            <strong>작성자만 글을 수정할 수 있습니다.</strong><br />
                            작성자 본인이라면, 글 작성시 입력한 비밀번호를 입력하여 글을 수정할 수 있습니다.
                        <?php } else if ($w == 'd' || $w == 'x') {  ?><br />
                            <strong>작성자만 글을 삭제할 수 있습니다.</strong>
                            작성자 본인이라면, 글 작성시 입력한 비밀번호를 입력하여 글을 삭제할 수 있습니다.
                        <?php } else {  ?>
                            <strong>비밀글 기능으로 보호된 글입니다.</strong><br />
                            작성자와 관리자만 열람하실 수 있습니다.<br> 본인이라면 비밀번호를 입력하세요.
                        <?php }  ?>
                    </div>

                    <form name="fboardpassword" action="<?php echo $action;  ?>" method="post">
                        <input type="hidden" name="w" value="<?php echo $w ?>">
                        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
                        <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
                        <input type="hidden" name="comment_id" value="<?php echo $comment_id ?>">
                        <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
                        <input type="hidden" name="stx" value="<?php echo $stx ?>">
                        <input type="hidden" name="page" value="<?php echo $page ?>">

                        <fieldset class="input-group">
                            <label for="pw_wr_password" class="input-group-text"><i class="bi bi-key-fill"></i><span class="visually-hidden">필수</span></label>
                            <input type="password" name="wr_password" id="password_wr_password" required class="form-control required" size="15" maxLength="20" placeholder="비밀번호 입력">
                            <input type="submit" value="확인" class="btn btn-outline-primary">
                        </fieldset>
                    </form>
                    <hr />
                    <a href='#' onclick="history.go(-1);"><i class="bi bi-reply-fill"></i> 뒤로</a>
                </div>
            </div>
        </div>
    </div>
</div>