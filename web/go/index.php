<?php
$sub_menu = "";
include_once(__DIR__ . '/../common.php');

// URL에서 코드 추출
$request_uri = $_SERVER['REQUEST_URI'];
$path_info = parse_url($request_uri, PHP_URL_PATH);

// /go/ 이후의 경로 추출
$pattern = '/\/go\/([^\/\?]+)/';
if (preg_match($pattern, $path_info, $matches)) {
    $url_code = $matches[1];
} else {
    // 코드가 없으면 메인 페이지로 리디렉션
    goto_url(G5_URL);
}

// url_code 검증 및 정리
$url_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $url_code);

if (!$url_code) {
    alert('유효하지 않은 URL 코드입니다.', G5_URL);
}

// get_yoil 함수는 common.lib.php에 이미 정의되어 있음

// dmk_branch 테이블에서 br_shortcut_code 또는 br_id로 지점 정보 조회
$url_code_safe = sql_real_escape_string($url_code);
$branch_sql = " SELECT b.*, 
                    COALESCE(br_m.mb_name, '') AS br_name, 
                    COALESCE(br_m.mb_nick, '') AS br_nick_from_member, 
                    COALESCE(br_m.mb_tel, '') AS br_phone, 
                    COALESCE(br_m.mb_addr1, '') AS br_address, 
                    COALESCE(ag_m.mb_nick, '') AS ag_name 
                FROM dmk_branch b 
                JOIN g5_member br_m ON b.br_id = br_m.mb_id 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                LEFT JOIN g5_member ag_m ON a.ag_id = ag_m.mb_id
                WHERE (b.br_shortcut_code = '$url_code_safe' OR b.br_id = '$url_code_safe') 
                AND b.br_status = 1 
                ORDER BY 
                    CASE WHEN b.br_shortcut_code = '$url_code_safe' THEN 1 ELSE 2 END
                LIMIT 1 ";
$branch = sql_fetch($branch_sql);

if (!$branch) {
    alert('유효하지 않거나 비활성화된 지점입니다.', G5_URL);
}

$br_id = $branch['br_id']; // 실제 br_id

// 상품 조회 (권한에 따른 필터링)
$items_sql = " SELECT it_id, it_name, it_cust_price as it_price, it_img1, it_stock_qty, ca_id, it_basic, it_explan
               FROM g5_shop_item 
               WHERE it_use = '1' AND it_soldout != '1' 
               ORDER BY it_order, it_id DESC ";
$items_result = sql_query($items_sql);

// 카테고리 조회
$categories_sql = " SELECT ca_id, ca_name 
                   FROM g5_shop_category 
                   WHERE ca_use = '1' 
                   ORDER BY ca_order, ca_id ";
$categories_result = sql_query($categories_sql);

$g5['title'] = $branch['br_name'] . ' 주문페이지';
?>
<!DOCTYPE html>
<html lang="ko" data-theme="emerald">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title'] ?></title>
    <meta name="description" content="도매까 지점 주문 시스템">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo G5_URL ?>/favicon.ico">
    
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
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        
        .btn-outline:hover {
            opacity: 0.8;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline:active {
            transform: translateY(0);
        }
        
        .btn-default {
            background-color: transparent;
            color: var(--foreground);
            border: 1px solid var(--default-200);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-default:hover {
            opacity: 0.8;
            background-color: var(--default-100);
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
            transition: background-color 0.2s ease;
        }
        
        .product-card:hover {
            background-color: rgba(0, 0, 0, 0.02);
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
            transition: transform 0.3s ease;
        }
        
        .fixed-footer.hidden {
            transform: translateY(100%);
        }
        
        /* Modal animation */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(20px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        
        .modal-backdrop {
            animation: modalFadeIn 0.3s ease;
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease;
        }
        
        /* 메인 컨테이너 최대 너비 제한 */
        .main-container {
            max-width: 768px;
            margin: 0 auto;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .product-card {
                padding: 1.25rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-white min-h-screen">
        <!-- Navigation -->
        <nav class="flex z-40 w-full h-auto items-center justify-center sticky top-0 inset-x-0 border-b border-gray-200 backdrop-blur-lg bg-white/70" style="--navbar-height: 4rem;">
            <header class="main-container z-40 flex px-6 gap-4 w-full flex-row relative flex-nowrap items-center justify-between h-16">
                <div class="flex gap-4 h-full flex-row flex-nowrap items-center flex-grow">
                    <div class="flex flex-row flex-grow flex-nowrap justify-start bg-transparent items-center">
                        <?php /*<img src="<?php echo G5_URL; ?>/theme/bootstrap5-basic/img/logo.png" alt="Logo" class="h-[30px] mr-2" />*/ ?>
                        <p class="font-bold text-inherit"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($branch['br_name']) ?></p>
                    </div>
                </div>
                <div class="flex gap-4 h-full flex-row flex-nowrap items-center">
                    <a href="#info" class="btn-default">매장 정보</a>
                    <?php if ($branch['br_phone']) { ?>
                    <a href="tel:<?php echo str_replace('-', '', $branch['br_phone']) ?>" class="btn-default">전화 문의</a>
                    <?php } ?>
                </div>
            </header>
        </nav>

        <!-- Main Content Container -->
        <div class="main-container">
            <!-- Date Selection -->
            <div class="flex flex-col">
                <div class="py-3 px-6 border-b overflow-x-auto scrollbar-hide">
                <div class="flex items-center space-x-3">
                    <?php
                    for ($i = 0; $i < 5; $i++) {
                        $date = date('Y-m-d', strtotime("+$i days"));
                        $display_date = date('n월j일', strtotime($date));
                        $yoil = get_yoil($date);
                        $is_active = $i === 0;
                        $btn_class = $is_active ? 'btn-outline' : 'btn-default';
                    ?>
                    <button class="<?php echo $btn_class ?> whitespace-nowrap" onclick="selectDate('<?php echo $date ?>')"><?php echo $display_date ?> (<?php echo $yoil ?>)</button>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Event Header -->
        <div class="pb-10">
            <div class="pt-5 px-5 pb-3 flex items-center">
                <div>
                    <h1 class="text-xl font-bold" id="orderTitle"><?php echo date('n.j.') . get_yoil(date('Y-m-d')) ?>요일 주문</h1>
                    <span class="text-sm font-normal text-gray-500" id="orderDate">주문일 <?php echo date('Y년 m월 d일') ?></span>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="no-scrollbar flex overflow-x-auto px-5 py-2 sticky-header gap-x-2 border-b">
                <button class="btn-outline whitespace-nowrap" onclick="filterProducts('all')">전체</button>
                <?php
                $category_counts = [];
                while ($cat = sql_fetch_array($categories_result)) {
                    $count_sql = " SELECT COUNT(*) as cnt 
                                  FROM g5_shop_item 
                                  WHERE ca_id = '{$cat['ca_id']}' AND it_use = '1' AND it_soldout != '1' ";
                    $count_result = sql_fetch($count_sql);
                    $count = $count_result['cnt'];
                    if ($count > 0) {
                        $category_counts[$cat['ca_id']] = $count;
                ?>
                <button class="btn-default whitespace-nowrap" onclick="filterProducts('<?php echo $cat['ca_id'] ?>')"><?php echo htmlspecialchars($cat['ca_name']) ?>(<?php echo $count ?>)</button>
                <?php
                    }
                }
                ?>
            </div>

            <!-- Product List -->
            <form class="flex flex-col" id="orderForm" method="post" action="<?php echo G5_DMK_URL ?>/adm/branch_admin/order_process.php">
                <input type="hidden" name="br_id" value="<?php echo $br_id ?>">
                <input type="hidden" name="order_date" value="<?php echo date('Y-m-d') ?>" id="selectedDate">
                
                <!-- Products will be populated by JavaScript -->
                <div id="productList">
                    <?php
                    $has_products = false;
                    while ($item = sql_fetch_array($items_result)) {
                        $has_products = true;
                        // 상품 이미지 경로 설정
                        if ($item['it_img1']) {
                            // it_img1이 전체 경로인 경우와 파일명만 있는 경우 처리
                            if (strpos($item['it_img1'], '/') !== false) {
                                $item_img = G5_DATA_URL.'/item/'.$item['it_img1'];
                            } else {
                                $item_img = G5_DATA_URL.'/item/'.$item['it_id'].'/'.$item['it_img1'];
                            }
                        } else {
                            $item_img = '';
                        }
                    ?>
                    <div class="product-card" data-category="<?php echo $item['ca_id'] ?>">
                        <div class="flex gap-x-5 items-center">
                            <div class="min-w-[100px] min-h-[100px] relative">
                                <?php if ($item_img) { ?>
                                <img src="<?php echo $item_img ?>" 
                                     alt="<?php echo htmlspecialchars($item['it_name']) ?>" 
                                     class="w-[100px] h-[100px] object-cover rounded-xl shadow-sm"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik01MCA2NUw2MCA0NUg0MEw1MCA2NVoiIGZpbGw9IiM2NDc0OEIiLz4KPC9zdmc+'" />
                                <?php } else { ?>
                                <div class="w-[100px] h-[100px] bg-gray-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400 text-2xl"></i>
                                </div>
                                <?php } ?>
                            </div>
                            <div class="flex flex-1 flex-col gap-y-1">
                                <div class="flex">
                                    <p class="text-sm min-w-[60px] text-gray-500">상품명</p>
                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($item['it_name']) ?></p>
                                </div>
                                <div class="flex">
                                    <p class="text-sm min-w-[60px] text-gray-500">가격</p>
                                    <p class="text-sm font-medium"><?php echo number_format($item['it_price']) ?>원</p>
                                </div>
                                <div class="flex">
                                    <p class="text-sm font-medium"><?php echo $item['it_stock_qty'] ?><span class="text-sm min-w-[60px] text-gray-500">개 재고</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1 mt-5">
                            <div class="w-full flex space-x-3">
                                <button type="button" class="border flex items-center justify-center rounded-lg font-medium px-4 text-sm" 
                                        onclick="showProductDetail('<?php echo $item['it_id'] ?>')"
                                        data-product-id="<?php echo $item['it_id'] ?>"
                                        data-product-name="<?php echo htmlspecialchars($item['it_name']) ?>"
                                        data-product-price="<?php echo number_format($item['it_price']) ?>"
                                        data-product-img="<?php echo $item_img ?>"
                                        data-product-basic="<?php echo htmlspecialchars($item['it_basic']) ?>"
                                        data-product-explan="<?php echo htmlspecialchars($item['it_explan']) ?>">상세보기</button>
                                <div class="border flex-1 flex items-center justify-center rounded-lg">
                                    <button type="button" 
                                            class="quantity-btn" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', -1)"
                                            disabled>-</button>
                                    <div class="flex items-center justify-center font-bold text-lg px-4">
                                        <span id="qty_<?php echo $item['it_id'] ?>">0</span>개
                                    </div>
                                    <button type="button" 
                                            class="quantity-btn" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', 1)">+</button>
                                </div>
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][qty]" value="0" id="input_<?php echo $item['it_id'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][price]" value="<?php echo $item['it_price'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][name]" value="<?php echo htmlspecialchars($item['it_name']) ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][stock]" value="<?php echo $item['it_stock_qty'] ?>">
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    
                    <?php if (!$has_products) { ?>
                    <div class="product-card text-center py-10">
                        <div class="text-gray-500">
                            <i class="fas fa-box-open text-4xl mb-4"></i>
                            <p class="text-lg font-medium">등록된 상품이 없습니다</p>
                            <p class="text-sm">상품 등록 후 주문이 가능합니다</p>
                        </div>
                    </div>
                    <?php } ?>
                </div>

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

                <!-- Delivery Method -->
                <div class="mx-8 py-5 border-b">
                    <div class="flex flex-col space-y-1 w-full">
                        <div class="relative flex flex-col gap-2">
                            <span class="text-gray-500">수령 방식</span>
                            <div class="flex flex-col flex-wrap gap-2">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="delivery_type" value="PICKUP" checked class="mr-2">
                                    <span>매장 픽업</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="delivery_type" value="DELIVERY" class="mr-2">
                                    <span>배송 수령</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="mx-8 flex flex-col gap-y-3 py-5">
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">주문자명 *</label>
                        <input type="text" name="customer_name" class="input-field" placeholder="주문자 이름을 입력해주세요" required>
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">전화번호 *</label>
                        <input type="tel" name="customer_phone" class="input-field" placeholder="연락 가능한 전화번호를 입력해주세요" required>
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">배송주소</label>
                        <input type="text" name="customer_address" class="input-field" placeholder="배송 수령 시 주소를 입력해주세요">
                    </div>
                    
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="text-sm font-medium text-gray-700">요청사항</label>
                        <input type="text" name="customer_message" class="input-field" placeholder="요청사항을 입력해주세요">
                    </div>

                    <!-- Branch Info -->
                    <div class="mt-5 flex flex-col gap-y-5" id="info">
                        <hr class="border-gray-200">
                        <div class="flex flex-col gap-2">
                            <p class="font-medium">📍 매장 정보</p>
                            <div class="text-gray-500 text-sm space-y-1">
                                <p><strong>매장:</strong> <?php echo htmlspecialchars($branch['br_name']) ?></p>
                                <p><strong>소속:</strong> <?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?></p>
                                <?php if ($branch['br_phone']) { ?>
                                <p><strong>전화:</strong> <?php echo htmlspecialchars($branch['br_phone']) ?></p>
                                <?php } ?>
                                <?php if ($branch['br_address']) { ?>
                                <p><strong>주소:</strong> <?php echo htmlspecialchars($branch['br_address']) ?></p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <footer class="mt-10 bg-gray-50">
                <div class="w-full mx-auto max-w-4xl px-5 py-10">
                    <div class="text-gray-500 text-xs">
                        도매까 지점 주문 시스템 <br>
                        <?php echo htmlspecialchars($branch['br_name']) ?> <br><br>
                        Copyright © <?php echo date('Y') ?> 도매까 All Rights Reserved.
                    </div>
                </div>
            </footer>

            <!-- Fixed Bottom Bar -->
            <div class="fixed-footer py-2 px-3 flex space-x-3">
                <div class="main-container flex space-x-3">
                    <a href="<?php echo G5_DMK_URL ?>/adm/branch_admin/orderlist.php?br_id=<?php echo $br_id ?>" class="btn-outline">주문내역</a>
                    <button type="submit" form="orderForm" class="btn-primary flex-1" id="submitOrder">주문하기</button>
                </div>
            </div>
            </div> <!-- End Main Content Container -->
        </div>
    </div>

    <!-- Product Detail Modal -->
    <div id="productModal" class="fixed inset-0 z-50 hidden">
        <!-- Backdrop -->
        <div class="modal-backdrop fixed inset-0 bg-black/50" onclick="closeProductModal()"></div>
        
        <!-- Modal Content -->
        <div class="fixed inset-0 flex items-center justify-center p-4 md:p-8">
            <div class="modal-content bg-white rounded-lg max-w-md w-full max-h-[calc(100vh-2rem)] md:max-h-[calc(100vh-4rem)] flex flex-col relative shadow-xl">
                <!-- Close Button -->
                <button onclick="closeProductModal()" class="absolute top-3 right-3 p-2 rounded-full hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                
                <!-- Header -->
                <div class="py-4 px-6 border-b">
                    <h3 class="text-lg font-semibold">상품 상세 정보</h3>
                </div>
                
                <!-- Content -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div id="modalProductImage" class="w-full mb-4"></div>
                    <h4 id="modalProductName" class="text-xl font-bold mb-2"></h4>
                    <p class="mb-2">가격: <span id="modalProductPrice" class="font-medium"></span>원</p>
                    <div id="modalProductBasic" class="mb-4 text-gray-600"></div>
                    <div id="modalProductExplan" class="prose max-w-none"></div>
                </div>
                
                <!-- Footer -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t">
                    <button onclick="closeProductModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        let cart = {};
        let currentFilter = 'all';
        let products = {};

        // Initialize products data
        <?php
        sql_data_seek($items_result, 0);
        echo "products = {\n";
        while ($item = sql_fetch_array($items_result)) {
            echo "    '{$item['it_id']}': {\n";
            echo "        id: '{$item['it_id']}',\n";
            echo "        name: '" . addslashes($item['it_name']) . "',\n";
            echo "        price: {$item['it_price']},\n";
            echo "        stock: {$item['it_stock_qty']},\n";
            echo "        category: '{$item['ca_id']}',\n";
            echo "        basic: '" . addslashes($item['it_basic']) . "',\n";
            echo "        explan: '" . addslashes($item['it_explan']) . "'\n";
            echo "    },\n";
        }
        echo "};\n";
        ?>

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateOrderSummary();
        });

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
                
                // Show all products
                document.querySelectorAll('.product-card').forEach(card => {
                    card.style.display = 'block';
                });
            } else {
                document.querySelector(`[onclick="filterProducts('${category}')"]`).className = 
                    document.querySelector(`[onclick="filterProducts('${category}')"]`).className.replace('btn-default', 'btn-outline');
                
                // Filter products by category
                document.querySelectorAll('.product-card').forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    card.style.display = cardCategory === category ? 'block' : 'none';
                });
            }
        }

        // Update quantity
        function updateQuantity(productId, change) {
            const product = products[productId];
            if (!product) return;

            if (!cart[productId]) cart[productId] = 0;
            
            const newQuantity = cart[productId] + change;
            
            if (newQuantity >= 0 && newQuantity <= product.stock) {
                cart[productId] = newQuantity;
                if (cart[productId] === 0) {
                    delete cart[productId];
                }
                
                // Update display
                document.getElementById('qty_' + productId).textContent = cart[productId] || 0;
                document.getElementById('input_' + productId).value = cart[productId] || 0;
                
                // Update button states
                const minusBtn = document.querySelector(`[onclick="updateQuantity('${productId}', -1)"]`);
                if (cart[productId] && cart[productId] > 0) {
                    minusBtn.disabled = false;
                    minusBtn.classList.remove('opacity-50');
                } else {
                    minusBtn.disabled = true;
                    minusBtn.classList.add('opacity-50');
                }
                
                updateOrderSummary();
            }
        }

        // Update order summary
        function updateOrderSummary() {
            let totalQuantity = 0;
            let totalAmount = 0;

            Object.keys(cart).forEach(productId => {
                const product = products[productId];
                if (product && cart[productId] > 0) {
                    totalQuantity += cart[productId];
                    totalAmount += product.price * cart[productId];
                }
            });

            document.getElementById('totalQuantity').textContent = `${totalQuantity}개`;
            document.getElementById('totalAmount').textContent = `${totalAmount.toLocaleString()}원`;
            document.getElementById('totalSum').textContent = `${totalAmount.toLocaleString()}원`;
        }

        // Select date
        function selectDate(date) {
            document.getElementById('selectedDate').value = date;
            
            const dateObj = new Date(date);
            const month = dateObj.getMonth() + 1;
            const day = dateObj.getDate();
            const yoil = ['일', '월', '화', '수', '목', '금', '토'][dateObj.getDay()];
            
            document.getElementById('orderTitle').textContent = `${month}.${day}.${yoil}요일 주문`;
            document.getElementById('orderDate').textContent = `주문일 ${dateObj.getFullYear()}년 ${String(month).padStart(2, '0')}월 ${String(day).padStart(2, '0')}일`;
            
            // Update button styles
            document.querySelectorAll('[onclick^="selectDate"]').forEach(btn => {
                btn.className = btn.className.replace('btn-outline', 'btn-default');
            });
            event.target.className = event.target.className.replace('btn-default', 'btn-outline');
        }

        // Show product detail modal
        function showProductDetail(productId) {
            const button = event.target;
            const productData = {
                id: button.getAttribute('data-product-id'),
                name: button.getAttribute('data-product-name'),
                price: button.getAttribute('data-product-price'),
                img: button.getAttribute('data-product-img'),
                basic: button.getAttribute('data-product-basic'),
                explan: button.getAttribute('data-product-explan')
            };
            
            // 모달에 데이터 채우기
            const modalImage = document.getElementById('modalProductImage');
            if (productData.img) {
                modalImage.innerHTML = `<img src="${productData.img}" alt="${productData.name}" class="w-full rounded-lg object-cover">`;
            } else {
                modalImage.innerHTML = `<div class="w-full h-48 bg-gray-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-image text-gray-400 text-4xl"></i>
                </div>`;
            }
            
            document.getElementById('modalProductName').textContent = productData.name;
            document.getElementById('modalProductPrice').textContent = productData.price;
            document.getElementById('modalProductBasic').textContent = productData.basic || '';
            
            // HTML 콘텐츠 처리
            const explanElement = document.getElementById('modalProductExplan');
            if (productData.explan) {
                explanElement.innerHTML = productData.explan;
            } else {
                explanElement.innerHTML = '<p class="text-gray-500">상품 설명이 없습니다.</p>';
            }
            
            // 하단 바 숨기기
            const footer = document.querySelector('.fixed-footer');
            if (footer) {
                footer.classList.add('hidden');
            }
            
            // 모달 표시 (애니메이션을 위한 약간의 지연)
            const modal = document.getElementById('productModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // 애니메이션 트리거
            setTimeout(() => {
                modal.querySelector('.modal-backdrop').style.opacity = '1';
                modal.querySelector('.modal-content').style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'translateY(0) scale(1)';
            }, 10);
        }
        
        // Close product modal
        function closeProductModal() {
            const modal = document.getElementById('productModal');
            const backdrop = modal.querySelector('.modal-backdrop');
            const content = modal.querySelector('.modal-content');
            
            // 애니메이션
            backdrop.style.opacity = '0';
            content.style.opacity = '0';
            content.style.transform = 'translateY(20px) scale(0.95)';
            
            // 애니메이션 완료 후 숨기기
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                
                // 하단 바 다시 표시
                const footer = document.querySelector('.fixed-footer');
                if (footer) {
                    footer.classList.remove('hidden');
                }
            }, 300);
        }

        // Handle form submission
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const customerName = document.querySelector('[name="customer_name"]').value;
            const customerPhone = document.querySelector('[name="customer_phone"]').value;
            
            if (!customerName || !customerPhone) {
                e.preventDefault();
                alert('주문자명과 전화번호를 입력해주세요.');
                return;
            }
            
            if (Object.keys(cart).length === 0) {
                e.preventDefault();
                alert('주문할 상품을 선택해주세요.');
                return;
            }
            
            // Confirm order
            let orderSummary = '주문 내역:\n';
            Object.keys(cart).forEach(productId => {
                const product = products[productId];
                if (product && cart[productId] > 0) {
                    orderSummary += `- ${product.name}: ${cart[productId]}개 (${(product.price * cart[productId]).toLocaleString()}원)\n`;
                }
            });
            
            const totalAmount = Object.keys(cart).reduce((total, productId) => {
                const product = products[productId];
                return total + (product ? product.price * cart[productId] : 0);
            }, 0);
            
            orderSummary += `\n총 주문금액: ${totalAmount.toLocaleString()}원`;
            
            if (!confirm(orderSummary + '\n\n주문을 진행하시겠습니까?')) {
                e.preventDefault();
            }
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
