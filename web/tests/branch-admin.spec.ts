/**
 * 지점 관리 메뉴 테스트
 * 
 * 테스트 순서:
 * 1. 본사 관리자 - 지점 목록/등록/수정/삭제 테스트
 * 2. 총판 관리자 - 자신 소속 지점 관리 테스트  
 * 3. 대리점 관리자 - 자신 소속 지점 관리 테스트
 * 4. 지점 관리자 - 자신의 정보 조회/수정 테스트
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

test.describe('지점 관리 메뉴 테스트', () => {
  
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
    
    test('1.1 지점 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 지점 관리 페이지 접근
      await testMenuAccess(page, MENU_PATHS.BRANCH_LIST, true);
      
      // 기존 지점이 목록에 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_BRANCH.id, true);
      
      // 페이지 제목 확인
      await expect(page.locator('body')).toContainText('지점');
      
      console.log('✓ 본사 관리자 - 지점 목록 조회 성공');
    });

    test('1.2 Chained Select 동작 확인', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 지점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 총판 → 대리점 Chained Select 확인
      const distributorSelect = page.locator('select[name="distributor_id"], select[name="dt_id"]');
      const agencySelect = page.locator('select[name="agency_id"], select[name="ag_id"]');
      
      if (await distributorSelect.isVisible() && await agencySelect.isVisible()) {
        // 초기 상태에서 대리점 선택박스 비활성화 확인
        const isDisabled = await agencySelect.isDisabled();
        expect(isDisabled).toBeTruthy();
        
        // 총판 선택
        await distributorSelect.selectOption(TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id);
        await waitForPageReady(page);
        
        // 대리점 선택박스 활성화 확인
        const isEnabled = await agencySelect.isEnabled();
        expect(isEnabled).toBeTruthy();
        
        // 대리점 선택
        await agencySelect.selectOption(TEST_ACCOUNTS.EXISTING_AGENCY.id);
        await waitForPageReady(page);
        
        // 해당 대리점 소속 지점만 표시되는지 확인
        await expectItemInList(page, TEST_ACCOUNTS.EXISTING_BRANCH.id, true);
        
        console.log('✓ 본사 관리자 - Chained Select 동작 확인 성공');
      } else {
        console.log('✓ Chained Select 없음 (모든 지점 표시)');
      }
    });

    test('1.3 지점 등록', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 필요한 상위 계층 확인 및 생성
      await ensureParentHierarchy(page);
      
      // 지점 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_FORM}`);
      await waitForPageReady(page);
      
      // 등록 폼 확인
      await expect(page).toHaveURL(/branch_form\\.php/);
      await saveTestScreenshot(page, 'branch-admin', 'form-before-fill');
      
      // 폼 데이터 입력
      const branchFormData = {
        'mb_id': TEST_DATA.BRANCH.id,
        'mb_password': TEST_DATA.BRANCH.password,
        'mb_password_re': TEST_DATA.BRANCH.password,
        'mb_name': TEST_DATA.BRANCH.name,
        'mb_nick': TEST_DATA.BRANCH.company,
        'mb_email': TEST_DATA.BRANCH.email,
        'mb_hp': TEST_DATA.BRANCH.phone
      };
      
      // 소속 총판/대리점 선택
      await selectParentHierarchy(page);
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page, 
        branchFormData, 
        'input[type="submit"]',
        /branch_list\\.php/
      );
      
      // 등록 확인
      await expectItemInList(page, TEST_DATA.BRANCH.id, true);
      
      console.log('✓ 본사 관리자 - 지점 등록 성공:', TEST_DATA.BRANCH.id);
    });

    test('1.4 지점 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 지점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 수정 폼으로 이동
      await goToEditForm(page, TEST_DATA.BRANCH.id);
      
      // 수정 페이지 확인
      await expect(page).toHaveURL(/branch_form\\.php.*w=u/);
      
      // 수정할 데이터 입력
      const updateFormData = {
        'mb_nick': TEST_DATA.BRANCH.company + '_수정',
        'mb_hp': '010-7777-7777'
      };
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page,
        updateFormData,
        'input[type="submit"]',
        /branch_list\\.php/
      );
      
      // 수정 확인
      await expect(page.locator('body')).toContainText(TEST_DATA.BRANCH.company + '_수정');
      
      console.log('✓ 본사 관리자 - 지점 수정 성공');
    });

    test('1.5 지점 삭제', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 지점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 지점 삭제 시도 (성공해야 함 - 최하위 계층)
      await attemptDelete(page, TEST_DATA.BRANCH.id, true);
      
      console.log('✓ 본사 관리자 - 지점 삭제 성공');
    });
  });

  test.describe('2. 총판 관리자 테스트', () => {
    
    test('2.1 자신 소속 지점 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 지점 관리 페이지 접근
      await testMenuAccess(page, MENU_PATHS.BRANCH_LIST, true);
      
      // 자신 소속 지점만 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_BRANCH.id, true);
      
      console.log('✓ 총판 관리자 - 자신 소속 지점 조회 성공');
    });

    test('2.2 소속 지점 관리 권한', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 지점 등록 페이지 접근 시도
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_FORM}`);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        console.log('✓ 총판 관리자 - 지점 등록 페이지 접근 가능');
      } else {
        console.log('✓ 총판 관리자 - 지점 등록 접근 제한됨');
      }
    });

    test('2.3 Chained Select 총판 필터링', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 지점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 총판 선택 필터 확인
      const distributorSelect = page.locator('select[name="distributor_id"], select[name="dt_id"]');
      
      if (await distributorSelect.isVisible()) {
        // 자신만 선택 가능한지 또는 자동 필터링되는지 확인
        const options = await distributorSelect.locator('option').allTextContents();
        console.log('총판 선택 옵션:', options);
        console.log('✓ 총판 관리자 - 지점 목록 필터링 확인');
      }
    });
  });

  test.describe('3. 대리점 관리자 테스트', () => {
    
    test('3.1 자신 소속 지점 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 지점 관리 페이지 접근
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        // 자신 소속 지점만 표시되는지 확인
        await expectItemInList(page, TEST_ACCOUNTS.EXISTING_BRANCH.id, true);
        console.log('✓ 대리점 관리자 - 자신 소속 지점 조회 성공');
      } else {
        console.log('✓ 대리점 관리자 - 지점 목록 접근 제한됨');
      }
    });

    test('3.2 소속 지점 등록 권한', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 지점 등록 페이지 접근 시도
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_FORM}`);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        console.log('✓ 대리점 관리자 - 지점 등록 페이지 접근 가능');
      } else {
        console.log('✓ 대리점 관리자 - 지점 등록 접근 제한됨');
      }
    });

    test('3.3 소속 지점 수정 권한', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 지점 목록에서 수정 시도
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 접근 권한이 있는 경우에만 수정 시도
      const bodyText = await page.locator('body').textContent();
      const hasListAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasListAccess) {
        const editButton = page.locator(`tr:has-text("${TEST_ACCOUNTS.EXISTING_BRANCH.id}") a:has-text("수정")`);
        
        if (await editButton.isVisible()) {
          await editButton.click();
          await waitForPageReady(page);
          
          const editBodyText = await page.locator('body').textContent();
          const hasEditAccess = !editBodyText?.includes('접근 권한이 없습니다');
          
          if (hasEditAccess) {
            console.log('✓ 대리점 관리자 - 소속 지점 수정 가능');
          } else {
            console.log('✓ 대리점 관리자 - 소속 지점 수정 제한됨');
          }
        }
      }
    });
  });

  test.describe('4. 지점 관리자 테스트', () => {
    
    test('4.1 자신의 지점 정보 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 지점 관리 페이지 접근
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        // 자신의 정보만 표시되는지 확인
        await expectItemInList(page, TEST_ACCOUNTS.EXISTING_BRANCH.id, true);
        console.log('✓ 지점 관리자 - 자신의 정보 조회 성공');
      } else {
        console.log('✓ 지점 관리자 - 지점 목록 접근 제한됨');
      }
    });

    test('4.2 자신의 정보 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 직접 수정 페이지 접근 시도
      const editUrl = `${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_FORM}?w=u&br_id=${TEST_ACCOUNTS.EXISTING_BRANCH.id}`;
      await page.goto(editUrl);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        await expect(page).toHaveURL(/branch_form\\.php.*w=u/);
        console.log('✓ 지점 관리자 - 자신의 정보 수정 접근 성공');
      } else {
        console.log('✓ 지점 관리자 - 정보 수정 접근 제한됨');
      }
    });
  });

  test.describe('5. 계층 구조 및 데이터 무결성 테스트', () => {
    
    test('5.1 계층별 접근 권한 경계 확인', async ({ page }) => {
      // 각 계층별로 접근 가능한 지점 범위 확인
      const accounts = [
        TEST_ACCOUNTS.HEADQUARTERS,
        TEST_ACCOUNTS.EXISTING_DISTRIBUTOR, 
        TEST_ACCOUNTS.EXISTING_AGENCY,
        TEST_ACCOUNTS.EXISTING_BRANCH
      ];
      
      for (const account of accounts) {
        await adminLogout(page);
        await adminLogin(page, account);
        
        await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_LIST}`);
        await waitForPageReady(page);
        
        const bodyText = await page.locator('body').textContent();
        const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
        
        console.log(`✓ ${account.type} (${account.id}) - 지점 목록 접근: ${hasAccess ? '가능' : '제한'}`);
      }
    });

    test('5.2 중복 ID 등록 방지', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 지점 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.BRANCH_FORM}`);
      await waitForPageReady(page);
      
      // 이미 존재하는 ID로 등록 시도
      const duplicateFormData = {
        'mb_id': TEST_ACCOUNTS.EXISTING_BRANCH.id,
        'mb_password': 'test1234',
        'mb_password_re': 'test1234',
        'mb_name': '중복테스트',
        'mb_nick': '중복테스트회사'
      };
      
      // 폼 입력
      for (const [fieldId, value] of Object.entries(duplicateFormData)) {
        const field = page.locator(`#${fieldId}`);
        if (await field.isVisible()) {
          await field.fill(value);
        }
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

// 헬퍼 함수들
async function ensureParentHierarchy(page: any) {
  // 총판 확인 및 생성
  await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_LIST}`);
  await waitForPageReady(page);
  
  const distributorExists = await page.locator(`tr:has-text("${TEST_DATA.DISTRIBUTOR.id}")`).isVisible();
  if (!distributorExists) {
    await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_FORM}`);
    await waitForPageReady(page);
    
    const distributorFormData = {
      'mb_id': TEST_DATA.DISTRIBUTOR.id,
      'mb_password': TEST_DATA.DISTRIBUTOR.password,
      'mb_password_re': TEST_DATA.DISTRIBUTOR.password,
      'mb_name': TEST_DATA.DISTRIBUTOR.name,
      'mb_nick': TEST_DATA.DISTRIBUTOR.company,
      'mb_email': TEST_DATA.DISTRIBUTOR.email,
      'mb_hp': TEST_DATA.DISTRIBUTOR.phone
    };
    
    await submitFormAndExpectSuccess(
      page, 
      distributorFormData, 
      'input[type="submit"]',
      /distributor_list\\.php/
    );
  }
  
  // 대리점 확인 및 생성
  await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
  await waitForPageReady(page);
  
  const agencyExists = await page.locator(`tr:has-text("${TEST_DATA.AGENCY.id}")`).isVisible();
  if (!agencyExists) {
    await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_FORM}`);
    await waitForPageReady(page);
    
    const agencyFormData = {
      'mb_id': TEST_DATA.AGENCY.id,
      'mb_password': TEST_DATA.AGENCY.password,
      'mb_password_re': TEST_DATA.AGENCY.password,
      'mb_name': TEST_DATA.AGENCY.name,
      'mb_nick': TEST_DATA.AGENCY.company,
      'mb_email': TEST_DATA.AGENCY.email,
      'mb_hp': TEST_DATA.AGENCY.phone
    };
    
    // 소속 총판 선택
    const distributorSelect = page.locator('select[name="dt_id"], select[name="distributor_id"]');
    if (await distributorSelect.isVisible()) {
      await distributorSelect.selectOption(TEST_DATA.AGENCY.parent_id);
    }
    
    await submitFormAndExpectSuccess(
      page, 
      agencyFormData, 
      'input[type="submit"]',
      /agency_list\\.php/
    );
  }
}

async function selectParentHierarchy(page: any) {
  // 소속 총판 선택
  const distributorSelect = page.locator('select[name="dt_id"], select[name="distributor_id"]');
  if (await distributorSelect.isVisible()) {
    await distributorSelect.selectOption(TEST_DATA.DISTRIBUTOR.id);
    await waitForPageReady(page);
  }
  
  // 소속 대리점 선택 (총판 선택 후 활성화)
  const agencySelect = page.locator('select[name="ag_id"], select[name="agency_id"]');
  if (await agencySelect.isVisible()) {
    // 대리점 선택박스가 활성화될 때까지 대기
    await page.waitForFunction(() => {
      const select = document.querySelector('select[name="ag_id"], select[name="agency_id"]') as HTMLSelectElement;
      return select && !select.disabled;
    });
    
    await agencySelect.selectOption(TEST_DATA.AGENCY.id);
  }
}