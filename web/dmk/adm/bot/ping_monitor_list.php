<?php
/**
 * 봇 상태 모니터링
 * kb_ping_monitor 테이블 기반 실시간 봇 상태 모니터링
 */

$sub_menu = "180400";
include_once('./_common.php');

auth_check('180400', 'r');

$g5['title'] = '봇 상태 모니터링';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $sql_search .= " AND (bot_name LIKE '%{$stx}%' OR device_id LIKE '%{$stx}%' OR client_ip LIKE '%{$stx}%') ";
}

if($bot_name) {
    $sql_search .= " AND bot_name = '{$bot_name}' ";
}

// 최근 24시간 데이터만 조회 (기본값)
$time_filter = $_GET['time_filter'] ?? '24h';
switch($time_filter) {
    case '1h':
        $sql_search .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ";
        break;
    case '6h':
        $sql_search .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR) ";
        break;
    case '24h':
        $sql_search .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ";
        break;
    case '7d':
        $sql_search .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
        break;
}

$where .= $sql_search;

// 봇 목록 조회 (선택박스용)
$bot_list = [];
$sql = " SELECT DISTINCT bot_name FROM kb_ping_monitor WHERE bot_name IS NOT NULL ORDER BY bot_name ";
$result_bots = sql_query($sql);
while($row = sql_fetch_array($result_bots)) {
    $bot_list[] = $row['bot_name'];
}

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt FROM kb_ping_monitor $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 30;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 최신 ping 데이터 조회
$sql = " SELECT * FROM kb_ping_monitor $where ORDER BY created_at DESC LIMIT $start, $rows ";
$result = sql_query($sql);

// 현재 활성 봇 통계
$active_bots_sql = " SELECT 
                        COUNT(DISTINCT bot_name) as total_bots,
                        COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN bot_name END) as active_bots,
                        AVG(rtt_ms) as avg_rtt,
                        AVG(memory_percent) as avg_memory
                     FROM kb_ping_monitor 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ";
$stats = sql_fetch($active_bots_sql);

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 봇 수</span><span class="ov_num"><?=number_format($stats['total_bots'])?></span></span>
    <span class="btn_ov01"><span class="ov_txt">활성 봇</span><span class="ov_num"><?=number_format($stats['active_bots'])?></span></span>
    <span class="btn_ov01"><span class="ov_txt">평균 RTT</span><span class="ov_num"><?=number_format($stats['avg_rtt'], 1)?>ms</span></span>
    <span class="btn_ov01"><span class="ov_txt">평균 메모리</span><span class="ov_num"><?=number_format($stats['avg_memory'], 1)?>%</span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>모니터링 검색</legend>
    <select name="time_filter">
        <option value="1h" <?php echo $time_filter=='1h'?'selected':'';?>>최근 1시간</option>
        <option value="6h" <?php echo $time_filter=='6h'?'selected':'';?>>최근 6시간</option>
        <option value="24h" <?php echo $time_filter=='24h'?'selected':'';?>>최근 24시간</option>
        <option value="7d" <?php echo $time_filter=='7d'?'selected':'';?>>최근 7일</option>
    </select>
    <select name="bot_name">
        <option value="">전체 봇</option>
        <?php foreach($bot_list as $bot): ?>
        <option value="<?=$bot?>" <?php echo $bot_name==$bot?'selected':'';?>><?=get_text($bot)?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="봇명/디바이스ID/IP 검색">
    <input type="submit" class="btn_submit" value="검색">
    <input type="button" class="btn_submit" value="새로고침" onclick="location.reload();">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오톡 봇의 실시간 상태를 모니터링합니다. ping 응답, 메모리 사용량, 메시지 큐 상태 등을 확인할 수 있습니다.</p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">봇명</th>
        <th scope="col">디바이스 ID</th>
        <th scope="col">클라이언트 IP</th>
        <th scope="col">메모리 사용량</th>
        <th scope="col">메시지 큐</th>
        <th scope="col">활성 채팅방</th>
        <th scope="col">RTT</th>
        <th scope="col">수신 시간</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 디바이스 ID 마스킹
        $masked_device_id = '';
        if($row['device_id']) {
            $masked_device_id = substr($row['device_id'], 0, 8) . '***' . substr($row['device_id'], -4);
        }
        
        // 메모리 사용량 표시
        $memory_text = '-';
        $memory_class = '';
        if($row['memory_percent'] !== null) {
            $memory_percent = round($row['memory_percent'], 1);
            $memory_text = $memory_percent . '%';
            if($row['total_memory'] && $row['memory_usage']) {
                $memory_text .= '<br><small>' . number_format($row['memory_usage']) . 'MB / ' . number_format($row['total_memory']) . 'MB</small>';
            }
            
            if($memory_percent > 80) {
                $memory_class = 'fc_dc3545';
            } elseif($memory_percent > 60) {
                $memory_class = 'fc_ffc107';
            } else {
                $memory_class = 'fc_28a745';
            }
        }
        
        // RTT 상태
        $rtt_text = $row['rtt_ms'] ? $row['rtt_ms'].'ms' : '-';
        $rtt_class = '';
        if($row['rtt_ms']) {
            if($row['rtt_ms'] > 1000) {
                $rtt_class = 'fc_dc3545';
            } elseif($row['rtt_ms'] > 500) {
                $rtt_class = 'fc_ffc107';
            } else {
                $rtt_class = 'fc_28a745';
            }
        }
        
        // 시간 계산
        $created_time = strtotime($row['created_at']);
        $time_diff = time() - $created_time;
        $status_class = '';
        $status_text = '';
        
        if($time_diff < 60) {
            $status_class = 'online';
            $status_text = '<span class="fc_28a745">활성</span>';
        } elseif($time_diff < 300) {
            $status_class = 'warn';
            $status_text = '<span class="fc_ffc107">지연</span>';
        } else {
            $status_class = 'offline';
            $status_text = '<span class="fc_dc3545">비활성</span>';
        }
        
        // 클라이언트/서버 상태 정보
        $client_status = '';
        $server_status = '';
        if($row['client_status']) {
            $client_data = json_decode($row['client_status'], true);
            if($client_data) {
                $client_status = '<small>v' . ($client_data['version'] ?? 'N/A') . '</small>';
            }
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_left"><?php echo get_text($row['bot_name'])?><?php echo $client_status?></td>
        <td class="td_monospace"><?php echo $masked_device_id?></td>
        <td><?php echo $row['client_ip']?></td>
        <td class="td_num <?php echo $memory_class?>"><?php echo $memory_text?></td>
        <td class="td_num"><?php echo $row['message_queue_size'] ?? '-'?></td>
        <td class="td_num"><?php echo $row['active_rooms'] ?? '-'?></td>
        <td class="td_num <?php echo $rtt_class?>"><?php echo $rtt_text?></td>
        <td class="td_datetime"><?php echo substr($row['created_at'], 5, 11)?></td>
    </tr>
    <?php
    }
    if($total_count == 0) {
        echo '<tr><td colspan="8" class="empty_table">검색된 모니터링 데이터가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<style>
.fc_28a745 { color: #28a745 !important; }
.fc_ffc107 { color: #ffc107 !important; }
.fc_dc3545 { color: #dc3545 !important; }
.fc_6c757d { color: #6c757d !important; }
.td_monospace { font-family: 'Courier New', monospace; font-size: 11px; }
.empty_table { text-align: center; padding: 30px; color: #999; }
.online { background-color: #d4edda; }
.warn { background-color: #fff3cd; }
.offline { background-color: #f8d7da; }
</style>

<script>
// 자동 새로고침 (30초마다)
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>