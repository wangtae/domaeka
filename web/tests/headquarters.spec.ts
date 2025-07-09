import { test, expect } from '@playwright/test';

// 본사 관리자 로그인 정보 (실제 정보로 대체 필요)
const HEADQUARTERS_ADMIN = {
  id: 'admin',
  password: '!domaekaservice@.',
  loginUrl: 'http://localhost:8001' // 실제 관리자 로그인 URL로 대체
};

test.describe('Headquarters Admin Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // 본사 관리자로 로그인
    await page.goto(HEADQUARTERS_ADMIN.loginUrl);
    await page.fill('#mb_id', HEADQUARTERS_ADMIN.id);
    await page.fill('#mb_password', HEADQUARTERS_ADMIN.password);
    await page.click('button[type="submit"]');
    // 로그인 성공 여부 확인 (예: 대시보드 텍스트 확인)
    await expect(page.getByRole('heading', { name: '관리자메인' })).toContainText('관리자메인');
  });

  test('본사 관리자는 총판을 등록하고 수정할 수 있다', async ({ page }) => {
    await test.step('총판 목록 페이지로 이동', async () => {
      await page.goto('http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php'); // 실제 URL로 대체
      await expect(page.locator('#container_title')).toContainText('총판 관리');
    });

    await test.step('새로운 총판 등록 폼으로 이동', async () => {
      await page.click('text=총판 등록');
      await expect(page.locator('#container_title')).toContainText('총판 등록/수정');
    });

    const newDistributorId = `DT_TEST_${Date.now()}`;
    const newDistributorName = `테스트총판_${Date.now()}`;
    const newDistributorNick = `테스트회사_${Date.now()}`;

    await test.step('새로운 총판 정보 입력 및 등록', async () => {
      await page.fill('#mb_id', newDistributorId);
      await page.fill('#mb_password', 'test123456');
      await page.fill('#mb_password_re', 'test123456');
      await page.fill('#mb_name', newDistributorName);
      await page.fill('#mb_nick', newDistributorNick);
      await page.click('input[type="submit"][value="총판 등록"]');
      await expect(page.locator('#container_title')).toContainText('총판 관리'); // 목록 페이지로 돌아왔는지 확인
      await expect(page.locator(`text=${newDistributorId}`)).toBeVisible(); // 등록된 총판이 목록에 있는지 확인
    });

    await test.step('등록된 총판 정보 수정', async () => {
      await page.locator(`a[href*="distributor_form.php?w=u&mb_id=${newDistributorId}"]`).click();
      await expect(page.locator('#container_title')).toContainText('총판 등록/수정');
      await page.fill('#mb_name', `${newDistributorName}_수정`);
      await page.click('input[type="submit"][value="총판 정보 수정"]');
      await expect(page.locator('#container_title')).toContainText('총판 관리');
      await expect(page.locator(`text=${newDistributorName}_수정`)).toBeVisible();
    });
  });

  test('본사 관리자는 대리점을 등록하고 수정할 수 있다', async ({ page }) => {
    await test.step('대리점 목록 페이지로 이동', async () => {
      await page.goto('http://localhost:8001/dmk/adm/agency_admin/agency_list.php'); // 실제 URL로 대체
      await expect(page.locator('#container_title')).toContainText('대리점 관리');
    });

    await test.step('새로운 대리점 등록 폼으로 이동', async () => {
      await page.click('text=대리점 등록');
      await expect(page.locator('#container_title')).toContainText('대리점 등록');
    });

    const newAgencyId = `AG_TEST_${Date.now()}`;
    const newAgencyName = `테스트대리점_${Date.now()}`;
    const newAgencyNick = `테스트회사_${Date.now()}`;

    await test.step('새로운 대리점 정보 입력 및 등록', async () => {
      // 본사 관리자는 총판을 선택해야 함
      await page.selectOption('#dt_id', { index: 1 }); // 첫 번째 총판 선택 (실제 환경에 따라 조정 필요)
      await page.waitForSelector('#ag_id option:not([value=""])'); // 대리점 로드 대기
      await page.selectOption('#ag_id', { index: 1 }); // 첫 번째 대리점 선택 (실제 환경에 따라 조정 필요)
      await page.fill('#ag_id', newAgencyId);
      await page.fill('#mb_password', 'test123456');
      await page.fill('#mb_password_confirm', 'test123456');
      await page.fill('#mb_name', newAgencyName);
      await page.fill('#mb_nick', newAgencyNick);
      await page.fill('#mb_email', `${newAgencyId}@test.com`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('#container_title')).toContainText('대리점 관리');
      await expect(page.locator(`text=${newAgencyId}`)).toBeVisible();
    });

    await test.step('등록된 대리점 정보 수정', async () => {
      await page.locator(`a[href*="agency_form.php?w=u&ag_id=${newAgencyId}"]`).click();
      await expect(page.locator('#container_title')).toContainText('대리점 등록/수정');
      await page.fill('#mb_name', `${newAgencyName}_수정`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('#container_title')).toContainText('대리점 관리');
      await expect(page.locator(`text=${newAgencyName}_수정`)).toBeVisible();
    });
  });

  test('본사 관리자는 지점을 등록하고 수정할 수 있다', async ({ page }) => {
    await test.step('지점 목록 페이지로 이동', async () => {
      await page.goto('http://localhost:8001/dmk/adm/branch_admin/branch_list.php'); // 실제 URL로 대체
      await expect(page.locator('#container_title')).toContainText('지점 관리');
    });

    await test.step('새로운 지점 등록 폼으로 이동', async () => {
      await page.click('text=지점 등록');
      await expect(page.locator('#container_title')).toContainText('지점 등록');
    });

    const newBranchId = `BR_TEST_${Date.now()}`;
    const newBranchName = `테스트지점_${Date.now()}`;
    const newBranchNick = `테스트회사_${Date.now()}`;

    await test.step('새로운 지점 정보 입력 및 등록', async () => {
      // 본사 관리자는 총판과 대리점을 선택해야 함
      await page.selectOption('#dt_id', { index: 1 }); // 첫 번째 총판 선택
      await page.waitForSelector('#ag_id option:not([value=""])'); // 대리점 로드 대기
      await page.selectOption('#ag_id', { index: 1 }); // 첫 번째 대리점 선택 (실제 환경에 따라 조정 필요)
      await page.fill('#br_id', newBranchId);
      await page.fill('#mb_password', 'test123456');
      await page.fill('#mb_password_confirm', 'test123456');
      await page.fill('#mb_nick', newBranchName);
      await page.fill('#mb_name', newBranchNick);
      await page.fill('#mb_email', `${newBranchId}@test.com`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('#container_title')).toContainText('지점 관리');
      await expect(page.locator(`text=${newBranchId}`)).toBeVisible();
    });

    await test.step('등록된 지점 정보 수정', async () => {
      await page.locator(`a[href*="branch_form.php?w=u&br_id=${newBranchId}"]`).click();
      await expect(page.locator('#container_title')).toContainText('지점 등록/수정');
      await page.fill('#mb_nick', `${newBranchName}_수정`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('#container_title')).toContainText('지점 관리');
      await expect(page.locator(`text=${newBranchName}_수정`)).toBeVisible();
    });
  });

  test('본사 관리자는 통계 대시보드에 접근할 수 있다', async ({ page }) => {
    await page.goto('http://localhost:8001/dmk/adm/statistics/statistics_dashboard.php'); // 실제 URL로 대체
    await expect(page.locator('#container_title')).toContainText('통계 대시보드 (프로토타입으로 실제 내용 구현해야 함)');
    await expect(page.locator('text=총 주문 건수')).toBeVisible();
    await expect(page.getByText('총 매출액', { exact: true })).toBeVisible();
  });
});
