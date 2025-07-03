# CLAUDE.md

이 파일은 이 저장소에서 작업할 때 Claude Code (claude.ai/code)에게 가이드를 제공합니다.

## 프로젝트 개요

도매까는 그누보드 5 (v5.6.14)와 영카트 5를 기반으로 구축된 종합 전자상거래 플랫폼입니다. 본사, 총판, 대리점, 지점에 대한 역할 기반 접근 제어가 있는 계층적 다단계 관리 구조를 특징으로 합니다.

## 핵심 아키텍처

### 계층별 관리 시스템 (DMK)
시스템은 4단계 계층을 구현합니다:
- **Super (본사)**: 전체 시스템 접근 권한
- **Distributor (총판)**: 지역 유통 관리
- **Agency (대리점)**: 지역 대리점 운영
- **Branch (지점)**: 개별 지점 운영

각 계층은 `dmk/dmk_global_settings.php`에 정의된 특정 메뉴 권한과 기능 제한을 가지고 있습니다.

### 주요 디렉토리 구조
```
/
├── adm/                    # 관리자 백엔드 (계층 시스템의 핵심)
├── shop/                   # 전자상거래 프론트엔드/백엔드
├── bbs/                    # 게시판/포럼 시스템
├── mobile/                 # 모바일 최적화 페이지
├── dmk/                    # 도매까 전용 계층 시스템
├── lib/                    # 핵심 라이브러리 및 유틸리티
├── plugin/                 # 외부 연동 (결제, SMS 등)
├── data/                   # 데이터베이스 설정 및 업로드
├── common.php              # 전역 설정 및 기능
└── shop.config.php         # 전자상거래 설정
```

### 데이터베이스 설정
- 연결 설정: `data/dbconfig-domaeka.php`
- MySQL 서버: 1.201.172.211:3307
- 데이터베이스: domaeka
- 사용자: domaeka

## 일반적인 개발 명령어

### 테스트
```bash
# Playwright 테스트 실행
npx playwright test

# 특정 테스트 파일 실행
npx playwright test tests/headquarters.spec.ts

# UI와 함께 테스트 실행
npx playwright test --ui
```

### 개발 환경 설정
```bash
# 의존성 설치
npm install

# 로컬 서버 시작 (해당하는 경우)
# 프로젝트는 PHP/Nginx 스택에서 실행됩니다
```

### 데이터베이스 작업
```bash
# DMK 테이블 생성 실행
# dmk/sql/ 디렉토리의 SQL 파일을 숫자 순서대로 실행:
# 001_create_dmk_tables.sql
# 002_alter_existing_tables.sql
# 005_insert_test_data.sql
# 그 다음 실행: 004_update_admin_passwords.php
```

## 개발용 주요 파일

### 핵심 시스템 파일
- `common.php` - 전역 함수 및 보안 설정
- `config.php` - 시스템 설정
- `version.php` - 버전 정보
- `data/dbconfig-domaeka.php` - 데이터베이스 연결 (민감한 정보)

### DMK 계층 시스템
- `dmk/dmk_global_settings.php` - 메뉴 권한 및 계층 설정
- `dmk/adm/lib/admin.auth.lib.php` - 인증 라이브러리
- `adm/_common.php` - 관리자 영역 공통 함수
- `adm/admin.head.php` - 관리자 메뉴 렌더링

### 전자상거래 핵심
- `shop.config.php` - 쇼핑몰 설정
- `shop/_common.php` - 쇼핑몰 공통 함수
- `lib/shop.lib.php` - 쇼핑몰 유틸리티 함수

## 핵심 개발 원칙

### 4계층 구조 원칙
**기존 영카트5 시스템을 4계층 관리자 구조로 확장**
- **본사 > 총판 > 대리점 > 지점** 순의 계층 구조
- **본사 관리자**: 기존 영카트 최고관리자에 해당하며 모든 메뉴 접근 가능
- **총판/대리점/지점**: `dmk/dmk_global_settings.php`에 정의된 메뉴만 접근 가능
- 모든 신규 기능은 `dmk/` 디렉토리 내에 구현하여 기존 시스템과 분리 유지

### Main/Sub 관리자 권한 모델
- **Main 관리자**: 각 계층당 1명, 해당 계층의 모든 기능 접근 가능
- **Sub 관리자**: 0명 이상 존재, 기본적으로 권한 없음
- Sub 관리자는 Main 관리자가 `g5_auth` 테이블을 통해 명시적으로 권한 부여 필요

### 수정 가능 메뉴 식별
- ⭐ (NEW): 신규 생성 메뉴 - 수정 허용
- 🚩 (개조): 기존 영카트 메뉴 개조 - 수정 허용  
- **주의**: 위 아이콘이 없는 메뉴는 절대 수정 금지

## 개발 가이드라인

### 표준 개발 패턴
**관리자 기능 파일 구조 (3파일 세트)**
- `_list.php`: 목록 조회 및 검색
- `_form.php`: 데이터 등록/수정 폼
- `_form_update.php`: 폼 데이터 DB 처리

**계층 선택 UI**
- `dmk_render_chain_select()` 함수 사용 필수
- 동적 AJAX 기반 계층 선택박스 (총판-대리점-지점) 제공
- 레거시 코드에는 미적용되었을 수 있으므로 구분 필요

### 권한 관리 함수 (필수 적용)
- `dmk_can_access_menu()`: 메뉴 접근 권한 확인
- `dmk_authenticate_form_access()`: 폼 접근 통합 권한 관리
- `dmk_can_create_admin()`: 관리자 생성 권한 확인
- `check_admin_token()`: CSRF 방어
- `dmk_log_admin_action()`: 관리자 활동 로깅

### 코드 표준
- 모든 Git 커밋 메시지는 한글로 작성해야 합니다
- 타임스탬프는 ISO 8601 UTC 형식 사용: 'YYYY-MM-DDTHH:mm:ssZ'
- 데이터베이스 자동 증가 ID는 'id'로 명명해야 합니다
- 기존 PHP 코딩 규칙을 따라야 합니다

### 보안 요구사항
- `data/dbconfig-domaeka.php` 같은 민감한 파일은 절대 커밋하지 마세요
- 패스워드 해싱은 `get_encrypt_string()` 사용 (그누보드 5 표준)
- 모든 사용자 입력은 기존 보안 함수를 통해 검증해야 합니다
- 모든 관리자 활동은 `dmk_action_logs` 테이블에 기록 필수

### 계층 시스템 개발
관리자 메뉴나 권한을 수정할 때:
1. `dmk/dmk_global_settings.php`에서 `$DMK_MENU_CONFIG` 업데이트
2. 다양한 사용자 타입으로 테스트: super, distributor, agency, branch
3. 디버그 페이지 사용: `dmk/test_menu_debug.php`
4. `dmk/docs/g5_modifications_log.md`에 변경사항 문서화

### 메뉴 코드 시스템
- 100XXX: 시스템 설정
- 190XXX: 프랜차이즈 관리 (DMK 전용)
- 200XXX: 회원 관리
- 300XXX: 게시판 관리
- 400XXX: 쇼핑몰 관리
- 500XXX: 쇼핑몰 통계/리포팅
- 900XXX: SMS 관리

## 권한 시스템 현황 및 검증된 모듈

### 완전 검증된 모듈 (권한 로직 문제 없음)
#### 총판 관리 (`dmk/adm/distributor_admin/`)
- ✅ `distributor_list.php`: 계층별 데이터 조회 권한 필터링 완료
- ✅ `distributor_form.php`: 통합 권한 관리 및 필드 접근 제어 완료
- ✅ `distributor_form_update.php`: CSRF 방어 및 액션 로깅 완료

#### 대리점 관리 (`dmk/adm/agency_admin/`)
- ✅ `agency_list.php`: 계층별 데이터 조회 권한 필터링 완료
- ✅ `agency_form.php`: 소속 총판 필드 제어 및 상태 필드 접근 제어 완료
- ✅ `agency_form_update.php`: 총판별 대리점 등록 권한 검증 완료

### 개선 완료된 모듈
#### 지점 관리 (`dmk/adm/branch_admin/`)
- ✅ `branch_list.php`: 권한 로직 문제 없음
- ✅ `branch_form.php`: 권한 로직 문제 없음
- 🔧 `branch_form_update.php`: `dmk_authenticate_form_access()` 함수 호출 추가로 권한 검증 개선

#### 통계 분석 (`dmk/adm/statistics/statistics_dashboard.php`)
- 🔧 메뉴 접근 권한 확인을 `dmk_can_access_menu()` 사용으로 변경
- 🔧 중복 정의된 상수 제거
- 🔧 지점 관리자 데이터 필터링 변수 수정 (`dmk_auth['mb_id']` 사용)

## 테스트 접근 방식

### Playwright 테스트
- 테스트 파일: `tests/*.spec.ts`
- 다양한 사용자 계층 레벨 테스트
- 전자상거래 워크플로우 테스트
- 수동 테스트 시나리오는 `tests/playwright_commands.md` 사용

### 수동 테스트
- 관리자 로그인: 다양한 `dmk_mb_type` 값을 가진 계정 사용
- 계층 레벨별 메뉴 가시성 테스트
- 사용자 타입별 쇼핑몰 기능 테스트

## 일반적인 문제 및 해결방법

### 패스워드 인증
- 기존 MySQL PASSWORD() 해시는 그누보드 5와 호환되지 않음
- `dmk/sql/004_update_admin_passwords.php`를 사용하여 수정
- 새 패스워드는 항상 `get_encrypt_string()` 사용

### 메뉴 권한
- 메뉴는 `dmk/dmk_global_settings.php`에서 제어됨
- `dmk_is_menu_allowed()` 함수가 권한 확인
- `dmk/test_menu_debug.php`로 디버깅

### 데이터베이스 연결
- `lib/connect.lib.php`의 기존 연결 함수 사용
- 새로운 연결 방법을 만들지 마세요
- 기존 트랜잭션 패턴을 존중하세요

## 파일 수정 추적

원본 그누보드/영카트 파일의 모든 수정사항은 다음 내용과 함께 `dmk/docs/g5_modifications_log.md`에 문서화되어야 합니다:
- 파일 경로
- 수정 날짜 (ISO 8601 UTC)
- 작성자
- 변경사항 요약
- 상세한 코드 변경사항

## 상품 유통 및 계층 권한 가이드

### 상품 유통 흐름 (공급 → 수신 → 노출)

도매까 시스템의 상품 유통은 **공급**, **수신**, **노출**의 3단계로 이루어집니다.

#### 1. 공급 (Supply) 단계
상위 계층이 하위 계층에게 상품을 제공하는 단계입니다.

**본사 → 총판 공급**
- 본사 관리자가 "총판용 상품"으로 등록한 상품들
- 총판은 본사가 공급한 상품 목록을 확인 가능
- 총판이 원하는 상품을 선택하여 "수신"할 수 있음

**총판 → 대리점 공급**
- 총판이 보유한 상품(본사로부터 수신 + 자체 등록) 중에서 대리점에게 제공할 상품 선택
- 대리점은 소속 총판이 공급한 상품 목록 확인 가능

**대리점 → 지점 공급**
- 대리점이 보유한 상품 중에서 지점에게 제공할 상품 선택
- 지점은 소속 대리점이 공급한 상품 목록 확인 가능

#### 2. 수신 (Receive) 단계
하위 계층이 상위 계층으로부터 제공받은 상품을 자신의 상품 목록에 추가하는 단계입니다.

**수신 권한**
- 총판: 본사로부터 공급받은 상품 수신 가능
- 대리점: 소속 총판으로부터 공급받은 상품 수신 가능  
- 지점: 소속 대리점으로부터 공급받은 상품 수신 가능

**수신 프로세스**
1. 공급받은 상품 목록에서 원하는 상품 선택
2. 가격, 재고 등 일부 정보 조정 가능 (정책에 따라)
3. "수신" 버튼 클릭으로 자신의 상품 목록에 추가
4. 수신된 상품은 해당 계층의 쇼핑몰에서 판매 가능한 상태가 됨

#### 3. 노출 (Display) 단계
각 계층의 쇼핑몰에서 상품이 실제로 고객에게 보여지는 단계입니다.

**노출 제어**
- 각 계층은 보유한 상품 중에서 실제로 쇼핑몰에 노출할 상품을 선택
- "노출 대상" 설정을 통해 특정 고객 그룹에게만 보여줄 수 있음
- 일시 품절, 시즌 오프 등의 이유로 일시적 노출 중단 가능

### UI 컴포넌트 예시

#### 공급 관리 인터페이스
```
[상품 공급 관리]
┌─────────────────────────────────────────┐
│ 공급 대상: [▼ 총판 선택]                │
│ 상품 검색: [________] [검색]              │
│                                         │
│ ☐ 상품A (재고: 100개) [공급하기]        │
│ ☐ 상품B (재고: 50개)  [공급하기]        │  
│ ☑ 상품C (재고: 200개) [공급중단]        │
│                                         │
│ [선택 상품 일괄 공급] [공급 내역]        │
└─────────────────────────────────────────┘
```

#### 수신 관리 인터페이스
```
[상품 수신 관리]
┌─────────────────────────────────────────┐
│ 공급처: 본사                            │
│ 공급 상품 목록:                         │
│                                         │
│ ○ 상품A (공급가: 10,000원) [수신하기]   │
│   ├ 판매가: [12,000원]                  │
│   └ 초기재고: [10개]                    │
│                                         │
│ ○ 상품B (공급가: 15,000원) [수신하기]   │
│   ├ 판매가: [18,000원]                  │
│   └ 초기재고: [5개]                     │
│                                         │
│ [선택 상품 일괄 수신]                   │
└─────────────────────────────────────────┘
```

#### 노출 관리 인터페이스
```
[상품 노출 관리]
┌─────────────────────────────────────────┐
│ 보유 상품 목록:                         │
│                                         │
│ ☑ 상품A [노출중] 대상: 일반고객         │
│ ☐ 상품B [비노출] 대상: VIP고객만        │
│ ☑ 상품C [노출중] 대상: 전체             │
│                                         │
│ 노출 대상 설정:                         │
│ ○ 전체 고객                            │
│ ○ 일반 고객만                          │
│ ○ VIP 고객만                           │
│ ○ 특정 그룹 [▼선택]                    │
│                                         │
│ [노출 설정 저장]                        │
└─────────────────────────────────────────┘
```

### 데이터 구조

#### 상품 테이블 (g5_shop_item)
```sql
-- 기존 필드 외 추가
dmk_supply_chain TEXT,     -- 공급 체인 정보 (JSON)
dmk_source_type ENUM('own', 'received'),  -- 자체상품/수신상품 구분
dmk_source_id VARCHAR(50), -- 원본 상품 ID (수신상품인 경우)
dmk_visibility_target VARCHAR(100), -- 노출 대상 설정
```

#### 공급 관리 테이블 (dmk_supply_management)
```sql
CREATE TABLE dmk_supply_management (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_type ENUM('super', 'distributor', 'agency') NOT NULL,
  supplier_id VARCHAR(50) NOT NULL,
  receiver_type ENUM('distributor', 'agency', 'branch') NOT NULL, 
  receiver_id VARCHAR(50) NOT NULL,
  item_id VARCHAR(50) NOT NULL,
  supply_price DECIMAL(10,2),
  status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 수신 내역 테이블 (dmk_receive_history)
```sql
CREATE TABLE dmk_receive_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receiver_type ENUM('distributor', 'agency', 'branch') NOT NULL,
  receiver_id VARCHAR(50) NOT NULL,
  supplier_type ENUM('super', 'distributor', 'agency') NOT NULL,
  supplier_id VARCHAR(50) NOT NULL,
  original_item_id VARCHAR(50) NOT NULL,
  received_item_id VARCHAR(50) NOT NULL,
  receive_price DECIMAL(10,2),    
  sell_price DECIMAL(10,2),
  initial_stock INT DEFAULT 0,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 상품 등록 권한 (계층별)

#### 본사 (Super Admin)
- **자체 상품 등록**: 무제한
- **공급 대상**: 전체 총판, 특정 총판, 특정 대리점, 특정 지점
- **카테고리**: 모든 카테고리 접근 가능
- **가격 정책**: 공급가, 권장판매가 설정 가능

#### 총판 (Distributor)
- **자체 상품 등록**: 가능 (자체 브랜드, 전용 상품 등)
- **수신 상품**: 본사로부터 공급받은 상품 수신 가능
- **공급 대상**: 소속 대리점, 소속 지점
- **카테고리**: 소속 총판 카테고리 + 본사 허용 카테고리
- **가격 정책**: 본사 공급가 기준으로 마진 설정

#### 대리점 (Agency)  
- **자체 상품 등록**: 제한적 가능 (소규모 지역 특산품 등)
- **수신 상품**: 소속 총판으로부터 공급받은 상품 수신 가능
- **공급 대상**: 소속 지점
- **카테고리**: 소속 대리점 카테고리 + 총판 허용 카테고리
- **가격 정책**: 총판 공급가 기준으로 마진 설정

#### 지점 (Branch)
- **자체 상품 등록**: 매우 제한적 (지점장 특별 승인 상품만)
- **수신 상품**: 소속 대리점으로부터 공급받은 상품 수신 가능  
- **공급 대상**: 없음 (최종 판매점)
- **카테고리**: 소속 지점 카테고리만
- **가격 정책**: 대리점 공급가 기준으로 소폭 마진만 조정 가능

### 노출 대상 제어 기능

#### 고객 그룹별 노출
```php
// 고객 등급별 상품 노출 제어
$visibility_rules = [
    'all' => '전체 고객',
    'normal' => '일반 고객', 
    'vip' => 'VIP 고객',
    'premium' => '프리미엄 고객',
    'wholesale' => '도매 고객'
];
```

#### 지역별 노출
```php
// 지역별 상품 노출 제어 (지점 한정)
$regional_visibility = [
    'local_only' => '지역 고객만',
    'neighbor_area' => '인근 지역 포함',
    'nationwide' => '전국 배송 가능'
];
```

#### 시간별 노출
```php
// 특정 시간대/기간별 노출 제어
$time_based_visibility = [
    'always' => '항상 노출',
    'business_hours' => '영업시간만',
    'weekend_only' => '주말만',
    'event_period' => '이벤트 기간만'
];
```

### 구현 제안사항

#### 1단계: 공급 관리 시스템
- 상위 계층의 상품 공급 인터페이스 구축
- 공급 가능 상품 목록 및 공급가 설정 기능
- 공급 대상 선택 (특정 하위 계층 또는 전체)

#### 2단계: 수신 관리 시스템  
- 하위 계층의 상품 수신 인터페이스 구축
- 수신 가능 상품 목록 조회 및 선택 기능
- 수신 시 가격 조정 및 초기 재고 설정 기능

#### 3단계: 노출 제어 시스템
- 보유 상품의 쇼핑몰 노출 여부 관리
- 고객 그룹별, 지역별, 시간별 노출 제어
- 노출 통계 및 효과 분석 기능

#### 4단계: 확장 기능
- 상품 공급/수신 히스토리 추적
- 계층간 상품 성과 분석 및 리포팅
- 자동 공급/수신 규칙 설정 (조건부)
- 모바일 최적화 인터페이스

이 가이드를 통해 도매까 시스템의 상품 유통 체계를 체계적으로 이해하고 구현할 수 있습니다.

## 개발 환경

프로젝트는 다음을 사용합니다:
- PHP 7.4+
- MySQL 5.7+
- 그누보드 5.6.14
- 영카트 5.4.5.5.1
- UI 컴포넌트용 Bootstrap 5
- 테스트용 Playwright


## 기타
- dmk_it_owner_type, dmk_it_owner_id 필드는 deprecated 필드입니다.
- dmk_dt_id, dmk_ag_id, dmk_br_id를 활용해야 합니다. (하위 계층 소속인 경우, 상위 계층 필드들에도 값들이 다 채워져야 합니다.)