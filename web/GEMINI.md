# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 주요 개발 명령어

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
```bash
# 도매까 테이블 생성
mysql -u root -p domaeka < dmk/sql/001_create_dmk_tables.sql

# 기존 테이블 수정
mysql -u root -p domaeka < dmk/sql/002_alter_existing_tables.sql

# 테스트 데이터 삽입
mysql -u root -p domaeka < dmk/sql/005_insert_test_data.sql

# 패스워드 표준화 (테스트 데이터 후 실행 필수)
php dmk/sql/004_update_admin_passwords.php
```

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