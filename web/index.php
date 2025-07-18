<?php
include_once('./_common.php');

// 페이지 타이틀 설정
$g5['title'] = '도매까 - 스마트한 공동구매 플랫폼';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap');
        body {
            font-family: 'Noto Sans KR', sans-serif;
        }
        
        /* 애니메이션 효과 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.8s ease-out;
        }
        
        /* 모던한 그라디언트 색상 */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* 새로운 버튼 스타일 */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }
        
        /* 로고 스타일 */
        .logo-img {
            height: 40px;
            width: auto;
        }
        
        /* 모바일 메뉴 스타일 */
        .mobile-menu-btn {
            display: none;
        }
        
        /* 모바일 메뉴 - 기본적으로 숨김 */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background-color: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 100;
            overflow-y: auto;
        }
        
        .mobile-menu.active {
            right: 0;
        }
        
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 99;
            display: none;
        }
        
        .mobile-menu-overlay.active {
            display: block;
        }
        
        @media (max-width: 640px) {
            .mobile-menu-btn {
                display: block;
            }
        }
        
        /* 카드 호버 효과 */
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            border-color: #ddd6fe;
        }
        
        /* 스크롤 애니메이션 */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }
        
        .scroll-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 네비게이션 -->
    <nav class="fixed w-full top-0 z-50 bg-white shadow-md">
        <div class="container mx-auto px-4 sm:px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="/assets/domaeka/logo/domaeka-logo-03-60h.png" alt="도매까" class="logo-img">
                </div>
                
                <!-- 모바일 로그인/주문 버튼과 햄버거 메뉴 -->
                <div class="flex items-center">
                    <!-- 데스크톱 메뉴 (PC에서만 표시) -->
                    <div class="desktop-menu hidden sm:flex items-center space-x-4 sm:space-x-6">
                    <!-- PC에서만 보이는 메뉴 -->
                    <a href="#features" class="hidden sm:block text-gray-700 hover:text-indigo-600 transition duration-300">주요 기능</a>
                    <a href="#benefits" class="hidden sm:block text-gray-700 hover:text-indigo-600 transition duration-300">장점</a>
                    <a href="#process" class="hidden sm:block text-gray-700 hover:text-indigo-600 transition duration-300">이용 방법</a>
                    <?php if ($is_member) { ?>
                        <?php if ($is_admin == 'super' || $is_admin == 'dmk_admin') { ?>
                            <a href="<?php echo G5_ADMIN_URL; ?>" class="btn-primary">
                                <i class="fas fa-cog mr-1 sm:mr-2"></i><span class="hidden sm:inline">관리자</span><span class="sm:hidden">관리</span>
                            </a>
                        <?php } else { 
                            // 회원의 지점 정보 확인
                            $order_url = '/go/orderlist.php';
                            if ($member['dmk_br_id']) {
                                // 지점의 shortcut_code 조회
                                $br_sql = " SELECT br_shortcut_code 
                                           FROM dmk_branch 
                                           WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
                                           AND br_shortcut_code IS NOT NULL 
                                           AND br_shortcut_code != '' 
                                           LIMIT 1 ";
                                $br_info = sql_fetch($br_sql);
                                if ($br_info && $br_info['br_shortcut_code']) {
                                    $order_url = '/go/' . $br_info['br_shortcut_code'];
                                } else {
                                    $order_url = '/go/' . $member['dmk_br_id'];
                                }
                            }
                        ?>
                            <a href="<?php echo $order_url; ?>" class="btn-primary">
                                <i class="fas fa-shopping-cart mr-1 sm:mr-2"></i><span class="hidden sm:inline">내 주문</span><span class="sm:hidden">주문</span>
                            </a>
                        <?php } ?>
                        <a href="<?php echo G5_BBS_URL; ?>/logout.php" class="text-gray-700 hover:text-indigo-600 transition duration-300 hidden sm:block">
                            로그아웃
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="btn-secondary">
                            <i class="fas fa-comment mr-1 sm:mr-2"></i><span class="hidden sm:inline">카카오 로그인</span><span class="sm:hidden">로그인</span>
                        </a>
                    <?php } ?>
                    </div>
                    
                    <!-- 모바일에서만 보이는 로그인/주문 버튼 -->
                    <div class="flex sm:hidden items-center space-x-3 mr-3">
                        <?php if ($is_member) { ?>
                            <?php if ($is_admin == 'super' || $is_admin == 'dmk_admin') { ?>
                                <a href="<?php echo G5_ADMIN_URL; ?>" class="text-gray-700">
                                    <i class="fas fa-cog text-xl"></i>
                                </a>
                            <?php } else { 
                                // 회원의 지점 정보 확인
                                $order_url = '/go/orderlist.php';
                                if ($member['dmk_br_id']) {
                                    // 지점의 shortcut_code 조회
                                    $br_sql = " SELECT br_shortcut_code 
                                               FROM dmk_branch 
                                               WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
                                               AND br_shortcut_code IS NOT NULL 
                                               AND br_shortcut_code != '' 
                                               LIMIT 1 ";
                                    $br_info = sql_fetch($br_sql);
                                    if ($br_info && $br_info['br_shortcut_code']) {
                                        $order_url = '/go/' . $br_info['br_shortcut_code'];
                                    } else {
                                        $order_url = '/go/' . $member['dmk_br_id'];
                                    }
                                }
                            ?>
                                <a href="<?php echo $order_url; ?>" class="text-gray-700">
                                    <i class="fas fa-shopping-cart text-xl"></i>
                                </a>
                            <?php } ?>
                        <?php } else { ?>
                            <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="text-gray-700">
                                <i class="fas fa-comment text-xl"></i>
                            </a>
                        <?php } ?>
                    </div>
                    
                    <!-- 모바일 메뉴 버튼 -->
                    <button class="mobile-menu-btn text-gray-700" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- 모바일 메뉴 오버레이 -->
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    
    <!-- 모바일 메뉴 -->
    <div class="mobile-menu">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <img src="/assets/domaeka/logo/domaeka-logo-03-60h.png" alt="도매까" class="h-10">
                <button onclick="toggleMobileMenu()" class="text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <a href="#features" class="block text-gray-700 py-2 border-b border-gray-200" onclick="toggleMobileMenu()">주요 기능</a>
                <a href="#benefits" class="block text-gray-700 py-2 border-b border-gray-200" onclick="toggleMobileMenu()">장점</a>
                <a href="#process" class="block text-gray-700 py-2 border-b border-gray-200" onclick="toggleMobileMenu()">이용 방법</a>
                
                <?php if ($is_member) { ?>
                    <?php if ($is_admin == 'super' || $is_admin == 'dmk_admin') { ?>
                        <a href="<?php echo G5_ADMIN_URL; ?>" class="block btn-primary text-center mt-6">
                            <i class="fas fa-cog mr-2"></i>관리자
                        </a>
                    <?php } else { 
                        // 회원의 지점 정보 확인
                        $order_url = '/go/orderlist.php';
                        if ($member['dmk_br_id']) {
                            // 지점의 shortcut_code 조회
                            $br_sql = " SELECT br_shortcut_code 
                                       FROM dmk_branch 
                                       WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
                                       AND br_shortcut_code IS NOT NULL 
                                       AND br_shortcut_code != '' 
                                       LIMIT 1 ";
                            $br_info = sql_fetch($br_sql);
                            if ($br_info && $br_info['br_shortcut_code']) {
                                $order_url = '/go/' . $br_info['br_shortcut_code'];
                            } else {
                                $order_url = '/go/' . $member['dmk_br_id'];
                            }
                        }
                    ?>
                        <a href="<?php echo $order_url; ?>" class="block btn-primary text-center mt-6">
                            <i class="fas fa-shopping-cart mr-2"></i>내 주문
                        </a>
                    <?php } ?>
                    <a href="<?php echo G5_BBS_URL; ?>/logout.php" class="block text-center text-gray-700 py-2 mt-4">
                        로그아웃
                    </a>
                <?php } else { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="block btn-secondary text-center mt-6">
                        <i class="fas fa-comment mr-2"></i>카카오 로그인
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- 히어로 섹션 -->
    <section class="gradient-bg text-white relative pt-32 pb-24">
        <div class="container mx-auto px-6 text-center relative z-10">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-6 animate-fadeInUp">
                스마트한 공동구매의 시작
                <div style="margin:10px"><span class="text-indigo-200">도매까</span></div>
            </h2>
            <p class="text-base sm:text-lg md:text-xl mb-10 animate-fadeInUp max-w-2xl mx-auto" style="animation-delay: 0.2s;">
                카카오톡으로 간편하게 주문하고, 함께 구매하여 더 저렴하게!
            </p>
            <div class="animate-fadeInUp mb-20" style="animation-delay: 0.4s;">
                <?php if (!$is_member) { ?>
                <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="bg-white text-gray-800 px-6 sm:px-8 py-3 sm:py-4 rounded-full text-base sm:text-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block shadow-lg">
                    <i class="fas fa-comment mr-2"></i>카카오톡으로 시작하기
                </a>
                <?php } ?>
            </div>
        </div>
        
        <!-- 웨이브 효과 -->
        <div class="absolute bottom-0 left-0 right-0">
            <svg class="w-full" style="margin-bottom: -2px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#f9fafb" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <!-- 주요 기능 섹션 -->
    <section id="features" class="py-20">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16 scroll-reveal">주요 기능</h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- 카카오톡 주문 -->
                <div class="feature-card bg-white rounded-lg shadow-lg p-8 text-center scroll-reveal">
                    <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-comment text-indigo-500 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold mb-4">카카오톡 간편 주문</h3>
                    <p class="text-gray-600">
                        별도의 앱 설치 없이 카카오톡으로 간편하게 주문하세요. 
                        QR코드나 링크로 바로 접속 가능합니다.
                    </p>
                </div>

                <!-- 공동구매 시스템 -->
                <div class="feature-card bg-white rounded-lg shadow-lg p-8 text-center scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-users text-purple-500 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold mb-4">스마트 공동구매</h3>
                    <p class="text-gray-600">
                        여러 명이 함께 주문하여 더 저렴한 가격으로 구매하세요. 
                        실시간으로 주문 현황을 확인할 수 있습니다.
                    </p>
                </div>

                <!-- 계층형 관리 -->
                <div class="feature-card bg-white rounded-lg shadow-lg p-8 text-center scroll-reveal" style="animation-delay: 0.4s;">
                    <div class="w-20 h-20 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-sitemap text-pink-500 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold mb-4">전국 물류 네트워크</h3>
                    <p class="text-gray-600">
                        전국 단위의 물류 시스템을 운영하므로 
                        다양한 상품들을 저렴한 가격에 공급 가능합니다.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 장점 섹션 -->
    <section id="benefits" class="py-20 bg-gradient-to-br from-gray-50 to-indigo-50">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16 scroll-reveal">도매까의 장점</h2>
            
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="scroll-reveal flex items-center justify-center p-8 bg-white rounded-lg shadow-lg">
                    <img src="/assets/domaeka/logo/domaeka-logo-01.png" alt="도매까" class="w-full max-w-sm">
                </div>
                
                <div class="space-y-6 scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">설치 없이 바로 사용</h4>
                            <p class="text-gray-600">카카오톡만 있으면 별도의 앱 설치 없이 바로 주문 가능</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">실시간 재고 확인</h4>
                            <p class="text-gray-600">실시간으로 재고를 확인하고 품절 걱정 없이 주문</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">간편한 주문 관리</h4>
                            <p class="text-gray-600">주문 내역을 한눈에 확인하고 관리할 수 있는 시스템</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">저렴한 공동구매 가격</h4>
                            <p class="text-gray-600">함께 구매하여 더 저렴한 가격으로 상품 구매 가능</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 이용 방법 섹션 -->
    <section id="process" class="py-20">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16 scroll-reveal">간단한 이용 방법</h2>
            
            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center scroll-reveal">
                    <div class="w-24 h-24 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        1
                    </div>
                    <h4 class="text-xl font-semibold mb-3">카카오톡 로그인</h4>
                    <p class="text-gray-600">카카오톡 계정으로 간편하게 로그인하세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        2
                    </div>
                    <h4 class="text-xl font-semibold mb-3">상품 선택</h4>
                    <p class="text-gray-600">원하는 상품을 장바구니에 담아주세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.4s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        3
                    </div>
                    <h4 class="text-xl font-semibold mb-3">주문 완료</h4>
                    <p class="text-gray-600">배송 정보를 입력하고 주문을 완료하세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.6s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        4
                    </div>
                    <h4 class="text-xl font-semibold mb-3">상품 픽업</h4>
                    <p class="text-gray-600">주문한 상품을 매장에서 수령합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA 섹션 -->
    <section class="gradient-bg text-white py-20">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold mb-6">지금 바로 시작하세요!</h2>
            <p class="text-xl mb-10">도매까와 함께 스마트한 공동구매를 경험해보세요</p>
            <?php if (!$is_member) { ?>
            <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="bg-white text-gray-800 px-8 py-4 rounded-full text-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block">
                <i class="fas fa-comment mr-2"></i>카카오톡으로 시작하기
            </a>
            <?php } else { 
                // 회원의 지점 정보 확인 (상단과 동일한 로직)
                $order_url = '/go/orderlist.php';
                if ($member['dmk_br_id']) {
                    // 지점의 shortcut_code 조회
                    $br_sql = " SELECT br_shortcut_code 
                               FROM dmk_branch 
                               WHERE br_id = '".sql_real_escape_string($member['dmk_br_id'])."' 
                               AND br_shortcut_code IS NOT NULL 
                               AND br_shortcut_code != '' 
                               LIMIT 1 ";
                    $br_info = sql_fetch($br_sql);
                    if ($br_info && $br_info['br_shortcut_code']) {
                        $order_url = '/go/' . $br_info['br_shortcut_code'];
                    } else {
                        $order_url = '/go/' . $member['dmk_br_id'];
                    }
                }
            ?>
            <a href="<?php echo $order_url; ?>" class="bg-white text-gray-800 px-8 py-4 rounded-full text-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block">
                <i class="fas fa-shopping-cart mr-2"></i>주문하러 가기
            </a>
            <?php } ?>
        </div>
    </section>

    <!-- 푸터 -->
    <footer class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <div class="mb-4">
                        <img src="/assets/domaeka/logo/domaeka-logo-03-60h.png" alt="도매까" class="h-12 brightness-0 invert">
                    </div>
                    <p class="text-gray-400">스마트한 공동구매 플랫폼</p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">빠른 링크</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition duration-300">주요 기능</a></li>
                        <li><a href="#benefits" class="text-gray-400 hover:text-white transition duration-300">장점</a></li>
                        <li><a href="#process" class="text-gray-400 hover:text-white transition duration-300">이용 방법</a></li>
                        <li><a href="/bbs/content.php?co_id=privacy" class="text-gray-400 hover:text-white transition duration-300">개인정보처리방침</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">고객 지원</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-clock mr-2"></i>평일 09:00 - 18:00</li>
                        <li><i class="fas fa-phone mr-2"></i>02-123-4567</li>
                        <li><i class="fas fa-envelope mr-2"></i>domaeka77@gmail.com</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">회사 정보</h4>
                    <ul class="space-y-1 text-gray-400 text-sm">
                        <li>(주)강성에프엔비</li>
                        <li>대표: 이지애</li>
                        <li>사업자번호: 235-88-02859</li>
                        <li>경기도 남양주시 순화궁로 272</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-12 pt-8 text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> 도매까. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // 모바일 메뉴 토글
        function toggleMobileMenu() {
            const menu = document.querySelector('.mobile-menu');
            const overlay = document.querySelector('.mobile-menu-overlay');
            
            menu.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // 스크롤 방지
            if (menu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        // 스크롤 애니메이션
        function handleScroll() {
            const reveals = document.querySelectorAll('.scroll-reveal');
            
            reveals.forEach(element => {
                const windowHeight = window.innerHeight;
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < windowHeight - elementVisible) {
                    element.classList.add('active');
                }
            });
        }
        
        window.addEventListener('scroll', handleScroll);
        handleScroll(); // 초기 로드 시 실행
        
        // 부드러운 스크롤
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>