# 테스트 실행 전략 및 가이드

## 개요
도매까 시스템의 Playwright 테스트를 위한 종합적인 실행 전략과 가이드라인을 정의합니다. 이 문서는 단위 테스트 시나리오, 테스트 데이터 관리, 실행 모드, 병렬 처리 전략 등을 포함합니다.

## 1. 단위 테스트 시나리오

### 1.1 테스트 원칙
- 각 메뉴별로 독립적인 테스트 시나리오 문서를 작성하여 관리
- 메뉴별로 본사-총판-대리점-지점 관리자로 각각 로그인하여 테스트 진행
- 테스트 실패 시 전체를 중단하고 문제 해결 후 재진행
- MySQL MCP를 활용하여 DB 테이블 구조와 데이터 참조
- 서브 관리자 권한 체크는 메인 관리자 테스트 완료 후 진행

### 1.2 기본 테스트 데이터

#### 총판 (dmk_distributor)
- `domaeka`: 실제 운영 중인 총판 (테스트 사용 금지)
- `distributor`: 사전 생성된 테스트용 총판
- `d_test_01`: 테스트 진행 중 생성할 총판 ID

#### 대리점 (dmk_agency)
- `domaeka1`: 실제 운영 중인 대리점 (테스트 사용 금지)
- `agency001`: 사전 생성된 테스트용 대리점
- `a_test_01`: 테스트 진행 중 생성할 대리점 ID

#### 지점 (dmk_branch)
- `domaeka11`: 실제 운영 중인 지점 (테스트 사용 금지)
- `store001`: 사전 생성된 테스트용 지점
- `b_test_01`: 테스트 진행 중 생성할 지점 ID

### 1.3 테스트 데이터 입력 규칙

#### 관리자 등록 시 필드 입력 형식
- **비밀번호**: `!{아이디}$.`
- **이름**: `{아이디}`
- **회사명**: `{아이디}회사`
- **닉네임**: `{아이디}`
- **이메일**: `{아이디}@mail.com`

#### 관리자 정보 수정
- 회사명 수정만 테스트: `{아이디}회사` → `{아이디}수정`

### 1.4 서브관리자 권한 테스트
- 각 계층별 서브관리자는 테스트용으로 새로 생성
- 권한별 테스트 순서:
  1. 읽기 권한: 읽기 가능, 쓰기/삭제 불가능 확인
  2. 쓰기 권한: 읽기/쓰기 가능, 삭제 불가능 확인
  3. 삭제 권한: 모든 권한 가능 확인

## 2. 테스트 실행 모드

### 2.1 헤드풀 모드 (개발/디버깅용)
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

### 2.2 헤드리스 모드 (CI/CD용)
백그라운드에서 빠르게 실행

```bash
# 기본 헤드리스 모드
npx playwright test

# 병렬 실행 (기본값)
npx playwright test --workers=4

# 순차 실행
npx playwright test --workers=1
```

### 2.3 하이브리드 접근법
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

## 3. 테스트와 데이터 정리 분리

### 3.1 테스트 실행 단계
```bash
# 1단계: 테스트만 실행 (데이터 정리 없음)
npm run test:no-cleanup

# 2단계: 테스트 결과 확인
npm run test:report

# 3단계: 데이터 정리 (별도 실행)
npm run test:cleanup
```

### 3.2 package.json 스크립트 설정
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

### 3.3 조건부 정리 구현
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

### 3.4 독립적인 정리 스크립트
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

## 4. 병렬 테스트 전략

### 4.1 병렬 실행 가능한 테스트 그룹

#### 그룹 A: 독립적인 읽기 전용 테스트
- 로그인 테스트
- 메뉴 접근 권한 테스트
- 목록 조회 테스트
- 권한 없는 페이지 접근 차단 테스트

#### 그룹 B: 서로 다른 계층의 독립적 테스트
- 서로 다른 총판의 서브관리자 테스트
- 서로 다른 대리점의 서브관리자 테스트
- 서로 다른 지점의 서브관리자 테스트

### 4.2 순차 실행 필요한 테스트 그룹

#### 그룹 X: 계층 구조 의존성이 있는 테스트
```
순차 실행 순서:
1. 총판 생성 테스트
2. 대리점 생성 테스트 (총판 필요)
3. 지점 생성 테스트 (대리점 필요)
4. 계층 구조 변경 테스트
5. 데이터 정리 테스트
```

### 4.3 병렬 테스트 구성
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

### 4.4 테스트 격리를 위한 데이터 네이밍
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

## 5. 테스트 실행 시나리오

### 5.1 개발 중 (시각적 확인 필요)
```bash
# 1. 헤드풀 모드로 단일 테스트 실행
npx playwright test headquarters.spec.ts --headed --workers=1

# 2. 문제 발생 시 디버그 모드
npx playwright test headquarters.spec.ts --debug

# 3. 데이터 정리 건너뛰기 (재실행을 위해)
SKIP_CLEANUP=true npx playwright test headquarters.spec.ts --headed
```

### 5.2 전체 테스트 실행
```bash
# 1. 헤드리스 모드로 전체 테스트
npm run test

# 2. 실패한 테스트만 재실행
npx playwright test --last-failed

# 3. 테스트 완료 후 데이터 정리
npm run test:cleanup
```

### 5.3 CI/CD 파이프라인
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

## 6. 성능 최적화

### 6.1 브라우저 컨텍스트 재사용
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

### 6.2 선택적 대기 전략
```javascript
// 느린 환경을 위한 대기 시간 조정
const waitOptions = {
  timeout: process.env.CI ? 30000 : 10000,
};

await page.waitForSelector('.distributor-list', waitOptions);
```

### 6.3 리소스 최적화
```javascript
// 이미지/폰트 로딩 차단으로 속도 향상
await page.route('**/*.{png,jpg,jpeg,gif,webp,svg,woff,woff2}', 
  route => route.abort()
);
```

## 7. 모니터링 및 리포팅

### 7.1 실시간 진행 상황
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

### 7.2 테스트 결과 대시보드
```bash
# HTML 리포트 생성 및 열기
npx playwright show-report

# JSON 형식으로 결과 저장
npx playwright test --reporter=json > test-results.json
```

## 8. 문제 해결 가이드

### 8.1 병렬 실행 시 충돌
- 고유 ID 생성 함수 사용
- 테스트 간 데이터 격리 확인
- 필요시 순차 실행으로 전환

### 8.2 헤드풀 모드 속도 문제
- `slowMo` 값 조정
- 특정 액션만 느리게 실행
- 스크린샷/비디오 기록 최소화

### 8.3 데이터 정리 실패
- 수동 정리 스크립트 실행
- 데이터베이스 직접 접근
- 정리 로그 확인 및 재시도

## 9. 테스트 계획 문서 작성 가이드

각 메뉴별 테스트 계획 문서는 다음 구조를 따라야 합니다:

1. **메뉴 개요**: 테스트 대상 메뉴의 기능과 목적
2. **테스트 범위**: 테스트할 기능의 상세 목록
3. **계층별 테스트 시나리오**: 본사/총판/대리점/지점별 시나리오
4. **권한 테스트 매트릭스**: 읽기/쓰기/삭제 권한별 예상 동작
5. **테스트 데이터**: 필요한 사전 데이터 및 생성할 데이터
6. **검증 항목**: 각 테스트에서 확인할 사항
7. **예외 처리**: 오류 상황 및 엣지 케이스
8. **의존성**: 다른 테스트와의 관계
9. **정리 작업**: 테스트 후 필요한 데이터 정리