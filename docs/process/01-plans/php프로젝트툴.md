# PHP 웹 프로젝트 AI 협업 도구 가이드

## 📋 개요

PHP 웹 프로젝트에서 AI와 효과적으로 협업하기 위한 개발 환경 설정 및 도구 활용 방법을 안내합니다. 로컬 개발 환경(Nginx + PHP + MariaDB)에서 AI가 문제를 신속하게 진단하고 해결할 수 있도록 필요한 로그, 디버깅, 모니터링 도구들을 설정합니다.

## 🛠️ 기본 개발 환경

### 환경 구성
- **웹 서버**: Nginx
- **언어**: PHP 8.3
- **데이터베이스**: MariaDB 10.11
- **운영체제**: Linux (WSL2)
- **AI 협업 도구**: MCP (Model Context Protocol)

### 설치된 MCP 서버
- **filesystem**: 파일 시스템 접근
- **mysql**: 데이터베이스 조회 및 관리
- **playwright**: 웹 브라우저 자동화 및 테스트

## 📊 로그 관리 및 모니터링

### 1. Nginx 로그

#### 로그 파일 위치
```bash
# 일반 Nginx 로그
/var/log/nginx/access.log      # 접근 로그
/var/log/nginx/error.log       # 에러 로그

# 프로젝트별 로그 (domaeka.local)
/var/log/nginx/domaeka.local_access.log    # 접근 로그
/var/log/nginx/domaeka.local_error.log     # 에러 로그
```

#### AI가 알아야 할 Nginx 로그 정보
- **접근 로그**: HTTP 요청, 응답 코드, 응답 시간, 사용자 에이전트
- **에러 로그**: 서버 에러, 설정 문제, 연결 오류
- **로그 회전**: 매일 자동 회전되며 압축 보관

#### Nginx 로그 분석 명령어
```bash
# 최근 에러 확인
sudo tail -f /var/log/nginx/domaeka.local_error.log

# 특정 시간대 접근 로그 확인
sudo grep "$(date '+%d/%b/%Y:%H')" /var/log/nginx/domaeka.local_access.log

# 404 에러 확인
sudo grep " 404 " /var/log/nginx/domaeka.local_access.log

# 5xx 서버 에러 확인
sudo grep " 5[0-9][0-9] " /var/log/nginx/domaeka.local_access.log
```

### 2. PHP 로그

#### 로그 파일 위치
```bash
# PHP-FPM 로그
/var/log/php8.3-fpm.log        # PHP-FPM 프로세스 로그

# PHP 에러 로그 (php.ini 설정에 따라)
/var/log/php_errors.log        # PHP 스크립트 에러
```

#### PHP 로그 설정 확인
```bash
# 현재 PHP 설정 확인
php -i | grep log

# PHP-FPM 설정 파일 위치
/etc/php/8.3/fpm/php.ini
/etc/php/8.3/fpm/pool.d/www.conf
```

#### 권장 PHP 로그 설정
```ini
; php.ini 설정
log_errors = On
error_log = /var/log/php_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off (운영환경)
display_startup_errors = Off
```

#### PHP 로그 분석 명령어
```bash
# PHP-FPM 로그 실시간 모니터링
sudo tail -f /var/log/php8.3-fpm.log

# PHP 에러 로그 확인
sudo tail -f /var/log/php_errors.log

# 치명적 에러 확인
sudo grep "FATAL" /var/log/php_errors.log
sudo grep "Parse error" /var/log/php_errors.log
```

### 3. MariaDB 로그

#### 로그 파일 위치
```bash
# MariaDB 로그
/var/log/mysql/error.log       # 데이터베이스 에러 로그
/var/log/mysql/mysql-slow.log  # 슬로우 쿼리 로그 (활성화된 경우)
```

#### MariaDB 로그 설정 확인
```sql
-- 현재 로그 설정 확인
SHOW VARIABLES LIKE 'log_error%';
SHOW VARIABLES LIKE 'slow_query%';
SHOW VARIABLES LIKE 'general_log%';
```

#### 권장 MariaDB 로그 설정
```ini
# my.cnf 또는 50-server.cnf
[mysqld]
log_error = /var/log/mysql/error.log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
```

## 🐛 PHP 디버깅 도구

### 1. Xdebug 설정

#### 설치 및 설정
```bash
# Xdebug 설치
sudo apt-get install php8.3-xdebug

# 설정 파일 생성
sudo nano /etc/php/8.3/mods-available/xdebug.ini
```

#### Xdebug 권장 설정
```ini
; /etc/php/8.3/mods-available/xdebug.ini
zend_extension=xdebug
xdebug.mode=debug,develop,coverage,profile
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.log=/var/log/xdebug.log
xdebug.log_level=7

; 프로파일링 설정
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p

; 코드 커버리지 설정
xdebug.coverage_enable=1
```

#### Xdebug 활성화
```bash
# Xdebug 모듈 활성화
sudo phpenmod xdebug

# PHP-FPM 재시작
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

# Xdebug 상태 확인
php -m | grep xdebug
```

### 2. 성능 프로파일링

#### PHP OpCache 설정
```ini
; opcache 설정 최적화
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.save_comments=1
```

#### 성능 모니터링 명령어
```bash
# OpCache 상태 확인
php -r "print_r(opcache_get_status());"

# 메모리 사용량 확인
php -r "echo memory_get_peak_usage(true) . ' bytes';"

# 실행 시간 측정
time php your_script.php
```

### 3. 에러 추적 및 로깅

#### 커스텀 에러 핸들러 예제
```php
<?php
// 개발환경용 에러 핸들러
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
    ];
    
    error_log(json_encode($error_data), 3, '/var/log/php_custom_errors.log');
    
    // AI가 읽기 쉬운 형태로 추가 로깅
    $ai_log = sprintf(
        "[%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $errstr,
        basename($errfile),
        $errline
    );
    error_log($ai_log, 3, '/var/log/php_ai_errors.log');
}

set_error_handler('customErrorHandler');
?>
```

## 📈 모니터링 및 분석 도구

### 1. 실시간 로그 모니터링

#### 멀티로그 모니터링 스크립트
```bash
#!/bin/bash
# watch_logs.sh - 모든 로그를 실시간으로 모니터링

gnome-terminal --tab --title="Nginx Access" -- bash -c "sudo tail -f /var/log/nginx/domaeka.local_access.log"
gnome-terminal --tab --title="Nginx Error" -- bash -c "sudo tail -f /var/log/nginx/domaeka.local_error.log"
gnome-terminal --tab --title="PHP-FPM" -- bash -c "sudo tail -f /var/log/php8.3-fpm.log"
gnome-terminal --tab --title="PHP Errors" -- bash -c "sudo tail -f /var/log/php_errors.log"
gnome-terminal --tab --title="MySQL Error" -- bash -c "sudo tail -f /var/log/mysql/error.log"
```

### 2. AI를 위한 로그 분석 스크립트

#### 통합 로그 분석기
```bash
#!/bin/bash
# analyze_logs.sh - AI가 읽기 쉬운 형태로 로그 요약

echo "=== 최근 1시간 로그 요약 ==="
echo "현재 시간: $(date)"
echo ""

echo "--- Nginx 에러 (최근 1시간) ---"
sudo grep "$(date '+%d/%b/%Y:%H' -d '1 hour ago')\|$(date '+%d/%b/%Y:%H')" /var/log/nginx/domaeka.local_error.log | tail -20

echo ""
echo "--- PHP 에러 (최근 1시간) ---"
sudo find /var/log -name "*php*" -type f -exec grep "$(date '+%Y-%m-%d %H' -d '1 hour ago')\|$(date '+%Y-%m-%d %H')" {} \; | tail -20

echo ""
echo "--- 데이터베이스 에러 (최근 1시간) ---"
sudo grep "$(date '+%Y-%m-%d %H' -d '1 hour ago')\|$(date '+%Y-%m-%d %H')" /var/log/mysql/error.log 2>/dev/null | tail -10

echo ""
echo "--- 시스템 리소스 사용량 ---"
echo "메모리 사용량: $(free -h | grep Mem | awk '{print $3 "/" $2}')"
echo "디스크 사용량: $(df -h / | tail -1 | awk '{print $3 "/" $2 " (" $5 ")"}')"
echo "CPU 로드: $(uptime | awk -F'load average:' '{print $2}')"
```

### 3. 성능 벤치마킹

#### 웹 성능 테스트 스크립트
```bash
#!/bin/bash
# performance_test.sh - 웹 성능 테스트

URL="http://domaeka.local"
REQUESTS=100
CONCURRENCY=10

echo "=== 웹 성능 테스트 시작 ==="
echo "URL: $URL"
echo "요청 수: $REQUESTS"
echo "동시 연결: $CONCURRENCY"
echo ""

# Apache Bench 테스트
ab -n $REQUESTS -c $CONCURRENCY $URL/

echo ""
echo "=== PHP-FPM 상태 ==="
sudo systemctl status php8.3-fpm --no-pager

echo ""
echo "=== Nginx 상태 ==="
sudo systemctl status nginx --no-pager
```

## 🔧 AI 협업을 위한 권장 설정

### 1. 로그 권한 설정

```bash
# AI가 로그에 접근할 수 있도록 권한 설정
sudo usermod -a -G adm $USER
sudo chmod 644 /var/log/nginx/*.log
sudo chmod 644 /var/log/php*.log
sudo chmod 644 /var/log/mysql/error.log
```

### 2. 프로젝트별 로그 디렉토리

```bash
# 프로젝트 전용 로그 디렉토리 생성
mkdir -p /home/wangt/projects/client/domaeka/domaeka.dev/log/
sudo ln -s /var/log/nginx/domaeka.local_access.log /home/wangt/projects/client/domaeka/domaeka.dev/log/nginx_access.log
sudo ln -s /var/log/nginx/domaeka.local_error.log /home/wangt/projects/client/domaeka/domaeka.dev/log/nginx_error.log
sudo ln -s /var/log/php8.3-fpm.log /home/wangt/projects/client/domaeka/domaeka.dev/log/php_fpm.log
```

### 3. AI에게 제공할 핵심 정보

#### 문제 발생 시 AI에게 제공할 로그 명령어
```bash
# 종합 상태 체크
sudo systemctl status nginx php8.3-fpm mariadb --no-pager

# 최근 에러 로그 (최근 100줄)
sudo tail -100 /var/log/nginx/domaeka.local_error.log
sudo tail -100 /var/log/php8.3-fpm.log
sudo tail -100 /var/log/mysql/error.log

# 현재 실행 중인 프로세스
ps aux | grep -E "(nginx|php-fpm|mysql)"

# 네트워크 연결 상태
sudo netstat -tlnp | grep -E ":80|:443|:3306|:9000"

# 디스크 및 메모리 상태
df -h
free -h
```

## 🎯 AI 협업 워크플로우

### 1. 문제 발생 시 진단 절차

1. **서비스 상태 확인**
   ```bash
   sudo systemctl status nginx php8.3-fpm mariadb
   ```

2. **로그 확인**
   ```bash
   # 에러 로그 우선 확인
   sudo tail -50 /var/log/nginx/domaeka.local_error.log
   sudo tail -50 /var/log/php8.3-fpm.log
   ```

3. **AI에게 정보 제공**
   - 발생한 문제 상황 설명
   - 관련 로그 내용
   - 최근 변경사항
   - 재현 단계

### 2. 성능 이슈 진단

1. **성능 지표 수집**
   ```bash
   # 응답 시간 측정
   curl -w "@curl-format.txt" -o /dev/null -s "http://domaeka.local"
   ```

2. **리소스 사용량 확인**
   ```bash
   top -p $(pgrep -d',' -f "nginx\|php-fpm\|mysql")
   ```

3. **슬로우 쿼리 분석**
   ```sql
   -- MySQL에서 슬로우 쿼리 확인
   SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
   ```

### 3. 개발 중 디버깅

1. **Xdebug 활성화 확인**
   ```bash
   php -m | grep xdebug
   ```

2. **디버그 정보 수집**
   ```php
   // PHP 스크립트에 추가
   error_log("DEBUG: " . print_r($variable, true));
   ```

3. **브라우저 개발자 도구와 연계**
   - Network 탭에서 HTTP 상태 확인
   - Console에서 JavaScript 에러 확인

## 📚 유용한 도구 및 명령어

### 1. 로그 분석 도구

```bash
# 실시간 로그 색상 강조
sudo apt-get install multitail
multitail /var/log/nginx/domaeka.local_error.log /var/log/php8.3-fpm.log

# 로그 통계 분석
sudo apt-get install goaccess
goaccess /var/log/nginx/domaeka.local_access.log --log-format=COMBINED
```

### 2. 성능 모니터링

```bash
# 시스템 리소스 모니터링
htop
iotop
nethogs

# 웹 서버 성능 테스트
apache2-utils  # ab 명령어
siege          # 부하 테스트 도구
```

### 3. 데이터베이스 도구

```bash
# MySQL 성능 분석
mysqldumpslow /var/log/mysql/mysql-slow.log
pt-query-digest /var/log/mysql/mysql-slow.log  # Percona Toolkit
```

## 🚀 AI 협업 최적화 팁

### 1. 구조화된 로그 메시지

```php
// 구조화된 로그 작성 예제
function logForAI($level, $message, $context = []) {
    $log_entry = [
        'timestamp' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'file' => debug_backtrace()[0]['file'] ?? 'unknown',
        'line' => debug_backtrace()[0]['line'] ?? 0
    ];
    
    error_log(json_encode($log_entry), 3, '/var/log/php_ai_structured.log');
}

// 사용 예
logForAI('ERROR', 'Database connection failed', [
    'host' => $host,
    'database' => $database,
    'error_code' => $pdo->errorCode()
]);
```

### 2. 환경별 설정 분리

```php
// config/environment.php
$environment = getenv('APP_ENV') ?: 'development';

$config = [
    'development' => [
        'debug' => true,
        'log_level' => 'DEBUG',
        'display_errors' => true
    ],
    'production' => [
        'debug' => false,
        'log_level' => 'ERROR',
        'display_errors' => false
    ]
];

return $config[$environment];
```

### 3. 자동화된 헬스체크

```php
// health_check.php - AI가 시스템 상태를 확인할 수 있는 엔드포인트
header('Content-Type: application/json');

$health = [
    'timestamp' => date('c'),
    'status' => 'ok',
    'services' => []
];

// 데이터베이스 연결 확인
try {
    $pdo = new PDO($dsn, $username, $password);
    $health['services']['database'] = 'ok';
} catch (PDOException $e) {
    $health['services']['database'] = 'error';
    $health['status'] = 'error';
}

// 캐시 서비스 확인 (Redis/Memcached 등)
// 파일 시스템 쓰기 권한 확인
// 외부 API 연결 확인 등

echo json_encode($health, JSON_PRETTY_PRINT);
```

## 🔍 문제 해결 체크리스트

### 서비스 시작 불가 시
- [ ] 포트 충돌 확인 (`sudo netstat -tlnp`)
- [ ] 설정 파일 문법 확인 (`nginx -t`, `php -l`)
- [ ] 권한 문제 확인 (`ls -la`)
- [ ] 로그 파일 확인

### 성능 저하 시
- [ ] 슬로우 쿼리 로그 확인
- [ ] PHP-FPM 프로세스 수 확인
- [ ] 메모리 사용량 확인
- [ ] 디스크 I/O 확인

### 에러 발생 시
- [ ] PHP 에러 로그 확인
- [ ] Nginx 에러 로그 확인
- [ ] 데이터베이스 연결 상태 확인
- [ ] 파일 권한 확인

---

**이 가이드를 통해 AI와 함께 PHP 웹 프로젝트를 효율적으로 개발하고 문제를 신속하게 해결할 수 있습니다.** 