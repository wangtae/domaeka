/**
 * 테스트 설정 및 상수 정의
 */

// 기본 설정
export const TEST_CONFIG = {
  BASE_URL: process.env.BASE_URL || 'http://domaeka.local',
  TIMEOUT: {
    DEFAULT: 10000,
    FORM_SUBMIT: 15000,
    PAGE_LOAD: 30000
  }
};

// 테스트 계정 정보
export const TEST_ACCOUNTS = {
  // 본사 최고관리자
  HEADQUARTERS: {
    id: 'admin',
    password: '!domaekaservice@.',
    type: 'super'
  },
  
  // 기존 테스트용 총판 (테스트 전부터 존재)
  EXISTING_DISTRIBUTOR: {
    id: 'test_distributor',
    password: 'test1234',
    name: '기존총판',
    company: '기존총판회사',
    email: 'existing_dist@test.com',
    phone: '010-1111-1111',
    type: 'distributor'
  },
  
  // 기존 테스트용 대리점 (테스트 전부터 존재)
  EXISTING_AGENCY: {
    id: 'test_agency',
    password: 'test1234',
    name: '기존대리점',
    company: '기존대리점회사',
    email: 'existing_agency@test.com',
    phone: '010-2222-2222',
    type: 'agency',
    parent_id: 'test_distributor'
  },
  
  // 기존 테스트용 지점 (테스트 전부터 존재)
  EXISTING_BRANCH: {
    id: 'test_branch',
    password: 'test1234',
    name: '기존지점',
    company: '기존지점회사',
    email: 'existing_branch@test.com',
    phone: '010-3333-3333',
    type: 'branch',
    parent_id: 'test_agency'
  }
};

// 테스트 중 생성할 데이터 (테스트 후 삭제 대상)
export const TEST_DATA = {
  DISTRIBUTOR: {
    id: 'temp_dist_01',
    password: 'temp_dist_01',
    name: '임시총판01',
    company: '임시총판회사01',
    email: 'temp_dist_01@test.com',
    phone: '010-4444-4444',
    companyUpdated: '임시총판회사01_수정',
    phoneUpdated: '010-4444-4445'
  },
  
  AGENCY: {
    id: 'temp_agency_01',
    password: 'temp_agency_01',
    name: '임시대리점01',
    company: '임시대리점회사01',
    email: 'temp_agency_01@test.com',
    phone: '010-5555-5555',
    parent_id: 'temp_dist_01',
    companyUpdated: '임시대리점회사01_수정',
    phoneUpdated: '010-5555-5556'
  },
  
  BRANCH: {
    id: 'temp_branch_01',
    password: 'temp_branch_01',
    name: '임시지점01',
    company: '임시지점회사01',
    email: 'temp_branch_01@test.com',
    phone: '010-6666-6666',
    parent_id: 'temp_agency_01'
  }
};

// 메뉴 경로 정의
export const MENU_PATHS = {
  DISTRIBUTOR_LIST: '/dmk/adm/distributor_admin/distributor_list.php',
  DISTRIBUTOR_FORM: '/dmk/adm/distributor_admin/distributor_form.php',
  AGENCY_LIST: '/dmk/adm/agency_admin/agency_list.php',
  AGENCY_FORM: '/dmk/adm/agency_admin/agency_form.php',
  BRANCH_LIST: '/dmk/adm/branch_admin/branch_list.php',
  BRANCH_FORM: '/dmk/adm/branch_admin/branch_form.php'
};

// 에러 메시지 패턴
export const ERROR_MESSAGES = {
  ACCESS_DENIED: '접근 권한이 없습니다',
  INVALID_LOGIN: '아이디나 비밀번호가 일치하지 않습니다',
  DUPLICATE_ID: '중복',
  REQUIRED_FIELD: '필수',
  HIERARCHY_CONSTRAINT: '하위.*존재'
};