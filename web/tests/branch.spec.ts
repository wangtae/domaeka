import { test, expect } from '@playwright/test';

// 지점 관리자 로그인 정보 (실제 정보로 대체 필요)
const BRANCH_ADMIN = {
  id: 'your_branch_id', // 실제 지점 ID로 대체
  password: 'your_branch_password', // 실제 지점 비밀번호로 대체
  loginUrl: 'http://your-domaeka-admin-url/adm/' // 실제 관리자 로그인 URL로 대체
};

test.describe('Branch Admin Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // 지점 관리자로 로그인
    await page.goto(BRANCH_ADMIN.loginUrl);
    await page.fill('#mb_id', BRANCH_ADMIN.id);
    await page.fill('#mb_password', BRANCH_ADMIN.password);
    await page.click('button[type="submit"]');
    // 로그인 성공 여부 확인
    await expect(page.locator('h1')).toContainText('관리자메인');
  });

  test('지점 관리자는 자신의 지점 정보만 조회할 수 있다', async ({ page }) => {
    await page.goto('http://your-domaeka-admin-url/dmk/adm/branch_admin/branch_list.php');
    await expect(page.locator('h1')).toContainText('지점 관리');
    // 자신의 지점 ID만 보이는지 확인
    await expect(page.locator(`text=${BRANCH_ADMIN.id}`)).toBeVisible();
    // 다른 지점 ID가 보이지 않는지 확인
    await expect(page.locator('text=OTHER_BR_ID')).not.toBeVisible();
  });

  test('지점 관리자는 자신의 지점 정보를 수정할 수 있다 (제한된 필드)', async ({ page }) => {
    await test.step('자신의 지점 수정 폼으로 이동', async () => {
      await page.goto(`http://your-domaeka-admin-url/dmk/adm/branch_admin/branch_form.php?w=u&br_id=${BRANCH_ADMIN.id}`);
      await expect(page.locator('h1')).toContainText('지점 수정');
    });

    await test.step('지점명, 단축 URL 코드, 상태 필드가 읽기 전용인지 확인', async () => {
      await expect(page.locator('#mb_nick')).toBeDisabled();
      await expect(page.locator('#br_shortcut_code')).toBeDisabled();
      await expect(page.locator('select[name="br_status"]')).not.toBeVisible(); // 상태 필드는 숨겨져야 함
    });

    await test.step('연락처 정보 수정', async () => {
      const newHp = '010-9876-5432';
      await page.fill('#mb_hp', newHp);
      await page.click('input[type="submit"][value="확인"]');
      await expect(page.locator('h1')).toContainText('지점 수정'); // 수정 후에도 같은 페이지에 머무는지 확인
      // 변경된 정보가 반영되었는지 확인하는 추가 검증 필요 (예: 페이지 새로고침 후 값 확인)
    });
  });

  test('지점 관리자는 통계 대시보드에 접근할 수 있다', async ({ page }) => {
    await page.goto('http://your-domaeka-admin-url/dmk/adm/statistics/statistics_dashboard.php');
    await expect(page.locator('h1')).toContainText('통계 대시보드');
    await expect(page.locator('text=총 주문 건수')).toBeVisible();
    // 지점은 자신의 지점 통계만 볼 수 있는지 확인하는 추가 검증 필요
  });
});
