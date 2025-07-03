<?php
/**
 * 채팅 내역 조회 페이지
 * 
 * 기존 카카오봇 시스템이 저장한 각 지점 톡방의 대화 내역을 조회하여 관리자에게 보여주는 기능을 제공합니다.
 * 권한별로 조회 가능한 지점이 제한됩니다.
 */

require_once '../../../adm/_common.php';
require_once G5_DMK_PATH . '/adm/lib/common.lib.php';
require_once G5_DMK_PATH . '/adm/bot/lib/bot.lib.php';

// 관리자 권한 확인
$auth = dmk_get_admin_auth();

// 채팅 로그 조회 기능 권한 확인
if (!kb_check_function_permission('chat_log', $auth)) {
    alert('채팅 내역 조회 권한이 없습니다.');
    exit;
}

// 검색 조건 처리
$search_br_id = isset($_GET['search_br_id']) ? trim($_GET['search_br_id']) : '';
$search_date_from = isset($_GET['search_date_from']) ? trim($_GET['search_date_from']) : '';
$search_date_to = isset($_GET['search_date_to']) ? trim($_GET['search_date_to']) : '';
$search_keyword = isset($_GET['search_keyword']) ? trim($_GET['search_keyword']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 기본 날짜 설정 (최근 7일)
if (!$search_date_from && !$search_date_to) {
    $search_date_to = date('Y-m-d');
    $search_date_from = date('Y-m-d', strtotime('-7 days'));
}

// 필터 조건 설정
$filters = [];

// 권한별 지점 필터링
if (!$auth['is_super']) {
    switch ($auth['mb_type']) {
        case 'branch':
            $filters['br_id'] = $auth['br_id'];
            $search_br_id = $auth['br_id']; // 강제 설정
            break;
        case 'agency':
            // 소속 지점들만 조회
            $agency_branches_sql = "SELECT br_id FROM dmk_branch WHERE ag_id = '" . sql_escape_string($auth['ag_id']) . "'";
            $agency_branches_result = sql_query($agency_branches_sql);
            $agency_branch_ids = [];
            while ($row = sql_fetch_array($agency_branches_result)) {
                $agency_branch_ids[] = $row['br_id'];
            }
            if (!empty($agency_branch_ids)) {
                $filters['br_ids'] = $agency_branch_ids;
            }
            break;
        case 'distributor':
            // 소속 대리점의 지점들만 조회
            $distributor_branches_sql = "SELECT b.br_id FROM dmk_branch b 
                                        JOIN dmk_agency a ON b.ag_id = a.ag_id 
                                        WHERE a.dt_id = '" . sql_escape_string($auth['dt_id']) . "'";
            $distributor_branches_result = sql_query($distributor_branches_sql);
            $distributor_branch_ids = [];
            while ($row = sql_fetch_array($distributor_branches_result)) {
                $distributor_branch_ids[] = $row['br_id'];
            }
            if (!empty($distributor_branch_ids)) {
                $filters['br_ids'] = $distributor_branch_ids;
            }
            break;
    }
}

// 추가 검색 조건
if ($search_br_id) {
    // 권한 확인
    if (kb_check_branch_permission($search_br_id, $auth)) {
        $filters['br_id'] = $search_br_id;
    } else {
        alert('해당 지점의 채팅 내역을 조회할 권한이 없습니다.');
        exit;
    }
}

if ($search_date_from) {
    $filters['date_from'] = $search_date_from;
}
if ($search_date_to) {
    $filters['date_to'] = $search_date_to;
}
if ($search_keyword) {
    $filters['search'] = $search_keyword;
}

// 채팅 로그 조회
$chat_data = kb_get_chat_log($filters, $page, 30);
$logs = $chat_data['logs'];
$total = $chat_data['total'];
$total_pages = $chat_data['total_pages'];

// 관리 가능한 지점 목록 조회
$branch_where = '';
if (!$auth['is_super']) {
    switch ($auth['mb_type']) {
        case 'branch':
            $branch_where = "AND br_id = '" . sql_escape_string($auth['br_id']) . "'";
            break;
        case 'agency':
            $branch_where = "AND ag_id = '" . sql_escape_string($auth['ag_id']) . "'";
            break;
        case 'distributor':
            $branch_where = "AND ag_id IN (SELECT ag_id FROM dmk_agency WHERE dt_id = '" . sql_escape_string($auth['dt_id']) . "')";
            break;
    }
}

$branch_sql = "SELECT br_id, br_name FROM dmk_branch WHERE br_status = 1 {$branch_where} ORDER BY br_name";
$branch_result = sql_query($branch_sql);
$branches = [];
while ($branch_row = sql_fetch_array($branch_result)) {
    $branches[] = $branch_row;
}

// 페이지 제목 및 헤더
$g5['title'] = '채팅 내역 조회';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 추가 CSS
echo '<style>
.search-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-form .form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.search-form .form-group {
    flex: 1;
    min-width: 200px;
}

.search-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.search-form select, .search-form input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.chat-message {
    margin-bottom: 15px;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.message-from-bot {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.message-from-user {
    background: #f3e5f5;
    border-left: 4px solid #9c27b0;
}

.message-system {
    background: #fff3e0;
    border-left: 4px solid #ff9800;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 14px;
}

.sender-info {
    font-weight: bold;
    color: #495057;
}

.message-time {
    color: #6c757d;
    font-size: 12px;
}

.message-content {
    line-height: 1.5;
    white-space: pre-wrap;
}

.message-image {
    max-width: 300px;
    max-height: 300px;
    margin-top: 10px;
    border-radius: 4px;
    cursor: pointer;
}

.no-logs {
    text-align: center;
    padding: 50px;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 8px;
}

.pagination {
    text-align: center;
    margin-top: 20px;
}

.pagination a {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #495057;
}

.pagination a:hover {
    background: #f8f9fa;
}

.pagination .current {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.stats-info {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #b8daff;
}

.export-buttons {
    margin-bottom: 20px;
    text-align: right;
}

.message-type-badge {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    color: white;
    margin-left: 8px;
}

.type-text { background: #6c757d; }
.type-image { background: #28a745; }
.type-file { background: #ffc107; color: #000; }
.type-sticker { background: #e83e8c; }
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">채팅 내역 조회</span></span>
    <span class="btn_ov01">
        <a href="bot_instant_send_form.php" class="ov_listall">즉시 발송</a>
    </span>
</div>

<div class="local_desc01 local_desc">
    <p>카카오톡 채널의 대화 내역을 조회할 수 있습니다.</p>
</div>

<!-- 검색 폼 -->
<div class="search-form">
    <form method="get">
        <div class="form-row">
            <?php if ($auth['mb_type'] !== 'branch'): ?>
            <div class="form-group">
                <label for="search_br_id">지점 선택</label>
                <select name="search_br_id" id="search_br_id">
                    <option value="">전체 지점</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['br_id']; ?>" 
                                <?php echo $search_br_id === $branch['br_id'] ? 'selected' : ''; ?>>
                            <?php echo $branch['br_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="search_date_from">시작 날짜</label>
                <input type="date" name="search_date_from" id="search_date_from" 
                       value="<?php echo $search_date_from; ?>">
            </div>
            
            <div class="form-group">
                <label for="search_date_to">종료 날짜</label>
                <input type="date" name="search_date_to" id="search_date_to" 
                       value="<?php echo $search_date_to; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="search_keyword">검색어</label>
                <input type="text" name="search_keyword" id="search_keyword" 
                       value="<?php echo htmlspecialchars($search_keyword); ?>" 
                       placeholder="메시지 내용 또는 발신자명">
            </div>
            
            <div class="form-group" style="display: flex; align-items: end; gap: 10px;">
                <button type="submit" class="btn btn_02">검색</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn_03">초기화</a>
            </div>
        </div>
    </form>
</div>

<!-- 통계 정보 -->
<?php if (!empty($logs)): ?>
<div class="stats-info">
    <strong>조회 결과:</strong> 
    총 <?php echo number_format($total); ?>건의 메시지 
    (<?php echo $search_date_from; ?> ~ <?php echo $search_date_to; ?>)
    
    <?php if ($search_br_id): ?>
        <?php
        $selected_branch_name = '';
        foreach ($branches as $branch) {
            if ($branch['br_id'] === $search_br_id) {
                $selected_branch_name = $branch['br_name'];
                break;
            }
        }
        ?>
        | 지점: <?php echo $selected_branch_name; ?>
    <?php endif; ?>
</div>

<!-- 내보내기 버튼 -->
<div class="export-buttons">
    <button type="button" class="btn btn_03" onclick="exportToCSV()">CSV 내보내기</button>
    <button type="button" class="btn btn_03" onclick="printLogs()">인쇄</button>
</div>
<?php endif; ?>

<!-- 채팅 로그 목록 -->
<div id="chat_logs">
    <?php if (empty($logs)): ?>
        <div class="no-logs">
            <h3>채팅 내역이 없습니다</h3>
            <p>선택한 조건에 해당하는 채팅 내역을 찾을 수 없습니다.</p>
        </div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
            <div class="chat-message <?php 
                echo $log['sender_type'] === 'bot' ? 'message-from-bot' : 
                     ($log['sender_type'] === 'user' ? 'message-from-user' : 'message-system'); 
            ?>">
                <div class="message-header">
                    <div class="sender-info">
                        <?php echo htmlspecialchars($log['sender_name'] ?: '알 수 없음'); ?>
                        
                        <?php
                        // 메시지 타입 뱃지
                        $message_type = $log['message_type'] ?? 'text';
                        switch ($message_type) {
                            case 'image':
                                echo '<span class="message-type-badge type-image">이미지</span>';
                                break;
                            case 'file':
                                echo '<span class="message-type-badge type-file">파일</span>';
                                break;
                            case 'sticker':
                                echo '<span class="message-type-badge type-sticker">스티커</span>';
                                break;
                            default:
                                echo '<span class="message-type-badge type-text">텍스트</span>';
                        }
                        ?>
                    </div>
                    <div class="message-time">
                        <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                    </div>
                </div>
                
                <div class="message-content">
                    <?php echo htmlspecialchars($log['message']); ?>
                    
                    <?php if (!empty($log['image_url'])): ?>
                        <div>
                            <img src="<?php echo $log['image_url']; ?>" 
                                 alt="첨부 이미지" 
                                 class="message-image"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- 페이징 -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php
    $start_page = max(1, $page - 5);
    $end_page = min($total_pages, $page + 5);
    
    // 이전 페이지
    if ($page > 1) {
        $query_params = $_GET;
        $query_params['page'] = $page - 1;
        echo '<a href="?' . http_build_query($query_params) . '">이전</a>';
    }
    
    // 페이지 번호
    for ($i = $start_page; $i <= $end_page; $i++) {
        $query_params = $_GET;
        $query_params['page'] = $i;
        
        if ($i == $page) {
            echo '<a href="?' . http_build_query($query_params) . '" class="current">' . $i . '</a>';
        } else {
            echo '<a href="?' . http_build_query($query_params) . '">' . $i . '</a>';
        }
    }
    
    // 다음 페이지
    if ($page < $total_pages) {
        $query_params = $_GET;
        $query_params['page'] = $page + 1;
        echo '<a href="?' . http_build_query($query_params) . '">다음</a>';
    }
    ?>
</div>
<?php endif; ?>

<div class="local_desc01 local_desc">
    <h3>채팅 내역 조회 안내</h3>
    <ul>
        <li><strong>파란색:</strong> 봇에서 발송한 메시지</li>
        <li><strong>보라색:</strong> 사용자가 보낸 메시지</li>
        <li><strong>주황색:</strong> 시스템 메시지</li>
        <li>이미지를 클릭하면 원본 크기로 볼 수 있습니다.</li>
        <li>최대 1개월 이내의 데이터만 조회할 수 있습니다.</li>
    </ul>
</div>

<script>
// CSV 내보내기
function exportToCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = '?' + params.toString();
    link.download = 'chat_log_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}

// 인쇄
function printLogs() {
    const printWindow = window.open('', '_blank');
    const chatLogs = document.getElementById('chat_logs').innerHTML;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>채팅 내역</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .chat-message { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; }
                .message-from-bot { background: #e3f2fd; }
                .message-from-user { background: #f3e5f5; }
                .message-system { background: #fff3e0; }
                .message-header { font-weight: bold; margin-bottom: 5px; }
                .message-time { float: right; color: #666; }
                .message-content { clear: both; }
                .message-image { max-width: 200px; }
            </style>
        </head>
        <body>
            <h2>채팅 내역</h2>
            ${chatLogs}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// 자동 새로고침 (5분마다) - 실시간 모니터링용
if (<?php echo json_encode($search_date_to === date('Y-m-d')); ?>) {
    setInterval(function() {
        location.reload();
    }, 300000); // 5분
}
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>