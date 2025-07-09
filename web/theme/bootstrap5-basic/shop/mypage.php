<?php
if (!defined("_GNUBOARD_")) {
    exit;
}

$g5['title'] = '마이페이지';
include_once './_head.php';

// 쿠폰
$cp_count = 0;
$sql = " SELECT cp_id
            from {$g5['g5_shop_coupon_table']}
            where mb_id IN ( '{$member['mb_id']}', '전체회원' )
            and cp_start <= '" . G5_TIME_YMD . "'
            and cp_end >= '" . G5_TIME_YMD . "' ";
$res = sql_query($sql);

for ($k = 0; $cp = sql_fetch_array($res); $k++) {
    if (!is_used_coupon($member['mb_id'], $cp['cp_id'])) {
        $cp_count++;
    }
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/shop/mypage.css">', 200);

?>

<div id="smb_my" class="mypage-wrap container mt-2">
    <div class="bg-white p-4 border mb-2">
        <h1 class="fs-4 fw-bolder mb-0"><i class="bi bi-person-circle"></i> <?php echo $g5['title'] ?></h1>
    </div>
    <div class="bg-white p-4 border">
        <h2 class="fs-5 fw-bolder mb-2 pb-2 border-bottom">회원정보 개요</h2>
        <section id="smb_my_ov" class="member-ouline-wrap d-flex flex-row flex-wrap mb-5">
            <div class="member-image-wrap p-2 d-flex flex-fill justify-content-center align-content-center flex-wrap flex-column">
                <div class="member-image align-content-center flex-wrap text-center"><?php echo get_member_profile_img($member['mb_id']); ?><br><?php echo $member['mb_name']; ?></div>
                <div class="align-content-center justify-content-center flex-wrap d-flex text-center">
                    <a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=register_form.php" class="btn btn-outline-primary me-1">정보수정</a>
                    <a href="<?php echo G5_BBS_URL ?>/logout.php" class="btn btn-outline-secondary">로그아웃</a>
                </div>
                <div class="member-assets align-content-center justify-content-center flex-wrap d-flex mt-2">
                    <ul id="smb_private" class="list-group list-group-horizontal-md">
                        <li class="list-group-item">
                            <a href="<?php echo G5_BBS_URL ?>/point.php" target="_blank" class="win_point">
                                <i class="fa fa-database" aria-hidden="true"></i> 포인트
                                <strong><?php echo number_format($member['mb_point']); ?></strong>
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="<?php echo G5_SHOP_URL ?>/coupon.php" target="_blank" class="win_coupon">
                                <i class="fa fa-ticket" aria-hidden="true"></i> 쿠폰
                                <strong><?php echo number_format($cp_count); ?></strong>
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="<?php echo G5_BBS_URL ?>/memo.php" target="_blank" class="win_memo">
                                <i class="fa fa-envelope-o" aria-hidden="true"></i> <span class="visually-hidden">안 읽은 </span>쪽지
                                <strong><?php echo $memo_not_read ?></strong>
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="<?php echo G5_BBS_URL ?>/scrap.php" target="_blank" class="win_scrap">
                                <i class="fa fa-thumb-tack" aria-hidden="true"></i> 스크랩
                                <strong class="scrap"><?php echo number_format($member['mb_scrap_cnt']); ?></strong>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="member-image-wrap p-2 d-flex flex-fill justify-content-center align-content-center flex-wrap flex-column">
                <dl class="list-group">
                    <li class="list-group-item">연락처 : <?php echo ($member['mb_tel'] ? $member['mb_tel'] : '미등록'); ?></li>
                    <li class="list-group-item">E-Mail : <?php echo ($member['mb_email'] ? $member['mb_email'] : '미등록'); ?></li>
                    <li class="list-group-item">최종접속일시 : <?php echo $member['mb_today_login']; ?></li>
                    <li class="list-group-item">회원가입일시 : <?php echo $member['mb_datetime']; ?></li>
                    <li class="list-group-item" id="smb_my_ovaddt">주소 : <?php echo sprintf("(%s%s)", $member['mb_zip1'], $member['mb_zip2']) . ' ' . print_address($member['mb_addr1'], $member['mb_addr2'], $member['mb_addr3'], $member['mb_addr_jibeon']); ?></li>
                    <li class="list-group-item text-end"><a href="<?php echo G5_BBS_URL; ?>/member_confirm.php?url=member_leave.php" onclick="return memberLeave();" class="withdrawal btn btn-outline-danger btn-sm">회원탈퇴</a></li>
                </dl>
            </div>

        </section>

        <div id="smb_my_list" class="my-list">
            <!-- 최근 주문내역 시작 { -->
            <section id="smb_my_od" class="my-order-list mb-5">
                <h2 class="fs-5 fw-bolder border-bottom pb-2 mb-2">주문내역조회</h2>
                <?php
                // 최근 주문내역
                define("_ORDERINQUIRY_", true);

                $limit = " limit 0, 5 ";
                include G5_SHOP_PATH . '/orderinquiry.sub.php';
                ?>

                <div class="d-flex">
                    <a href="./orderinquiry.php" class="btn btn-outline-secondary ms-auto btn-sm ">더보기</a>
                </div>
            </section>
            <!-- } 최근 주문내역 끝 -->

            <!-- 최근 위시리스트 시작 { -->
            <section id="smb_my_wish" class="my-wish-list">
                <h2 class="fs-5 fw-bolder border-bottom pb-2 mb-2">최근 위시리스트</h2>
                <form name="fwishlist" method="post" action="./cartupdate.php">
                    <input type="hidden" name="act" value="multi">
                    <input type="hidden" name="sw_direct" value="">
                    <input type="hidden" name="prog" value="wish">
                    <div class="row g-2">
                        <?php
                        $sql = " SELECT *
                           from {$g5['g5_shop_wish_table']} a,
                                {$g5['g5_shop_item_table']} b
                          where a.mb_id = '{$member['mb_id']}'
                            and a.it_id  = b.it_id
                          order by a.wi_id desc
                          limit 0, 8 ";
                        $result = sql_query($sql);
                        for ($i = 0; $row = sql_fetch_array($result); $i++) {
                            $image = get_it_image($row['it_id'], 400, 300, true);

                            $sql = " SELECT count(*) as cnt from {$g5['g5_shop_item_option_table']} where it_id = '{$row['it_id']}' and io_type = '0' ";
                            $tmp = sql_fetch($sql);
                            $out_cd = (isset($tmp['cnt']) && $tmp['cnt']) ? 'no' : '';
                        ?>

                            <div class="col-sm-12 col-md-3 mb-3">
                                <div class="wish-item p-2 position-relative h-100">
                                    <div class="position-relative text-center mb-2 image-wrap">
                                        <div class="position-absolute">
                                            <?php if (is_soldout($row['it_id'])) { ?>
                                                품절
                                            <?php } else { ?>
                                                <div class="chk_box">
                                                    <input type="checkbox" name="chk_it_id[<?php echo $i; ?>]" value="1" id="chk_it_id_<?php echo $i; ?>" onclick="out_cd_check(this, '<?php echo $out_cd; ?>');" class="selec_chk">
                                                    <label for="chk_it_id_<?php echo $i; ?>"><span></span><b class="visually-hidden"><?php echo $row['it_name']; ?></b></label>
                                                </div>
                                            <?php } ?>
                                            <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
                                            <input type="hidden" name="io_type[<?php echo $row['it_id']; ?>][0]" value="0">
                                            <input type="hidden" name="io_id[<?php echo $row['it_id']; ?>][0]" value="">
                                            <input type="hidden" name="io_value[<?php echo $row['it_id']; ?>][0]" value="<?php echo $row['it_name']; ?>">
                                            <input type="hidden" name="ct_qty[<?php echo $row['it_id']; ?>][0]" value="1">
                                        </div>
                                        <?php echo $image; ?>
                                    </div>
                                    <div class="wish-title fw-bolder"><a href="<?php echo shop_item_url($row['it_id']); ?>"><?php echo stripslashes($row['it_name']); ?></a></div>
                                    <div class="wish-price"><?php echo display_price(get_price($row), $row['it_tel_inq']); ?></div>
                                    <div class="wish-date"><?php echo $row['wi_time']; ?></div>
                                    <div class="wish-delete position-absolute">
                                        <a href="./wishupdate.php?w=d&amp;wi_id=<?php echo $row['wi_id']; ?>" class="wish_del btn btn-outline-danger border-0"><i class="fa fa-trash" aria-hidden="true"></i><span class="visually-hidden">삭제</span></a>
                                    </div>
                                </div>
                            </div>
                        <?php
                        }

                        if ($i == 0) {
                            echo '<div class="col-12"><div class="p-4 text-center">보관 내역이 없습니다.</div></div>';
                        }
                        ?>
                    </div>

                    <div class="d-flex">
                        <a href="./wishlist.php" class="btn btn-outline-secondary btn-sm ms-auto">더보기</a>
                    </div>

                    <div id="smb_ws_act" class="btn-group">
                        <button type="submit" class="btn01 btn btn-outline-primary" onclick="return fwishlist_check(document.fwishlist,'');"><i class="bi bi-cart3"></i> 장바구니</button>
                        <button type="submit" class="btn02 btn btn-outline-success" onclick="return fwishlist_check(document.fwishlist,'direct_buy');"><i class="bi bi-bag-fill"></i> 주문하기</button>
                    </div>
                </form>
            </section>
            <!-- } 최근 위시리스트 끝 -->
        </div>
    </div>

    <script>
        function memberLeave() {
            if (confirm('정말 회원에서 탈퇴 하시겠습니까?')) {
                return true;
            } else {
                return false;
            }
        }

        function out_cd_check(fld, out_cd) {
            if (out_cd == 'no') {
                alert("옵션이 있는 상품입니다.\n\n상품을 클릭하여 상품페이지에서 옵션을 선택한 후 주문하십시오.");
                fld.checked = false;
                return;
            }

            if (out_cd == 'tel_inq') {
                alert("이 상품은 전화로 문의해 주십시오.\n\n장바구니에 담아 구입하실 수 없습니다.");
                fld.checked = false;
                return;
            }
        }

        function fwishlist_check(f, act) {
            var k = 0;
            var length = f.elements.length;

            for (i = 0; i < length; i++) {
                if (f.elements[i].checked) {
                    k++;
                }
            }

            if (k == 0) {
                alert("상품을 하나 이상 체크 하십시오");
                return false;
            }

            if (act == "direct_buy") {
                f.sw_direct.value = 1;
            } else {
                f.sw_direct.value = 0;
            }

            return true;
        }
    </script>
</div>
<?php
include_once "./_tail.php";
