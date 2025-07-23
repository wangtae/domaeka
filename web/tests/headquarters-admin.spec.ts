import { test, expect } from '@playwright/test';

// 테스트 설정
const BASE_URL = process.env.BASE_URL || 'http://domaeka.local';
const ADMIN_ID = 'admin';
const ADMIN_PW = '!domaekaservice@.';

// 테스트 데이터
const testData = {
  distributor: {
    id: 'd_test_01',
    password: 'd_test_01',
    name: 'd_test_01',
    company: 'd_test_01총판',
    companyUpdated: 'd_test_01총판2',
    email: 'd_test_01@gmail.com',
    phone: '010-1234-5678',
    phoneUpdated: '010-1234-5679'
  },
  agency: {
    id: 'a_test_01',
    password: 'a_test_01',
    name: 'a_test_01',
    company: 'a_test_01대리점',
    companyUpdated: 'a_test_01대리점2',
    email: 'a_test_01@gmail.com',
    phone: '010-2345-6789',
    phoneUpdated: '010-2345-6780'
  },
  branch: {
    id: 'b_test_01',
    password: 'b_test_01',
    name: 'b_test_01',
    company: 'b_test_01지점',
    email: 'b_test_01@gmail.com',
    phone: '010-3456-7890'
  }
};

test.describe('본사 최고관리자 테스트', () => {
  // 각 테스트 전에 로그인
  test.beforeEach(async ({ page }) => {
    // 관리자 페이지 접속
    await page.goto(`${BASE_URL}/adm`);
    
    // 로그인
    await page.fill('#login_id', ADMIN_ID);
    await page.fill('#login_pw', ADMIN_PW);
    await page.click('button[type="submit"]');
    
    // 로그인 확인
    await page.waitForURL(/\/adm\/?$/);
    await expect(page.locator('.adm_menu_list')).toBeVisible();
  });

  test.describe('1. 총판 관리 테스트', () => {
    test('1.1 총판 목록 조회', async ({ page }) => {
      // 총판관리 메뉴 클릭
      await page.click('text=프랜차이즈 관리');
      await page.click('a:has-text("총판관리")');
      
      // 페이지 로드 확인
      await expect(page).toHaveURL(/distributor_list\.php/);
      await expect(page.locator('h1')).toContainText('총판 관리');
      
      // 테이블 존재 확인
      const table = page.locator('table.tbl_head01');
      await expect(table).toBeVisible();
      
      // 테이블 헤더 확인
      await expect(table.locator('th')).toContainText(['번호', 'ID', '총판명', '회사명', '연락처']);
    });

    test('1.2 총판 등록', async ({ page }) => {
      // 총판관리 페이지로 이동
      await page.goto(`${BASE_URL}/adm/shop_admin/distributor_list.php`);
      
      // 총판 등록 버튼 클릭
      await page.click('a:has-text("+ 총판 등록")');
      await expect(page).toHaveURL(/distributor_form\.php/);
      
      // 폼 입력
      await page.fill('input[name="distributor_id"]', testData.distributor.id);
      await page.fill('input[name="distributor_pw"]', testData.distributor.password);
      await page.fill('input[name="distributor_name"]', testData.distributor.name);
      await page.fill('input[name="company_name"]', testData.distributor.company);
      await page.fill('input[name="email"]', testData.distributor.email);
      await page.fill('input[name="phone"]', testData.distributor.phone);
      
      // 등록 버튼 클릭
      await page.click('button:has-text("총판 등록")');
      
      // 성공 확인
      await page.waitForURL(/distributor_list\.php/);
      
      // 등록된 총판 확인
      const newRow = page.locator(`tr:has-text("${testData.distributor.id}")`);
      await expect(newRow).toBeVisible();
      await expect(newRow).toContainText(testData.distributor.company);
      await expect(newRow).toContainText(testData.distributor.phone);
      
      // 관리자 액션 로그 확인 (API 호출로 확인)
      const logResponse = await page.evaluate(async (distributorId) => {
        const response = await fetch('/adm/ajax_get_admin_log.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'INSERT',
            target: distributorId,
            limit: 1
          })
        });
        return response.json();
      }, testData.distributor.id);
      
      expect(logResponse).toBeTruthy();
      expect(logResponse.action).toBe('INSERT');
    });

    test('1.3 총판 수정', async ({ page }) => {
      // 총판관리 페이지로 이동
      await page.goto(`${BASE_URL}/adm/shop_admin/distributor_list.php`);
      
      // 수정 버튼 클릭
      const editButton = page.locator(`tr:has-text("${testData.distributor.id}") a:has-text("수정")`);
      await editButton.click();
      
      // 수정 페이지 확인
      await expect(page).toHaveURL(/distributor_form\.php.*w=u/);
      
      // 정보 수정
      await page.fill('input[name="company_name"]', testData.distributor.companyUpdated);
      await page.fill('input[name="phone"]', testData.distributor.phoneUpdated);
      
      // 수정 버튼 클릭
      await page.click('button:has-text("총판 정보 수정")');
      
      // 성공 확인
      await page.waitForURL(/distributor_list\.php/);
      
      // 수정된 정보 확인
      const updatedRow = page.locator(`tr:has-text("${testData.distributor.id}")`);
      await expect(updatedRow).toContainText(testData.distributor.companyUpdated);
      await expect(updatedRow).toContainText(testData.distributor.phoneUpdated);
    });
  });

  test.describe('2. 대리점 관리 테스트', () => {
    test('2.1 대리점 목록 조회', async ({ page }) => {
      // 대리점관리 메뉴 클릭
      await page.click('text=프랜차이즈 관리');
      await page.click('a:has-text("대리점관리")');
      
      // 페이지 로드 확인
      await expect(page).toHaveURL(/agency_list\.php/);
      await expect(page.locator('h1')).toContainText('대리점 관리');
      
      // 총판 선택 박스 확인
      const distributorSelect = page.locator('select[name="distributor_id"]');
      await expect(distributorSelect).toBeVisible();
      
      // 대리점 선택 박스는 없어야 함
      const agencySelect = page.locator('select[name="agency_id"]');
      await expect(agencySelect).not.toBeVisible();
    });

    test('2.2 총판별 대리점 필터링', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/agency_list.php`);
      
      // 총판 선택
      await page.selectOption('select[name="distributor_id"]', testData.distributor.id);
      
      // 필터링 적용 (자동 또는 버튼 클릭)
      await page.waitForTimeout(500); // 필터링 적용 대기
      
      // 선택한 총판의 대리점만 표시되는지 확인
      const rows = page.locator('table.tbl_head01 tbody tr');
      const count = await rows.count();
      
      if (count > 0) {
        // 각 행이 올바른 총판 소속인지 확인
        for (let i = 0; i < count; i++) {
          const distributorCell = rows.nth(i).locator('td:nth-child(3)'); // 총판 컬럼
          await expect(distributorCell).toContainText(testData.distributor.id);
        }
      }
    });

    test('2.3 대리점 등록', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/agency_list.php`);
      
      // 대리점 등록 버튼 클릭
      await page.click('a:has-text("+ 대리점 등록")');
      await expect(page).toHaveURL(/agency_form\.php/);
      
      // 폼 입력
      await page.selectOption('select[name="distributor_id"]', testData.distributor.id);
      await page.fill('input[name="agency_id"]', testData.agency.id);
      await page.fill('input[name="agency_pw"]', testData.agency.password);
      await page.fill('input[name="agency_name"]', testData.agency.name);
      await page.fill('input[name="company_name"]', testData.agency.company);
      await page.fill('input[name="email"]', testData.agency.email);
      await page.fill('input[name="phone"]', testData.agency.phone);
      
      // 등록 버튼 클릭
      await page.click('button:has-text("확인")');
      
      // 성공 확인
      await page.waitForURL(/agency_list\.php/);
      
      // 등록된 대리점 확인
      const newRow = page.locator(`tr:has-text("${testData.agency.id}")`);
      await expect(newRow).toBeVisible();
      await expect(newRow).toContainText(testData.agency.company);
      await expect(newRow).toContainText(testData.distributor.id); // 소속 총판 확인
    });

    test('2.4 대리점 수정', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/agency_list.php`);
      
      // 수정 버튼 클릭
      const editButton = page.locator(`tr:has-text("${testData.agency.id}") a:has-text("수정")`);
      await editButton.click();
      
      // 수정 페이지 확인
      await expect(page).toHaveURL(/agency_form\.php.*w=u/);
      
      // 정보 수정
      await page.fill('input[name="company_name"]', testData.agency.companyUpdated);
      await page.fill('input[name="phone"]', testData.agency.phoneUpdated);
      
      // 수정 버튼 클릭
      await page.click('button:has-text("대리점 정보 수정")');
      
      // 성공 확인
      await page.waitForURL(/agency_list\.php/);
      
      // 수정된 정보 확인
      const updatedRow = page.locator(`tr:has-text("${testData.agency.id}")`);
      await expect(updatedRow).toContainText(testData.agency.companyUpdated);
      await expect(updatedRow).toContainText(testData.agency.phoneUpdated);
    });
  });

  test.describe('3. 지점 관리 테스트', () => {
    test('3.1 지점 목록 조회', async ({ page }) => {
      // 지점관리 메뉴 클릭
      await page.click('text=프랜차이즈 관리');
      await page.click('a:has-text("지점관리")');
      
      // 페이지 로드 확인
      await expect(page).toHaveURL(/branch_list\.php/);
      await expect(page.locator('h1')).toContainText('지점 관리');
      
      // Chained Select 확인
      const distributorSelect = page.locator('select[name="distributor_id"]');
      const agencySelect = page.locator('select[name="agency_id"]');
      
      await expect(distributorSelect).toBeVisible();
      await expect(agencySelect).toBeVisible();
      
      // 초기 상태에서 대리점 선택 박스는 비활성화
      await expect(agencySelect).toBeDisabled();
    });

    test('3.2 Chained Select 동작 테스트', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/branch_list.php`);
      
      // 총판 선택
      await page.selectOption('select[name="distributor_id"]', testData.distributor.id);
      
      // 대리점 선택 박스 활성화 확인
      const agencySelect = page.locator('select[name="agency_id"]');
      await expect(agencySelect).toBeEnabled();
      
      // 대리점 옵션 확인
      const agencyOptions = await agencySelect.locator('option').allTextContents();
      expect(agencyOptions).toContain(testData.agency.name);
      
      // 대리점 선택
      await page.selectOption('select[name="agency_id"]', testData.agency.id);
      
      // 필터링 적용 대기
      await page.waitForTimeout(500);
      
      // 선택한 대리점의 지점만 표시되는지 확인
      const rows = page.locator('table.tbl_head01 tbody tr');
      const count = await rows.count();
      
      if (count > 0) {
        for (let i = 0; i < count; i++) {
          const agencyCell = rows.nth(i).locator('td:nth-child(4)'); // 대리점 컬럼
          await expect(agencyCell).toContainText(testData.agency.id);
        }
      }
    });

    test('3.3 지점 등록', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/branch_list.php`);
      
      // 지점 등록 버튼 클릭
      await page.click('a:has-text("+ 지점 등록")');
      await expect(page).toHaveURL(/branch_form\.php/);
      
      // 폼 입력
      await page.selectOption('select[name="distributor_id"]', testData.distributor.id);
      
      // 대리점 선택 박스 활성화 대기
      await page.waitForFunction(
        () => {
          const select = document.querySelector('select[name="agency_id"]') as HTMLSelectElement;
          return select && !select.disabled;
        }
      );
      
      await page.selectOption('select[name="agency_id"]', testData.agency.id);
      await page.fill('input[name="branch_id"]', testData.branch.id);
      await page.fill('input[name="branch_pw"]', testData.branch.password);
      await page.fill('input[name="branch_name"]', testData.branch.name);
      await page.fill('input[name="company_name"]', testData.branch.company);
      await page.fill('input[name="email"]', testData.branch.email);
      await page.fill('input[name="phone"]', testData.branch.phone);
      
      // 등록 버튼 클릭
      await page.click('button:has-text("확인")');
      
      // 성공 확인
      await page.waitForURL(/branch_list\.php/);
      
      // 등록된 지점 확인
      const newRow = page.locator(`tr:has-text("${testData.branch.id}")`);
      await expect(newRow).toBeVisible();
      await expect(newRow).toContainText(testData.branch.company);
      await expect(newRow).toContainText(testData.distributor.id); // 소속 총판 확인
      await expect(newRow).toContainText(testData.agency.id); // 소속 대리점 확인
    });
  });

  test.describe('4. 권한 경계 테스트', () => {
    test('4.1 전체 접근 권한 확인', async ({ page }) => {
      // 본사 최고관리자는 모든 메뉴에 접근 가능
      const menus = [
        { text: '총판관리', url: /distributor_list\.php/ },
        { text: '대리점관리', url: /agency_list\.php/ },
        { text: '지점관리', url: /branch_list\.php/ },
        { text: '회원관리', url: /member_list\.php/ },
        { text: '상품관리', url: /itemlist\.php/ },
        { text: '주문관리', url: /orderlist\.php/ }
      ];
      
      for (const menu of menus) {
        await page.goto(`${BASE_URL}/adm`);
        await page.click(`text=${menu.text}`);
        await expect(page).toHaveURL(menu.url);
        
        // 페이지 접근 가능 확인 (에러 메시지 없음)
        await expect(page.locator('text=접근 권한이 없습니다')).not.toBeVisible();
      }
    });
  });

  test.describe('5. 데이터 일관성 테스트', () => {
    test('5.1 계층 구조 일관성', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/distributor_list.php`);
      
      // 하위 조직이 있는 총판 삭제 시도
      const deleteButton = page.locator(`tr:has-text("${testData.distributor.id}") a:has-text("삭제")`);
      
      if (await deleteButton.isVisible()) {
        await deleteButton.click();
        
        // 확인 대화상자 처리
        page.on('dialog', async dialog => {
          await dialog.accept();
        });
        
        // 오류 메시지 확인
        await expect(page.locator('.alert, .error')).toContainText(/하위.*존재/);
      }
    });

    test('5.2 중복 ID 검증', async ({ page }) => {
      await page.goto(`${BASE_URL}/adm/shop_admin/distributor_form.php`);
      
      // 이미 존재하는 ID로 등록 시도
      await page.fill('input[name="distributor_id"]', testData.distributor.id);
      await page.fill('input[name="distributor_pw"]', 'test1234');
      await page.fill('input[name="distributor_name"]', '중복테스트');
      await page.fill('input[name="company_name"]', '중복테스트');
      
      // 등록 버튼 클릭
      await page.click('button:has-text("총판 등록")');
      
      // 오류 메시지 확인
      await expect(page.locator('.alert, .error')).toContainText(/중복|이미.*존재/);
    });
  });
});

// 테스트 데이터 정리 (선택적 - 환경변수로 제어)
test.afterAll(async () => {
  if (process.env.SKIP_CLEANUP === 'true') {
    console.log('테스트 데이터 정리 건너뛰기 (SKIP_CLEANUP=true)');
    return;
  }
  
  // 자동 삭제는 당분간 사용하지 않음
  console.log('테스트 완료. 수동으로 데이터를 정리하거나 cleanup 스크립트를 실행하세요.');
});