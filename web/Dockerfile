# 도매까 웹 서버 Dockerfile
FROM php:8.2.21-fpm

# 시스템 패키지 설치
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libfreetype6-dev \
    libwebp-dev \
    libxpm-dev \
    libbz2-dev \
    zip \
    unzip \
    libxml2-dev \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP 확장 설치
RUN docker-php-ext-configure gd \
    --with-jpeg \
    --with-webp \
    --with-freetype \
    --with-xpm \
 && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    bz2 \
    gd \
    mbstring \
    exif \
    fileinfo \
    opcache

# 웹 디렉토리 권한 설정
RUN mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

# Nginx 설정 복사 (상위 디렉토리에서 마운트됨)
# COPY ../conf/nginx/default.conf /etc/nginx/sites-available/default

# 작업 디렉토리
WORKDIR /var/www/html

# 포트 노출
EXPOSE 80

# Supervisor로 Nginx와 PHP-FPM 실행
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]