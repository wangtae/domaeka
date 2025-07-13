<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title'] ?> - <?php echo $config['cf_title'] ?></title>
    
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
            background-color: var(--default-100);
            color: var(--foreground);
            margin: 0;
            padding: 0;
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--default-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--primary-foreground);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary:hover {
            background-color: #0ea968;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid var(--primary);
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-secondary:hover {
            background-color: rgba(16, 185, 129, 0.05);
            transform: translateY(-1px);
        }
        
        .checkbox-custom {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid var(--default-500);
            border-radius: 0.25rem;
            margin-right: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkbox-custom:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .login-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 2rem;
            width: 100%;
            max-width: 24rem;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="login-card">
            <!-- Logo/Title -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">도매까</h1>
                <p class="text-gray-600">로그인하여 서비스를 이용하세요</p>
            </div>
            
            <!-- Login Form -->
            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post">
                <input type="hidden" name="url" value="<?php echo $login_url ?>">
                
                <div class="space-y-4">
                    <!-- ID Input -->
                    <div>
                        <label for="login_id" class="block text-sm font-medium text-gray-700 mb-2">
                            아이디
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   name="mb_id" 
                                   id="login_id" 
                                   required 
                                   class="input-field pl-10" 
                                   placeholder="아이디를 입력하세요">
                            <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Password Input -->
                    <div>
                        <label for="login_pw" class="block text-sm font-medium text-gray-700 mb-2">
                            비밀번호
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   name="mb_password" 
                                   id="login_pw" 
                                   required 
                                   class="input-field pl-10" 
                                   placeholder="비밀번호를 입력하세요">
                            <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Remember Me & Find Password -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="auto_login" 
                                   id="login_auto_login" 
                                   class="checkbox-custom">
                            <span class="text-sm text-gray-600">자동 로그인</span>
                        </label>
                        <a href="<?php echo G5_BBS_URL ?>/password_lost.php" 
                           class="text-sm text-primary hover:underline">
                            아이디/비밀번호 찾기
                        </a>
                    </div>
                    
                    <!-- Login Button -->
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        로그인
                    </button>
                </div>
            </form>
            
            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">또는</span>
                </div>
            </div>
            
            <!-- Social Login -->
            <?php if ($social_login_html) { ?>
            <div class="space-y-3">
                <?php echo $social_login_html; ?>
            </div>
            <?php } ?>
            
            <!-- Sign Up Link -->
            <div class="text-center mt-6">
                <p class="text-sm text-gray-600">
                    아직 회원이 아니신가요?
                    <a href="<?php echo G5_BBS_URL ?>/register.php" 
                       class="text-primary font-medium hover:underline">
                        회원가입
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <?php // 쇼핑몰 사용시 비회원 구매 ?>
    <?php if (isset($default['de_level_sell']) && $default['de_level_sell'] == 1 && preg_match("/orderform.php/", $url)) { ?>
    <div class="mt-8 max-w-md mx-auto px-4">
        <div class="login-card">
            <h2 class="text-lg font-bold mb-4">비회원 구매</h2>
            <p class="text-sm text-gray-600 mb-4">
                비회원으로 주문하시는 경우 포인트는 지급하지 않습니다.
            </p>
            
            <div class="bg-gray-50 p-4 rounded-lg mb-4 text-sm text-gray-700">
                <?php echo conv_content($default['de_guest_privacy'], $config['cf_editor']); ?>
            </div>
            
            <label class="flex items-center mb-4 cursor-pointer">
                <input type="checkbox" id="agree" value="1" class="checkbox-custom">
                <span class="text-sm text-gray-600">
                    개인정보수집에 대한 내용을 읽었으며 이에 동의합니다.
                </span>
            </label>
            
            <button onclick="guest_submit(document.flogin)" class="btn-secondary w-full">
                <i class="fas fa-shopping-cart mr-2"></i>
                비회원으로 구매하기
            </button>
        </div>
    </div>
    <?php } ?>
    
    <?php // 비회원 주문조회 ?>
    <?php if (isset($default['de_level_sell']) && $default['de_level_sell'] == 1 && preg_match("/orderinquiry.php$/", $url)) { ?>
    <div class="mt-8 max-w-md mx-auto px-4">
        <div class="login-card">
            <h2 class="text-lg font-bold mb-4">비회원 주문조회</h2>
            <p class="text-sm text-gray-600 mb-4">
                메일로 발송해드린 주문서의 <strong>주문번호</strong> 및 주문 시 입력하신 <strong>비밀번호</strong>를 정확히 입력해주십시오.
            </p>
            
            <form name="forderinquiry" method="post" action="<?php echo urldecode($url); ?>" autocomplete="off">
                <div class="space-y-4">
                    <div>
                        <label for="od_id" class="block text-sm font-medium text-gray-700 mb-2">
                            주문번호
                        </label>
                        <input type="text" 
                               name="od_id" 
                               value="<?php echo $od_id; ?>" 
                               id="od_id" 
                               required 
                               class="input-field" 
                               placeholder="주문번호를 입력하세요">
                    </div>
                    
                    <div>
                        <label for="od_pwd" class="block text-sm font-medium text-gray-700 mb-2">
                            비밀번호
                        </label>
                        <input type="password" 
                               name="od_pwd" 
                               id="od_pwd" 
                               required 
                               class="input-field" 
                               placeholder="비밀번호를 입력하세요">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search mr-2"></i>
                        주문 조회
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>

    <script>
    // Auto login confirmation
    document.getElementById('login_auto_login')?.addEventListener('click', function() {
        if (this.checked) {
            this.checked = confirm("자동로그인을 사용하시면 다음부터 회원아이디와 비밀번호를 입력하실 필요가 없습니다.\n\n공공장소에서는 개인정보가 유출될 수 있으니 사용을 자제하여 주십시오.\n\n자동로그인을 사용하시겠습니까?");
        }
    });

    // Guest purchase
    function guest_submit(f) {
        if (document.getElementById('agree')) {
            if (!document.getElementById('agree').checked) {
                alert("개인정보수집에 대한 내용을 읽고 이에 동의하셔야 합니다.");
                return;
            }
        }

        f.url.value = "<?php echo $url; ?>";
        f.action = "<?php echo $url; ?>";
        f.submit();
    }

    // Form submission
    function flogin_submit(f) {
        if( document.body.dispatchEvent && document.body.dispatchEvent(new CustomEvent('login_sumit', {detail: {form: f, name: 'flogin'}})) !== false ){
            return true;
        }
        return false;
    }
    </script>
</body>
</html>