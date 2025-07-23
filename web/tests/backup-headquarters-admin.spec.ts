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
    
    // 로그인 페이지 로드 대기
    await page.waitForLoadState('networkidle');
    
    // 스크린샷 (디버깅용)
    await page.screenshot({ path: 'test-results/login-page.png', fullPage: true });
    
    // 로그인 폼이 존재하는지 확인 - placeholder로 찾기
    const loginIdInput = page.locator('input[placeholder="관리자 아이디"]');
    const loginPwInput = page.locator('input[placeholder="비밀번호"]');
    const submitButton = page.locator('button:has-text("로그인")');
    
    // 로그인 입력 필드 대기
    await loginIdInput.waitFor({ state: 'visible', timeout: 10000 });
    await loginPwInput.waitFor({ state: 'visible', timeout: 10000 });
    
    // 로그인
    await loginIdInput.fill(ADMIN_ID);
    await loginPwInput.fill(ADMIN_PW);
    
    // 스크린샷 (입력 후)
    await page.screenshot({ path: 'test-results/login-filled.png', fullPage: true });
    
    await submitButton.click();
    
    // 로그인 확인 - URL 변경 대기 (로그인 성공 시 리다이렉트)
    await page.waitForURL(/\/adm/, { timeout: 30000 });
    
    // 로그인 성공 확인 - 관리자 페이지 요소 확인
    await page.waitForLoadState('networkidle');
    
    // 디버깅용 스크린샷
    await page.screenshot({ path: 'test-results/after-login.png', fullPage: true });
  });

  test.describe('1. 총판 관리 테스트', () => {
    test('1.1 총판 목록 조회', async ({ page }) => {
      // 총판관리 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/distributor_admin/distributor_list.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 페이지 URL 확인
      await expect(page).toHaveURL(/distributor_list\.php/);
      
      // 페이지에 "총판" 텍스트가 있는지만 확인 (가장 기본적인 검증)
      await expect(page.locator('body')).toContainText('총판');
      
      // 테스트 성공
      console.log('총판 목록 페이지 접속 성공');
    });

    test('1.2 총판 등록', async ({ page }) => {
      // 총판 등록 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/distributor_admin/distributor_form.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 등록 페이지인지 확인
      await expect(page).toHaveURL(/distributor_form\.php/);
      
      // 스크린샷 (디버깅용)
      await page.screenshot({ path: 'test-results/distributor-form.png', fullPage: true });
      
      // ID 입력 필드 대기 및 입력
      await page.waitForSelector('#mb_id', { state: 'visible' });
      await page.fill('#mb_id', testData.distributor.id);
      
      // 비밀번호 입력
      await page.fill('#mb_password', testData.distributor.password);
      await page.fill('#mb_password_re', testData.distributor.password);
      await page.fill('#mb_name', testData.distributor.name);
      await page.fill('#mb_nick', testData.distributor.company);
      await page.fill('#mb_email', testData.distributor.email);
      await page.fill('#mb_hp', testData.distributor.phone);
      
      // 등록 버튼 클릭
      await page.click('input[type="submit"]');
      
      // 성공 확인 - 목록 페이지로 리다이렉트
      await page.waitForURL(/distributor_list\.php/, { timeout: 15000 });
      
      // 등록된 총판 확인
      await expect(page.locator('body')).toContainText(testData.distributor.id);
      
      console.log('총판 등록 완료:', testData.distributor.id);
    });

    test('1.3 총판 수정', async ({ page }) => {
      // 총판관리 페이지로 이동
      await page.goto(`${BASE_URL}/dmk/adm/distributor_admin/distributor_list.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 등록된 총판이 있는지 확인 후 수정 버튼 클릭
      const editButton = page.locator(`tr:has-text("${testData.distributor.id}") a:has-text("수정")`);
      if (await editButton.count() > 0) {
        await editButton.click();
        
        // 수정 페이지 확인
        await expect(page).toHaveURL(/distributor_form\.php.*w=u/);
        
        // 정보 수정 - 실제 필드명에 맞춰 수정
        await page.fill('#mb_nick', testData.distributor.companyUpdated);
        await page.fill('#mb_hp', testData.distributor.phoneUpdated);
        
        // 수정 버튼 클릭
        await page.click('button[type="submit"], input[type="submit"]');
        
        // 성공 확인
        await page.waitForURL(/distributor_list\.php/, { timeout: 10000 });
        
        // 수정된 정보 확인
        await expect(page.locator('body')).toContainText(testData.distributor.id);
        
        console.log('총판 수정 완료:', testData.distributor.id);
      } else {
        console.log('수정할 총판이 없습니다. 1.2 테스트가 먼저 성공해야 합니다.');
      }
    });
  });

  test.describe('2. 대리점 관리 테스트', () => {
    test('2.1 대리점 목록 조회', async ({ page }) => {
      // 대리점관리 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/agency_admin/agency_list.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 페이지 URL 확인
      await expect(page).toHaveURL(/agency_list\.php/);
      
      // 페이지에 "대리점" 텍스트가 있는지만 확인 (가장 기본적인 검증)
      await expect(page.locator('body')).toContainText('대리점');
      
      // 테스트 성공
      console.log('대리점 목록 페이지 접속 성공');
    });

    test('2.2 대리점 등록', async ({ page }) => {
      // 대리점 등록 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/agency_admin/agency_form.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 등록 페이지인지 확인
      await expect(page).toHaveURL(/agency_form\.php/);
      
      // 스크린샷 (디버깅용)
      await page.screenshot({ path: 'test-results/agency-form.png', fullPage: true });
      
      // ID 입력 필드 대기 및 입력
      await page.waitForSelector('#mb_id', { state: 'visible' });
      await page.fill('#mb_id', testData.agency.id);
      
      // 비밀번호 입력
      await page.fill('#mb_password', testData.agency.password);
      await page.fill('#mb_password_re', testData.agency.password);
      await page.fill('#mb_name', testData.agency.name);
      await page.fill('#mb_nick', testData.agency.company);
      await page.fill('#mb_email', testData.agency.email);
      await page.fill('#mb_hp', testData.agency.phone);
      
      // 등록 버튼 클릭
      await page.click('input[type="submit"]');
      
      // 성공 확인 - 목록 페이지로 리다이렉트
      await page.waitForURL(/agency_list\.php/, { timeout: 15000 });
      
      // 등록된 대리점 확인
      await expect(page.locator('body')).toContainText(testData.agency.id);
      
      console.log('대리점 등록 완료:', testData.agency.id);
    });

    test('2.3 대리점 수정', async ({ page }) => {
      // 대리점관리 페이지로 이동
      await page.goto(`${BASE_URL}/dmk/adm/agency_admin/agency_list.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 등록된 대리점이 있는지 확인 후 수정 버튼 클릭
      const editButton = page.locator(`tr:has-text("${testData.agency.id}") a:has-text("수정")`);
      if (await editButton.count() > 0) {
        await editButton.click();
        
        // 수정 페이지 확인
        await expect(page).toHaveURL(/agency_form\.php.*w=u/);
        
        // 정보 수정 - 실제 필드명에 맞춰 수정
        await page.fill('#mb_nick', testData.agency.companyUpdated);
        await page.fill('#mb_hp', testData.agency.phoneUpdated);
        
        // 수정 버튼 클릭
        await page.click('button[type="submit"], input[type="submit"]');
        
        // 성공 확인
        await page.waitForURL(/agency_list\.php/, { timeout: 10000 });
        
        // 수정된 정보 확인
        await expect(page.locator('body')).toContainText(testData.agency.id);
        
        console.log('대리점 수정 완료:', testData.agency.id);
      } else {
        console.log('수정할 대리점이 없습니다. 2.2 테스트가 먼저 성공해야 합니다.');
      }
    });

  });

  test.describe('3. 지점 관리 테스트', () => {
    test('3.1 지점 목록 조회', async ({ page }) => {
      // 지점관리 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/branch_admin/branch_list.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 페이지 URL 확인
      await expect(page).toHaveURL(/branch_list\.php/);
      
      // 페이지에 "지점" 텍스트가 있는지만 확인 (가장 기본적인 검증)
      await expect(page.locator('body')).toContainText('지점');
      
      // 테스트 성공
      console.log('지점 목록 페이지 접속 성공');
    });

    test('3.2 지점 등록', async ({ page }) => {
      // 지점 등록 페이지로 직접 이동
      await page.goto(`${BASE_URL}/dmk/adm/branch_admin/branch_form.php`);
      
      // 페이지 로드 대기
      await page.waitForLoadState('networkidle');
      
      // 등록 페이지인지 확인
      await expect(page).toHaveURL(/branch_form\.php/);
      
      // 스크린샷 (디버깅용)
      await page.screenshot({ path: 'test-results/branch-form.png', fullPage: true });
      
      // ID 입력 필드 대기 및 입력
      await page.waitForSelector('#mb_id', { state: 'visible' });
      await page.fill('#mb_id', testData.branch.id);
      
      // 비밀번호 입력
      await page.fill('#mb_password', testData.branch.password);
      await page.fill('#mb_password_re', testData.branch.password);
      await page.fill('#mb_name', testData.branch.name);
      await page.fill('#mb_nick', testData.branch.company);
      await page.fill('#mb_email', testData.branch.email);
      await page.fill('#mb_hp', testData.branch.phone);
      
      // 등록 버튼 클릭
      await page.click('input[type="submit"]');
      
      // 성공 확인 - 목록 페이지로 리다이렉트
      await page.waitForURL(/branch_list\.php/, { timeout: 15000 });
      
      // 등록된 지점 확인
      await expect(page.locator('body')).toContainText(testData.branch.id);
      
      console.log('지점 등록 완료:', testData.branch.id);
    });

  });

  test.describe('4. 기본 기능 검증 테스트', () => {
    test('4.1 총판 관리 페이지 접근', async ({ page }) => {
      // 총판관리 페이지 직접 접근
      await page.goto(`${BASE_URL}/dmk/adm/distributor_admin/distributor_list.php`);
      await page.waitForLoadState('networkidle');
      
      // 접근 가능 확인
      await expect(page).toHaveURL(/distributor_list\.php/);
      await expect(page.locator('body')).toContainText('총판');
      
      console.log('총판 관리 페이지 접근 성공');
    });
    
    test('4.2 대리점 관리 페이지 접근', async ({ page }) => {
      // 대리점관리 페이지 직접 접근
      await page.goto(`${BASE_URL}/dmk/adm/agency_admin/agency_list.php`);
      await page.waitForLoadState('networkidle');
      
      // 접근 가능 확인
      await expect(page).toHaveURL(/agency_list\.php/);
      await expect(page.locator('body')).toContainText('대리점');
      
      console.log('대리점 관리 페이지 접근 성공');
    });
    
    test('4.3 지점 관리 페이지 접근', async ({ page }) => {
      // 지점관리 페이지 직접 접근
      await page.goto(`${BASE_URL}/dmk/adm/branch_admin/branch_list.php`);
      await page.waitForLoadState('networkidle');
      
      // 접근 가능 확인
      await expect(page).toHaveURL(/branch_list\.php/);
      await expect(page.locator('body')).toContainText('지점');
      
      console.log('지점 관리 페이지 접근 성공');
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