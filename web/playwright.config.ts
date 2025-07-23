import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright 설정
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests',
  /* 테스트 실행 최대 시간 */
  timeout: 30 * 1000,
  expect: {
    /**
     * expect() 최대 대기 시간
     */
    timeout: 5000
  },
  /* 병렬 테스트 실패 시 재시도 금지 */
  fullyParallel: false,
  /* CI에서만 재시도 */
  forbidOnly: !!process.env.CI,
  /* 실패 시 재시도 횟수 */
  retries: process.env.CI ? 2 : 0,
  /* 병렬 실행 워커 수 */
  workers: process.env.CI ? 1 : 4,
  /* 리포터 설정 */
  reporter: [
    ['html'],
    ['list'],
    ['json', { outputFile: 'test-results.json' }]
  ],
  /* 전역 설정 */
  use: {
    /* 기본 URL */
    baseURL: process.env.BASE_URL || 'http://domaeka.local',

    /* 모든 액션 추적 */
    trace: 'retain-on-failure',

    /* 스크린샷 */
    screenshot: 'only-on-failure',

    /* 비디오 녹화 */
    video: process.env.CI ? 'retain-on-failure' : 'on',

    /* 액션 타임아웃 */
    actionTimeout: 10 * 1000,

    /* 헤드리스 모드 */
    headless: process.env.CI ? true : false,

    /* 느린 모션 (개발 시) */
    slowMo: process.env.CI ? 0 : 100,

    /* 뷰포트 크기 */
    viewport: { width: 1280, height: 720 },

    /* 사용자 에이전트 */
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',

    /* 로케일 */
    locale: 'ko-KR',

    /* 타임존 */
    timezoneId: 'Asia/Seoul',
  },

  /* 브라우저 프로젝트 설정 */
  projects: [
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        // WSL/Linux 그래픽 렌더링 문제 해결
        launchOptions: {
          args: [
            '--no-sandbox',
            '--disable-gpu',
            '--disable-software-rasterizer'
          ],
          ignoreDefaultArgs: ['--enable-automation'],
        },
      },
    },
    
    // Chrome 브라우저 사용 (시스템에 설치된 Chrome)
    // 주석 처리하여 기본적으로 실행되지 않도록 함
    /*
    {
      name: 'chrome',
      use: { 
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        launchOptions: {
          args: [
            '--no-sandbox',
            '--disable-gpu',
            '--disable-software-rasterizer'
          ],
        },
      },
    },
    */

    // Firefox - WSL에서 더 나은 렌더링 제공
    // 주석 처리하여 기본적으로 실행되지 않도록 함
    /*
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    */

    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    /* 모바일 뷰포트 테스트 */
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
  ],

  /* 로컬 개발 서버 실행 (필요 시) */
  // webServer: {
  //   command: 'npm run start',
  //   port: 3000,
  //   reuseExistingServer: !process.env.CI,
  // },
});