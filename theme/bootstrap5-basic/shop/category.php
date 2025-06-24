<?php

/**
 * 쇼핑몰 분류
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/category.css">', 200);
?>
<div id="category" class="category-wrap position-relative">
    <div id='cate-view' class="categorys position-relative">
        <a href='#categorylink' class="primary-menu"><i class="bi bi-list"></i> <span class="d-none d-md-inline">분류</span></a>
        <?php
        //1단계
        $i = 0;
        foreach ($mshop_categories as $cate1) {
            if (empty($cate1)) {
                continue;
            }

            $mshop_ca_row1 = $cate1['text'];

            $position = '';
            if ($i > 3) {
                $position = 'bottoms';
            }
            if ($i == 0) {
                echo '<ul class="list-group depth1 position-absolute flex-column shadow-sm">' . PHP_EOL;
            }

            echo '<li class="list-group-item position-relative justify-content-between d-flex ' . $position . '">';
            echo '<a href="' . $mshop_ca_row1['url'] . '" class="cate_li_1_a">' . get_text($mshop_ca_row1['ca_name']) . '</a>';
            if (count($cate1) > 1) {
                echo '<span class="text"><i class="bi bi-plus"></i></span>';
            }

            //top 포지션
            $top_position = 0;
            if ($i > 0) {
                $top_position = "-" . $i * 40;
            }
            //2단계
            $j = 0;
            foreach ($cate1 as $key => $cate2) {
                if (empty($cate2) || $key === 'text') {
                    continue;
                }
                $mshop_ca_row2 = $cate2['text'];


                if ($j == 0) {
                    echo '<ul class="list-group depth2 position-absolute flex-column shadow-sm" style="top:' . $top_position . 'px">' . PHP_EOL;
                }

                echo '<li class="list-group-item position-relative justify-content-between d-flex">';
                echo '<a href="' . $mshop_ca_row2['url'] . '">' . get_text($mshop_ca_row2['ca_name']) . '</a>';
                if (count($cate2) > 1) {
                    echo '<span class="text"><i class="bi bi-plus"></i></span>';
                }

                //top 포지션
                $top_position2 = 0;
                if ($j > 0) {
                    $top_position2 = "-" . $j * 40;
                }

                //3단계
                $x = 0;
                foreach ($cate2 as $key => $cate3) {
                    if (empty($cate3) || $key === 'text') {
                        continue;
                    }

                    $mshop_ca_row3 = $cate3['text'];
                    if ($x == 0) {
                        echo '<ul class="list-group depth3 position-absolute shadow-sm" style="top:' . $top_position2 . 'px">' . PHP_EOL;
                    }

                    echo '<li class="list-group-item">';
                    echo '<a href="' . $mshop_ca_row3['url'] . '">' . get_text($mshop_ca_row3['ca_name']) . '</a>';
                    echo '</li>';
                    $x++;
                }
                if ($x > 0) {
                    echo '</ul>' . PHP_EOL;
                }
                echo '</li>';
                $j++;
            }

            if ($j > 0) {
                echo '</ul>' . PHP_EOL;
            }
            echo '</li>';
            $i++;
        }   // end for

        if ($i > 0) {
            echo '</ul>' . PHP_EOL;
        } else {
            echo '<p class="p-2 text-center">등록된 분류가 없습니다.</p>' . PHP_EOL;
        }
        ?>
    </div>
</div>

<script>
    //카테고리 높이 맞추기
    $(function() {
        var list1_height = '';
        var list2_height = '';
        $('#category').mouseover(function() {
            //1단계 카테고리 높이
            list1_height = $('.list-group.depth1').height();
            $('.list-group.depth1').children('.list-group-item').mouseover(function() {
                list2_height = $(this).children('.list-group.depth2').height();
                //1단계 높이가 더 높으면
                if (list1_height > list2_height) {
                    $(this).children('.list-group.depth2').height(list1_height);
                }
            });

            $('.list-group.depth2').children('.list-group-item').mouseover(function() {
                var list3_height = $(this).children('.list-group.depth3').height();
                if (list2_height > list3_height) {
                    $(this).children('.list-group.depth3').height(list2_height);
                }
            });


        });

    });
</script>