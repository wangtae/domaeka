<?php

/**
 * 모바일 쇼핑몰 좌우 메뉴
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
        <!-- 검색 -->
        <div class="mobile-search-wrap align-content-center flex-wrap d-flex">
            <form name="frmsearch1" method="get" action="<?php echo G5_SHOP_URL; ?>/search.php" onsubmit="return msearch_submit(this);">
                <input type="hidden" name="sfl" value="wr_subject||wr_content">
                <input type="hidden" name="sop" value="and">
                <label for="mobile_sch_stx" class="visually-hidden">검색어 필수</label>
                <div class="input-group goods-search">
                    <input type="text" name="q" maxlength="20" value="<?php echo stripslashes(get_text(get_search_string($q))); ?>" class="form-control" id="mobile_sch_stx" placeholder="검색어를 입력하십시오" aria-label="검색어를 입력하십시오" aria-describedby="msearch-submit">
                    <button class="btn btn-outline-secondary" type="submit" id="msearch-submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <script>
                function msearch_submit(f) {
                    if (f.q.value.length < 2) {
                        alert("검색어는 두글자 이상 입력하십시오.");
                        f.q.select();
                        f.q.focus();
                        return false;
                    }
                    return true;
                }
            </script>
        </div>
        <!--
            #############################
            ## Mobile 쇼핑몰 분류 출력 ##
            #############################
        -->
        <?php $mobile_shop_category = get_shop_category_array(true); ?>
        <div id="mobile-shop-category" class="mobile-shop-category-wrap mt-5">
            <div id='category-view' class="mobile-categorys">
                <?php
                //1단계
                $i = 0;
                foreach ($mobile_shop_category as $cate1) {
                    if (empty($cate1)) {
                        continue;
                    }

                    $mshop_ca_row1 = $cate1['text'];

                    $position = '';
                    if ($i > 3) {
                        $position = 'bottoms';
                    }
                    if ($i == 0) {
                        echo '<div class="accordion accordion-flush mobile-depth1" id="mobile-accordion">' . PHP_EOL;
                    }

                    echo '<div class="accordion-item">';
                    echo '<div class="accordion-header justify-content-between d-flex" id="heading' . $i . '">';
                    echo '<a href="' . $mshop_ca_row1['url'] . '" class="flex-wrap align-content-center d-flex ps-2">' . get_text($mshop_ca_row1['ca_name']) . '</a>';
                    if (count($cate1) > 1) {
                        echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '" aria-expanded="false" aria-controls="collapse' . $i . '"></button>';
                    }
                    echo '</div>';


                    //2단계
                    $j = 0;
                    foreach ($cate1 as $key => $cate2) {
                        if (empty($cate2) || $key === 'text') {
                            continue;
                        }
                        $mshop_ca_row2 = $cate2['text'];


                        if ($j == 0) {
                            echo '<div class="mobile-depth2 accordion-collapse collapse" id="collapse' . $i . '" aria-labelledby="heading' . $i . '" data-bs-parent="#mobile-accordion">' . PHP_EOL;
                            echo "<div class='accordion' id='mobile-accordion-sub1{$i}'>";
                        }

                        echo '<div class="sub-cate accordion-item">';
                        echo '<div class="accordion-header d-flex flex-row d-flex justify-content-between" id="headingSub' . $j . '">';
                        echo '<a href="' . $mshop_ca_row2['url'] . '" class="flex-wrap align-content-center d-flex ps-2"><i class="bi bi-dot"></i> ' . get_text($mshop_ca_row2['ca_name']) . '</a>';
                        if (count($cate2) > 1) {
                            echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSub' . $i . $j . '" aria-expanded="false" aria-controls="collapseSub' . $i . $j . '"></button>';
                        }
                        echo '</div>';

                        //3단계
                        $x = 0;
                        foreach ($cate2 as $key => $cate3) {
                            if (empty($cate3) || $key === 'text') {
                                continue;
                            }

                            $mshop_ca_row3 = $cate3['text'];
                            if ($x == 0) {
                                echo '<div class="mobile-depth3 accordion-collapse collapse" id="collapseSub' . $i . $j . '" class="" aria-labelledby="headingSub' . $i . $j . '" data-bs-parent="#mobile-accordion-sub1' . $i . '">' . PHP_EOL;
                            }

                            echo '<div class="depth3-item p-1">';
                            echo '<a href="' . $mshop_ca_row3['url'] . '"> &middot;&nbsp; ' . get_text($mshop_ca_row3['ca_name']) . '</a>';
                            echo '</div>';
                            $x++;
                        }

                        if ($x > 0) {
                            echo '</div><!--//.mobile-depth3 -->' . PHP_EOL;
                        }

                        echo '</div><!--//.sub-cate  -->';
                        $j++;
                    }

                    if ($j > 0) {
                        echo '</div><!--//#mobile-accordion-sub1 -->' . PHP_EOL;
                        echo '</div><!--//.mobile-depth2 -->' . PHP_EOL;
                    }
                    echo '</div><!--//.accordion -->';
                    $i++;
                }   // end for

                if ($i > 0) {
                    echo '</div>' . PHP_EOL;
                } else {
                    echo '<p class="p-2 text-center">등록된 분류가 없습니다.</p>' . PHP_EOL;
                }
                ?>
            </div>
        </div>
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