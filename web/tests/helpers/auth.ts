import { Page } from '@playwright/test';

const BASE_URL = process.env.BASE_URL || 'http://domaeka.local';

/**
 * 관리자 로그인
 */
export async function loginAsAdmin(page: Page, username: string, password: string) {
  await page.goto(`${BASE_URL}/adm`);
  await page.fill('#login_id', username);
  await page.fill('#login_pw', password);
  await page.click('button[type="submit"]');
  
  // 로그인 성공 확인
  await page.waitForURL(/\/adm\/?$/);
}

/**
 * 본사 최고관리자 로그인
 */
export async function loginAsHeadquarters(page: Page) {
  await loginAsAdmin(page, 'admin', '!domaekaservice@.');
}

/**
 * 총판 관리자 로그인
 */
export async function loginAsDistributor(page: Page, distributorId: string, password: string) {
  await loginAsAdmin(page, distributorId, password);
}

/**
 * 대리점 관리자 로그인
 */
export async function loginAsAgency(page: Page, agencyId: string, password: string) {
  await loginAsAdmin(page, agencyId, password);
}

/**
 * 지점 관리자 로그인
 */
export async function loginAsBranch(page: Page, branchId: string, password: string) {
  await loginAsAdmin(page, branchId, password);
}