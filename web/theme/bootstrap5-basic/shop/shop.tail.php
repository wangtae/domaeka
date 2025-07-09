<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

if (G5_IS_MOBILE) {
    include_once(G5_THEME_MSHOP_PATH . '/shop.tail.php');
    return;
}

$admin = get_admin("super");

// 사용자 화면 우측과 하단을 담당하는 페이지입니다.
// 우측, 하단 화면을 꾸미려면 이 파일을 수정합니다.
?>
<?php echo strpos($_SERVER['PHP_SELF'], 'orderform') ? "</div></div><!--//.orderform-wrap-->" : ""; ?>
<?php echo strpos($_SERVER['PHP_SELF'], 'personalpayform') ? "</div></div><!--//.personalpayform-wrap-->" : ""; ?>
</main>
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
            </div>
            <div class="d-flex ms-0 ms-md-3 ms-lg-5 mt-5 mt-md-0">
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

<!-- ########################################################################################
우측 슬라이더용 : 삭제금지
############################################################################################# -->
<div class="my-side-menu position-fixed shadow">
    <div class="aside-content-wrap">
        <button class="text-start btn btn-outline-secondary shadow show-side-menu-btn d-none d-md-inline-block" id='my-menu' data-bs-toggle="offcanvas" data-bs-target="#right-content-menu" aria-controls="right-content-menu"><i class="bi bi-list"></i><span class="d-none">&nbsp; MENU</span></button>
        <aside class="offcanvas offcanvas-end" data-bs-scroll="false" tabindex="-1" id="right-content-menu" aria-labelledby="right-content-menuLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="right-content-menuLabel">MENU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="nav nav-tabs" id="aside-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-01" data-bs-toggle="tab" data-bs-target="#tab-01-pane" type="button" role="tab" aria-controls="tab-01-pane" aria-selected="true"><i class="bi bi-person-circle"></i><span class="d-none">&nbsp; 마이메뉴</span></button></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-02" data-bs-toggle="tab" data-bs-target="#tab-02-pane" type="button" role="tab" aria-controls="tab-02-pane" aria-selected="false"><i class="bi bi-card-list"></i></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-03" data-bs-toggle="tab" data-bs-target="#tab-03-pane" type="button" role="tab" aria-controls="tab-03-pane" aria-selected="false"><i class="bi bi-cart3"></i></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-04" data-bs-toggle="tab" data-bs-target="#tab-04-pane" type="button" role="tab" aria-controls="tab-04-pane" aria-selected="false"><i class="bi bi-bag-heart"></i></button>
                    </li>
                </ul>
                <div class="tab-content side-menu-content pt-2" id="side-tab-content">
                    <div class="tab-pane fade show active" id="tab-01-pane" role="tabpanel" aria-labelledby="tab-01" tabindex="0">
                        <div class="qk_con_wr">
                            <?php echo outlogin('theme/shop_side'); ?>
                            <ul class="list-group list-group-flush">
                                <?php if ($is_member) { ?>
                                    <li class="list-group-item"><a href="<?php echo G5_SHOP_URL; ?>/mypage.php">마이페이지</a></li>
                                <?php } ?>
                                <li class="list-group-item"><a href="<?php echo G5_SHOP_URL; ?>/orderinquiry.php">주문내역</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_BBS_URL ?>/faq.php">FAQ</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_BBS_URL ?>/qalist.php">1:1문의</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_SHOP_URL ?>/personalpay.php">개인결제</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_SHOP_URL ?>/itemuselist.php">사용후기</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_SHOP_URL ?>/itemqalist.php">상품문의</a></li>
                                <li class="list-group-item"><a href="<?php echo G5_SHOP_URL; ?>/couponzone.php">쿠폰존</a></li>
                            </ul>
                            <?php include_once(G5_SHOP_SKIN_PATH . '/boxcommunity.skin.php'); ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-02-pane" role="tabpanel" aria-labelledby="tab-02" tabindex="0"> <?php include G5_SHOP_SKIN_PATH . '/boxtodayview.skin.php'; ?></div>
                    <div class="tab-pane fade" id="tab-03-pane" role="tabpanel" aria-labelledby="tab-03" tabindex="0"><?php include_once G5_SHOP_SKIN_PATH . '/boxcart.skin.php'; ?></div>
                    <div class="tab-pane fade" id="tab-04-pane" role="tabpanel" aria-labelledby="tab-04" tabindex="0"><?php include_once G5_SHOP_SKIN_PATH . '/boxwish.skin.php'; ?></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<button type="button" id="gotop" class="bg-dark position-fixed gotop btn btn-sm btn-outline-secondary opacity-0 shadow">
    <i class="bi bi-chevron-up"></i><span class="visually-hidden">상단으로</span>
</button>

<?php
$sec = get_microtime() - $begin_time;
$file = $_SERVER['SCRIPT_NAME'];

if ($config['cf_analytics']) {
    echo $config['cf_analytics'];
}
?>

<script src="<?php echo G5_JS_URL; ?>/sns.js"></script>
<!-- } 하단 끝 -->
<script>
    $(function() {
        //상,하단 내용넣기에 컨테이너 적용
        $('#sev_thtml,#sev_hhtml,#sct_hhtml,#sct_thtml,#sit_hhtml').addClass('container mt-2 mb-2');
    });
</script>
<div class="sticky-bottom mobile-bottom-menu border-top shadow-lg p-2">
    <div class="d-flex justify-content-around w-100">
        <div class="d-flex menu-item">
            <a class="off-menu-btn" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_menu" aria-controls="offcanvas_menu">
                <i class="bi bi-list"></i>
            </a>
        </div>
        <div class="d-flex menu-item">
            <a href='/'><i class="bi bi-house"></i></a>
        </div>
        <div class="d-flex menu-item">
            <a href="<?php echo G5_SHOP_URL; ?>/cart.php" class="position-relative">
                <i class="bi bi-basket-fill"></i>
                <span class="position-absolute translate-middle badge rounded-pill bg-danger cart-num">
                    <?php echo get_boxcart_datas_count(); ?>
                    <span class="visually-hidden">장바구니 담은 개수</span>
                </span>
            </a>
        </div>
        <div class="d-flex menu-item">
            <a class="off-menu-btn off-member" data-bs-toggle="offcanvas" data-bs-target="#right-content-menu" aria-controls="right-content-menu">
                <i class="bi bi-person"></i>
            </a>
        </div>
    </div>
</div>

<script>
    $(function() {
        $("#gotop").on("click", function() {
            $("html, body").animate({
                scrollTop: 0
            }, '200');
            return false;
        });
        //tooltip 초기화
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        //매직스크롤
        var menuTrigger = 400;
        var controller = new ScrollMagic.Controller();

        new ScrollMagic.Scene({
            offset: menuTrigger
        }).setClassToggle(".gotop", "opacity-100").addTo(controller);

        new ScrollMagic.Scene({
            offset: menuTrigger + 100
        }).setClassToggle(".sit_admin", "visually-hidden").addTo(controller);

        //모바일 하단 메뉴 출력
        new ScrollMagic.Scene({
            offset: menuTrigger
        }).setClassToggle(".mobile-bottom-menu", "view-mobile-bottom-menu").addTo(controller);
        /*
        new ScrollMagic.Scene({
            offset: menuTrigger
        }).setClassToggle(".quick-menu", "top-0").addTo(controller);

        window.onscroll = function(e) {
            if (this.oldScroll > this.scrollY === true) {
                $('.quick-menu').removeClass('top-0');
            } else {
                if (menuTrigger < this.scrollY) {
                    $('.quick-menu').addClass('top-0');
                }
            }
            this.oldScroll = this.scrollY;
        }
        */
    });
</script>
<?php
include_once(G5_THEME_PATH . '/tail.sub.php');
