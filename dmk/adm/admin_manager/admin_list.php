<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

// 공통 체인 선택박스 라이브러리 포함
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

$g5['title'] = '서브 관리자 관리';

// 현재 관리자 권한 확인
$dmk_auth = dmk_get_admin_auth();

// 검색 파라미터 가져오기
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : ''; // 총판 ID 검색
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : ''; // 대리점 ID 검색
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : ''; // 지점 ID 검색

// 총판 목록 조회 (페이지 로드 시 초기값 설정용)
$distributors = [];
if ($dmk_auth['is_super']) {
    $dt_sql = "SELECT d.dt_id, m.mb_nick as dt_name FROM dmk_distributor d JOIN {$g5['member_table']} m ON d.dt_id = m.mb_id WHERE d.dt_status = 1 ORDER BY m.mb_nick ASC";
    $dt_res = sql_query($dt_sql);
    while($dt_row = sql_fetch_array($dt_res)) {
        $distributors[$dt_row['dt_id']] = $dt_row['dt_name'];
    }
} elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && !empty($dmk_auth['dt_id'])) {
    $distributors[$dmk_auth['dt_id']] = $dmk_auth['dt_name'] ?? $dmk_auth['dt_id'];
}

// 대리점 및 지점 목록은 AJAX로 동적 로드 예정이므로 여기서는 초기화만
$agencies = [];
$branches = [];

// 현재 로그인한 관리자의 dt_id, ag_id, br_id 가져오기
$current_dt_id = $dmk_auth['dt_id'] ?? '';
$current_ag_id = $dmk_auth['ag_id'] ?? '';
$current_br_id = $dmk_auth['br_id'] ?? '';

// 검색 조건 설정
$sql_search = " WHERE m.mb_level >= 4 AND m.mb_level < 10 AND m.mb_id != 'admin' AND m.dmk_admin_type = 'sub' "; // 서브 관리자만 조회

// 조인할 테이블 변수 초기화
$sql_join = "";

// 검색 파라미터에 따른 SQL 조건 추가
if (!empty($sdt_id)) {
    $sql_search .= " AND m.dmk_dt_id = '".sql_escape_string($sdt_id)."' ";
}
if (!empty($sag_id)) {
    $sql_search .= " AND m.dmk_ag_id = '".sql_escape_string($sag_id)."' ";
}
if (!empty($sbr_id)) {
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

// 검색 기능 (mb_id, mb_name, mb_nick)
if ($stx) {
    $sql_search .= " AND ( ";
    $sql_search .= " m.".sql_escape_string($sfl)." LIKE '".sql_escape_string($stx)."%' ";
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

// 디버깅용 SQL 쿼리 출력
echo "<!-- DEBUG SQL: " . htmlspecialchars($sql) . " -->";
echo "<!-- DEBUG SEARCH PARAMS: sdt_id={$sdt_id}, sag_id={$sag_id}, sbr_id={$sbr_id} -->";

// URL 쿼리 스트링 생성
$qstr = 'sfl='.$sfl.'&amp;stx='.$stx.'&amp;sdt_id='.$sdt_id.'&amp;sag_id='.$sag_id.'&amp;sbr_id='.$sbr_id;

require_once '../../../adm/admin.head.php';

// 체인 선택박스 에셋 포함
echo dmk_include_chain_select_assets();

function get_agency_name($ag_id) {
    global $g5;
    $sql = "SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($ag_id)."'";
    $row = sql_fetch($sql);
    return $row ? $row['mb_nick'] : '미등록대리점';
}
function get_branch_name($br_id) {
    global $g5;
    $sql = "SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($br_id)."'";
    $row = sql_fetch($sql);
    return $row ? $row['mb_nick'] : '미등록지점';
}
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 관리자수 </span><span class="ov_num"> <?php echo number_format($total_count) ?>명 </span></span>
</div>

<form id="fsearch" name="fsearch" class="local_sch01 local_sch" method="get">

    <?php
    // 공통 체인 선택박스 렌더링 (간소화된 버전)
    echo dmk_render_chain_select([
        'page_type' => DMK_CHAIN_SELECT_FULL,
        'auto_submit' => true, // 관리자 목록에서도 자동 제출 활성화
        'debug' => true // 디버그 모드 활성화
    ]);
    ?>

    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="mb_id" <?php echo get_selected($sfl, "mb_id"); ?>>관리자아이디</option>
        <option value="mb_name" <?php echo get_selected($sfl, "mb_name"); ?>>이름</option>
        <option value="mb_nick" <?php echo get_selected($sfl, "mb_nick"); ?>>닉네임</option>
    </select>
    <label for="stx" class="sound_only">검색어</label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
    <input type="submit" class="btn_submit" value="검색">

</form>

<div class="btn_fixed_top">
    <?php
    if ($dmk_auth && $dmk_auth['admin_type'] === 'main') { // main 관리자만 등록 버튼 노출
        $can_create_sub_admin = false;
        $target_type = '';

        // 현재 관리자 계층에 따라 생성 가능한 서브 관리자 유형 결정
        if ($dmk_auth['is_super']) {
            $can_create_sub_admin = true; // 본사는 모든 서브 관리자 생성 가능
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            $can_create_sub_admin = true; // 총판은 대리점/지점 서브 관리자 생성 가능
            $target_type = 'agency'; // 기본적으로 대리점 생성으로 연결 (선택 가능하게 할 경우 로직 변경 필요)
        } elseif ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            $can_create_sub_admin = true; // 대리점은 지점 서브 관리자 생성 가능
            $target_type = 'branch'; // 지점 생성으로 연결
        }

        if ($can_create_sub_admin) {
    ?>
    <a href="admin_form.php?w=<?php echo $target_type ? '&amp;target_type=' . $target_type : ''; ?>" class="btn_01 btn">서브관리자 등록</a>
    <?php
        }
    }
    ?>
</div>

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
                <th scope="col">관리자계층</th>
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
                
                // 관리자 계층 정보
                $admin_level_name = '';
                switch ($row['dmk_mb_type']) {
                    case DMK_MB_TYPE_DISTRIBUTOR:
                        $admin_level_name = '총판 관리자';
                        break;
                    case DMK_MB_TYPE_AGENCY:
                        $admin_level_name = '대리점 관리자';
                        break;
                    case DMK_MB_TYPE_BRANCH:
                        $admin_level_name = '지점 관리자';
                        break;
                    default:
                        $admin_level_name = '미지정';
                        break;
                }
                
                // 관리자 소속 기관 정보 조회 (계층별, 누락시 미등록 표시)
                $admin_org_name = '';
                $admin_org_id = '';

                // 1. 지점 관리자
                if ($row['dmk_br_id']) {
                    $br_name = get_branch_name($row['dmk_br_id']);
                    $br_sql = "SELECT ag_id FROM dmk_branch WHERE br_id = '".sql_escape_string($row['dmk_br_id'])."'";
                    $br_row = sql_fetch($br_sql);
                    if ($br_row) {
                        $ag_name = get_agency_name($br_row['ag_id']);
                        $ag_sql = "SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($br_row['ag_id'])."'";
                        $ag_row = sql_fetch($ag_sql);
                        if ($ag_row) {
                            $dt_sql = "SELECT mb_nick as dt_name FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($ag_row['dt_id'])."'";
                            $dt_row = sql_fetch($dt_sql);
                            $admin_org_name = ($dt_row ? $dt_row['dt_name'] : '미등록총판') . ' > ' . $ag_name . ' > ' . $br_name;
                            $admin_org_id = $row['dmk_br_id'];
                        } else {
                            $admin_org_name = '미등록대리점 > ' . $br_name;
                            $admin_org_id = $row['dmk_br_id'];
                        }
                    } else {
                        $admin_org_name = '미등록지점';
                        $admin_org_id = $row['dmk_br_id'];
                    }
                }
                // 2. 대리점 관리자
                elseif ($row['dmk_ag_id']) {
                    $ag_name = get_agency_name($row['dmk_ag_id']);
                    $ag_sql = "SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($row['dmk_ag_id'])."'";
                    $ag_row = sql_fetch($ag_sql);
                    if ($ag_row) {
                        $dt_sql = "SELECT mb_nick as dt_name FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($ag_row['dt_id'])."'";
                        $dt_row = sql_fetch($dt_sql);
                        $admin_org_name = ($dt_row ? $dt_row['dt_name'] : '미등록총판') . ' > ' . $ag_name;
                        $admin_org_id = $row['dmk_ag_id'];
                    } else {
                        $admin_org_name = '미등록대리점';
                        $admin_org_id = $row['dmk_ag_id'];
                    }
                }
                // 3. 총판 관리자
                elseif ($row['dmk_dt_id']) {
                    $dt_sql = "SELECT mb_nick as dt_name FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($row['dmk_dt_id'])."'";
                    $dt_row = sql_fetch($dt_sql);
                    $admin_org_name = $dt_row ? $dt_row['dt_name'] : '미등록총판';
                    $admin_org_id = $row['dmk_dt_id'];
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
                <td class="td_left"><?php echo $admin_level_name ?></td>
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