<?php
/**
 * 채팅방 관리
 * kb_rooms 테이블 기반 채팅방 관리
 */

$sub_menu = "180500";
include_once('./_common.php');

auth_check('180500', 'r');

$g5['title'] = '채팅방 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// URL 파라미터
$qstr = '';
if (isset($sfl))  $qstr .= 'sfl=' . urlencode($sfl) . '&amp;';
if (isset($stx))  $qstr .= 'stx=' . urlencode($stx) . '&amp;';
if (isset($sst))  $qstr .= 'sst=' . urlencode($sst) . '&amp;';
if (isset($sod))  $qstr .= 'sod=' . urlencode($sod) . '&amp;';
if (isset($status)) $qstr .= 'status=' . urlencode($status) . '&amp;';
if (isset($bot_name)) $qstr .= 'bot_name=' . urlencode($bot_name) . '&amp;';

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $sql_search .= " AND (room_name LIKE '%{$stx}%' OR bot_name LIKE '%{$stx}%' OR room_id LIKE '%{$stx}%') ";
}

if($status) {
    $sql_search .= " AND status = '{$status}' ";
}

if($bot_name) {
    $sql_search .= " AND bot_name = '{$bot_name}' ";
}

$where .= $sql_search;

// 봇 목록 조회 (선택박스용)
$bot_list = [];
$sql = " SELECT DISTINCT bot_name FROM kb_rooms WHERE bot_name IS NOT NULL ORDER BY bot_name ";
$result_bots = sql_query($sql);
while($row = sql_fetch_array($result_bots)) {
    $bot_list[] = $row['bot_name'];
}

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt FROM kb_rooms $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 20;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 채팅방 목록 조회 - JOIN으로 지점명까지 한번에 조회
$sql = " SELECT r.*, m.mb_name as owner_name 
         FROM kb_rooms r 
         LEFT JOIN {$g5['member_table']} m ON r.owner_id = m.mb_id 
         $where 
         ORDER BY r.created_at DESC 
         LIMIT $start, $rows ";
$result = sql_query($sql);

// 쿼리 결과 확인
if($result) {
    $num_rows = sql_num_rows($result);
    echo "<!-- Query returned $num_rows rows -->";
} else {
    echo "<!-- Query failed -->";
}

// 상태별 통계
$stats_sql = " SELECT status, COUNT(*) as cnt FROM kb_rooms GROUP BY status ";
$stats_result = sql_query($stats_sql);
$stats = [];
while($row = sql_fetch_array($stats_result)) {
    $stats[$row['status']] = $row['cnt'];
}

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 채팅방</span><span class="ov_num"><?=number_format($total_count)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">승인됨</span><span class="ov_num"><?=number_format($stats['approved'] ?? 0)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">대기중</span><span class="ov_num"><?=number_format($stats['pending'] ?? 0)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">거부됨</span><span class="ov_num"><?=number_format($stats['denied'] ?? 0)?></span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>채팅방 검색</legend>
    <select name="bot_name">
        <option value="">전체 봇</option>
        <?php foreach($bot_list as $bot): ?>
        <option value="<?=$bot?>" <?php echo $bot_name==$bot?'selected':'';?>><?=get_text($bot)?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">전체 상태</option>
        <option value="pending" <?php echo $status=='pending'?'selected':'';?>>승인 대기</option>
        <option value="approved" <?php echo $status=='approved'?'selected':'';?>>승인됨</option>
        <option value="denied" <?php echo $status=='denied'?'selected':'';?>>거부됨</option>
        <option value="revoked" <?php echo $status=='revoked'?'selected':'';?>>취소됨</option>
        <option value="blocked" <?php echo $status=='blocked'?'selected':'';?>>차단됨</option>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="채팅방명/봇명/방ID 검색">
    <input type="submit" class="btn_submit" value="검색">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오톡 채팅방의 봇 승인 상태를 관리합니다. 새로운 채팅방의 승인/거부 및 로그 설정을 관리할 수 있습니다.</p>
</div>

<form name="froomlist" id="froomlist">
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">채팅방 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">방 ID</th>
        <th scope="col">채팅방명</th>
        <th scope="col">봇명</th>
        <th scope="col">디바이스</th>
        <th scope="col">배정 지점</th>
        <th scope="col">방장 정보</th>
        <th scope="col">상태</th>
        <th scope="col">등록일</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 디버깅용
        if($i == 0) {
            echo "<!-- First row data: " . print_r($row, true) . " -->";
        }
        
        // 상태별 클래스와 텍스트
        $status_class = '';
        $status_text = '';
        switch($row['status']) {
            case 'pending':
                $status_class = 'warn';
                $status_text = '<span class="fc_ffc107">승인 대기</span>';
                break;
            case 'approved':
                $status_class = 'online';
                $status_text = '<span class="fc_28a745">승인됨</span>';
                break;
            case 'denied':
                $status_class = 'offline';
                $status_text = '<span class="fc_dc3545">거부됨</span>';
                break;
            case 'revoked':
                $status_class = 'offline';
                $status_text = '<span class="fc_6c757d">취소됨</span>';
                break;
            case 'blocked':
                $status_class = 'error';
                $status_text = '<span class="fc_dc3545">차단됨</span>';
                break;
        }
        
        // 방장 정보 파싱
        $owners_info = '';
        if($row['room_owners']) {
            $owners = json_decode($row['room_owners'], true);
            if($owners && is_array($owners)) {
                $owners_info = implode(', ', array_slice($owners, 0, 3));
                if(count($owners) > 3) {
                    $owners_info .= ' 외 ' . (count($owners) - 3) . '명';
                }
            }
        }
        
        // 로그 설정 정보
        $log_settings_info = '';
        if($row['log_settings']) {
            $log_settings = json_decode($row['log_settings'], true);
            if($log_settings) {
                $log_settings_info = '<small>';
                if(isset($log_settings['enabled']) && $log_settings['enabled']) {
                    $log_settings_info .= '로그 활성화';
                } else {
                    $log_settings_info .= '로그 비활성화';
                }
                $log_settings_info .= '</small>';
            }
        }
        
        // 방 ID 마스킹 - 채널 ID만 표시
        $masked_room_id = substr($row['room_id'], 0, 10) . '***';
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['room_name']?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $row['room_id']?>" id="chk_<?php echo $i; ?>">
        </td>
        <td class="td_monospace"><?php echo $masked_room_id?></td>
        <td class="td_left">
            <a href="./room_form.php?w=u&amp;room_id=<?php echo urlencode($row['room_id'])?>&amp;bot_name=<?php echo urlencode($row['bot_name'])?>&amp;device_id=<?php echo urlencode($row['device_id'])?>">
                <?php echo get_text($row['room_name'])?>
            </a>
            <?php echo $log_settings_info?>
        </td>
        <td><?php echo get_text($row['bot_name'])?></td>
        <td>
            <?php if($row['device_id']): ?>
                <span title="<?php echo $row['device_id']?>"><?php echo substr($row['device_id'], 0, 8)?>...</span>
            <?php else: ?>
                <span class="fc_999">-</span>
            <?php endif; ?>
        </td>
        <td class="td_left">
            <?php if($row['owner_id']): ?>
                <?php if($row['owner_name']): ?>
                    <?php echo get_text($row['owner_name'])?>
                    <small>(<?php echo $row['owner_id']?>)</small>
                <?php else: ?>
                    <?php echo get_text($row['owner_id'])?>
                    <small class="fc_red">(지점 정보 없음)</small>
                <?php endif; ?>
            <?php else: ?>
                <span class="fc_999">미배정</span>
            <?php endif; ?>
        </td>
        <td class="td_left">
            <?php if($owners_info): ?>
                <?php echo get_text($owners_info)?>
            <?php else: ?>
                <span class="fc_999">정보 없음</span>
            <?php endif; ?>
        </td>
        <td class="<?php echo $status_class?>"><?php echo $status_text?></td>
        <td class="td_datetime"><?php echo substr($row['created_at'], 0, 16)?></td>
        <td class="td_mng td_mng_s">
            <a href="./room_form.php?w=u&amp;room_id=<?php echo urlencode($row['room_id'])?>&amp;bot_name=<?php echo urlencode($row['bot_name'])?>&amp;device_id=<?php echo urlencode($row['device_id'])?>&amp;<?php echo $qstr?>" class="btn btn_03">수정</a>
        </td>
    </tr>
    <?php
    }
    
    if ($i == 0) {
        echo '<tr><td colspan="9" class="empty_table">등록된 채팅방이 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>


</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<style>
.fc_28a745 { color: #28a745 !important; }
.fc_ffc107 { color: #ffc107 !important; }
.fc_dc3545 { color: #dc3545 !important; }
.fc_6c757d { color: #6c757d !important; }
.fc_999 { color: #999 !important; }
.td_monospace { font-family: 'Courier New', monospace; font-size: 11px; }
.online { background-color: #d4edda; }
.warn { background-color: #fff3cd; }
.offline { background-color: #f8d7da; }
.error { background-color: #f5c6cb; }
</style>


<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>