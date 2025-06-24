<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

//반응형이라 모바일 테마는 사용하지 않는다. 
if (G5_IS_MOBILE) {
    include_once G5_THEME_MOBILE_PATH . '/tail.php';
    return;
}
//커뮤니티 사용하지 않을때
if (G5_COMMUNITY_USE === false) {
    include_once G5_THEME_SHOP_PATH . '/shop.tail.php';
    return;
}
echo '</main>';
?>

<footer class='footer mt-5 bg-dark text-light pt-3 pb-3'>
    <div class='container'>
        <div class='tail-menu pb-2 mb-2'>
            <a href="<?php echo get_pretty_url('content', 'company'); ?>" class='text-light'>회사소개</a> &middot;
            <a href="<?php echo get_pretty_url('content', 'privacy'); ?>" class='text-light'>개인정보처리방침</a> &middot;
            <a href="<?php echo get_pretty_url('content', 'provision'); ?>" class='text-light'>서비스이용약관</a>
        </div>
        <div class="d-flex flex-wrap align-items-start justify-content-between justify-content-lg-start">
            <div class="me-auto">
                <a href='/' class="site-title align-content-center">ASK-SEO.NET</a>
                <div class="sns-icons-wrap">
                    <a href='#twitter-link'><i class="bi bi-twitter"></i></a>
                    <a href='#youtube-link'><i class="bi bi-youtube"></i></a>
                    <a href='#facebook-link'><i class="bi bi-facebook"></i></a>
                    <a href='#instagram-link'><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="d-flex mt-5 mt-md-0">
                <?php if ((defined('G5_USE_SHOP') && G5_USE_SHOP == true) && isset($default)) { ?>
                    <!--쇼핑몰 사용시 -->
                    <ul class="list-group list-group-flush company-info">
                        <li class="list-group-item">회사명 : <?php echo $default['de_admin_company_name'] ?></li>
                        <li class="list-group-item">대표 : <?php echo $default['de_admin_company_owner'] ?></li>
                        <li class="list-group-item">주소 : <?php echo $default['de_admin_company_addr'] ?></li>
                        <li class="list-group-item">사업자 등록번호 : <?php echo $default['de_admin_company_saupja_no'] ?></li>
                        <li class="list-group-item">전화 : <?php echo $default['de_admin_company_tel'] ?></li>
                        <li class="list-group-item">팩스 : <?php echo $default['de_admin_company_fax'] ?></li>
                        <li class="list-group-item">통신판매업신고번호 : <?php echo $default['de_admin_tongsin_no'] ?></li>
                        <li class="list-group-item">개인정보관리책임자 : <?php echo $default['de_admin_info_email'] ?></li>
                    </ul>
                <?php } else { ?>
                    <!--커뮤니티만 사용시 -->
                    <ul class="list-group list-group-flush company-info">
                        <li class="list-group-item">회사명 : ASK-SEO.NET</li>
                        <li class="list-group-item">대표 : 대표명</li>
                        <li class="list-group-item">주소 : 주소를 입력해 주세요.</li>
                        <li class="list-group-item">사업자 등록번호 : 000-00-0000</li>
                        <li class="list-group-item">전화 : 000-000-0000</li>
                        <li class="list-group-item">팩스 : 000-000-0001</li>
                        <li class="list-group-item">통신판매업신고번호 : 통신판매업 신고 번호 입력</li>
                        <li class="list-group-item">개인정보관리책임자 : 개인정보관리책임자 email 입력</li>
                    </ul>
                <?php } ?>
            </div>
            <div class="d-flex ms-0 ms-md-3 ms-lg-5  mt-5 mt-md-0">
                <?php echo latest('theme/notice', 'notice', 6, 50); ?>
            </div>
            <div class="d-flex ms-0 ms-md-3 ms-lg-5 mt-5 mt-md-0 poll-item">
                <?php echo poll('theme/basic'); ?>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-center justify-content-md-end">
            <?php echo visit('theme/basic'); ?>
        </div>

        <div class="d-flex flex-wrap justify-content-center pt-5">
            <div class="tail-copyright">Copyright &copy; <strong>ASK-SEO.NET.</strong> All rights reserved.</div>
        </div>
    </div>



</footer>

<button type="button" id="gotop" class="position-fixed gotop btn btn-sm btn-outline-success">
    <i class="bi bi-chevron-up"></i><span class="visually-hidden">상단으로</span>
</button>
<script>
    $(function() {
        $("#gotop").on("click", function() {
            $("html, body").animate({
                scrollTop: 0
            }, '500');
            return false;
        });
    });
    //tooltip 초기화
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    /*
    const button = document.querySelector('#category');
    const tooltip = document.querySelector('#cate-view');

    // Pass the button, the tooltip, and some options, and Popper will do the
    // magic positioning for you:
    Popper.createPopper(button, tooltip, {
        placement: 'right',
    });
    */
</script>

<?php
if ($config['cf_analytics']) {
    echo $config['cf_analytics'];
}

include_once G5_THEME_PATH . "/tail.sub.php";
