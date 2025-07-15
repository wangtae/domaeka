<?php
/**
 * 스케줄링 발송 관리 목록
 */

$sub_menu = "180600";
include_once('./_common.php');

auth_check('180600', 'r');

$g5['title'] = '스케줄링 발송 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$sfl = $_GET['sfl'] ? $_GET['sfl'] : 'title';
$stx = $_GET['stx'];

// 권한별 조회 조건
$where = " WHERE 1=1 ";

$user_info = dmk_get_admin_auth($member['mb_id']);
$user_type = $user_info['type'];
$user_key = $user_info['key'];

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
        $where .= " AND (" . implode(' OR ', $conditions) . ") ";
    } else if ($user_type == 'branch') {
        // 지점은 자신의 스케줄만 조회
        $where .= " AND created_by_type = 'branch' AND created_by_id = '$user_key' ";
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
$sql = " SELECT s.*, r.room_name
         FROM kb_schedule s
         LEFT JOIN kb_rooms r ON s.target_room_id = r.room_id AND s.target_bot_name = r.bot_name
         $where
         $order_by
         LIMIT $from_record, $rows ";
$result = sql_query($sql);

$qstr = "&sfl=$sfl&stx=$stx";
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
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
    <a href="./bot_schedule_form.php" class="btn btn_01">스케줄 등록</a>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">ID</th>
        <th scope="col">제목</th>
        <th scope="col">대상 톡방</th>
        <th scope="col">스케줄 타입</th>
        <th scope="col">발송 시간</th>
        <th scope="col">상태</th>
        <th scope="col">다음 발송</th>
        <th scope="col">발송 횟수</th>
        <th scope="col">등록일</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
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
        
        // 톡방 이름
        $room_name = $row['room_name'] ? $row['room_name'] : $row['target_room_id'];
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_num"><?php echo $row['id'] ?></td>
        <td class="td_left"><?php echo get_text($row['title']) ?></td>
        <td class="td_left"><?php echo get_text($room_name) ?></td>
        <td class="td_category"><?php echo $schedule_type_text ?></td>
        <td class="td_datetime"><?php echo $send_time_text ?></td>
        <td class="td_boolean"><?php echo $status_text ?></td>
        <td class="td_datetime"><?php echo $row['next_send_at'] ? substr($row['next_send_at'], 0, 16) : '-' ?></td>
        <td class="td_num"><?php echo number_format($row['send_count']) ?>회</td>
        <td class="td_datetime"><?php echo substr($row['created_at'], 0, 10) ?></td>
        <td class="td_mng td_mng_m">
            <a href="./bot_schedule_form.php?w=u&amp;id=<?php echo $row['id'] ?>&amp;<?php echo $qstr ?>" class="btn btn_03">수정</a>
            <a href="./bot_schedule_delete.php?id=<?php echo $row['id'] ?>&amp;<?php echo $qstr ?>" onclick="return confirm('정말로 삭제하시겠습니까?');" class="btn btn_02">삭제</a>
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