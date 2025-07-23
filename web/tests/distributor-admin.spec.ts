/**
 * 총판 관리 메뉴 테스트
 * 
 * 테스트 순서:
 * 1. 본사 관리자 - 총판 목록/등록/수정/삭제 테스트
 * 2. 총판 관리자 - 자신의 정보 조회/수정 테스트  
 * 3. 대리점 관리자 - 접근 거부 테스트
 * 4. 지점 관리자 - 접근 거부 테스트
 */

import { test, expect } from '@playwright/test';
import { 
  TEST_CONFIG, 
  TEST_ACCOUNTS, 
  TEST_DATA, 
  MENU_PATHS,
  ERROR_MESSAGES 
} from './utils/test-config';
import { 
  adminLogin, 
  adminLogout, 
  submitFormAndExpectSuccess,
  expectItemInList,
  goToEditForm,
  attemptDelete,
  testMenuAccess,
  waitForPageReady,
  saveTestScreenshot
} from './utils/test-helpers';
import { cleanupTestData, initializeTestEnvironment } from './utils/data-cleanup';

test.describe('총판 관리 메뉴 테스트', () => {
  
  // 테스트 환경 초기화
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await initializeTestEnvironment(page);
    await page.close();
  });
  
  // 각 테스트 전 로그아웃
  test.beforeEach(async ({ page }) => {
    await adminLogout(page);
  });
  
  // 테스트 완료 후 데이터 정리
  test.afterAll(async ({ browser }) => {
    if (process.env.SKIP_CLEANUP !== 'true') {
      const page = await browser.newPage();
      await cleanupTestData(page);
      await page.close();
    }
  });

  test.describe('1. 본사 관리자 테스트', () => {
    
    test('1.1 총판 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 총판 관리 페이지 접근
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_LIST, true);
      
      // 기존 총판이 목록에 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id, true);
      
      // 페이지 제목 확인
      await expect(page.locator('body')).toContainText('총판');
      
      console.log('✓ 본사 관리자 - 총판 목록 조회 성공');
    });

    test('1.2 총판 등록', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 총판 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_FORM}`);
      await waitForPageReady(page);
      
      // 등록 폼 확인
      await expect(page).toHaveURL(/distributor_form\\.php/);
      await saveTestScreenshot(page, 'distributor-admin', 'form-before-fill');
      
      // 폼 데이터 입력
      const distributorFormData = {
        'mb_id': TEST_DATA.DISTRIBUTOR.id,
        'mb_password': TEST_DATA.DISTRIBUTOR.password,
        'mb_password_re': TEST_DATA.DISTRIBUTOR.password,
        'mb_name': TEST_DATA.DISTRIBUTOR.name,
        'mb_nick': TEST_DATA.DISTRIBUTOR.company,
        'mb_email': TEST_DATA.DISTRIBUTOR.email,
        'mb_hp': TEST_DATA.DISTRIBUTOR.phone
      };
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page, 
        distributorFormData, 
        'input[type="submit"]',
        /distributor_list\\.php/
      );
      
      // 등록 확인
      await expectItemInList(page, TEST_DATA.DISTRIBUTOR.id, true);
      
      console.log('✓ 본사 관리자 - 총판 등록 성공:', TEST_DATA.DISTRIBUTOR.id);
    });

    test('1.3 총판 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 총판 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_LIST}`);
      await waitForPageReady(page);
      
      // 수정 폼으로 이동
      await goToEditForm(page, TEST_DATA.DISTRIBUTOR.id);
      
      // 수정 페이지 확인
      await expect(page).toHaveURL(/distributor_form\\.php.*w=u/);
      
      // 수정할 데이터 입력
      const updateFormData = {
        'mb_nick': TEST_DATA.DISTRIBUTOR.companyUpdated,
        'mb_hp': TEST_DATA.DISTRIBUTOR.phoneUpdated
      };
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page,
        updateFormData,
        'input[type="submit"]',
        /distributor_list\\.php/
      );
      
      // 수정 확인
      await expect(page.locator('body')).toContainText(TEST_DATA.DISTRIBUTOR.companyUpdated);
      
      console.log('✓ 본사 관리자 - 총판 수정 성공');
    });

    test('1.4 총판 삭제 제한 확인', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 총판 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_LIST}`);
      await waitForPageReady(page);
      
      // 하위 조직이 있는 총판 삭제 시도 (실패해야 함)
      await attemptDelete(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id, false);
      
      console.log('✓ 본사 관리자 - 총판 삭제 제한 확인 성공');
    });
  });

  test.describe('2. 총판 관리자 테스트', () => {
    
    test('2.1 자신의 총판 정보 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 총판 관리 페이지 접근 (자신만 표시되어야 함)
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_LIST, true);
      
      // 자신의 정보만 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id, true);
      
      console.log('✓ 총판 관리자 - 자신의 정보 조회 성공');
    });

    test('2.2 자신의 정보 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 총판 목록에서 자신의 수정 폼으로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_LIST}`);
      await waitForPageReady(page);
      
      await goToEditForm(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id);
      
      // 수정 페이지 접근 확인
      await expect(page).toHaveURL(/distributor_form\\.php.*w=u/);
      
      console.log('✓ 총판 관리자 - 자신의 정보 수정 접근 성공');
    });
  });

  test.describe('3. 대리점 관리자 접근 제한 테스트', () => {
    
    test('3.1 총판 관리 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 접근 거부되어야 함
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_LIST, false);
      
      console.log('✓ 대리점 관리자 - 총판 관리 접근 거부 확인');
    });

    test('3.2 총판 등록 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 접근 거부되어야 함
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_FORM, false);
      
      console.log('✓ 대리점 관리자 - 총판 등록 접근 거부 확인');
    });
  });

  test.describe('4. 지점 관리자 접근 제한 테스트', () => {
    
    test('4.1 총판 관리 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 접근 거부되어야 함
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_LIST, false);
      
      console.log('✓ 지점 관리자 - 총판 관리 접근 거부 확인');
    });

    test('4.2 총판 등록 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 접근 거부되어야 함  
      await testMenuAccess(page, MENU_PATHS.DISTRIBUTOR_FORM, false);
      
      console.log('✓ 지점 관리자 - 총판 등록 접근 거부 확인');
    });
  });

  test.describe('5. 데이터 무결성 테스트', () => {
    
    test('5.1 중복 ID 등록 방지', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 총판 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_FORM}`);
      await waitForPageReady(page);
      
      // 이미 존재하는 ID로 등록 시도
      const duplicateFormData = {
        'mb_id': TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id,
        'mb_password': 'test1234',
        'mb_password_re': 'test1234',
        'mb_name': '중복테스트',
        'mb_nick': '중복테스트회사'
      };
      
      // 폼 입력
      for (const [fieldId, value] of Object.entries(duplicateFormData)) {
        await page.fill(`#${fieldId}`, value);
      }
      
      // 제출 시도
      await page.click('input[type="submit"]');
      await waitForPageReady(page);
      
      // 오류 메시지 확인
      const bodyText = await page.locator('body').textContent();
      const hasError = bodyText?.includes('중복') || bodyText?.includes('이미') || bodyText?.includes('존재');
      expect(hasError).toBeTruthy();
      
      console.log('✓ 중복 ID 등록 방지 확인');
    });
  });
});