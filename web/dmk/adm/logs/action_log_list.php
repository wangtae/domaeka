<?php


$sub_menu = "190900"; // 로그 관리 메뉴 코드
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
include_once(G5_PATH.'/dmk/adm/lib/chain-select.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

// 관리자 액션 로그는 본사와 총판만 접근 가능
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth || (!$dmk_auth['is_super'] && $dmk_auth['mb_type'] != 1)) {
    // is_super = true (본사) 또는 mb_type = 1 (총판)만 접근 가능
    alert('관리자 액션 로그는 본사와 총판만 접근할 수 있습니다.');
}

$g5['title'] = '관리자 액션 로그 <i class="fa fa-list-alt dmk-updated-icon" title="새로추가"></i>';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// SQL common 조인
$sql_common = " FROM dmk_action_logs l
                LEFT JOIN {$g5['member_table']} m ON l.mb_id = m.mb_id ";
$sql_search = " WHERE 1=1 ";

// 권한에 따른 데이터 필터링
// $dmk_auth는 이미 위에서 정의됨

// 검색 조건
$action_type = isset($_GET['action_type']) ? sql_escape_string(trim($_GET['action_type'])) : '';
$mb_id = isset($_GET['mb_id']) ? sql_escape_string(trim($_GET['mb_id'])) : '';
$start_date = isset($_GET['start_date']) ? sql_escape_string(trim($_GET['start_date'])) : '';
$end_date = isset($_GET['end_date']) ? sql_escape_string(trim($_GET['end_date'])) : '';
$sdt_id = isset($_GET['sdt_id']) ? sql_escape_string(trim($_GET['sdt_id'])) : '';

if ($action_type) {
    $sql_search .= " AND l.action_type = '".sql_escape_string($action_type)."' ";
}

if ($mb_id) {
    $sql_search .= " AND l.mb_id = '".sql_escape_string($mb_id)."' ";
}

if ($start_date) {
    $sql_search .= " AND DATE(l.log_datetime) >= '".sql_escape_string($start_date)."' ";
}

if ($end_date) {
    $sql_search .= " AND DATE(l.log_datetime) <= '".sql_escape_string($end_date)."' ";
}

// 총판 필터링 - 총판 로그인시 자신의 데이터만 표시
if ($dmk_auth['mb_type'] == 1) { // 총판 (mb_type = 1)
    // 총판은 자신의 액션만 볼 수 있음
    $sql_search .= " AND l.mb_id = '".sql_escape_string($dmk_auth['mb_id'])."' ";
} else if ($sdt_id) {
    // 본사에서 특정 총판 선택시
    $sql_search .= " AND l.mb_id IN (
        SELECT mb_id FROM {$g5['member_table']} 
        WHERE dmk_mb_type = 1 AND dmk_dt_id = '".sql_escape_string($sdt_id)."'
    ) ";
}

if ($stx) {
    $sql_search .= " AND (
                        l.mb_id LIKE '%".sql_escape_string($stx)."%' OR
                        l.action_detail LIKE '%".sql_escape_string($stx)."%' OR
                        l.target_table LIKE '%".sql_escape_string($stx)."%' OR
                        l.target_id LIKE '%".sql_escape_string($stx)."%'
                    ) ";
}

if (!$sst) {
    $sst = "l.log_datetime";
    $sod = "desc";
}

$sql_order = " ORDER BY $sst $sod ";

$sql = " SELECT COUNT(*) as cnt " . $sql_common . $sql_search;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

// SELECT 문
$sql = " SELECT l.*, m.mb_name, m.mb_nick
          " . $sql_common . $sql_search . $sql_order . " LIMIT $from_record, $rows ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

// 액션 타입 목록
$action_types = array(
    '' => '전체',
    'CREATE' => '생성',
    'UPDATE' => '수정', 
    'DELETE' => '삭제',
    'LOGIN' => '로그인',
    'LOGOUT' => '로그아웃'
);
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
    <div class="sch_last">
    <?php 
        // 본사만 총판 선택박스 표시
        if ($dmk_auth['is_super']) {
            echo dmk_chain_select_for_list([
                'sdt_id' => $sdt_id
            ], DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY, [
                'auto_submit' => true,
                'form_id' => 'fsearch'
            ]);
        }
        ?>

        
        <select name="action_type" class="frm_input">
            <?php foreach($action_types as $val => $name) { ?>
            <option value="<?php echo $val ?>" <?php echo ($action_type == $val) ? 'selected' : '' ?>><?php echo $name ?></option>
            <?php } ?>
        </select>
        
        
        
        <input type="text" name="mb_id" value="<?php echo $mb_id ?>" placeholder="관리자ID" class="frm_input" style="width:120px;">
        
        <input type="date" name="start_date" value="<?php echo $start_date ?>" class="frm_input" style="width:140px;">
        ~
        <input type="date" name="end_date" value="<?php echo $end_date ?>" class="frm_input" style="width:140px;">
        
        <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
        <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" placeholder="관리자ID, 액션상세, 테이블명, 대상ID">
        <input type="submit" class="btn_submit" value="검색">
    </div>
</form>

<div class="local_desc01 local_desc">
    <p>
        <strong>관리자 액션 로그</strong><br>
        • 관리자의 모든 중요한 액션(생성, 수정, 삭제, 로그인 등)이 기록됩니다.<br>
        • 변경 전후 데이터와 상세 정보를 확인할 수 있습니다.
    </p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" style="width:80px;"><?php echo subject_sort_link('l.id') ?>번호</a></th>
        <th scope="col" style="width:100px;"><?php echo subject_sort_link('l.mb_id') ?>관리자ID</a></th>
        <th scope="col" style="width:80px;">관리자명</th>
        <th scope="col" style="width:80px;"><?php echo subject_sort_link('l.action_type') ?>액션타입</a></th>
        <th scope="col" style="width:80px;"><?php echo subject_sort_link('l.menu_code') ?>메뉴코드</a></th>
        <th scope="col" style="width:100px;"><?php echo subject_sort_link('l.target_table') ?>대상테이블</a></th>
        <th scope="col" style="width:100px;"><?php echo subject_sort_link('l.target_id') ?>대상ID</a></th>
        <th scope="col">액션상세</th>
        <th scope="col" style="width:100px;"><?php echo subject_sort_link('l.action_ip') ?>IP주소</a></th>
        <th scope="col" style="width:140px;"><?php echo subject_sort_link('l.log_datetime') ?>실행시간</a></th>
        <th scope="col" style="width:80px;">상세보기</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 액션 타입별 스타일 지정
        $action_class = '';
        switch($row['action_type']) {
            case 'CREATE': $action_class = 'txt_true'; break;
            case 'UPDATE': $action_class = 'txt_blue'; break;
            case 'DELETE': $action_class = 'txt_false'; break;
            case 'LOGIN': $action_class = 'txt_green'; break;
            case 'LOGOUT': $action_class = 'txt_gray'; break;
        }
    ?>

    <tr class="<?php echo $bg; ?>">
        <td class="td_num"><?php echo $row['id'] ?></td>
        <td class="td_left">
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>" target="_blank">
                <?php echo $row['mb_id'] ?>
            </a>
        </td>
        <td><?php echo get_text($row['mb_name'] ?: $row['mb_nick']) ?></td>
        <td class="<?php echo $action_class ?>"><?php echo $row['action_type'] ?></td>
        <td class="td_num"><?php echo $row['menu_code'] ?></td>
        <td class="td_left"><?php echo $row['target_table'] ?></td>
        <td class="td_left"><?php 
            // g5_로 시작하는 테이블명에서 접두사 제거
            $target_id = $row['target_id'];
            if (strpos($target_id, 'g5_') === 0) {
                $target_id = substr($target_id, 3);
            } 
            echo $target_id;
        ?></td>
        <td class="td_left">
            <?php 
            $detail = get_text($row['action_detail']);
            echo mb_strlen($detail) > 50 ? mb_substr($detail, 0, 50) . '...' : $detail;
            ?>
        </td>
        <td class="td_num"><?php echo $row['action_ip'] ?></td>
        <td class="td_datetime"><?php echo $row['log_datetime'] ?></td>
        <td class="td_mng td_mng_s">
            <a href="./action_log_view.php?id=<?php echo $row['id'] ?>" class="btn btn_03">상세</a>
        </td>
    </tr>

    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="11" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<script>
// 오늘 날짜 설정
function setToday() {
    var today = new Date().toISOString().split('T')[0];
    document.fsearch.start_date.value = today;
    document.fsearch.end_date.value = today;
}

// 최근 7일 설정
function setWeek() {
    var today = new Date();
    var weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    document.fsearch.start_date.value = weekAgo.toISOString().split('T')[0];
    document.fsearch.end_date.value = today.toISOString().split('T')[0];
}
</script>

<div class="btn_fixed_top">
    <a href="javascript:setToday()" class="btn_01 btn">오늘</a>
    <a href="javascript:setWeek()" class="btn_01 btn">최근 7일</a>
</div>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>