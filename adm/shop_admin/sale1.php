<?php
$sub_menu = '500110';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, "r");

// 계층별 필터링을 위한 GET 파라미터 처리
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

$g5['title'] = '매출현황';
include_once (G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');
?>

<div class="local_sch03 local_sch">

    <!-- 도매까 계층 선택박스 (NEW) -->
    <div class="hierarchy_filter">
        <form name="fhierarchy" id="fhierarchy" method="get">
            <?php
            // 도매까 체인 선택박스 포함
            include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
            
            echo dmk_render_chain_select([
                'page_type' => DMK_CHAIN_SELECT_FULL,
                'auto_submit' => true,
                'form_id' => 'fhierarchy',
                'field_names' => [
                    'distributor' => 'sdt_id',
                    'agency' => 'sag_id', 
                    'branch' => 'sbr_id'
                ],
                'current_values' => [
                    'sdt_id' => $filter_dt_id,
                    'sag_id' => $filter_ag_id,
                    'sbr_id' => $filter_br_id
                ],
                'placeholders' => [
                    'distributor' => '전체 총판',
                    'agency' => '전체 대리점',
                    'branch' => '전체 지점'
                ]
            ]);
            ?>
        </form>
        <?php
        // 도매까 권한 정보 조회
        $dmk_auth = dmk_get_admin_auth();
        if ($dmk_auth && $dmk_auth['mb_type'] != 3) { // 지점 관리자가 아닌 경우에만 메시지 표시
        ?>
        <div class="hierarchy_desc">
            선택된 계층에 따라 매출 조회 범위가 제한됩니다.<br>
            아래 매출 조회 시 선택된 계층 정보가 자동으로 반영됩니다.
        </div>
        <?php } ?>
    </div>
    <!-- //도매까 계층 선택박스 -->

    <div>
        <form name="frm_sale_today" action="./sale1today.php" method="get">
        <!-- 계층 필터 정보 전달을 위한 hidden 필드 -->
        <?php if ($filter_dt_id) { ?><input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>"><?php } ?>
        <?php if ($filter_ag_id) { ?><input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>"><?php } ?>
        <?php if ($filter_br_id) { ?><input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>"><?php } ?>
        
        <strong>일일 매출</strong>
        <input type="text" name="date" value="<?php echo date("Ymd", G5_SERVER_TIME); ?>" id="date" required class="required frm_input" size="8" maxlength="8">
        <label for="date">일 하루</label>
        <input type="submit" value="확인" class="btn_submit">
        </form>
    </div>

    <div>
        <form name="frm_sale_date" action="./sale1date.php" method="get">
        <!-- 계층 필터 정보 전달을 위한 hidden 필드 -->
        <?php if ($filter_dt_id) { ?><input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>"><?php } ?>
        <?php if ($filter_ag_id) { ?><input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>"><?php } ?>
        <?php if ($filter_br_id) { ?><input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>"><?php } ?>
        
        <strong>일간 매출</strong>
        <input type="text" name="fr_date" value="<?php echo date("Ym01", G5_SERVER_TIME); ?>" id="fr_date" required class="required frm_input" size="8" maxlength="8">
        <label for="fr_date">일 ~</label>
        <input type="text" name="to_date" value="<?php echo date("Ymd", G5_SERVER_TIME); ?>" id="to_date" required class="required frm_input" size="8" maxlength="8">
        <label for="to_date">일</label>
        <input type="submit" value="확인" class="btn_submit">
        </form>
    </div>

    <div>
        <form name="frm_sale_month" action="./sale1month.php" method="get">
        <!-- 계층 필터 정보 전달을 위한 hidden 필드 -->
        <?php if ($filter_dt_id) { ?><input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>"><?php } ?>
        <?php if ($filter_ag_id) { ?><input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>"><?php } ?>
        <?php if ($filter_br_id) { ?><input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>"><?php } ?>
        
        <strong>월간 매출</strong>
        <input type="text" name="fr_month" value="<?php echo date("Y01", G5_SERVER_TIME); ?>" id="fr_month" required class="required frm_input" size="6" maxlength="6">
        <label for="fr_month">월 ~</label>
        <input type="text" name="to_month" value="<?php echo date("Ym", G5_SERVER_TIME); ?>" id="to_month" required class="required frm_input" size="6" maxlength="6">
        <label for="to_month">월</label>
        <input type="submit" value="확인" class="btn_submit">
        </form>
    </div>

    <div class="sch_last">
        <form name="frm_sale_year" action="./sale1year.php" method="get">
        <!-- 계층 필터 정보 전달을 위한 hidden 필드 -->
        <?php if ($filter_dt_id) { ?><input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id; ?>"><?php } ?>
        <?php if ($filter_ag_id) { ?><input type="hidden" name="sag_id" value="<?php echo $filter_ag_id; ?>"><?php } ?>
        <?php if ($filter_br_id) { ?><input type="hidden" name="sbr_id" value="<?php echo $filter_br_id; ?>"><?php } ?>
        
        <strong>연간 매출</strong>
        <input type="text" name="fr_year" value="<?php echo date("Y", G5_SERVER_TIME)-1; ?>" id="fr_year" required class="required frm_input" size="4" maxlength="4">
        <label for="fr_year">년 ~</label>
        <input type="text" name="to_year" value="<?php echo date("Y", G5_SERVER_TIME); ?>" id="to_year" required class="required frm_input" size="4" maxlength="4">
        <label for="to_year">년</label>
        <input type="submit" value="확인" class="btn_submit">
        </form>
    </div>

</div>

<script>
$(function() {
    $("#date, #fr_date, #to_date").datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: "yymmdd",
        showButtonPanel: true,
        yearRange: "c-99:c+99",
        maxDate: "+0d"
    });
});
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');