<?php

/**
 * Bootstrap Basic Shop
 */
if (!defined("_GNUBOARD_")) {
    exit;
}

$q = isset($_GET['q']) ? clean_xss_tags($_GET['q'], 1, 1) : '';

if (G5_IS_MOBILE) {
    include_once G5_THEME_MSHOP_PATH . '/shop.head.php';
    return;
}

include_once G5_THEME_PATH . '/head.sub.php';
include_once G5_LIB_PATH . '/outlogin.lib.php';
include_once G5_LIB_PATH . '/poll.lib.php';
include_once G5_LIB_PATH . '/visit.lib.php';
include_once G5_LIB_PATH . '/connect.lib.php';
include_once G5_LIB_PATH . '/popular.lib.php';
include_once G5_LIB_PATH . '/latest.lib.php';

add_javascript('<script src="' . BB_ASSETS_URL . '/js/owl.carousel/dist/owl.carousel.min.js"></script>', 500);
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/js/owl.carousel/dist/assets/owl.carousel.min.css">', 500);
//카테고리 정보 가져오기
$mshop_categories = get_shop_category_array(true);

?>
<?php
################################################
# 설치설명서 확인 후 영카트 배너관리자 수정할것 #
################################################
echo display_banner('메인상단', 'topbanner.skin.php');
?>
<!--Sticky header 를 사용하려면 main-header class를 아래 _shop 에 추가해야 한다. -->
<header class="_shop">
    <div class="top-menu-wrap">
        <div class="container">
            <div class="d-flex justify-content-between py-2">
                <?php if (defined('G5_COMMUNITY_USE') && G5_COMMUNITY_USE === true) { ?>
                    <div class='me-auto'>
                        <a href="<?php echo G5_URL; ?>/"><i class="bi bi-chat-dots"></i> 커뮤니티</a>
                        <?php if (defined('G5_USE_SHOP') && G5_USE_SHOP) { ?>
                            <a href="<?php echo G5_SHOP_URL ?>/" class="fw-bold"><i class="bi bi-cart4"></i> 쇼핑몰</a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class='me-auto'>
                        <a href="<?php echo G5_SHOP_URL ?>/" class="fw-bold"><i class="bi bi-house"></i> HOME</a>
                    </div>
                <?php } ?>
                <div class="d-flex justify-content-end">
                    <!-- PC용 -->
                    <div class="me-auto flex-row d-none d-md-flex">
                        <a href="<?php echo G5_BBS_URL ?>/faq.php" class="me-2"><i class="bi bi-card-checklist"></i> FAQ</a>
                        <?php if ($is_member) { ?>
                            <a href="<?php echo G5_BBS_URL ?>/qalist.php" class="me-2"><i class="bi bi-question-circle"></i> Q&amp;A</a>
                            <a href="<?php echo G5_SHOP_URL ?>/personalpay.php" class="me-2"><i class="bi bi-person"></i> 개인결제</a>
                        <?php } ?>
                        <a href="<?php echo G5_SHOP_URL ?>/itemuselist.php" class="me-2"><i class="bi bi-file-earmark-break"></i> 사용후기</a>
                        <a href="<?php echo G5_SHOP_URL ?>/itemqalist.php" class="me-2"><i class="bi bi-question"></i> 상품문의</a>
                        <!-- <a href="<?php echo G5_SHOP_URL; ?>/couponzone.php"><i class="bi bi-book-half"></i> 쿠폰존</a> -->
                        <?php if (!$is_member && $default['de_guest_cart_use'] == 1) { ?>
                            <a href="<?php echo G5_SHOP_URL; ?>/cart.php" class="ms-2"><i class="bi bi-cart-fill"></i> 장바구니 <?php echo (get_boxcart_datas_count() > 0) ? "(" . get_boxcart_datas_count() . ")" : ""; ?></a>
                        <?php } ?>
                    </div>
                    <!-- 모바일용 -->
                    <div class="dropdown d-flex d-md-none mobile-top-menu">
                        <div class="login-wrap-mobile d-flex d-lg-none me-3">
                            <?php if ($is_member) {  ?>
                                <a href="<?php echo G5_SHOP_URL; ?>/cart.php" class="position-relative">
                                    <i class="bi bi-basket-fill"></i>
                                    <span class="position-absolute translate-middle badge rounded-pill bg-danger cart-num">
                                        <?php echo get_boxcart_datas_count(); ?>
                                        <span class="visually-hidden">장바구니 담은 개수</span>
                                    </span>
                                </a>
                            <?php } else { ?>
                                <a href="<?php echo G5_BBS_URL ?>/login.php?url=<?php echo $urlencode; ?>" class="d-flex me-1">
                                    <i class="bi bi-box-arrow-in-right text-center login-icon"></i>
                                    &nbsp;<span class="d-flex justify-content-center">로그인</span>
                                </a>
                                <a href="<?php echo G5_BBS_URL ?>/register.php" class="d-flex justify-content-center">
                                    <i class="bi bi-person-plus-fill text-center login-icon"></i>
                                    &nbsp;<span class="d-flex justify-content-center">회원가입</span>
                                </a>
                            <?php } ?>
                        </div>

                        <a class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></a>
                        <ul class="dropdown-menu">
                            <li class='dropdown-item'><a href="<?php echo G5_BBS_URL ?>/faq.php" class="me-1"><i class="bi bi-card-checklist"></i> FAQ</a></li>
                            <?php if ($is_member) { ?>
                                <li class='dropdown-item'><a href="<?php echo G5_BBS_URL ?>/qalist.php" class="me-1"><i class="bi bi-question-circle"></i> Q&amp;A</a></li>
                                <li class='dropdown-item'><a href="<?php echo G5_SHOP_URL ?>/personalpay.php" class="me-1"><i class="bi bi-person"></i> 개인결제</a></li>
                            <?php } ?>
                            <li class='dropdown-item'><a href="<?php echo G5_SHOP_URL ?>/itemuselist.php" class="me-1"><i class="bi bi-file-earmark-break"></i> 사용후기</a></li>
                            <li class='dropdown-item'><a href="<?php echo G5_SHOP_URL ?>/itemqalist.php" class="me-1"><i class="bi bi-question"></i> 상품문의</a></li>
                            <li class='dropdown-item'><a href="<?php echo G5_SHOP_URL; ?>/couponzone.php"><i class="bi bi-book-half"></i> 쿠폰존</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--//.top-menu-wrap -->
    <div class="main-menu-wrap">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <!-- 모바일 메뉴 호출 -->
                <a class="off-menu-btn d-lg-none d-flex opacity-75" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_menu" aria-controls="offcanvas_menu">
                    <i class="bi bi-list"></i>
                </a>
                <a href="<?php echo G5_SHOP_URL ?>" class="logo d-flex align-items-center py-4">
                    <img src="<?php echo G5_THEME_URL ?>/img/logo.png" />
                </a>
                <!-- 검색 -->
                <div class="search-wrap align-content-center flex-wrap d-none d-lg-flex flex-fill justify-content-center">
                    <form name="frmsearch1" method="get" action="<?php echo G5_SHOP_URL; ?>/search.php" onsubmit="return search_submit(this);" class='align-content-center flex-wrap d-none d-lg-flex flex-fill justify-content-center'>
                        <input type="hidden" name="sfl" value="wr_subject||wr_content">
                        <input type="hidden" name="sop" value="and">
                        <label for="sch_stx" class="visually-hidden">검색어 필수</label>
                        <div class="input-group goods-search">
                            <?php
                            $search_category = "<select name='qcaid' class='form-select'>";
                            $search_category .= "<option value=''>전체</option>";
                            foreach ($mshop_categories as $cate_search) {
                                $search_category .= "<option value='{$cate_search['text']['ca_id']}'>{$cate_search['text']['ca_name']}</option>";
                            }
                            $search_category .= "</select>";
                            echo $search_category;
                            ?>
                            <input type="text" name="q" maxlength="20" value="<?php echo stripslashes(get_text(get_search_string($q))); ?>" class="form-control flex-grow-1" id="sch_stx" placeholder="검색어를 입력하십시오" aria-label="검색어를 입력하십시오" aria-describedby="search-submit">
                            <button class="btn btn-outline-primary" type="submit" id="search-submit"><i class="bi bi-search"></i></button>
                        </div>
                        <script>
                            function search_submit(f) {
                                if (f.q.value.length < 2) {
                                    alert("검색어는 두글자 이상 입력하십시오.");
                                    f.q.select();
                                    f.q.focus();
                                    return false;
                                }
                                return true;
                            }
                        </script>
                    </form>
                </div><!--//.search-wrap -->

                <div class="login-wrap d-none d-lg-flex">
                    <?php if ($is_member) {  ?>
                        <?php echo outlogin('theme/shop_basic'); ?>
                        <a href="<?php echo G5_SHOP_URL; ?>/cart.php" class="btn btn-primary position-relative">
                            <i class="bi bi-basket-fill"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo get_boxcart_datas_count(); ?>
                                <span class="visually-hidden">장바구니 담은 개수</span>
                            </span>
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo G5_BBS_URL ?>/login.php?url=<?php echo $urlencode; ?>" class="d-flex flex-column p-2 me-2">
                            <i class="bi bi-box-arrow-in-right text-center login-icon"></i>
                            <span class="d-flex justify-content-center">로그인</span>
                        </a>
                        <a href="<?php echo G5_BBS_URL ?>/register.php" class="d-flex flex-column justify-content-center p-2">
                            <i class="bi bi-person-plus-fill text-center login-icon"></i>
                            <span class="d-flex justify-content-center">회원가입</span>
                        </a>
                    <?php } ?>
                </div>

                <!-- 모바일 회원 메뉴 호출 - CART -->
                <a class="off-menu-btn off-member d-lg-none d-flex opacity-75" data-bs-toggle="offcanvas" data-bs-target="#right-content-menu" aria-controls="right-content-menu">
                    <i class="bi bi-person"></i>
                </a>
            </div>
            <div class="category-wrap d-flex justify-content-around">
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center d-none d-lg-flex category-item"><?php include_once G5_THEME_SHOP_PATH . '/category.php'; ?></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (isset($type) && $type == 1) ? 'active' : ''; ?>"><a href="<?php echo shop_type_url(1); ?>" class="primary-menu"><i class="bi bi-star-fill"></i> 히트<span class="d-none d-md-inline">상품</span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (isset($type) && $type == 2) ? 'active' : ''; ?>"><a href="<?php echo shop_type_url(2); ?>" class="primary-menu"><i class="bi bi-award-fill"></i> 추천<span class="d-none d-md-inline">상품</span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (isset($type) && $type == 3) ? 'active' : ''; ?>"><a href="<?php echo shop_type_url(3); ?>" class="primary-menu"><i class="bi bi-box-seam-fill"></i> 최신<span class="d-none d-md-inline">상품</span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (isset($type) && $type == 4) ? 'active' : ''; ?>"><a href="<?php echo shop_type_url(4); ?>" class="primary-menu"><i class="bi bi-suit-heart-fill"></i> 인기<span class="d-none d-md-inline">상품</span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (isset($type) && $type == 5) ? 'active' : ''; ?>"><a href="<?php echo shop_type_url(5); ?>" class="primary-menu"><i class="bi bi-cash-coin"></i> 할인<span class="d-none d-md-inline">상품</span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (stripos($_SERVER['PHP_SELF'], '_event')) ? 'active' : ''; ?>"><a href="<?php echo G5_THEME_SHOP_URL; ?>/_event.php" class="primary-menu"><i class="bi bi-balloon-fill"></i> EVENT<span class="d-none d-md-inline"></span></a></div>
                <div class="py-2 flex-fill header-main-nav d-flex justify-content-center <?php echo (stripos($_SERVER['PHP_SELF'], 'couponzone')) ? 'active' : ''; ?>"><a href="<?php echo G5_SHOP_URL; ?>/couponzone.php" class="primary-menu"><i class="bi bi-book-half"></i> 쿠폰<span class="d-none d-md-inline">존</span></a></div>
            </div>
        </div>
    </div>

</header>

<?php
###############
# 모바일 메뉴 #
###############
include_once G5_THEME_PATH  . "/_mobile_shopmenu.inc.php";
?>

<?php
if (defined('_INDEX_')) {
    // index에서만 실행 - 팝업레이어
    include G5_THEME_PATH . '/newwin.inc.php';
}
?>

<main>
    <?php include G5_THEME_PATH . "/_member.inc.php"; ?>

    <?php
    //주문서용
    if (strpos($_SERVER['PHP_SELF'], 'orderform')) {
        echo "<div class='container mt-2'>";
        echo "<div class='bg-white p-4 border'>";
        echo "<h2 class='fs-4 fw-bolder mb-0'><i class='bi bi-cart-check'></i> " . $g5['title'] . "</div>";
        echo "</div></div>";
        echo "<div class='container orderform-wrap mt-2'>";
        echo "<div class='bg-white p-2 p-lg-4 border'>";
    }
    //개인결제용
    if (strpos($_SERVER['PHP_SELF'], 'personalpayform')) {
        echo "<div class='container mt-2'>";
        echo "<div class='bg-white p-4 border'>";
        echo "<h2 class='fs-4 fw-bolder mb-0'><i class='bi bi-cart-check'></i> " . $g5['title'] . "</div>";
        echo "</div></div>";
        echo "<div class='container personalpayform-wrap mt-2'>";
        echo "<div class='bg-white p-2 p-lg-4 border'>";
    }
    ?>