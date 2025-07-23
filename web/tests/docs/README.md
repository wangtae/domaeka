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
각 메뉴별로 독립적인 테스트 시나리오 문서를 작성하여 관리합니다.
메뉴별로 본사-총판-대리점-지점 관리자로 각각 로그인해서 테스트를 진행해야 합니다.
테스트를 진행하면서 실패시 전체를 중단해서 문제를 해결하고 다시 진행해야 합니다.
mysql mcp를 활용하여 db 테이블 구조와 입력되어 있는 데이터를 참고해야 합니다.

서브 관리자 권한 체크는 메인 관리자 테스트가 완료되면 테스트를 구현하여 진행합니다. (아직은 진행 안함)

#### 기본 데이터
- 총판(dmk_distributor)
  - domaeka : 실제 운영중인 총판으로 이 총판은 테스트에 이용하면 안됩니다.
  - distributor : 테스트 전에 미리 생성되어 있는 총판 아이디입니다.
  - d_test_01 : 테스트를 진행하면서 총판 아이디를 새로 생성할때 만들어야 하는 아이디입니다. (총판-대리점-지점 등록/수정 등의 테스트를 위함)

- 대리점(dmk_agency)
  - domaeka1 : 실제 운영중인 대리점으로 이 대리점은 테스트에 이용하면 안됩니다.
  - agency001 : 테스트 전에 미리 생성되어 있는 대리점 아이디입니다.
  - a_test_01 : 테스트를 진행하면서 대리점 아이디를 새로 생성할때 만들어야 하는 아이디입니다. (총판-대리점-지점 등록/수정 등의 테스트를 위함)

- 대리점(dmk_agency)
  - domaeka11 : 실제 운영중인 지점으로 이 지점은 테스트에 이용하면 안됩니다.
  - store001 : 테스트 전에 미리 생성되어 있는 지점 아이디입니다.
  - b_test_01 : 테스트를 진행하면서 지점 아이디를 새로 생성할때 만들어야 하는 아이디입니다. (총판-대리점-지점 등록/수정 등의 테스트를 위함)

- 각 단계별 서브관리자
  - 각 단계별로 생성되어 있는 서브관리자 아이디는 테스트로 이용할 일이 없으며 테스트를 위해서는 테스트용 서브관리자를 생성해서 테스트 해야 합니다. 
  - 각 메뉴들에 대한 계층별 테스트가 완료되면 각 계층별 해당 메뉴에 대한 서브 관리자를 등록후 권한을 부여하여 각 권한별로 테스트를 진행해야 합니다.
  - 읽기 권한만 설정하고 읽기 권한 가능, 쓰기 권한 불가능, 삭제 권한 불가능 여부를 테스트 해야 합니다. 
  - 마찬가지로 쓰기, 삭제 권한도 순차적으로 진행해야 합니다.

- 각 관리자 등록시 등록 필드 입력 방법 (등록페이지마다 필드는 다르지만 형식은 아래와 같습니다.)
  - 비밀 번호: !{아이디}$.
  - 이름 : {아이디}
  - 회사명 : {아이디}회사
  - 닉네임 : {아이디}
  - 이메일 : {아이디}@mail.com

- 관리자 정보 수정은 '회사명' 수정만 테스트 합니다. 
  - {아이디}회사 -> {아이디}수정정


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




# 테스트 실행 전략 및 가이드

## 개요
Playwright 테스트의 효율적인 실행과 관리를 위한 전략을 정의합니다.

## 테스트 실행 모드

### 1. 헤드풀 모드 (개발/디버깅용)
브라우저 UI를 표시하여 테스트 진행 상황을 시각적으로 확인

```bash
# 헤드풀 모드로 실행
npx playwright test --headed

# 디버그 모드로 실행 (중단점 사용 가능)
npx playwright test --debug

# 특정 테스트만 헤드풀 모드로 실행
npx playwright test headquarters.spec.ts --headed

# 느린 속도로 실행 (각 액션 확인 가능)
npx playwright test --headed --slow-mo=1000
```

### 2. 헤드리스 모드 (CI/CD용)
백그라운드에서 빠르게 실행

```bash
# 기본 헤드리스 모드
npx playwright test

# 병렬 실행 (기본값)
npx playwright test --workers=4

# 순차 실행
npx playwright test --workers=1
```

### 3. 하이브리드 접근법
개발 단계별로 다른 모드 사용

```javascript
// playwright.config.ts
export default defineConfig({
  use: {
    // 환경변수로 모드 제어
    headless: process.env.CI ? true : false,
    
    // 개발 환경에서만 느린 모션
    slowMo: process.env.CI ? 0 : 500,
    
    // 스크린샷 설정
    screenshot: 'only-on-failure',
    video: process.env.CI ? 'retain-on-failure' : 'on',
  },
});
```

## 테스트와 데이터 정리 분리

### 1. 테스트 실행 단계
```bash
# 1단계: 테스트만 실행 (데이터 정리 없음)
npm run test:no-cleanup

# 2단계: 테스트 결과 확인
npm run test:report

# 3단계: 데이터 정리 (별도 실행)
npm run test:cleanup
```

### 2. package.json 스크립트 설정
```json
{
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "playwright test --debug",
    "test:no-cleanup": "SKIP_CLEANUP=true playwright test",
    "test:cleanup": "node scripts/cleanup-test-data.js",
    "test:full": "npm run test:no-cleanup && npm run test:cleanup",
    "test:report": "playwright show-report"
  }
}
```

### 3. 조건부 정리 구현
```javascript
// tests/helpers/cleanup.ts
export async function conditionalCleanup(page: Page) {
  if (process.env.SKIP_CLEANUP === 'true') {
    console.log('테스트 데이터 정리 건너뛰기 (SKIP_CLEANUP=true)');
    return;
  }
  
  await cleanupTestData(page);
}

// 테스트 파일에서
test.afterAll(async ({ page }) => {
  await conditionalCleanup(page);
});
```

### 4. 독립적인 정리 스크립트
```javascript
// scripts/cleanup-test-data.js
const { chromium } = require('@playwright/test');

async function cleanupAllTestData() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  try {
    // 관리자 로그인
    await page.goto('http://domaeka.local/adm');
    await page.fill('#login_id', 'admin');
    await page.fill('#login_pw', '!domaekaservice@.');
    await page.click('button[type="submit"]');
    
    // 계층별 데이터 삭제 (역순)
    await deleteAllTestBranches(page);
    await deleteAllTestAgencies(page);
    await deleteAllTestDistributors(page);
    await deleteAllTestSubAdmins(page);
    
    console.log('테스트 데이터 정리 완료');
  } catch (error) {
    console.error('데이터 정리 실패:', error);
  } finally {
    await browser.close();
  }
}

cleanupAllTestData();
```

## 병렬 테스트 전략

### 1. 병렬 실행 가능한 테스트 그룹

#### 그룹 A: 독립적인 읽기 전용 테스트
- 로그인 테스트
- 메뉴 접근 권한 테스트
- 목록 조회 테스트
- 권한 없는 페이지 접근 차단 테스트

#### 그룹 B: 서로 다른 계층의 독립적 테스트
- 서로 다른 총판의 서브관리자 테스트
- 서로 다른 대리점의 서브관리자 테스트
- 서로 다른 지점의 서브관리자 테스트

### 2. 순차 실행 필요한 테스트 그룹

#### 그룹 X: 계층 구조 의존성이 있는 테스트
```
순차 실행 순서:
1. 총판 생성 테스트
2. 대리점 생성 테스트 (총판 필요)
3. 지점 생성 테스트 (대리점 필요)
4. 계층 구조 변경 테스트
5. 데이터 정리 테스트
```

### 3. 병렬 테스트 구성
```javascript
// playwright.config.ts
export default defineConfig({
  projects: [
    {
      name: 'auth-tests',
      testMatch: /auth\.spec\.ts/,
      fullyParallel: true,
    },
    {
      name: 'hierarchy-tests',
      testMatch: /hierarchy\.spec\.ts/,
      fullyParallel: false, // 순차 실행
    },
    {
      name: 'sub-admin-tests',
      testMatch: /sub-admin-.*\.spec\.ts/,
      fullyParallel: true,
    },
  ],
  
  // 전역 병렬 설정
  workers: process.env.CI ? 2 : 4,
  fullyParallel: false, // 기본값은 순차
});
```

### 4. 테스트 격리를 위한 데이터 네이밍
```javascript
// 병렬 실행 시 충돌 방지를 위한 고유 ID 생성
function generateTestId(prefix: string): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(2, 5);
  return `${prefix}_${timestamp}_${random}`;
}

// 사용 예
const distributorId = generateTestId('d_test'); // d_test_1703123456_abc
const agencyId = generateTestId('a_test');     // a_test_1703123457_def
```

## 테스트 실행 시나리오

### 시나리오 1: 개발 중 (시각적 확인 필요)
```bash
# 1. 헤드풀 모드로 단일 테스트 실행
npx playwright test headquarters.spec.ts --headed --workers=1

# 2. 문제 발생 시 디버그 모드
npx playwright test headquarters.spec.ts --debug

# 3. 데이터 정리 건너뛰기 (재실행을 위해)
SKIP_CLEANUP=true npx playwright test headquarters.spec.ts --headed
```

### 시나리오 2: 전체 테스트 실행
```bash
# 1. 헤드리스 모드로 전체 테스트
npm run test

# 2. 실패한 테스트만 재실행
npx playwright test --last-failed

# 3. 테스트 완료 후 데이터 정리
npm run test:cleanup
```

### 시나리오 3: CI/CD 파이프라인
```yaml
# .github/workflows/test.yml
jobs:
  test:
    steps:
      - name: Run tests
        run: |
          npm run test:no-cleanup
          
      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: playwright-report/
          
      - name: Cleanup test data
        if: always()
        run: npm run test:cleanup
```

## 성능 최적화

### 1. 브라우저 컨텍스트 재사용
```javascript
// 같은 권한 테스트는 컨텍스트 공유
test.describe('총판 관리자 테스트', () => {
  let context: BrowserContext;
  
  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    const page = await context.newPage();
    await loginAsDistributor(page);
  });
  
  test.afterAll(async () => {
    await context.close();
  });
  
  test('테스트 1', async () => {
    const page = await context.newPage();
    // 이미 로그인된 상태로 테스트
  });
});
```

### 2. 선택적 대기 전략
```javascript
// 느린 환경을 위한 대기 시간 조정
const waitOptions = {
  timeout: process.env.CI ? 30000 : 10000,
};

await page.waitForSelector('.distributor-list', waitOptions);
```

### 3. 리소스 최적화
```javascript
// 이미지/폰트 로딩 차단으로 속도 향상
await page.route('**/*.{png,jpg,jpeg,gif,webp,svg,woff,woff2}', 
  route => route.abort()
);
```

## 모니터링 및 리포팅

### 1. 실시간 진행 상황
```javascript
// 커스텀 리포터로 진행 상황 표시
class ProgressReporter {
  onTestBegin(test) {
    console.log(`🏃 시작: ${test.title}`);
  }
  
  onTestEnd(test, result) {
    const emoji = result.status === 'passed' ? '✅' : '❌';
    console.log(`${emoji} 완료: ${test.title} (${result.duration}ms)`);
  }
}
```

### 2. 테스트 결과 대시보드
```bash
# HTML 리포트 생성 및 열기
npx playwright show-report

# JSON 형식으로 결과 저장
npx playwright test --reporter=json > test-results.json
```

## 문제 해결 가이드

### 병렬 실행 시 충돌
- 고유 ID 생성 함수 사용
- 테스트 간 데이터 격리 확인
- 필요시 순차 실행으로 전환

### 헤드풀 모드 속도 문제
- `slowMo` 값 조정
- 특정 액션만 느리게 실행
- 스크린샷/비디오 기록 최소화

### 데이터 정리 실패
- 수동 정리 스크립트 실행
- 데이터베이스 직접 접근
- 정리 로그 확인 및 재시도