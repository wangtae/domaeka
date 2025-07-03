<?php
/**
 * 즉시 메시지 발송 폼 페이지
 * 
 * 관리자가 작성한 메시지와 이미지를 즉시 카카오톡 채널로 발송할 수 있는 폼을 제공합니다.
 * 실제 운영 톡방 또는 테스트용 톡방을 선택하여 발송할 수 있습니다.
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

// 지점 관리자의 경우 기본 지점 설정
$default_br_id = '';
if ($auth['mb_type'] === 'branch') {
    $default_br_id = $auth['br_id'];
}

// 페이지 제목 및 헤더
$g5['title'] = '즉시 메시지 발송';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 추가 CSS 및 JS
echo '<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #495057;
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    height: 120px;
    resize: vertical;
}

.radio-group {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.help-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.button-group {
    text-align: center;
    margin-top: 30px;
}

.button-group .btn {
    margin: 0 5px;
}

.message-preview {
    background: #e9ecef;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    border: 1px solid #dee2e6;
}

.message-preview h4 {
    margin-top: 0;
    color: #495057;
}

.preview-content {
    background: white;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    margin-top: 10px;
}

.preview-image {
    max-width: 200px;
    max-height: 200px;
    margin-top: 10px;
    border-radius: 4px;
}

.char-counter {
    text-align: right;
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.char-counter.warning {
    color: #dc3545;
}

.send-options {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.send-options h4 {
    margin-top: 0;
    color: #856404;
}

.target-info {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">즉시 메시지 발송</span></span>
    <span class="btn_ov01">
        <a href="bot_schedule_list.php" class="ov_listall">스케줄 목록</a>
    </span>
</div>

<div class="local_desc01 local_desc">
    <p>작성한 메시지와 이미지를 즉시 카카오톡 채널로 발송할 수 있습니다.</p>
</div>

<div class="form-container">
    <form method="post" action="bot_instant_send_update.php" enctype="multipart/form-data" id="sendForm">
        <!-- 발송 대상 선택 -->
        <div class="send-options">
            <h4>발송 대상 선택</h4>
            <div class="form-group">
                <label for="br_id">지점 선택 *</label>
                <select name="br_id" id="br_id" required <?php echo $auth['mb_type'] === 'branch' ? 'disabled' : ''; ?>>
                    <option value="">지점을 선택하세요</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['br_id']; ?>" 
                                <?php echo $default_br_id === $branch['br_id'] ? 'selected' : ''; ?>>
                            <?php echo $branch['br_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($auth['mb_type'] === 'branch'): ?>
                    <input type="hidden" name="br_id" value="<?php echo $default_br_id; ?>">
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>발송 채널 *</label>
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" name="target_type" id="target_live" value="live" checked>
                        <label for="target_live">운영용 채널</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="target_type" id="target_test" value="test">
                        <label for="target_test">테스트용 채널</label>
                    </div>
                </div>
                <div class="target-info" id="target_info">
                    실제 고객이 참여하는 운영용 채널에 발송됩니다.
                </div>
            </div>
        </div>
        
        <!-- 메시지 내용 -->
        <div class="form-group">
            <label for="message">메시지 내용 *</label>
            <textarea name="message" id="message" required maxlength="1000" placeholder="발송할 메시지를 입력하세요..."></textarea>
            <div class="char-counter">
                <span id="char_count">0</span> / 1000자
            </div>
            <div class="help-text">카카오톡으로 발송될 메시지 내용을 입력하세요.</div>
        </div>
        
        <!-- 이미지 첨부 -->
        <div class="form-group">
            <label for="image_file">이미지 첨부</label>
            <input type="file" name="image_file" id="image_file" accept="image/*">
            <div class="help-text">JPG, PNG, GIF 파일만 업로드 가능합니다. (최대 5MB)</div>
        </div>
        
        <!-- 메시지 미리보기 -->
        <div class="message-preview" id="message_preview" style="display: none;">
            <h4>메시지 미리보기</h4>
            <div class="preview-content">
                <div id="preview_text"></div>
                <div id="preview_image"></div>
            </div>
        </div>
        
        <!-- 발송 확인 -->
        <div class="form-group">
            <label>
                <input type="checkbox" id="confirm_send" required>
                위 내용으로 즉시 메시지를 발송하는 것에 동의합니다.
            </label>
            <div class="help-text">발송된 메시지는 취소할 수 없습니다. 내용을 다시 한 번 확인해주세요.</div>
        </div>
        
        <!-- 버튼 -->
        <div class="button-group">
            <button type="button" id="preview_btn" class="btn btn_03">미리보기</button>
            <button type="submit" id="send_btn" class="btn btn_02">즉시 발송</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char_count');
    const previewBtn = document.getElementById('preview_btn');
    const sendBtn = document.getElementById('send_btn');
    const messagePreview = document.getElementById('message_preview');
    const previewText = document.getElementById('preview_text');
    const previewImage = document.getElementById('preview_image');
    const imageFile = document.getElementById('image_file');
    const targetRadios = document.querySelectorAll('input[name="target_type"]');
    const targetInfo = document.getElementById('target_info');
    const sendForm = document.getElementById('sendForm');
    
    // 글자 수 카운터
    function updateCharCount() {
        const count = messageTextarea.value.length;
        charCount.textContent = count;
        charCount.parentElement.classList.toggle('warning', count > 900);
    }
    
    messageTextarea.addEventListener('input', updateCharCount);
    
    // 발송 대상 정보 업데이트
    function updateTargetInfo() {
        const selectedTarget = document.querySelector('input[name="target_type"]:checked').value;
        if (selectedTarget === 'live') {
            targetInfo.textContent = '실제 고객이 참여하는 운영용 채널에 발송됩니다.';
            targetInfo.style.color = '#dc3545';
        } else {
            targetInfo.textContent = '테스트용 채널에 발송됩니다.';
            targetInfo.style.color = '#6c757d';
        }
    }
    
    targetRadios.forEach(radio => {
        radio.addEventListener('change', updateTargetInfo);
    });
    
    // 미리보기 기능
    previewBtn.addEventListener('click', function() {
        const message = messageTextarea.value.trim();
        const selectedBranch = document.getElementById('br_id').value;
        const selectedTarget = document.querySelector('input[name="target_type"]:checked').value;
        
        if (!message) {
            alert('메시지를 입력해주세요.');
            return;
        }
        
        if (!selectedBranch) {
            alert('지점을 선택해주세요.');
            return;
        }
        
        // 텍스트 미리보기
        previewText.innerHTML = message.replace(/\n/g, '<br>');
        
        // 이미지 미리보기
        const file = imageFile.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.innerHTML = '<img src="' + e.target.result + '" alt="첨부 이미지" class="preview-image">';
            };
            reader.readAsDataURL(file);
        } else {
            previewImage.innerHTML = '';
        }
        
        // 미리보기 표시
        messagePreview.style.display = 'block';
        messagePreview.scrollIntoView({ behavior: 'smooth' });
    });
    
    // 이미지 파일 변경 시 미리보기 업데이트
    imageFile.addEventListener('change', function() {
        if (messagePreview.style.display === 'block') {
            previewBtn.click();
        }
    });
    
    // 폼 제출 시 확인
    sendForm.addEventListener('submit', function(e) {
        const message = messageTextarea.value.trim();
        const selectedBranch = document.getElementById('br_id').value;
        const selectedTarget = document.querySelector('input[name="target_type"]:checked').value;
        const confirmSend = document.getElementById('confirm_send').checked;
        
        if (!message) {
            alert('메시지를 입력해주세요.');
            e.preventDefault();
            return;
        }
        
        if (!selectedBranch) {
            alert('지점을 선택해주세요.');
            e.preventDefault();
            return;
        }
        
        if (!confirmSend) {
            alert('발송 동의 체크박스를 선택해주세요.');
            e.preventDefault();
            return;
        }
        
        // 운영용 채널 발송 시 추가 확인
        if (selectedTarget === 'live') {
            if (!confirm('실제 운영용 채널에 메시지를 발송하시겠습니까?\n발송 후에는 취소할 수 없습니다.')) {
                e.preventDefault();
                return;
            }
        }
        
        // 발송 버튼 비활성화 (중복 클릭 방지)
        sendBtn.disabled = true;
        sendBtn.textContent = '발송 중...';
    });
    
    // 이미지 파일 크기 체크
    imageFile.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('이미지 파일 크기는 5MB를 초과할 수 없습니다.');
                this.value = '';
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('JPG, PNG, GIF 파일만 업로드 가능합니다.');
                this.value = '';
                return;
            }
        }
    });
    
    // 초기 설정
    updateCharCount();
    updateTargetInfo();
});
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>