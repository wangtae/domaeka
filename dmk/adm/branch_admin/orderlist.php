<?php
$sub_menu = "600800";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

auth_check_menu($auth, $sub_menu, 'r');

// 현재 관리자의 권한 정보 가져오기
$dmk_auth = dmk_get_admin_auth();

// 지점 선택 처리 (상위 계층 관리자용)
$selected_br_id = '';
$available_branches = array();

if (is_super_admin($member['mb_id']) || ($dmk_auth && $dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR)) {
    // 최고관리자, 총판: 모든 지점 선택 가능
    $sql = "SELECT br_id, br_name, ag_id FROM dmk_branch WHERE br_status = 1 ORDER BY br_name";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $available_branches[] = $row;
    }
} elseif ($dmk_auth && $dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
    // 대리점: 소속 지점들만 선택 가능
    $branch_ids = dmk_get_agency_branch_ids($dmk_auth['ag_id']);
    if (!empty($branch_ids)) {
        $sql = "SELECT br_id, br_name, ag_id FROM dmk_branch 
                WHERE br_id IN ('" . implode("','", array_map('sql_escape_string', $branch_ids)) . "') 
                AND br_status = 1 
                ORDER BY br_name";
        $result = sql_query($sql);
        while ($row = sql_fetch_array($result)) {
            $available_branches[] = $row;
        }
    }
} elseif ($dmk_auth && $dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
    // 지점: 자신의 지점만
    $branch_info = get_branch_info($member['dmk_br_id']);
    if ($branch_info) {
        $available_branches[] = array(
            'br_id' => $branch_info['br_id'],
            'br_name' => $branch_info['br_name'],
            'ag_id' => $branch_info['ag_id']
        );
    }
}

// 선택된 지점 ID 결정
if (isset($_GET['br_id']) && $_GET['br_id']) {
    $selected_br_id = trim($_GET['br_id']);
    // 권한 확인
    $has_permission = false;
    foreach ($available_branches as $branch) {
        if ($branch['br_id'] == $selected_br_id) {
            $has_permission = true;
            break;
        }
    }
    if (!$has_permission) {
        alert('해당 지점에 대한 권한이 없습니다.');
        goto_url($_SERVER['PHP_SELF']);
    }
} elseif (count($available_branches) == 1) {
    // 지점이 하나만 있으면 자동 선택
    $selected_br_id = $available_branches[0]['br_id'];
} elseif ($dmk_auth && $dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
    // 지점 관리자는 자신의 지점 자동 선택
    $selected_br_id = $member['dmk_br_id'];
}

// 지점이 선택되지 않았으면 선택 페이지 표시 (다중 지점 관리자용)
if (!$selected_br_id && count($available_branches) > 1) {
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>지점 선택 - 도매까</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
                <div class="text-center mb-6">
                    <i class="fas fa-list-alt text-4xl text-green-600 mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-900">지점 선택</h1>
                    <p class="text-gray-600 mt-2">주문 내역을 조회할 지점을 선택해주세요.</p>
                </div>
                
                <div class="space-y-3">
                    <?php foreach ($available_branches as $branch) { ?>
                    <a href="?br_id=<?php echo urlencode($branch['br_id']); ?>" 
                       class="block w-full p-4 bg-gray-50 hover:bg-green-50 rounded-lg border border-gray-200 hover:border-green-300 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($branch['br_name']); ?></h3>
                                <p class="text-sm text-gray-500">지점 ID: <?php echo htmlspecialchars($branch['br_id']); ?></p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </div>
                    </a>
                    <?php } ?>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="<?php echo G5_DMK_ADM_URL; ?>" class="text-green-600 hover:text-green-800">
                        <i class="fas fa-arrow-left mr-2"></i>관리자 메인으로 돌아가기
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 선택된 지점 정보 가져오기
$branch_info = get_branch_info($selected_br_id);
if (!$branch_info) {
    alert('지점 정보를 찾을 수 없습니다.');
    goto_url(G5_DMK_ADM_URL);
}

// 검색 조건
$sfl = isset($_GET['sfl']) ? trim($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? trim($_GET['stx']) : '';
$sst = isset($_GET['sst']) ? trim($_GET['sst']) : 'od_time';
$sod = isset($_GET['sod']) ? trim($_GET['sod']) : 'desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 권한 조건
$order_where_condition = dmk_get_order_where_condition($selected_br_id, $member['dmk_ag_id'], $member['dmk_dt_id']);

// 검색 조건 추가
$where_sql = " WHERE 1=1 $order_where_condition ";
if ($sfl && $stx) {
    switch ($sfl) {
        case 'od_id':
            $where_sql .= " AND od_id LIKE '%$stx%' ";
            break;
        case 'od_name':
            $where_sql .= " AND od_name LIKE '%$stx%' ";
            break;
        case 'od_hp':
            $where_sql .= " AND od_hp LIKE '%$stx%' ";
            break;
    }
}

// 전체 개수
$sql = "SELECT COUNT(*) as cnt FROM {$g5['g5_shop_order_table']} $where_sql";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 20;
$total_page = ceil($total_count / $rows);
if ($page < 1) $page = 1;
if ($page > $total_page) $page = $total_page;

$from_record = ($page - 1) * $rows;

// 주문 목록 조회
$sql = "SELECT * FROM {$g5['g5_shop_order_table']} 
        $where_sql 
        ORDER BY $sst $sod 
        LIMIT $from_record, $rows";
$result = sql_query($sql);

$g5['title'] = '주문 목록 - ' . $branch_info['br_name'];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title']; ?> - 도매까</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .dmk-label {
            display: inline-block;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 3px;
            color: white;
            text-align: center;
            min-width: 40px;
        }
        
        .dmk-label-type3 { background-color: #28a745; }
        
        .status-주문 { background-color: #007bff; color: white; }
        .status-입금 { background-color: #28a745; color: white; }
        .status-준비 { background-color: #ffc107; color: black; }
        .status-배송 { background-color: #17a2b8; color: white; }
        .status-완료 { background-color: #6c757d; color: white; }
        .status-취소 { background-color: #dc3545; color: white; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo $branch_info['br_name']; ?></h1>
                        <span class="dmk-label dmk-label-type3">지점</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="order_page.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>새 주문
                        </a>
                        <a href="<?php echo G5_DMK_ADM_URL; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            관리자
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Search Form -->
            <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                <form method="get" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-2">검색 조건</label>
                        <select name="sfl" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">전체</option>
                            <option value="od_id" <?php echo $sfl == 'od_id' ? 'selected' : ''; ?>>주문번호</option>
                            <option value="od_name" <?php echo $sfl == 'od_name' ? 'selected' : ''; ?>>주문자명</option>
                            <option value="od_hp" <?php echo $sfl == 'od_hp' ? 'selected' : ''; ?>>연락처</option>
                        </select>
                    </div>
                    <div class="flex-2 min-w-64">
                        <label class="block text-sm font-medium text-gray-700 mb-2">검색어</label>
                        <input type="text" name="stx" value="<?php echo htmlspecialchars($stx); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="검색어를 입력하세요">
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>검색
                        </button>
                        <a href="orderlist.php" class="ml-2 bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            초기화
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Summary -->
            <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        총 <span class="font-semibold text-blue-600"><?php echo number_format($total_count); ?></span>건의 주문이 있습니다.
                    </div>
                    <div class="text-sm text-gray-600">
                        <?php echo $page; ?> / <?php echo $total_page; ?> 페이지
                    </div>
                </div>
            </div>

            <!-- Order List -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <?php if ($total_count > 0) { ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">주문번호</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">주문일시</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">주문자</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">연락처</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">주문금액</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">관리</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($row = sql_fetch_array($result)) { ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $row['od_id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('Y-m-d H:i', strtotime($row['od_time'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['od_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['od_hp']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo number_format($row['od_receipt_price']); ?>원</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full status-<?php echo $row['od_status']; ?>">
                                            <?php echo $row['od_status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="order_view.php?od_id=<?php echo $row['od_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> 상세
                                        </a>
                                        <?php if ($row['od_status'] == '주문') { ?>
                                        <a href="order_cancel.php?od_id=<?php echo $row['od_id']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('정말 취소하시겠습니까?')">
                                            <i class="fas fa-times"></i> 취소
                                        </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_page > 1) { ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex justify-center">
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php
                                $start_page = max(1, $page - 5);
                                $end_page = min($total_page, $page + 5);
                                
                                if ($page > 1) {
                                    echo "<a href='?page=1&sfl=$sfl&stx=$stx' class='relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50'>처음</a>";
                                    echo "<a href='?page=" . ($page - 1) . "&sfl=$sfl&stx=$stx' class='relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50'>이전</a>";
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo "<span class='relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600'>$i</span>";
                                    } else {
                                        echo "<a href='?page=$i&sfl=$sfl&stx=$stx' class='relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50'>$i</a>";
                                    }
                                }
                                
                                if ($page < $total_page) {
                                    echo "<a href='?page=" . ($page + 1) . "&sfl=$sfl&stx=$stx' class='relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50'>다음</a>";
                                    echo "<a href='?page=$total_page&sfl=$sfl&stx=$stx' class='relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50'>마지막</a>";
                                }
                                ?>
                            </nav>
                        </div>
                    </div>
                    <?php } ?>

                <?php } else { ?>
                    <div class="text-center py-12">
                        <i class="fas fa-shopping-cart text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 text-lg">주문 내역이 없습니다.</p>
                        <a href="order_page.php" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            첫 주문하기
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>