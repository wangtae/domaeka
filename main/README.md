# Domaeka 프로젝트 (`main` 폴더)

이 문서는 Domaeka 프로젝트의 `main` 폴더에 대한 개요, 구조, 작동 방식 및 개발 시 중요하게 고려해야 할 사항들을 설명합니다. `main` 폴더는 영카트5(그누보드5) 기반의 CMS 및 쇼핑몰 프로그램의 핵심 소스 코드를 포함하고 있습니다.

## 1. 프로젝트 개요

Domaeka 프로젝트는 그누보드5(GnuBoard5)와 영카트5(Youngcart5)를 기반으로 구축된 포괄적인 웹 서비스입니다. CMS(콘텐츠 관리 시스템) 기능과 쇼핑몰 기능을 통합하여 운영될 예정이며, 향후 **관리자 계층별 권한 지정** 기능을 통해 본사, 지사, 대리점 등 다양한 관리자 레벨에 따른 접근 및 운영 권한을 세분화할 예정입니다.

## 2. 주요 기능

*   **CMS 기능**: 게시판, 최신 글, 회원 관리, 컨텐츠 페이지 등 웹사이트 전반의 콘텐츠 관리 기능을 제공합니다.
*   **쇼핑몰 기능**: 상품 관리, 주문/결제 시스템, 장바구니, 위시리스트, 쿠폰, 배송 관리 등 전자상거래에 필요한 모든 기능을 포함합니다.
*   **관리자 시스템**: 웹사이트 및 쇼핑몰 운영을 위한 강력한 관리자 백오피스 기능을 제공합니다.
*   **모바일 지원**: 모바일 환경에 최적화된 웹 페이지를 지원합니다.

## 3. 폴더 및 파일 구조

아래는 `main` 폴더의 주요 디렉토리 구조 및 역할에 대한 Mermaid 다이어그램입니다.

```mermaid
graph TD
    A[main/] --> B[_common.php];
    A --> C[common.php];
    A --> D[index.php];
    A --> E[shop.config.php];
    A --> F[head.php];
    A --> G[tail.php];
    A --> H[adm/];
    H --> H1[관리자 백오피스 관련 파일];
    A --> I[shop/];
    I --> I1[쇼핑몰 핵심 로직 및 템플릿];
    A --> J[bbs/];
    J --> J1[게시판 관련 파일];
    A --> K[mobile/];
    K --> K1[모바일 웹 페이지 및 스킨];
    A --> L[data/];
    L --> L1[dbconfig.php (데이터베이스 설정)];
    L --> L2[cache/, log/, tmp/ (임시/로그 데이터)];
    L --> L3[file/, item/ (업로드 파일)];
    A --> M[lib/];
    M --> M1[공통 라이브러리 및 유틸리티 함수];
    A --> N[plugin/];
    N --> N1[결제 모듈, 소셜 로그인 등 외부 연동];
    A --> O[skin/];
    O --> O1[게시판, 회원, 쇼핑몰 등 프론트엔드 스킨];
    A --> P[theme/];
    P --> P1[전체 웹사이트 테마 파일];
    A --> Q[css/];
    Q --> Q1[스타일시트 파일];
    A --> R[js/];
    R --> R1[JavaScript 파일];
    A --> S[img/];
    S --> S1[이미지 파일];
    A --> T[install/];
    T --> T1[설치 스크립트 (운영 시 불필요)];
```

### 주요 폴더 설명

| 폴더명      | 설명                                                                 | 중요도 |
| :---------- | :------------------------------------------------------------------- | :----- |
| `adm/`      | 관리자 페이지 관련 모든 파일. 계층별 권한 기능 개선 시 핵심적으로 다룰 폴더. | 높음   |
| `shop/`     | 쇼핑몰의 프론트엔드 및 백엔드 핵심 로직 파일.                        | 높음   |
| `bbs/`      | 게시판 관련 기능(목록, 보기, 쓰기 등) 및 스킨 파일.                  | 보통   |
| `mobile/`   | 모바일 웹 서비스 관련 파일.                                          | 보통   |
| `data/`     | 데이터베이스 설정(`dbconfig.php`), 업로드 파일, 캐시, 로그 등 데이터 저장. `dbconfig.php`는 매우 중요. | 높음   |
| `lib/`      | 공통 함수, 유틸리티 클래스, 핵심 라이브러리 파일.                    | 높음   |
| `plugin/`   | 외부 서비스 연동 모듈(결제PG, 소셜 로그인, 캡챠 등) 및 자체 플러그인. | 보통   |
| `skin/`     | 각 기능(게시판, 회원, 쇼핑몰)별 화면 구성(HTML/PHP) 및 CSS/JS.       | 보통   |
| `theme/`    | 전체 웹사이트의 디자인 테마 관련 파일.                              | 보통   |
| `css/`      | 공통 CSS 파일.                                                       | 낮음   |
| `js/`       | 공통 JavaScript 파일.                                                | 낮음   |
| `img/`      | 공통 이미지 파일.                                                    | 낮음   |
| `install/`  | 초기 설치 스크립트. 배포 후에는 중요도 낮음.                         | 낮음   |
| `temp/`     | 임시 파일 저장 폴더 (워크스페이스 루트에 있지만 관련성 높음).       | 낮음   |

### 주요 파일 설명 (main/ 루트)

*   `common.php`: 그누보드/영카트의 가장 핵심적인 공통 설정 및 함수 정의 파일. 거의 모든 페이지에서 include 됨.
*   `shop.config.php`: 영카트 쇼핑몰 관련 전역 설정 및 변수 정의 파일.
*   `index.php`: 웹사이트의 메인 진입점.
*   `head.php`, `tail.php`: 웹페이지의 헤더 및 푸터 부분.
*   `_common.php`, `_head.php`, `_tail.php`: 하위 모듈에서 사용되는 공통 include 파일.

## 4. 작동 방식

이 프로젝트는 PHP 스크립트와 MySQL 데이터베이스를 기반으로 작동합니다.
클라이언트의 요청은 `index.php` (또는 특정 모듈의 진입점 파일)를 통해 처리되며, 필요한 경우 `common.php`, `shop.config.php` 등의 설정을 로드하고, `lib/` 폴더의 공통 함수들을 활용하여 데이터를 처리합니다.
데이터는 `data/dbconfig.php`에 정의된 연결 정보를 통해 MySQL 데이터베이스와 상호작용하며 저장 및 조회됩니다.

## 5. 개발 참고 사항

### 5.1. 데이터베이스 관리

*   **설정 파일**: 데이터베이스 접속 정보는 `main/data/dbconfig.php` 파일에 정의되어 있습니다. 이 파일은 민감한 정보를 포함하므로 Git 저장소에 직접 커밋하지 않도록 주의해야 합니다. ( `.gitignore` 설정 필요)
*   **시간 형식**: 시간 관련 데이터는 항상 **UTC를 기준으로 ISO 8601 형식('YYYY-MM-DDTHH:mm:ssZ')**으로 저장하고, 클라이언트에서는 사용자 로컬 시간대 및 지역화된 형식으로 표시해야 합니다.
*   **자동 증가 ID**: 데이터베이스 테이블의 자동 증가(auto_increment) ID 컬럼은 반드시 `id`로 명명해야 합니다.

### 5.2. 코드 품질 및 설계 원칙

*   **DRY (Don't Repeat Yourself)**: 중복되는 코드는 함수나 모듈로 추출하여 재사용성을 높여야 합니다.
*   **SOLID 원칙**:
    *   **단일 책임 원칙 (SRP)**: 각 모듈, 함수, 클래스는 하나의 명확한 책임만을 가져야 합니다.
    *   **확장/수정 원칙 (OCP)**: 새로운 기능 추가 시 기존 코드를 수정하지 않고 확장할 수 있도록 설계해야 합니다. 특히 향후 **계층별 관리자 권한 기능**을 구현할 때, 기존 `adm/` 관련 로직을 최소한으로 수정하며 확장할 수 있도록 설계해야 합니다.
    *   나머지 원칙(LSP, ISP, DIP) 또한 코드를 작성하거나 리팩토링할 때 고려해야 합니다.
*   **모듈화**: 코드의 가독성, 유지보수성, 확장성을 위해 적절한 모듈 분리 및 파일 분리가 필수적입니다.
*   **명명 규칙**: 명확하고 서술적인 이름을 사용합니다.
*   **Git 커밋 메시지**: 모든 Git 커밋 메시지는 한글로 작성합니다.

### 5.3. 향후 기능 개선: 계층별 관리자 권한

*   이 프로젝트의 중요한 향후 개선 사항은 쇼핑몰 관리자를 본사, 지사, 대리점 등으로 계층별로 구분하고 각 계층에 맞는 권한을 부여하는 기능입니다.
*   이 기능은 주로 `main/adm/` 폴더 내의 파일들과 기존 관리자 인증 및 권한 로직에 영향을 미칠 것입니다.
*   `main/adm/_common.php` 및 관련 인증/권한 체크 로직을 중심으로 확장 가능성을 고려하여 설계해야 합니다.

## 6. AI (Cursor AI)를 위한 지침

Cursor AI의 효율적인 작업을 위해, 프로젝트의 핵심 로직과 불필요한 부분을 구분합니다. 이는 `.cursorignore` 파일 작성에도 도움이 됩니다.

### AI가 중요하게 기억할 핵심 로직/파일/폴더

이 목록의 파일과 폴더는 프로젝트의 핵심 기능을 담고 있으며, AI가 작업 시 최우선으로 분석하고 이해해야 합니다.

*   `main/adm/` (관리자 시스템, 특히 계층별 권한 기능 개선 시 핵심)
*   `main/shop/` (쇼핑몰 기능의 핵심)
*   `main/lib/` (공통 라이브러리 및 유틸리티)
*   `main/data/dbconfig.php` (데이터베이스 설정)
*   `main/common.php` (전역 공통 함수 및 설정)
*   `main/shop.config.php` (쇼핑몰 전역 설정)
*   `main/bbs/` (게시판 관련)
*   `main/mobile/` (모바일 관련)
*   `main/plugin/` (외부 연동 및 핵심 플러그인)

### AI가 무시해도 되는 폴더 (`.cursorignore` 권장)

이 목록의 폴더들은 일반적으로 빌드 아티팩트, 캐시, 로그, 임시 파일 또는 외부 라이브러리/자산이므로, 코드 분석 시 제외해도 무방합니다.

```
# Domaeka 프로젝트 관련 무시할 파일/폴더

# 빌드 및 임시 파일
/main/data/cache/
/main/data/log/
/main/data/tmp/
/temp/

# 설치 관련 파일 (운영 시 불필요)
/main/install/

# 특정 플러그인의 과도한 파일 또는 불필요한 파일 (AI 분석 효율 증대)
/main/plugin/debugbar/
/main/plugin/jqplot/
/main/js/font-awesome/
/main/js/owlcarousel/
/main/js/remodal/
/main/js/swiper/
/main/js/tooltipster/
/main/lib/PHPExcel/ # 대규모 외부 라이브러리
/main/lib/Excel/php_writeexcel/ # 대규모 외부 라이브러리
/main/plugin/PHPMailer/ # 대규모 외부 라이브러리
/main/plugin/editor/cheditor5/
/main/plugin/editor/smarteditor2/
/main/plugin/htmlpurifier/
/main/plugin/kcaptcha/mp3/
/main/plugin/PHPMailer/docs/
/main/plugin/PHPMailer/examples/
/main/plugin/PHPMailer/language/

# VCS 관련 (git 이외) 및 OS 관련
.vscode/ # 사용자 IDE 설정

# 자동 생성되거나 빌드 관련 파일
node_modules/
vendor/
__pycache__/
dist/
build/
target/
.next/
out/
*.log
logs/
debug.log
error.log
*.tmp
*.temp
*.cache
*.pid
*.lock
*.db
*.sqlite
*.sqlite3
*.zip
*.tar.gz
*.rar
Thumbs.db
Desktop.ini
*~
*.bak
*.backup
*.orig
