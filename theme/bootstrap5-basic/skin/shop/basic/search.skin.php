<?php
if (!defined('_GNUBOARD_')) {
    exit;
}
?>
<div class="container">
    <div class="bg-white border p-3 p-lg-4 mt-2">
        <h2 class="fs-5 fw-bolder ff-noto"><i class="bi bi-search"></i> <?php echo $q; ?> 검색 결과 <span class="fs-6 fw-bold ff-noto text-secondary">총 <?php echo $total_count; ?>건</span></h2>
    </div>

    <div class="bg-white border p-2 p-lg-4 mt-2 mb-2">
        <div class="search-form-wrap d-flex justify-content-center ml-auto me-auto">
            <form name="frmdetailsearch">
                <input type="hidden" name="qsort" id="qsort" value="<?php echo $qsort ?>">
                <input type="hidden" name="qorder" id="qorder" value="<?php echo $qorder ?>">
                <input type="hidden" name="qcaid" id="qcaid" value="<?php echo $qcaid ?>">
                <div class="input-group">
                    <label for="ssch_q" class="visually-hidden">검색어</label>
                    <input type="text" name="q" value="<?php echo $q; ?>" id="ssch_q" class="form-control" size="40" maxlength="30" placeholder="검색어 입력">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search" aria-hidden="true"></i> 검색</button>
                </div>
                <div class="alert alert-info alert-sm mt-1 p-2">
                    상세검색을 선택하지 않거나, 상품가격을 입력하지 않으면 전체에서 검색합니다. 검색어는 최대 30글자까지, 여러개의 검색어를 공백으로 구분하여 입력 할수 있습니다.
                </div>
                <div class="search-options row g-1 justify-content-center">
                    <div class="col-auto">
                        <strong class="visually-hidden">검색범위</strong>
                        <input type="checkbox" name="qname" id="ssch_qname" value="1" <?php echo $qname_check ? 'checked="checked"' : ''; ?>> <label for="ssch_qname"><span></span>상품명</label>
                    </div>
                    <div class="col-auto">
                        <input type="checkbox" name="qexplan" id="ssch_qexplan" value="1" <?php echo $qexplan_check ? 'checked="checked"' : ''; ?>> <label for="ssch_qexplan"><span></span>상품설명</label>
                    </div>
                    <div class="col-auto">
                        <input type="checkbox" name="qbasic" id="ssch_qbasic" value="1" <?php echo $qbasic_check ? 'checked="checked"' : ''; ?>> <label for="ssch_qbasic"><span></span>기본설명</label>
                    </div>
                    <div class="col-auto">
                        <input type="checkbox" name="qid" id="ssch_qid" value="1" <?php echo $qid_check ? 'checked="checked"' : ''; ?>> <label for="ssch_qid"><span></span>상품코드</label>
                    </div>
                    <div class="col-auto">
                        <strong class="visually-hidden">상품가격 (원)</strong>
                        <label for="ssch_qfrom" class="visually-hidden">최소 가격</label>
                        <input type="text" name="qfrom" value="<?php echo $qfrom; ?>" id="ssch_qfrom" class="form-inline border" size="10" placeholder="가격범위"> 원
                    </div>
                    <div class="col-auto"> ~ </div>
                    <div class="col-auto">
                        <label for="ssch_qto" class="visually-hidden">최대 가격</label>
                        <input type="text" name="qto" value="<?php echo $qto; ?>" id="ssch_qto" class="form-inline border" size="10"> 원
                    </div>
                </div>
            </form>
        </div>
        <div class='result-category mt-4'>
            <div class="d-flex flex-wrap flex-row">
                <?php
                echo '<div class="flex-fill cate-item p-2 m-1"><a href="#" onclick="set_ca_id(\'\'); return false;">전체분류 <span>(' . $total_count . ')</span></a></div>' . PHP_EOL;
                $total_cnt = 0;
                foreach ((array) $categorys as $row) {
                    if (empty($row)) continue;
                    echo "<div class='flex-fill cate-item p-2 m-1'><a href=\"#\" onclick=\"set_ca_id('{$row['ca_id']}'); return false;\">{$row['ca_name']} (" . $row['cnt'] . ")</a></div>\n";
                    $total_cnt += $row['cnt'];
                }
                ?>
            </div>
        </div>
    </div>
</div>
<div class="item-sort-wrap mt-2">
    <h2 class="visually-hidden">상품 정렬</h2>
    <div class="container">
        <div class="item-sort-wrap">
            <ul class="list-group list-group-horizontal justify-content-end">
                <li class="list-group-item text-center d-flex <?php echo $sort == 'it_sum_qty' ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_sum_qty', 'desc'); return false;">판매 <i class="bi bi-arrow-up-circle"></i></a></li>
                <li class="list-group-item text-center d-flex <?php echo ($sort == 'it_price' && $sortodr == 'asc') ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_price', 'asc'); return false;">가격 <i class="bi bi-arrow-up-circle"></i></a></li>
                <li class="list-group-item text-center d-flex <?php echo ($sort == 'it_price' && $sortodr == 'desc')  ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_price', 'desc'); return false;">가격 <i class="bi bi-arrow-down-circle"></i></a></li>
                <li class="list-group-item text-center d-flex <?php echo $sort == 'it_use_avg' ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_use_avg', 'desc'); return false;">평점 <i class="bi bi-arrow-up-circle"></i></a></li>
                <li class="list-group-item text-center d-flex <?php echo $sort == 'it_use_cnt' ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_use_cnt', 'desc'); return false;">후기 <i class="bi bi-arrow-up-circle"></i></a></li>
                <li class="list-group-item text-center d-flex <?php echo $sort == 'it_update_time' ? 'active' : ''; ?>"><a href="#" onclick="set_sort('it_update_time', 'desc'); return false;">최신<span class="d-none d-md-inline">등록순</span></a></li>
            </ul>
        </div>
    </div>
</div>
<div class="search-result-wrap">
    <div class="result">
        <?php
        // 리스트 유형별로 출력
        if (isset($list) && is_object($list) && method_exists($list, 'run')) {
            $list->set_is_page(true);
            $list->set_view('it_img', true);
            $list->set_view('it_name', true);
            $list->set_view('it_basic', true);
            $list->set_view('it_cust_price', false);
            $list->set_view('it_price', true);
            $list->set_view('it_icon', true);
            $list->set_view('sns', true);
            $list->set_view('star', true);
            echo $list->run();
        } else {
            $i = 0;
            $error = '<div class="alert alert-danger">' . $list_file . ' 파일을 찾을 수 없습니다. 관리자에게 알려주시면 감사하겠습니다.</div>';
        }

        if ($i == 0) {
            echo '<div>' . $error . '</div>';
        }

        $query_string = 'qname=' . $qname . '&amp;qexplan=' . $qexplan . '&amp;qid=' . $qid;
        if ($qfrom && $qto) $query_string .= '&amp;qfrom=' . $qfrom . '&amp;qto=' . $qto;
        $query_string .= '&amp;qcaid=' . $qcaid . '&amp;q=' . urlencode($q);
        $query_string .= '&amp;qsort=' . $qsort . '&amp;qorder=' . $qorder;
        echo get_paging($config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'] . '?' . $query_string . '&amp;page=');
        ?>
    </div>
    <!-- } 검색결과 끝 -->
</div>
<!-- } 검색 끝 -->

<script>
    function set_sort(qsort, qorder) {
        var f = document.frmdetailsearch;
        f.qsort.value = qsort;
        f.qorder.value = qorder;
        f.submit();
    }

    function set_ca_id(qcaid) {
        var f = document.frmdetailsearch;
        f.qcaid.value = qcaid;
        f.submit();
    }
</script>