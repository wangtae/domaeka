# 업로드 크기 제한 설정 가이드

## 문제 상황
스케줄링 발송 관리에서 이미지를 최대 30개까지 업로드할 수 있도록 기능을 구현했으나, nginx와 PHP의 기본 업로드 크기 제한으로 인해 "413 Request Entity Too Large" 오류가 발생합니다.

## 해결 방법

### 1. Nginx 설정 수정

`/etc/nginx/sites-available/domaeka.local` 파일을 수정합니다:

```bash
sudo nano /etc/nginx/sites-available/domaeka.local
```

`charset utf-8;` 다음 라인에 아래 설정을 추가합니다:

```nginx
# 파일 업로드 크기 제한 (이미지 30개 업로드 가능하도록 충분히 크게 설정)
client_max_body_size 100M;
```

### 2. PHP 설정 수정

#### 방법 1: php.ini 수정 (권장)

PHP 8.3의 php.ini 파일을 수정합니다:

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

다음 값들을 찾아서 수정합니다:

```ini
; 최대 업로드 파일 크기 (개별 파일)
upload_max_filesize = 10M

; POST 데이터 최대 크기 (전체 요청)
post_max_size = 100M

; 최대 파일 업로드 개수
max_file_uploads = 60

; 메모리 제한 (이미지 리사이징을 위해 충분히 설정)
memory_limit = 256M

; 스크립트 실행 시간 (이미지 처리를 위해 충분히 설정)
max_execution_time = 300

; 입력 시간 제한
max_input_time = 300
```

#### 방법 2: .user.ini 사용 (이미 적용됨)

`/home/wangt/projects/client/domaeka/domaeka.dev/web/dmk/adm/bot/.user.ini` 파일이 이미 생성되어 있습니다.

**참고**: .user.ini 파일은 PHP-FPM에서만 작동하며, 디렉토리별로 설정을 재정의할 수 있습니다.

### 3. 서비스 재시작

설정 변경 후 서비스를 재시작합니다:

```bash
# Nginx 재시작
sudo systemctl restart nginx

# PHP-FPM 재시작
sudo systemctl restart php8.3-fpm
```

### 4. 설정 확인

PHP 정보 페이지를 만들어 설정이 적용되었는지 확인합니다:

```php
<?php phpinfo(); ?>
```

## 권장 설정 값 설명

- **upload_max_filesize = 10M**: 개별 이미지 파일의 최대 크기
- **post_max_size = 100M**: 전체 폼 데이터의 최대 크기 (30개 이미지 × 3MB 평균 + 여유분)
- **max_file_uploads = 60**: 한 번에 업로드 가능한 최대 파일 수 (이미지 그룹 2개 × 30개)
- **memory_limit = 256M**: 이미지 리사이징 처리를 위한 메모리
- **max_execution_time = 300**: 많은 이미지 처리를 위한 충분한 실행 시간

## 추가 고려사항

1. **서버 자원**: 많은 이미지를 동시에 처리하면 서버 자원을 많이 사용합니다.
2. **네트워크 속도**: 사용자의 인터넷 속도에 따라 업로드 시간이 오래 걸릴 수 있습니다.
3. **브라우저 제한**: 일부 브라우저는 자체적으로 업로드 크기나 시간 제한이 있을 수 있습니다.

## 문제 해결

만약 여전히 오류가 발생한다면:

1. nginx 에러 로그 확인: `sudo tail -f /var/log/nginx/error.log`
2. PHP 에러 로그 확인: `sudo tail -f /var/log/php8.3-fpm.log`
3. 브라우저 개발자 도구의 네트워크 탭에서 실제 요청 크기 확인