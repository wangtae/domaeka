<?php
/**
 * 채팅 내역 조회
 * kb_chat_logs 테이블 기반 채팅 로그 조회
 */

include_once('./_common.php');

auth_check('180700', 'r');

$g5['title'] = '채팅 내역 조회';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $search_type = $_GET['search_type'] ?? 'all';
    switch($search_type) {
        case 'room_name':
            $sql_search .= " AND room_name LIKE '%{$stx}%' ";
            break;
        case 'sender':
            $sql_search .= " AND sender LIKE '%{$stx}%' ";
            break;
        case 'message':
            $sql_search .= " AND message LIKE '%{$stx}%' ";
            break;
        default:
            $sql_search .= " AND (room_name LIKE '%{$stx}%' OR sender LIKE '%{$stx}%' OR message LIKE '%{$stx}%') ";
    }
}

if($bot_name) {
    $sql_search .= " AND bot_name = '{$bot_name}' ";
}

if($room_name) {
    $sql_search .= " AND room_name = '{$room_name}' ";
}

if($message_type) {
    $sql_search .= " AND message_type = '{$message_type}' ";
}

if($is_meaningful !== '') {
    $sql_search .= " AND is_meaningful = '{$is_meaningful}' ";
}

// 날짜 범위 필터
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if($date_from) {
    $sql_search .= " AND server_timestamp >= '{$date_from} 00:00:00' ";
}
if($date_to) {
    $sql_search .= " AND server_timestamp <= '{$date_to} 23:59:59' ";
}

// 기본적으로 최근 24시간 데이터만 조회
if(!$date_from && !$date_to) {
    $sql_search .= " AND server_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ";
}

$where .= $sql_search;

// 봇 목록 조회 (선택박스용)
$bot_list = [];
$sql = " SELECT DISTINCT bot_name FROM kb_chat_logs WHERE bot_name IS NOT NULL ORDER BY bot_name ";
$result_bots = sql_query($sql);
while($row = sql_fetch_array($result_bots)) {
    $bot_list[] = $row['bot_name'];
}

// 채팅방 목록 조회 (선택박스용)
$room_list = [];
$sql = " SELECT DISTINCT room_name FROM kb_chat_logs WHERE room_name IS NOT NULL ORDER BY room_name LIMIT 50 ";
$result_rooms = sql_query($sql);
while($row = sql_fetch_array($result_rooms)) {
    $room_list[] = $row['room_name'];
}

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt FROM kb_chat_logs $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 50;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 채팅 로그 목록 조회
$sql = " SELECT * FROM kb_chat_logs $where ORDER BY server_timestamp DESC LIMIT $start, $rows ";
$result = sql_query($sql);

// 통계 정보
$stats_sql = " SELECT 
                  COUNT(*) as total_messages,
                  COUNT(DISTINCT room_name) as total_rooms,
                  COUNT(CASE WHEN is_meaningful = 1 THEN 1 END) as meaningful_messages,
                  COUNT(CASE WHEN is_our_bot_response = 1 THEN 1 END) as bot_responses
               FROM kb_chat_logs $where ";
$stats = sql_fetch($stats_sql);

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 메시지</span><span class="ov_num"><?=number_format($stats['total_messages'])?></span></span>
    <span class="btn_ov01"><span class="ov_txt">채팅방 수</span><span class="ov_num"><?=number_format($stats['total_rooms'])?></span></span>
    <span class="btn_ov01"><span class="ov_txt">의미있는 메시지</span><span class="ov_num"><?=number_format($stats['meaningful_messages'])?></span></span>
    <span class="btn_ov01"><span class="ov_txt">봇 응답</span><span class="ov_num"><?=number_format($stats['bot_responses'])?></span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>채팅 로그 검색</legend>
    <div style="margin-bottom: 10px;">
        <input type="date" name="date_from" value="<?php echo $date_from?>" class="frm_input">
        ~
        <input type="date" name="date_to" value="<?php echo $date_to?>" class="frm_input">
        <span class="frm_info">날짜를 지정하지 않으면 최근 24시간 데이터만 조회됩니다.</span>
    </div>
    <select name="bot_name">
        <option value="">전체 봇</option>
        <?php foreach($bot_list as $bot): ?>
        <option value="<?=$bot?>" <?php echo $bot_name==$bot?'selected':'';?>><?=get_text($bot)?></option>
        <?php endforeach; ?>
    </select>
    <select name="room_name">
        <option value="">전체 채팅방</option>
        <?php foreach($room_list as $room): ?>
        <option value="<?=$room?>" <?php echo $room_name==$room?'selected':'';?>><?=get_text($room)?></option>
        <?php endforeach; ?>
    </select>
    <select name="message_type">
        <option value="">전체 타입</option>
        <option value="text" <?php echo $message_type=='text'?'selected':'';?>>텍스트</option>
        <option value="photo" <?php echo $message_type=='photo'?'selected':'';?>>사진</option>
        <option value="video" <?php echo $message_type=='video'?'selected':'';?>>동영상</option>
        <option value="file" <?php echo $message_type=='file'?'selected':'';?>>파일</option>
        <option value="sticker" <?php echo $message_type=='sticker'?'selected':'';?>>스티커</option>
    </select>
    <select name="is_meaningful">
        <option value="">전체 메시지</option>
        <option value="1" <?php echo $is_meaningful=='1'?'selected':'';?>>의미있는 메시지</option>
        <option value="0" <?php echo $is_meaningful=='0'?'selected':'';?>>일반 메시지</option>
    </select>
    <br>
    <select name="search_type">
        <option value="all" <?php echo $search_type=='all'?'selected':'';?>>전체</option>
        <option value="room_name" <?php echo $search_type=='room_name'?'selected':'';?>>채팅방명</option>
        <option value="sender" <?php echo $search_type=='sender'?'selected':'';?>>발신자</option>
        <option value="message" <?php echo $search_type=='message'?'selected':'';?>>메시지 내용</option>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="검색어 입력">
    <input type="submit" class="btn_submit" value="검색">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오톡 채팅 로그를 조회합니다. 봇의 응답 내역과 사용자 메시지를 확인할 수 있습니다.</p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">시간</th>
        <th scope="col">채팅방</th>
        <th scope="col">발신자</th>
        <th scope="col">메시지</th>
        <th scope="col">타입</th>
        <th scope="col">봇</th>
        <th scope="col">상태</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 메시지 타입별 아이콘
        $type_icon = '';
        switch($row['message_type']) {
            case 'text':
                $type_icon = '<i class="fa fa-comment"></i>';
                break;
            case 'photo':
                $type_icon = '<i class="fa fa-image"></i>';
                break;
            case 'video':
                $type_icon = '<i class="fa fa-video-camera"></i>';
                break;
            case 'file':
                $type_icon = '<i class="fa fa-file"></i>';
                break;
            case 'sticker':
                $type_icon = '<i class="fa fa-smile-o"></i>';
                break;
            default:
                $type_icon = '<i class="fa fa-question"></i>';
        }
        
        // 메시지 내용 처리
        $message_content = get_text($row['message']);
        if(strlen($message_content) > 100) {
            $message_content = substr($message_content, 0, 100) . '...';
        }
        
        // 상태 표시
        $status_badges = [];
        if($row['is_meaningful']) {
            $status_badges[] = '<span class="badge badge-primary">의미있음</span>';
        }
        if($row['is_our_bot_response']) {
            $status_badges[] = '<span class="badge badge-success">봇응답</span>';
        }
        if($row['is_mention']) {
            $status_badges[] = '<span class="badge badge-warning">멘션</span>';
        }
        if($row['is_scheduled']) {
            $status_badges[] = '<span class="badge badge-info">예약</span>';
        }
        
        // 발신자 표시
        $sender_name = get_text($row['sender']);
        if($row['is_bot']) {
            $sender_name = '<i class="fa fa-android"></i> ' . $sender_name;
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_datetime"><?php echo substr($row['server_timestamp'], 5, 11)?></td>
        <td class="td_left"><?php echo get_text($row['room_name'])?></td>
        <td><?php echo $sender_name?></td>
        <td class="td_left">
            <?php echo $message_content?>
            <?php if($row['directive']): ?>
                <br><small class="fc_999">지시어: <?php echo get_text($row['directive'])?></small>
            <?php endif; ?>
        </td>
        <td class="td_center"><?php echo $type_icon?> <?php echo $row['message_type']?></td>
        <td><?php echo get_text($row['bot_name'])?></td>
        <td class="td_center">
            <?php echo implode(' ', $status_badges)?>
        </td>
    </tr>
    <?php
    }
    if($total_count == 0) {
        echo '<tr><td colspan="7" class="empty_table">검색된 채팅 로그가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<style>
.badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: bold;
    color: white;
    border-radius: 3px;
    margin-right: 2px;
}
.badge-primary { background-color: #007bff; }
.badge-success { background-color: #28a745; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-info { background-color: #17a2b8; }
.fc_999 { color: #999 !important; }
.empty_table { text-align: center; padding: 30px; color: #999; }
.td_center { text-align: center; }
</style>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>