<?php
/**
 * 스케줄링 발송 로그 목록
 * 본사, 총판 전용
 */

$sub_menu = "180610";
include_once('./_common.php');

auth_check('180610', 'r');

$g5['title'] = '스케줄링 발송 로그';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 권한 확인 - 본사, 총판만 접근 가능
$user_info = dmk_get_admin_auth($member['mb_id']);
$user_type = dmk_get_current_user_type();

if (!in_array($user_type, ['super', 'distributor'])) {
    alert('접근 권한이 없습니다.');
}

// 검색 조건
$sfl = $_GET['sfl'] ? $_GET['sfl'] : '';
$stx = $_GET['stx'];
$sdt = $_GET['sdt'] ? $_GET['sdt'] : ''; // 날짜 필터 기본값 제거
$edt = $_GET['edt'] ? $_GET['edt'] : '';

// 상태 필터
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// 메시지 타입 필터
$message_type = isset($_GET['message_type']) ? $_GET['message_type'] : '';

// 권한별 조회 조건
$where = " WHERE 1=1 ";

// 날짜 검색
if ($sdt && $edt) {
    $where .= " AND DATE(l.completed_at) BETWEEN '$sdt' AND '$edt' ";
} else {
    // 날짜 필터가 없으면 전체 데이터 표시 (기본값 제거)
    // $where .= " AND DATE(l.completed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
}

// 상태별 필터링
if ($status_filter) {
    $where .= " AND l.status = '".sql_real_escape_string($status_filter)."' ";
}

// 메시지 타입별 필터링
if ($message_type) {
    $where .= " AND s.message_type = '".sql_real_escape_string($message_type)."' ";
}

// 계층별 필터링
if ($user_type == 'distributor') {
    // 총판은 자신과 하위 대리점/지점의 로그 조회
    $ag_list = [];
    $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['dt_id']}' ";
    $result = sql_query($sql);
    while($row = sql_fetch_array($result)) {
        $ag_list[] = "'" . $row['ag_id'] . "'";
    }
    
    $br_list = [];
    if (count($ag_list) > 0) {
        $ag_in = implode(',', $ag_list);
        $sql = " SELECT br_id FROM dmk_branch WHERE ag_id IN ($ag_in) ";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $br_list[] = "'" . $row['br_id'] . "'";
        }
    }
    
    $conditions = ["(s.created_by_type = 'distributor' AND s.created_by_id = '{$user_info['dt_id']}')"];
    if (count($ag_list) > 0) {
        $conditions[] = "(s.created_by_type = 'agency' AND s.created_by_id IN (" . implode(',', $ag_list) . "))";
    }
    if (count($br_list) > 0) {
        $conditions[] = "(s.created_by_type = 'branch' AND s.created_by_id IN (" . implode(',', $br_list) . "))";
    }
    $where .= " AND (" . implode(' OR ', $conditions) . ") ";
}

// 검색
if ($stx) {
    switch ($sfl) {
        case 'schedule_title':
            $where .= " AND s.title LIKE '%$stx%' ";
            break;
        case 'room_id':
            $where .= " AND l.target_room_id LIKE '%$stx%' ";
            break;
        case 'message':
            $where .= " AND l.sent_message_text LIKE '%$stx%' ";
            break;
    }
}

// 정렬
$order_by = " ORDER BY l.id DESC ";

// 페이징
$sql_common = " FROM kb_schedule_logs l 
                LEFT JOIN kb_schedule s ON l.schedule_id = s.id 
                LEFT JOIN kb_rooms r ON l.target_room_id COLLATE utf8mb4_general_ci = r.room_id
                $where ";

$sql = " SELECT COUNT(*) as cnt $sql_common ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

// 목록 조회
$sql = " SELECT l.*, s.title as schedule_title, s.message_type, s.created_by_type, s.created_by_id,
                s.target_bot_name, s.target_device_id, r.room_name
         $sql_common
         $order_by
         LIMIT $from_record, $rows ";
$result = sql_query($sql);

$qstr = "&sfl=$sfl&stx=$stx&sdt=$sdt&edt=$edt&status_filter=$status_filter&message_type=$message_type";

// 통계 정보
$stat_sql = " SELECT 
                COUNT(*) as total_cnt,
                SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as success_cnt,
                SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) as failed_cnt,
                SUM(CASE WHEN l.status = 'partial' THEN 1 ELSE 0 END) as partial_cnt
              FROM kb_schedule_logs l 
              LEFT JOIN kb_schedule s ON l.schedule_id = s.id 
              $where ";
$stat = sql_fetch($stat_sql);
?>

<style>
.search-box {
    background: #f8f9fa;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
}
.search-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    gap: 10px;
}
.search-label {
    min-width: 80px;
    font-weight: bold;
}
.date-input {
    width: 120px;
}
.status-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
}
.status-success {
    background: #d4edda;
    color: #155724;
}
.status-failed {
    background: #f8d7da;
    color: #721c24;
}
.status-partial {
    background: #fff3cd;
    color: #856404;
}
.message-type-badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    background: #e7f3ff;
    color: #0066cc;
}
.stat-box {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.stat-item {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    flex: 1;
}
.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}
.stat-label {
    font-size: 13px;
    color: #666;
}
.components-list {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
.component-badge {
    padding: 2px 6px;
    background: #f1f3f5;
    border-radius: 3px;
    font-size: 11px;
}
</style>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" class="search-box" method="get">
    <div class="search-row">
        <span class="search-label">발송일자</span>
        <input type="date" name="sdt" value="<?php echo $sdt ?>" class="frm_input date-input">
        <span>~</span>
        <input type="date" name="edt" value="<?php echo $edt ?>" class="frm_input date-input">
    </div>
    
    <div class="search-row">
        <span class="search-label">메시지타입</span>
        <select name="message_type" class="frm_input">
            <option value="">전체</option>
            <option value="schedule" <?php echo ($message_type == 'schedule') ? 'selected' : ''; ?>>일반 스케줄</option>
            <option value="order_placed" <?php echo ($message_type == 'order_placed') ? 'selected' : ''; ?>>상품주문</option>
            <option value="order_complete" <?php echo ($message_type == 'order_complete') ? 'selected' : ''; ?>>주문완료</option>
            <option value="stock_warning" <?php echo ($message_type == 'stock_warning') ? 'selected' : ''; ?>>품절임박</option>
            <option value="stock_out" <?php echo ($message_type == 'stock_out') ? 'selected' : ''; ?>>품절</option>
        </select>
        
        <span class="search-label" style="margin-left: 20px;">발송상태</span>
        <select name="status_filter" class="frm_input">
            <option value="">전체</option>
            <option value="success" <?php echo ($status_filter == 'success') ? 'selected' : ''; ?>>성공</option>
            <option value="failed" <?php echo ($status_filter == 'failed') ? 'selected' : ''; ?>>실패</option>
            <option value="partial" <?php echo ($status_filter == 'partial') ? 'selected' : ''; ?>>부분성공</option>
        </select>
    </div>
    
    <div class="search-row">
        <span class="search-label">검색</span>
        <select name="sfl" id="sfl" class="frm_input">
            <option value="schedule_title"<?php echo get_selected($sfl, 'schedule_title'); ?>>스케줄 제목</option>
            <option value="room_id"<?php echo get_selected($sfl, 'room_id'); ?>>톡방 ID</option>
            <option value="message"<?php echo get_selected($sfl, 'message'); ?>>메시지 내용</option>
        </select>
        <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input" style="width: 300px;">
        <input type="submit" class="btn_search" value="검색">
    </div>
</form>

<div class="stat-box">
    <div class="stat-item">
        <div class="stat-number"><?php echo number_format($stat['total_cnt']); ?></div>
        <div class="stat-label">전체 발송</div>
    </div>
    <div class="stat-item" style="background-color: #f0f8ff;">
        <div class="stat-number" style="color: #155724;"><?php echo number_format($stat['success_cnt']); ?></div>
        <div class="stat-label">성공</div>
    </div>
    <div class="stat-item" style="background-color: #fff5f5;">
        <div class="stat-number" style="color: #721c24;"><?php echo number_format($stat['failed_cnt']); ?></div>
        <div class="stat-label">실패</div>
    </div>
    <div class="stat-item" style="background-color: #fffaf0;">
        <div class="stat-number" style="color: #856404;"><?php echo number_format($stat['partial_cnt']); ?></div>
        <div class="stat-label">부분성공</div>
    </div>
</div>

<form name="floglist" id="floglist" method="post">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="sdt" value="<?php echo $sdt ?>">
<input type="hidden" name="edt" value="<?php echo $edt ?>">
<input type="hidden" name="status_filter" value="<?php echo $status_filter ?>">
<input type="hidden" name="message_type" value="<?php echo $message_type ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" width="60">번호</th>
        <th scope="col" width="120">발송일시</th>
        <th scope="col">스케줄 제목</th>
        <th scope="col" width="100">메시지타입</th>
        <th scope="col" width="120">봇/디바이스</th>
        <th scope="col" width="150">채팅방</th>
        <th scope="col" width="100">발송구성</th>
        <th scope="col" width="80">상태</th>
        <th scope="col" width="100">예약시간</th>
        <th scope="col" width="80">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg' . ($i % 2);
        
        // 상태 표시
        $status_class = '';
        $status_text = '';
        switch($row['status']) {
            case 'success':
                $status_class = 'status-success';
                $status_text = '성공';
                break;
            case 'failed':
                $status_class = 'status-failed';
                $status_text = '실패';
                break;
            case 'partial':
                $status_class = 'status-partial';
                $status_text = '부분성공';
                break;
        }
        
        // 메시지 타입 표시
        $message_type_text = '';
        switch($row['message_type']) {
            case 'schedule':
                $message_type_text = '일반스케줄';
                break;
            case 'order_placed':
                $message_type_text = '상품주문';
                break;
            case 'order_complete':
                $message_type_text = '주문완료';
                break;
            case 'stock_warning':
                $message_type_text = '품절임박';
                break;
            case 'stock_out':
                $message_type_text = '품절';
                break;
            default:
                $message_type_text = $row['message_type'];
        }
        
        // 발송 구성 요소
        $components = [];
        if (!empty($row['send_components'])) {
            $comp_arr = explode(',', $row['send_components']);
            foreach ($comp_arr as $comp) {
                $comp = trim($comp);
                if ($comp == 'text') $components[] = '텍스트';
                else if ($comp == 'images_1') $components[] = '이미지1';
                else if ($comp == 'images_2') $components[] = '이미지2';
            }
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_num"><?php echo ($total_count - ($page - 1) * $rows - $i); ?></td>
        <td class="td_datetime"><?php echo $row['completed_at'] ? date('m-d H:i', strtotime($row['completed_at'])) : '-'; ?></td>
        <td class="td_left">
            <?php echo htmlspecialchars($row['schedule_title']); ?>
            <?php if ($row['error_message']): ?>
                <br><small style="color: red;">오류: <?php echo htmlspecialchars($row['error_message']); ?></small>
            <?php endif; ?>
        </td>
        <td class="td_center">
            <span class="message-type-badge"><?php echo $message_type_text; ?></span>
        </td>
        <td class="td_center" style="font-size: 11px;">
            <?php echo htmlspecialchars($row['target_bot_name']); ?><br>
            <span style="color: #666;"><?php echo substr($row['target_device_id'], 0, 8); ?>...</span>
        </td>
        <td class="td_left" style="font-size: 12px;">
            <?php echo htmlspecialchars($row['room_name'] ?: $row['target_room_id']); ?>
        </td>
        <td class="td_center">
            <div class="components-list">
                <?php foreach ($components as $comp): ?>
                    <span class="component-badge"><?php echo $comp; ?></span>
                <?php endforeach; ?>
            </div>
        </td>
        <td class="td_center">
            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
        </td>
        <td class="td_datetime" style="font-size: 11px;">
            <?php echo $row['scheduled_at'] ? date('m-d H:i', strtotime($row['scheduled_at'])) : '-'; ?>
        </td>
        <td class="td_mng td_mng_s">
            <a href="./bot_schedule_log_view.php?id=<?php echo $row['id']; ?>&<?php echo $qstr; ?>" class="btn btn_03">상세</a>
        </td>
    </tr>
    <?php
    }
    
    if ($i == 0)
        echo "<tr><td colspan='10' class='empty_table'>발송 로그가 없습니다.</td></tr>";
    ?>
    </tbody>
    </table>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>