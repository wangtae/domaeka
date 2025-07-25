<?php
/**
 * 스케줄링 발송 관리 목록
 */

$sub_menu = "180600";
include_once('./_common.php');

auth_check('180600', 'r');

$g5['title'] = '스케줄링 발송 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 이미지 파일 크기 가져오기 함수
function get_file_size($filename) {
    $filepath = G5_DATA_PATH.'/schedule/'.$filename;
    if (file_exists($filepath)) {
        return filesize($filepath);
    }
    return 0;
}

// 바이트를 읽기 쉬운 형식으로 변환
function format_bytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// 검색 조건
$sfl = $_GET['sfl'] ? $_GET['sfl'] : 'title';
$stx = $_GET['stx'];

// 메시지 타입 필터
$message_type = isset($_GET['message_type']) ? $_GET['message_type'] : '';

// 유효기간 필터 (valid, pending, expired) - 기본값은 valid
$validity_filter = isset($_GET['validity_filter']) ? $_GET['validity_filter'] : 'valid';

// 상태 필터 (active, inactive, completed, error) - 기본값은 active
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'active';

// 권한별 조회 조건
$where = " WHERE 1=1 ";

// 메시지 타입별 필터링
if ($message_type) {
    $where .= " AND message_type = '".sql_real_escape_string($message_type)."' ";
} else {
    // 기본 탭에서는 일반 스케줄만 표시
    $where .= " AND (message_type = 'schedule' OR message_type IS NULL) ";
}

// 유효기간별 필터링
$today = date('Y-m-d H:i:s');
if ($validity_filter == 'valid') {
    // 유효 스케줄: 현재 유효기간 내
    $where .= " AND valid_from <= '$today' AND valid_until >= '$today' ";
} else if ($validity_filter == 'pending') {
    // 대기 스케줄: 아직 시작 안함
    $where .= " AND valid_from > '$today' ";
} else if ($validity_filter == 'expired') {
    // 만료 스케줄: 유효기간 지남
    $where .= " AND valid_until < '$today' ";
}

// 상태별 필터링
if ($status_filter) {
    $where .= " AND status = '".sql_real_escape_string($status_filter)."' ";
}

// 각 메시지 타입별 활성 스케줄 수 계산을 위한 기본 where 조건 구성
$base_where = " WHERE 1=1 ";
$base_where .= " AND status = 'active' ";
$base_where .= " AND valid_from <= '$today' AND valid_until >= '$today' ";

$user_info = dmk_get_admin_auth($member['mb_id']);

// type이 없으면 admin_type과 is_super를 확인하여 설정
if ($user_info['is_super']) {
    $user_type = 'super';
} else if ($user_info['admin_type'] == 'distributor') {
    $user_type = 'distributor';
} else if ($user_info['admin_type'] == 'agency') {
    $user_type = 'agency';
} else if ($user_info['admin_type'] == 'branch') {
    $user_type = 'branch';
} else {
    $user_type = 'super'; // 기본값
}

// key 설정
if ($user_type == 'distributor') {
    $user_key = $user_info['dt_id'];
} else if ($user_type == 'agency') {
    $user_key = $user_info['ag_id'];
} else if ($user_type == 'branch') {
    $user_key = $user_info['br_id'];
} else {
    $user_key = $user_info['mb_id'];
}


// 계층별 필터링
if ($user_type != 'super') {
    if ($user_type == 'distributor') {
        // 총판은 자신과 하위 대리점/지점의 스케줄 조회
        $ag_list = [];
        $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '$user_key' ";
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
        
        $conditions = ["(created_by_type = 'distributor' AND created_by_id = '$user_key')"];
        if (count($ag_list) > 0) {
            $conditions[] = "(created_by_type = 'agency' AND created_by_id IN (" . implode(',', $ag_list) . "))";
        }
        if (count($br_list) > 0) {
            $conditions[] = "(created_by_type = 'branch' AND created_by_id IN (" . implode(',', $br_list) . "))";
        }
        // created_by_type이 빈 문자열인 경우도 포함 (이전 데이터 호환성)
        $conditions[] = "(created_by_type = '' OR created_by_type IS NULL)";
        $where .= " AND (" . implode(' OR ', $conditions) . ") ";
    } else if ($user_type == 'agency') {
        // 대리점은 자신과 하위 지점의 스케줄 조회
        $br_list = [];
        $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '$user_key' ";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $br_list[] = "'" . $row['br_id'] . "'";
        }
        
        $conditions = ["(created_by_type = 'agency' AND created_by_id = '$user_key')"];
        if (count($br_list) > 0) {
            $conditions[] = "(created_by_type = 'branch' AND created_by_id IN (" . implode(',', $br_list) . "))";
        }
        // created_by_type이 빈 문자열인 경우도 포함 (이전 데이터 호환성)
        $conditions[] = "(created_by_type = '' OR created_by_type IS NULL)";
        $where .= " AND (" . implode(' OR ', $conditions) . ") ";
    } else if ($user_type == 'branch') {
        // 지점은 자신의 스케줄만 조회
        $where .= " AND (created_by_type = 'branch' AND created_by_id = '$user_key' OR created_by_type = '' OR created_by_type IS NULL) ";
    }
}

// 권한별 필터링 조건을 저장 (활성 스케줄 수 계산에 재사용)
$auth_where = "";
if ($user_type != 'super') {
    if ($user_type == 'distributor') {
        // 총판 권한 조건
        $ag_list = [];
        $sql = " SELECT ag_id FROM dmk_agency WHERE dt_id = '$user_key' ";
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
        
        $conditions = ["(created_by_type = 'distributor' AND created_by_id = '$user_key')"];
        if (count($ag_list) > 0) {
            $conditions[] = "(created_by_type = 'agency' AND created_by_id IN (" . implode(',', $ag_list) . "))";
        }
        if (count($br_list) > 0) {
            $conditions[] = "(created_by_type = 'branch' AND created_by_id IN (" . implode(',', $br_list) . "))";
        }
        $conditions[] = "(created_by_type = '' OR created_by_type IS NULL)";
        $auth_where = " AND (" . implode(' OR ', $conditions) . ") ";
    } else if ($user_type == 'agency') {
        // 대리점 권한 조건
        $br_list = [];
        $sql = " SELECT br_id FROM dmk_branch WHERE ag_id = '$user_key' ";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $br_list[] = "'" . $row['br_id'] . "'";
        }
        
        $conditions = ["(created_by_type = 'agency' AND created_by_id = '$user_key')"];
        if (count($br_list) > 0) {
            $conditions[] = "(created_by_type = 'branch' AND created_by_id IN (" . implode(',', $br_list) . "))";
        }
        $conditions[] = "(created_by_type = '' OR created_by_type IS NULL)";
        $auth_where = " AND (" . implode(' OR ', $conditions) . ") ";
    } else if ($user_type == 'branch') {
        // 지점 권한 조건
        $auth_where = " AND (created_by_type = 'branch' AND created_by_id = '$user_key' OR created_by_type = '' OR created_by_type IS NULL) ";
    }
}

// 각 메시지 타입별 활성 스케줄 수 계산
$message_types = [
    '' => '일반 스케줄',
    'order_placed' => '상품주문',
    'order_complete' => '주문완료',
    'stock_warning' => '품절임박',
    'stock_out' => '품절'
];

$active_counts = [];
foreach ($message_types as $type => $name) {
    $count_where = $base_where . $auth_where;
    if ($type) {
        $count_where .= " AND message_type = '".sql_real_escape_string($type)."' ";
    } else {
        $count_where .= " AND (message_type = 'schedule' OR message_type IS NULL) ";
    }
    
    $count_sql = "SELECT COUNT(*) as cnt FROM kb_schedule $count_where";
    $count_row = sql_fetch($count_sql);
    $active_counts[$type] = $count_row['cnt'];
}

// 현재 메시지 타입에 대한 유효기간별/상태별 카운트 계산
$validity_counts = [];
$status_counts = [];

// 유효기간별 카운트
$validity_types = [
    'valid' => '유효 스케줄',
    'pending' => '대기 스케줄', 
    'expired' => '만료 스케줄'
];

foreach ($validity_types as $v_type => $v_name) {
    $count_where = " WHERE 1=1 " . $auth_where;
    
    // 메시지 타입 필터
    if ($message_type) {
        $count_where .= " AND message_type = '".sql_real_escape_string($message_type)."' ";
    } else {
        $count_where .= " AND (message_type = 'schedule' OR message_type IS NULL) ";
    }
    
    // 유효기간 필터
    if ($v_type == 'valid') {
        $count_where .= " AND valid_from <= '$today' AND valid_until >= '$today' ";
    } else if ($v_type == 'pending') {
        $count_where .= " AND valid_from > '$today' ";
    } else if ($v_type == 'expired') {
        $count_where .= " AND valid_until < '$today' ";
    }
    
    $count_sql = "SELECT COUNT(*) as cnt FROM kb_schedule $count_where";
    $count_row = sql_fetch($count_sql);
    $validity_counts[$v_type] = $count_row['cnt'];
}

// 현재 유효기간 필터에 대한 상태별 카운트
$status_types = [
    'active' => '활성',
    'inactive' => '비활성',
    'completed' => '완료',
    'error' => '에러'
];

foreach ($status_types as $s_type => $s_name) {
    $count_where = " WHERE 1=1 " . $auth_where;
    
    // 메시지 타입 필터
    if ($message_type) {
        $count_where .= " AND message_type = '".sql_real_escape_string($message_type)."' ";
    } else {
        $count_where .= " AND (message_type = 'schedule' OR message_type IS NULL) ";
    }
    
    // 유효기간 필터 (현재 선택된 유효기간 필터 적용)
    if ($validity_filter == 'valid') {
        $count_where .= " AND valid_from <= '$today' AND valid_until >= '$today' ";
    } else if ($validity_filter == 'pending') {
        $count_where .= " AND valid_from > '$today' ";
    } else if ($validity_filter == 'expired') {
        $count_where .= " AND valid_until < '$today' ";
    }
    
    // 상태 필터
    $count_where .= " AND status = '".sql_real_escape_string($s_type)."' ";
    
    $count_sql = "SELECT COUNT(*) as cnt FROM kb_schedule $count_where";
    $count_row = sql_fetch($count_sql);
    $status_counts[$s_type] = $count_row['cnt'];
}

// 검색
if ($stx) {
    switch ($sfl) {
        case 'title':
            $where .= " AND title LIKE '%$stx%' ";
            break;
        case 'target_room_id':
            $where .= " AND target_room_id LIKE '%$stx%' ";
            break;
        case 'message_text':
            $where .= " AND message_text LIKE '%$stx%' ";
            break;
    }
}

// 정렬
$order_by = " ORDER BY id DESC ";

// 페이징
$sql_common = " FROM kb_schedule $where ";

$sql = " SELECT COUNT(*) as cnt $sql_common ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

// 목록 조회
$sql = " SELECT DISTINCT s.*, r.room_name
         FROM kb_schedule s
         LEFT JOIN kb_rooms r ON (s.target_room_id = r.room_id 
                                  AND s.target_bot_name = r.bot_name 
                                  AND s.target_device_id = r.device_id)
         $where
         $order_by
         LIMIT $from_record, $rows ";

// 목록 조회 - 썸네일 데이터와 저장 방식 포함
$sql = " SELECT 
            s.id, s.title, s.description, s.message_type, s.reference_type, s.reference_id,
            s.created_by_type, s.created_by_id, s.created_by_mb_id,
            s.target_bot_name, s.target_device_id, s.target_room_id,
            s.message_text,
            s.send_interval_seconds, s.media_wait_time_1, s.media_wait_time_2,
            s.schedule_type, s.schedule_date, s.schedule_time, s.schedule_times, s.schedule_weekdays,
            s.valid_from, s.valid_until, s.status,
            s.last_sent_at, s.next_send_at, s.send_count,
            s.created_at, s.updated_at,
            -- 썸네일 데이터만 포함
            s.message_thumbnails_1, s.message_thumbnails_2,
            -- 이미지 존재 여부만 확인
            CASE WHEN s.message_images_1 IS NOT NULL AND s.message_images_1 != '[]' AND s.message_images_1 != '' THEN 1 ELSE 0 END as has_images_1,
            CASE WHEN s.message_images_2 IS NOT NULL AND s.message_images_2 != '[]' AND s.message_images_2 != '' THEN 1 ELSE 0 END as has_images_2,
            -- 저장 방식 추가
            s.image_storage_mode,
            -- Base64 이미지인 경우 첫 번째 이미지만 가져오기 (썸네일용)
            CASE WHEN s.image_storage_mode = 'base64' AND s.message_images_1 != '[]' AND s.message_images_1 != '' 
                 THEN s.message_images_1 
                 ELSE NULL END as base64_images_1,
            CASE WHEN s.image_storage_mode = 'base64' AND s.message_images_2 != '[]' AND s.message_images_2 != '' 
                 THEN s.message_images_2 
                 ELSE NULL END as base64_images_2
         FROM kb_schedule s 
         $where 
         $order_by 
         LIMIT $from_record, $rows ";
$result = sql_query($sql);

$qstr = "&sfl=$sfl&stx=$stx&message_type=$message_type&validity_filter=$validity_filter&status_filter=$status_filter";
?>

<style>
.schedule-tabs {
    display: flex;
    margin-bottom: 10px;
    border-bottom: 2px solid #ddd;
}
.schedule-tab {
    padding: 10px 20px;
    margin-bottom: -2px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-bottom: 2px solid #ddd;
    text-decoration: none;
    color: #333;
    font-weight: bold;
    margin-right: 5px;
    cursor: pointer;
}
.schedule-tab:hover {
    background: #e5e5e5;
}
.schedule-tab.active {
    background: #fff;
    border-bottom: 2px solid #fff;
    color: #000;
}
.schedule-tab .count {
    color: #666;
    font-weight: normal;
}
.schedule-tab.active .count {
    color: #000;
    font-weight: bold;
}
.validity-filters {
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.validity-filter {
    display: inline-block;
    padding: 8px 20px;
    margin-right: 10px;
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 25px;
    text-decoration: none;
    color: #333;
    font-size: 15px;
    font-weight: 500;
}
.validity-filter:hover {
    background: #e9ecef;
}
.validity-filter.active {
    background: #435ffe;
    color: #fff;
    border-color: #435ffe;
}
.validity-filter .count {
    color: #666;
    font-weight: normal;
    font-size: 14px;
}
.validity-filter.active .count {
    color: #fff;
}
.status-filters {
    margin-bottom: 20px;
    padding: 10px;
    background: #e8f0fe;
    border: 1px solid #c6d7f4;
    border-radius: 4px;
}
.status-filter {
    display: inline-block;
    padding: 5px 15px;
    margin-right: 8px;
    margin-bottom: 5px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 18px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
}
.status-filter:hover {
    background: #f0f0f0;
}
.status-filter.active {
    background: #1a73e8;
    color: #fff;
    border-color: #1a73e8;
}
.status-filter .count {
    color: #666;
    font-weight: normal;
    font-size: 12px;
}
.status-filter.active .count {
    color: #fff;
}
</style>

<div class="schedule-tabs">
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => '', 'page' => 1])); ?>" 
       class="schedule-tab <?php echo (!$message_type) ? 'active' : ''; ?>">
       일반 스케줄 <span class="count">(<?php echo number_format($active_counts['']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'order_placed', 'page' => 1])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'order_placed') ? 'active' : ''; ?>">
       상품주문 <span class="count">(<?php echo number_format($active_counts['order_placed']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'order_complete', 'page' => 1])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'order_complete') ? 'active' : ''; ?>">
       주문완료 <span class="count">(<?php echo number_format($active_counts['order_complete']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'stock_warning', 'page' => 1])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'stock_warning') ? 'active' : ''; ?>">
       품절임박 <span class="count">(<?php echo number_format($active_counts['stock_warning']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'stock_out', 'page' => 1])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'stock_out') ? 'active' : ''; ?>">
       품절 <span class="count">(<?php echo number_format($active_counts['stock_out']); ?>)</span>
    </a>
</div>

<div class="validity-filters">
    <a href="?<?php echo http_build_query(array_merge($_GET, ['validity_filter' => 'valid', 'status_filter' => '', 'page' => 1])); ?>" 
       class="validity-filter <?php echo ($validity_filter == 'valid') ? 'active' : ''; ?>">
       유효 스케줄 <span class="count">(<?php echo number_format($validity_counts['valid']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['validity_filter' => 'pending', 'status_filter' => '', 'page' => 1])); ?>" 
       class="validity-filter <?php echo ($validity_filter == 'pending') ? 'active' : ''; ?>">
       대기 스케줄 <span class="count">(<?php echo number_format($validity_counts['pending']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['validity_filter' => 'expired', 'status_filter' => '', 'page' => 1])); ?>" 
       class="validity-filter <?php echo ($validity_filter == 'expired') ? 'active' : ''; ?>">
       만료 스케줄 <span class="count">(<?php echo number_format($validity_counts['expired']); ?>)</span>
    </a>
</div>

<div class="status-filters">
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => '', 'page' => 1])); ?>" 
       class="status-filter <?php echo (!$status_filter) ? 'active' : ''; ?>">
       전체
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => 'active', 'page' => 1])); ?>" 
       class="status-filter <?php echo ($status_filter == 'active') ? 'active' : ''; ?>">
       활성 <span class="count">(<?php echo number_format($status_counts['active']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => 'inactive', 'page' => 1])); ?>" 
       class="status-filter <?php echo ($status_filter == 'inactive') ? 'active' : ''; ?>">
       비활성 <span class="count">(<?php echo number_format($status_counts['inactive']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => 'completed', 'page' => 1])); ?>" 
       class="status-filter <?php echo ($status_filter == 'completed') ? 'active' : ''; ?>">
       완료 <span class="count">(<?php echo number_format($status_counts['completed']); ?>)</span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => 'error', 'page' => 1])); ?>" 
       class="status-filter <?php echo ($status_filter == 'error') ? 'active' : ''; ?>">
       에러 <span class="count">(<?php echo number_format($status_counts['error']); ?>)</span>
    </a>
</div>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<input type="hidden" name="message_type" value="<?php echo $message_type; ?>">
<input type="hidden" name="validity_filter" value="<?php echo $validity_filter; ?>">
<input type="hidden" name="status_filter" value="<?php echo $status_filter; ?>">
<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="title"<?php echo get_selected($sfl, 'title'); ?>>제목</option>
    <option value="target_room_id"<?php echo get_selected($sfl, 'target_room_id'); ?>>톡방 ID</option>
    <option value="message_text"<?php echo get_selected($sfl, 'message_text'); ?>>메시지 내용</option>
</select>
<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="btn_fixed_top">
    <a href="./bot_schedule_form.php?message_type=<?php echo $message_type; ?>&validity_filter=<?php echo $validity_filter; ?>&status_filter=<?php echo $status_filter; ?>" class="btn btn_01">스케줄 등록</a>
</div>

<style>
.schedule-list-table {
    width: 100%;
    border-collapse: collapse;
}
.schedule-row {
    border-bottom: 2px solid #ddd;
}
.schedule-row.bg0 {
    background-color: #f9f9f9;
}
.schedule-row.bg1 {
    background-color: #fff;
}
.schedule-info {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.schedule-info td {
    padding: 5px 10px;
    vertical-align: middle;
}
.schedule-preview {
    padding: 10px;
}
.message-preview {
    margin-bottom: 10px;
    padding: 10px;
    background-color: #f0f0f0;
    border-radius: 5px;
    cursor: help;
    position: relative;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.message-tooltip {
    display: none;
    position: fixed;
    background: #333;
    color: white;
    padding: 10px;
    border-radius: 5px;
    max-width: 400px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 10000;
    white-space: pre-wrap;
    word-wrap: break-word;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    font-size: 13px;
    line-height: 1.5;
    pointer-events: auto;  /* 툴팁 내부에서는 마우스 이벤트 허용 (스크롤 가능) */
}
.image-thumbnails {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.image-group {
    border: 1px solid #ddd;
    padding: 5px;
    border-radius: 3px;
}
.image-group-title {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}
.thumbnails {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
.thumbnail-item {
    position: relative;
    width: 80px;
    height: 80px;
    border: 1px solid #ccc;
    overflow: hidden;
}
.thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.thumbnail-size {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.7);
    color: white;
    font-size: 10px;
    padding: 2px;
    text-align: center;
}
.no-images {
    color: #999;
    font-style: italic;
}
.image-status {
    color: #28a745;
    padding: 5px;
}
.image-status i {
    margin-right: 5px;
}
.image-status small {
    color: #666;
    display: block;
    font-size: 11px;
    margin-top: 3px;
}
.thumbnail-list {
    display: flex;
    gap: 5px;
    align-items: center;
    flex-wrap: wrap;
}
.schedule-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border: 1px solid #ddd;
    border-radius: 3px;
}
.more-images {
    display: inline-block;
    padding: 5px 10px;
    background: #e0e0e0;
    color: #666;
    font-size: 12px;
    border-radius: 3px;
    font-weight: bold;
}
.total-size {
    margin-top: 5px;
    font-size: 11px;
    color: #666;
}
.thumbnail-item img {
    cursor: pointer;
}
.image-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
}
.image-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 90%;
    max-height: 90%;
}
.image-modal-content img {
    width: 100%;
    height: auto;
}
.image-modal-close {
    position: absolute;
    top: 20px;
    right: 40px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}
.image-modal-close:hover {
    color: #ccc;
}
</style>

<div id="imageModal" class="image-modal" onclick="closeModal()">
    <span class="image-modal-close">&times;</span>
    <div class="image-modal-content">
        <img id="modalImage" src="" alt="">
    </div>
</div>

<div id="messageTooltip" class="message-tooltip" 
     onmouseenter="if(hideTimeout) clearTimeout(hideTimeout);" 
     onmouseleave="hideTooltip()"></div>

<script>
function showModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// 툴팁 표시 함수
function showTooltip(element, text) {
    // 숨기기 타이머가 있으면 취소
    if (hideTimeout) {
        clearTimeout(hideTimeout);
    }
    
    var tooltip = document.getElementById('messageTooltip');
    tooltip.textContent = text;
    tooltip.style.display = 'block';
    
    // 요소의 위치 가져오기
    var rect = element.getBoundingClientRect();
    
    // 해당 행의 td_mng(수정/삭제 버튼) 영역 찾기
    var row = element.closest('tr');
    var tdMng = row.querySelector('.td_mng');
    var tdMngRect = tdMng ? tdMng.getBoundingClientRect() : null;
    
    // 툴팁 크기
    var tooltipWidth = 400; // max-width
    
    // 툴팁을 td_mng 왼쪽에 배치 (10px 간격)
    var x = tdMngRect ? tdMngRect.left - tooltipWidth - 10 : window.innerWidth - tooltipWidth - 120;
    
    // 텍스트 메시지 영역의 중앙에 툴팁 중앙이 오도록 Y 위치 계산
    var elementCenterY = rect.top + (rect.height / 2);
    var tooltipHeight = 300; // 예상 높이 (실제로는 동적으로 변함)
    var y = elementCenterY - (tooltipHeight / 2);
    
    // 화면 위/아래 경계 체크
    if (y < 10) {
        y = 10;
    }
    if (y + tooltipHeight > window.innerHeight - 10) {
        y = window.innerHeight - tooltipHeight - 10;
    }
    
    // 화면 왼쪽 벗어나면 조정
    if (x < 10) {
        x = 10;
    }
    
    // position: fixed로 스크롤과 무관하게 위치 설정
    tooltip.style.position = 'fixed';
    tooltip.style.left = x + 'px';
    tooltip.style.top = y + 'px';
    
    // 툴팁이 실제로 렌더링된 후 높이를 기준으로 위치 재조정
    setTimeout(function() {
        var actualHeight = tooltip.offsetHeight;
        var newY = elementCenterY - (actualHeight / 2);
        
        if (newY < 10) {
            newY = 10;
        }
        if (newY + actualHeight > window.innerHeight - 10) {
            newY = window.innerHeight - actualHeight - 10;
        }
        
        tooltip.style.top = newY + 'px';
    }, 0);
}

var hideTimeout;

function hideTooltip() {
    // 약간의 지연 후 툴팁 숨기기 (깜빡거림 방지)
    hideTimeout = setTimeout(function() {
        document.getElementById('messageTooltip').style.display = 'none';
    }, 100);
}

// 툴팁 마우스 이동 추적
function moveTooltip(event) {
    var tooltip = document.getElementById('messageTooltip');
    if (tooltip.style.display === 'block') {
        // 마우스 포인터가 툴팁 내부에 위치하도록 설정
        var x = event.pageX - 20;
        var y = event.pageY - 20;
        
        var tooltipWidth = tooltip.offsetWidth;
        var tooltipHeight = tooltip.offsetHeight;
        
        // 화면 왼쪽 벗어나면 조정
        if (x < 10) {
            x = 10;
        }
        
        // 화면 오른쪽 벗어나면 마우스가 툴팁 오른쪽 내부에 위치하도록
        if (x + tooltipWidth > window.innerWidth + window.pageXOffset - 20) {
            x = event.pageX - tooltipWidth + 20;
        }
        
        // 화면 위쪽 벗어나면 조정
        if (y < 10) {
            y = 10;
        }
        
        // 화면 아래쪽 벗어나면 마우스가 툴팁 아래쪽 내부에 위치하도록
        if (y + tooltipHeight > window.innerHeight + window.pageYOffset - 20) {
            y = event.pageY - tooltipHeight + 20;
        }
        
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }
}
</script>

<div class="tbl_head01 tbl_wrap">
    <table class="schedule-list-table">
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" width="50">ID</th>
        <th scope="col" width="180">제목</th>
        <th scope="col" width="130">대상 톡방</th>
        <th scope="col" width="80">카테고리</th>
        <th scope="col" width="70">주기</th>
        <th scope="col" width="130">발송 시간</th>
        <th scope="col" width="60">상태</th>
        <th scope="col" width="100">다음 발송</th>
        <th scope="col" width="50">횟수</th>
        <th scope="col" width="70">등록일</th>
        <th scope="col" width="100">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 메시지 타입 표시
        $message_type_text = '';
        switch($row['message_type']) {
            case 'schedule':
                $message_type_text = '<span style="color:#333;">일반</span>';
                break;
            case 'order_placed':
                $message_type_text = '<span style="color:#0066cc;">상품주문</span>';
                break;
            case 'order_complete':
                $message_type_text = '<span style="color:#009900;">주문완료</span>';
                break;
            case 'stock_warning':
                $message_type_text = '<span style="color:#ff9900;">품절임박</span>';
                break;
            case 'stock_out':
                $message_type_text = '<span style="color:#cc0000;">품절</span>';
                break;
            default:
                $message_type_text = '<span style="color:#333;">일반</span>';
                break;
        }
        
        // 스케줄 타입 표시
        $schedule_type_text = '';
        switch($row['schedule_type']) {
            case 'once':
                $schedule_type_text = '1회성';
                break;
            case 'daily':
                $schedule_type_text = '매일';
                break;
            case 'weekly':
                $schedule_type_text = '주간반복';
                break;
        }
        
        // 상태 표시
        $status_text = '';
        $status_class = '';
        switch($row['status']) {
            case 'active':
                $status_text = '<span style="color:green;">활성</span>';
                break;
            case 'inactive':
                $status_text = '<span style="color:gray;">비활성</span>';
                break;
            case 'completed':
                $status_text = '<span style="color:blue;">완료</span>';
                break;
            case 'error':
                $status_text = '<span style="color:red;">오류</span>';
                break;
        }
        
        // 발송 시간 표시
        $send_time_text = '';
        
        // 복수 시간 처리
        $times_array = [];
        if (!empty($row['schedule_times'])) {
            $times_array = json_decode($row['schedule_times'], true);
        } else if (!empty($row['schedule_time'])) {
            $times_array = [$row['schedule_time']];
        }
        
        if ($row['schedule_type'] == 'once') {
            $send_time_text = $row['schedule_date'] . ' ';
            if (count($times_array) > 1) {
                $send_time_text .= implode(', ', $times_array);
            } else {
                $send_time_text .= $times_array[0];
            }
        } else {
            if (count($times_array) > 1) {
                $send_time_text = implode(', ', $times_array);
            } else {
                $send_time_text = $times_array[0];
            }
            
            if ($row['schedule_type'] == 'weekly' && $row['schedule_weekdays']) {
                $weekdays = explode(',', $row['schedule_weekdays']);
                $weekday_kr = [];
                foreach($weekdays as $wd) {
                    switch($wd) {
                        case 'monday': $weekday_kr[] = '월'; break;
                        case 'tuesday': $weekday_kr[] = '화'; break;
                        case 'wednesday': $weekday_kr[] = '수'; break;
                        case 'thursday': $weekday_kr[] = '목'; break;
                        case 'friday': $weekday_kr[] = '금'; break;
                        case 'saturday': $weekday_kr[] = '토'; break;
                        case 'sunday': $weekday_kr[] = '일'; break;
                    }
                }
                $send_time_text .= ' (' . implode(',', $weekday_kr) . ')';
            }
        }
        
        // 톡방 이름 조회
        $room_sql = "SELECT room_name FROM kb_rooms 
                     WHERE room_id = '".sql_escape_string($row['target_room_id'])."' 
                     AND bot_name = '".sql_escape_string($row['target_bot_name'])."' 
                     AND device_id = '".sql_escape_string($row['target_device_id'])."' 
                     LIMIT 1";
        $room_row = sql_fetch($room_sql);
        $room_name = $room_row['room_name'] ? $room_row['room_name'] : $row['target_room_id'];
        
        // 메시지 미리보기 (1줄만 표시)
        $message_preview = '';
        $message_full = '';
        if ($row['message_text']) {
            $message_full = $row['message_text'];
            // 줄바꿈 기준으로 첫 줄만 추출
            $lines = explode("\n", $message_full);
            $first_line = trim($lines[0]);
            
            // 첫 줄이 너무 길면 50자로 자르기
            if (mb_strlen($first_line, 'utf-8') > 50) {
                $message_preview = mb_substr($first_line, 0, 50, 'utf-8') . '...';
            } else if (count($lines) > 1) {
                // 여러 줄이 있으면 첫 줄만 표시하고 ... 추가
                $message_preview = $first_line . '...';
            } else {
                $message_preview = $first_line;
            }
        }
        
        // 이미지 존재 여부만 확인 (목록에서는 전체 데이터를 가져오지 않음)
        $has_images_1 = $row['has_images_1'];
        $has_images_2 = $row['has_images_2'];
    ?>
    <tr class="schedule-row <?php echo $bg; ?>">
        <td colspan="11" style="padding: 0;">
            <table width="100%">
                <tr class="schedule-info">
                    <td class="td_num" width="50"><?php echo $row['id'] ?></td>
                    <td class="td_left" width="180"><?php echo get_text($row['title']) ?></td>
                    <td class="td_left" width="130"><?php echo get_text($room_name) ?></td>
                    <td class="td_category" width="80"><?php echo $message_type_text ?></td>
                    <td class="td_category" width="70"><?php echo $schedule_type_text ?></td>
                    <td class="td_datetime" width="130"><?php echo $send_time_text ?></td>
                    <td class="td_boolean" width="60"><?php echo $status_text ?></td>
                    <td class="td_datetime" width="100"><?php echo $row['next_send_at'] ? substr($row['next_send_at'], 0, 16) : '-' ?></td>
                    <td class="td_num" width="50"><?php echo number_format($row['send_count']) ?>회</td>
                    <td class="td_datetime" width="70"><?php echo substr($row['created_at'], 0, 10) ?></td>
                    <td class="td_mng td_mng_m" width="100">
                        <a href="./bot_schedule_form.php?w=u&amp;id=<?php echo $row['id'] ?>&amp;<?php echo $qstr ?>" class="btn btn_03">수정</a>
                        <a href="./bot_schedule_delete.php?id=<?php echo $row['id'] ?>&amp;<?php echo $qstr ?>" onclick="return confirm('정말로 삭제하시겠습니까?');" class="btn btn_02">삭제</a>
                    </td>
                </tr>
                <tr class="schedule-preview">
                    <td colspan="11">
                        <?php if ($message_preview): ?>
                        <div class="message-preview" 
                             onmouseover="showTooltip(this, <?php echo htmlspecialchars(json_encode($message_full), ENT_QUOTES) ?>)" 
                             onmouseout="hideTooltip()">
                            <?php echo htmlspecialchars($message_preview) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="image-thumbnails">
                            <div class="image-group">
                                <div class="image-group-title">이미지 그룹 1</div>
                                <?php if ($has_images_1): ?>
                                    <div class="thumbnail-list">
                                        <?php
                                        // 썸네일 표시
                                        if ($row['image_storage_mode'] == 'base64' && !empty($row['message_thumbnails_1'])) {
                                            // Base64 썸네일 표시
                                            $base64_thumbnails = json_decode($row['message_thumbnails_1'], true);
                                            if ($base64_thumbnails && is_array($base64_thumbnails)) {
                                                foreach ($base64_thumbnails as $idx => $thumb) {
                                                    if (isset($thumb['base64'])) {
                                                        // MIME 타입 추론
                                                        $mimeType = 'image/jpeg';
                                                        if (isset($thumb['name'])) {
                                                            $ext = strtolower(pathinfo($thumb['name'], PATHINFO_EXTENSION));
                                                            if ($ext == 'png') $mimeType = 'image/png';
                                                            else if ($ext == 'gif') $mimeType = 'image/gif';
                                                            else if ($ext == 'webp') $mimeType = 'image/webp';
                                                        }
                                                        echo '<img src="data:'.$mimeType.';base64,'.$thumb['base64'].'" alt="" class="schedule-thumb" style="cursor:pointer;" onclick="viewBase64Original(\''.$row['id'].'\', 1, '.$idx.')">';
                                                    }
                                                }
                                            }
                                        } else if (!empty($row['message_thumbnails_1'])) {
                                            // 파일 방식 썸네일 표시
                                            $thumbnails_1 = json_decode($row['message_thumbnails_1'], true);
                                            if ($thumbnails_1) {
                                                foreach ($thumbnails_1 as $idx => $thumb) {
                                                    // 모든 이미지 표시
                                                    $thumb_path = G5_DATA_PATH.'/'.$thumb['path'];
                                                    $thumb_url = G5_DATA_URL.'/'.$thumb['path'];
                                                    if (file_exists($thumb_path)) {
                                                        // 원본 이미지 경로 구하기
                                                        $original_path = str_replace('thumb_', '', $thumb['path']);
                                                        $original_url = G5_DATA_URL.'/'.$original_path;
                                                        echo '<img src="'.$thumb_url.'" alt="" class="schedule-thumb" onclick="viewOriginalImage(\''.$original_url.'\')" style="cursor:pointer;">';
                                                    }
                                                }
                                            }
                                        } else {
                                            // 썸네일이 없으면 아이콘만 표시
                                            echo '<div class="image-status"><i class="fa fa-image"></i> 이미지 있음</div>';
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-images">이미지 없음</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="image-group">
                                <div class="image-group-title">이미지 그룹 2</div>
                                <?php if ($has_images_2): ?>
                                    <div class="thumbnail-list">
                                        <?php
                                        // 썸네일 표시
                                        if ($row['image_storage_mode'] == 'base64' && !empty($row['message_thumbnails_2'])) {
                                            // Base64 썸네일 표시
                                            $base64_thumbnails = json_decode($row['message_thumbnails_2'], true);
                                            if ($base64_thumbnails && is_array($base64_thumbnails)) {
                                                foreach ($base64_thumbnails as $idx => $thumb) {
                                                    if (isset($thumb['base64'])) {
                                                        // MIME 타입 추론
                                                        $mimeType = 'image/jpeg';
                                                        if (isset($thumb['name'])) {
                                                            $ext = strtolower(pathinfo($thumb['name'], PATHINFO_EXTENSION));
                                                            if ($ext == 'png') $mimeType = 'image/png';
                                                            else if ($ext == 'gif') $mimeType = 'image/gif';
                                                            else if ($ext == 'webp') $mimeType = 'image/webp';
                                                        }
                                                        echo '<img src="data:'.$mimeType.';base64,'.$thumb['base64'].'" alt="" class="schedule-thumb" style="cursor:pointer;" onclick="viewBase64Original(\''.$row['id'].'\', 2, '.$idx.')">';
                                                    }
                                                }
                                            }
                                        } else if (!empty($row['message_thumbnails_2'])) {
                                            // 파일 방식 썸네일 표시
                                            $thumbnails_2 = json_decode($row['message_thumbnails_2'], true);
                                            if ($thumbnails_2) {
                                                foreach ($thumbnails_2 as $idx => $thumb) {
                                                    // 모든 이미지 표시
                                                    $thumb_path = G5_DATA_PATH.'/'.$thumb['path'];
                                                    $thumb_url = G5_DATA_URL.'/'.$thumb['path'];
                                                    if (file_exists($thumb_path)) {
                                                        // 원본 이미지 경로 구하기
                                                        $original_path = str_replace('thumb_', '', $thumb['path']);
                                                        $original_url = G5_DATA_URL.'/'.$original_path;
                                                        echo '<img src="'.$thumb_url.'" alt="" class="schedule-thumb" onclick="viewOriginalImage(\''.$original_url.'\')" style="cursor:pointer;">';
                                                    }
                                                }
                                            }
                                        } else {
                                            // 썸네일이 없으면 아이콘만 표시
                                            echo '<div class="image-status"><i class="fa fa-image"></i> 이미지 있음</div>';
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-images">이미지 없음</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
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

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<script>
function viewOriginalImage(imageUrl) {
    // 간단한 모달 팝업으로 원본 이미지 표시
    var modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
    
    var img = document.createElement('img');
    img.src = imageUrl;
    img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;';
    
    modal.appendChild(img);
    
    // 클릭하면 닫기
    modal.onclick = function() {
        document.body.removeChild(modal);
    };
    
    document.body.appendChild(modal);
}

// Base64 원본 이미지 보기를 위한 AJAX 호출
function viewBase64Original(scheduleId, groupNum, imageIndex) {
    // AJAX로 원본 이미지 데이터 가져오기
    $.ajax({
        url: './bot_schedule_get_image.php',
        type: 'POST',
        data: {
            id: scheduleId,
            group: groupNum,
            index: imageIndex
        },
        success: function(response) {
            if (response.success && response.base64) {
                // Base64 데이터로 모달 표시
                var modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
                
                var img = document.createElement('img');
                var mimeType = 'image/jpeg';
                if (response.name) {
                    var ext = response.name.split('.').pop().toLowerCase();
                    if (ext == 'png') mimeType = 'image/png';
                    else if (ext == 'gif') mimeType = 'image/gif';
                    else if (ext == 'webp') mimeType = 'image/webp';
                }
                img.src = 'data:' + mimeType + ';base64,' + response.base64;
                img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;';
                
                modal.appendChild(img);
                
                // 클릭하면 닫기
                modal.onclick = function() {
                    document.body.removeChild(modal);
                };
                
                document.body.appendChild(modal);
            } else {
                alert('이미지를 불러올 수 없습니다.');
            }
        },
        error: function() {
            alert('이미지 로드 중 오류가 발생했습니다.');
        }
    });
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>