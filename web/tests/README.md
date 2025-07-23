# 도매까 프랜차이즈 관리 시스템 테스트

이 디렉토리는 도매까 프랜차이즈 관리 시스템의 계층별 권한 및 메뉴 기능을 테스트하는 Playwright 테스트를 포함합니다.

## 테스트 구조

### 메뉴별 테스트 파일

- **`distributor-admin.spec.ts`** - 총판 관리 메뉴 테스트
- **`agency-admin.spec.ts`** - 대리점 관리 메뉴 테스트  
- **`branch-admin.spec.ts`** - 지점 관리 메뉴 테스트

### 공통 유틸리티 (utils/)

- **`test-config.ts`** - 테스트 설정 및 상수 정의
- **`test-helpers.ts`** - 공통 테스트 유틸리티 함수
- **`data-cleanup.ts`** - 테스트 데이터 정리 유틸리티

## 테스트 원칙

### 계층별 권한 검증

각 테스트 파일은 다음 순서로 계층별 권한을 검증합니다:

1. **본사 관리자** - 모든 권한 (목록/등록/수정/삭제)
2. **상위 계층 관리자** - 하위 계층 관리 권한
3. **해당 계층 관리자** - 자신의 정보 조회/수정 권한
4. **하위 계층 관리자** - 접근 거부 확인

### 테스트 실패 시 중단

각 테스트는 이전 테스트가 실패하면 전체 테스트를 중지하도록 설계되었습니다.
특히 등록 테스트가 실패하면 수정/삭제 테스트가 의미없으므로 중단됩니다.

### 데이터 관리

- **기존 데이터**: 테스트 전부터 존재하는 계정들 (삭제하지 않음)
- **임시 데이터**: 테스트 중 생성되는 데이터 (테스트 후 자동 삭제)

## 테스트 실행 방법

### 개별 메뉴 테스트 실행

```bash
# 총판 관리 메뉴 테스트
npx playwright test distributor-admin.spec.ts --headed

# 대리점 관리 메뉴 테스트
npx playwright test agency-admin.spec.ts --headed

# 지점 관리 메뉴 테스트
npx playwright test branch-admin.spec.ts --headed
```

### 전체 테스트 실행 (순차)

```bash
# 모든 메뉴 테스트 순차 실행
npx playwright test distributor-admin.spec.ts agency-admin.spec.ts branch-admin.spec.ts --headed --workers=1
```

### 테스트 옵션

```bash
# 헤드리스 모드 (빠른 실행)
npx playwright test distributor-admin.spec.ts

# 특정 테스트만 실행
npx playwright test distributor-admin.spec.ts -g "본사 관리자"

# 데이터 정리 건너뛰기
SKIP_CLEANUP=true npx playwright test distributor-admin.spec.ts
```

## 주요 기능

### 계층별 권한 체계적 검증
- 본사 → 총판 → 대리점 → 지점 순서로 권한 검증
- 각 계층은 자신의 정보와 하위 계층만 관리 가능
- 상위 계층 접근 시 권한 거부 메시지 확인

### 자동 데이터 관리
- 테스트 시작 전 환경 초기화
- 테스트 중 임시 데이터 생성
- 테스트 완료 후 자동 정리 (역순 삭제)

### 실패 시 중단 메커니즘  
- 이전 테스트 실패 시 후속 테스트 중단
- 의존성 있는 테스트 순서 보장
- 명확한 실패 원인 제공

## 사전 요구사항

### 기본 테스트 계정 준비

다음 계정들이 시스템에 미리 생성되어 있어야 합니다:

- **test_distributor** (기존 총판)
- **test_agency** (기존 대리점) 
- **test_branch** (기존 지점)
- **admin** (본사 최고관리자)

### 환경 설정

```bash
BASE_URL=http://domaeka.local
SKIP_CLEANUP=false
```

## 생성된 파일 목록

### 테스트 파일
- `/web/tests/distributor-admin.spec.ts`
- `/web/tests/agency-admin.spec.ts`
- `/web/tests/branch-admin.spec.ts`

### 유틸리티 파일
- `/web/tests/utils/test-config.ts`
- `/web/tests/utils/test-helpers.ts`
- `/web/tests/utils/data-cleanup.ts`

### 문서
- `/web/tests/README.md` (이 파일)

### 백업 파일
- `/web/tests/backup-headquarters-admin.spec.ts` (기존 통합 테스트 백업)