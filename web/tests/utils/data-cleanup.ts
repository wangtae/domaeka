/**
 * 테스트 데이터 정리 유틸리티
 */

import { Page } from '@playwright/test';
import { TEST_CONFIG, TEST_DATA, TEST_ACCOUNTS } from './test-config';
import { adminLogin, waitForPageReady, attemptDelete } from './test-helpers';

/**
 * 테스트 데이터 정리 (역순 삭제 - 지점 → 대리점 → 총판)
 */
export async function cleanupTestData(page: Page) {
  console.log('테스트 데이터 정리 시작...');
  
  // 본사 관리자로 로그인
  await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
  
  try {
    // 1. 지점 삭제
    console.log('1. 임시 지점 삭제 중...');
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/branch_admin/branch_list.php`);
    await waitForPageReady(page);
    await attemptDelete(page, TEST_DATA.BRANCH.id, true);
    
    // 2. 대리점 삭제  
    console.log('2. 임시 대리점 삭제 중...');
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/agency_admin/agency_list.php`);
    await waitForPageReady(page);
    await attemptDelete(page, TEST_DATA.AGENCY.id, true);
    
    // 3. 총판 삭제
    console.log('3. 임시 총판 삭제 중...');
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/distributor_admin/distributor_list.php`);
    await waitForPageReady(page);
    await attemptDelete(page, TEST_DATA.DISTRIBUTOR.id, true);
    
    console.log('테스트 데이터 정리 완료');
    
  } catch (error) {
    console.warn('테스트 데이터 정리 중 오류 발생:', error);
    console.log('수동 정리가 필요할 수 있습니다.');
  }
}

/**
 * 수동 데이터 정리를 위한 SQL 쿼리 생성
 */
export function generateCleanupSQL(): string {
  return `
-- 테스트 데이터 수동 삭제 쿼리
-- 실행 순서: 하위 계층부터 역순으로 삭제

-- 1. 지점 데이터 삭제
DELETE FROM g5_dmk_branches WHERE br_id = '${TEST_DATA.BRANCH.id}';

-- 2. 대리점 데이터 삭제
DELETE FROM g5_dmk_agencies WHERE ag_id = '${TEST_DATA.AGENCY.id}';

-- 3. 총판 데이터 삭제
DELETE FROM g5_dmk_distributors WHERE dt_id = '${TEST_DATA.DISTRIBUTOR.id}';

-- 4. 회원 데이터 삭제
DELETE FROM g5_member WHERE mb_id IN ('${TEST_DATA.DISTRIBUTOR.id}', '${TEST_DATA.AGENCY.id}', '${TEST_DATA.BRANCH.id}');

-- 5. 권한 데이터 삭제 (있는 경우)
DELETE FROM g5_auth WHERE mb_id IN ('${TEST_DATA.DISTRIBUTOR.id}', '${TEST_DATA.AGENCY.id}', '${TEST_DATA.BRANCH.id}');

-- 확인 쿼리
SELECT 'cleanup verification' as step;
SELECT COUNT(*) as remaining_distributors FROM g5_dmk_distributors WHERE dt_id = '${TEST_DATA.DISTRIBUTOR.id}';
SELECT COUNT(*) as remaining_agencies FROM g5_dmk_agencies WHERE ag_id = '${TEST_DATA.AGENCY.id}';  
SELECT COUNT(*) as remaining_branches FROM g5_dmk_branches WHERE br_id = '${TEST_DATA.BRANCH.id}';
SELECT COUNT(*) as remaining_members FROM g5_member WHERE mb_id IN ('${TEST_DATA.DISTRIBUTOR.id}', '${TEST_DATA.AGENCY.id}', '${TEST_DATA.BRANCH.id}');
`;
}

/**
 * 기존 테스트 데이터 존재 여부 확인
 */
export async function verifyExistingTestData(page: Page): Promise<boolean> {
  console.log('기존 테스트 데이터 확인 중...');
  
  try {
    await adminLogin(page, TEST_ACCOUNTS.HEADQUARTERS);
    
    // 기존 총판 확인
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/distributor_admin/distributor_list.php`);
    await waitForPageReady(page);
    const distributorExists = await page.locator(`tr:has-text("${TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id}")`).isVisible();
    
    // 기존 대리점 확인
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/agency_admin/agency_list.php`);
    await waitForPageReady(page);
    const agencyExists = await page.locator(`tr:has-text("${TEST_ACCOUNTS.EXISTING_AGENCY.id}")`).isVisible();
    
    // 기존 지점 확인
    await page.goto(`${TEST_CONFIG.BASE_URL}/dmk/adm/branch_admin/branch_list.php`);
    await waitForPageReady(page);
    const branchExists = await page.locator(`tr:has-text("${TEST_ACCOUNTS.EXISTING_BRANCH.id}")`).isVisible();
    
    const allExist = distributorExists && agencyExists && branchExists;
    
    if (!allExist) {
      console.warn('일부 기존 테스트 데이터가 없습니다:');
      console.warn(`- 총판 (${TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id}): ${distributorExists ? '존재' : '없음'}`);
      console.warn(`- 대리점 (${TEST_ACCOUNTS.EXISTING_AGENCY.id}): ${agencyExists ? '존재' : '없음'}`);
      console.warn(`- 지점 (${TEST_ACCOUNTS.EXISTING_BRANCH.id}): ${branchExists ? '존재' : '없음'}`);
    }
    
    return allExist;
    
  } catch (error) {
    console.error('기존 테스트 데이터 확인 중 오류:', error);
    return false;
  }
}

/**
 * 테스트 환경 초기화
 */
export async function initializeTestEnvironment(page: Page) {
  console.log('테스트 환경 초기화 중...');
  
  // 기존 임시 데이터가 있다면 정리
  await cleanupTestData(page);
  
  // 기존 테스트 데이터 확인
  const hasExistingData = await verifyExistingTestData(page);
  
  if (!hasExistingData) {
    console.warn('기존 테스트 데이터가 부족합니다. 수동으로 기본 테스트 데이터를 생성해주세요.');
    console.log('필요한 기본 데이터:');
    console.log(`- 총판: ${TEST_ACCOUNTS.EXISTING_DISTRIBUTOR.id}`);
    console.log(`- 대리점: ${TEST_ACCOUNTS.EXISTING_AGENCY.id}`);
    console.log(`- 지점: ${TEST_ACCOUNTS.EXISTING_BRANCH.id}`);
  }
  
  console.log('테스트 환경 초기화 완료');
  return hasExistingData;
}