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

// 권한별 조회 조건
$where = " WHERE 1=1 ";

// 메시지 타입별 필터링
if ($message_type) {
    $where .= " AND message_type = '".sql_real_escape_string($message_type)."' ";
} else {
    // 기본 탭에서는 일반 스케줄만 표시
    $where .= " AND (message_type = 'schedule' OR message_type IS NULL) ";
}

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

// 목록 조회
$sql = " SELECT s.* FROM kb_schedule s $where $order_by LIMIT $from_record, $rows ";
$result = sql_query($sql);

$qstr = "&sfl=$sfl&stx=$stx&message_type=$message_type";
?>

<style>
.schedule-tabs {
    display: flex;
    margin-bottom: 20px;
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
</style>

<div class="schedule-tabs">
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => ''])); ?>" 
       class="schedule-tab <?php echo (!$message_type) ? 'active' : ''; ?>">일반 스케줄</a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'order_placed'])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'order_placed') ? 'active' : ''; ?>">상품주문</a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'order_complete'])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'order_complete') ? 'active' : ''; ?>">주문완료</a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'stock_warning'])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'stock_warning') ? 'active' : ''; ?>">품절임박</a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['message_type' => 'stock_out'])); ?>" 
       class="schedule-tab <?php echo ($message_type == 'stock_out') ? 'active' : ''; ?>">품절</a>
</div>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<input type="hidden" name="message_type" value="<?php echo $message_type; ?>">
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
    <a href="./bot_schedule_form.php?message_type=<?php echo $message_type; ?>" class="btn btn_01">스케줄 등록</a>
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
function showTooltip(event, text) {
    // 숨기기 타이머가 있으면 취소
    if (hideTimeout) {
        clearTimeout(hideTimeout);
    }
    
    var tooltip = document.getElementById('messageTooltip');
    tooltip.textContent = text;
    tooltip.style.display = 'block';
    
    // 마우스 포인터가 툴팁 내부에 위치하도록 설정
    // 툴팁 좌상단에서 20px 안쪽에 마우스가 위치
    var x = event.pageX - 20;
    var y = event.pageY - 20;
    
    // 화면 경계 체크
    var tooltipWidth = 400; // max-width
    var tooltipHeight = 300; // max-height
    
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
        
        // 메시지 미리보기 (30자)
        $message_preview = '';
        $message_full = '';
        if ($row['message_text']) {
            $message_full = $row['message_text'];
            if (mb_strlen($message_full, 'utf-8') > 30) {
                $message_preview = mb_substr($message_full, 0, 30, 'utf-8') . '...';
            } else {
                $message_preview = $message_full;
            }
        }
        
        // 이미지 정보 파싱
        $images_1 = $row['message_images_1'] ? json_decode($row['message_images_1'], true) : [];
        $images_2 = $row['message_images_2'] ? json_decode($row['message_images_2'], true) : [];
        
        // 썸네일 정보 파싱
        $thumbnails_1 = $row['message_thumbnails_1'] ? json_decode($row['message_thumbnails_1'], true) : [];
        $thumbnails_2 = $row['message_thumbnails_2'] ? json_decode($row['message_thumbnails_2'], true) : [];
        
        // 이미지 용량 계산 (Base64 방식)
        $total_size = 0;
        $image_sizes_1 = [];
        $image_sizes_2 = [];
        
        foreach ($images_1 as $idx => $img) {
            if (isset($img['base64'])) {
                // Base64 데이터 크기 (실제 파일 크기는 약 3/4)
                $size = strlen($img['base64']) * 3 / 4;
                $image_sizes_1[$idx] = $size;
                $total_size += $size;
            } elseif (isset($img['file'])) {
                // 이전 방식 호환성
                $size = get_file_size($img['file']);
                $image_sizes_1[$img['file']] = $size;
                $total_size += $size;
            }
        }
        
        foreach ($images_2 as $idx => $img) {
            if (isset($img['base64'])) {
                // Base64 데이터 크기 (실제 파일 크기는 약 3/4)
                $size = strlen($img['base64']) * 3 / 4;
                $image_sizes_2[$idx] = $size;
                $total_size += $size;
            } elseif (isset($img['file'])) {
                // 이전 방식 호환성
                $size = get_file_size($img['file']);
                $image_sizes_2[$img['file']] = $size;
                $total_size += $size;
            }
        }
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
                             onmouseover="showTooltip(event, <?php echo htmlspecialchars(json_encode($message_full), ENT_QUOTES) ?>)" 
                             onmouseout="hideTooltip()"
                             onmousemove="moveTooltip(event)">
                            <?php echo nl2br(htmlspecialchars($message_preview)) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="image-thumbnails">
                            <div class="image-group">
                                <div class="image-group-title">이미지 그룹 1</div>
                                <?php if (count($images_1) > 0): ?>
                                    <div class="thumbnails">
                                        <?php 
                                        // 썸네일이 있으면 썸네일 사용, 없으면 원본 이미지 사용
                                        $has_thumbnails_1 = !empty($thumbnails_1);
                                        
                                        foreach ($images_1 as $idx => $img): 
                                            // 해당 인덱스의 썸네일 찾기
                                            $thumbnail = null;
                                            if ($has_thumbnails_1) {
                                                foreach ($thumbnails_1 as $thumb) {
                                                    if (isset($thumb['original_idx']) && $thumb['original_idx'] == $idx) {
                                                        $thumbnail = $thumb;
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                            <?php if ($thumbnail && isset($thumbnail['base64'])): ?>
                                            <!-- 썸네일 사용 -->
                                            <div class="thumbnail-item" data-original-idx="<?php echo $idx ?>">
                                                <img src="data:image/jpeg;base64,<?php echo $thumbnail['base64'] ?>" 
                                                     alt="" 
                                                     onclick="showModal('data:image/jpeg;base64,<?php echo $img['base64'] ?>')"
                                                     title="썸네일 (클릭하여 원본 보기)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_1[$idx]) ?></div>
                                            </div>
                                            <?php elseif (isset($img['base64'])): ?>
                                            <!-- 원본 이미지 사용 (썸네일 없음) -->
                                            <div class="thumbnail-item">
                                                <img src="data:image/jpeg;base64,<?php echo $img['base64'] ?>" alt="" onclick="showModal(this.src)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_1[$idx]) ?></div>
                                            </div>
                                            <?php elseif (isset($img['file'])): ?>
                                            <!-- 이전 방식 호환 -->
                                            <div class="thumbnail-item">
                                                <img src="<?php echo G5_DATA_URL ?>/schedule/<?php echo $img['file'] ?>" alt="" onclick="showModal(this.src)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_1[$img['file']]) ?></div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-images">이미지 없음</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="image-group">
                                <div class="image-group-title">이미지 그룹 2</div>
                                <?php if (count($images_2) > 0): ?>
                                    <div class="thumbnails">
                                        <?php 
                                        // 썸네일이 있으면 썸네일 사용, 없으면 원본 이미지 사용
                                        $has_thumbnails_2 = !empty($thumbnails_2);
                                        
                                        foreach ($images_2 as $idx => $img): 
                                            // 해당 인덱스의 썸네일 찾기
                                            $thumbnail = null;
                                            if ($has_thumbnails_2) {
                                                foreach ($thumbnails_2 as $thumb) {
                                                    if (isset($thumb['original_idx']) && $thumb['original_idx'] == $idx) {
                                                        $thumbnail = $thumb;
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                            <?php if ($thumbnail && isset($thumbnail['base64'])): ?>
                                            <!-- 썸네일 사용 -->
                                            <div class="thumbnail-item" data-original-idx="<?php echo $idx ?>">
                                                <img src="data:image/jpeg;base64,<?php echo $thumbnail['base64'] ?>" 
                                                     alt="" 
                                                     onclick="showModal('data:image/jpeg;base64,<?php echo $img['base64'] ?>')"
                                                     title="썸네일 (클릭하여 원본 보기)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_2[$idx]) ?></div>
                                            </div>
                                            <?php elseif (isset($img['base64'])): ?>
                                            <!-- 원본 이미지 사용 (썸네일 없음) -->
                                            <div class="thumbnail-item">
                                                <img src="data:image/jpeg;base64,<?php echo $img['base64'] ?>" alt="" onclick="showModal(this.src)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_2[$idx]) ?></div>
                                            </div>
                                            <?php elseif (isset($img['file'])): ?>
                                            <!-- 이전 방식 호환 -->
                                            <div class="thumbnail-item">
                                                <img src="<?php echo G5_DATA_URL ?>/schedule/<?php echo $img['file'] ?>" alt="" onclick="showModal(this.src)">
                                                <div class="thumbnail-size"><?php echo format_bytes($image_sizes_2[$img['file']]) ?></div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-images">이미지 없음</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($total_size > 0): ?>
                        <div class="total-size">전체 이미지 용량: <?php echo format_bytes($total_size) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php
    }
    
    if ($i == 0)
        echo '<tr><td colspan="10" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>