<?php
// 루트 URL 접속 시 관리자 로그인 페이지로 리다이렉션

include_once('./_common.php');

// 이미 로그인 중이라면 관리자 페이지로 리다이렉션
if ($is_admin || $is_member) {
    goto_url(G5_ADMIN_URL);
}

// 로그인 액션 URL
$login_action_url = G5_BBS_URL . "/login_check.php";
$login_url = G5_URL; // 로그인 성공 후 이동할 기본 URL

// CSRF 토큰 생성 (필요한 경우)
// if (function_exists(\'get_login_token\')) {
//     $token = get_login_token();
// } else {
//     $token = ""; // 또는 다른 방식으로 토큰 생성
// }

// 페이지 타이틀 설정
$g5['title'] = '관리자 로그인';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?> - 도매까 관리 시스템</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;700&display=swap');

        body {
            font-family: 'Noto Sans KR', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #1a202c;
            color: #e2e8f0;
            transition: background-color 0.3s ease;
        }

        .login-container {
            background-color: #2d3748;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            text-align: center;
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }

        .login-container h1 {
            color: #63b3ed;
            margin-bottom: 30px;
            font-size: 2em;
            font-weight: 700;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #cbd5e0;
        }

        .input-group input[type="text"],
        .input-group input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background-color: #242b38;
            color: #e2e8f0;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        .input-group input[type="text"]:focus,
        .input-group input[type="password"]:focus {
            border-color: #63b3ed;
            outline: none;
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background-color: #63b3ed;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-button:hover {
            background-color: #4299e1;
        }

        .links {
            margin-top: 25px;
            font-size: 0.9em;
        }

        .links a {
            color: #90cdf4;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #63b3ed;
        }

        .footer-text {
            margin-top: 30px;
            font-size: 0.8em;
            color: #a0aec0;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>도매까 관리 시스템</h1>
        <form name="flogin" action="<?php echo $login_action_url; ?>" onsubmit="return flogin_submit(this);" method="post">
            <input type="hidden" name="url" value="<?php echo $login_url; ?>">
            <?php // if ($token) { ?>
                <!-- <input type="hidden" name="token" value="<?php echo $token; ?>"> -->
            <?php // } ?>

            <div class="input-group">
                <label for="mb_id">아이디</label>
                <input type="text" name="mb_id" id="mb_id" placeholder="관리자 아이디" required autofocus>
            </div>
            <div class="input-group">
                <label for="mb_password">비밀번호</label>
                <input type="password" name="mb_password" id="mb_password" placeholder="비밀번호" required>
            </div>
            <button type="submit" class="login-button">로그인</button>
        </form>
        <div class="links">
            <a href="<?php echo G5_BBS_URL; ?>/password_lost.php">아이디/비밀번호 찾기</a>
            <a href="<?php echo G5_BBS_URL; ?>/register.php">회원가입</a>
        </div>
        <p class="footer-text">© <?php echo date("Y"); ?> Domaeka. All rights reserved.</p>
    </div>

    <script>
        function flogin_submit(f) {
            // 이메일 유효성 검사는 관리자 ID와는 다를 수 있으므로 주석 처리
            // if (!f.mb_email.value) {
            //     alert("이메일을 입력하세요.");
            //     f.mb_email.focus();
            //     return false;
            // }
            // var emailRegex = /^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/;
            // if (!emailRegex.test(f.mb_email.value)) {
            //     alert("유효한 이메일 주소를 입력하세요.");
            //     f.mb_email.focus();
            //     return false;
            // }

            if (!f.mb_id.value) {
                alert("아이디를 입력하세요.");
                f.mb_id.focus();
                return false;
            }
            if (!f.mb_password.value) {
                alert("비밀번호를 입력하세요.");
                f.mb_password.focus();
                return false;
            }
            return true;
        }
    </script>
</body>
</html>