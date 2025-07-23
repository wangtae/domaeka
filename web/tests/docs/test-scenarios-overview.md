# 도매까 웹 관리페이지 자동화 테스트 시나리오

## 개요

도매까(Domaeka) 웹 관리페이지는 영카트5 기반의 4계층 프랜차이즈 관리 시스템입니다. 본 문서는 Playwright를 활용한 자동화 테스트 시나리오를 정의합니다.

### 테스트 환경
- **URL**: http://domaeka.local/adm
- **최고관리자**: admin / !domaekaservice@.
- **기반 시스템**: 영카트5 + 도매까 확장

### 관리 계층 구조
```
본사 (headquarters)
  └─ 총판 (distributor)
      └─ 대리점 (agency)
          └─ 지점 (branch)
```

### 관리자 타입
- **main**: 최고관리자 - 해당 계층의 모든 권한 보유
- **sub**: 서브관리자 - 최고관리자가 부여한 권한(g5_auth)만 사용 가능

### 권한 원칙
1. 각 계층은 자신과 하위 계층의 정보만 열람/수정/삭제 가능
2. 상위 계층 정보는 접근 불가
3. 동일 계층의 다른 조직 정보는 접근 불가

## 테스트 시나리오 구성

### 1. 단위 테스트 시나리오
각 계층별로 독립적인 테스트 시나리오 문서를 작성하여 관리합니다.

#### 최고관리자 테스트
- `01-headquarters-admin-test.md`: 본사 최고관리자 테스트
- `02-distributor-admin-test.md`: 총판 최고관리자 테스트
- `03-agency-admin-test.md`: 대리점 최고관리자 테스트
- `04-branch-admin-test.md`: 지점 최고관리자 테스트

#### 서브관리자 권한 테스트
- `05-distributor-sub-admin-test.md`: 총판 서브관리자 권한 테스트
- `06-agency-sub-admin-test.md`: 대리점 서브관리자 권한 테스트
- `07-branch-sub-admin-test.md`: 지점 서브관리자 권한 테스트

### 2. 통합 테스트 시나리오
계층 간 상호작용 및 권한 경계 테스트

- `10-hierarchy-permission-test.md`: 계층별 권한 경계 테스트
- `11-cascade-effect-test.md`: 상위 계층 변경 시 하위 계층 영향 테스트
- `12-chained-select-test.md`: Chained Select UI 동작 테스트

### 3. 엣지 케이스 테스트
예외 상황 및 오류 처리 테스트

- `20-error-handling-test.md`: 오류 상황 처리 테스트
- `21-concurrent-access-test.md`: 동시 접근 테스트

## 핵심 UI 컴포넌트

### Chained Select (연쇄 선택 박스)
"총판 → 대리점 → 지점" 순서로 선택하는 계층적 선택 UI
- 상위 선택에 따라 하위 옵션이 동적으로 변경
- 권한에 따라 표시되는 옵션이 제한됨

### 데이터 테이블
- `dmk_action_logs`: 관리자 엑션 로그 정보
- `dmk_distributors`: 총판 정보
- `dmk_agencies`: 대리점 정보
- `dmk_branches`: 지점 정보
- `g5_member`: 관리자 계정 정보
- `g5_auth`: 권한 정보

## 테스트 데이터 관리

### 테스트 계정 명명 규칙
- 본사: `admin` (기본 최고관리자)
- 총판: `d_test_XX` (예: d_test_01)
- 대리점: `a_test_XX` (예: a_test_01)
- 지점: `b_test_XX` (예: b_test_01)
- 서브관리자: `{계층}_{역할}_sub_XX` (예: d_sales_sub_01)

### 테스트 데이터 생명주기
1. **생성 순서**: 총판 → 대리점 → 지점 (계층 구조 유지)
2. **삭제 순서**: 지점 → 대리점 → 총판 (역순 삭제)
3. **데이터 유지**: 하위 계층 테스트를 위해 상위 계층 데이터는 전체 테스트 완료까지 유지

### 관리자 액션 로그 검증
모든 관리 작업(조회/등록/수정/삭제)은 관리자 액션 로그에 기록되며, 각 테스트 단계에서 로그 기록 여부를 검증합니다.

## 테스트 데이터 관리 상세

### 핵심 원칙

#### 1. 계층적 의존성 유지
- 총판 → 대리점 → 지점 순서로 데이터 생성
- 지점 → 대리점 → 총판 역순으로 데이터 삭제
- 상위 계층 데이터는 하위 계층 테스트 완료까지 유지

#### 2. 테스트 데이터 격리
- 각 테스트는 고유한 식별자를 가진 데이터 생성
- 기존 운영 데이터와 충돌하지 않는 명명 규칙 사용
- 테스트 접두어 사용: `test_`, `d_test_`, `a_test_`, `b_test_`

### 테스트 데이터 생명주기

#### Phase 1: 테스트 환경 준비
```javascript
// 1. 기존 테스트 데이터 존재 여부 확인
async function checkExistingTestData() {
  // test_ 접두어를 가진 데이터 조회
  // 존재하면 경고 메시지 출력
}

// 2. 테스트 시작 전 스냅샷 생성 (선택적)
async function createDatabaseSnapshot() {
  // 현재 데이터베이스 상태 백업
}
```

#### Phase 2: 계층별 데이터 생성
```
테스트 순서:
1. 본사 관리자 테스트
   └─ 총판 생성 (d_test_01, d_test_02)
   
2. 총판 관리자 테스트
   └─ 대리점 생성 (a_test_01, a_test_02)
   
3. 대리점 관리자 테스트
   └─ 지점 생성 (b_test_01, b_test_02)
   
4. 각 계층 서브관리자 테스트
   └─ 서브관리자 계정 생성
```

#### Phase 3: 테스트 데이터 정리
```
정리 순서 (역순):
1. 서브관리자 계정 삭제
2. 지점 데이터 삭제
3. 대리점 데이터 삭제
4. 총판 데이터 삭제
5. 관련 로그 데이터 정리 (선택적)
```

### 데이터 정리 구현 방법

#### 1. AfterAll Hook 활용
```javascript
test.afterAll(async ({ page }) => {
  // 전체 테스트 완료 후 데이터 정리
  await cleanupTestData(page);
});
```

#### 2. 계층별 삭제 함수
```javascript
async function cleanupTestData(page) {
  // 1. 지점 삭제
  await deleteBranches(page, ['b_test_01', 'b_test_02']);
  
  // 2. 대리점 삭제
  await deleteAgencies(page, ['a_test_01', 'a_test_02']);
  
  // 3. 총판 삭제
  await deleteDistributors(page, ['d_test_01', 'd_test_02']);
  
  // 4. 서브관리자 삭제
  await deleteSubAdmins(page, [
    'd_sales_sub_01',
    'a_order_sub_01',
    'b_staff_sub_01'
  ]);
}
```

#### 3. 안전한 삭제 처리
```javascript
async function deleteWithRetry(page, deleteFunction, id, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      await deleteFunction(page, id);
      return true;
    } catch (error) {
      if (error.message.includes('하위 조직이 존재')) {
        // 하위 조직 먼저 삭제 후 재시도
        await wait(1000);
        continue;
      }
      throw error;
    }
  }
  return false;
}
```

### 관리자 액션 로그 검증 상세

#### 1. 로그 기록 항목
- 로그인/로그아웃
- 조회 (목록, 상세)
- 등록
- 수정
- 삭제
- 권한 변경

#### 2. 로그 검증 함수
```javascript
async function verifyActionLog(page, action, target, adminId) {
  // dmk_action_logs 테이블 조회
  const log = await page.evaluate(async (params) => {
    // Ajax 또는 직접 DB 조회로 최근 로그 확인
    const response = await fetch('/adm/ajax_get_admin_log.php', {
      method: 'POST',
      body: JSON.stringify({
        action: params.action,
        target: params.target,
        admin_id: params.adminId,
        limit: 1
      })
    });
    return response.json();
  }, { action, target, adminId });
  
  expect(log).toBeTruthy();
  expect(log.action).toBe(action);
  expect(log.target).toContain(target);
}
```

#### 3. 각 테스트 단계별 로그 검증
```javascript
// 총판 등록 후
await verifyActionLog(page, 'INSERT', 'd_test_01', 'admin');

// 대리점 수정 후
await verifyActionLog(page, 'UPDATE', 'a_test_01', 'd_test_01');

// 지점 삭제 후
await verifyActionLog(page, 'DELETE', 'b_test_01', 'a_test_01');
```

### 테스트 데이터 상태 관리

#### 1. 테스트 컨텍스트 공유
```javascript
// test-context.js
export const testContext = {
  createdDistributors: [],
  createdAgencies: [],
  createdBranches: [],
  createdSubAdmins: [],
  
  addDistributor(id) {
    this.createdDistributors.push(id);
  },
  
  addAgency(id) {
    this.createdAgencies.push(id);
  },
  
  // ... 기타 메서드
};
```

#### 2. 테스트 간 데이터 전달
```javascript
test.describe('프랜차이즈 관리 통합 테스트', () => {
  test('총판 생성', async ({ page }) => {
    // 총판 생성
    await createDistributor(page, 'd_test_01');
    testContext.addDistributor('d_test_01');
  });
  
  test('대리점 생성', async ({ page }) => {
    // 이전 테스트에서 생성한 총판 사용
    const distributorId = testContext.createdDistributors[0];
    await createAgency(page, 'a_test_01', distributorId);
    testContext.addAgency('a_test_01');
  });
});
```

### 예외 상황 처리

#### 1. 부분 실패 시 정리
- 테스트 중간 실패 시에도 생성된 데이터는 정리
- try-finally 블록 활용

#### 2. 정리 실패 시 로깅
```javascript
async function cleanupWithLogging(page) {
  const failures = [];
  
  for (const id of testContext.createdBranches) {
    try {
      await deleteBranch(page, id);
    } catch (error) {
      failures.push({ type: 'branch', id, error: error.message });
    }
  }
  
  if (failures.length > 0) {
    console.error('테스트 데이터 정리 실패:', failures);
    // 수동 정리 필요 알림
  }
}
```

#### 3. 수동 정리 스크립트
```sql
-- 테스트 데이터 수동 정리 SQL
-- 주의: test_ 접두어를 가진 데이터만 삭제

-- 지점 삭제
DELETE FROM dmk_branches WHERE branch_id LIKE 'b_test_%';

-- 대리점 삭제
DELETE FROM dmk_agencies WHERE agency_id LIKE 'a_test_%';

-- 총판 삭제
DELETE FROM dmk_distributors WHERE distributor_id LIKE 'd_test_%';

-- 관련 회원 삭제
DELETE FROM g5_member WHERE mb_id LIKE '%_test_%' AND mb_level IN (8,7,6,5);

-- 권한 삭제
DELETE FROM g5_auth WHERE mb_id LIKE '%_test_%';
```

### 모범 사례

#### 1. 테스트 독립성 보장
- 각 테스트는 다른 테스트에 의존하지 않도록 설계
- 필요한 전제 조건은 각 테스트에서 직접 생성

#### 2. 명확한 테스트 데이터 식별
- 테스트 데이터는 명확한 접두어/접미어 사용
- 실제 운영 데이터와 구분 가능하도록 설계

#### 3. 정리 검증
- 데이터 정리 후 실제로 삭제되었는지 검증
- 관련 연결 데이터도 함께 정리되었는지 확인

#### 4. 로그 보존
- 테스트 관련 로그는 별도로 보존
- 문제 발생 시 추적 가능하도록 관리

## 테스트 실행 방법

### 기본 실행 명령어
```bash
# 모든 테스트 실행
npx playwright test

# 특정 테스트 파일 실행
npx playwright test headquarters.spec.ts

# 디버그 모드로 실행
npx playwright test --debug

# 테스트 결과 리포트 보기
npx playwright show-report
```

### 고급 실행 전략
테스트 실행 모드, 병렬 처리, 데이터 정리 분리 등 상세한 실행 전략은 `test-execution-strategy.md` 참조

## 다음 단계

1. 각 단위 테스트 시나리오 문서 작성
2. Playwright 테스트 코드 구현
3. CI/CD 파이프라인 통합
4. 테스트 커버리지 분석