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
        
        /* 그라디언트 배경 */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* 카드 호버 효과 */
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
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
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold gradient-text">도매까</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="#features" class="text-gray-700 hover:text-purple-600 transition duration-300">주요 기능</a>
                    <a href="#benefits" class="text-gray-700 hover:text-purple-600 transition duration-300">장점</a>
                    <a href="#process" class="text-gray-700 hover:text-purple-600 transition duration-300">이용 방법</a>
                    <?php if ($is_member) { ?>
                        <?php if ($is_admin == 'super' || $is_admin == 'dmk_admin') { ?>
                            <a href="<?php echo G5_ADMIN_URL; ?>" class="bg-purple-600 text-white px-6 py-2 rounded-full hover:bg-purple-700 transition duration-300">
                                <i class="fas fa-cog mr-2"></i>관리자
                            </a>
                        <?php } else { ?>
                            <a href="/go/orderlist.php" class="bg-purple-600 text-white px-6 py-2 rounded-full hover:bg-purple-700 transition duration-300">
                                <i class="fas fa-shopping-cart mr-2"></i>내 주문
                            </a>
                        <?php } ?>
                        <a href="<?php echo G5_BBS_URL; ?>/logout.php" class="text-gray-700 hover:text-purple-600 transition duration-300">
                            로그아웃
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="bg-yellow-400 text-black px-6 py-2 rounded-full hover:bg-yellow-500 transition duration-300">
                            <i class="fas fa-comment mr-2"></i>카카오 로그인
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 히어로 섹션 -->
    <section class="gradient-bg text-white pt-32 pb-20">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-5xl font-bold mb-6 animate-fadeInUp">
                스마트한 공동구매의 시작<br>
                <span class="text-yellow-300">도매까</span>
            </h2>
            <p class="text-xl mb-10 animate-fadeInUp" style="animation-delay: 0.2s;">
                카카오톡으로 간편하게 주문하고, 함께 구매하여 더 저렴하게!
            </p>
            <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                <?php if (!$is_member) { ?>
                <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="bg-yellow-400 text-black px-8 py-4 rounded-full text-lg font-semibold hover:bg-yellow-500 transition duration-300 inline-block">
                    <i class="fas fa-comment mr-2"></i>카카오톡으로 시작하기
                </a>
                <?php } ?>
            </div>
        </div>
        
        <!-- 웨이브 효과 -->
        <div class="relative">
            <svg class="absolute bottom-0 w-full" style="margin-bottom: -2px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
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
                    <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-comment text-yellow-500 text-3xl"></i>
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
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-sitemap text-blue-500 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold mb-4">체계적인 관리</h3>
                    <p class="text-gray-600">
                        본사-총판-대리점-지점의 계층형 구조로 
                        효율적인 상품 및 주문 관리가 가능합니다.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 장점 섹션 -->
    <section id="benefits" class="py-20 bg-gray-100">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16 scroll-reveal">도매까의 장점</h2>
            
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="scroll-reveal">
                    <img src="https://via.placeholder.com/600x400/667eea/ffffff?text=도매까+장점" alt="도매까 장점" class="rounded-lg shadow-lg w-full">
                </div>
                
                <div class="space-y-6 scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">설치 없이 바로 사용</h4>
                            <p class="text-gray-600">카카오톡만 있으면 별도의 앱 설치 없이 바로 주문 가능</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">실시간 재고 확인</h4>
                            <p class="text-gray-600">실시간으로 재고를 확인하고 품절 걱정 없이 주문</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold mb-2">간편한 주문 관리</h4>
                            <p class="text-gray-600">주문 내역을 한눈에 확인하고 관리할 수 있는 시스템</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-purple-600"></i>
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
                    <div class="w-24 h-24 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        1
                    </div>
                    <h4 class="text-xl font-semibold mb-3">카카오톡 로그인</h4>
                    <p class="text-gray-600">카카오톡 계정으로 간편하게 로그인하세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        2
                    </div>
                    <h4 class="text-xl font-semibold mb-3">상품 선택</h4>
                    <p class="text-gray-600">원하는 상품을 장바구니에 담아주세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.4s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        3
                    </div>
                    <h4 class="text-xl font-semibold mb-3">주문 완료</h4>
                    <p class="text-gray-600">배송 정보를 입력하고 주문을 완료하세요</p>
                </div>
                
                <div class="text-center scroll-reveal" style="animation-delay: 0.6s;">
                    <div class="w-24 h-24 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                        4
                    </div>
                    <h4 class="text-xl font-semibold mb-3">배송 받기</h4>
                    <p class="text-gray-600">편하게 상품을 받아보세요</p>
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
            <a href="<?php echo G5_BBS_URL; ?>/login-kakao.php" class="bg-yellow-400 text-black px-8 py-4 rounded-full text-lg font-semibold hover:bg-yellow-500 transition duration-300 inline-block">
                <i class="fas fa-comment mr-2"></i>카카오톡으로 시작하기
            </a>
            <?php } else { ?>
            <a href="/go/orderlist.php" class="bg-white text-purple-600 px-8 py-4 rounded-full text-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block">
                <i class="fas fa-shopping-cart mr-2"></i>주문하러 가기
            </a>
            <?php } ?>
        </div>
    </section>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-2xl font-bold mb-4">도매까</h3>
                    <p class="text-gray-400">스마트한 공동구매 플랫폼</p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">빠른 링크</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition duration-300">주요 기능</a></li>
                        <li><a href="#benefits" class="text-gray-400 hover:text-white transition duration-300">장점</a></li>
                        <li><a href="#process" class="text-gray-400 hover:text-white transition duration-300">이용 방법</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">고객 지원</h4>
                    <ul class="space-y-2">
                        <li class="text-gray-400">평일 09:00 - 18:00</li>
                        <li class="text-gray-400">support@domaeka.com</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">소셜 미디어</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-facebook-f text-2xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-youtube text-2xl"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-12 pt-8 text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> 도매까. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
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