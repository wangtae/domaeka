<?php
/**
 * 즉시 발송 폼 데이터 처리 페이지
 * 
 * 즉시 발송 폼에서 전송된 데이터를 처리하고 카카오봇 API를 통해 메시지를 발송합니다.
 * 발송 결과는 로그에 기록됩니다.
 */

require_once '../../../adm/_common.php';
require_once G5_DMK_PATH . '/adm/lib/common.lib.php';
require_once G5_DMK_PATH . '/adm/bot/lib/bot.lib.php';

// 관리자 권한 확인
$auth = dmk_get_admin_auth();

// 즉시 발송 기능 권한 확인
if (!kb_check_function_permission('instant_send', $auth)) {
    alert('즉시 메시지 발송 권한이 없습니다.');
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    exit;
}

// 필수 필드 검사
$required_fields = ['message', 'br_id', 'target_type'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        alert($field . ' 필드는 필수입니다.');
        exit;
    }
}

// 데이터 받기
$message = trim($_POST['message']);
$br_id = trim($_POST['br_id']);
$target_type = trim($_POST['target_type']);

// 입력값 검증
if (mb_strlen($message) > 1000) {
    alert('메시지는 1000자를 초과할 수 없습니다.');
    exit;
}

if (!in_array($target_type, ['live', 'test'])) {
    alert('올바른 발송 대상을 선택해주세요.');
    exit;
}

// 지점 권한 확인
if (!kb_check_branch_permission($br_id, $auth)) {
    alert('해당 지점에 대한 권한이 없습니다.');
    exit;
}

// 지점 정보 조회
$branch_sql = "SELECT br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "' AND br_status = 1";
$branch_result = sql_query($branch_sql);

if (!$branch_result || sql_num_rows($branch_result) === 0) {
    alert('지점 정보를 찾을 수 없습니다.');
    exit;
}

$branch = sql_fetch_array($branch_result);

// 이미지 파일 업로드 처리
$image_url = '';
$upload_dir = G5_DATA_PATH . '/bot_images/';
$upload_url = G5_DATA_URL . '/bot_images/';

// 업로드 디렉터리 생성
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['image_file'];
    
    // 파일 크기 체크 (5MB)
    if ($uploaded_file['size'] > 5 * 1024 * 1024) {
        alert('이미지 파일 크기는 5MB를 초과할 수 없습니다.');
        exit;
    }
    
    // 파일 확장자 체크
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        alert('JPG, PNG, GIF 파일만 업로드 가능합니다.');
        exit;
    }
    
    // 파일명 생성 (시간 + 랜덤 문자열)
    $new_filename = 'instant_' . date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // 파일 이동
    if (move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
        $image_url = $upload_url . $new_filename;
    } else {
        alert('이미지 업로드에 실패했습니다.');
        exit;
    }
}

// 발송 데이터 준비
$send_data = [
    'message' => $message,
    'br_id' => $br_id,
    'target_type' => $target_type,
    'image_url' => $image_url
];

// 발송 시간 기록
$send_start_time = microtime(true);

// 카카오봇 API를 통한 메시지 발송
$result = kb_send_instant_message($send_data);

// 발송 소요 시간 계산
$send_duration = round((microtime(true) - $send_start_time) * 1000); // 밀리초

// 발송 결과 페이지 표시
$g5['title'] = '메시지 발송 결과';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 결과 페이지 CSS
echo '<style>
.result-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 30px;
    border-radius: 8px;
    text-align: center;
}

.result-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.result-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.result-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.result-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}

.result-message {
    font-size: 16px;
    margin-bottom: 25px;
    line-height: 1.5;
}

.result-details {
    background: rgba(255, 255, 255, 0.7);
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 25px;
    text-align: left;
}

.result-details h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #495057;
}

.detail-item {
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.detail-label {
    font-weight: bold;
    display: inline-block;
    width: 120px;
}

.button-group {
    margin-top: 20px;
}

.button-group .btn {
    margin: 0 5px;
}

.sent-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    text-align: left;
    border: 1px solid #dee2e6;
}

.sent-image {
    max-width: 200px;
    max-height: 200px;
    margin-top: 10px;
    border-radius: 4px;
}
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">메시지 발송 결과</span></span>
</div>

<div class="result-container <?php echo $result['success'] ? 'result-success' : 'result-error'; ?>">
    <div class="result-icon">
        <?php echo $result['success'] ? '✅' : '❌'; ?>
    </div>
    
    <div class="result-title">
        <?php echo $result['success'] ? '발송 완료' : '발송 실패'; ?>
    </div>
    
    <div class="result-message">
        <?php 
        if ($result['success']) {
            echo '메시지가 성공적으로 발송되었습니다.';
        } else {
            echo '메시지 발송에 실패했습니다.<br>' . htmlspecialchars($result['message']);
        }
        ?>
    </div>
    
    <div class="result-details">
        <h4>발송 정보</h4>
        
        <div class="detail-item">
            <span class="detail-label">지점:</span>
            <?php echo htmlspecialchars($branch['br_name']) . ' (' . $br_id . ')'; ?>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">발송 대상:</span>
            <?php echo $target_type === 'live' ? '운영용 채널' : '테스트용 채널'; ?>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">발송 시간:</span>
            <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">소요 시간:</span>
            <?php echo $send_duration; ?>ms
        </div>
        
        <?php if ($result['success'] && isset($result['data']['message_id'])): ?>
        <div class="detail-item">
            <span class="detail-label">메시지 ID:</span>
            <?php echo htmlspecialchars($result['data']['message_id']); ?>
        </div>
        <?php endif; ?>
        
        <div class="sent-content">
            <strong>발송된 내용:</strong>
            <div style="margin-top: 10px; white-space: pre-wrap;"><?php echo htmlspecialchars($message); ?></div>
            
            <?php if ($image_url): ?>
                <div style="margin-top: 10px;">
                    <strong>첨부 이미지:</strong><br>
                    <img src="<?php echo $image_url; ?>" alt="첨부 이미지" class="sent-image">
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="button-group">
        <a href="bot_instant_send_form.php" class="btn btn_02">다시 발송</a>
        <a href="bot_schedule_list.php" class="btn btn_03">스케줄 목록</a>
        <?php if ($result['success']): ?>
            <a href="bot_chat_log_list.php?search_br_id=<?php echo $br_id; ?>" class="btn btn_03">채팅 내역</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($result['success']): ?>
<div class="local_desc01 local_desc">
    <h3>발송 완료 안내</h3>
    <ul>
        <li>메시지가 성공적으로 발송되었습니다.</li>
        <li>발송된 메시지는 채팅 내역에서 확인할 수 있습니다.</li>
        <li>발송 로그는 시스템에 자동으로 기록됩니다.</li>
    </ul>
</div>
<?php else: ?>
<div class="local_desc01 local_desc">
    <h3>발송 실패 안내</h3>
    <ul>
        <li>메시지 발송에 실패했습니다.</li>
        <li>네트워크 연결 상태를 확인해주세요.</li>
        <li>문제가 지속되면 시스템 관리자에게 문의하세요.</li>
    </ul>
    
    <?php if (isset($result['debug_info'])): ?>
    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
        <strong>디버그 정보:</strong>
        <pre style="margin-top: 10px; font-size: 12px;"><?php echo htmlspecialchars(print_r($result['debug_info'], true)); ?></pre>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
// 자동 새로고침 방지
window.history.replaceState(null, null, window.location.href);

// 성공 시 자동 리다이렉트 (5초 후)
<?php if ($result['success']): ?>
setTimeout(function() {
    if (confirm('메시지가 성공적으로 발송되었습니다.\n스케줄 목록 페이지로 이동하시겠습니까?')) {
        location.href = 'bot_schedule_list.php';
    }
}, 5000);
<?php endif; ?>
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>