# 도매까 웹 관리자 시스템 가이드

## 시스템 개요

도매까 웹 시스템은 **그누보드5 + 영카트5를 기반으로 한 계층형 관리자 시스템**입니다. 카카오톡 봇과 연동된 공동구매 플랫폼으로, 4단계 계층별 권한 관리와 프랜차이즈 비즈니스 로직을 제공합니다.

### 핵심 특징
- **하이브리드 아키텍처**: 검증된 그누보드5/영카트5 + 도매까 확장 모듈
- **4단계 계층별 권한**: 본사(super) → 총판(distributor) → 대리점(agency) → 지점(branch)
- **카카오톡 봇 통합**: 실시간 메시지 발송 및 관리
- **계층별 데이터 필터링**: 권한에 따른 회원/주문/상품 데이터 자동 필터링

## 시스템 구조

```
web/
├── adm/                    # 관리자 시스템 (그누보드5 기반 + 도매까 확장)
│   ├── index.php           # 메인 대시보드 (계층별 권한 적용)
│   ├── admin.menu*.php     # 권한별 메뉴 구조
│   ├── shop_admin/         # 쇼핑몰 관리 (영카트5 기반 + 계층별 확장)
│   └── sms_admin/          # SMS 발송 관리
├── dmk/                    # 도매까 전용 확장 모듈
│   ├── admin/              # 도매까 관리 기능
│   ├── ajax/               # AJAX 처리
│   └── lib/                # 도매까 라이브러리
├── bbs/                    # 게시판 시스템 (그누보드5)
└── shop/                   # 쇼핑몰 프론트엔드 (영카트5)
```

## 관리자 시스템 (`/adm`)

### 메뉴 시각적 구분 시스템

도매까 관리자 시스템은 **기존 영카트5 메뉴의 개조**와 **완전히 새로운 메뉴 추가**를 시각적으로 구분하기 위해 FontAwesome 아이콘을 사용합니다:

- **⭐ (fa-star)**: 신규 메뉴 - 도매까에서 새롭게 개발한 기능
- **🚩 (fa-flag)**: 개조 메뉴 - 기존 영카트5 기능을 도매까 계층형 권한에 맞게 수정

#### UI 구조
- **좌측 패널**: 메인 메뉴 FontAwesome 아이콘으로 표시
- **우측 영역**: 메인 메뉴 클릭 시 서브 메뉴 목록 표시
- **서브 메뉴**: 메뉴명 오른쪽에 구분 아이콘 표시

#### 메인 메뉴 아이콘 시스템 (FontAwesome)

도매까 시스템은 기존 영카트의 이미지 기반 아이콘을 **FontAwesome 아이콘 시스템**으로 업그레이드했습니다:

| 메뉴 | FontAwesome 아이콘 | 색상 | 설명 |
|------|-------------------|------|------|
| **환경설정** (100) | `fa-cog` | #6c757d | 시스템 설정 및 관리 |
| **봇 관리** (180) | `fa-robot` | #28a745 | 카카오톡 봇 시스템 |
| **프랜차이즈 관리** (190) | `fa-sitemap` | #dc3545 | 도매까 핵심 기능 |
| **회원관리** (200) | `fa-users` | #17a2b8 | 계층별 회원 관리 |
| **게시판관리** (300) | `fa-list-alt` | #6610f2 | 커뮤니티 관리 |
| **상품/주문관리** (400) | `fa-shopping-cart` | #fd7e14 | 쇼핑몰 관리 |
| **매출/통계** (500) | `fa-chart-bar` / `fa-chart-line` | #20c997 | 통계 및 분석 |
| **SMS관리** (900) | `fa-sms` | #e83e8c | 문자 발송 관리 |

**특징**:
- **확장성**: 새로운 메뉴 추가 시 FontAwesome 아이콘만 설정하면 됨
- **반응형**: 화면 크기에 따라 아이콘 크기 자동 조정
- **접근성**: 명확한 색상 구분과 툴팁 제공
- **일관성**: 통일된 디자인 시스템으로 사용자 경험 향상

### 계층별 메뉴 구조

#### 1. 환경설정 (`menu100`) - 본사/총판만 접근
- **기본환경설정**: 사이트 기본 정보 및 설정
- **관리권한설정**: 관리자 권한 및 메뉴 관리
- **테마설정**: UI 테마 및 스킨 관리
- **메뉴설정**: 메뉴 구조 및 권한 설정
- **팝업레이어관리**: 공지사항 팝업 관리
- **시스템 관리**: 캐시/세션/파일 정리, DB 업그레이드

#### 2. 봇 관리 (`menu180`) - 카카오톡 봇 시스템
- **서버 관리** ⭐: 봇 서버 상태 모니터링 (본사 전용)
- **클라이언트 봇 관리** ⭐: 디바이스 승인 및 채팅방 관리
- **스케줄링 발송 관리** ⭐: 예약 메시지 발송 관리
- **메시지 즉시 발송** ⭐: 실시간 메시지 발송
- **채팅 내역 조회** ⭐: 대화 로그 조회 및 분석

#### 3. 프랜차이즈 관리 (`menu190`) - 도매까 핵심 기능
- **총판관리** ⭐: 총판 등록/수정/삭제 (본사 전용)
- **대리점관리** ⭐: 대리점 등록/수정/삭제 (본사/총판)
- **지점관리** ⭐: 지점 등록/수정/삭제 (본사/총판/대리점)
- **통계분석** ⭐: 계층별 매출 및 회원 통계
- **서브관리자관리** ⭐: 계층별 관리자 계정 관리
- **서브관리자권한설정** ⭐: 세부 메뉴별 권한 설정
- **관리자엑션로그** ⭐: 관리자 활동 추적
- **계층별메뉴권한설정** ⭐: 메뉴별 권한 매트릭스 관리

#### 4. 회원관리 (`menu200`) - 계층별 필터링
- **회원목록** 🚩: 권한에 따른 회원 조회 (계층별 필터링 적용)
  - 본사: 전체 회원
  - 총판: 소속 대리점/지점 회원만
  - 대리점: 소속 지점 회원만  
  - 지점: 자신만
- **포인트관리**: 계층별 포인트 지급/차감
- **메일발송**: 회원 대상 이메일 발송

#### 5. 게시판관리 (`menu300`) - 그누보드5 기반
- **게시판관리**: 게시판 생성/수정/삭제
- **게시글관리**: 게시글 관리 및 답변
- **댓글관리**: 댓글 관리 및 승인

#### 6. 쇼핑몰관리 (`menu400`, `menu500`) - 영카트5 + 도매까 확장

**상품 관리** (`menu400`):
- **분류관리** 🚩: 계층별 카테고리 소유권 관리
- **상품관리** 🚩: 계층별 상품 등록/수정 (소유자 필터링)
- **상품유형관리** 🚩: 소유자별 상품 유형 정의
- **재고관리** 🚩: 계층별 재고 조회 및 알림
- **상품옵션재고관리** 🚩: 옵션별 재고 관리

**주문 관리** (`menu400`):
- **주문관리** 🚩: 계층별 주문 조회 및 처리
- **미완료주문** 🚩: 미완료 주문 추적 관리

**매출 통계** (`menu500`):
- **매출현황** 🚩: 계층별 매출 통계
- **상품판매순위** 🚩: 상품별 판매 실적
- **주문내역출력** 🚩: 주문서 출력 관리
- **보관함현황** 🚩: 장바구니/관심상품 현황

**기타 기능**:
- **쿠폰관리**: 쿠폰 발행 및 관리
- **배너관리**: 사이트 배너 관리

#### 7. SMS관리 (`menu900`) - 아이코드 SMS 연동
- **SMS 설정**: SMS 서비스 기본 설정
- **SMS 발송**: 개별/그룹 SMS 발송
- **발송내역**: SMS 발송 이력 조회
- **주소록관리**: 연락처 그룹 관리

## 도매까 실제 메뉴 현황

### 신규 메뉴 (⭐ fa-star) - 총 13개

#### 봇 관리 (`menu180`) - 5개
| 메뉴코드 | 메뉴명 | 경로 | 설명 |
|---------|--------|------|------|
| 180100 | 서버 관리 | `/dmk/adm/bot/bot_server_status.php` | 봇 서버 상태 모니터링 |
| 180200 | 클라이언트 봇 관리 | `/dmk/adm/bot/bot_client_list.php` | 디바이스 승인 및 관리 |
| 180300 | 스케줄링 발송 관리 | `/dmk/adm/bot/bot_schedule_list.php` | 예약 메시지 발송 |
| 180400 | 메시지 즉시 발송 | `/dmk/adm/bot/bot_instant_send_form.php` | 실시간 메시지 발송 |
| 180500 | 채팅 내역 조회 | `/dmk/adm/bot/bot_chat_log_list.php` | 대화 로그 조회 |

#### 프랜차이즈 관리 (`menu190`) - 8개
| 메뉴코드 | 메뉴명 | 경로 | 설명 |
|---------|--------|------|------|
| 190100 | 총판관리 | `/dmk/adm/distributor_admin/distributor_list.php` | 총판 계정 관리 |
| 190200 | 대리점관리 | `/dmk/adm/agency_admin/agency_list.php` | 대리점 계정 관리 |
| 190300 | 지점관리 | `/dmk/adm/branch_admin/branch_list.php` | 지점 계정 관리 |
| 190400 | 통계분석 | `/dmk/adm/statistics/statistics_dashboard.php` | 계층별 통계 |
| 190600 | 서브관리자관리 | `/dmk/adm/admin_manager/admin_list.php` | 관리자 계정 관리 |
| 190700 | 서브관리자권한설정 | `/dmk/adm/admin_manager/dmk_auth_list.php` | 권한 설정 |
| 190800 | 계층별메뉴권한설정 | `/dmk/adm/admin_manager/menu_config.php` | 메뉴별 권한 매트릭스 |
| 190900 | 관리자엑션로그 | `/dmk/adm/logs/action_log_list.php` | 관리자 활동 로그 |

### 개조 메뉴 (🚩 fa-flag) - 총 12개

#### 회원관리 (`menu200`) - 1개
| 메뉴코드 | 메뉴명 | 개조 내용 |
|---------|--------|----------|
| 200100 | 회원목록 | 계층별 회원 필터링 적용 |

#### 쇼핑몰 관리 (`menu400`) - 7개
| 메뉴코드 | 메뉴명 | 개조 내용 |
|---------|--------|----------|
| 400200 | 분류관리 | 계층별 카테고리 소유권 관리 |
| 400300 | 상품관리 | 계층별 상품 소유자 필터링 |
| 400400 | 주문관리 | 계층별 주문 조회 권한 적용 |
| 400410 | 미완료주문 | 계층별 미완료 주문 관리 |
| 400500 | 상품옵션재고관리 | 계층별 옵션 재고 관리 |
| 400610 | 상품유형관리 | 소유자별 상품 유형 정의 |
| 400620 | 재고관리 | 계층별 재고 조회 및 알림 |

#### 쇼핑몰 통계 (`menu500`) - 4개
| 메뉴코드 | 메뉴명 | 개조 내용 |
|---------|--------|----------|
| 500100 | 상품판매순위 | 계층별 판매 실적 통계 |
| 500110 | 매출현황 | 계층별 매출 통계 |
| 500120 | 주문내역출력 | 계층별 주문서 출력 |
| 500140 | 보관함현황 | 계층별 장바구니/관심상품 현황 |

### 기존 메뉴 (아이콘 없음)

#### 환경설정 (`menu100`)
- 그누보드5 기본 환경설정 메뉴들 (수정 최소화)

#### 게시판관리 (`menu300`)
- 그누보드5 기본 게시판 관리 기능 (수정 없음)

#### SMS관리 (`menu900`)
- 기존 SMS 모듈 활용 (수정 없음)

### 권한별 접근 제어

```php
// 권한 확인 예시
$dmk_auth = dmk_get_admin_auth();

if ($dmk_auth['is_super']) {
    // 본사: 모든 기능 접근
} else if ($dmk_auth['user_type'] == 'distributor') {
    // 총판: 소속 대리점/지점 관리
} else if ($dmk_auth['user_type'] == 'agency') {
    // 대리점: 소속 지점 관리
} else if ($dmk_auth['user_type'] == 'branch') {
    // 지점: 제한된 기능만
}
```

### 데이터 필터링 시스템

모든 회원, 주문, 상품 데이터는 사용자의 권한에 따라 자동으로 필터링됩니다:

**회원 데이터**:
- WHERE 조건에 계층별 필터 자동 추가
- 상위 계층은 하위 계층 데이터 조회 가능

**주문 데이터**:
- 계층별 주문 조회 권한 적용
- 매출 통계도 계층별로 집계

**상품 데이터**:
- 소유자(`dmk_owner_type`, `dmk_owner_id`) 기반 필터링
- 계층별 상품 등록/수정 권한 적용

## 도매까 확장 모듈 (`/dmk`)

### 핵심 라이브러리 (`/dmk/lib`)
- **`dmk_admin_auth.php`**: 계층별 권한 인증
- **`dmk_common.php`**: 공통 유틸리티 함수
- **`dmk_menu.php`**: 메뉴 권한 관리
- **`dmk_data_filter.php`**: 데이터 필터링 로직

### 관리 기능 (`/dmk/admin`)
- **프랜차이즈 관리**: 총판/대리점/지점 CRUD
- **권한 관리**: 세부 메뉴별 권한 설정
- **통계 분석**: 계층별 매출/회원 통계
- **봇 관리**: 카카오톡 봇 연동 기능

### AJAX 처리 (`/dmk/ajax`)
- **계층별 데이터 로딩**: 대리점/지점 동적 로딩
- **권한 검증**: 실시간 권한 확인
- **통계 데이터**: 차트 및 그래프 데이터

## 개발 가이드라인

### 파일 수정 원칙
1. **기존 그누보드5/영카트5 파일 직접 수정 최소화**
2. **확장 기능은 `/dmk` 폴더에 구현**
3. **수정 내역은 `dmk/docs/g5_modifications_log.md`에 기록**

## 도매까 개조/신규 메뉴 개발 가이드라인

### 개발 접근 방식

도매까 시스템은 기존 영카트5 프로그램을 다음 두 가지 방식으로 확장합니다:

1. **개조(Modified)**: 기존 영카트5 메뉴를 도매까 4단계 계층별 권한에 맞게 수정
2. **신규(New)**: `list`, `form`, `form_update` 패턴을 복사하여 새로운 기능 구현

### 공통 권한 체크 시스템

모든 도매까 개조/신규 메뉴는 **공통 권한 체크**와 **메뉴별 커스텀 권한 체크**를 별도로 구현해야 합니다.

#### 1. 공통 권한 체크 파일 구조

```
/web/adm/dmk/include/
├── dmk_head.sub.php           # 공통 권한 체크 (모든 파일에서 include)
├── dmk_auth_config.php        # 메뉴별 권한 설정 정의
├── dmk_data_filter.php        # 공통 데이터 필터링 헬퍼
└── dmk_menu_permissions.php   # 메뉴별 세부 권한 매트릭스
```

#### 2. dmk_head.sub.php - 공통 권한 체크

```php
<?php
/**
 * 도매까 공통 권한 체크 파일
 * 모든 개조/신규 메뉴 파일 상단에 포함 필수
 */

if (!defined('_GNUBOARD_')) exit;

// 1. 기본 도매까 권한 정보 로드
$dmk_auth = dmk_get_admin_auth();
if (!$dmk_auth) {
    alert('도매까 관리자 로그인이 필요합니다.');
}

// 2. 현재 메뉴 코드 자동 감지 (파일명 기반)
$current_file = basename($_SERVER['SCRIPT_NAME'], '.php');
$dmk_menu_code = dmk_get_menu_code_from_file($current_file);

// 3. 공통 접근 권한 체크
if (!dmk_check_basic_menu_access($dmk_auth, $dmk_menu_code)) {
    alert('해당 메뉴에 접근할 권한이 없습니다.');
}

// 4. 공통 변수 설정
$dmk_user_type = $dmk_auth['user_type'];
$dmk_user_id = $dmk_auth['user_id'];
$dmk_is_super = $dmk_auth['is_super'];

// 5. 메뉴별 추가 권한 설정 로드
$dmk_menu_perms = dmk_load_menu_permissions($dmk_menu_code, $dmk_user_type);

// 6. 공통 데이터 필터링 WHERE 조건 생성
$dmk_common_where = dmk_get_common_data_filter($dmk_auth);

// 7. 디버그 정보 (개발 환경에서만)
if (G5_IS_ADMIN && defined('DMK_DEBUG') && DMK_DEBUG) {
    echo "<!-- DMK DEBUG: Menu={$dmk_menu_code}, UserType={$dmk_user_type}, UserID={$dmk_user_id} -->";
}
?>
```

#### 3. dmk_auth_config.php - 메뉴별 권한 설정

```php
<?php
/**
 * 도매까 메뉴별 권한 설정 매트릭스
 */

// 메뉴별 접근 권한 정의
$dmk_menu_access = [
    // 프랜차이즈 관리
    'distributor_list' => ['super'],
    'distributor_form' => ['super'],
    'agency_list' => ['super', 'distributor'],
    'agency_form' => ['super', 'distributor'],
    'branch_list' => ['super', 'distributor', 'agency'],
    'branch_form' => ['super', 'distributor', 'agency'],
    
    // 상품 관리 (개조)
    'itemlist' => ['super', 'distributor', 'agency', 'branch'],
    'itemform' => ['super', 'distributor', 'agency', 'branch'],
    'categorylist' => ['super', 'distributor', 'agency'],
    
    // 주문 관리 (개조)
    'orderlist' => ['super', 'distributor', 'agency', 'branch'],
    'orderform' => ['super', 'distributor', 'agency'],
];

// 메뉴별 작업 권한 정의 (CRUD)
$dmk_menu_actions = [
    'itemlist' => [
        'read' => ['super', 'distributor', 'agency', 'branch'],
        'create' => ['super', 'distributor', 'agency'],
        'update' => ['super', 'distributor', 'agency'],
        'delete' => ['super', 'distributor'],
    ],
    'orderlist' => [
        'read' => ['super', 'distributor', 'agency', 'branch'],
        'update' => ['super', 'distributor', 'agency'],
        'delete' => ['super', 'distributor'],
    ],
];
?>
```

#### 4. 영카트 권한 체크 → 도매까 권한 체크 대체

**⚠️ 중요**: 신규/개조 메뉴에서는 **기존 영카트 권한 체크를 완전히 제거**하고 도매까 권한 체크로 대체해야 합니다.

##### 제거해야 할 영카트 권한 체크 함수들

```php
// ❌ 제거해야 할 기존 영카트 권한 체크들
auth_check_menu($auth, $sub_menu, "r");     // 기본 메뉴 접근 권한
auth_check_menu($auth, $sub_menu, "w");     // 쓰기 권한
auth_check($auth[$sub_menu], "r");          // 배열 기반 권한 체크
auth_check($auth[$sub_menu], "w");          // 배열 기반 쓰기 권한

// ❌ 제거해야 할 기존 변수들
$auth = get_admin_token();                  // 영카트 권한 토큰
$is_admin = admin_check_menu($auth, ...);   // 영카트 관리자 체크
```

##### 도매까 권한 체크로 대체

```php
<?php
// ✅ 올바른 도매까 개조/신규 메뉴 파일 구조

// === 1. 기본 include (영카트 기본) ===
include_once('./_common.php');

// === 2. 영카트 권한 체크 제거 후 도매까 권한 체크 적용 ===
// ❌ 기존 영카트 권한 체크 제거
// auth_check_menu($auth, $sub_menu, "r"); // 이런 라인들 모두 제거

// ✅ 도매까 공통 권한 체크 적용
include_once('./dmk/include/dmk_head.sub.php');  // 이것이 모든 기본 권한 체크 처리

// === 3. 메뉴별 커스텀 권한 체크 (필요시에만) ===
// 특정 작업 권한 체크 (예: 상품 등록)
if ($_POST['w'] == 'u' && !dmk_check_action_permission($dmk_menu_code, 'create', $dmk_user_type)) {
    alert('상품 등록 권한이 없습니다.');
}

// 소유권 기반 권한 체크 (예: 타인 상품 수정 방지)  
if ($it_id && !dmk_check_item_ownership($it_id, $dmk_auth)) {
    alert('해당 상품을 수정할 권한이 없습니다.');
}

// === 4. 데이터 조회 시 도매까 필터링 적용 ===
// ❌ 기존 영카트 방식
// $sql = "SELECT * FROM g5_shop_item WHERE 1=1";

// ✅ 도매까 계층별 필터링 적용
$sql = "SELECT * FROM g5_shop_item WHERE 1=1 {$dmk_common_where}";

// 메뉴별 추가 필터링
$additional_where = dmk_get_item_filter_where($dmk_auth);
$sql .= $additional_where;

// === 5. 메뉴별 커스텀 로직 ===
// 여기에 각 메뉴의 고유한 비즈니스 로직 구현
?>
```

##### 변경 전후 비교 예시

**변경 전 (기존 영카트)**:
```php
<?php
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "r");  // ❌ 영카트 권한 체크

$sql = "SELECT * FROM g5_shop_item WHERE 1=1";  // ❌ 필터링 없음
if (isset($stx) && $stx) {
    $sql .= " AND it_name LIKE '%{$stx}%'";
}
?>
```

**변경 후 (도매까 적용)**:
```php
<?php
include_once('./_common.php');
include_once('./dmk/include/dmk_head.sub.php');  // ✅ 도매까 권한 체크

// ✅ 도매까 계층별 필터링 자동 적용
$sql = "SELECT * FROM g5_shop_item WHERE 1=1 {$dmk_common_where}";
if (isset($stx) && $stx) {
    $sql .= " AND it_name LIKE '%{$stx}%'";
}
?>
```

### 계층별 데이터 필터링 시스템

#### 1. Chained SelectBox 구현

```javascript
// 도매까 계층별 선택박스 체인
function initDmkChainedSelect() {
    // 총판 선택 시 대리점 목록 로드
    $('#distributor_select').change(function() {
        var dt_id = $(this).val();
        loadAgencies(dt_id);
        $('#branch_select').empty().append('<option value="">지점 선택</option>');
    });
    
    // 대리점 선택 시 지점 목록 로드
    $('#agency_select').change(function() {
        var ag_id = $(this).val();
        loadBranches(ag_id);
    });
}

function loadAgencies(dt_id) {
    $.ajax({
        url: './dmk/ajax/get_agencies.php',
        data: {dt_id: dt_id},
        success: function(data) {
            $('#agency_select').html(data);
        }
    });
}
```

#### 2. 권한별 필터링 범위

```php
/**
 * 계층별 데이터 조회 범위 정의
 */
function dmk_get_data_scope($user_type, $user_id) {
    switch($user_type) {
        case 'super':
            return ['type' => 'all'];  // 모든 데이터
            
        case 'distributor':
            return [
                'type' => 'distributor',
                'dt_id' => $user_id,
                'include_agencies' => true,
                'include_branches' => true
            ];
            
        case 'agency':
            return [
                'type' => 'agency', 
                'ag_id' => $user_id,
                'include_branches' => true
            ];
            
        case 'branch':
            return [
                'type' => 'branch',
                'br_id' => $user_id
            ];
    }
}
```

### 메뉴 개발 워크플로우

#### 1. 개조 메뉴 개발 순서

1. **기존 파일 복사**: `itemlist.php` → `dmk/admin/itemlist.php`
2. **공통 권한 체크 추가**: `dmk_head.sub.php` include
3. **메뉴별 권한 설정**: `dmk_auth_config.php`에 권한 정의
4. **데이터 필터링 적용**: WHERE 조건에 계층별 필터 추가
5. **UI 수정**: 권한에 따른 버튼/링크 표시/숨김
6. **테스트**: 각 권한별 접근 및 기능 테스트

#### 2. 신규 메뉴 개발 순서

1. **템플릿 복사**: 기존 `list/form/form_update` 패턴 복사
2. **메뉴 코드 정의**: 새로운 메뉴 코드 생성
3. **권한 설정**: `dmk_auth_config.php`에 새 메뉴 권한 추가
4. **데이터 모델링**: 계층별 소유권 필드 추가
5. **비즈니스 로직 구현**: 도매까 요구사항에 맞는 로직
6. **UI/UX 구현**: 계층별 인터페이스 차별화
7. **통합 테스트**: 전체 시스템과의 연동 테스트

### 권한 체크 필수 적용

```php
// 1. 기본 메뉴 접근 권한 (dmk_head.sub.php에서 자동 처리)
include_once('./dmk/include/dmk_head.sub.php');

// 2. 작업별 권한 체크 (각 메뉴에서 커스텀)
if (!dmk_check_action_permission($dmk_menu_code, 'create', $dmk_user_type)) {
    alert('등록 권한이 없습니다.');
}

// 3. 소유권 기반 권한 체크
if (!dmk_check_data_ownership($data_id, $dmk_auth)) {
    alert('해당 데이터를 수정할 권한이 없습니다.');
}

// 4. 계층별 데이터 필터링
$where_condition = dmk_get_auth_where_condition($dmk_auth);
$sql .= " WHERE 1=1 {$where_condition}";
```

### 도매까 메뉴 개발 체크리스트

#### 개발 시작 전 체크리스트
- [ ] 기존 영카트 파일을 복사할 때 권한 관련 코드 위치 파악
- [ ] 해당 메뉴의 도매까 권한 요구사항 명확화 (어떤 사용자 유형이 접근 가능한지)
- [ ] 계층별 데이터 필터링 범위 정의

#### 코드 수정 체크리스트
- [ ] **영카트 권한 체크 완전 제거** (가장 중요!)
  - [ ] `auth_check_menu()` 함수 호출 제거
  - [ ] `auth_check()` 함수 호출 제거  
  - [ ] `$auth` 변수 사용 제거
  - [ ] 기타 영카트 권한 관련 변수/함수 제거
- [ ] **도매까 권한 체크 적용**
  - [ ] `dmk_head.sub.php` include 추가
  - [ ] `dmk_auth_config.php`에 메뉴 권한 정의 추가
  - [ ] 메뉴별 커스텀 권한 로직 구현 (필요시)
- [ ] **데이터 필터링 적용**
  - [ ] `$dmk_common_where` 변수를 모든 SELECT 쿼리에 적용
  - [ ] 계층별 추가 필터링 로직 구현
  - [ ] UI에서 권한에 따른 버튼/링크 표시/숨김 처리

#### 테스트 체크리스트
- [ ] **권한별 접근 테스트**
  - [ ] 본사(super) 관리자로 모든 기능 접근 확인
  - [ ] 총판(distributor) 관리자로 허용된 기능만 접근 확인
  - [ ] 대리점(agency) 관리자로 허용된 기능만 접근 확인
  - [ ] 지점(branch) 관리자로 허용된 기능만 접근 확인
- [ ] **데이터 필터링 테스트**
  - [ ] 각 권한별로 올바른 데이터만 조회되는지 확인
  - [ ] 타인 데이터 수정/삭제 차단 확인
  - [ ] Chained SelectBox 동작 확인 (해당되는 경우)
- [ ] **보안 테스트**
  - [ ] 권한 우회 시도 차단 확인
  - [ ] 직접 URL 접근 차단 확인
  - [ ] 타인 데이터 조작 시도 차단 확인

### 자주 발생하는 실수들

#### 1. 영카트 권한 체크 제거 누락
```php
// ❌ 실수 예시: 기존 영카트 권한 체크를 그대로 둔 채 도매까 권한 체크 추가
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "r");  // ❌ 이것을 제거하지 않음
include_once('./dmk/include/dmk_head.sub.php');  // ✅ 도매까 권한 추가

// ✅ 올바른 방식: 영카트 권한 체크 완전 제거
include_once('./_common.php');
// auth_check_menu($auth, $sub_menu, "r");  // ❌ 완전히 제거 또는 주석 처리
include_once('./dmk/include/dmk_head.sub.php');  // ✅ 도매까 권한만 사용
```

#### 2. 데이터 필터링 적용 누락
```php
// ❌ 실수 예시: 필터링 없이 모든 데이터 조회
$sql = "SELECT * FROM g5_shop_item WHERE it_use = '1'";

// ✅ 올바른 방식: 도매까 필터링 적용
$sql = "SELECT * FROM g5_shop_item WHERE it_use = '1' {$dmk_common_where}";
```

#### 3. 권한 변수 혼용
```php
// ❌ 실수 예시: 영카트 변수와 도매까 변수 혼용
if ($auth['itemlist']['w'] && $dmk_user_type == 'super') {  // ❌ $auth 사용

// ✅ 올바른 방식: 도매까 변수만 사용
if (dmk_check_action_permission($dmk_menu_code, 'create', $dmk_user_type)) {  // ✅
```

#### 4. 메뉴 권한 설정 누락
```php
// ❌ 실수: dmk_auth_config.php에 새 메뉴 권한 정의하지 않음
// 결과: dmk_head.sub.php에서 권한 체크 실패

// ✅ 해결: dmk_auth_config.php에 반드시 추가
$dmk_menu_access['new_menu_name'] = ['super', 'distributor'];
```

### 개발 시 주의사항

1. **권한 우회 방지**: 모든 파일에 `dmk_head.sub.php` 필수 include
2. **이중 권한 체크 금지**: 영카트 권한 체크와 도매까 권한 체크 동시 사용 금지
3. **일관성 유지**: 동일한 권한 체크 로직을 모든 메뉴에 적용
4. **변수 네임스페이스**: `$dmk_` 접두사 변수만 사용, 기존 `$auth` 변수 사용 금지
5. **성능 고려**: 권한 정보 캐싱으로 성능 최적화
6. **확장성 고려**: 새로운 권한 유형 추가 시 쉽게 확장 가능한 구조
7. **보안 강화**: SQL Injection, XSS 등 보안 취약점 방지
8. **테스트 필수**: 권한별 기능 테스트 반드시 수행

### FontAwesome 메뉴 아이콘 확장 가이드

#### 새로운 메뉴 아이콘 추가 방법

1. **아이콘 설정 추가** (`/web/adm/dmk/include/dmk_menu_icons.php`):
```php
// 새로운 메뉴 코드 추가 (예: 1000번 메뉴)
'1000' => [
    'icon' => 'fa-new-icon',           // 일반 상태 아이콘
    'active_icon' => 'fa-new-icon',    // 활성 상태 아이콘  
    'color' => '#007bff',              // 일반 상태 색상
    'active_color' => '#0056b3',       // 활성 상태 색상
    'title' => '새 메뉴'               // 툴팁 제목
],
```

2. **CSS 오버라이드 추가** (`/web/adm/css/admin_dmk_fontawesome.css`):
```css
/* 기존 이미지 아이콘 비활성화에 새 메뉴 추가 */
#gnb .gnb_li .btn_op.menu-1000,
#gnb .on .btn_op.menu-1000 {
    background-image: none !important;
}
```

3. **기존 CSS에 임시 이미지 아이콘 추가** (`/web/adm/css/admin.css`):
```css
/* 폴백용 이미지 아이콘 (선택사항) */
#gnb .gnb_li .btn_op.menu-1000{background:url(../img/menu-new-1.png) 50% 50% no-repeat #ebebeb }
#gnb .on .btn_op.menu-1000{background:url(../img/menu-new.png) 50% 50% no-repeat #fff}
```

#### 아이콘 커스터마이징

**색상 변경**:
```php
// 브랜드 색상 사용
'color' => '#ff6b6b',        // 도매까 브랜드 색상
'active_color' => '#ff5252', // 더 진한 톤
```

**애니메이션 효과 추가**:
```css
.dmk-menu-icon.fa-your-icon {
    transition: transform 0.3s ease;
}

.dmk-menu-icon.fa-your-icon:hover {
    transform: rotate(360deg);
}
```

**크기 조정**:
```css
.dmk-menu-icon.fa-large-icon {
    font-size: 20px !important; /* 기본 18px에서 확대 */
}
```

### 코드 리뷰 포인트

개발 완료 후 다음 사항들을 반드시 확인하세요:

1. **영카트 권한 코드 완전 제거 확인**
2. **dmk_head.sub.php include 확인**
3. **메뉴 권한 설정 추가 확인**
4. **데이터 필터링 적용 확인**
5. **권한별 UI 차별화 확인**
6. **보안 테스트 통과 확인**
7. **FontAwesome 아이콘 정상 표시 확인**
8. **메뉴 아이콘 반응형 동작 확인**

### 데이터베이스 확장
```sql
-- 기존 테이블에 도매까 필드 추가
ALTER TABLE g5_shop_item ADD COLUMN dmk_owner_type VARCHAR(20);
ALTER TABLE g5_shop_item ADD COLUMN dmk_owner_id INT;

-- 도매까 전용 테이블
CREATE TABLE g5_dmk_agencies (...);
CREATE TABLE g5_dmk_branches (...);
```

## 테스트 및 배포

### Playwright 테스트
```bash
# 관리자 시스템 테스트
npx playwright test headquarters.spec.ts

# 권한별 접근 테스트
npx playwright test auth.spec.ts

# 쇼핑몰 기능 테스트
npx playwright test shop.spec.ts
```

### 데이터베이스 마이그레이션
```bash
# 기본 테이블 생성
mysql -u root -p domaeka < dmk/sql/001_create_dmk_tables.sql

# 기존 테이블 확장
mysql -u root -p domaeka < dmk/sql/002_alter_existing_tables.sql

# 테스트 데이터 삽입
mysql -u root -p domaeka < dmk/sql/005_insert_test_data.sql

# 관리자 비밀번호 업데이트
php dmk/sql/004_update_admin_passwords.php
```

### 파일 권한 설정
```bash
chmod +x perms.sh && ./perms.sh
```

## 보안 고려사항

- **CSRF 토큰 검증**: 모든 폼에 토큰 적용
- **XSS 방지**: 사용자 입력 데이터 필터링
- **SQL Injection 방지**: Prepared Statement 사용
- **권한별 데이터 격리**: 계층별 데이터 접근 제한
- **파일 업로드 보안**: 확장자 및 MIME 타입 검증

## 성능 최적화

- **데이터베이스 인덱스**: 계층별 필터링 쿼리 최적화
- **캐시 시스템**: 메뉴 권한 및 설정 캐싱
- **AJAX 비동기 로딩**: 대용량 데이터 페이징 처리
- **이미지 최적화**: 상품 이미지 리사이징 및 압축

이 시스템은 전통적인 CMS의 안정성과 현대적인 계층형 비즈니스 요구사항을 성공적으로 결합하여, 도매까 공동구매 플랫폼의 복잡한 권한 관리와 비즈니스 로직을 효과적으로 지원합니다.