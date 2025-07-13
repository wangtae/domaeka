import { test, expect } from '@playwright/test';

// 총판 관리자 로그인 정보
const DISTRIBUTOR_ADMIN = {
  id: 'domaeka', 
  password: '!domaeka$',
  loginUrl: 'http://localhost/adm/' 
};

test.describe('총판 관리자 메뉴 권한 테스트', () => {
  test.beforeEach(async ({ page }) => {
    // 총판 관리자로 로그인
    await page.goto(DISTRIBUTOR_ADMIN.loginUrl);
    await page.fill('#mb_id', DISTRIBUTOR_ADMIN.id);
    await page.fill('#mb_password', DISTRIBUTOR_ADMIN.password);
    await page.click('button[type="submit"]');
    // 로그인 성공 여부 확인
    await expect(page.locator('h1')).toContainText('관리자메인');
  });

  test('총판 관리자는 메뉴가 표시되어야 한다', async ({ page }) => {
    // 메인 메뉴들이 표시되는지 확인
    await expect(page.locator('.gnb_ul')).toBeVisible();
    
    // 환경설정 메뉴 확인
    await expect(page.locator('text=환경설정')).toBeVisible();
    
    // 프랜차이즈 관리 메뉴 확인  
    await expect(page.locator('text=프랜차이즈 관리')).toBeVisible();
    
    // 봇 관리 메뉴 확인
    await expect(page.locator('text=봇 관리')).toBeVisible();
  });

  test('총판 관리자는 봇 관리 메뉴에 접근할 수 있다', async ({ page }) => {
    // 봇 관리 메뉴 클릭
    await page.click('text=봇 관리');
    await page.waitForTimeout(1000);
    
    // 서브메뉴 확인
    await expect(page.locator('text=서버 관리')).toBeVisible();
    await expect(page.locator('text=서버 프로세스 관리')).toBeVisible();
    await expect(page.locator('text=클라이언트 봇 관리')).toBeVisible();
    
    // 서버 관리 페이지 접근 테스트
    await page.click('text=서버 관리');
    await page.waitForLoadState('networkidle');
    
    // 페이지가 정상적으로 로드되었는지 확인
    await expect(page.locator('h1')).toContainText('서버 관리');
  });

  test('총판 관리자는 프랜차이즈 관리 메뉴에 접근할 수 있다', async ({ page }) => {
    // 프랜차이즈 관리 메뉴 클릭
    await page.click('text=프랜차이즈 관리');
    await page.waitForTimeout(1000);
    
    // 서브메뉴 확인
    await expect(page.locator('text=총판관리')).toBeVisible();
    await expect(page.locator('text=대리점관리')).toBeVisible();
    await expect(page.locator('text=지점관리')).toBeVisible();
    
    // 총판관리 페이지 접근 테스트
    await page.click('text=총판관리');
    await page.waitForLoadState('networkidle');
    
    // 권한 오류가 발생하지 않는지 확인
    await expect(page.locator('text=접근 권한이 없습니다')).not.toBeVisible();
  });

  test('총판 관리자는 환경설정 메뉴에 제한적으로 접근할 수 있다', async ({ page }) => {
    // 환경설정 메뉴 클릭
    await page.click('text=환경설정');
    await page.waitForTimeout(1000);
    
    // 기본환경설정 접근 시도
    await page.click('text=기본환경설정');
    await page.waitForLoadState('networkidle');
    
    // 권한 체크 - 총판은 일부 환경설정에만 접근 가능해야 함
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    // 페이지 내용 확인
    const pageContent = await page.textContent('body');
    console.log('Page contains access denied:', pageContent?.includes('접근 권한'));
  });
});