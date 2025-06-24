<?php

/**
 * FAQ Skin
 */
if (!defined('_GNUBOARD_')) {
    exit;
}

add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/faq/faq-basic.css">', 120);
?>
<?php if ($himg_src) { ?>
    <!-- 상단이미지가 있을 경우. -->
    <div class="hero-header-wrap">
        <section class="top-hero-section sub-page-hero background-zoom">
            <div class="hero-wrap container">
                <h1 class="hero-title"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
                <p class="lead-text delay-1">
                    FAQ 목록
                </p>
            </div>
        </section>
    </div>
    <style>
        .top-hero-section::before {
            /* 배경이미지 */
            background-image: url(<?php echo $himg_src ?>);
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
        }
    </style>
<?php } else { ?>
    <div class="faq-header container mt-2">
        <div class="bg-white border p-3 p-lg-4">
            <h1 class="fs-3 fw-bolder"><i class="bi bi-question-circle"></i> <?php echo $g5['title'] ?></h1>
        </div>
    </div>
<?php } ?>
<div class='container faq-wrap'>
    <?php if (isset($fm['fm_head_html']) && $fm['fm_head_html']) { ?>
        <div class="alert alert-success mt-2" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-square-fill"></i> Notice</h4>
            <hr>
            <p class="mb-0"><?php echo conv_content($fm['fm_head_html'], 1) ?></p>
        </div>
    <?php } ?>
    <div class='d-flex justify-content-end mt-2 mb-2'>
        <!-- Button trigger modal -->
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#faq-search">
            <i class="bi bi-search"></i>
        </button>
        <form name="faq_search_form" method="get">
            <div class="modal fade" id="faq-search" tabindex="-1" aria-labelledby="faq-searchLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">FAQ 검색</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="fm_id" value="<?php echo $fm_id; ?>">
                            <label for="stx" class="visually-hidden">검색어<strong class="visually-hidden"> 필수</strong></label>
                            <input type="text" name="stx" value="<?php echo $stx; ?>" required class="form-control" size="15" maxlength="15" placeholder="검색어를 입력하세요.">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" value="검색" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> 검색</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
    <?php
    if (isset($faq_master_list) && count($faq_master_list)) { ?>
        <nav class="item-list mt-5">
            <span class="visually-hidden">자주하시는질문 분류</span>
            <ul class="nav nav-tabs">
                <?php
                foreach ($faq_master_list as $v) {
                    $category_msg = '';
                    $category_option = '';
                    $active = '';
                    if ($v['fm_id'] == $fm_id) { // 현재 선택된 카테고리라면
                        $category_option = ' id="bo_cate_on"';
                        $category_msg = '<span class="visually-hidden">열린 분류 </span>';
                        $active = 'active';
                    }
                ?>
                    <li class="nav-item"><a href="<?php echo $category_href; ?>?fm_id=<?php echo $v['fm_id']; ?>" <?php echo $category_option; ?> class="nav-link <?php echo $active ?>"><?php echo $category_msg . $v['fm_subject']; ?></a></li>
                <?php
                }
                ?>
            </ul>
        </nav>
    <?php } ?>

    <!-- FAQ 내용 -->
    <div class="faq_<?php echo $fm_id; ?> faq-content-wrap bg-white border border-top-0 p-2 p-lg-4 mb-4">
        <?php if (isset($faq_list) && count($faq_list)) { ?>
            <article class="accordion accordion-flush" id="accordion-item">
                <span class="visually-hidden"><?php echo $g5['title']; ?> 목록</span>
                <?php
                $i = 0;
                foreach ($faq_list as $key => $v) {
                    if (empty($v)) {
                        continue;
                    }
                ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $i ?>">
                            <div class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i ?>" aria-expanded="false" aria-controls="collapse<?php echo $i ?>">
                                <span class="fw-bolder fs-6 text-primary"><i class="bi bi-question"></i> 질문 &nbsp;</span>
                                <div class='d-block'>
                                    <?php echo conv_content($v['fa_subject'], 1); ?>
                                </div>
                            </div>
                        </h2>
                        <div id="collapse<?php echo $i ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $i ?>" data-bs-parent="#accordion-item">
                            <div class="accordion-body">
                                <?php echo conv_content($v['fa_content'], 1); ?>
                            </div>
                        </div>
                    </div>
                <?php
                    $i++;
                } ?>
            </article>
        <?php

        } else {
            if ($stx) {
                echo '<div class="empty-item p-5 center">검색된 게시물이 없습니다.</div>';
            } else {
                echo '<div class="empty-item p-5 center">등록된 FAQ가 없습니다.';
                if ($is_admin) {
                    echo '<a href="' . G5_ADMIN_URL . '/faqmasterlist.php">FAQ를 새로 등록하시려면 FAQ관리</a> 메뉴를 이용하십시오.';
                }
                echo '</div>';
            }
        }
        ?>
    </div>

    <div class='paging-wrap d-fex flex-column mt-5 mb-5 justify-content-center'>
        <?php
        //반응형 PC 테마를 사용하기 때문에 페이징 함수 불러와 사용.
        $write_pages = get_paging(is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page,  $_SERVER['SCRIPT_NAME'] . '?' . $qstr . '&amp;page=');
        $paging = str_replace("sound_only", "visually-hidden", $write_pages);
        $paging = str_replace("처음", "<i class='bi bi-chevron-double-left'></i>", $paging);
        $paging = str_replace("이전", "<i class='bi bi-chevron-compact-left'></i>", $paging);
        $paging = str_replace("다음", "<i class='bi bi-chevron-compact-right'></i>", $paging);
        $paging = str_replace("맨끝", "<i class='bi bi-chevron-double-right'></i>", $paging);
        echo $paging;
        ?>
    </div>
    <?php if (isset($fm['fm_tail_html']) && $fm['fm_tail_html']) { ?>
        <div class="alert alert-success mt-2" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-square-fill"></i> Notice</h4>
            <hr>
            <p class="mb-0"><?php echo conv_content($fm['fm_tail_html'], 1) ?></p>
        </div>
    <?php }
    if ($timg_src) {
        echo '<div class="tail-image-wrap"><img src="' . $timg_src . '" alt="" class="img-fluid"></div>';
    }

    if ($admin_href)
        echo '<div class="faq-admin-button d-flex justify-content-end mt-5"><a href="' . $admin_href . '" class="btn btn-danger"><i class="bi bi-gear"></i><span class="visually-hidden">FAQ 수정</span></a></div>';
    ?>
</div>
<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>