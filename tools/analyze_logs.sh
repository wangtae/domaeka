#!/bin/bash
# analyze_logs.sh - AI가 읽기 쉬운 형태로 로그 요약

echo "=== Domaeka 프로젝트 로그 분석 ==="
echo "현재 시간: $(date)"
echo "분석 대상: 최근 1시간"
echo ""

# 시스템 상태 확인
echo "=== 시스템 서비스 상태 ==="
sudo systemctl status nginx --no-pager -l | head -5
sudo systemctl status php8.3-fpm --no-pager -l | head -5
sudo systemctl status mariadb --no-pager -l | head -5
echo ""

echo "=== 시스템 리소스 사용량 ==="
echo "메모리 사용량: $(free -h | grep Mem | awk '{print $3 "/" $2 " (" int($3/$2*100) "%)"}')"
echo "디스크 사용량: $(df -h / | tail -1 | awk '{print $3 "/" $2 " (" $5 ")"}')"
echo "CPU 로드: $(uptime | awk -F'load average:' '{print $2}')"
echo ""

# Nginx 로그 분석
echo "--- Nginx 에러 로그 (최근 1시간) ---"
current_hour=$(date '+%d/%b/%Y:%H')
prev_hour=$(date '+%d/%b/%Y:%H' -d '1 hour ago')

if [ -f /var/log/nginx/domaeka.local_error.log ]; then
    error_count=$(sudo grep -c "$prev_hour\|$current_hour" /var/log/nginx/domaeka.local_error.log 2>/dev/null || echo "0")
    echo "에러 발생 건수: $error_count"
    if [ "$error_count" -gt 0 ]; then
        echo "최근 에러들:"
        sudo grep "$prev_hour\|$current_hour" /var/log/nginx/domaeka.local_error.log 2>/dev/null | tail -10
    fi
else
    echo "Nginx 에러 로그 파일을 찾을 수 없습니다."
fi
echo ""

# PHP 로그 분석
echo "--- PHP 에러 로그 (최근 1시간) ---"
current_php_hour=$(date '+%Y-%m-%d %H')
prev_php_hour=$(date '+%Y-%m-%d %H' -d '1 hour ago')

php_error_count=0
for log_file in /var/log/php*fpm.log /var/log/php_errors.log; do
    if [ -f "$log_file" ]; then
        count=$(sudo grep -c "$prev_php_hour\|$current_php_hour" "$log_file" 2>/dev/null || echo "0")
        php_error_count=$((php_error_count + count))
    fi
done

echo "PHP 에러 발생 건수: $php_error_count"
if [ "$php_error_count" -gt 0 ]; then
    echo "최근 PHP 에러들:"
    sudo find /var/log -name "*php*" -type f -exec grep "$prev_php_hour\|$current_php_hour" {} \; 2>/dev/null | tail -10
fi
echo ""

# MySQL 로그 분석
echo "--- MariaDB 에러 로그 (최근 1시간) ---"
if [ -f /var/log/mysql/error.log ]; then
    mysql_error_count=$(sudo grep -c "$current_php_hour\|$prev_php_hour" /var/log/mysql/error.log 2>/dev/null || echo "0")
    echo "데이터베이스 에러 발생 건수: $mysql_error_count"
    if [ "$mysql_error_count" -gt 0 ]; then
        echo "최근 데이터베이스 에러들:"
        sudo grep "$current_php_hour\|$prev_php_hour" /var/log/mysql/error.log 2>/dev/null | tail -5
    fi
else
    echo "MySQL 에러 로그 파일을 찾을 수 없습니다."
fi
echo ""

# 네트워크 연결 상태
echo "=== 네트워크 서비스 상태 ==="
echo "웹 서버 포트 (80, 443):"
sudo netstat -tlnp | grep -E ":80|:443" || echo "웹 서버 포트가 열려있지 않습니다."
echo ""
echo "데이터베이스 포트 (3306):"
sudo netstat -tlnp | grep ":3306" || echo "데이터베이스 포트가 열려있지 않습니다."
echo ""
echo "PHP-FPM 포트 (9000):"
sudo netstat -tlnp | grep ":9000" || echo "PHP-FPM 포트가 열려있지 않습니다."
echo ""

# 프로세스 상태
echo "=== 주요 프로세스 상태 ==="
echo "Nginx 프로세스:"
ps aux | grep "[n]ginx" | wc -l | xargs echo "실행 중인 프로세스 수:"
echo ""
echo "PHP-FPM 프로세스:"
ps aux | grep "[p]hp-fpm" | wc -l | xargs echo "실행 중인 프로세스 수:"
echo ""
echo "MySQL 프로세스:"
ps aux | grep "[m]ysqld" | wc -l | xargs echo "실행 중인 프로세스 수:"
echo ""

echo "=== 분석 완료 ==="
echo "문제가 발견되면 AI에게 다음 정보를 제공하세요:"
echo "1. 위 분석 결과"
echo "2. 발생한 구체적인 문제 상황"
echo "3. 문제 발생 시점"
echo "4. 최근 변경사항" 