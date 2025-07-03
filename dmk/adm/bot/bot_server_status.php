<?php
/**
 * 카카오봇 서버 상태 관리 페이지
 * 
 * 카카오봇 서버(Python) 프로그램의 프로세스를 관리하는 본사 전용 메뉴입니다.
 * - 서버 프로세스 상태 확인
 * - 프로세스 시작/중지/재시작
 * - 서버 리소스 사용량 모니터링
 */

require_once '../../../adm/_common.php';
require_once G5_DMK_PATH . '/adm/lib/common.lib.php';
require_once G5_DMK_PATH . '/adm/bot/lib/bot.lib.php';

// 관리자 권한 확인
$auth = dmk_get_admin_auth();

// 본사 관리자만 접근 가능
if (!$auth['is_super']) {
    alert('본사 관리자만 접근 가능합니다.');
    exit;
}

// POST 요청 처리 (서버 제어)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $result = kb_control_server($action);
    
    if ($result['success']) {
        alert($result['message'], $_SERVER['PHP_SELF']);
    } else {
        alert($result['message']);
    }
    exit;
}

// 서버 상태 조회
$server_status = kb_get_server_status();

// 페이지 제목 및 헤더
$g5['title'] = '카카오봇 서버 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 추가 CSS
echo '<style>
.server-status-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-running { background-color: #28a745; }
.status-stopped { background-color: #dc3545; }
.status-unknown { background-color: #6c757d; }

.control-buttons {
    margin-top: 20px;
}

.control-buttons button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.resource-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.resource-item {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #e9ecef;
    text-align: center;
}

.resource-value {
    font-size: 24px;
    font-weight: bold;
    color: #495057;
}

.resource-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">카카오봇 서버 관리</span></span>
</div>

<div class="local_desc01 local_desc">
    <p>카카오봇 서버 프로세스의 상태를 확인하고 제어할 수 있습니다.</p>
</div>

<div class="server-status-card">
    <h3>서버 상태 정보</h3>
    
    <div style="margin-bottom: 15px;">
        <?php
        $status_class = 'status-unknown';
        $status_text = '알 수 없음';
        
        switch ($server_status['status']) {
            case 'running':
                $status_class = 'status-running';
                $status_text = '실행 중';
                break;
            case 'stopped':
                $status_class = 'status-stopped';
                $status_text = '중지됨';
                break;
        }
        ?>
        <span class="status-indicator <?php echo $status_class; ?>"></span>
        <strong>상태: <?php echo $status_text; ?></strong>
    </div>
    
    <div class="resource-info">
        <div class="resource-item">
            <div class="resource-value"><?php echo $server_status['pid'] ?: 'N/A'; ?></div>
            <div class="resource-label">프로세스 ID</div>
        </div>
        
        <div class="resource-item">
            <div class="resource-value"><?php echo number_format($server_status['memory_usage'] ?: 0); ?> MB</div>
            <div class="resource-label">메모리 사용량</div>
        </div>
        
        <div class="resource-item">
            <div class="resource-value"><?php echo number_format($server_status['cpu_usage'] ?: 0, 1); ?>%</div>
            <div class="resource-label">CPU 사용률</div>
        </div>
        
        <div class="resource-item">
            <div class="resource-value"><?php echo $server_status['last_check'] ?: 'N/A'; ?></div>
            <div class="resource-label">마지막 확인</div>
        </div>
    </div>
    
    <div class="control-buttons">
        <h4>서버 제어</h4>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn_02" 
                    <?php echo $server_status['status'] === 'running' ? 'disabled' : ''; ?>>
                서버 시작
            </button>
        </form>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="stop">
            <button type="submit" class="btn btn_02" 
                    <?php echo $server_status['status'] !== 'running' ? 'disabled' : ''; ?>>
                서버 중지
            </button>
        </form>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="restart">
            <button type="submit" class="btn btn_02">서버 재시작</button>
        </form>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="status">
            <button type="submit" class="btn btn_03">상태 새로고침</button>
        </form>
    </div>
</div>

<div class="local_desc01 local_desc">
    <h3>서버 관리 안내</h3>
    <ul>
        <li><strong>서버 시작:</strong> 카카오봇 서버 프로세스를 시작합니다.</li>
        <li><strong>서버 중지:</strong> 실행 중인 카카오봇 서버 프로세스를 안전하게 중지합니다.</li>
        <li><strong>서버 재시작:</strong> 서버를 중지한 후 다시 시작합니다.</li>
        <li><strong>상태 새로고침:</strong> 현재 서버 상태 정보를 최신으로 업데이트합니다.</li>
    </ul>
    
    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
        <strong>주의사항:</strong>
        <ul style="margin-top: 10px;">
            <li>서버 제어는 신중하게 수행하세요.</li>
            <li>서버 중지 시 진행 중인 메시지 발송이 중단될 수 있습니다.</li>
            <li>서버 재시작 시 일시적으로 서비스가 중단됩니다.</li>
        </ul>
    </div>
</div>

<script>
// 자동 새로고침 (30초마다)
setInterval(function() {
    location.reload();
}, 30000);

// 확인 대화상자
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const action = form.querySelector('input[name="action"]').value;
            let message = '';
            
            switch (action) {
                case 'start':
                    message = '카카오봇 서버를 시작하시겠습니까?';
                    break;
                case 'stop':
                    message = '카카오봇 서버를 중지하시겠습니까?\n진행 중인 메시지 발송이 중단될 수 있습니다.';
                    break;
                case 'restart':
                    message = '카카오봇 서버를 재시작하시겠습니까?\n일시적으로 서비스가 중단됩니다.';
                    break;
                case 'status':
                    return true; // 상태 확인은 확인 없이 진행
            }
            
            if (message && !confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>