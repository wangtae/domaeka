<?php
/**
 * 스케줄링 발송 목록 관리 페이지
 * 
 * 등록된 스케줄 목록을 조회, 검색, 삭제할 수 있습니다.
 * 계층별 권한에 따라 접근 가능한 데이터가 제한됩니다.
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

// 스케줄 삭제 처리
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $schedule_id = intval($_POST['schedule_id']);
    
    // 스케줄 정보 조회 (권한 확인용)
    $schedule_sql = "SELECT * FROM " . KB_TABLE_PREFIX . "schedule WHERE id = " . $schedule_id;
    $schedule_result = sql_query($schedule_sql);
    
    if ($schedule_result && sql_num_rows($schedule_result) > 0) {
        $schedule = sql_fetch_array($schedule_result);
        
        // 지점 권한 확인
        if (kb_check_branch_permission($schedule['br_id'], $auth)) {
            if (kb_delete_schedule($schedule_id)) {
                alert('스케줄이 삭제되었습니다.', $_SERVER['PHP_SELF']);
            } else {
                alert('스케줄 삭제에 실패했습니다.');
            }
        } else {
            alert('해당 스케줄을 삭제할 권한이 없습니다.');
        }
    } else {
        alert('스케줄을 찾을 수 없습니다.');
    }
    exit;
}

// 검색 조건 처리
$search_br_id = isset($_GET['search_br_id']) ? trim($_GET['search_br_id']) : '';
$search_status = isset($_GET['search_status']) ? trim($_GET['search_status']) : '';
$search_keyword = isset($_GET['search_keyword']) ? trim($_GET['search_keyword']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 필터 조건 설정
$filters = [];

// 권한별 지점 필터링
if (!$auth['is_super']) {
    switch ($auth['mb_type']) {
        case 'branch':
            $filters['br_id'] = $auth['br_id'];
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
    $filters['br_id'] = $search_br_id;
}
if ($search_status) {
    $filters['status'] = $search_status;
}
if ($search_keyword) {
    $filters['search'] = $search_keyword;
}

// 스케줄 목록 조회
$schedule_data = kb_get_schedule_list($filters, $page, 20);
$schedules = $schedule_data['schedules'];
$total = $schedule_data['total'];
$total_pages = $schedule_data['total_pages'];

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
$g5['title'] = '스케줄링 발송 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 추가 CSS
echo '<style>
.search-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-form .form-group {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 10px;
}

.search-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.search-form select, .search-form input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 200px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    min-width: 60px;
    display: inline-block;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-completed { background: #cce5ff; color: #004085; }

.schedule-type {
    font-size: 12px;
    color: #6c757d;
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    margin-right: 5px;
}

.schedule-info {
    font-size: 14px;
    color: #495057;
    margin-top: 5px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    margin-right: 5px;
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
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">스케줄링 발송 관리</span></span>
    <span class="btn_ov01">
        <a href="bot_schedule_form.php" class="ov_listall">스케줄 등록</a>
    </span>
</div>

<div class="local_desc01 local_desc">
    <p>등록된 스케줄링 발송 목록을 조회하고 관리할 수 있습니다.</p>
</div>

<!-- 검색 폼 -->
<div class="search-form">
    <form method="get">
        <?php if ($auth['is_super'] || $auth['mb_type'] !== 'branch'): ?>
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
            <label for="search_status">상태 선택</label>
            <select name="search_status" id="search_status">
                <option value="">전체 상태</option>
                <option value="active" <?php echo $search_status === 'active' ? 'selected' : ''; ?>>활성</option>
                <option value="inactive" <?php echo $search_status === 'inactive' ? 'selected' : ''; ?>>비활성</option>
                <option value="completed" <?php echo $search_status === 'completed' ? 'selected' : ''; ?>>완료</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="search_keyword">검색어</label>
            <input type="text" name="search_keyword" id="search_keyword" 
                   value="<?php echo htmlspecialchars($search_keyword); ?>" 
                   placeholder="제목 또는 메시지">
        </div>
        
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn_02">검색</button>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn_03">전체보기</a>
        </div>
    </form>
</div>

<!-- 스케줄 목록 -->
<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>스케줄링 발송 목록 (전체 <?php echo number_format($total); ?>건)</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">제목</th>
                <th scope="col">지점</th>
                <th scope="col">발송 유형</th>
                <th scope="col">발송 시간</th>
                <th scope="col">상태</th>
                <th scope="col">등록일</th>
                <th scope="col">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="8" class="empty_table">등록된 스케줄이 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td><?php echo $schedule['id']; ?></td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($schedule['title']); ?></strong></div>
                            <div class="schedule-info">
                                <?php echo mb_substr(htmlspecialchars($schedule['message']), 0, 50); ?>
                                <?php if (mb_strlen($schedule['message']) > 50) echo '...'; ?>
                            </div>
                        </td>
                        <td>
                            <div><strong><?php echo $schedule['br_id']; ?></strong></div>
                            <div class="schedule-info">
                                <?php
                                // 지점 이름 조회
                                $br_name_sql = "SELECT br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($schedule['br_id']) . "'";
                                $br_name_result = sql_query($br_name_sql);
                                $br_name = $br_name_result ? sql_fetch_array($br_name_result)['br_name'] : '알 수 없음';
                                echo $br_name;
                                ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            switch ($schedule['schedule_type']) {
                                case 'daily':
                                    echo '<span class="schedule-type">매일</span>';
                                    break;
                                case 'weekly':
                                    echo '<span class="schedule-type">요일별</span>';
                                    break;
                                case 'once':
                                    echo '<span class="schedule-type">1회성</span>';
                                    break;
                                default:
                                    echo '<span class="schedule-type">알 수 없음</span>';
                            }
                            ?>
                            <div class="schedule-info">
                                <?php echo $schedule['target_type'] === 'live' ? '운영용' : '테스트용'; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            if ($schedule['schedule_type'] === 'once' && $schedule['schedule_date']) {
                                echo date('Y-m-d', strtotime($schedule['schedule_date'])) . '<br>';
                            }
                            if ($schedule['schedule_time']) {
                                echo date('H:i', strtotime($schedule['schedule_time']));
                            }
                            if ($schedule['schedule_type'] === 'weekly' && $schedule['schedule_weekdays']) {
                                echo '<br><small>' . $schedule['schedule_weekdays'] . '</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch ($schedule['status']) {
                                case 'active':
                                    $status_class = 'status-active';
                                    $status_text = '활성';
                                    break;
                                case 'inactive':
                                    $status_class = 'status-inactive';
                                    $status_text = '비활성';
                                    break;
                                case 'completed':
                                    $status_class = 'status-completed';
                                    $status_text = '완료';
                                    break;
                                default:
                                    $status_class = 'status-inactive';
                                    $status_text = '알 수 없음';
                            }
                            ?>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($schedule['created_at'])); ?></td>
                        <td>
                            <?php if (kb_check_branch_permission($schedule['br_id'], $auth)): ?>
                                <a href="bot_schedule_form.php?id=<?php echo $schedule['id']; ?>" 
                                   class="btn btn_03 btn-sm">수정</a>
                                
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                    <button type="submit" class="btn btn_02 btn-sm" 
                                            onclick="return confirm('이 스케줄을 삭제하시겠습니까?')">
                                        삭제
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="btn btn_03 btn-sm" style="opacity: 0.5;">권한 없음</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
    <h3>스케줄링 발송 안내</h3>
    <ul>
        <li><strong>매일:</strong> 지정된 시간에 매일 발송됩니다.</li>
        <li><strong>요일별:</strong> 지정된 요일과 시간에 발송됩니다.</li>
        <li><strong>1회성:</strong> 지정된 날짜와 시간에 1회만 발송됩니다.</li>
        <li><strong>운영용/테스트용:</strong> 발송 대상 채널을 구분합니다.</li>
    </ul>
</div>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>