# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.


- mysql mcp 가 연결되어 있습니다.
- playwright mcp가 연결되어 있습니다.
- filesystem mcp가 연결되어 있습니다. 
- 카카오 소셜 로그인 모듈은 영카트 기본 구현을 절대로 수정해서는 안됩니다. (오랜 기간 검증된 모델입니다. 새로운 시도를 하려면 복사본을 만들어서 별도로 진행해야 합니다.)
- 영카트 쇼핑몰 운영 방식을 변경하는 수정은 해서는 안됩니다.

## 주요 개발 명령어

### 테스트 환경
- **URL**: http://domaeka.local/adm
- **최고관리자**: admin / !domaekaservice@.
- **기반 시스템**: 영카트5 + 도매까 확장

### 테스트 실행
```bash
# Playwright 테스트 실행
npx playwright test

# 특정 테스트 파일 실행
npx playwright test headquarters.spec.ts

# 테스트 결과 HTML 리포트 보기
npx playwright show-report
```

### 개발 환경 설정
```bash
# 의존성 설치
npm install

# 파일 권한 설정 (필요 시)
chmod +x perms.sh && ./perms.sh
```

### 데이터베이스 관리
mysql mcp 연동되어 있으므로 직접 확인 가능.

## 프로젝트 구조

### 핵심 디렉토리

```
web/
├── dmk/                    # 도매까 커스텀 개발 코드
│   ├── adm/               # 관리자 페이지 확장
│   ├── sql/               # 데이터베이스 마이그레이션
│   └── docs/              # 프로젝트 문서
├── adm/                   # 기존 관리자 시스템
├── shop/                  # 쇼핑몰 기능
├── bbs/                   # 게시판 시스템
├── lib/                   # 공통 라이브러리
├── plugin/                # 외부 플러그인
└── data/                  # 데이터 저장소
```

### 도매까 확장 기능 (dmk/)

이 프로젝트는 **그누보드5/영카트5 기반의 계층형 관리자 시스템**을 구현합니다:

- **본사(super)**: 모든 권한 보유
- **총판(distributor)**: 총판 아래 대리점/지점 관리
- **대리점(agency)**: 대리점 아래 지점 관리  
- **지점(branch)**: 제한된 상품/주문 관리

## 아키텍처 특징

### 계층형 권한 시스템
- `dmk/dmk_global_settings.php`: 메뉴 권한 설정
- `dmk/adm/lib/admin.auth.lib.php`: 권한 인증 라이브러리
- `adm/admin.head.php`: 도매까 권한 시스템 통합

### 데이터베이스 구조
- **기본 테이블**: `g5_` 접두사 (그누보드5 표준)
- **도매까 확장 테이블**: `g5_dmk_` 접두사
- **계층 관리**: `g5_dmk_agencies`, `g5_dmk_branches`
- **상품 소유권**: `g5_shop_item`에 `dmk_owner_type/dmk_owner_id` 컬럼 추가

### 프론트엔드 구조
- **관리자 페이지**: `adm/` + `dmk/adm/` 통합
- **쇼핑몰**: `shop/` (영카트5 기반)
- **게시판**: `bbs/` (그누보드5 기반)
- **모바일**: `mobile/` (반응형 지원)

## 주요 설정 파일

### 필수 설정 파일
- `data/dbconfig.php`: 데이터베이스 연결 설정
- `common.php`: 전역 상수 및 보안 설정
- `shop.config.php`: 쇼핑몰 설정
- `dmk/dmk_global_settings.php`: 계층별 메뉴 권한

### 개발 환경 설정
```php
// common.php에서 개발자 IP 설정
define('G5_DEVELOPER_IPS', '127.0.0.1,192.168.1.100');

// dmk_global_settings.php에서 개발자 모드 활성화
define('DMK_DEVELOPER_MODE', true);
```

## 코딩 규칙

### 파일 수정 시 주의사항
- 기존 그누보드5/영카트5 파일 수정 시 `dmk/docs/g5_modifications_log.md`에 기록
- 데이터베이스 변경 시 마이그레이션 파일 생성 (`dmk/sql/`)
- 새로운 기능은 `dmk/` 폴더에 구현 (기존 시스템 최소 수정)

### 권한 체크 패턴
```php
// 권한 확인
$auth = dmk_get_admin_auth();
if (!$auth || !dmk_is_menu_allowed('190200', $auth['mb_type'])) {
    alert('접근 권한이 없습니다.');
}
```

### 데이터베이스 접근 패턴
```php
// 계층별 데이터 필터링
$where_clause = dmk_get_hierarchy_where_clause($auth);
$sql = "SELECT * FROM g5_shop_item WHERE 1 $where_clause";
```

## 테스트 가이드

### Playwright 테스트 구조
```
tests/
├── headquarters.spec.ts   # 본사 관리자 테스트
├── distributor.spec.ts    # 총판 관리자 테스트
├── agency.spec.ts         # 대리점 관리자 테스트
└── branch.spec.ts         # 지점 관리자 테스트
```

### 테스트 데이터 준비
1. 데이터베이스 마이그레이션 실행
2. 테스트 데이터 삽입 (`005_insert_test_data.sql`)
3. 패스워드 표준화 (`004_update_admin_passwords.php`)

## 배포 시 주의사항

### 보안 설정
- `data/dbconfig.php`: Git 제외 (.gitignore 확인)
- `install/` 폴더: 운영 환경에서 삭제 필요
- 개발자 IP 설정: 운영 환경에서 제거

### 파일 권한
```bash
# 업로드 디렉토리 권한 설정
chmod 777 data/
chmod 777 data/file/
chmod 777 data/item/
```

### 성능 최적화
- `.cursorignore` 파일로 불필요한 파일 제외
- 캐시 디렉토리 정리: `data/cache/`, `data/tmp/`
- 로그 파일 관리: `data/log/`

## 문제 해결

### 관리자 메뉴 활성화 문제 (✅ 2025-01-24 해결완료)

#### 기존 문제점
**증상**: 관리자 페이지에서 좌측 메뉴나 서브메뉴가 선택된 상태로 표시되지 않음

**원인**: PHP와 JavaScript에서 메뉴 선택을 중복 처리하는 비효율적 구조
- PHP 측: `$sub_menu` 변수로 메뉴 선택 상태 설정
- JavaScript 측: 하드코딩된 URL 매핑으로 다시 메뉴 활성화
- 새 페이지 추가 시 두 곳 모두 수동 업데이트 필요

#### 해결방안: 메뉴 선택 자동화 시스템 구현

**1. 자동화 원리**
- PHP에서 설정한 `$sub_menu` 값을 JavaScript로 전달
- 메뉴 코드 기반으로 상위 메뉴 자동 감지 (예: `190900` → `.menu-190`)
- 하드코딩된 URL 매핑 제거하고 동적 처리로 변경

**2. 구현 내용 (`web/adm/admin.head.php` 수정)**
```javascript
// PHP에서 전달된 메뉴 정보
var currentSubMenu = '<?php echo isset($sub_menu) ? $sub_menu : ""; ?>';

// 메뉴 코드를 기반으로 상위 메뉴 자동 감지
if (currentSubMenu) {
    var menuGroup = currentSubMenu.substring(0, 3); // 예: "190900" -> "190"
    currentMenu = '.menu-' + menuGroup;
}

// 서브메뉴 자동 활성화
function activateSubmenu(currentPath, currentSubMenu) {
    if (currentSubMenu) {
        activeSubmenuId = currentSubMenu; // PHP 값 우선 사용
    } else {
        // 폴백: URL 기반 매핑 (기존 하드코딩 유지)
    }
}
```

**3. 사용법 (완벽 자동화)**
새로운 관리자 페이지에서는 단순히 다음 한 줄만 추가:
```php
$sub_menu = "190900"; // 원하는 메뉴 코드
include_once './_common.php';
```

**4. 개선 효과**
- ✅ **개발 효율성**: 메뉴 코드 하나만 설정하면 자동으로 메뉴 활성화
- ✅ **유지보수성**: JavaScript 하드코딩 제거로 관리 포인트 단순화  
- ✅ **확장성**: 새 페이지 추가 시 추가 설정 불필요
- ✅ **일관성**: 모든 페이지에서 동일한 방식으로 메뉴 처리

**5. 메뉴 그룹 매핑**
- `.menu-100` (환경설정): 100xxx 메뉴 코드
- `.menu-180` (봇 관리): 180xxx 메뉴 코드  
- `.menu-190` (프랜차이즈 관리): 190xxx 메뉴 코드
- `.menu-200` (회원관리): 200xxx 메뉴 코드
- `.menu-300` (게시판관리): 300xxx 메뉴 코드
- `.menu-400` (쇼핑몰관리): 400xxx 메뉴 코드
- `.menu-500` (쇼핑몰현황): 500xxx 메뉴 코드
- `.menu-900` (SMS관리): 900xxx 메뉴 코드

### 로그인 문제
- 패스워드 형식 확인: 그누보드5 표준 (`sha256:12000:...`)
- 권한 테이블 확인: `g5_auth`, `g5_dmk_admins`

### 권한 문제
- 메뉴 권한 설정: `dmk_global_settings.php`
- 관리자 타입 확인: `dmk_mb_type` 컬럼

### 데이터베이스 문제
- 마이그레이션 순서: `001` → `002` → `005` → `004` (패스워드 수정)
- 테이블 접두사: `g5_` (기본), `g5_dmk_` (도매까 확장)

## 참고 문서

- `web/README.md`: 프로젝트 전체 개요
- `dmk/docs/g5_modifications_log.md`: 기존 파일 수정 내역
- `docs/SCHEMA.md`: 데이터베이스 스키마 문서
- `docs/계층별메뉴시스템.md`: 메뉴 시스템 상세 설명