<?php
$sub_menu = "600700";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, 'r');

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

// 지점이 선택되지 않았으면 선택 페이지 표시
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
                    <i class="fas fa-store text-4xl text-blue-600 mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-900">지점 선택</h1>
                    <p class="text-gray-600 mt-2">주문을 진행할 지점을 선택해주세요.</p>
                </div>
                
                <div class="space-y-3">
                    <?php foreach ($available_branches as $branch) { ?>
                    <a href="?br_id=<?php echo urlencode($branch['br_id']); ?>" 
                       class="block w-full p-4 bg-gray-50 hover:bg-blue-50 rounded-lg border border-gray-200 hover:border-blue-300 transition-colors">
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
                    <a href="<?php echo G5_DMK_ADM_URL; ?>" class="text-blue-600 hover:text-blue-800">
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

// 상품 목록 가져오기 (선택된 지점 기준)
$item_where_condition = dmk_get_item_where_condition($selected_br_id, $branch_info['ag_id'], '');

$sql = "SELECT it_id, it_name, it_cust_price, it_use, it_soldout, it_stock_qty, ca_id, dmk_it_owner_type, dmk_it_owner_id
        FROM {$g5['g5_shop_item_table']} 
        WHERE it_use = '1' AND it_soldout = '0' $item_where_condition
        ORDER BY it_name ASC";
$result = sql_query($sql);

$items = array();
while ($row = sql_fetch_array($result)) {
    $items[] = $row;
}

// 분류 목록 가져오기
$categories = array();
$cat_sql = "SELECT ca_id, ca_name FROM {$g5['g5_shop_category_table']} WHERE LENGTH(ca_id) = 10 ORDER BY ca_order, ca_id";
$cat_result = sql_query($cat_sql);
while ($cat_row = sql_fetch_array($cat_result)) {
    $categories[$cat_row['ca_id']] = $cat_row['ca_name'];
}

$g5['title'] = '주문 페이지 - ' . $branch_info['br_name'];
?>

<!DOCTYPE html>
<html lang="ko" data-theme="emerald">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title']; ?> - 도매까</title>
    <meta name="description" content="도매까 지점 주문 시스템">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary: #10b981;
            --primary-foreground: #ffffff;
            --background: #ffffff;
            --foreground: #0f172a;
            --default-100: #f1f5f9;
            --default-200: #e2e8f0;
            --default-500: #64748b;
            --default-700: #334155;
            --divider: #e2e8f0;
            --danger: #ef4444;
            --focus: #3b82f6;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--background);
            color: var(--foreground);
        }
        
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--primary-foreground);
            border: 1px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-outline:hover {
            opacity: 0.8;
        }
        
        .btn-default {
            background-color: transparent;
            color: var(--foreground);
            border: 1px solid var(--default-200);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-default:hover {
            opacity: 0.8;
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--default-100);
            border: 1px solid transparent;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .input-field:focus {
            outline: none;
            background-color: var(--default-100);
            border-color: var(--focus);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .product-card {
            border-bottom: 1px solid var(--divider);
            padding: 1.25rem 1.875rem;
        }
        
        .quantity-btn {
            width: 100%;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quantity-btn:hover {
            background-color: var(--default-100);
        }
        
        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .sticky-header {
            position: sticky;
            top: 65px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            z-index: 49;
        }
        
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            background: white;
            border-top: 1px solid var(--divider);
        }
        
        @media (max-width: 768px) {
            .product-card {
                padding: 1.25rem 1rem;
            }
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
        
        .dmk-label-type1 { background-color: #dc3545; } /* 총판 */
        .dmk-label-type2 { background-color: #007bff; } /* 대리점 */
        .dmk-label-type3 { background-color: #28a745; } /* 지점 */
        
        /* Modal Styles for Mobile Viewport Changes */
        #productModal {
            --safe-area-inset-top: env(safe-area-inset-top, 0);
            --safe-area-inset-bottom: env(safe-area-inset-bottom, 0);
        }
        
        /* 모달 컨테이너가 전체 visible viewport를 차지하도록 설정 */
        #modalContainer {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        
        #modalContent {
            --modal-max-height: calc(var(--viewport-height, 100vh) - 2rem);
            max-height: var(--modal-max-height);
            transition: max-height 0.3s ease;
        }
        
        #modalBody {
            max-height: calc(var(--modal-max-height) - 120px); /* Header + Footer height */
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }
        
        @supports (height: 100dvh) {
            #modalContent {
                --modal-max-height: calc(100dvh - 2rem);
                max-height: calc(100dvh - 2rem);
            }
            
            #modalBody {
                max-height: calc(100dvh - 2rem - 120px);
            }
        }
        
        /* iOS Safari specific fixes */
        @supports (-webkit-touch-callout: none) {
            #productModal.modal-open {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
            }
        }
        
        /* Responsive modal sizing */
        @media (max-width: 640px) {
            #modalContent {
                margin: 0.5rem;
                max-width: calc(100vw - 1rem);
            }
            
            #modalBody {
                max-height: calc(var(--viewport-height, 100vh) - 1rem - 120px);
            }
        }
        
        /* Handle landscape orientation */
        @media (orientation: landscape) and (max-height: 600px) {
            #modalContent {
                max-height: calc(var(--viewport-height, 100vh) - 1rem);
            }
            
            #modalBody {
                max-height: calc(var(--viewport-height, 100vh) - 1rem - 100px);
            }
            
            .modal-header, .modal-footer {
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-white min-h-screen">
        <!-- Product Detail Modal -->
        <div id="productModal" class="fixed inset-0 z-[9999] hidden">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/50" onclick="closeProductModal()"></div>
            
            <!-- Modal Container -->
            <div id="modalContainer" class="fixed inset-0 flex items-center justify-center p-4 overflow-y-auto">
                <!-- Modal Content -->
                <div id="modalContent" class="relative w-full max-w-lg bg-white rounded-lg shadow-xl overflow-hidden my-auto">
                    <!-- Modal Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                        <h3 class="text-lg font-semibold">상품 상세정보</h3>
                        <button type="button" onclick="closeProductModal()" class="p-2 hover:bg-gray-100 rounded-full transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <div id="modalBody" class="p-4 overflow-y-auto">
                        <!-- Content will be loaded here -->
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-200 px-4 py-3">
                        <button type="button" onclick="closeProductModal()" class="w-full btn-primary">닫기</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Navigation -->
        <nav class="flex z-40 w-full h-auto items-center justify-center sticky top-0 inset-x-0 border-b border-gray-200 backdrop-blur-lg bg-white/70" style="--navbar-height: 4rem;">
            <header class="z-40 flex px-6 gap-4 w-full flex-row relative flex-nowrap items-center justify-between h-16 max-w-4xl">
                <div class="flex gap-4 h-full flex-row flex-nowrap items-center flex-grow">
                    <div class="flex flex-row flex-grow flex-nowrap justify-start bg-transparent items-center">
                        <p class="font-bold text-inherit"><?php echo $branch_info['br_name']; ?></p>
                        <span class="dmk-label dmk-label-type3 ml-2">지점</span>
                    </div>
                </div>
                <div class="flex gap-4 h-full flex-row flex-nowrap items-center">
                    <a href="<?php echo G5_DMK_ADM_URL; ?>" class="btn-default">관리자</a>
                    <a href="orderlist.php" class="btn-default">주문내역</a>
                </div>
            </header>
        </nav>

        <!-- Date Selection -->
        <div class="flex flex-col">
            <div class="py-3 px-6 border-b overflow-x-auto scrollbar-hide">
                <div class="flex items-center space-x-3">
                    <?php
                    for ($i = 0; $i < 7; $i++) {
                        $date = date('n월j일 (D)', strtotime("+$i days"));
                        $selected = $i == 0 ? 'btn-outline' : 'btn-default';
                        echo "<button class='$selected whitespace-nowrap' onclick='selectDate(\"$date\")'>$date</button>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Event Header -->
        <div class="pb-10">
            <div class="pt-5 px-5 pb-3 flex items-center">
                <div>
                    <h1 class="text-xl font-bold"><?php echo date('n.j.') . get_yoil(date('Y-m-d')) . '요일'; ?> 주문</h1>
                    <span class="text-sm font-normal text-gray-500">주문일 <?php echo date('Y년 m월 d일'); ?></span>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="no-scrollbar flex overflow-x-auto px-5 py-2 sticky-header gap-x-2 border-b">
                <button class="btn-outline whitespace-nowrap" onclick="filterProducts('all')">전체</button>
                <?php
                $category_counts = array();
                foreach ($items as $item) {
                    $cat_name = isset($categories[$item['ca_id']]) ? $categories[$item['ca_id']] : '기타';
                    if (!isset($category_counts[$cat_name])) {
                        $category_counts[$cat_name] = 0;
                    }
                    $category_counts[$cat_name]++;
                }
                
                foreach ($category_counts as $cat_name => $count) {
                    echo "<button class='btn-default whitespace-nowrap' onclick='filterProducts(\"" . htmlspecialchars($cat_name) . "\")'>{$cat_name}({$count})</button>";
                }
                ?>
            </div>

            <!-- Product List -->
            <form class="flex flex-col" id="orderForm" method="post" action="order_process.php">
                <input type="hidden" name="branch_id" value="<?php echo $selected_br_id; ?>">
                <input type="hidden" name="order_date" value="<?php echo date('Y-m-d'); ?>">
                
                <!-- Products will be populated by JavaScript -->
                <div id="productList"></div>

                <!-- Order Summary -->
                <div class="mx-8 gap-y-1 py-5 border-b flex flex-col">
                    <div class="flex">
                        <span>주문수량</span>
                        <span class="ml-auto font-medium text-md" id="totalQuantity">0개</span>
                    </div>
                    <div class="flex">
                        <span>주문금액</span>
                        <span class="ml-auto font-medium text-md" id="totalAmount">0원</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium">합계</span>
                        <span class="ml-auto font-bold text-lg" id="totalSum">0원</span>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="mx-8 flex flex-col gap-y-3 py-5">
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">주문자 *</label>
                        <input type="text" name="orderer_name" class="input-field" value="<?php echo htmlspecialchars($member['mb_name']); ?>" readonly>
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">연락처 *</label>
                        <input type="tel" name="orderer_phone" class="input-field" placeholder="연락처를 입력해주세요." required>
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">배송지</label>
                        <input type="text" name="delivery_address" class="input-field" value="<?php echo htmlspecialchars($branch_info['br_addr']); ?>" placeholder="배송 주소를 입력해주세요.">
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">요청사항</label>
                        <textarea name="order_memo" class="input-field" rows="3" placeholder="요청사항을 입력해주세요."></textarea>
                    </div>

                    <!-- Order Policy -->
                    <div class="mt-5 flex flex-col gap-y-5">
                        <hr class="border-gray-200">
                        <div class="flex flex-col gap-2">
                            <p class="font-medium">주문 안내사항</p>
                            <p class="text-gray-500 text-sm">
                                • 주문 확인 후 배송 일정을 안내드립니다.<br>
                                • 상품 재고 상황에 따라 주문이 취소될 수 있습니다.<br>
                                • 문의사항은 관리자에게 연락해주세요.
                            </p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Fixed Bottom Bar -->
            <div class="fixed-footer py-2 px-3 flex space-x-3">
                <a href="orderlist.php" class="btn-outline">주문내역</a>
                <button type="submit" form="orderForm" class="btn-primary flex-1" id="submitOrder">주문하기</button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Product data from PHP
        const products = <?php echo json_encode($items); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        
        let cart = {};
        let currentFilter = 'all';

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            renderProducts();
            updateOrderSummary();
            
            // Initialize viewport height
            updateViewportHeight();
            
            // Force initial viewport calculation
            if (window.visualViewport) {
                // 초기 높이 설정
                document.documentElement.style.setProperty('--initial-viewport-height', `${window.visualViewport.height}px`);
            }
            
            // Add ESC key handler for modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !document.getElementById('productModal').classList.contains('hidden')) {
                    closeProductModal();
                }
            });
        });

        // Render products
        function renderProducts() {
            const productList = document.getElementById('productList');
            let filteredProducts = products;
            
            if (currentFilter !== 'all') {
                filteredProducts = products.filter(product => {
                    const catName = categories[product.ca_id] || '기타';
                    return catName === currentFilter;
                });
            }

            productList.innerHTML = filteredProducts.map(product => {
                const ownerLabel = getOwnerLabel(product.dmk_it_owner_type);
                const imageSrc = `<?php echo G5_DATA_URL; ?>/item/${product.it_id}_0`;
                
                return `
                <div class="product-card" data-category="${categories[product.ca_id] || '기타'}">
                    <div class="flex gap-x-5 items-center">
                        <div class="min-w-[100px] min-h-[100px] relative">
                            <img src="${imageSrc}" 
                                 alt="${product.it_name}" 
                                 class="w-[100px] h-[100px] object-cover rounded-xl shadow-sm"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik01MCA2NUw2MCA0NUg0MEw1MCA2NVoiIGZpbGw9IiM2NDc0OEIiLz4KPC9zdmc+'" />
                        </div>
                        <div class="flex flex-1 flex-col gap-y-1">
                            <div class="flex items-center">
                                <p class="text-sm min-w-[60px] text-gray-500">상품명</p>
                                <p class="text-sm font-medium">${product.it_name}</p>
                                ${ownerLabel}
                            </div>
                            <div class="flex">
                                <p class="text-sm min-w-[60px] text-gray-500">가격</p>
                                <p class="text-sm font-medium">${parseInt(product.it_cust_price).toLocaleString()}원</p>
                            </div>
                            <div class="flex">
                                <p class="text-sm font-medium">${product.it_stock_qty}<span class="text-sm min-w-[60px] text-gray-500">개 재고</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-1 mt-5">
                        <div class="w-full flex space-x-3">
                            <button type="button" class="border flex items-center justify-center rounded-lg font-medium px-4 text-sm" 
                                    onclick="showProductDetail('${product.it_id}')">상세보기</button>
                            <div class="border flex-1 flex items-center justify-center rounded-lg">
                                <button type="button" 
                                        class="quantity-btn ${(!cart[product.it_id] || cart[product.it_id] === 0) ? 'opacity-50' : ''}" 
                                        onclick="updateQuantity('${product.it_id}', -1)"
                                        ${(!cart[product.it_id] || cart[product.it_id] === 0) ? 'disabled' : ''}>-</button>
                                <div class="flex items-center justify-center font-bold text-lg px-4">
                                    ${cart[product.it_id] || 0}개
                                </div>
                                <button type="button" 
                                        class="quantity-btn" 
                                        onclick="updateQuantity('${product.it_id}', 1)">+</button>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="items[${product.it_id}]" value="${cart[product.it_id] || 0}">
                </div>
            `;
            }).join('');
        }

        // Get owner label
        function getOwnerLabel(ownerType) {
            switch(ownerType) {
                case '1': return '<span class="dmk-label dmk-label-type1 ml-2">총판</span>';
                case '2': return '<span class="dmk-label dmk-label-type2 ml-2">대리점</span>';
                case '3': return '<span class="dmk-label dmk-label-type3 ml-2">지점</span>';
                default: return '';
            }
        }

        // Filter products
        function filterProducts(category) {
            currentFilter = category;
            
            // Update button styles
            document.querySelectorAll('.sticky-header button').forEach(btn => {
                btn.className = btn.className.replace('btn-outline', 'btn-default');
            });
            
            if (category === 'all') {
                document.querySelector('[onclick="filterProducts(\'all\')"]').className = 
                    document.querySelector('[onclick="filterProducts(\'all\')"]').className.replace('btn-default', 'btn-outline');
            } else {
                const targetBtn = Array.from(document.querySelectorAll('.sticky-header button')).find(btn => 
                    btn.textContent.includes(category)
                );
                if (targetBtn) {
                    targetBtn.className = targetBtn.className.replace('btn-default', 'btn-outline');
                }
            }
            
            renderProducts();
        }

        // Update quantity
        function updateQuantity(productId, change) {
            const product = products.find(p => p.it_id === productId);
            if (!product) return;

            if (!cart[productId]) cart[productId] = 0;
            
            const newQuantity = cart[productId] + change;
            
            if (newQuantity >= 0 && newQuantity <= parseInt(product.it_stock_qty)) {
                cart[productId] = newQuantity;
                if (cart[productId] === 0) {
                    delete cart[productId];
                }
                renderProducts();
                updateOrderSummary();
            }
        }

        // Update order summary
        function updateOrderSummary() {
            let totalQuantity = 0;
            let totalAmount = 0;

            Object.keys(cart).forEach(productId => {
                const product = products.find(p => p.it_id === productId);
                if (product) {
                    totalQuantity += cart[productId];
                    totalAmount += parseInt(product.it_cust_price) * cart[productId];
                }
            });

            document.getElementById('totalQuantity').textContent = `${totalQuantity}개`;
            document.getElementById('totalAmount').textContent = `${totalAmount.toLocaleString()}원`;
            document.getElementById('totalSum').textContent = `${totalAmount.toLocaleString()}원`;
        }

        // Show product detail
        function showProductDetail(productId) {
            const product = products.find(p => p.it_id === productId);
            if (product) {
                showProductModal(product);
            }
        }
        
        // Show product modal
        function showProductModal(product) {
            const modal = document.getElementById('productModal');
            const modalBody = document.getElementById('modalBody');
            
            // 스크롤 위치 저장
            const scrollY = window.scrollY || window.pageYOffset;
            
            // Generate modal content
            const imageSrc = `<?php echo G5_DATA_URL; ?>/item/${product.it_id}_0`;
            const ownerLabel = getOwnerLabel(product.dmk_it_owner_type);
            
            modalBody.innerHTML = `
                <div class="space-y-4">
                    <!-- Product Image -->
                    <div class="w-full">
                        <img src="${imageSrc}" 
                             alt="${product.it_name}" 
                             class="w-full h-auto max-h-64 object-contain rounded-lg bg-gray-50"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik01MCA2NUw2MCA0NUg0MEw1MCA2NVoiIGZpbGw9IiM2NDc0OEIiLz4KPC9zdmc+'" />
                    </div>
                    
                    <!-- Product Info -->
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <h4 class="text-lg font-semibold">${product.it_name}</h4>
                            ${ownerLabel}
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500">상품코드</span>
                                <p class="font-medium">${product.it_id}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">판매가격</span>
                                <p class="font-medium">${parseInt(product.it_cust_price).toLocaleString()}원</p>
                            </div>
                            <div>
                                <span class="text-gray-500">재고수량</span>
                                <p class="font-medium">${product.it_stock_qty}개</p>
                            </div>
                            <div>
                                <span class="text-gray-500">카테고리</span>
                                <p class="font-medium">${categories[product.ca_id] || '기타'}</p>
                            </div>
                        </div>
                        
                        <!-- Additional Info (if needed) -->
                        <div class="pt-3 border-t">
                            <p class="text-sm text-gray-600">
                                더 자세한 정보가 필요하시면 관리자에게 문의해주세요.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Quick Order Section -->
                    <div class="pt-3 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">빠른 주문</span>
                            <div class="flex items-center space-x-2">
                                <button type="button" 
                                        class="w-8 h-8 flex items-center justify-center rounded border hover:bg-gray-50"
                                        onclick="updateQuantityFromModal('${product.it_id}', -1)">
                                    <span class="text-lg font-bold">-</span>
                                </button>
                                <span class="w-12 text-center font-medium" id="modal-qty-${product.it_id}">
                                    ${cart[product.it_id] || 0}개
                                </span>
                                <button type="button" 
                                        class="w-8 h-8 flex items-center justify-center rounded border hover:bg-gray-50"
                                        onclick="updateQuantityFromModal('${product.it_id}', 1)">
                                    <span class="text-lg font-bold">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            
            // 모달 열린 직후 viewport 높이 강제 업데이트
            requestAnimationFrame(() => {
                updateViewportHeight();
                
                // 모달 컨텐츠가 화면 중앙에 위치하도록 보장
                const modalContainer = document.getElementById('modalContainer');
                if (modalContainer) {
                    modalContainer.scrollTop = 0;
                }
                
                // viewport 변경 이벤트 강제 트리거
                if (window.visualViewport) {
                    window.visualViewport.dispatchEvent(new Event('resize'));
                }
                window.dispatchEvent(new Event('resize'));
                
                // 한 번 더 업데이트
                setTimeout(updateViewportHeight, 100);
            });
            
            // Prevent scrolling on iOS
            if (isIOS()) {
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
                document.body.style.top = `-${scrollY}px`;
            }
        }
        
        // Close product modal
        function closeProductModal() {
            const modal = document.getElementById('productModal');
            modal.classList.add('hidden');
            modal.classList.remove('modal-open');
            document.body.style.overflow = '';
            
            // iOS 스크롤 위치 복원
            if (isIOS() && document.body.style.position === 'fixed') {
                const scrollY = Math.abs(parseInt(document.body.style.top || '0'));
                document.body.style.position = '';
                document.body.style.width = '';
                document.body.style.top = '';
                window.scrollTo(0, scrollY);
            }
        }
        
        // Update quantity from modal
        function updateQuantityFromModal(productId, change) {
            updateQuantity(productId, change);
            const modalQtyElement = document.getElementById(`modal-qty-${productId}`);
            if (modalQtyElement) {
                modalQtyElement.textContent = `${cart[productId] || 0}개`;
            }
        }
        
        // Check if iOS
        function isIOS() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        }
        
        // Update viewport height for mobile browsers
        function updateViewportHeight() {
            // Visual Viewport API 사용 (실제 보이는 영역)
            const visualViewport = window.visualViewport;
            let actualHeight;
            let offsetTop = 0;
            
            if (visualViewport) {
                // Visual Viewport가 지원되는 경우 (대부분의 모던 모바일 브라우저)
                actualHeight = visualViewport.height;
                offsetTop = visualViewport.offsetTop || 0;
                
                // 모바일 브라우저 UI로 인한 오프셋 처리
                if (offsetTop > 0) {
                    actualHeight = Math.min(actualHeight, window.innerHeight - offsetTop);
                }
            } else {
                // Fallback: window.innerHeight 사용
                actualHeight = window.innerHeight;
            }
            
            // CSS 변수 업데이트
            document.documentElement.style.setProperty('--viewport-height', `${actualHeight}px`);
            
            // 모달이 열려있을 때만 업데이트
            const modal = document.getElementById('productModal');
            const modalContent = document.getElementById('modalContent');
            const modalBody = document.getElementById('modalBody');
            const modalContainer = document.getElementById('modalContainer');
            
            if (modal && !modal.classList.contains('hidden') && modalContent && modalBody) {
                // 실제 보이는 영역 기준으로 계산
                const padding = 32; // 상하 패딩 16px씩
                const headerFooterHeight = 120; // 헤더와 푸터 공간
                
                // 모바일에서 브라우저 UI가 변경될 때를 대비한 안전한 계산
                let safeHeight = actualHeight;
                if (visualViewport && window.innerHeight > actualHeight) {
                    // 브라우저 UI가 표시된 상태에서는 더 작은 값 사용
                    safeHeight = Math.min(actualHeight, window.innerHeight * 0.9);
                }
                
                const maxContentHeight = safeHeight - padding;
                const maxBodyHeight = safeHeight - padding - headerFooterHeight;
                
                modalContent.style.maxHeight = `${maxContentHeight}px`;
                modalBody.style.maxHeight = `${maxBodyHeight}px`;
                
                // 모달 컨테이너가 전체 화면을 차지하도록 보장
                if (modalContainer) {
                    modalContainer.style.height = `${actualHeight}px`;
                    
                    // Visual Viewport API를 사용한 정확한 위치 조정
                    if (visualViewport) {
                        const scrollY = visualViewport.pageTop || 0;
                        const offsetY = visualViewport.offsetTop || 0;
                        
                        if (scrollY > 0 || offsetY > 0) {
                            modalContainer.style.transform = `translateY(${-scrollY}px)`;
                        } else {
                            modalContainer.style.transform = '';
                        }
                    }
                }
                
                // 디버그 정보
                console.log(`Viewport updated - Visual: ${actualHeight}px, Safe: ${safeHeight}px, Window: ${window.innerHeight}px`);
            }
        }
        
        // Handle viewport changes
        let viewportTimeout;
        function handleViewportChange() {
            clearTimeout(viewportTimeout);
            viewportTimeout = setTimeout(() => {
                updateViewportHeight();
            }, 100);
        }
        
        // Visual Viewport API for better mobile support
        if ('visualViewport' in window) {
            window.visualViewport.addEventListener('resize', handleViewportChange);
            window.visualViewport.addEventListener('scroll', handleViewportChange);
        }
        
        // Fallback for browsers without Visual Viewport API
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('orientationchange', handleViewportChange);

        // Select date
        function selectDate(date) {
            // Update button styles
            document.querySelectorAll('.py-3 button').forEach(btn => {
                btn.className = btn.className.replace('btn-outline', 'btn-default');
            });
            
            event.target.className = event.target.className.replace('btn-default', 'btn-outline');
        }

        // Handle form submission
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const ordererPhone = document.querySelector('[name="orderer_phone"]').value;
            
            if (!ordererPhone) {
                e.preventDefault();
                alert('연락처를 입력해주세요.');
                return;
            }
            
            if (Object.keys(cart).length === 0) {
                e.preventDefault();
                alert('주문할 상품을 선택해주세요.');
                return;
            }
            
            // Update hidden inputs
            Object.keys(cart).forEach(productId => {
                const input = document.querySelector(`[name="items[${productId}]"]`);
                if (input) {
                    input.value = cart[productId];
                }
            });
        });

        // Handle scroll for sticky elements
        window.addEventListener('scroll', function() {
            const stickyHeader = document.querySelector('.sticky-header');
            if (window.scrollY > 65) {
                stickyHeader.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            } else {
                stickyHeader.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>