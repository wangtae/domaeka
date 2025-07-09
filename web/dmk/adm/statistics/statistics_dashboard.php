<?php
/**
 * 도매까 통계 대시보드
 * 지점별 매출 통계 및 분석 기능
 */

$sub_menu = '190400';
include_once('../../../adm/_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 지점 관리자 mb_type 상수 정의


// 메뉴 접근 권한 확인
$current_user_type = dmk_get_current_user_type();
if (!dmk_can_access_menu($sub_menu, $current_user_type)) {
    alert('접근 권한이 없습니다.');
}

$g5['title'] = '통계 대시보드 (프로토타입으로 실제 내용 구현해야 함)';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 날짜 범위 설정
$start_date = isset($_GET['start_date']) ? clean_xss_tags($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_xss_tags($_GET['end_date']) : date('Y-m-t');
$period_type = isset($_GET['period_type']) ? clean_xss_tags($_GET['period_type']) : 'daily';

// 계층별 필터링을 위한 GET 파라미터 처리
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 도매까 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

// 권한에 따른 데이터 필터링
$branch_filter = '';

// 기본 권한별 필터링
if ($dmk_auth && !$dmk_auth['is_super']) {
    switch ($dmk_auth['mb_type']) {
        case 1: // 총판 - 자신의 총판에 속한 지점만
            if (!empty($dmk_auth['dt_id'])) {
                $branch_filter = " AND b.br_id IN (
                    SELECT DISTINCT br.br_id FROM dmk_branch br
                    JOIN dmk_agency ag ON br.ag_id = ag.ag_id 
                    WHERE ag.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."'
                )";
            }
            break;
        case 2: // 대리점 - 소속 지점만 조회 가능
            if (!empty($dmk_auth['ag_id'])) {
                $branch_filter = " AND b.br_id IN (
                    SELECT DISTINCT br_id FROM dmk_branch 
                    WHERE ag_id = '".sql_escape_string($dmk_auth['ag_id'])."'
                )";
            }
            break;
        case 3: // 지점 - 자신의 지점만 조회 가능
            if (!empty($dmk_auth['br_id'])) {
                $branch_filter = " AND b.br_id = '" . sql_escape_string($dmk_auth['br_id']) . "'";
            }
            break;
    }
}

// 계층별 필터링 추가 (GET 파라미터 기반)
if ($filter_dt_id) {
    $branch_filter .= " AND b.br_id IN (
        SELECT DISTINCT br.br_id FROM dmk_branch br
        JOIN dmk_agency ag ON br.ag_id = ag.ag_id 
        WHERE ag.dt_id = '".sql_escape_string($filter_dt_id)."'
    )";
}
if ($filter_ag_id) {
    $branch_filter .= " AND b.br_id IN (
        SELECT DISTINCT br_id FROM dmk_branch 
        WHERE ag_id = '".sql_escape_string($filter_ag_id)."'
    )";
}
if ($filter_br_id) {
    $branch_filter .= " AND b.br_id = '".sql_escape_string($filter_br_id)."'";
}

// 전체 통계 요약
$summary_sql = "
    SELECT 
        COUNT(DISTINCT o.od_id) as total_orders,
        SUM(o.od_cart_price + o.od_send_cost + o.od_send_cost2) as total_revenue,
        COUNT(DISTINCT c.dmk_br_id) as active_branches,
        AVG(o.od_cart_price + o.od_send_cost + o.od_send_cost2) as avg_order_value
    FROM {$g5['g5_shop_order_table']} o
    INNER JOIN {$g5['g5_shop_cart_table']} c ON o.od_id = c.od_id
    INNER JOIN dmk_branch b ON c.dmk_br_id = b.br_id
    WHERE o.od_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    AND o.od_status NOT IN ('주문취소', '환불')
    $branch_filter
";

$summary_result = sql_query($summary_sql);
$summary_row = sql_fetch_array($summary_result);

// 기본값으로 초기화
$summary = array(
    'total_orders' => 0,
    'total_revenue' => 0,
    'active_branches' => 0,
    'avg_order_value' => 0
);

// 쿼리 결과가 있으면 값 업데이트
if ($summary_row) {
    $summary['total_orders'] = intval($summary_row['total_orders'] ?: 0);
    $summary['total_revenue'] = intval($summary_row['total_revenue'] ?: 0);
    $summary['active_branches'] = intval($summary_row['active_branches'] ?: 0);
    $summary['avg_order_value'] = intval($summary_row['avg_order_value'] ?: 0);
}

// 상위 지점 조회
$top_branches_sql = "
    SELECT 
        b.br_id,
        b.br_name,
        a.ag_name,
        COUNT(DISTINCT o.od_id) as order_count,
        SUM(o.od_cart_price + o.od_send_cost + o.od_send_cost2) as total_sales
    FROM {$g5['g5_shop_order_table']} o
    INNER JOIN {$g5['g5_shop_cart_table']} c ON o.od_id = c.od_id
    INNER JOIN dmk_branch b ON c.dmk_br_id = b.br_id
    LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id
    WHERE o.od_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    AND o.od_status NOT IN ('주문취소', '환불')
    $branch_filter
    GROUP BY b.br_id
    ORDER BY total_sales DESC
    LIMIT 10
";

$top_branches_result = sql_query($top_branches_sql);
?>

<style>
.stats-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.stats-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-header h1 {
    margin: 0;
    font-size: 2.2em;
    font-weight: 300;
}

.stats-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.filter-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-weight: 600;
    color: #333;
    font-size: 0.9em;
}

.filter-group input, .filter-group select {
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.4;
    height: 48px;
    min-height: 48px;
    box-sizing: border-box;
    transition: all 0.3s ease;
    vertical-align: top;
}

/* chained-select 스타일 조정 */
.dmk-chain-select-container {
    width: 100%;
    margin-bottom: 15px;
}

.dmk-chain-select-container select {
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    height: 48px;
    min-height: 48px;
    box-sizing: border-box;
    transition: all 0.3s ease;
    margin-right: 10px;
}

.dmk-chain-select-container select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.dmk-hierarchy-info {
    background: #f8f9fa;
    padding: 12px 15px;
    border-radius: 8px;
    color: #495057;
    font-weight: 500;
    border: 2px solid #e9ecef;
}

.filter-group input:focus, .filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-group select {
    appearance: none;
    background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns='https://www.w3.org/2000/svg' viewBox='0 0 4 5'><path fill='%23666' d='M2 0L0 2h4zm0 5L0 3h4z'/></svg>");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
    padding-right: 35px;
}

.btn-search {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    height: fit-content;
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
}

.summary-card .icon {
    font-size: 2.5em;
    margin-bottom: 15px;
    color: #667eea;
}

.summary-card .value {
    font-size: 2em;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.summary-card .label {
    color: #666;
    font-size: 0.9em;
    font-weight: 500;
}

.table-section {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
    font-size: 1.2em;
    font-weight: 600;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
}

.stats-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e9ecef;
}

.stats-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.3s ease;
}

.stats-table tr:hover td {
    background-color: #f8f9fa;
}

.rank-badge {
    display: inline-block;
    width: 25px;
    height: 25px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 25px;
    font-weight: bold;
    font-size: 0.8em;
}

.amount {
    font-weight: 600;
    color: #28a745;
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="stats-container">
    <!-- 헤더 섹션 -->
    <div class="stats-header">
        <h1><i class="fa fa-chart-line"></i> 도매까 통계 대시보드</h1>
        <p>지점별 매출 현황 및 성과 분석</p>
    </div>

    <!-- 필터 섹션 -->
    <div class="filter-section">
        <form method="get" class="filter-form" id="statisticsForm">
            
            <!-- 도매까 계층 선택박스 -->
            <div class="filter-group" style="width: 100%; margin-bottom: 20px;">
                <?php
                // 도매까 체인 선택박스 포함 (권한에 따라 표시)
                if ($dmk_auth['is_super'] || $dmk_auth['mb_type'] == 1) {
                    include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                    
                    // 현재 선택된 계층 값들 (권한에 따라 자동 설정)
                    $current_dt_id = $filter_dt_id;
                    $current_ag_id = $filter_ag_id;
                    $current_br_id = $filter_br_id;
                    
                    // 권한에 따른 페이지 타입 결정
                    $page_type = DMK_CHAIN_SELECT_FULL;
                    if ($dmk_auth['mb_type'] == 1) {
                        $page_type = DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY;
                        // 총판 관리자는 자신의 총판으로 고정
                        $current_dt_id = $dmk_auth['dt_id'];
                    }
                    
                    echo dmk_render_chain_select([
                        'page_type' => $page_type,
                        'auto_submit' => false,
                        'form_id' => 'statisticsForm',
                        'field_names' => [
                            'distributor' => 'sdt_id',
                            'agency' => 'sag_id', 
                            'branch' => 'sbr_id'
                        ],
                        'current_values' => [
                            'sdt_id' => $current_dt_id,
                            'sag_id' => $current_ag_id,
                            'sbr_id' => $current_br_id
                        ],
                        'placeholders' => [
                            'distributor' => '전체 총판',
                            'agency' => '전체 대리점',
                            'branch' => '전체 지점'
                        ]
                    ]);
                } else if ($dmk_auth['mb_type'] == 2) {
                    // 대리점 관리자는 소속 지점만 선택 가능
                    include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
                    
                    echo dmk_render_chain_select([
                        'page_type' => DMK_CHAIN_SELECT_FULL,
                        'auto_submit' => false,
                        'form_id' => 'statisticsForm',
                        'field_names' => [
                            'distributor' => 'sdt_id',
                            'agency' => 'sag_id', 
                            'branch' => 'sbr_id'
                        ],
                        'current_values' => [
                            'sdt_id' => $dmk_auth['dt_id'],
                            'sag_id' => $dmk_auth['ag_id'],
                            'sbr_id' => $filter_br_id
                        ],
                        'placeholders' => [
                            'distributor' => '전체 총판',
                            'agency' => '전체 대리점',
                            'branch' => '전체 지점'
                        ]
                    ]);
                } else if ($dmk_auth['mb_type'] == 3) {
                    // 지점 관리자는 자신의 지점만 표시 (선택박스 없음)
                    echo '<div class="dmk-chain-select-container">';
                    echo '<span class="dmk-hierarchy-info">현재 조회 범위: ' . htmlspecialchars($dmk_auth['br_name'] ?? '해당 지점') . ' 지점</span>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="filter-group">
                <label for="start_date">시작일</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            
            <div class="filter-group">
                <label for="end_date">종료일</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            
            <div class="filter-group">
                <label for="period_type">기간 유형</label>
                <select id="period_type" name="period_type">
                    <option value="daily" <?php echo $period_type == 'daily' ? 'selected' : ''; ?>>일별</option>
                    <option value="monthly" <?php echo $period_type == 'monthly' ? 'selected' : ''; ?>>월별</option>
                    <option value="yearly" <?php echo $period_type == 'yearly' ? 'selected' : ''; ?>>연별</option>
                </select>
            </div>
            
            <button type="submit" class="btn-search">
                <i class="fa fa-search"></i> 조회
            </button>
        </form>
    </div>

    <!-- 요약 통계 카드 -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="icon"><i class="fa fa-shopping-cart"></i></div>
            <div class="value"><?php echo number_format(isset($summary['total_orders']) ? $summary['total_orders'] : 0); ?></div>
            <div class="label">총 주문 건수</div>
        </div>
        
        <div class="summary-card">
            <div class="icon"><i class="fa fa-won-sign"></i></div>
            <div class="value"><?php echo number_format(isset($summary['total_revenue']) ? $summary['total_revenue'] : 0); ?>원</div>
            <div class="label">총 매출액</div>
        </div>
        
        <div class="summary-card">
            <div class="icon"><i class="fa fa-store"></i></div>
            <div class="value"><?php echo number_format(isset($summary['active_branches']) ? $summary['active_branches'] : 0); ?></div>
            <div class="label">활성 지점 수</div>
        </div>
        
        <div class="summary-card">
            <div class="icon"><i class="fa fa-calculator"></i></div>
            <div class="value"><?php echo number_format(isset($summary['avg_order_value']) ? $summary['avg_order_value'] : 0); ?>원</div>
            <div class="label">평균 주문액</div>
        </div>
    </div>

    <!-- 상위 지점 순위 테이블 -->
    <div class="table-section">
        <div class="table-header">
            <i class="fa fa-trophy"></i> 상위 성과 지점 (TOP 10)
        </div>
        
        <table class="stats-table">
            <thead>
                <tr>
                    <th>순위</th>
                    <th>지점명</th>
                    <th>소속 대리점</th>
                    <th>주문 건수</th>
                    <th>총 매출액</th>
                    <th>평균 주문액</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                while ($row = sql_fetch_array($top_branches_result)) {
                    $avg_order = $row['order_count'] > 0 ? $row['total_sales'] / $row['order_count'] : 0;
                ?>
                <tr>
                    <td>
                        <span class="rank-badge"><?php echo $rank; ?></span>
                    </td>
                    <td><strong><?php echo htmlspecialchars($row['br_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['ag_name'] ?: '직영'); ?></td>
                    <td><?php echo number_format($row['order_count']); ?>건</td>
                    <td class="amount"><?php echo number_format($row['total_sales']); ?>원</td>
                    <td><?php echo number_format($avg_order); ?>원</td>
                </tr>
                <?php 
                    $rank++;
                } 
                if ($rank == 1) {
                    echo '<tr><td colspan="6" style="text-align: center; padding: 50px; color: #666;">조회된 데이터가 없습니다.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?> 