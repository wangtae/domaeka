<?php
$sub_menu = "";
include_once(__DIR__ . '/../common.php');

// 로그인 체크
if (!$member['mb_id']) {
    // 현재 페이지 URL을 인코딩하여 로그인 후 돌아올 URL로 설정
    $current_url = urlencode(G5_URL . $_SERVER['REQUEST_URI']);
    
    // 카카오 로그인 전용 페이지로 리다이렉트 (자동 회원가입 처리)
    goto_url(G5_BBS_URL . '/login-kakao.php?url=' . $current_url);
}

// URL에서 브랜치 ID 추출
$br_id = isset($_GET['br_id']) ? trim($_GET['br_id']) : '';

if (!$br_id) {
    alert('잘못된 접근입니다.', G5_URL);
}

// 지점 정보 조회
$branch_sql = " SELECT b.*, 
                    COALESCE(br_m.mb_name, '') AS br_name, 
                    COALESCE(br_m.mb_tel, '') AS br_phone, 
                    COALESCE(br_m.mb_hp, '') AS br_hp, 
                    COALESCE(br_m.mb_addr1, '') AS br_address
                FROM dmk_branch b 
                JOIN g5_member br_m ON b.br_id = br_m.mb_id 
                WHERE b.br_id = '$br_id' AND b.br_status = 1 ";
$branch = sql_fetch($branch_sql);

if (!$branch) {
    alert('유효하지 않거나 비활성화된 지점입니다.', G5_URL);
}

// 주문 내역 조회
$sql = " SELECT o.*, 
         (SELECT COUNT(*) FROM g5_shop_cart WHERE od_id = o.od_id) as item_count
         FROM g5_shop_order o 
         WHERE o.dmk_od_br_id = '$br_id' 
         ORDER BY o.od_time DESC ";
$result = sql_query($sql);

$g5['title'] = $branch['br_name'] . ' 주문내역';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title'] ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
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
        
        
        .order-status-waiting { color: #3b82f6; }
        .order-status-prepare { color: #3b82f6; }
        .order-status-complete { color: #10b981; }
        .order-status-cancel { color: #f59e0b; }
    </style>
</head>
<body>
    <div class="bg-white min-h-screen">
        <!-- Navigation -->
        <nav class="flex z-40 w-full h-auto items-center justify-center sticky top-0 inset-x-0 border-b border-gray-200 backdrop-blur-lg bg-white/70">
            <header class="z-40 flex px-6 gap-4 w-full flex-row relative flex-nowrap items-center justify-between h-16">
                <div class="flex gap-4 h-full flex-row flex-nowrap items-center">
                    <div class="flex flex-row flex-nowrap justify-start bg-transparent items-center">
                        <p class="font-bold text-inherit"><?php echo htmlspecialchars($branch['br_name']) ?> 주문내역</p>
                    </div>
                </div>
                <div class="flex gap-2 h-full flex-row flex-nowrap items-center">
                    <a href="/go/<?php echo $branch['br_shortcut_code'] ?: $br_id ?>" class="btn-outline text-sm">
                        <i class="fas fa-shopping-cart mr-1"></i> 주문페이지
                    </a>
                    <?php if ($member['mb_id']) { ?>
                    <a href="<?php echo G5_BBS_URL ?>/logout.php" class="btn-outline text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i> 로그아웃
                    </a>
                    <?php } ?>
                </div>
            </header>
        </nav>

        <!-- Main Content -->
        <div>
            <div class="flex flex-col">
                <div class="pt-6 flex flex-col px-5 gap-y-4">
                    <!-- 주문 통계 -->
                    <div class="flex flex-col gap-y-2">
                        <?php
                        $status_count = array(
                            'waiting' => 0,
                            'prepare' => 0,
                            'complete' => 0,
                            'cancel' => 0,
                            'total' => 0
                        );
                        
                        // 전체 주문 수 계산
                        sql_data_seek($result, 0);
                        while ($row = sql_fetch_array($result)) {
                            $status_count['total']++;
                            switch($row['od_status']) {
                                case '주문':
                                    $status_count['waiting']++;
                                    break;
                                case '준비':
                                case '배송':
                                    $status_count['prepare']++;
                                    break;
                                case '완료':
                                    $status_count['complete']++;
                                    break;
                                case '취소':
                                    $status_count['cancel']++;
                                    break;
                            }
                        }
                        ?>
                        <div class="flex items-center">
                            <h2 class="text-md font-bold">전체 주문 <?php echo number_format($status_count['total']) ?>건</h2>
                        </div>
                        <div class="ml-auto flex space-x-2 items-center">
                            <span class="order-status-waiting font-normal text-sm">대기 <?php echo number_format($status_count['waiting']) ?>건</span>
                            <span class="w-[1px] self-stretch bg-[#ddd]"></span>
                            <span class="order-status-prepare font-normal text-sm">준비 <?php echo number_format($status_count['prepare']) ?>건</span>
                            <span class="w-[1px] self-stretch bg-[#ddd]"></span>
                            <span class="order-status-complete font-normal text-sm">완료 <?php echo number_format($status_count['complete']) ?>건</span>
                            <span class="w-[1px] self-stretch bg-[#ddd]"></span>
                            <span class="order-status-cancel font-normal text-sm">취소 <?php echo number_format($status_count['cancel']) ?>건</span>
                        </div>
                    </div>
                    
                    <!-- 주문 목록 -->
                    <div class="gap-y-3 flex flex-col">
                        <?php
                        sql_data_seek($result, 0);
                        while ($order = sql_fetch_array($result)) {
                            $order_date = date('Y-m-d H:i', strtotime($order['od_time']));
                            $status_class = 'order-status-waiting';
                            $status_text = '대기';
                            
                            switch($order['od_status']) {
                                case '주문':
                                    $status_class = 'order-status-waiting';
                                    $status_text = '대기';
                                    break;
                                case '준비':
                                case '배송':
                                    $status_class = 'order-status-prepare';
                                    $status_text = '준비';
                                    break;
                                case '완료':
                                    $status_class = 'order-status-complete';
                                    $status_text = '완료';
                                    break;
                                case '취소':
                                    $status_class = 'order-status-cancel';
                                    $status_text = '취소';
                                    break;
                            }
                        ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-semibold text-sm">주문번호: <?php echo $order['od_id'] ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $order_date ?></p>
                                </div>
                                <span class="<?php echo $status_class ?> font-medium text-sm"><?php echo $status_text ?></span>
                            </div>
                            <div class="text-sm text-gray-700">
                                <p>주문자: <?php echo htmlspecialchars($order['od_name']) ?></p>
                                <p>연락처: <?php echo htmlspecialchars($order['od_hp']) ?></p>
                                <p>상품: <?php echo number_format($order['item_count']) ?>개</p>
                                <p class="font-medium mt-1">금액: <?php echo number_format($order['od_cart_price']) ?>원</p>
                            </div>
                            <?php if ($order['od_memo']) { ?>
                            <div class="mt-2 text-sm text-gray-600">
                                <p>메모: <?php echo nl2br(htmlspecialchars($order['od_memo'])) ?></p>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        
                        <?php if ($status_count['total'] == 0) { ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>주문 내역이 없습니다.</p>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
    </div>
    
    <style>
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-outline:hover {
            opacity: 0.8;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</body>
</html>