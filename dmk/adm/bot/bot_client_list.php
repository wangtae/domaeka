<?php
/**
 * 클라이언트 봇 관리 페이지
 * 
 * 실제 지점별 톡방에 참여하여 활동하는 클라이언트 봇의 상태를 관리하고 제어하는 본사 전용 메뉴입니다.
 * - 클라이언트 봇 목록 조회
 * - 봇 상태 관리 (활성/비활성)
 * - 봇 연결 상태 모니터링
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

// POST 요청 처리 (봇 상태 변경)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $client_id = intval($_POST['client_id']);
    
    if ($action === 'toggle_status') {
        $new_status = $_POST['new_status'];
        $result = kb_update_client_status($client_id, $new_status);
        
        if ($result) {
            alert('봇 상태가 변경되었습니다.', $_SERVER['PHP_SELF']);
        } else {
            alert('봇 상태 변경에 실패했습니다.');
        }
    }
    exit;
}

// 검색 조건 처리
$search_br_id = isset($_GET['search_br_id']) ? trim($_GET['search_br_id']) : '';
$search_status = isset($_GET['search_status']) ? trim($_GET['search_status']) : '';

// 필터 조건 설정
$filters = [];
if ($search_br_id) {
    $filters['br_id'] = $search_br_id;
}
if ($search_status) {
    $filters['status'] = $search_status;
}

// 클라이언트 봇 목록 조회
$clients = kb_get_client_list($filters);

// 지점 목록 조회 (검색용)
$branch_sql = "SELECT br_id, br_name FROM dmk_branch WHERE br_status = 1 ORDER BY br_name";
$branch_result = sql_query($branch_sql);
$branches = [];
while ($branch_row = sql_fetch_array($branch_result)) {
    $branches[] = $branch_row;
}

// 페이지 제목 및 헤더
$g5['title'] = '클라이언트 봇 관리';
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
.status-error { background: #fff3cd; color: #856404; }

.btn-toggle {
    padding: 4px 8px;
    font-size: 12px;
    margin-left: 5px;
}

.client-info {
    font-size: 14px;
    color: #6c757d;
}

.last-activity {
    font-size: 12px;
    color: #6c757d;
}
</style>';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">클라이언트 봇 관리</span></span>
</div>

<div class="local_desc01 local_desc">
    <p>각 지점의 카카오톡 채널에 참여하는 클라이언트 봇의 상태를 관리할 수 있습니다.</p>
</div>

<!-- 검색 폼 -->
<div class="search-form">
    <form method="get">
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
        
        <div class="form-group">
            <label for="search_status">상태 선택</label>
            <select name="search_status" id="search_status">
                <option value="">전체 상태</option>
                <option value="active" <?php echo $search_status === 'active' ? 'selected' : ''; ?>>활성</option>
                <option value="inactive" <?php echo $search_status === 'inactive' ? 'selected' : ''; ?>>비활성</option>
                <option value="error" <?php echo $search_status === 'error' ? 'selected' : ''; ?>>오류</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn_02">검색</button>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn_03">전체보기</a>
        </div>
    </form>
</div>

<!-- 클라이언트 봇 목록 -->
<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>클라이언트 봇 목록</caption>
        <thead>
            <tr>
                <th scope="col">봇 ID</th>
                <th scope="col">지점 정보</th>
                <th scope="col">봇 이름</th>
                <th scope="col">채널 정보</th>
                <th scope="col">상태</th>
                <th scope="col">마지막 활동</th>
                <th scope="col">등록일</th>
                <th scope="col">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="8" class="empty_table">등록된 클라이언트 봇이 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?php echo $client['id']; ?></td>
                        <td>
                            <div><strong><?php echo $client['br_id']; ?></strong></div>
                            <div class="client-info">
                                <?php
                                // 지점 이름 조회
                                $br_name_sql = "SELECT br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($client['br_id']) . "'";
                                $br_name_result = sql_query($br_name_sql);
                                $br_name = $br_name_result ? sql_fetch_array($br_name_result)['br_name'] : '알 수 없음';
                                echo $br_name;
                                ?>
                            </div>
                        </td>
                        <td>
                            <div><strong><?php echo $client['bot_name']; ?></strong></div>
                            <div class="client-info"><?php echo $client['bot_description'] ?? ''; ?></div>
                        </td>
                        <td>
                            <div><strong><?php echo $client['channel_name']; ?></strong></div>
                            <div class="client-info">
                                <?php echo $client['channel_type'] === 'live' ? '운영용' : '테스트용'; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch ($client['status']) {
                                case 'active':
                                    $status_class = 'status-active';
                                    $status_text = '활성';
                                    break;
                                case 'inactive':
                                    $status_class = 'status-inactive';
                                    $status_text = '비활성';
                                    break;
                                case 'error':
                                    $status_class = 'status-error';
                                    $status_text = '오류';
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
                        <td>
                            <div class="last-activity">
                                <?php echo $client['last_activity'] ? date('Y-m-d H:i', strtotime($client['last_activity'])) : 'N/A'; ?>
                            </div>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($client['created_at'])); ?></td>
                        <td>
                            <?php if ($client['status'] === 'active'): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                    <input type="hidden" name="new_status" value="inactive">
                                    <button type="submit" class="btn btn_02 btn-toggle" 
                                            onclick="return confirm('이 봇을 비활성화하시겠습니까?')">
                                        비활성화
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                    <input type="hidden" name="new_status" value="active">
                                    <button type="submit" class="btn btn_03 btn-toggle" 
                                            onclick="return confirm('이 봇을 활성화하시겠습니까?')">
                                        활성화
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 통계 정보 -->
<?php
$total_clients = count($clients);
$active_clients = count(array_filter($clients, function($client) { return $client['status'] === 'active'; }));
$inactive_clients = count(array_filter($clients, function($client) { return $client['status'] === 'inactive'; }));
$error_clients = count(array_filter($clients, function($client) { return $client['status'] === 'error'; }));
?>

<div class="local_desc01 local_desc">
    <h3>클라이언트 봇 현황</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #495057;"><?php echo $total_clients; ?></div>
            <div style="font-size: 14px; color: #6c757d;">전체 봇</div>
        </div>
        <div style="background: #d4edda; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #155724;"><?php echo $active_clients; ?></div>
            <div style="font-size: 14px; color: #155724;">활성 봇</div>
        </div>
        <div style="background: #f8d7da; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #721c24;"><?php echo $inactive_clients; ?></div>
            <div style="font-size: 14px; color: #721c24;">비활성 봇</div>
        </div>
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #856404;"><?php echo $error_clients; ?></div>
            <div style="font-size: 14px; color: #856404;">오류 봇</div>
        </div>
    </div>
</div>

<div class="local_desc01 local_desc">
    <h3>클라이언트 봇 관리 안내</h3>
    <ul>
        <li><strong>활성:</strong> 정상적으로 작동 중인 봇</li>
        <li><strong>비활성:</strong> 일시적으로 중지된 봇</li>
        <li><strong>오류:</strong> 연결 오류 또는 기타 문제가 있는 봇</li>
    </ul>
    
    <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border: 1px solid #b8daff; border-radius: 5px;">
        <strong>참고사항:</strong>
        <ul style="margin-top: 10px;">
            <li>봇 상태 변경은 즉시 적용됩니다.</li>
            <li>비활성화된 봇은 메시지 발송 및 채팅 참여가 중단됩니다.</li>
            <li>오류 상태의 봇은 자동으로 재연결을 시도합니다.</li>
        </ul>
    </div>
</div>

<script>
// 자동 새로고침 (60초마다)
setInterval(function() {
    location.reload();
}, 60000);
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>