/**
 * 테스트 데이터 생성 헬퍼
 */

/**
 * 고유한 테스트 ID 생성
 */
export function generateTestId(prefix: string): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(2, 5);
  return `${prefix}_${timestamp}_${random}`;
}

/**
 * 기본 테스트 데이터 구조
 */
export interface TestOrganization {
  id: string;
  password: string;
  name: string;
  company: string;
  companyUpdated?: string;
  email: string;
  phone: string;
  phoneUpdated?: string;
}

/**
 * 총판 테스트 데이터 생성
 */
export function createDistributorTestData(customId?: string): TestOrganization {
  const id = customId || generateTestId('d_test');
  return {
    id,
    password: id,
    name: id,
    company: `${id}총판`,
    companyUpdated: `${id}총판_수정`,
    email: `${id}@test.com`,
    phone: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`,
    phoneUpdated: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`
  };
}

/**
 * 대리점 테스트 데이터 생성
 */
export function createAgencyTestData(customId?: string): TestOrganization {
  const id = customId || generateTestId('a_test');
  return {
    id,
    password: id,
    name: id,
    company: `${id}대리점`,
    companyUpdated: `${id}대리점_수정`,
    email: `${id}@test.com`,
    phone: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`,
    phoneUpdated: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`
  };
}

/**
 * 지점 테스트 데이터 생성
 */
export function createBranchTestData(customId?: string): TestOrganization {
  const id = customId || generateTestId('b_test');
  return {
    id,
    password: id,
    name: id,
    company: `${id}지점`,
    email: `${id}@test.com`,
    phone: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`
  };
}

/**
 * 테스트 컨텍스트 - 생성된 데이터 추적
 */
export class TestContext {
  private createdDistributors: string[] = [];
  private createdAgencies: string[] = [];
  private createdBranches: string[] = [];
  private createdSubAdmins: string[] = [];

  addDistributor(id: string) {
    this.createdDistributors.push(id);
  }

  addAgency(id: string) {
    this.createdAgencies.push(id);
  }

  addBranch(id: string) {
    this.createdBranches.push(id);
  }

  addSubAdmin(id: string) {
    this.createdSubAdmins.push(id);
  }

  getCreatedDistributors(): string[] {
    return [...this.createdDistributors];
  }

  getCreatedAgencies(): string[] {
    return [...this.createdAgencies];
  }

  getCreatedBranches(): string[] {
    return [...this.createdBranches];
  }

  getCreatedSubAdmins(): string[] {
    return [...this.createdSubAdmins];
  }

  getAllCreatedData() {
    return {
      distributors: this.getCreatedDistributors(),
      agencies: this.getCreatedAgencies(),
      branches: this.getCreatedBranches(),
      subAdmins: this.getCreatedSubAdmins()
    };
  }

  clear() {
    this.createdDistributors = [];
    this.createdAgencies = [];
    this.createdBranches = [];
    this.createdSubAdmins = [];
  }
}

// 전역 테스트 컨텍스트
export const testContext = new TestContext();