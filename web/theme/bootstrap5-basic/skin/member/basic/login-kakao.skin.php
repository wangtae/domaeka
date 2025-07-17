<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap');

.kakao-login-wrapper {
    font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-height: 100vh;
    background: white;
}

.kakao-login-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #e5e7eb;
    background: white;
}

.kakao-login-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    height: 64px;
    width: 100%;
    max-width: 1024px;
}

.kakao-login-title {
    font-weight: bold;
    font-size: 18px;
}

.kakao-login-content {
    text-align: center;
    margin-top: 24px;
    margin-bottom: 32px;
    padding: 0 20px;
}

.kakao-login-subtitle {
    font-size: 18px;
    line-height: 1.5;
}

.kakao-login-main {
    display: block;
    margin-top: 8px;
    font-size: 24px;
    font-weight: 500;
    line-height: 1.4;
}

.kakao-login-links {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 40px auto 0;
    padding: 0 20px;
    max-width: 400px;
}

.kakao-login-links a {
    color: black;
    text-decoration: underline;
    font-size: 14px;
    text-align: center;
}

/* 카카오 로그인 버튼 전체 감싸기 */
.kakao-login-button-wrapper {
    padding: 0 20px;
    margin: 0 auto;
    max-width: 600px;
}

/* 카카오 로그인 버튼 스타일 */
.kakao-login-button {
    display: block !important;
    width: 100% !important;
    height: 60px !important;
    position: relative !important;
    overflow: hidden !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    cursor: pointer !important;
}

/* 카카오 버튼 배경 이미지 */
.kakao-login-button::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAlgAAABkCAYAAABaQU4jAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAA6HSURBVHgB7d1NbBzneQfw/8x+cJeLXe6SIiVKokTJsh3HqWPHttPGTdPGRZoWaAO0QJGiQAP00KIXH3rpqZdeeumlQA+99VCgQFsUKFAELVqgaZo0aZLGiZPYjmPHsmRLokSK4hfJJffrzNv3HSZlylIs7nI5uzPz/wGCzGiX3iVF7j/P+77PK+Qw1tbWhJMiC2+pUsqCB0U4xvdKSKmOn6sPJaQ6hXKMxyilhWAc8xiH8GOqA+iMY/zkzxBn/dSP2T/9L8bAHEK4/JiF40KoA+Nt8f9G8PHB7T9uBBZgN7mL2UaKu5jdEQ6xsGzNQ1qlGMelPL4/DP8ybRJCCIYrmz9uAB+7oQGUcOEIww1lBKOAKRLLzYRzLJo/+aRAiUqo/v9YCdTOAh23G/xjdRk8GIcDCQCHBhBCQsJRhOKkShgCxXFGHONx11B9n1TCOcdKqD9GeQA1SJnfzLUgOdxJlhGOGOLgROyejEXG4QNxxjNK6LT+gCcUhDwlwm8JpJi8FY0QdlBe+Ig4hgO4UJn6JgoQzoYC/H8NCBhHGZ8O1J7H37NxdBWyHKVx/J2qnKLKqJfOGKz6Kw5YIlBYQhUXhOrEGJzJcRSFQB5YRB6L+JJII3sii1xcxA9n8djpFaSeEtiXFyieFKABAgcOgRQwBUQCUCL80W6xyHhKOOYdxcfgmP7/V8WnK9djQgJCqo8rr9d5kOV9JdQvdHN8+4EyFOjJCPwvOQJvUw5iFAvQRu0+yk8JoSYQQ0ZggUH4QgFAXbEQJcAQQn3V9tdwBXdRJfO2LFh7HiMsJYoKBQVPGDKlnlQKKg8YJqUcNIxKwJSRcuGKCMpHU5eJJX4SQvgihQYKqEBaXUILLgHaT3GoqlGo6vBJofoUgIGqkYcrpwL2C48iJJBz4ABHYcGXKX+kzOOBIzDGT5WOlYkQkFXJBD6iR6G+DsFFgPaXHKwjBdbj0AVeGdcBywxOQqrCqgaXqisXD4Cl7qIILEJ8xaojOaGAKSdYWUGvJseuOPBmPKSQRBkJlJBEBUmU9eMJWMigjCHYeAQzOIgxzGEcczgFE9cBgqBx5IQQJTzm8nJCOBJJJZGGJRIi+BBAa6mCJ9Q6oqxKFKBGCgJR1JCBhUfhIoUy0qjAho0sHkQJ51vb2PqrQgjrMgDaH8FBWEfB2oj5vJCigMfhqXNwVFJ9GxHhwf5L1XzP/VKJCaQ4ARTlAAtoLUdYClRbg5SlQN6yxVJvEdaFbx7fhiSl21rYy15rQojChVU3V72gZ5ZPL8aGQp1ElxUHrFK47FBN7lcuEugdxAJOYQETOMdLdJHmGlHCU6hVgrfn1TqiQgjXOAFQQJXx0yD0MFx+DEJ9e7TBv90dKIcfoRw+jm2pxUk4+Mx2wF4UQpQvI7AbLQXLEsJKoJmqQ0x+6GKJhBc5cI8pCLsBfyO9j4jIJtOa7/+FqkL9MQqoQK3pP7+q39S3M7hGEONR/DsexW0s46f0m3/H2FkGMcK7BusbKLh3t9+HAbQ5WRKOiOoOQrKb0AcL6H5xdQQXsYrz+k21vGBi+0Qmqe4JkWz3wOcFqy2sGi5DBUlcQE0FqjMdxikcxSyO4BSilGCNzlJbhfWXsLG3+UECYIm9VKQ8b9tSdQvBU4PWKV8dBUKsAAbQiJpSNVGHXRaigGxrm1YJLGMJy3r/qozgBq6JC/1G77pVhABU8QJY2RtWPQqXv6kcOazO6I5A8Y/v+Og8rQOJmrBVzRYrlhS9UaQkJBqJSQ48kUIRJ3EJf6r/3gFs6N2DpzGOSX1xG7tUh7GCqNiRLRGx7nRWgQu1VqhsIaRzGAn9vLDOY/qCqQxUhCNTcfUs7HEKqx6GZa9hFz9DwV0GHHRtcRUL+JiwzgOE7sKyJuViALqPMBUIxXF3Fb3r6uUqXNgwMYQTmNEbrLfbdTiJKUxhCjM4oQNYTJnB/w7Lv3/tJoT6wP8DQ6iV0PvBqoYnfJlSxyFQxCcvdRg8zO4/u5qpEJuoyYV2p/0+H3LPqRl7FrOYxZP4Ai5hCgfwFBaRQRHDKOF/yy1WEcJFE5G9YNlCOIchLv4qQH2m6gZXCd2lhD40VCWgqsJGEiXdQXUKUziOab0vJLFXEkdQx4Xeb/WKGuTn4JbKrR5SfgJdJk6a5pU6hgtHaYdxe5+d6F7WNz4n//vN4A7GGQZQD3m1y0DI9UcOkN11TaJT3cOlfsyyUdSlwBUoJVEfIh6AJQSECJz8fM0G2c0CQNGqCCczz2/AEFaxhGkdqLgoKWoYGfTnP3IUsGRD7YIgVB1XhJu9dHdwINB0s9SzwkYFaV0F9GEgAxNjWBBn+o1+xAOrfsjM0jNpPdO8IjJdGhyKUy+jGCzHMIQzHW0nArQ7Dly2LiJcCgbQfSxUcdFdYeIgTuAczmEBNzGALZjBbsU1fXOwjYQaFJcBB1WRvXBaQHjrO3cMOrJeBx/O7dPlwBh16yp/2EQVA0Gk0DlKKAOJA9J5pQy/tRhv0jVh4jfxGm7iMu7h7/E8yjoI7kIUmvlNXhRiCmJBFpRdVxUs6C79lBBIQQhfrMmE+vv+5JsEBDqxvwXQkJXNUgJGMNxKz1o1MlBNECgcBNqjgjzywYu2MwgC1TrsQJuCkJo2cJGGlYcruqFzJQJFpNQGksyiQ6oJVJeNu3YcQsUvT9yLINa8wkBCW9DuGLxzKn/qXnXz+M2hh5DHvN5LWkaQ1jhwQehBGiSu3BV0x8/g1aBJdUoZD6qb6Gy5ycRpWJhT5/Ah/oNLgLFiJJRbvBb7cYwfvhBW3VOJ9AZCsKHNVm4EiQcsixM7S4iJELvBuiQJ4+lHN6sqsFFFBRkEAzuSGMWMCL7U1lK9wJJCdaWa5ooN4eotq1CWENYULJy9e4jYFvR9Z7vRXdYbqoSSvkrOwcKsOivM5l4G1HW2bfUcRLfZsSSu/P2aG1w6VD4KCJ5GyqKIx7CIBzCNSZzFcxCoBGfL7JBIxGC2kCeKN9Xu1JyKugb7tJuDgyCJeJIcGLQnD4qhgjSWsagfW8eQfpzE6fZP/G2TbZu5j4Yl1mZ3pjZl92DtoSHNq0Uw0WsjCqBJwUGoMJAwUEQO0ziMe8hjAQ/oPaXJbiP4b7xKiKYO+u7ByjxkHf1z7AOwQigWRJrz7H1TsO8yBBM1pBBkwOgZvqRB5VzCdEIgxcqXfxsrAV3vKnT1NmjJGFhGHgsIJsKfwzRmdCZMCyfFsyIiJBOLxOyBkMuLtXzRYxI5KdTdgwDZnRvsCpOQYgIJrOGo3ph7F7O4hAX9WE2fBzWC39TtXhBNkVQJuQOhnpM3+eRZQJoRRwqEK1SnS6z8VNFHB1WQQxJFvRy4iCV9CkRe9y5MISEOCuFXMJsCKT+j6sN6AzQ7iuPCeUHOwEB3BcmwJJI4hmn9VPcJzOpAZmLvQ/CZJCIhcOGJ9LcAAhU09sHqhQSKePCwR7CIw7ijs0FyNchzDbsQdKRKu39WiNQJQJIJzOIo7um9p4NY0ocsNrNEg+h5b0sR0b9Z9P6fP65qbEI0oGOJcOCVBGOcdZ5BAkW8ggRWcULnaRzGIl6ChSaRJiqKBCtBBXJCfQ8gkBLJGvJY1K8u7+KjmMAp3MEC7ulDFqOa1tLTz02K9F4cz4C6EtcNUH7hxu5EvH5TQcU9K6TXhGZo2VyeFEJNXjvlD6HJAhKYwxTu4RQm8SwexlGcEcJxXfRsyaHHk3yjJQKhRGRKgcWv5I9PcI+gQJJKQGVzG6zOJJBBBUsYxjGcx0O4gcdxo8kyBAOJBEINAw46eYHYzTmwOpOPKocCBsQJEdGhvqGQHoJfDd4ioIKKoGaWJj8Jm5JCMVdVJxn7xFsEGimhglUs4w4u4jie1vtQ93ACz2AOL2AQMziBdH8zfnYoqURE94MKMQbhLvQjYKFZfJgcN7gSkAJIo4w5PMjLgeHzBBJlCCGJG6IA/f9VaWvnDaxI/KXKQX0/6FSgSBd4GJjHbVzCS7iE72EQ72FYHyVTRQa2tFET4jZ6KxKsOluqDzLjqG7LqnYjO4sFnMcazuE0juhbfvjS7BSUYBagJjKYE/M7nzQOWMQQIq6dWMJWQRHDOqnJEs7hJC7gAQziLVzAjM7h5gZ9ARmEwdbOzxMgHIgDljkfQQFLWQJJbEGggKByyK8ikTh6y9RBqwBbdwqmUIIFFzYKqAUryKYOXNDYT8rBPTEZPIaT4VmCf9jfXM8TQvibdON6D5YUQi0jKD3HaR8V3SJnAYrjdFU2hLSPKiJMYkFnvxzSS32uDlxxvWXOvJfCw8iINTNzPo2IJMiXQjmnhCqKCBJ9x2u8dJ8aZnSr3/uY0xvU72IYW3gBpg5YJnJCOKaMb2WJ9ByJbCt6MFiQP1tYwgJu67UtKTh6CW+4P6iIGd4h8jvdxKRQ3qIQorQE4YmNMOvJQQgtdU2IwqMI12LRQCXEvQhwRoiuKOL3sYUBJHA9mK1HbEw8nN8s6C0HgSAphRBCD8hfE2LN+kkkGJ7FkYdaBJlJqRdEFJN8P7xT0EL4GSQcO7H/uIBV10t+FQRTQRkxsZ6AMK7rXQKppLKhgiBHlZR/DiLBilCyLF2MJqnDBJrJQAzCRQZlpFBEBUmUkcGcOOsLBE3v6eKO0WMgD9KfKvXGX/4PsGf+kIyMhrgAAAAASUVORK5CYII=') !important;
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
}

.kakao-login-button:hover::before {
    opacity: 0.95 !important;
}

/* 부트스트랩 테마 소셜 로그인 숨기기 */
.kakao-login-button-wrapper #sns_login {
    display: none !important;
}
</style>

<div class="kakao-login-wrapper">
    <!-- Navigation -->
    <nav class="kakao-login-nav">
        <header class="kakao-login-header">
            <div class="kakao-login-title">우리공구 로그인</div>
        </header>
    </nav>
    
    <!-- Main Content -->
    <div class="kakao-login-content">
        <span class="kakao-login-subtitle">간편하게 로그인하고</span><br>
        <span class="kakao-login-main">
            우리동네 공동구매를<br> 
            경험해 보세요
        </span>
    </div>
    
    <!-- Kakao Login Button -->
    <div class="kakao-login-button-wrapper">
        <?php if ($config['cf_social_login_use'] && $social_login_html) { ?>
            <!-- 기존 소셜 로그인 숨김 -->
            <?php echo $social_login_html; ?>
        <?php } ?>
        
        <!-- 카카오 로그인 버튼 -->
        <a href="<?php echo G5_BBS_URL; ?>/login.php?provider=kakao&amp;url=<?php echo urlencode($url); ?>" 
           class="kakao-login-button">
        </a>
    </div>
    
    <!-- Additional Links -->
    <div class="kakao-login-links">
        <a href="<?php echo G5_BBS_URL ?>/login.php<?php echo $url ? '?url='.$url : ''; ?>">
            전화번호 로그인
        </a>
    </div>
</div>