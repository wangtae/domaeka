# 🚀 실서버 Mixed Content 오류 해결 가이드

## 📝 문제 상황
실서버 `https://domaeka.com/shop`에서 Mixed Content 오류가 발생:
```
Mixed Content: The page at 'https://domaeka.com/shop/cart.php' was loaded over HTTPS, 
but requested an insecure stylesheet 'http://domaeka.com/shop/'. 
This request has been blocked; the content must be served over HTTPS.
```

## 🔧 해결 방법

### 1. 실서버 config.php 수정 **(최우선)**

실서버의 `/web/config.php` 파일에서 다음과 같이 수정해야 합니다:

**❌ 현재 (잘못된 설정):**
```php
define('G5_DOMAIN', 'http://domaeka.com');
define('G5_HTTPS_DOMAIN', '');
```

**✅ 수정 후 (올바른 설정):**
```php
define('G5_DOMAIN', 'https://domaeka.com');
define('G5_HTTPS_DOMAIN', 'https://domaeka.com');
```

### 2. Bootstrap Icons SVG 파일 수정 ✅ 완료

- **수정된 파일**: 1,953개의 SVG 파일
- **변경 내용**: `xmlns="http://www.w3.org/2000/svg"` → `xmlns="https://www.w3.org/2000/svg"`
- **커밋 완료**: 로컬에서 수정되어 GitHub에 푸시됨

### 3. SSL 인증서 확인

실서버에서 HTTPS가 올바르게 설정되어 있는지 확인:
- SSL 인증서가 유효한지 확인
- HTTPS 리다이렉션이 올바르게 작동하는지 확인

## 🔍 검증 방법

실서버 수정 후 다음 방법으로 검증:

1. **브라우저 개발자 도구 콘솔 확인**
   - Mixed Content 오류가 사라졌는지 확인
   - jQuery 오류가 해결되었는지 확인

2. **네트워크 탭 확인**
   - 모든 리소스가 HTTPS로 로드되는지 확인
   - 404 오류가 줄어들었는지 확인

## ⚠️ 주의사항

- **config.php 백업**: 수정 전 반드시 백업
- **캐시 클리어**: 수정 후 브라우저 캐시와 서버 캐시 클리어
- **테스트**: 주요 기능들이 정상 작동하는지 확인

## 📋 체크리스트

- [ ] 실서버 config.php에서 G5_DOMAIN을 https로 수정
- [ ] 실서버 config.php에서 G5_HTTPS_DOMAIN을 https로 수정  
- [x] Bootstrap Icons SVG 파일들 https로 수정 (완료)
- [ ] 브라우저에서 Mixed Content 오류 해결 확인
- [ ] 주요 기능 테스트 완료

## 🆘 추가 문제 시

만약 위 설정 후에도 문제가 지속되면:
1. `.htaccess` 파일에서 HTTPS 강제 리다이렉션 확인
2. 웹서버(Apache/Nginx) 설정 확인
3. CDN 설정 확인 (사용 시) 