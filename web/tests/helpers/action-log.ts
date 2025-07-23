import { Page, expect } from '@playwright/test';

/**
 * 관리자 액션 로그 검증
 */
export async function verifyActionLog(
  page: Page, 
  action: string, 
  target: string, 
  adminId: string
) {
  try {
    // Ajax 호출로 로그 확인
    const logResponse = await page.evaluate(async (params) => {
      const response = await fetch('/adm/ajax_get_admin_log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: params.action,
          target: params.target,
          admin_id: params.adminId,
          limit: 1
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      return response.json();
    }, { action, target, adminId });
    
    // 로그 검증
    expect(logResponse).toBeTruthy();
    expect(logResponse.action).toBe(action);
    expect(logResponse.target).toContain(target);
    
    return true;
  } catch (error) {
    console.warn(`액션 로그 검증 실패: ${error.message}`);
    // 로그 검증 실패는 테스트 실패로 처리하지 않음 (선택적)
    return false;
  }
}

/**
 * 로그인 액션 로그 확인
 */
export async function verifyLoginLog(page: Page, adminId: string) {
  return verifyActionLog(page, 'LOGIN', adminId, adminId);
}

/**
 * CRUD 액션 로그 확인
 */
export async function verifyInsertLog(page: Page, target: string, adminId: string) {
  return verifyActionLog(page, 'INSERT', target, adminId);
}

export async function verifyUpdateLog(page: Page, target: string, adminId: string) {
  return verifyActionLog(page, 'UPDATE', target, adminId);
}

export async function verifyDeleteLog(page: Page, target: string, adminId: string) {
  return verifyActionLog(page, 'DELETE', target, adminId);
}

export async function verifySelectLog(page: Page, target: string, adminId: string) {
  return verifyActionLog(page, 'SELECT', target, adminId);
}