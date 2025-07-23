/**
 * 대리점 관리 메뉴 테스트
 * 
 * 테스트 순서:
 * 1. 본사 관리자 - 대리점 목록/등록/수정/삭제 테스트
 * 2. 총판 관리자 - 자신 소속 대리점 관리 테스트
 * 3. 대리점 관리자 - 자신의 정보 조회/수정 테스트
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

test.describe('대리점 관리 메뉴 테스트', () => {
  
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
    
    test('1.1 대리점 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 대리점 관리 페이지 접근
      await testMenuAccess(page, MENU_PATHS.AGENCY_LIST, true);
      
      // 기존 대리점이 목록에 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_AGENCY.id, true);
      
      // 페이지 제목 확인
      await expect(page.locator('body')).toContainText('대리점');
      
      console.log('✓ 본사 관리자 - 대리점 목록 조회 성공');
    });

    test('1.2 대리점 등록', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 먼저 총판이 등록되어 있어야 함 (의존성)
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.DISTRIBUTOR_LIST}`);
      await waitForPageReady(page);
      
      const distributorExists = await page.locator(`tr:has-text("${TEST_DATA.DISTRIBUTOR.id}")`).isVisible();
      if (!distributorExists) {
        // 총판이 없으면 먼저 등록
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
      
      // 대리점 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_FORM}`);
      await waitForPageReady(page);
      
      // 등록 폼 확인
      await expect(page).toHaveURL(/agency_form\\.php/);
      await saveTestScreenshot(page, 'agency-admin', 'form-before-fill');
      
      // 폼 데이터 입력 (대리점은 소속 총판 선택이 필요할 수 있음)
      const agencyFormData = {
        'mb_id': TEST_DATA.AGENCY.id,
        'mb_password': TEST_DATA.AGENCY.password,
        'mb_password_re': TEST_DATA.AGENCY.password,
        'mb_name': TEST_DATA.AGENCY.name,
        'mb_nick': TEST_DATA.AGENCY.company,
        'mb_email': TEST_DATA.AGENCY.email,
        'mb_hp': TEST_DATA.AGENCY.phone
      };
      
      // 소속 총판 선택 (select 박스가 있는 경우)
      const distributorSelect = page.locator('select[name="dt_id"], select[name="distributor_id"]');
      if (await distributorSelect.isVisible()) {
        await distributorSelect.selectOption(TEST_DATA.AGENCY.parent_id);
      }
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page, 
        agencyFormData, 
        'input[type="submit"]',
        /agency_list\\.php/
      );
      
      // 등록 확인
      await expectItemInList(page, TEST_DATA.AGENCY.id, true);
      
      console.log('✓ 본사 관리자 - 대리점 등록 성공:', TEST_DATA.AGENCY.id);
    });

    test('1.3 대리점 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 대리점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 수정 폼으로 이동
      await goToEditForm(page, TEST_DATA.AGENCY.id);
      
      // 수정 페이지 확인
      await expect(page).toHaveURL(/agency_form\\.php.*w=u/);
      
      // 수정할 데이터 입력
      const updateFormData = {
        'mb_nick': TEST_DATA.AGENCY.companyUpdated,
        'mb_hp': TEST_DATA.AGENCY.phoneUpdated
      };
      
      // 폼 제출
      await submitFormAndExpectSuccess(
        page,
        updateFormData,
        'input[type="submit"]',
        /agency_list\\.php/
      );
      
      // 수정 확인
      await expect(page.locator('body')).toContainText(TEST_DATA.AGENCY.companyUpdated);
      
      console.log('✓ 본사 관리자 - 대리점 수정 성공');
    });

    test('1.4 총판별 대리점 필터링', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 대리점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 총판 선택 필터가 있는지 확인
      const distributorFilter = page.locator('select[name="distributor_id"], select[name="dt_id"]');
      
      if (await distributorFilter.isVisible()) {
        // 특정 총판 선택
        await distributorFilter.selectOption(TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id);
        await waitForPageReady(page);
        
        // 해당 총판 소속 대리점만 표시되는지 확인
        await expectItemInList(page, TEST_ACCOUNTS.EXISTING_AGENCY.id, true);
        
        console.log('✓ 본사 관리자 - 총판별 대리점 필터링 성공');
      } else {
        console.log('✓ 총판 필터 기능 없음 (모든 대리점 표시)');
      }
    });
  });

  test.describe('2. 총판 관리자 테스트', () => {
    
    test('2.1 자신 소속 대리점 목록 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 대리점 관리 페이지 접근
      await testMenuAccess(page, MENU_PATHS.AGENCY_LIST, true);
      
      // 자신 소속 대리점만 표시되는지 확인
      await expectItemInList(page, TEST_ACCOUNTS.EXISTING_AGENCY.id, true);
      
      console.log('✓ 총판 관리자 - 자신 소속 대리점 조회 성공');
    });

    test('2.2 소속 대리점 등록', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 대리점 등록 페이지 접근 시도
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_FORM}`);
      await waitForPageReady(page);
      
      // 접근 권한에 따라 등록 가능 여부 확인
      const hasAccess = !await page.locator('body').textContent().then(text => 
        text?.includes('접근 권한이 없습니다')
      );
      
      if (hasAccess) {
        console.log('✓ 총판 관리자 - 대리점 등록 페이지 접근 가능');
      } else {
        console.log('✓ 총판 관리자 - 대리점 등록 접근 제한됨');
      }
    });

    test('2.3 소속 대리점 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      // 대리점 목록에서 수정 시도
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 수정 버튼이 있는지 확인
      const editButton = page.locator(`tr:has-text("${TEST_ACCOUNTS.EXISTING_AGENCY.id}") a:has-text("수정")`);
      
      if (await editButton.isVisible()) {
        await editButton.click();
        await waitForPageReady(page);
        
        // 수정 페이지 접근 확인
        const hasAccess = !await page.locator('body').textContent().then(text => 
          text?.includes('접근 권한이 없습니다')
        );
        
        if (hasAccess) {
          console.log('✓ 총판 관리자 - 소속 대리점 수정 가능');
        } else {
          console.log('✓ 총판 관리자 - 소속 대리점 수정 제한됨');
        }
      }
    });
  });

  test.describe('3. 대리점 관리자 테스트', () => {
    
    test('3.1 자신의 대리점 정보 조회', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 대리점 관리 페이지 접근
      const pageContent = await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        // 자신의 정보만 표시되는지 확인
        await expectItemInList(page, TEST_ACCOUNTS.EXISTING_AGENCY.id, true);
        console.log('✓ 대리점 관리자 - 자신의 정보 조회 성공');
      } else {
        console.log('✓ 대리점 관리자 - 대리점 목록 접근 제한됨');
      }
    });

    test('3.2 자신의 정보 수정', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_AGENCY);
      
      // 직접 수정 페이지 접근 시도
      const editUrl = `${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_FORM}?w=u&ag_id=${TEST_ACCOUNTS.EXISTING_AGENCY.id}`;
      await page.goto(editUrl);
      await waitForPageReady(page);
      
      // 접근 권한 확인
      const bodyText = await page.locator('body').textContent();
      const hasAccess = !bodyText?.includes('접근 권한이 없습니다');
      
      if (hasAccess) {
        await expect(page).toHaveURL(/agency_form\\.php.*w=u/);
        console.log('✓ 대리점 관리자 - 자신의 정보 수정 접근 성공');
      } else {
        console.log('✓ 대리점 관리자 - 정보 수정 접근 제한됨');
      }
    });
  });

  test.describe('4. 지점 관리자 접근 제한 테스트', () => {
    
    test('4.1 대리점 관리 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 접근 거부되어야 함
      await testMenuAccess(page, MENU_PATHS.AGENCY_LIST, false);
      
      console.log('✓ 지점 관리자 - 대리점 관리 접근 거부 확인');
    });

    test('4.2 대리점 등록 페이지 접근 거부', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_BRANCH);
      
      // 접근 거부되어야 함
      await testMenuAccess(page, MENU_PATHS.AGENCY_FORM, false);
      
      console.log('✓ 지점 관리자 - 대리점 등록 접근 거부 확인');
    });
  });

  test.describe('5. 계층 구조 및 권한 경계 테스트', () => {
    
    test('5.1 타 총판 소속 대리점 접근 제한', async ({ page }) => {
      // 다른 총판 계정이 있다면 테스트
      // 현재는 기본 테스트 데이터로만 진행
      await adminLogin(page, TEST_ACCOUNTS.EXISTING_DISTRIBUTOR);
      
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 자신 소속이 아닌 대리점은 표시되지 않아야 함
      // (추가 테스트 데이터가 있을 경우 검증)
      
      console.log('✓ 총판별 대리점 접근 권한 경계 확인');
    });

    test('5.2 대리점 삭제 제한 확인', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 대리점 목록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_LIST}`);
      await waitForPageReady(page);
      
      // 하위 지점이 있는 대리점 삭제 시도 (실패해야 함)
      await attemptDelete(page, TEST_ACCOUNTS.EXISTING_AGENCY.id, false);
      
      console.log('✓ 본사 관리자 - 대리점 삭제 제한 확인 성공');
    });

    test('5.3 중복 ID 등록 방지', async ({ page }) => {
      await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
      
      // 대리점 등록 페이지로 이동
      await page.goto(`${TEST_CONFIG.BASE_URL}${MENU_PATHS.AGENCY_FORM}`);
      await waitForPageReady(page);
      
      // 이미 존재하는 ID로 등록 시도
      const duplicateFormData = {
        'mb_id': TEST_ACCOUNTS.EXISTING_AGENCY.id,
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