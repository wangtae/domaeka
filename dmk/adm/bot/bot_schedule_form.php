<?php
/**
 * 스케줄 등록/수정 폼 페이지
 * 
 * 스케줄링 발송을 위한 스케줄을 등록하거나 수정할 수 있는 폼을 제공합니다.
 * 매일, 요일별, 1회성 발송 옵션을 지원합니다.
 */

require_once '../../../adm/_common.php';
require_once G5_DMK_PATH . '/adm/lib/common.lib.php';
require_once G5_DMK_PATH . '/adm/bot/lib/bot.lib.php';

// 관리자 권한 확인
$auth = dmk_get_admin_auth();

// 스케줄링 기능 권한 확인
if (!kb_check_function_permission('schedule', $auth)) {
    alert('스케줄링 발송 관리 권한이 없습니다.');
    exit;
}

// 수정 모드인지 확인
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = $schedule_id > 0;

// 기본값 설정
$schedule = [
    'id' => 0,
    'title' => '',
    'message' => '',
    'image_url' => '',
    'br_id' => '',
    'schedule_type' => 'daily',
    'schedule_time' => '',
    'schedule_date' => '',
    'schedule_weekdays' => '',
    'target_type' => 'live',
    'status' => 'active'
];

// 수정 모드일 경우 기존 데이터 조회
if ($is_edit) {
    $schedule_sql = "SELECT * FROM " . KB_TABLE_PREFIX . "schedule WHERE id = " . $schedule_id;
    $schedule_result = sql_query($schedule_sql);
    
    if ($schedule_result && sql_num_rows($schedule_result) > 0) {
        $existing_schedule = sql_fetch_array($schedule_result);
        
        // 권한 확인
        if (!kb_check_branch_permission($existing_schedule['br_id'], $auth)) {
            alert('해당 스케줄을 수정할 권한이 없습니다.');
            exit;
        }
        
        $schedule = array_merge($schedule, $existing_schedule);
    } else {
        alert('스케줄을 찾을 수 없습니다.');
        exit;
    }
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

// 지점 관리자의 경우 자동으로 지점 설정
if ($auth['mb_type'] === 'branch' && !$is_edit) {
    $schedule['br_id'] = $auth['br_id'];
}

// 페이지 제목 및 헤더
$g5['title'] = $is_edit ? '스케줄 수정' : '스케줄 등록';
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

.form-group-inline {
    display: flex;
    gap: 15px;
}

.form-group-inline .form-group {
    flex: 1;
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

.weekday-selector {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.weekday-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.schedule-type-options {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #e9ecef;
    border-radius: 4px;
}

.schedule-type-options.active {
    display: block;
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

.image-preview {
    max-width: 200px;
    max-height: 200px;
    margin-top: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01">
        <span class="ov_txt"><?php echo $is_edit ? '스케줄 수정' : '스케줄 등록'; ?></span>
    </span>
    <span class="btn_ov01">
        <a href="bot_schedule_list.php" class="ov_listall">목록으로</a>
    </span>
</div>

<div class="form-container">
    <form method="post" action="bot_schedule_form_update.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $schedule['id']; ?>">
        
        <!-- 기본 정보 -->
        <div class="form-group">
            <label for="title">제목 *</label>
            <input type="text" name="title" id="title" 
                   value="<?php echo htmlspecialchars($schedule['title']); ?>" 
                   required maxlength="100">
            <div class="help-text">스케줄을 구분하기 위한 제목을 입력하세요.</div>
        </div>
        
        <div class="form-group">
            <label for="message">메시지 내용 *</label>
            <textarea name="message" id="message" required maxlength="1000"><?php echo htmlspecialchars($schedule['message']); ?></textarea>
            <div class="help-text">카카오톡으로 발송될 메시지 내용을 입력하세요. (최대 1000자)</div>
        </div>
        
        <div class="form-group">
            <label for="image_file">이미지 첨부</label>
            <input type="file" name="image_file" id="image_file" accept="image/*">
            <?php if ($schedule['image_url']): ?>
                <div style="margin-top: 10px;">
                    <img src="<?php echo $schedule['image_url']; ?>" alt="첨부 이미지" class="image-preview">
                    <div class="help-text">현재 첨부된 이미지입니다. 새 이미지를 선택하면 기존 이미지가 교체됩니다.</div>
                </div>
            <?php endif; ?>
            <div class="help-text">JPG, PNG, GIF 파일만 업로드 가능합니다. (최대 5MB)</div>
        </div>
        
        <!-- 지점 선택 -->
        <div class="form-group">
            <label for="br_id">지점 선택 *</label>
            <select name="br_id" id="br_id" required <?php echo $auth['mb_type'] === 'branch' ? 'disabled' : ''; ?>>
                <option value="">지점을 선택하세요</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['br_id']; ?>" 
                            <?php echo $schedule['br_id'] === $branch['br_id'] ? 'selected' : ''; ?>>
                        <?php echo $branch['br_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($auth['mb_type'] === 'branch'): ?>
                <input type="hidden" name="br_id" value="<?php echo $schedule['br_id']; ?>">
            <?php endif; ?>
        </div>
        
        <!-- 발송 대상 -->
        <div class="form-group">
            <label>발송 대상</label>
            <div class="radio-group">
                <div class="radio-item">
                    <input type="radio" name="target_type" id="target_live" value="live" 
                           <?php echo $schedule['target_type'] === 'live' ? 'checked' : ''; ?>>
                    <label for="target_live">운영용 채널</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="target_type" id="target_test" value="test" 
                           <?php echo $schedule['target_type'] === 'test' ? 'checked' : ''; ?>>
                    <label for="target_test">테스트용 채널</label>
                </div>
            </div>
        </div>
        
        <!-- 발송 유형 -->
        <div class="form-group">
            <label>발송 유형</label>
            <div class="radio-group">
                <div class="radio-item">
                    <input type="radio" name="schedule_type" id="type_daily" value="daily" 
                           <?php echo $schedule['schedule_type'] === 'daily' ? 'checked' : ''; ?>>
                    <label for="type_daily">매일 발송</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="schedule_type" id="type_weekly" value="weekly" 
                           <?php echo $schedule['schedule_type'] === 'weekly' ? 'checked' : ''; ?>>
                    <label for="type_weekly">요일별 발송</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="schedule_type" id="type_once" value="once" 
                           <?php echo $schedule['schedule_type'] === 'once' ? 'checked' : ''; ?>>
                    <label for="type_once">1회성 발송</label>
                </div>
            </div>
        </div>
        
        <!-- 매일 발송 옵션 -->
        <div id="daily_options" class="schedule-type-options">
            <label for="daily_time">발송 시간</label>
            <input type="time" name="daily_time" id="daily_time" 
                   value="<?php echo $schedule['schedule_type'] === 'daily' ? $schedule['schedule_time'] : ''; ?>">
            <div class="help-text">매일 지정된 시간에 발송됩니다.</div>
        </div>
        
        <!-- 요일별 발송 옵션 -->
        <div id="weekly_options" class="schedule-type-options">
            <label for="weekly_time">발송 시간</label>
            <input type="time" name="weekly_time" id="weekly_time" 
                   value="<?php echo $schedule['schedule_type'] === 'weekly' ? $schedule['schedule_time'] : ''; ?>">
            
            <label style="margin-top: 15px;">발송 요일</label>
            <div class="weekday-selector">
                <?php
                $weekdays = ['월', '화', '수', '목', '금', '토', '일'];
                $selected_weekdays = $schedule['schedule_type'] === 'weekly' ? explode(',', $schedule['schedule_weekdays']) : [];
                
                foreach ($weekdays as $index => $weekday) {
                    $checked = in_array($weekday, $selected_weekdays) ? 'checked' : '';
                    echo '<div class="weekday-item">';
                    echo '<input type="checkbox" name="weekdays[]" id="weekday_' . $index . '" value="' . $weekday . '" ' . $checked . '>';
                    echo '<label for="weekday_' . $index . '">' . $weekday . '</label>';
                    echo '</div>';
                }
                ?>
            </div>
            <div class="help-text">선택한 요일의 지정된 시간에 발송됩니다.</div>
        </div>
        
        <!-- 1회성 발송 옵션 -->
        <div id="once_options" class="schedule-type-options">
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="once_date">발송 날짜</label>
                    <input type="date" name="once_date" id="once_date" 
                           value="<?php echo $schedule['schedule_type'] === 'once' ? $schedule['schedule_date'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="once_time">발송 시간</label>
                    <input type="time" name="once_time" id="once_time" 
                           value="<?php echo $schedule['schedule_type'] === 'once' ? $schedule['schedule_time'] : ''; ?>">
                </div>
            </div>
            <div class="help-text">지정된 날짜와 시간에 1회만 발송됩니다.</div>
        </div>
        
        <!-- 상태 -->
        <div class="form-group">
            <label for="status">상태</label>
            <select name="status" id="status">
                <option value="active" <?php echo $schedule['status'] === 'active' ? 'selected' : ''; ?>>활성</option>
                <option value="inactive" <?php echo $schedule['status'] === 'inactive' ? 'selected' : ''; ?>>비활성</option>
            </select>
            <div class="help-text">비활성 상태에서는 스케줄이 실행되지 않습니다.</div>
        </div>
        
        <!-- 버튼 -->
        <div class="button-group">
            <button type="submit" class="btn btn_02">
                <?php echo $is_edit ? '수정하기' : '등록하기'; ?>
            </button>
            <a href="bot_schedule_list.php" class="btn btn_03">취소</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 발송 유형에 따른 옵션 표시/숨김
    const scheduleTypeRadios = document.querySelectorAll('input[name="schedule_type"]');
    const optionDivs = {
        'daily': document.getElementById('daily_options'),
        'weekly': document.getElementById('weekly_options'),
        'once': document.getElementById('once_options')
    };
    
    function updateScheduleOptions() {
        const selectedType = document.querySelector('input[name="schedule_type"]:checked').value;
        
        // 모든 옵션 숨김
        Object.values(optionDivs).forEach(div => {
            div.classList.remove('active');
        });
        
        // 선택된 옵션 표시
        if (optionDivs[selectedType]) {
            optionDivs[selectedType].classList.add('active');
        }
    }
    
    // 초기 상태 설정
    updateScheduleOptions();
    
    // 라디오 버튼 변경 이벤트
    scheduleTypeRadios.forEach(radio => {
        radio.addEventListener('change', updateScheduleOptions);
    });
    
    // 폼 제출 시 유효성 검사
    document.querySelector('form').addEventListener('submit', function(e) {
        const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
        
        if (scheduleType === 'daily') {
            if (!document.getElementById('daily_time').value) {
                alert('발송 시간을 입력해주세요.');
                e.preventDefault();
                return;
            }
        } else if (scheduleType === 'weekly') {
            if (!document.getElementById('weekly_time').value) {
                alert('발송 시간을 입력해주세요.');
                e.preventDefault();
                return;
            }
            
            const selectedWeekdays = document.querySelectorAll('input[name="weekdays[]"]:checked');
            if (selectedWeekdays.length === 0) {
                alert('발송 요일을 선택해주세요.');
                e.preventDefault();
                return;
            }
        } else if (scheduleType === 'once') {
            if (!document.getElementById('once_date').value) {
                alert('발송 날짜를 입력해주세요.');
                e.preventDefault();
                return;
            }
            
            if (!document.getElementById('once_time').value) {
                alert('발송 시간을 입력해주세요.');
                e.preventDefault();
                return;
            }
            
            // 과거 날짜 체크
            const selectedDate = new Date(document.getElementById('once_date').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('과거 날짜는 선택할 수 없습니다.');
                e.preventDefault();
                return;
            }
        }
    });
    
    // 이미지 미리보기
    document.getElementById('image_file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let preview = document.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'image-preview';
                    e.target.parentNode.appendChild(preview);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>