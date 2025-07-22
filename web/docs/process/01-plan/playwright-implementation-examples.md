# Playwright 테스트 구현 예제

## 1. 프로젝트 구조

```
web/
├── tests/
│   ├── fixtures/
│   │   ├── auth.fixture.ts         # 인증 관련 픽스처
│   │   └── test-data.fixture.ts    # 테스트 데이터 픽스처
│   ├── pages/
│   │   ├── LoginPage.ts            # 로그인 페이지 객체
│   │   ├── DashboardPage.ts        # 대시보드 페이지 객체
│   │   ├── OrderListPage.ts        # 주문 목록 페이지 객체
│   │   └── ProductPage.ts          # 상품 관리 페이지 객체
│   ├── utils/
│   │   ├── db-helper.ts            # 데이터베이스 헬퍼
│   │   └── test-helpers.ts         # 공통 테스트 헬퍼
│   ├── headquarters.spec.ts        # 본사 관리자 테스트
│   ├── distributor.spec.ts         # 총판 관리자 테스트
│   ├── agency.spec.ts              # 대리점 관리자 테스트
│   └── branch.spec.ts              # 지점 관리자 테스트
├── playwright.config.ts            # Playwright 설정
├── package.json
└── .env.test                       # 테스트 환경 변수
```

## 2. 기본 설정

### 2.1 playwright.config.ts

```typescript
import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';

// 테스트 환경 변수 로드
dotenv.config({ path: '.env.test' });

export default defineConfig({
  testDir: './tests',
  
  // 테스트 실행 설정
  timeout: 30 * 1000,
  expect: {
    timeout: 5000
  },
  
  // 병렬 실행 설정
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  
  // 리포터 설정
  reporter: [
    ['html', { outputFolder: 'test-results/html-report', open: 'never' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['line'],
  ],
  
  // 전역 설정
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10 * 1000,
    navigationTimeout: 30 * 1000,
  },
  
  // 프로젝트별 설정
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'mobile',
      use: { ...devices['iPhone 12'] },
    },
  ],
  
  // 웹서버 설정 (로컬 개발 시)
  webServer: process.env.CI ? undefined : {
    command: 'php -S localhost:8000',
    port: 8000,
    reuseExistingServer: true,
  },
});
```

### 2.2 테스트 데이터 픽스처

```typescript
// tests/fixtures/test-data.fixture.ts
import { test as base } from '@playwright/test';

export type TestUser = {
  id: string;
  password: string;
  name: string;
  level: number;
  type: 'super' | 'distributor' | 'agency' | 'branch';
};

export type TestData = {
  users: {
    super: TestUser;
    distributor: TestUser;
    agency: TestUser;
    branch: TestUser;
  };
  products: Array<{
    name: string;
    price: number;
    stock: number;
  }>;
};

export const test = base.extend<{
  testData: TestData;
}>({
  testData: async ({}, use) => {
    const data: TestData = {
      users: {
        super: {
          id: 'admin',
          password: process.env.SUPER_PASSWORD || 'admin123',
          name: '최고관리자',
          level: 10,
          type: 'super'
        },
        distributor: {
          id: 'dist_seoul',
          password: process.env.DIST_PASSWORD || 'dist123',
          name: '서울총판',
          level: 8,
          type: 'distributor'
        },
        agency: {
          id: 'agency_gangnam',
          password: process.env.AGENCY_PASSWORD || 'agency123',
          name: '강남대리점',
          level: 6,
          type: 'agency'
        },
        branch: {
          id: 'branch_samsung',
          password: process.env.BRANCH_PASSWORD || 'branch123',
          name: '삼성지점',
          level: 4,
          type: 'branch'
        }
      },
      products: [
        { name: '테스트상품A', price: 10000, stock: 100 },
        { name: '테스트상품B', price: 20000, stock: 50 },
        { name: '테스트상품C', price: 30000, stock: 30 }
      ]
    };
    
    await use(data);
  },
});
```

## 3. Page Object Model 구현

### 3.1 로그인 페이지

```typescript
// tests/pages/LoginPage.ts
import { Page, Locator } from '@playwright/test';

export class LoginPage {
  readonly page: Page;
  readonly usernameInput: Locator;
  readonly passwordInput: Locator;
  readonly loginButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.usernameInput = page.locator('input[name="mb_id"]');
    this.passwordInput = page.locator('input[name="mb_password"]');
    this.loginButton = page.locator('button[type="submit"]:has-text("로그인")');
    this.errorMessage = page.locator('.alert-danger');
  }

  async goto() {
    await this.page.goto('/bbs/login.php');
    await this.page.waitForLoadState('networkidle');
  }

  async login(username: string, password: string) {
    await this.usernameInput.fill(username);
    await this.passwordInput.fill(password);
    await this.loginButton.click();
    
    // 로그인 후 리다이렉트 대기
    await this.page.waitForURL(url => !url.includes('/bbs/login.php'), {
      timeout: 5000
    });
  }

  async expectLoginError(message: string) {
    await this.errorMessage.waitFor({ state: 'visible' });
    await expect(this.errorMessage).toContainText(message);
  }
}
```

### 3.2 대시보드 페이지

```typescript
// tests/pages/DashboardPage.ts
import { Page, Locator, expect } from '@playwright/test';

export class DashboardPage {
  readonly page: Page;
  readonly userInfo: Locator;
  readonly menuItems: Locator;
  readonly logoutButton: Locator;
  readonly breadcrumb: Locator;

  constructor(page: Page) {
    this.page = page;
    this.userInfo = page.locator('.admin-user-info');
    this.menuItems = page.locator('#admin_menu .menu-item');
    this.logoutButton = page.locator('a:has-text("로그아웃")');
    this.breadcrumb = page.locator('.breadcrumb');
  }

  async goto() {
    await this.page.goto('/adm');
    await this.page.waitForLoadState('networkidle');
  }

  async expectUserLevel(level: string) {
    await expect(this.userInfo).toContainText(level);
  }

  async getVisibleMenus(): Promise<string[]> {
    const menus = await this.menuItems.allTextContents();
    return menus.filter(menu => menu.trim() !== '');
  }

  async clickMenu(menuText: string) {
    await this.page.locator(`#admin_menu >> text="${menuText}"`).click();
    await this.page.waitForLoadState('networkidle');
  }

  async logout() {
    await this.logoutButton.click();
    await this.page.waitForURL('**/bbs/login.php');
  }
}
```

### 3.3 주문 목록 페이지

```typescript
// tests/pages/OrderListPage.ts
import { Page, Locator, expect } from '@playwright/test';

export class OrderListPage {
  readonly page: Page;
  readonly searchForm: Locator;
  readonly orderTable: Locator;
  readonly filterSelect: Locator;
  readonly searchButton: Locator;
  readonly orderRows: Locator;

  constructor(page: Page) {
    this.page = page;
    this.searchForm = page.locator('#fsearch');
    this.orderTable = page.locator('.tbl_head01');
    this.filterSelect = page.locator('select[name="od_status"]');
    this.searchButton = page.locator('button:has-text("검색")');
    this.orderRows = page.locator('.tbl_head01 tbody tr');
  }

  async goto() {
    await this.page.goto('/adm/shop_admin/orderlist.php');
    await this.page.waitForLoadState('networkidle');
  }

  async filterByStatus(status: string) {
    await this.filterSelect.selectOption(status);
    await this.searchButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  async getOrderCount(): Promise<number> {
    // 데이터 없음 메시지 체크
    const noDataMessage = this.page.locator('td:has-text("자료가 없습니다")');
    if (await noDataMessage.isVisible()) {
      return 0;
    }
    return await this.orderRows.count();
  }

  async getOrderBranchNames(): Promise<string[]> {
    const branchCells = this.page.locator('td[data-column="branch_name"]');
    return await branchCells.allTextContents();
  }

  async clickOrderDetail(orderId: string) {
    await this.page.locator(`a:has-text("${orderId}")`).click();
    await this.page.waitForLoadState('networkidle');
  }
}
```

## 4. 계층별 테스트 구현

### 4.1 본사 관리자 테스트

```typescript
// tests/headquarters.spec.ts
import { test, expect } from './fixtures/test-data.fixture';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { OrderListPage } from './pages/OrderListPage';

test.describe('본사 관리자 테스트', () => {
  let loginPage: LoginPage;
  let dashboardPage: DashboardPage;
  let orderListPage: OrderListPage;

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
    dashboardPage = new DashboardPage(page);
    orderListPage = new OrderListPage(page);
  });

  test('본사 관리자 로그인 및 전체 메뉴 접근', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.super.id, testData.users.super.password);
    
    // 대시보드 확인
    await expect(page).toHaveURL(/\/adm\/?$/);
    await dashboardPage.expectUserLevel('최고관리자');
    
    // 전체 메뉴 표시 확인
    const visibleMenus = await dashboardPage.getVisibleMenus();
    expect(visibleMenus).toContain('환경설정');
    expect(visibleMenus).toContain('회원관리');
    expect(visibleMenus).toContain('상품관리');
    expect(visibleMenus).toContain('주문관리');
    expect(visibleMenus).toContain('계층관리');
  });

  test('본사 관리자 전체 주문 조회', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.super.id, testData.users.super.password);
    
    // 주문 목록 페이지로 이동
    await orderListPage.goto();
    
    // 전체 주문 조회 (필터 없음)
    const orderCount = await orderListPage.getOrderCount();
    console.log(`전체 주문 수: ${orderCount}`);
    
    // 다양한 지점의 주문이 표시되는지 확인
    const branchNames = await orderListPage.getOrderBranchNames();
    const uniqueBranches = [...new Set(branchNames)];
    expect(uniqueBranches.length).toBeGreaterThan(1);
  });

  test('본사 관리자 시스템 설정 접근', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.super.id, testData.users.super.password);
    
    // 환경설정 메뉴 클릭
    await dashboardPage.clickMenu('환경설정');
    
    // 기본환경설정 페이지 확인
    await expect(page).toHaveURL(/config_form\.php/);
    await expect(page.locator('h1')).toContainText('기본환경설정');
    
    // 설정 폼 필드 존재 확인
    await expect(page.locator('input[name="cf_title"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeEnabled();
  });
});
```

### 4.2 지점 관리자 테스트

```typescript
// tests/branch.spec.ts
import { test, expect } from './fixtures/test-data.fixture';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { OrderListPage } from './pages/OrderListPage';

test.describe('지점 관리자 테스트', () => {
  let loginPage: LoginPage;
  let dashboardPage: DashboardPage;
  let orderListPage: OrderListPage;

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
    dashboardPage = new DashboardPage(page);
    orderListPage = new OrderListPage(page);
  });

  test('지점 관리자 제한된 메뉴 접근', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.branch.id, testData.users.branch.password);
    
    // 대시보드 확인
    await expect(page).toHaveURL(/\/adm\/?$/);
    await dashboardPage.expectUserLevel('지점');
    
    // 제한된 메뉴만 표시되는지 확인
    const visibleMenus = await dashboardPage.getVisibleMenus();
    expect(visibleMenus).toContain('주문관리');
    expect(visibleMenus).toContain('상품관리');
    expect(visibleMenus).not.toContain('환경설정');
    expect(visibleMenus).not.toContain('회원관리');
    expect(visibleMenus).not.toContain('계층관리');
  });

  test('지점 관리자 자신의 주문만 조회', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.branch.id, testData.users.branch.password);
    
    // 주문 목록 페이지로 이동
    await orderListPage.goto();
    
    // 주문 목록의 모든 지점명 확인
    const branchNames = await orderListPage.getOrderBranchNames();
    
    // 모든 주문이 자신의 지점 것인지 확인
    const expectedBranchName = testData.users.branch.name;
    for (const branchName of branchNames) {
      expect(branchName).toBe(expectedBranchName);
    }
  });

  test('지점 관리자 상위 메뉴 접근 차단', async ({ page, testData }) => {
    // 로그인
    await loginPage.goto();
    await loginPage.login(testData.users.branch.id, testData.users.branch.password);
    
    // 직접 URL로 환경설정 페이지 접근 시도
    await page.goto('/adm/config_form.php');
    
    // 권한 없음 메시지 또는 리다이렉트 확인
    const currentUrl = page.url();
    const hasError = await page.locator('text=/권한이 없습니다|접근할 수 없습니다/').isVisible();
    
    expect(hasError || !currentUrl.includes('config_form.php')).toBeTruthy();
  });
});
```

### 4.3 권한 매트릭스 테스트

```typescript
// tests/permission-matrix.spec.ts
import { test, expect } from './fixtures/test-data.fixture';
import { LoginPage } from './pages/LoginPage';

// 권한별 접근 가능 URL 정의
const permissionMatrix = {
  super: {
    allowed: [
      '/adm/config_form.php',
      '/adm/member_list.php',
      '/adm/shop_admin/itemlist.php',
      '/adm/shop_admin/orderlist.php',
      '/adm/dmk/distributor_list.php'
    ],
    denied: []
  },
  distributor: {
    allowed: [
      '/adm/shop_admin/itemlist.php',
      '/adm/shop_admin/orderlist.php',
      '/adm/dmk/agency_list.php'
    ],
    denied: [
      '/adm/config_form.php',
      '/adm/member_list.php'
    ]
  },
  agency: {
    allowed: [
      '/adm/shop_admin/itemlist.php',
      '/adm/shop_admin/orderlist.php',
      '/adm/dmk/branch_list.php'
    ],
    denied: [
      '/adm/config_form.php',
      '/adm/dmk/distributor_list.php'
    ]
  },
  branch: {
    allowed: [
      '/adm/shop_admin/orderlist.php',
      '/adm/shop_admin/itemlist.php'
    ],
    denied: [
      '/adm/config_form.php',
      '/adm/member_list.php',
      '/adm/dmk/agency_list.php'
    ]
  }
};

test.describe('권한 매트릭스 검증', () => {
  let loginPage: LoginPage;

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
  });

  for (const [userType, permissions] of Object.entries(permissionMatrix)) {
    test(`${userType} 권한 매트릭스 검증`, async ({ page, testData }) => {
      // 해당 유저로 로그인
      const user = testData.users[userType as keyof typeof testData.users];
      await loginPage.goto();
      await loginPage.login(user.id, user.password);
      
      // 허용된 URL 접근 테스트
      for (const url of permissions.allowed) {
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        
        // 정상 페이지 로드 확인 (에러 메시지 없음)
        const hasError = await page.locator('text=/권한이 없습니다|접근할 수 없습니다/').isVisible();
        expect(hasError).toBeFalsy();
      }
      
      // 거부된 URL 접근 테스트
      for (const url of permissions.denied) {
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        
        // 권한 오류 또는 리다이렉트 확인
        const currentUrl = page.url();
        const hasError = await page.locator('text=/권한이 없습니다|접근할 수 없습니다/').isVisible();
        
        expect(hasError || !currentUrl.includes(url)).toBeTruthy();
      }
    });
  }
});
```

## 5. 유틸리티 함수

### 5.1 데이터베이스 헬퍼

```typescript
// tests/utils/db-helper.ts
import mysql from 'mysql2/promise';

export class DatabaseHelper {
  private connection: mysql.Connection | null = null;

  async connect() {
    this.connection = await mysql.createConnection({
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'domaeka'
    });
  }

  async disconnect() {
    if (this.connection) {
      await this.connection.end();
    }
  }

  async setupTestData() {
    if (!this.connection) throw new Error('Database not connected');
    
    // 테스트 데이터 초기화
    await this.connection.execute(`
      INSERT IGNORE INTO g5_member (mb_id, mb_password, mb_name, mb_level, dmk_type) VALUES
      ('test_branch', PASSWORD('branch123'), '테스트지점', 4, 'branch'),
      ('test_agency', PASSWORD('agency123'), '테스트대리점', 6, 'agency')
    `);
    
    // 계층 관계 설정
    await this.connection.execute(`
      INSERT IGNORE INTO g5_dmk_branches (mb_id, agency_id, name) VALUES
      ('test_branch', 'test_agency', '테스트지점')
    `);
  }

  async cleanupTestData() {
    if (!this.connection) throw new Error('Database not connected');
    
    // 테스트 데이터 정리
    await this.connection.execute(`
      DELETE FROM g5_shop_order WHERE od_branch_id LIKE 'test_%'
    `);
    await this.connection.execute(`
      DELETE FROM g5_dmk_branches WHERE mb_id LIKE 'test_%'
    `);
    await this.connection.execute(`
      DELETE FROM g5_member WHERE mb_id LIKE 'test_%'
    `);
  }

  async createTestOrder(branchId: string, status: string = '주문') {
    if (!this.connection) throw new Error('Database not connected');
    
    const orderId = `TEST${Date.now()}`;
    await this.connection.execute(`
      INSERT INTO g5_shop_order (
        od_id, od_branch_id, od_status, od_time, od_receipt_price
      ) VALUES (?, ?, ?, NOW(), 10000)
    `, [orderId, branchId, status]);
    
    return orderId;
  }
}
```

### 5.2 공통 테스트 헬퍼

```typescript
// tests/utils/test-helpers.ts
import { Page, expect } from '@playwright/test';

export async function waitForToast(page: Page, message: string) {
  const toast = page.locator('.toast-message', { hasText: message });
  await toast.waitFor({ state: 'visible', timeout: 5000 });
  await expect(toast).toBeVisible();
}

export async function dismissModal(page: Page) {
  const modal = page.locator('.modal.show');
  if (await modal.isVisible()) {
    await page.locator('.modal .btn-close').click();
    await modal.waitFor({ state: 'hidden' });
  }
}

export async function takeScreenshotOnFailure(page: Page, testName: string) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  await page.screenshot({
    path: `test-results/screenshots/${testName}-${timestamp}.png`,
    fullPage: true
  });
}

export async function checkAccessibility(page: Page) {
  // axe-core를 사용한 접근성 검사
  await page.addScriptTag({
    url: 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.7.2/axe.min.js'
  });
  
  const violations = await page.evaluate(() => {
    return new Promise((resolve) => {
      // @ts-ignore
      axe.run((err, results) => {
        if (err) throw err;
        resolve(results.violations);
      });
    });
  });
  
  expect(violations).toHaveLength(0);
}
```

## 6. 테스트 실행 스크립트

### 6.1 package.json

```json
{
  "name": "domaeka-e2e-tests",
  "version": "1.0.0",
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "PWDEBUG=1 playwright test",
    "test:branch": "playwright test branch.spec.ts",
    "test:agency": "playwright test agency.spec.ts",
    "test:distributor": "playwright test distributor.spec.ts",
    "test:headquarters": "playwright test headquarters.spec.ts",
    "test:ci": "playwright test --reporter=github",
    "report": "playwright show-report",
    "codegen": "playwright codegen",
    "trace": "playwright show-trace"
  },
  "devDependencies": {
    "@playwright/test": "^1.40.0",
    "@types/node": "^20.0.0",
    "dotenv": "^16.0.0",
    "mysql2": "^3.0.0",
    "typescript": "^5.0.0"
  }
}
```

### 6.2 테스트 실행 명령어

```bash
# 전체 테스트 실행
npm test

# 특정 계층 테스트만 실행
npm run test:branch

# 디버그 모드로 실행
npm run test:debug

# 헤드리스 모드 비활성화
npm run test:headed

# CI 환경에서 실행
npm run test:ci

# 테스트 리포트 보기
npm run report

# 코드 생성기 실행 (테스트 작성 도우미)
npm run codegen http://localhost/adm

# 실패한 테스트의 trace 보기
npm run trace trace.zip
```

## 7. CI/CD 통합 예제

### 7.1 GitHub Actions

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]
  schedule:
    - cron: '0 2 * * *'  # 매일 새벽 2시 실행

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: domaeka_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        extensions: mbstring, mysql, gd, zip
    
    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'
    
    - name: Install PHP dependencies
      run: |
        composer install --no-progress --prefer-dist
        
    - name: Setup test database
      run: |
        mysql -h 127.0.0.1 -u root -proot domaeka_test < database/schema.sql
        mysql -h 127.0.0.1 -u root -proot domaeka_test < dmk/sql/001_create_dmk_tables.sql
        mysql -h 127.0.0.1 -u root -proot domaeka_test < tests/fixtures/test-data.sql
        
    - name: Start PHP server
      run: |
        php -S localhost:8000 &
        sleep 5
        
    - name: Install Playwright
      run: |
        npm ci
        npx playwright install --with-deps
        
    - name: Run E2E tests
      run: npm run test:ci
      env:
        BASE_URL: http://localhost:8000
        DB_HOST: 127.0.0.1
        DB_USER: root
        DB_PASSWORD: root
        DB_NAME: domaeka_test
        
    - name: Upload test results
      if: always()
      uses: actions/upload-artifact@v3
      with:
        name: test-results
        path: test-results/
        retention-days: 30
        
    - name: Upload coverage reports
      if: always()
      uses: codecov/codecov-action@v3
      with:
        file: ./test-results/coverage.xml
```

## 8. 트러블슈팅 가이드

### 8.1 일반적인 문제와 해결 방법

| 문제 | 원인 | 해결 방법 |
|------|------|-----------|
| `Timeout exceeded` 오류 | 페이지 로딩 지연 | `waitForLoadState('networkidle')` 추가 또는 타임아웃 값 증가 |
| `Element not found` 오류 | DOM 변경 또는 동적 로딩 | 명시적 대기 추가: `await locator.waitFor()` |
| 로그인 실패 | 쿠키/세션 충돌 | `context.clearCookies()` 추가 |
| 간헐적 테스트 실패 | 네트워크 불안정 | 재시도 로직 추가 및 `retries` 설정 |

### 8.2 디버깅 팁

```typescript
// 디버깅용 헬퍼 함수
async function debugTest(page: Page) {
  // 현재 페이지 상태 출력
  console.log('Current URL:', page.url());
  console.log('Page title:', await page.title());
  
  // 스크린샷 저장
  await page.screenshot({ path: 'debug-screenshot.png' });
  
  // HTML 저장
  const html = await page.content();
  require('fs').writeFileSync('debug-page.html', html);
  
  // 콘솔 로그 캡처
  page.on('console', msg => console.log('Browser log:', msg.text()));
  
  // 네트워크 요청 모니터링
  page.on('request', request => {
    console.log('Request:', request.method(), request.url());
  });
}
```

이 문서는 도메까 계층형 관리자 시스템의 Playwright 테스트 구현을 위한 실제 코드 예제와 가이드를 제공합니다.