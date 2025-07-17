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

// 회원이 지점 정보가 있고 URL에 코드가 없는 경우 리다이렉트
if ($member['dmk_br_id'] && !isset($_GET['code']) && !isset($_GET['noredirect'])) {
    // 지점의 shortcut_code 조회
    $br_sql = " SELECT br_shortcut_code 
               FROM dmk_branch 
               WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
               AND br_shortcut_code IS NOT NULL 
               AND br_shortcut_code != '' 
               LIMIT 1 ";
    $br_info = sql_fetch($br_sql);
    
    if ($br_info && $br_info['br_shortcut_code']) {
        goto_url('/go/' . $br_info['br_shortcut_code']);
    } else {
        goto_url('/go/' . $member['dmk_br_id']);
    }
}

// URL에서 코드 추출 (옵션)
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

// 로그인한 회원의 주문내역 조회를 위한 정보 설정
$mb_id = $member['mb_id'];

// 회원의 지점 정보 확인
$order_page_url = '/go/'; // 기본값
$branch_name = '도매까'; // 기본 지점명

// dmk_br_id가 있는지 확인
if (isset($member['dmk_br_id']) && $member['dmk_br_id']) {
    // 지점의 shortcut_code 조회
    $br_sql = " SELECT br_id, br_shortcut_code, br_name 
               FROM dmk_branch 
               WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
               AND br_status = 1
               LIMIT 1 ";
    $br_info = sql_fetch($br_sql);
    
    if ($br_info) {
        // shortcut_code가 있으면 사용, 없으면 br_id 사용
        if (!empty($br_info['br_shortcut_code'])) {
            $order_page_url = '/go/' . $br_info['br_shortcut_code'];
        } else {
            $order_page_url = '/go/' . $br_info['br_id'];
        }
        $branch_name = $br_info['br_name'] ? $br_info['br_name'] : '도매까';
    } else {
        // 지점 정보가 없지만 dmk_br_id가 있으면 그대로 사용
        $order_page_url = '/go/' . $member['dmk_br_id'];
    }
}

// 주문 내역 조회 (로그인한 회원의 모든 주문)
$sql = " SELECT o.*, 
         (SELECT COUNT(*) FROM g5_shop_cart WHERE od_id = o.od_id) as item_count,
         b.br_name as branch_name
         FROM g5_shop_order o 
         LEFT JOIN dmk_branch b ON o.dmk_od_br_id = b.br_id
         WHERE o.mb_id = '$mb_id' 
         ORDER BY o.od_time DESC ";
$result = sql_query($sql);

$g5['title'] = '내 주문내역';
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
            justify-content: center;
        }
        
        .btn-outline:hover {
            opacity: 0.8;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
                        <p class="font-bold text-inherit"><?php echo htmlspecialchars($branch_name) ?> 주문내역</p>
                        <?php if (defined('G5_IS_ADMIN') && G5_IS_ADMIN && isset($_GET['debug'])) { ?>
                        <span class="text-xs text-gray-500 ml-2">[dmk_br_id: <?php echo $member['dmk_br_id'] ?>, URL: <?php echo $order_page_url ?>]</span>
                        <?php } ?>
                    </div>
                </div>
                <div class="flex gap-2 h-full flex-row flex-nowrap items-center">
                    <a href="<?php echo $order_page_url ?>" class="btn-outline text-sm">
                        <i class="fas fa-shopping-cart mr-1"></i> 주문페이지
                    </a>
                    <?php if ($member['mb_id']) { 
                        // 로그아웃 후 돌아올 현재 페이지 URL
                        $return_url = '/go/orderlist.php?noredirect=1';
                        // 로그아웃 후 카카오 로그인 페이지로 이동하도록 URL 구성 (도메인 제외)
                        $logout_url = G5_BBS_URL . '/logout.php?url=' . urlencode('/bbs/login-kakao.php?url=' . urlencode($return_url));
                    ?>
                    <a href="<?php echo $logout_url ?>" class="btn-outline text-sm">
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