<?php
$sub_menu = '400200';
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

auth_check_menu($auth, $sub_menu, "r");

// 도매까 권한 확인 - 총판 관리자만 분류 관리 가능
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] > 1) {
    alert('분류관리는 총판 관리자만 접근할 수 있습니다.', G5_ADMIN_URL);
}

// JavaScript에서 사용할 PHP 상수 정의
define('DMK_MB_TYPE_SUPER_ADMIN', 1);
define('DMK_MB_TYPE_DISTRIBUTOR', 2);
define('DMK_MB_TYPE_AGENCY', 3);
define('DMK_MB_TYPE_BRANCH', 4);

// DMK_OWNER_TYPE 관련 상수는 admin.auth.lib.php에 정의되어 있을 것으로 예상되므로, 여기서는 직접 정의하지 않고 사용합니다.

$dmk_js_consts = [
    'DMK_OWNER_TYPE_DISTRIBUTOR' => DMK_OWNER_TYPE_DISTRIBUTOR,
    'DMK_OWNER_TYPE_AGENCY' => DMK_OWNER_TYPE_AGENCY,
    'DMK_OWNER_TYPE_BRANCH' => DMK_OWNER_TYPE_BRANCH,
    'DMK_MB_TYPE_SUPER_ADMIN' => DMK_MB_TYPE_SUPER_ADMIN,
    'DMK_MB_TYPE_DISTRIBUTOR' => DMK_MB_TYPE_DISTRIBUTOR,
    'DMK_MB_TYPE_AGENCY' => DMK_MB_TYPE_AGENCY,
    'DMK_MB_TYPE_BRANCH' => DMK_MB_TYPE_BRANCH,
];

$g5['title'] = '분류관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 계층 필터링을 위한 변수 초기화
$selected_dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';
$selected_ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';
$selected_br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';

$display_dt_select = false;
$display_ag_select = false;
$display_br_select = false;

$distributors = [];
$agencies = [];
$branches = [];

// 현재 로그인한 관리자 유형에 따른 선택 박스 표시 및 초기 데이터 설정
if ($dmk_auth['is_super']) {
    $display_dt_select = true;
    $display_ag_select = true;
    $display_br_select = true;
    $distributors = dmk_get_distributors();

    // 선택된 총판이 있으면 해당 대리점 목록 로드
    if ($selected_dt_id) {
        $agencies = dmk_get_agencies($selected_dt_id);
    }
    // 선택된 대리점이 있으면 해당 지점 목록 로드
    if ($selected_ag_id) {
        $branches = dmk_get_branches($selected_ag_id);
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
    // 총판 관리자는 대리점 및 지점 선택 가능
    $display_ag_select = true;
    $display_br_select = true;
    // 자신의 총판 ID를 필터링에 기본 적용
    $selected_dt_id = $dmk_auth['mb_id']; 
    $distributors = [['mb_id' => $dmk_auth['mb_id'], 'mb_name' => $dmk_auth['mb_name']]]; // 자신의 총판만 목록에 표시

    // 자신의 총판에 속한 대리점 목록 로드 (선택된 대리점이 없거나 선택된 총판이 자신인 경우)
    $agencies = dmk_get_agencies($dmk_auth['mb_id']);

    // 선택된 대리점이 있으면 해당 지점 목록 로드
    if ($selected_ag_id) {
        $branches = dmk_get_branches($selected_ag_id);
    }
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    // 대리점 관리자는 지점만 선택 가능
    $display_br_select = true;
    // 자신의 대리점 ID를 필터링에 기본 적용
    $selected_ag_id = $dmk_auth['ag_id'];
    $selected_dt_id = dmk_get_agency_distributor_id($dmk_auth['ag_id']); // 상위 총판 ID 설정 (조회용)
    $agencies = [['ag_id' => $dmk_auth['ag_id'], 'ag_name' => $dmk_auth['ag_name']]]; // 자신의 대리점만 목록에 표시

    // 자신의 대리점에 속한 지점 목록 로드
    $branches = dmk_get_branches($dmk_auth['ag_id']);
} else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
    // 지점 관리자는 선택박스 없이 자신의 정보로 고정
    $selected_br_id = $dmk_auth['br_id'];
    $selected_ag_id = dmk_get_branch_agency_id($dmk_auth['br_id']); // 상위 대리점 ID 설정 (조회용)
    $selected_dt_id = dmk_get_agency_distributor_id($selected_ag_id); // 상위 총판 ID 설정 (조회용)
}

$where = " where ";
$sql_search = "";

$sfl = in_array($sfl, array('ca_name', 'ca_id', 'ca_mb_id')) ? $sfl : '';

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if (isset($save_stx) && $save_stx && ($save_stx != $stx))
        $page = 1;
}

// 계층 필터링 조건 추가
$hierarchy_where = dmk_get_category_where_condition($selected_dt_id, $selected_ag_id, $selected_br_id);
if ($hierarchy_where) {
    $sql_search .= " $where 1=1 $hierarchy_where ";
    $where = " and ";
}

$sql_common = " from {$g5['g5_shop_category_table']} ";
// 기존 is_admin != 'super' 조건은 dmk_get_category_where_condition에서 처리되므로 제거
// if ($is_admin != 'super')
//     $sql_search .= " $where ca_mb_id = '{$member['mb_id']}' ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

if (!$sst)
{
    $sst  = "ca_id";
    $sod = "asc";
}
$sql_order = "order by $sst $sod";

// 출력할 레코드를 얻음
$sql  = " select *
             $sql_common
             $sql_order
             limit $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">생성된  분류 수</span><span class="ov_num">  <?php echo number_format($total_count); ?>개</span></span>
</div>

<form name="fsearch" id="fsearch" class="frm_search" action="<?php echo G5_ADMIN_URL; ?>/shop_admin/categorylist.php" method="get">
    <input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
    <input type="hidden" name="sst" value="<?php echo $sst; ?>">
    <input type="hidden" name="sod" value="<?php echo $sod; ?>">

    <?php if ($display_dt_select) { ?>
    <div style="display: inline-block; margin-right: 5px;">
        <label for="dt_id" class="sound_only">총판 선택</label>
        <select name="dt_id" id="dt_id">
            <option value="">- 총판 선택 -</option>
            <?php foreach ($distributors as $dt) { ?>
            <option value="<?php echo $dt['mb_id']; ?>" <?php echo get_selected($selected_dt_id, $dt['mb_id']); ?>><?php echo $dt['mb_name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <?php } ?>

    <?php if ($display_ag_select) { ?>
    <div style="display: inline-block; margin-right: 5px;">
        <label for="ag_id" class="sound_only">대리점 선택</label>
        <select name="ag_id" id="ag_id">
            <option value="">- 대리점 선택 -</option>
            <?php foreach ($agencies as $ag) { ?>
            <option value="<?php echo $ag['ag_id']; ?>" <?php echo get_selected($selected_ag_id, $ag['ag_id']); ?>><?php echo $ag['ag_name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <?php } ?>

    <?php if ($display_br_select) { ?>
    <div style="display: inline-block; margin-right: 5px;">
        <label for="br_id" class="sound_only">지점 선택</label>
        <select name="br_id" id="br_id">
            <option value="">- 지점 선택 -</option>
            <?php foreach ($branches as $br) { ?>
            <option value="<?php echo $br['br_id']; ?>" <?php echo get_selected($selected_br_id, $br['br_id']); ?>><?php echo $br['br_name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <?php } ?>

    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="ca_name" <?php echo get_selected($sfl, 'ca_name', true); ?>>분류명</option>
        <option value="ca_id" <?php echo get_selected($sfl, 'ca_id'); ?>>분류코드</option>
        <option value="ca_mb_id" <?php echo get_selected($sfl, 'ca_mb_id'); ?>>관리자ID</option>
    </select>
    <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
    <input type="text" name="stx" value="<?php echo $stx; ?>" required class="frm_input required" size="20">
    <button type="submit" class="btn_submit">검색</button>
</form>

<div class="btn_confirm">
    <a href="<?php echo G5_ADMIN_URL; ?>/shop_admin/categoryform.php" class="btn_frm_setup">분류 추가</a>
</div>

<h2>분류 목록</h2>

<div class="local_desc01 local_desc">
    <p>
        하위 분류는 더블 클릭하면 분류 등록 / 수정 화면으로 이동합니다.<br>
        분류명 클릭으로도 분류 등록 / 수정 화면으로 이동합니다.
    </p>
</div>

<form name="fcategorylist" id="fcategorylist" action="./categorylistupdate.php" method="post">
    <input type="hidden" name="sst" value="<?php echo $sst; ?>">
    <input type="hidden" name="sod" value="<?php echo $sod; ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
    <input type="hidden" name="stx" value="<?php echo $stx; ?>">
    <input type="hidden" name="page" value="<?php echo $page; ?>">
    <input type="hidden" name="token" value="<?php echo get_token(); ?>">

    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?> 목록</caption>
            <thead>
                <tr>
                    <th scope="col">
                        <label for="chkall" class="sound_only">분류 전체</label>
                        <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                    </th>
                    <th scope="col"><?php echo subject_sort_link('ca_id', '분류코드'); ?></th>
                    <th scope="col">이미지</th>
                    <th scope="col"><?php echo subject_sort_link('ca_name', '분류명'); ?></th>
                    <th scope="col">계층 정보</th>
                    <th scope="col"><?php echo subject_sort_link('ca_use', '노출'); ?></th>
                    <th scope="col"><?php echo subject_sort_link('ca_order', '순서'); ?></th>
                    <th scope="col">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (isset($list) && is_array($list)) {
                    for ($i=0; $i<count($list); $i++) {
                        $row = $list[$i];
                        $bg = 'bg'.($i%2);

                        $s_level = strlen($row['ca_id']) / 2 - 1;
                        $ca_name = '';
                        if ($s_level > 0) {
                            $ca_name = str_repeat('&nbsp;&nbsp;&nbsp;', $s_level) . '<img src="'.G5_ADMIN_URL.'/img/icon_tree.gif" alt="트리" align="absmiddle">';
                        }
                        $ca_name .= get_text($row['ca_name']);

                        $category_info = dmk_get_category_owner_info($row['dmk_ca_owner_type'], $row['dmk_ca_owner_id']);
                        $owner_info_display = '';
                        if ($category_info) {
                            $owner_info_display = "({$category_info['type_name']}: {$category_info['owner_name']} ({$category_info['owner_id']}))";
                        }
                ?>
                <tr class="<?php echo $bg; ?>">
                    <td class="td_chk">
                        <label for="chk_ca_id_<?php echo $i; ?>" class="sound_only"><?php echo $ca_name; ?> 분류</label>
                        <input type="checkbox" name="chk_ca_id[]" value="<?php echo $row['ca_id']; ?>" id="chk_ca_id_<?php echo $i; ?>">
                    </td>
                    <td class="td_category_id"><a href="./categoryform.php?ca_id=<?php echo $row['ca_id']; ?>"><?php echo $row['ca_id']; ?></a></td>
                    <td class="td_img"><?php echo get_it_image($row['ca_id'], 50, 50); ?></td>
                    <td class="td_category_name">
                        <div class="category_name" style="float:left;">
                            <a href="./categoryform.php?ca_id=<?php echo $row['ca_id']; ?>"><?php echo $ca_name; ?></a>
                        </div>
                    </td>
                    <td class="td_category_owner"><?php echo $owner_info_display; ?></td>
                    <td class="td_num">
                        <?php echo ($row['ca_use']) ? '<span class="txt_true">예</span>' : '<span class="txt_false">아니오</span>'; ?>
                    </td>
                    <td class="td_num">
                        <label for="ca_order_<?php echo $i; ?>" class="sound_only">정렬순서</label>
                        <input type="text" name="ca_order[<?php echo $i; ?>]" value="<?php echo $row['ca_order']; ?>" id="ca_order_<?php echo $i; ?>" class="frm_input" size="3">
                        <input type="hidden" name="ca_id[<?php echo $i; ?>]" value="<?php echo $row['ca_id']; ?>">
                    </td>
                    <td class="td_mng">
                        <a href="./categoryform.php?ca_id=<?php echo $row['ca_id']; ?>" class="btn_frm_modify">수정</a>
                        <a href="./categoryformupdate.php?w=d&amp;ca_id=<?php echo $row['ca_id']; ?>&amp;<?php echo $qstr; ?>" onclick="del(this.href); return false;" class="btn_frm_del">삭제</a>
                    </td>
                </tr>
                <?php
                    }
                }
                if ($i == 0)
                    echo '<tr><td colspan="8" class="empty_table">자료가 없습니다.</td></tr>';
                ?>
            </tbody>
        </table>
    </div>

    <div class="btn_list03 btn_list">
        <input type="submit" name="act_button" value="선택수정" onclick="document.fcategorylist.action='./categorylistupdate.php'" class="btn_submit">
    </div>

</form>

<?php
echo get_paging($config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page=');
?>

<script>
jQuery(document).ready(function($) {
    var dmkAuth = <?php echo json_encode($dmk_auth); ?>;
    var selectedDtId = '<?php echo $selected_dt_id; ?>';
    var selectedAgId = '<?php echo $selected_ag_id; ?>';
    var selectedBrId = '<?php echo $selected_br_id; ?>';

    var dmkJsConsts = <?php echo json_encode($dmk_js_consts); ?>;

    var $dtSelect = $('#dt_id');
    var $agSelect = $('#ag_id');
    var $brSelect = $('#br_id');

    function populateDropdown(targetSelect, items, selectedValue, emptyOptionText) {
        console.log('[DMK DEBUG] populateDropdown 호출:', {
            items: items,
            selectedValue: selectedValue,
            emptyOptionText: emptyOptionText
        });
        
        targetSelect.empty();
        targetSelect.append('<option value="">' + emptyOptionText + '</option>');
        
        if (Array.isArray(items)) {
            $.each(items, function(index, item) {
                console.log('[DMK DEBUG] 아이템 처리:', item);
                
                // AJAX 응답에서 받은 데이터 구조 (id, name)
                var id = item.id || item.mb_id || item.ag_id || item.br_id;
                var name = item.name || item.mb_name || item.ag_name || item.br_name;
                
                if (id && name) {
                    var selectedAttr = (selectedValue === id) ? 'selected' : '';
                    targetSelect.append('<option value="' + id + '" ' + selectedAttr + '>' + name + ' (' + id + ')</option>');
                } else {
                    console.warn('[DMK WARNING] 유효하지 않은 아이템:', item);
                }
            });
        } else {
            console.error('[DMK ERROR] items가 배열이 아닙니다:', items);
        }
        
        console.log('[DMK DEBUG] 드롭다운 업데이트 완료, 옵션 수:', targetSelect.find('option').length - 1);
    }

    // 총판 선택 변경 시
    $dtSelect.on('change', function() {
        var dt_id = $(this).val();
        console.log('[DMK DEBUG] 총판 선택 변경:', dt_id);
        
        $agSelect.empty().append('<option value="">- 대리점 선택 -</option>');
        $brSelect.empty().append('<option value="">- 지점 선택 -</option>');

        if (dt_id) {
            console.log('[DMK DEBUG] AJAX 요청 시작 - owner_type:', dmkJsConsts.DMK_OWNER_TYPE_AGENCY, 'parent_id:', dt_id);
            
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: dt_id },
                success: function(data) {
                    console.log('[DMK DEBUG] AJAX 성공 응답:', data);
                    if (data.error) {
                        console.error('[DMK ERROR] 서버 오류:', data.error);
                        alert('대리점 목록을 가져오는 중 오류가 발생했습니다: ' + data.error);
                    } else {
                        populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("[DMK ERROR] AJAX 오류:", {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    alert('대리점 목록을 가져오는 중 네트워크 오류가 발생했습니다.');
                }
            });
        }
    });

    // 대리점 선택 변경 시
    $agSelect.on('change', function() {
        var ag_id = $(this).val();
        $brSelect.empty().append('<option value="">- 지점 선택 -</option>');

        if (ag_id) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: ag_id },
                success: function(data) {
                    populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error (지점 로드): ", status, error);
                }
            });
        }
    });

    // 페이지 로드 시 초기값 설정 및 비활성화 처리
    if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_DISTRIBUTOR) {
        $dtSelect.prop('disabled', true);
        // 총판 본인이므로 대리점 목록만 로드
        if (selectedDtId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: selectedDtId },
                success: function(data) {
                    populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                    // 대리점 선택이 되어있다면 지점 목록도 로드
                    if (selectedAgId) {
                         $.ajax({
                            url: './ajax.get_dmk_owner_ids.php',
                            type: 'GET',
                            dataType: 'json',
                            data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                            success: function(data) {
                                populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                            }
                        });
                    }
                }
            });
        }
    } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_AGENCY) {
        $dtSelect.prop('disabled', true);
        $agSelect.prop('disabled', true);
        // 대리점 본인이므로 지점 목록만 로드
        if (selectedAgId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                success: function(data) {
                    populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                }
            });
        }
    } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_BRANCH) {
        $dtSelect.prop('disabled', true);
        $agSelect.prop('disabled', true);
        $brSelect.prop('disabled', true);
    } else { // Super Admin
        // 총판이 선택되어 있다면 대리점 목록 로드
        if (selectedDtId) {
            $.ajax({
                url: './ajax.get_dmk_owner_ids.php',
                type: 'GET',
                dataType: 'json',
                data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_AGENCY, parent_id: selectedDtId },
                success: function(data) {
                    populateDropdown($agSelect, data, selectedAgId, '- 대리점 선택 -');
                    // 대리점도 선택되어 있다면 지점 목록 로드
                    if (selectedAgId) {
                        $.ajax({
                            url: './ajax.get_dmk_owner_ids.php',
                            type: 'GET',
                            dataType: 'json',
                            data: { owner_type: dmkJsConsts.DMK_OWNER_TYPE_BRANCH, parent_id: selectedAgId },
                            success: function(data) {
                                populateDropdown($brSelect, data, selectedBrId, '- 지점 선택 -');
                            }
                        });
                    }
                }
            });
        }
    }
});
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');