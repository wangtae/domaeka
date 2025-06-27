<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

$g5['title'] = '서브 관리자 관리';

// 현재 관리자 권한 확인
$dmk_auth = dmk_get_admin_auth();

// 검색 파라미터 가져오기
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : ''; // 총판 ID 검색
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : ''; // 대리점 ID 검색
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : ''; // 지점 ID 검색

// 총판, 대리점, 지점 목록 조회 (현재 로그인한 관리자의 권한에 따라)
$distributors = array();
$agencies = array();
$branches = array();

// 현재 로그인한 관리자의 dt_id, ag_id, br_id 가져오기
$current_dt_id = $dmk_auth['dt_id'] ?? '';
$current_ag_id = $dmk_auth['ag_id'] ?? '';
$current_br_id = $dmk_auth['br_id'] ?? '';

// 총판 목록 조회
if ($dmk_auth['is_super']) {
    // 최고관리자: 모든 총판 조회
    $dt_sql = "SELECT dt_id, dt_name FROM dmk_distributor ORDER BY dt_name ASC";
    $dt_res = sql_query($dt_sql);
    while($dt_row = sql_fetch_array($dt_res)) {
        $distributors[$dt_row['dt_id']] = $dt_row['dt_name'];
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && !empty($current_dt_id)) {
    // 총판 관리자: 자신의 총판만
    $distributors[$current_dt_id] = $dmk_auth['dt_name'] ?? $current_dt_id;
}

// 대리점 목록 조회
if ($dmk_auth['is_super']) {
    // 최고관리자: 모든 대리점 조회 (선택된 총판에 따라 필터링)
    $ag_sql_where = '';
    if (!empty($sdt_id)) {
        $ag_sql_where = " WHERE dt_id = '".sql_escape_string($sdt_id)."' ";
    }
    $ag_sql = "SELECT ag_id, ag_name, dt_id FROM dmk_agency ". $ag_sql_where ." ORDER BY ag_name ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[$ag_row['ag_id']] = array(
            'name' => $ag_row['ag_name'],
            'dt_id' => $ag_row['dt_id']
        );
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && !empty($current_dt_id)) {
    // 총판 관리자: 산하 대리점만 조회
    $ag_sql_where = " WHERE dt_id = '".sql_escape_string($current_dt_id)."' ";
    if (!empty($sdt_id) && $sdt_id == $current_dt_id) {
        $ag_sql_where = " WHERE dt_id = '".sql_escape_string($sdt_id)."' ";
    } elseif (!empty($sdt_id) && $sdt_id != $current_dt_id) {
        $ag_sql_where = " WHERE 1=0 "; // 다른 총판 선택 시 빈 결과
    }
    $ag_sql = "SELECT ag_id, ag_name, dt_id FROM dmk_agency ". $ag_sql_where ." ORDER BY ag_name ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[$ag_row['ag_id']] = array(
            'name' => $ag_row['ag_name'],
            'dt_id' => $ag_row['dt_id']
        );
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && !empty($current_ag_id)) {
    // 대리점 관리자: 자신의 대리점만
    $agencies[$current_ag_id] = array(
        'name' => $dmk_auth['ag_name'] ?? $current_ag_id,
        'dt_id' => $current_dt_id
    );
}

// 지점 목록 조회 (전체 또는 선택된 대리점에 따라)
if ($dmk_auth['is_super']) {
    // 최고관리자: 모든 지점 조회 (선택된 대리점에 따라 필터링)
    $br_sql_where = '';
    if (!empty($sag_id)) {
        $br_sql_where = " WHERE ag_id = '".sql_escape_string($sag_id)."' ";
    } elseif (!empty($sdt_id)) {
        // 총판이 선택된 경우 해당 총판의 대리점들의 지점 조회
        $temp_ag_ids = array();
        $temp_ag_res = sql_query("SELECT ag_id FROM dmk_agency WHERE dt_id = '".sql_escape_string($sdt_id)."'");
        while($temp_ag_row = sql_fetch_array($temp_ag_res)) {
            $temp_ag_ids[] = $temp_ag_row['ag_id'];
        }
        if (!empty($temp_ag_ids)) {
            $br_sql_where = " WHERE ag_id IN ('".implode("','", array_map('sql_escape_string', $temp_ag_ids))."') ";
        } else {
            $br_sql_where = " WHERE 1=0 ";
        }
    }
    $br_sql = "SELECT br_id, br_name, ag_id FROM dmk_branch ". $br_sql_where ." ORDER BY br_name ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[$br_row['br_id']] = array(
            'name' => $br_row['br_name'],
            'ag_id' => $br_row['ag_id']
        );
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && !empty($current_dt_id)) {
    // 총판 관리자: 산하 대리점들의 지점 조회
    $br_sql_where = '';
    if (!empty($sag_id)) {
        // 대리점이 선택된 경우 해당 대리점의 지점만
        $br_sql_where = " WHERE ag_id = '".sql_escape_string($sag_id)."' ";
    } else {
        // 총판의 모든 산하 지점 조회
        $temp_ag_ids = array();
        $temp_ag_res = sql_query("SELECT ag_id FROM dmk_agency WHERE dt_id = '".sql_escape_string($current_dt_id)."'");
        while($temp_ag_row = sql_fetch_array($temp_ag_res)) {
            $temp_ag_ids[] = $temp_ag_row['ag_id'];
        }
        if (!empty($temp_ag_ids)) {
            $br_sql_where = " WHERE ag_id IN ('".implode("','", array_map('sql_escape_string', $temp_ag_ids))."') ";
        } else {
            $br_sql_where = " WHERE 1=0 ";
        }
    }
    $br_sql = "SELECT br_id, br_name, ag_id FROM dmk_branch ". $br_sql_where ." ORDER BY br_name ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[$br_row['br_id']] = array(
            'name' => $br_row['br_name'],
            'ag_id' => $br_row['ag_id']
        );
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && !empty($current_ag_id)) {
    // 대리점 관리자: 자신의 산하 지점만
    $br_sql_where = " WHERE ag_id = '".sql_escape_string($current_ag_id)."' ";
    if (!empty($sag_id) && $sag_id == $current_ag_id) {
        $br_sql_where = " WHERE ag_id = '".sql_escape_string($sag_id)."' ";
    } elseif (!empty($sag_id) && $sag_id != $current_ag_id) {
        $br_sql_where = " WHERE 1=0 "; // 다른 대리점 선택 시 빈 결과
    }
    $br_sql = "SELECT br_id, br_name, ag_id FROM dmk_branch ". $br_sql_where ." ORDER BY br_name ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[$br_row['br_id']] = array(
            'name' => $br_row['br_name'],
            'ag_id' => $br_row['ag_id']
        );
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && !empty($current_br_id)) {
    // 지점 관리자: 자신의 지점만
    $branches[$current_br_id] = array(
        'name' => $dmk_auth['br_name'] ?? $current_br_id,
        'ag_id' => $current_ag_id
    );
}

// 검색 조건 설정
$sql_search = " WHERE m.mb_level >= 4 AND m.mb_level < 10 AND m.mb_id != 'admin' AND m.dmk_admin_type = 'sub' "; // 서브 관리자만 조회

// 조인할 테이블 변수 초기화
$sql_join = "";

// 검색 파라미터에 따른 SQL 조건 추가
if (!empty($sdt_id)) {
    $sql_search .= " AND m.dmk_dt_id = '".sql_escape_string($sdt_id)."' ";
} elseif (!empty($sag_id)) {
    $sql_search .= " AND m.dmk_ag_id = '".sql_escape_string($sag_id)."' ";
} elseif (!empty($sbr_id)) {
    $sql_search .= " AND m.dmk_br_id = '".sql_escape_string($sbr_id)."' ";
}

// 최종 권한별 관리자 레벨 필터링
if (!$dmk_auth['is_super']) {
    if ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) {
        $sql_search .= " AND m.mb_level >= 4 AND m.mb_level <= 8 ";
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
        $sql_search .= " AND m.mb_level >= 4 AND m.mb_level <= 6 ";
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH) {
        $sql_search .= " AND m.mb_level = 4 ";
    }
} else {
    // 최고관리자는 모든 서브 관리자 조회
    $sql_search .= " AND m.mb_level < 10 ";
}

// 검색 기능 (mb_id, mb_name, mb_nick, mb_level)
if ($stx) {
    $sql_search .= " AND ( ";
    switch ($sfl) {
        case 'mb_level':
            $sql_search .= " m.mb_level = '".sql_escape_string($stx)."' ";
            break;
        default:
            $sql_search .= " m.".sql_escape_string($sfl)." LIKE '".sql_escape_string($stx)."%' ";
            break;
    }
    $sql_search .= " ) ";
}

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows = 20;

$sql_common = " FROM {$g5['member_table']} m ";
// $sql_join은 위의 조건문들에서 이미 추가됨 (이번에는 필요 없음)
$sql_order = " ORDER BY m.mb_level DESC, m.mb_datetime DESC ";

// 전체 개수 (DISTINCT mb_id 사용하여 중복 방지)
$sql = " SELECT COUNT(DISTINCT m.mb_id) as cnt {$sql_common} {$sql_search} "; // 조인 제거
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$total_page = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

// 목록 조회 (DISTINCT mb_id 사용하여 중복 방지 및 m.*로 모든 컬럼 선택)
$sql = " SELECT DISTINCT m.* {$sql_common} {$sql_search} {$sql_order} LIMIT {$from_record}, {$rows} "; // 조인 제거
$result = sql_query($sql);

// URL 쿼리 스트링 생성
$qstr = 'sfl='.$sfl.'&amp;stx='.$stx.'&amp;sdt_id='.$sdt_id.'&amp;sag_id='.$sag_id.'&amp;sbr_id='.$sbr_id;

require_once '../../../adm/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 관리자수 </span><span class="ov_num"> <?php echo number_format($total_count) ?>명 </span></span>
</div>

<form id="fsearch" name="fsearch" class="local_sch01 local_sch" method="get">

    <?php
    // 최고관리자 또는 총판인 경우 총판 선택박스 노출
    if ($dmk_auth['is_super'] || $dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) {
    ?>
    <label for="sdt_id" class="sound_only">총판 선택</label>
    <select name="sdt_id" id="sdt_id" onchange="updateAgencyOptionsAndSubmit();">
        <option value="">전체 총판</option>
        <?php foreach ($distributors as $dt_id => $dt_name) { ?>
            <option value="<?php echo $dt_id ?>" <?php echo ($sdt_id == $dt_id) ? 'selected' : '' ?>><?php echo $dt_name ?> (<?php echo $dt_id ?>)</option>
        <?php } ?>
    </select>
    <?php } ?>

    <?php
    // 대리점 이상의 관리자인 경우 대리점 선택박스 노출
    if ($dmk_auth['is_super'] || $dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR || $dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
    ?>
    <label for="sag_id" class="sound_only">대리점 선택</label>
    <select name="sag_id" id="sag_id" onchange="updateBranchOptionsAndSubmit();">
        <option value="">전체 대리점</option>
        <?php 
        foreach ($agencies as $ag_id => $ag_info) {
            // 총판이 선택된 경우 해당 총판의 대리점만 표시
            if (empty($sdt_id) || $ag_info['dt_id'] == $sdt_id) {
                $selected = ($sag_id == $ag_id) ? 'selected' : '';
                echo '<option value="' . $ag_id . '" data-dt-id="' . $ag_info['dt_id'] . '" ' . $selected . '>' . $ag_info['name'] . ' (' . $ag_id . ')</option>';
            }
        }
        ?>
    </select>
    <?php } ?>

    <?php
    // 대리점 관리자 이상인 경우 지점 선택박스 노출 (지점 관리자는 선택박스 불필요)
    if ($dmk_auth['is_super'] || $dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR || $dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
    ?>
    <label for="sbr_id" class="sound_only">지점 선택</label>
    <select name="sbr_id" id="sbr_id" onchange="this.form.submit();">
        <option value="">전체 지점</option>
        <?php 
        foreach ($branches as $br_id => $br_info) {
            // 대리점이 선택된 경우 해당 대리점의 지점만 표시
            if (empty($sag_id) || $br_info['ag_id'] == $sag_id) {
                $selected = ($sbr_id == $br_id) ? 'selected' : '';
                echo '<option value="' . $br_id . '" data-ag-id="' . $br_info['ag_id'] . '" ' . $selected . '>' . $br_info['name'] . ' (' . $br_id . ')</option>';
            }
        }
        ?>
    </select>
    <?php } ?>

    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="mb_id" <?php echo get_selected($sfl, "mb_id"); ?>>관리자아이디</option>
        <option value="mb_name" <?php echo get_selected($sfl, "mb_name"); ?>>이름</option>
        <option value="mb_nick" <?php echo get_selected($sfl, "mb_nick"); ?>>닉네임</option>
        <option value="mb_level" <?php echo get_selected($sfl, "mb_level"); ?>>권한레벨</option>
    </select>
    <label for="stx" class="sound_only">검색어</label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
    <input type="submit" class="btn_submit" value="검색">

</form>

<script>
// 총판 선택 시 대리점 선택박스 업데이트
function updateAgencyOptions() {
    var sdtId = document.getElementById('sdt_id') ? document.getElementById('sdt_id').value : '';
    var sagSelect = document.getElementById('sag_id');
    
    if (!sagSelect) return; // 대리점 선택박스가 없는 경우
    
    // 모든 대리점 옵션을 숨기고 다시 표시
    var options = sagSelect.querySelectorAll('option');
    
    // 첫 번째 옵션(전체 대리점)은 항상 표시
    for (var i = 1; i < options.length; i++) {
        var option = options[i];
        var optionDtId = option.getAttribute('data-dt-id');
        
        if (sdtId === '' || optionDtId === sdtId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
    // 현재 선택된 대리점이 숨겨진 경우 초기화
    var currentSelected = sagSelect.value;
    var currentOption = sagSelect.querySelector('option[value="' + currentSelected + '"]');
    if (currentOption && currentOption.style.display === 'none') {
        sagSelect.value = '';
        updateBranchOptions(); // 지점도 초기화
    }
}

// 대리점 선택 시 지점 선택박스 업데이트
function updateBranchOptions() {
    var sagId = document.getElementById('sag_id') ? document.getElementById('sag_id').value : '';
    var sbrSelect = document.getElementById('sbr_id');
    
    if (!sbrSelect) return; // 지점 선택박스가 없는 경우 (지점 관리자)
    
    // 모든 지점 옵션을 숨기고 다시 표시
    var options = sbrSelect.querySelectorAll('option');
    
    // 첫 번째 옵션(전체 지점)은 항상 표시
    for (var i = 1; i < options.length; i++) {
        var option = options[i];
        var optionAgId = option.getAttribute('data-ag-id');
        
        if (sagId === '' || optionAgId === sagId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
    // 현재 선택된 지점이 숨겨진 경우 초기화
    var currentSelected = sbrSelect.value;
    var currentOption = sbrSelect.querySelector('option[value="' + currentSelected + '"]');
    if (currentOption && currentOption.style.display === 'none') {
        sbrSelect.value = '';
    }
}

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    updateAgencyOptions();
    updateBranchOptions();
});

function updateAgencyOptionsAndSubmit() {
    updateAgencyOptions();
    updateBranchOptions();
    document.getElementById('fsearch').submit();
}

function updateBranchOptionsAndSubmit() {
    updateBranchOptions();
    document.getElementById('fsearch').submit();
}
</script>

<div class="local_desc01 local_desc">
    <p>
        관리자 계정의 권한 관리 및 정보 수정을 할 수 있습니다. 
        <?php if (!$dmk_auth['is_super']) { ?>
        현재 로그인한 관리자보다 상위 레벨의 관리자는 수정할 수 없습니다.
        <?php } ?>
    </p>
</div>

<form name="fadminlist" id="fadminlist" method="post">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="sdt_id" value="<?php echo $sdt_id ?>">
    <input type="hidden" name="sag_id" value="<?php echo $sag_id ?>">
    <input type="hidden" name="sbr_id" value="<?php echo $sbr_id ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">

    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?> 목록</caption>
            <thead>
            <tr>
                <th scope="col">번호</th>
                <th scope="col">관리자ID</th>
                <th scope="col">이름</th>
                <th scope="col">닉네임</th>
                <th scope="col">권한레벨</th>
                <th scope="col">소속기관</th>
                <th scope="col">가입일</th>
                <th scope="col">최종접속</th>
                <th scope="col">관리</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $i = 0;
            while ($row = sql_fetch_array($result)) {
                $i++;
                $num = $total_count - ($page - 1) * $rows - $i + 1;
                
                // 관리자 소속 정보 조회
                $admin_org_name = '';
                $admin_org_id = '';
                
                if ($row['dmk_dt_id']) {
                    $org_sql = "SELECT dt_name FROM dmk_distributor WHERE dt_id = '".sql_escape_string($row['dmk_dt_id'])."'";
                    $org_row = sql_fetch($org_sql);
                    if ($org_row) {
                        $admin_org_name = '총판: ' . $org_row['dt_name'];
                        $admin_org_id = $row['dmk_dt_id'];
                    }
                } elseif ($row['dmk_ag_id']) {
                    $org_sql = "SELECT ag_name FROM dmk_agency WHERE ag_id = '".sql_escape_string($row['dmk_ag_id'])."'";
                    $org_row = sql_fetch($org_sql);
                    if ($org_row) {
                        $admin_org_name = '대리점: ' . $org_row['ag_name'];
                        $admin_org_id = $row['dmk_ag_id'];
                    }
                } elseif ($row['dmk_br_id']) {
                    $org_sql = "SELECT br_name FROM dmk_branch WHERE br_id = '".sql_escape_string($row['dmk_br_id'])."'";
                    $org_row = sql_fetch($org_sql);
                    if ($org_row) {
                        $admin_org_name = '지점: ' . $org_row['br_name'];
                        $admin_org_id = $row['dmk_br_id'];
                    }
                } else {
                    $admin_org_name = '미분류';
                }
                
                $admin_org_display = $admin_org_name;
                if (!empty($admin_org_id)) {
                    $admin_org_display .= ' (' . $admin_org_id . ')';
                }
                
                $mb_nick = get_sideview($row['mb_id'], $row['mb_nick'], $row['mb_email'], $row['mb_homepage']);
                
                // 수정 권한 체크
                $can_modify = true;
                if (!$dmk_auth['is_super'] && $row['mb_level'] > $dmk_auth['mb_level']) {
                    $can_modify = false;
                }
            ?>
            <tr>
                <td class="td_num"><?php echo $num ?></td>
                <td class="td_left"><?php echo $row['mb_id'] ?></td>
                <td class="td_left"><?php echo $row['mb_name'] ?></td>
                <td class="td_left"><?php echo $mb_nick ?></td>
                <td class="td_num"><?php echo $row['mb_level'] ?></td>
                <td class="td_left"><?php echo $admin_org_display ?></td>
                <td class="td_datetime"><?php echo substr($row['mb_datetime'], 0, 10) ?></td>
                <td class="td_datetime"><?php echo $row['mb_today_login'] ? substr($row['mb_today_login'], 0, 10) : '-' ?></td>
                <td class="td_mng">
                    <?php if ($can_modify) { ?>
                    <a href="admin_form.php?w=u&amp;mb_id=<?php echo $row['mb_id'] ?>&amp;<?php echo $qstr ?>&amp;page=<?php echo $page ?>" class="btn btn_03">수정</a>
                    <?php } else { ?>
                    <span class="btn btn_02">수정불가</span>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            
            <?php if ($i == 0) { ?>
            <tr>
                <td colspan="9" class="empty_table">자료가 없습니다.</td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

</form>

<?php
$pagelist = get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page=');
echo $pagelist;

require_once '../../../adm/admin.tail.php';
?> 