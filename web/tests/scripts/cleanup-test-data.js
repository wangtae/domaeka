#!/usr/bin/env node

/**
 * 테스트 데이터 정리 스크립트
 * 모든 테스트 완료 후 생성된 데이터를 안전하게 삭제합니다.
 * 
 * 사용법:
 * node scripts/cleanup-test-data.js
 * node scripts/cleanup-test-data.js --dry-run  (실제 삭제 없이 시뮬레이션)
 */

const { chromium } = require('@playwright/test');

// 설정
const config = {
  baseUrl: process.env.BASE_URL || 'http://domaeka.local',
  adminId: process.env.ADMIN_ID || 'admin',
  adminPw: process.env.ADMIN_PW || '!domaekaservice@.',
  dryRun: process.argv.includes('--dry-run'),
  verbose: process.argv.includes('--verbose') || process.argv.includes('-v'),
};

// 로깅 헬퍼
const log = {
  info: (msg) => console.log(`ℹ️  ${msg}`),
  success: (msg) => console.log(`✅ ${msg}`),
  error: (msg) => console.error(`❌ ${msg}`),
  warn: (msg) => console.warn(`⚠️  ${msg}`),
  debug: (msg) => config.verbose && console.log(`🔍 ${msg}`),
};

// 삭제할 테스트 데이터 패턴
const testDataPatterns = {
  branches: ['b_test_', 'b_sub_test_'],
  agencies: ['a_test_', 'a_sub_test_'],
  distributors: ['d_test_'],
  subAdmins: ['_sub_', '_test_sub_'],
};

/**
 * 관리자 로그인
 */
async function loginAsAdmin(page) {
  log.debug('관리자 로그인 시작');
  
  await page.goto(`${config.baseUrl}/adm`);
  await page.fill('#login_id', config.adminId);
  await page.fill('#login_pw', config.adminPw);
  await page.click('button[type="submit"]');
  
  // 로그인 확인
  await page.waitForURL(/\/adm\/?$/);
  log.success('관리자 로그인 성공');
}

/**
 * 지점 데이터 삭제
 */
async function deleteTestBranches(page) {
  log.info('지점 테스트 데이터 삭제 시작');
  
  await page.goto(`${config.baseUrl}/adm/shop_admin/branch_list.php`);
  
  let deletedCount = 0;
  
  for (const pattern of testDataPatterns.branches) {
    const branches = await page.$$eval(
      'table.tbl_head01 tbody tr',
      (rows, pat) => {
        return rows
          .filter(row => {
            const idCell = row.querySelector('td:nth-child(2)');
            return idCell && idCell.textContent.includes(pat);
          })
          .map(row => ({
            id: row.querySelector('td:nth-child(2)').textContent.trim(),
            deleteBtn: row.querySelector('a[href*="delete"]')?.getAttribute('href'),
          }));
      },
      pattern
    );
    
    for (const branch of branches) {
      log.debug(`지점 삭제: ${branch.id}`);
      
      if (config.dryRun) {
        log.warn(`[DRY RUN] 지점 ${branch.id} 삭제 예정`);
        continue;
      }
      
      // 삭제 실행
      await page.goto(branch.deleteBtn);
      await page.click('button[type="submit"]'); // 확인
      deletedCount++;
    }
  }
  
  log.success(`지점 ${deletedCount}개 삭제 완료`);
}

/**
 * 대리점 데이터 삭제
 */
async function deleteTestAgencies(page) {
  log.info('대리점 테스트 데이터 삭제 시작');
  
  await page.goto(`${config.baseUrl}/adm/shop_admin/agency_list.php`);
  
  let deletedCount = 0;
  
  for (const pattern of testDataPatterns.agencies) {
    const agencies = await page.$$eval(
      'table.tbl_head01 tbody tr',
      (rows, pat) => {
        return rows
          .filter(row => {
            const idCell = row.querySelector('td:nth-child(2)');
            return idCell && idCell.textContent.includes(pat);
          })
          .map(row => ({
            id: row.querySelector('td:nth-child(2)').textContent.trim(),
            deleteBtn: row.querySelector('a[href*="delete"]')?.getAttribute('href'),
          }));
      },
      pattern
    );
    
    for (const agency of agencies) {
      log.debug(`대리점 삭제: ${agency.id}`);
      
      if (config.dryRun) {
        log.warn(`[DRY RUN] 대리점 ${agency.id} 삭제 예정`);
        continue;
      }
      
      // 삭제 실행
      await page.goto(agency.deleteBtn);
      await page.click('button[type="submit"]'); // 확인
      deletedCount++;
    }
  }
  
  log.success(`대리점 ${deletedCount}개 삭제 완료`);
}

/**
 * 총판 데이터 삭제
 */
async function deleteTestDistributors(page) {
  log.info('총판 테스트 데이터 삭제 시작');
  
  await page.goto(`${config.baseUrl}/adm/shop_admin/distributor_list.php`);
  
  let deletedCount = 0;
  
  for (const pattern of testDataPatterns.distributors) {
    const distributors = await page.$$eval(
      'table.tbl_head01 tbody tr',
      (rows, pat) => {
        return rows
          .filter(row => {
            const idCell = row.querySelector('td:nth-child(2)');
            return idCell && idCell.textContent.includes(pat);
          })
          .map(row => ({
            id: row.querySelector('td:nth-child(2)').textContent.trim(),
            deleteBtn: row.querySelector('a[href*="delete"]')?.getAttribute('href'),
          }));
      },
      pattern
    );
    
    for (const distributor of distributors) {
      log.debug(`총판 삭제: ${distributor.id}`);
      
      if (config.dryRun) {
        log.warn(`[DRY RUN] 총판 ${distributor.id} 삭제 예정`);
        continue;
      }
      
      // 삭제 실행
      await page.goto(distributor.deleteBtn);
      await page.click('button[type="submit"]'); // 확인
      deletedCount++;
    }
  }
  
  log.success(`총판 ${deletedCount}개 삭제 완료`);
}

/**
 * 서브관리자 계정 삭제
 */
async function deleteTestSubAdmins(page) {
  log.info('서브관리자 테스트 계정 삭제 시작');
  
  await page.goto(`${config.baseUrl}/adm/member_list.php`);
  
  let deletedCount = 0;
  
  for (const pattern of testDataPatterns.subAdmins) {
    // 서브관리자 검색
    await page.fill('input[name="stx"]', pattern);
    await page.click('button[type="submit"]');
    
    const members = await page.$$eval(
      'table.tbl_head01 tbody tr',
      (rows, pat) => {
        return rows
          .filter(row => {
            const idCell = row.querySelector('td.td_mbid');
            const levelCell = row.querySelector('td.td_level');
            const level = levelCell ? parseInt(levelCell.textContent) : 0;
            
            // 관리자 레벨(5-8)인 테스트 계정만
            return idCell && 
                   idCell.textContent.includes(pat) && 
                   level >= 5 && level <= 8;
          })
          .map(row => ({
            id: row.querySelector('td.td_mbid').textContent.trim(),
            deleteBtn: row.querySelector('a[href*="member_delete"]')?.getAttribute('href'),
          }));
      },
      pattern
    );
    
    for (const member of members) {
      log.debug(`서브관리자 삭제: ${member.id}`);
      
      if (config.dryRun) {
        log.warn(`[DRY RUN] 서브관리자 ${member.id} 삭제 예정`);
        continue;
      }
      
      // 삭제 실행
      await page.goto(member.deleteBtn);
      deletedCount++;
    }
  }
  
  log.success(`서브관리자 ${deletedCount}명 삭제 완료`);
}

/**
 * 정리 작업 실행
 */
async function cleanupTestData() {
  log.info('테스트 데이터 정리 시작');
  
  if (config.dryRun) {
    log.warn('DRY RUN 모드 - 실제 삭제는 수행되지 않습니다');
  }
  
  const browser = await chromium.launch({
    headless: !config.verbose, // verbose 모드에서는 브라우저 표시
  });
  
  const context = await browser.newContext();
  const page = await context.newPage();
  
  try {
    // 1. 관리자 로그인
    await loginAsAdmin(page);
    
    // 2. 계층 구조 역순으로 삭제
    await deleteTestBranches(page);
    await deleteTestAgencies(page);
    await deleteTestDistributors(page);
    
    // 3. 서브관리자 계정 삭제
    await deleteTestSubAdmins(page);
    
    log.success('모든 테스트 데이터 정리 완료!');
    
  } catch (error) {
    log.error(`정리 작업 중 오류 발생: ${error.message}`);
    
    if (config.verbose) {
      console.error(error);
    }
    
    // 스크린샷 저장
    await page.screenshot({ 
      path: `cleanup-error-${Date.now()}.png`,
      fullPage: true 
    });
    
    process.exit(1);
    
  } finally {
    await browser.close();
  }
}

/**
 * 사용법 출력
 */
function printUsage() {
  console.log(`
테스트 데이터 정리 스크립트

사용법:
  node cleanup-test-data.js [옵션]

옵션:
  --dry-run    실제 삭제 없이 시뮬레이션만 수행
  -v, --verbose    상세 로그 출력 및 브라우저 표시
  --help       도움말 표시

환경변수:
  BASE_URL     기본 URL (기본값: http://domaeka.local)
  ADMIN_ID     관리자 ID (기본값: admin)
  ADMIN_PW     관리자 비밀번호

예제:
  # 기본 실행
  node cleanup-test-data.js
  
  # 시뮬레이션 모드
  node cleanup-test-data.js --dry-run
  
  # 상세 모드
  node cleanup-test-data.js --verbose
  `);
}

// 메인 실행
if (process.argv.includes('--help')) {
  printUsage();
} else {
  cleanupTestData().catch(error => {
    log.error('예기치 않은 오류:', error.message);
    process.exit(1);
  });
}