<?php
if (!defined('_GNUBOARD_')) {
    exit;
}

//모바일 사용일 경우 - 이 테마는 모바일을 사용하지 않는다. PC를 기준으로 반응형을 사용한다.
if (G5_IS_MOBILE) {
    include_once G5_THEME_MOBILE_PATH . '/head.php';
    return;
}

//커뮤니티 페이지를 사용하지 않으면 쇼핑몰 바로 사용
if (defined('G5_COMMUNITY_USE') && G5_COMMUNITY_USE === false) {
    define('G5_IS_COMMUNITY_PAGE', true);
    include_once G5_THEME_SHOP_PATH . '/shop.head.php';
    return;
}

include_once G5_THEME_PATH . '/head.sub.php';
include_once G5_LIB_PATH . '/latest.lib.php';
include_once G5_LIB_PATH . '/outlogin.lib.php';
include_once G5_LIB_PATH . '/poll.lib.php';
include_once G5_LIB_PATH . '/visit.lib.php';
include_once G5_LIB_PATH . '/connect.lib.php';
include_once G5_LIB_PATH . '/popular.lib.php';

if ($is_member) {
    // 회원이라면 로그인 중이라는 메세지를 출력해준다. 화면에는 보이지 않는다.
    $sr_admin_msg = '';
    if ($is_admin == 'super') {
        $sr_admin_msg = "최고관리자 ";
    } else if ($is_admin == 'group') {
        $sr_admin_msg = "그룹관리자 ";
    } else if ($is_admin == 'board') {
        $sr_admin_msg = "게시판관리자 ";
    }

    echo '<div class="visually-hidden">' . $sr_admin_msg . get_text($member['mb_nick']) . '님 로그인 중 ';
    echo '<a href="' . G5_BBS_URL . '/logout.php">로그아웃</a></div>';
}
?>
<header class="main-header text-bg-primary">
    <div class="container ">
        <div class="px-2 py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between justify-content-center justify-content-lg-start">
                <!-- 모바일 메뉴 호출 -->
                <a class="off-menu-btn d-lg-none d-flex opacity-75" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_menu" aria-controls="offcanvas_menu">
                    <i class="bi bi-list"></i>
                </a>
                <a href="/" class="logo d-flex align-items-center my-2 my-lg-0 me-lg-auto text-white text-decoration-none">
                    <i class="bi bi-globe-central-south-asia"></i>
                    &nbsp;도매까
                </a>
                <!-- 모바일 회원 메뉴 호출 -->
                <a class="off-menu-btn off-member d-lg-none d-flex opacity-75" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_member" aria-controls="offcanvasmember">
                    <i class="bi bi-person"></i>
                </a>
                <!--
                    ##################
                    ## PC 메뉴 출력 ##
                    ##################
                 -->
                <ul class="nav col-12 col-lg-auto my-2 justify-content-center my-md-0 text-small d-none d-lg-flex main-nav-menu">
                    <?php
                    $menu_datas = get_menu_db(0, true);
                    $i = 0;
                    foreach ($menu_datas as $row) {
                        if (empty($row)) {
                            continue;
                        }
                        $add_class = (isset($row['sub']) && $row['sub']) ? 'sub-menu' : '';
                    ?>
                        <li class="<?php echo $add_class; ?> position-relative">
                            <a href="<?php echo $row['me_link']; ?>" target="_<?php echo $row['me_target']; ?>" class="nav-link depth1"><?php echo $row['me_name'] ?></a>
                            <?php
                            $k = 0;
                            foreach ((array) $row['sub'] as $row2) {

                                if (empty($row2)) {
                                    continue;
                                }

                                if ($k == 0) {
                                    echo '<span class="visually-hidden">하위분류</span><div class="sub-menu-wrap position-absolute shadow"><ul class="sub-nav nav flex-column">' . PHP_EOL;
                                }
                                echo "<li class=''><a href='{$row2['me_link']}' target='_{$row2['me_target']}' class='nav-link depth2 text-break'>{$row2['me_name']}</a></li>";
                                $k++;
                            }   //end foreach $row2

                            if ($k > 0) {
                                echo '</ul></div>' . PHP_EOL;
                            }
                            ?>
                        </li>
                    <?php
                        $i++;
                    }   //end foreach $row

                    if ($i === 0) {  ?>
                        <li class="menu-empty p-2"><?php if ($is_admin) { ?> <a href="<?php echo G5_ADMIN_URL; ?>/menu_list.php">관리자모드 &gt; 환경설정 &gt; 메뉴설정</a>에서 설정하실 수 있습니다.<?php } ?></li>
                    <?php } ?>
                    <!-- 검색 버튼 -->
                    <li>
                        <a href='#' class="nav-link depth1" data-bs-toggle="modal" data-bs-target="#searchModal"><i class="bi bi-search"></i></a>
                    </li>
                </ul>

            </div>
        </div>
    </div>
    <div class="px-3 py-2 border-bottom bg-light shadow-sm">
        <div class="container d-flex flex-wrap default-menu justify-content-end">
            <?php if (defined('G5_COMMUNITY_USE') && G5_COMMUNITY_USE) { ?>
                <div class='me-auto'>
                    <a href="<?php echo G5_URL ?>/" class="fw-bold"><i class="bi bi-chat-dots"></i> 커뮤니티</a>
                    <?php if (defined('G5_USE_SHOP') && G5_USE_SHOP) { ?>
                        <a href="<?php echo G5_SHOP_URL ?>/"><i class="bi bi-cart4"></i> 쇼핑몰</a>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="text-end d-none d-lg-flex default-menu-right">
                <!-- 
                <a href="<?php echo G5_BBS_URL ?>/faq.php"><i class="bi bi-card-checklist"></i> FAQ</a>
                <a href="<?php echo G5_BBS_URL ?>/qalist.php"><i class="bi bi-question-circle"></i> Q&amp;A</a>
                -->
                <a href="<?php echo G5_BBS_URL ?>/new.php"><i class="bi bi-newspaper"></i> 새글</a>
                <a href="<?php echo G5_BBS_URL ?>/current_connect.php" class="visit"> <i class="bi bi-link-45deg"></i>
                    접속자 <?php echo connect('theme/basic'); ?>
                </a>
                <!-- 아웃로그인 -->
            </div>
            <div class="text-end d-none d-lg-flex ms-2">
                <?php echo outlogin('theme/basic'); ?>
            </div>
        </div>
    </div>
</header>

<?php
###############
# 모바일 메뉴 #
###############
include_once G5_THEME_PATH  . "/_mobile_menu.inc.php";
?>
<!-- 
   ##########
   # 검색창 #
   ##########
 -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="searchModalLabel">전체검색</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <fieldset id="search_box">
                    <form name="fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
                        <input type="hidden" name="sfl" value="wr_subject||wr_content">
                        <input type="hidden" name="sop" value="and">
                        <label for="sch_stx" class="visually-hidden">검색어 필수</label>
                        <div class="input-group mb-3">
                            <input type="text" name="stx" maxlength="20" class="form-control" id="sch_stx" placeholder="검색어를 입력하십시오" aria-label="검색어를 입력하십시오" aria-describedby="search-submit">
                            <button class="btn btn-outline-primary" type="submit" id="search-submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>

                    <script>
                        function fsearchbox_submit(f) {
                            var stx = f.stx.value.trim();
                            if (stx.length < 2) {
                                alert("검색어는 두글자 이상 입력하십시오.");
                                f.stx.select();
                                f.stx.focus();
                                return false;
                            }

                            // 검색에 많은 부하가 걸리는 경우 이 주석을 제거하세요.
                            var cnt = 0;
                            for (var i = 0; i < stx.length; i++) {
                                if (stx.charAt(i) == ' ')
                                    cnt++;
                            }

                            if (cnt > 1) {
                                alert("빠른 검색을 위하여 검색어에 공백은 한개만 입력할 수 있습니다.");
                                f.stx.select();
                                f.stx.focus();
                                return false;
                            }
                            f.stx.value = stx;

                            return true;
                        }
                    </script>

                </fieldset>

                <?php echo popular('theme/basic'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
if (defined('_INDEX_')) {
    // index에서만 실행 - 팝업레이어
    include G5_THEME_PATH . '/newwin.inc.php';
}
?>

<main>
    <?php include G5_THEME_PATH . "/_member.inc.php"; ?>