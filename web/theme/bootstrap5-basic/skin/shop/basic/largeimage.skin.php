<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
?>

<div id="sit_pvi_nw" class="new_win large-image-wrap">
    <div class="container-fluid">
        <div class="bg-white border p-3 p-lg-4 mt-2">
            <h1 id="win_title" class="fs-4 fw-bolder ff-noto mb-0"> <i class="bi bi-image"></i> 상품 이미지 새창 보기</h1>
        </div>
    </div>
    <div class="container-fluid">
        <div class="bg-white border p-2 p-lg-4 mt-2">

            <div class="img-fluid">
                <div class="swiper largeimageSwiper">
                    <?php
                    $image = array();
                    for ($i = 1; $i <= 10; $i++) {
                        $image[$i]['file'] = G5_DATA_PATH . '/item/' . $row['it_img' . $i];
                        $image[$i]['url'] = G5_DATA_URL . '/item/' . $row['it_img' . $i];
                        $image[$i]['size'] = file_exists($image[$i]['file']) ? @getimagesize($image[$i]['file']) : array();
                    }
                    //print_t($image);
                    ?>
                    <!-- Swiper -->
                    <div class="swiper-wrapper">
                        <?php $i = 1;
                        foreach ($image as $img) { ?>
                            <div data-hash="slide<?php echo $i ?>" class="swiper-slide d-flex justify-content-center align-content-center">
                                <?php echo "<img src='{$img['url']}' class='img-fluid align-content-center d-flex'>"; ?>
                            </div>
                        <?php
                            $i++;
                        } ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>
        </div>

        <div class="d-flex p-2 mt-3 mb-3 justify-content-center">
            <button type="button" onclick="javascript:window.close();" class="btn_close btn btn-outline-danger">창닫기</button>
        </div>
    </div>
</div>
</div>
<!-- Initialize Swiper -->
<script>
    var swiper = new Swiper(".largeimageSwiper", {
        autoHeight: false,
        hashNavigation: {
            watchState: true,
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
    });
</script>