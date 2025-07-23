/**
 * 테스트 공통 유틸리티 함수
 */

import { Page, expect } from '@playwright/test';
import { TEST_CONFIG, TEST_ACCOUNTS } from './test-config';

/**
 * 관리자 로그인 함수
 */
export async function adminLogin(page: Page, account: { id: string; password: string }) {
  // 관리자 페이지 접속
  await page.goto(`${TEST_CONFIG.BASE_URL}/adm`);
  
  // 로그인 페이지 로드 대기
  await page.waitForLoadState('networkidle');
  
  // 로그인 폼 입력
  const loginIdInput = page.locator('input[placeholder="관리자 아이디"]');
  const loginPwInput = page.locator('input[placeholder="비밀번호"]');
  const submitButton = page.locator('button:has-text("로그인")');
  
  await loginIdInput.waitFor({ state: 'visible', timeout: TEST_CONFIG.TIMEOUT.DEFAULT });
  await loginPwInput.waitFor({ state: 'visible', timeout: TEST_CONFIG.TIMEOUT.DEFAULT });
  
  await loginIdInput.fill(account.id);
  await loginPwInput.fill(account.password);
  await submitButton.click();
  
  // 로그인 확인
  await page.waitForURL(/\/adm/, { timeout: TEST_CONFIG.TIMEOUT.PAGE_LOAD });
  await page.waitForLoadState('networkidle');
}

/**
 * 로그아웃 함수
 */
export async function adminLogout(page: Page) {
  try {
    // 로그아웃 링크나 버튼 찾아서 클릭
    const logoutLink = page.locator('a[href*="logout"], button:has-text("로그아웃")');
    if (await logoutLink.isVisible()) {
      await logoutLink.click();
      await page.waitForURL(/\/adm.*login/, { timeout: TEST_CONFIG.TIMEOUT.DEFAULT });
    }
  } catch (error) {
    // 로그아웃이 실패하면 직접 로그인 페이지로 이동
    await page.goto(`${TEST_CONFIG.BASE_URL}/adm`);
  }
}

/**
 * 접근 권한 거부 메시지 확인
 */
export async function expectAccessDenied(page: Page, errorMessage = '접근 권한이 없습니다') {
  // 페이지 로드 대기
  await page.waitForLoadState('networkidle');
  
  // 에러 메시지나 접근 거부 확인
  const bodyText = await page.locator('body').textContent();
  expect(bodyText).toContain(errorMessage);
}

/**
 * 폼 제출 및 성공 확인
 */
export async function submitFormAndExpectSuccess(
  page: Page, 
  formData: Record<string, string>,
  submitButtonSelector: string = 'input[type="submit"]',
  successUrlPattern?: RegExp
) {
  // 폼 필드 입력
  for (const [fieldId, value] of Object.entries(formData)) {
    const field = page.locator(`#${fieldId}`);
    await field.waitFor({ state: 'visible' });
    await field.fill(value);
  }
  
  // 폼 제출
  await page.click(submitButtonSelector);
  
  // 성공 확인 (URL 패턴이 제공된 경우)
  if (successUrlPattern) {
    await page.waitForURL(successUrlPattern, { timeout: TEST_CONFIG.TIMEOUT.FORM_SUBMIT });
  }
  
  await page.waitForLoadState('networkidle');
}

/**
 * 목록에서 특정 항목 존재 확인
 */
export async function expectItemInList(page: Page, itemId: string, shouldExist = true) {
  await page.waitForLoadState('networkidle');
  const itemLocator = page.locator(`tr:has-text("${itemId}")`);
  
  if (shouldExist) {
    await expect(itemLocator).toBeVisible();
  } else {
    await expect(itemLocator).not.toBeVisible();
  }
}

/**
 * 수정 폼으로 이동
 */
export async function goToEditForm(page: Page, itemId: string) {
  const editButton = page.locator(`tr:has-text("${itemId}") a:has-text("수정")`);
  await expect(editButton).toBeVisible();
  await editButton.click();
  await page.waitForLoadState('networkidle');
}

/**
 * 삭제 시도 및 결과 확인
 */
export async function attemptDelete(page: Page, itemId: string, shouldSucceed = true) {
  const deleteButton = page.locator(`tr:has-text("${itemId}") a:has-text("삭제")`);
  
  if (await deleteButton.isVisible()) {
    // 삭제 확인 대화상자 처리 준비
    page.on('dialog', async dialog => {
      await dialog.accept();
    });
    
    await deleteButton.click();
    await page.waitForLoadState('networkidle');
    
    if (shouldSucceed) {
      // 삭제 성공 시 목록에서 해당 항목이 사라져야 함
      await expectItemInList(page, itemId, false);
    } else {
      // 삭제 실패 시 에러 메시지나 항목이 여전히 존재해야 함
      const hasError = await page.locator('.alert, .error').isVisible();
      const itemExists = await page.locator(`tr:has-text("${itemId}")`).isVisible();
      expect(hasError || itemExists).toBeTruthy();
    }
  }
}

/**
 * 테스트용 스크린샷 저장
 */
export async function saveTestScreenshot(page: Page, testName: string, step: string) {
  const filename = `test-results/${testName}-${step}-${Date.now()}.png`;
  await page.screenshot({ path: filename, fullPage: true });
  console.log(`스크린샷 저장: ${filename}`);
}

/**
 * 계층별 계정으로 순차 테스트 실행
 */
export async function testWithDifferentAccounts(
  page: Page,
  testFunction: (page: Page, account: any) => Promise<void>,
  accounts: any[]
) {
  for (const account of accounts) {
    console.log(`${account.type} 계정(${account.id})으로 테스트 시작`);
    
    // 로그아웃 후 해당 계정으로 로그인
    await adminLogout(page);
    await adminLogin(page, account);
    
    // 테스트 실행
    await testFunction(page, account);
    
    console.log(`${account.type} 계정(${account.id}) 테스트 완료`);
  }
}

/**
 * 페이지 로드 상태 확인
 */
export async function waitForPageReady(page: Page) {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500); // 추가 안정화 시간
}

/**
 * 메뉴 접근 테스트
 */
export async function testMenuAccess(page: Page, menuPath: string, shouldAccess = true) {
  await page.goto(`${TEST_CONFIG.BASE_URL}${menuPath}`);
  await waitForPageReady(page);
  
  if (shouldAccess) {
    // 접근 성공 시 URL 확인
    expect(page.url()).toContain(menuPath);
    // 접근 거부 메시지가 없어야 함
    const accessDeniedText = await page.locator('body').textContent();
    expect(accessDeniedText).not.toContain('접근 권한이 없습니다');
  } else {
    // 접근 실패 시 에러 메시지 확인
    await expectAccessDenied(page);
  }
}