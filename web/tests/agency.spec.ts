import { test, expect } from '@playwright/test';

// 대리점 관리자 로그인 정보 (실제 정보로 대체 필요)
const AGENCY_ADMIN = {
  id: 'your_agency_id', // 실제 대리점 ID로 대체
  password: 'your_agency_password', // 실제 대리점 비밀번호로 대체
  loginUrl: 'http://your-domaeka-admin-url/adm/' // 실제 관리자 로그인 URL로 대체
};

test.describe('Agency Admin Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // 대리점 관리자로 로그인
    await page.goto(AGENCY_ADMIN.loginUrl);
    await page.fill('#mb_id', AGENCY_ADMIN.id);
    await page.fill('#mb_password', AGENCY_ADMIN.password);
    await page.click('button[type="submit"]');
    // 로그인 성공 여부 확인
    await expect(page.locator('h1')).toContainText('관리자메인');
  });

  test('대리점 관리자는 자신의 대리점 정보만 조회할 수 있다', async ({ page }) => {
    await page.goto('http://your-domaeka-admin-url/dmk/adm/agency_admin/agency_list.php');
    await expect(page.locator('h1')).toContainText('대리점 관리');
    // 자신의 대리점 ID만 보이는지 확인
    await expect(page.locator(`text=${AGENCY_ADMIN.id}`)).toBeVisible();
    // 다른 대리점 ID가 보이지 않는지 확인
    await expect(page.locator('text=OTHER_AG_ID')).not.toBeVisible();
  });

  test('대리점 관리자는 지점을 등록하고 수정할 수 있다', async ({ page }) => {
    await test.step('지점 목록 페이지로 이동', async () => {
      await page.goto('http://your-domaeka-admin-url/dmk/adm/branch_admin/branch_list.php');
      await expect(page.locator('h1')).toContainText('지점 관리');
    });

    await test.step('새로운 지점 등록 폼으로 이동', async () => {
      await page.click('text=지점 등록');
      await expect(page.locator('h1')).toContainText('지점 등록/수정');
    });

    const newBranchId = `BR_TEST_${Date.now()}`;
    const newBranchName = `테스트지점_${Date.now()}`;
    const newBranchNick = `테스트회사_${Date.now()}`;

    await test.step('새로운 지점 정보 입력 및 등록 (자신의 대리점에 소속)', async () => {
      // 대리점 관리자는 자신의 대리점이 자동으로 선택되어야 함
      await expect(page.locator('input[name="ag_id"][type="hidden"]')).toHaveValue(AGENCY_ADMIN.id);
      await page.fill('#br_id', newBranchId);
      await page.fill('#mb_password', 'test123456');
      await page.fill('#mb_password_confirm', 'test123456');
      await page.fill('#mb_nick', newBranchName);
      await page.fill('#mb_name', newBranchNick);
      await page.fill('#mb_email', `${newBranchId}@test.com`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('h1')).toContainText('지점 관리');
      await expect(page.locator(`text=${newBranchId}`)).toBeVisible();
    });

    await test.step('등록된 지점 정보 수정', async () => {
      await page.locator(`a[href*="branch_form.php?w=u&br_id=${newBranchId}"]`).click();
      await expect(page.locator('h1')).toContainText('지점 등록/수정');
      await page.fill('#mb_nick', `${newBranchName}_수정`);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('h1')).toContainText('지점 관리');
      await expect(page.locator(`text=${newBranchName}_수정`)).toBeVisible();
    });
  });

  test('대리점 관리자는 통계 대시보드에 접근할 수 있다', async ({ page }) => {
    await page.goto('http://your-domaeka-admin-url/dmk/adm/statistics/statistics_dashboard.php');
    await expect(page.locator('h1')).toContainText('통계 대시보드');
    await expect(page.locator('text=총 주문 건수')).toBeVisible();
    // 대리점은 자신의 대리점에 속한 지점들의 통계만 볼 수 있는지 확인하는 추가 검증 필요
  });
});
