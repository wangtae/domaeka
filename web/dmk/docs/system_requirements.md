# 도매까 시스템 요구사항

## PHP 확장 모듈

도매까 시스템의 모든 기능을 사용하려면 다음 PHP 확장 모듈이 필요합니다:

### 필수 확장 모듈

1. **PHP GD Library** (이미지 처리)
   - 이미지 업로드 및 리사이징 기능에 필수
   - 스케줄링 발송 시 이미지 자동 리사이징
   - 설치 방법:
   ```bash
   # Ubuntu/Debian
   sudo apt-get update
   sudo apt-get install php-gd
   sudo service apache2 restart
   
   # CentOS/RHEL
   sudo yum install php-gd
   sudo service httpd restart
   
   # PHP-FPM 사용 시
   sudo service php-fpm restart
   ```

2. **PHP MySQLi Extension** (데이터베이스)
   - MySQL 데이터베이스 연결에 필수
   - 대부분의 PHP 설치에 기본 포함

3. **PHP JSON Extension** (JSON 처리)
   - API 통신 및 데이터 처리에 필수
   - PHP 5.2.0 이상에서 기본 포함

### 권장 확장 모듈

1. **PHP cURL Extension** (외부 API 통신)
   - 카카오봇 서버와의 통신
   - 설치: `sudo apt-get install php-curl`

2. **PHP mbstring Extension** (멀티바이트 문자열)
   - 한글 처리 최적화
   - 설치: `sudo apt-get install php-mbstring`

## 설치 확인 방법

현재 설치된 PHP 확장 모듈 확인:

```bash
# 커맨드라인에서 확인
php -m

# 웹에서 확인 (phpinfo)
<?php phpinfo(); ?>
```

## 최소 시스템 요구사항

- PHP 7.4 이상
- MySQL 5.7 이상
- Apache 2.4 이상 또는 Nginx 1.18 이상
- 메모리: 최소 2GB RAM (이미지 처리 시 4GB 권장)
- 디스크 공간: 최소 10GB (이미지 저장용)

## 주의사항

- PHP GD 라이브러리가 없으면 이미지 업로드 기능이 작동하지 않습니다
- 이미지 리사이징 기능은 메모리를 많이 사용하므로 PHP memory_limit 설정을 512M 이상으로 권장합니다
- 파일 업로드 관련 PHP 설정 확인:
  - `upload_max_filesize`: 10M 이상
  - `post_max_size`: 100M 이상
  - `max_file_uploads`: 30 이상