<?php

/**
 * 모바일 좌우 메뉴
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
?>
<!--Offcanvas Menu -->
<nav class="offcanvas offcanvas-start mobile-left-offcanvas" tabindex="-1" id="offcanvas_menu" aria-labelledby="offcanvas_menuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvas_menuLabel">MENU</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#offcanvas_menu" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <fieldset id="search_box">
            <form name="fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
                <input type="hidden" name="sfl" value="wr_subject||wr_content">
                <input type="hidden" name="sop" value="and">
                <label for="mobile-search" class="visually-hidden">검색어 필수</label>
                <div class="input-group mb-3">
                    <input type="text" name="stx" maxlength="20" class="form-control" id="mobile-search" placeholder="검색어를 입력하십시오" aria-label="검색어를 입력하십시오" aria-describedby="sch_submit">
                    <button class="btn btn-outline-primary" type="submit" id="sch_submit"><i class="bi bi-search"></i></button>
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
        <!--
            ######################
            ## Mobile 메뉴 출력 ##
            ######################
        -->
        <ul class="nav flex-column mobile-nav-menu">
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
                            echo '<span class="visually-hidden">하위분류</span><div class="sub-menu-wrap ps-4"><ul class="sub-nav nav flex-column">' . PHP_EOL;
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

            if ($i == 0) {  ?>
                <li class="menu-empty">메뉴 준비 중입니다.<?php if ($is_admin) { ?> <a href="<?php echo G5_ADMIN_URL; ?>/menu_list.php">관리자모드 &gt; 환경설정 &gt; 메뉴설정</a>에서 설정하실 수 있습니다.<?php } ?></li>
            <?php } ?>
        </ul>



    </div>
</nav>

<!--
##########################################
모바일 우측 회원 메뉴
##########################################
-->
<nav class="offcanvas offcanvas-end mobile-right-offcanvas" tabindex="-1" id="offcanvas_member" aria-labelledby="offcanvas_memberLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvas_memberLabel">Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#offcanvas_member" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <?php
        //모바일 로그인
        echo outlogin('theme/mobile');
        ?>
        <hr />
        <a href="<?php echo G5_BBS_URL ?>/faq.php"><i class="bi bi-card-checklist"></i> FAQ</a>
        <a href="<?php echo G5_BBS_URL ?>/qalist.php"><i class="bi bi-question-circle"></i> Q&amp;A</a>
        <a href="<?php echo G5_BBS_URL ?>/new.php"><i class="bi bi-newspaper"></i> 새글</a>
        <a href="<?php echo G5_BBS_URL ?>/current_connect.php" class="visit"> <i class="bi bi-link-45deg"></i>
            접속자 <?php echo connect('theme/basic'); ?>
        </a>
    </div>
</nav>