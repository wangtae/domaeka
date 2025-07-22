# 도메까 계층형 관리자 시스템 자동화 테스트 방법론

## 1. 개요 및 목표

### 1.1 개요
영카트5 기반의 도메까 관리자 시스템은 기존 단일 관리자 체계에서 **본사-총판-대리점-지점**의 4단계 계층형 관리자 시스템으로 개편되었습니다. 각 계층별로 서로 다른 권한, UI 표시, 데이터 접근 범위를 가지므로 체계적인 자동화 테스트가 필요합니다.

### 1.2 테스트 목표
- **권한 검증**: 각 계층별 관리자가 자신의 권한에 맞는 기능만 접근 가능한지 검증
- **데이터 격리**: 각 관리자가 자신의 권한 범위 내의 데이터만 조회 가능한지 확인
- **UI 일관성**: 권한에 따른 메뉴 표시, 버튼 활성화 상태 등 UI 요소의 정확성 검증
- **기능 동작**: 각 계층별 관리자의 핵심 업무 프로세스가 정상 작동하는지 확인
- **회귀 방지**: 코드 변경 시 기존 기능의 정상 작동 여부를 자동으로 검증

## 2. 테스트 대상 및 범위

### 2.1 계층별 관리자 유형
```
본사(super)
  └── 총판(distributor)
        └── 대리점(agency)
              └── 지점(branch)
```

### 2.2 주요 테스트 영역

#### 2.2.1 인증 및 권한
- 로그인/로그아웃
- 메뉴 접근 권한
- 기능 실행 권한
- 데이터 조회 권한

#### 2.2.2 계층별 핵심 기능
| 계층 | 핵심 기능 |
|------|-----------|
| 본사 | - 전체 시스템 설정<br>- 모든 계층 관리<br>- 전체 통계 조회<br>- 마스터 상품 관리 |
| 총판 | - 하위 대리점/지점 관리<br>- 총판 영역 통계<br>- 상품 배분 관리 |
| 대리점 | - 하위 지점 관리<br>- 대리점 영역 통계<br>- 상품 재고 관리 |
| 지점 | - 주문 관리<br>- 지점 상품 관리<br>- 고객 응대 |

#### 2.2.3 공통 테스트 항목
- 대시보드 표시 데이터
- 리스트 페이지 필터링
- 상세 페이지 접근
- CRUD 작업 권한
- 파일 업로드/다운로드

## 3. Playwright 기반 테스트 구현

### 3.1 테스트 환경 구성

```javascript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30000,
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['html', { outputFolder: 'test-results/html-report' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }]
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
  ],
});
```

### 3.2 테스트 데이터 관리

```typescript
// test-data/users.ts
export const testUsers = {
  super: {
    id: 'admin',
    password: process.env.SUPER_ADMIN_PASSWORD,
    name: '최고관리자',
    level: 10
  },
  distributor: {
    id: 'dist_seoul',
    password: process.env.DISTRIBUTOR_PASSWORD,
    name: '서울총판',
    level: 8
  },
  agency: {
    id: 'agency_gangnam',
    password: process.env.AGENCY_PASSWORD,
    name: '강남대리점',
    level: 6
  },
  branch: {
    id: 'branch_samsung',
    password: process.env.BRANCH_PASSWORD,
    name: '삼성지점',
    level: 4
  }
};
```

### 3.3 Page Object Model 구현

```typescript
// pages/LoginPage.ts
import { Page, Locator } from '@playwright/test';

export class LoginPage {
  readonly page: Page;
  readonly usernameInput: Locator;
  readonly passwordInput: Locator;
  readonly loginButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.usernameInput = page.locator('input[name="mb_id"]');
    this.passwordInput = page.locator('input[name="mb_password"]');
    this.loginButton = page.locator('button[type="submit"]');
  }

  async goto() {
    await this.page.goto('/bbs/login.php');
  }

  async login(username: string, password: string) {
    await this.usernameInput.fill(username);
    await this.passwordInput.fill(password);
    await this.loginButton.click();
    await this.page.waitForNavigation();
  }
}
```

## 4. 계층별 테스트 시나리오

### 4.1 권한 매트릭스 테스트

```typescript
// tests/permission-matrix.spec.ts
import { test, expect } from '@playwright/test';
import { testUsers } from '../test-data/users';
import { MenuPermissions } from '../test-data/permissions';

test.describe('계층별 메뉴 접근 권한 테스트', () => {
  for (const [userType, user] of Object.entries(testUsers)) {
    test(`${userType} 관리자 메뉴 권한 검증`, async ({ page }) => {
      // 로그인
      await loginAsUser(page, user);
      
      // 대시보드 이동
      await page.goto('/adm');
      
      // 권한별 메뉴 표시 검증
      const allowedMenus = MenuPermissions[userType];
      const deniedMenus = MenuPermissions.all.filter(
        menu => !allowedMenus.includes(menu)
      );
      
      // 허용된 메뉴 표시 확인
      for (const menu of allowedMenus) {
        await expect(page.locator(`[data-menu="${menu}"]`)).toBeVisible();
      }
      
      // 거부된 메뉴 숨김 확인
      for (const menu of deniedMenus) {
        await expect(page.locator(`[data-menu="${menu}"]`)).not.toBeVisible();
      }
    });
  }
});
```

### 4.2 데이터 격리 테스트

```typescript
// tests/data-isolation.spec.ts
test.describe('계층별 데이터 조회 권한', () => {
  test('지점 관리자는 자신의 지점 데이터만 조회 가능', async ({ page }) => {
    await loginAsUser(page, testUsers.branch);
    
    // 주문 목록 페이지 이동
    await page.goto('/adm/shop_admin/orderlist.php');
    
    // 주문 목록 로드 대기
    await page.waitForSelector('.tbl_head01');
    
    // 모든 주문의 지점명 확인
    const branchNames = await page.$$eval(
      'td[data-column="branch_name"]',
      cells => cells.map(cell => cell.textContent)
    );
    
    // 모든 주문이 자신의 지점 것인지 확인
    expect(branchNames.every(name => name === '삼성지점')).toBeTruthy();
  });
});
```

### 4.3 기능 동작 테스트

```typescript
// tests/branch-order-management.spec.ts
test.describe('지점 주문 관리 기능', () => {
  test('지점 관리자 주문 상태 변경', async ({ page }) => {
    await loginAsUser(page, testUsers.branch);
    
    // 주문 상세 페이지 이동
    await page.goto('/adm/shop_admin/orderform.php?od_id=2024112000001');
    
    // 주문 상태 변경
    await page.selectOption('select[name="od_status"]', '배송중');
    await page.click('button[type="submit"]');
    
    // 성공 메시지 확인
    await expect(page.locator('.alert-success')).toContainText('주문 상태가 변경되었습니다');
    
    // 변경된 상태 확인
    await page.reload();
    const status = await page.inputValue('select[name="od_status"]');
    expect(status).toBe('배송중');
  });
});
```

## 5. 테스트 자동화 실행

### 5.1 로컬 실행

```bash
# 전체 테스트 실행
npm test

# 특정 계층 테스트만 실행
npm test -- --grep "지점"

# 디버그 모드로 실행
npm test -- --debug

# 특정 브라우저로 실행
npm test -- --project=chromium
```

### 5.2 CI/CD 통합 (GitHub Actions)

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        user-type: [super, distributor, agency, branch]
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
    
    - name: Install dependencies
      run: npm ci
    
    - name: Install Playwright browsers
      run: npx playwright install --with-deps
    
    - name: Run tests for ${{ matrix.user-type }}
      run: npm test -- --grep "${{ matrix.user-type }}"
      env:
        BASE_URL: ${{ secrets.TEST_BASE_URL }}
        SUPER_ADMIN_PASSWORD: ${{ secrets.SUPER_ADMIN_PASSWORD }}
        DISTRIBUTOR_PASSWORD: ${{ secrets.DISTRIBUTOR_PASSWORD }}
        AGENCY_PASSWORD: ${{ secrets.AGENCY_PASSWORD }}
        BRANCH_PASSWORD: ${{ secrets.BRANCH_PASSWORD }}
    
    - name: Upload test results
      if: always()
      uses: actions/upload-artifact@v3
      with:
        name: test-results-${{ matrix.user-type }}
        path: test-results/
```

## 6. 테스트 모니터링 및 리포팅

### 6.1 테스트 결과 대시보드

```typescript
// scripts/generate-report.ts
import { readFileSync } from 'fs';
import { generateHTMLReport } from './report-generator';

const results = JSON.parse(readFileSync('test-results/results.json', 'utf-8'));

const summary = {
  total: results.tests.length,
  passed: results.tests.filter(t => t.status === 'passed').length,
  failed: results.tests.filter(t => t.status === 'failed').length,
  skipped: results.tests.filter(t => t.status === 'skipped').length,
  duration: results.duration,
  timestamp: new Date().toISOString()
};

generateHTMLReport(summary, results.tests);
```

### 6.2 실패 알림 설정

```javascript
// scripts/notify-failures.js
const { WebClient } = require('@slack/web-api');

async function notifyTestFailures(failures) {
  if (failures.length === 0) return;
  
  const slack = new WebClient(process.env.SLACK_TOKEN);
  
  await slack.chat.postMessage({
    channel: '#dev-alerts',
    text: `⚠️ E2E 테스트 실패: ${failures.length}건`,
    blocks: [
      {
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: failures.map(f => `• ${f.title}: ${f.error}`).join('\n')
        }
      }
    ]
  });
}
```

## 7. 베스트 프랙티스

### 7.1 테스트 작성 원칙
- **독립성**: 각 테스트는 다른 테스트에 의존하지 않고 독립적으로 실행 가능해야 함
- **재현성**: 동일한 조건에서 항상 같은 결과를 보장
- **명확성**: 테스트 이름과 assertion이 명확한 의도를 표현
- **유지보수성**: Page Object Model 사용으로 UI 변경에 대한 영향 최소화

### 7.2 테스트 데이터 관리
- 테스트용 계정은 별도로 생성하여 운영 데이터와 격리
- 테스트 실행 전 데이터 초기화 스크립트 실행
- 민감한 정보는 환경 변수로 관리

### 7.3 성능 최적화
- 병렬 실행을 통한 테스트 시간 단축
- 불필요한 대기 시간 제거
- 선택적 테스트 실행으로 개발 중 빠른 피드백

## 8. 트러블슈팅 가이드

### 8.1 일반적인 문제 해결

| 문제 | 원인 | 해결 방법 |
|------|------|-----------|
| 로그인 실패 | 테스트 계정 비활성화 | DB에서 계정 상태 확인 및 활성화 |
| 요소를 찾을 수 없음 | UI 변경 | Page Object 셀렉터 업데이트 |
| 타임아웃 | 서버 응답 지연 | 타임아웃 값 증가 또는 대기 조건 수정 |
| 권한 오류 | 테스트 데이터 불일치 | 권한 설정 초기화 스크립트 실행 |

### 8.2 디버깅 팁
```bash
# 헤드리스 모드 비활성화
PWDEBUG=1 npm test

# 특정 테스트만 디버그
npm test -- --grep "주문 상태 변경" --debug

# 스크린샷 및 비디오 확인
open test-results/
```

## 9. 향후 개선 사항

- **시각적 회귀 테스트**: Percy, Applitools 등 도입 검토
- **성능 테스트**: Lighthouse CI 통합
- **접근성 테스트**: Axe-core 통합
- **크로스 브라우저 테스트**: BrowserStack 연동
- **모바일 테스트**: 반응형 UI 테스트 추가

## 10. 참고 자료

- [Playwright 공식 문서](https://playwright.dev/)
- [영카트5 관리자 가이드](https://sir.kr/manual/g5/)
- [도메까 시스템 아키텍처 문서](../../../dmk/docs/)
- [테스트 자동화 모범 사례](https://testautomationu.applitools.com/)