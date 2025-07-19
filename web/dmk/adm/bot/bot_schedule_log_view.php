<?php
/**
 * 스케줄링 발송 로그 상세보기
 * 본사, 총판 전용
 */

$sub_menu = "180610";
include_once('./_common.php');

auth_check('180610', 'r');

$g5['title'] = '스케줄링 발송 로그 상세';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 권한 확인 - 본사, 총판만 접근 가능
$user_info = dmk_get_admin_auth($member['mb_id']);
$user_type = dmk_get_current_user_type();

if (!in_array($user_type, ['super', 'distributor'])) {
    alert('접근 권한이 없습니다.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    alert('잘못된 접근입니다.', './bot_schedule_log_list.php');
}

// 로그 정보 조회
$sql = " SELECT l.*, s.title as schedule_title, s.message_type, s.created_by_type, s.created_by_id,
                s.target_bot_name, s.target_device_id, s.message_text as template_text,
                s.images_1, s.images_2, s.description,
                r.room_name, r.room_id
         FROM kb_schedule_logs l 
         LEFT JOIN kb_schedule s ON l.schedule_id = s.id 
         LEFT JOIN kb_rooms r ON l.target_room_id COLLATE utf8mb4_general_ci = r.room_id
         WHERE l.id = $id ";

// 권한별 조회 제한
if ($user_type == 'distributor') {
    // 총판은 자신과 하위 대리점/지점의 로그만 조회 가능
    $ag_list = [];
    $sql_ag = " SELECT ag_id FROM dmk_agency WHERE dt_id = '{$user_info['dt_id']}' ";
    $result_ag = sql_query($sql_ag);
    while($row = sql_fetch_array($result_ag)) {
        $ag_list[] = "'" . $row['ag_id'] . "'";
    }
    
    $br_list = [];
    if (count($ag_list) > 0) {
        $ag_in = implode(',', $ag_list);
        $sql_br = " SELECT br_id FROM dmk_branch WHERE ag_id IN ($ag_in) ";
        $result_br = sql_query($sql_br);
        while($row = sql_fetch_array($result_br)) {
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
    $sql .= " AND (" . implode(' OR ', $conditions) . ") ";
}

$log = sql_fetch($sql);

if (!$log) {
    alert('해당 로그를 찾을 수 없습니다.', './bot_schedule_log_list.php');
}

// 뒤로가기 URL 파라미터
$qstr = isset($_GET['qstr']) ? $_GET['qstr'] : '';

// 메시지 타입 텍스트
$message_type_text = '';
switch($log['message_type']) {
    case 'schedule':
        $message_type_text = '일반 스케줄';
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
        $message_type_text = $log['message_type'];
}

// 상태 표시
$status_class = '';
$status_text = '';
switch($log['status']) {
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

// 발송 구성 요소
$components = [];
if (!empty($log['send_components'])) {
    $comp_arr = explode(',', $log['send_components']);
    foreach ($comp_arr as $comp) {
        $comp = trim($comp);
        if ($comp == 'text') $components[] = '텍스트';
        else if ($comp == 'images_1') $components[] = '이미지1';
        else if ($comp == 'images_2') $components[] = '이미지2';
    }
}

// 이미지 경로 처리
$image1_url = '';
$image2_url = '';
if (!empty($log['images_1'])) {
    $image1_url = str_replace(G5_PATH, G5_URL, $log['images_1']);
}
if (!empty($log['images_2'])) {
    $image2_url = str_replace(G5_PATH, G5_URL, $log['images_2']);
}
?>

<style>
.view-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
}
.view-section {
    margin-bottom: 30px;
}
.view-section:last-child {
    margin-bottom: 0;
}
.section-title {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e5e5;
}
.info-table {
    width: 100%;
}
.info-table th,
.info-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.info-table th {
    width: 150px;
    background: #f8f9fa;
    text-align: left;
    font-weight: normal;
    color: #666;
}
.status-badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
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
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 12px;
    background: #e7f3ff;
    color: #0066cc;
    display: inline-block;
}
.message-content {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    white-space: pre-wrap;
    line-height: 1.6;
    font-family: monospace;
    font-size: 13px;
}
.error-content {
    background: #fff5f5;
    border: 1px solid #ffdddd;
    border-radius: 4px;
    padding: 15px;
    color: #721c24;
    white-space: pre-wrap;
    font-size: 13px;
}
.component-badge {
    padding: 2px 8px;
    background: #f1f3f5;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 5px;
}
.message-images {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}
.message-image {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
    max-width: 300px;
}
.message-image img {
    max-width: 100%;
    height: auto;
}
.btn-list {
    display: inline-block;
    padding: 8px 20px;
    background: #6c757d;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
}
.btn-list:hover {
    background: #5a6268;
}
</style>

<div class="view-box">
    <!-- 기본 정보 -->
    <div class="view-section">
        <h3 class="section-title">기본 정보</h3>
        <table class="info-table">
            <tr>
                <th>스케줄 제목</th>
                <td><?php echo htmlspecialchars($log['schedule_title']); ?></td>
            </tr>
            <tr>
                <th>메시지 타입</th>
                <td><span class="message-type-badge"><?php echo $message_type_text; ?></span></td>
            </tr>
            <tr>
                <th>발송 상태</th>
                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
            </tr>
            <tr>
                <th>예약 시간</th>
                <td><?php echo $log['scheduled_at'] ? date('Y-m-d H:i:s', strtotime($log['scheduled_at'])) : '-'; ?></td>
            </tr>
            <tr>
                <th>실제 발송 시간</th>
                <td><?php echo $log['started_at'] ? date('Y-m-d H:i:s', strtotime($log['started_at'])) : '-'; ?></td>
            </tr>
            <tr>
                <th>완료 시간</th>
                <td><?php echo $log['completed_at'] ? date('Y-m-d H:i:s', strtotime($log['completed_at'])) : '-'; ?></td>
            </tr>
            <?php if ($log['description']): ?>
            <tr>
                <th>설명</th>
                <td><?php echo nl2br(htmlspecialchars($log['description'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 발송 정보 -->
    <div class="view-section">
        <h3 class="section-title">발송 정보</h3>
        <table class="info-table">
            <tr>
                <th>봇 이름</th>
                <td><?php echo htmlspecialchars($log['target_bot_name']); ?></td>
            </tr>
            <tr>
                <th>디바이스 ID</th>
                <td><?php echo htmlspecialchars($log['target_device_id']); ?></td>
            </tr>
            <tr>
                <th>채팅방</th>
                <td>
                    <?php echo htmlspecialchars($log['room_name'] ?: $log['target_room_id']); ?>
                    <small style="color: #666;">(<?php echo htmlspecialchars($log['target_room_id']); ?>)</small>
                </td>
            </tr>
            <tr>
                <th>발송 구성</th>
                <td>
                    <?php foreach ($components as $comp): ?>
                        <span class="component-badge"><?php echo $comp; ?></span>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- 메시지 내용 -->
    <div class="view-section">
        <h3 class="section-title">메시지 내용</h3>
        
        <?php if (!empty($log['sent_message_text'])): ?>
        <h4 style="font-size: 14px; color: #666; margin-bottom: 10px;">발송된 메시지</h4>
        <div class="message-content"><?php echo htmlspecialchars($log['sent_message_text']); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($log['template_text']) && $log['template_text'] != $log['sent_message_text']): ?>
        <h4 style="font-size: 14px; color: #666; margin: 20px 0 10px 0;">원본 템플릿</h4>
        <div class="message-content" style="background: #f0f8ff;"><?php echo htmlspecialchars($log['template_text']); ?></div>
        <?php endif; ?>
        
        <?php if ($image1_url || $image2_url): ?>
        <div class="message-images">
            <?php if ($image1_url && file_exists(str_replace(G5_URL, G5_PATH, $image1_url))): ?>
            <div class="message-image">
                <h4 style="font-size: 13px; color: #666; margin-bottom: 10px;">이미지 1</h4>
                <img src="<?php echo $image1_url; ?>" alt="이미지 1">
            </div>
            <?php endif; ?>
            
            <?php if ($image2_url && file_exists(str_replace(G5_URL, G5_PATH, $image2_url))): ?>
            <div class="message-image">
                <h4 style="font-size: 13px; color: #666; margin-bottom: 10px;">이미지 2</h4>
                <img src="<?php echo $image2_url; ?>" alt="이미지 2">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 오류 정보 -->
    <?php if (!empty($log['error_message'])): ?>
    <div class="view-section">
        <h3 class="section-title" style="color: #721c24;">오류 정보</h3>
        <div class="error-content"><?php echo htmlspecialchars($log['error_message']); ?></div>
    </div>
    <?php endif; ?>
</div>

<div style="text-align: center;">
    <a href="./bot_schedule_log_list.php?<?php echo $qstr; ?>" class="btn-list">목록으로</a>
</div>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>