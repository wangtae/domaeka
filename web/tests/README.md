# Playwright 테스트 가이드

## WSL 환경에서 브라우저 투명 문제 해결

WSL 환경에서 Playwright를 실행할 때 브라우저가 투명하게 표시되는 문제가 발생할 수 있습니다.

### 해결 방법

#### 방법 1: `pw` 래퍼 스크립트 사용 (권장)
```bash
# npx playwright 대신 ./pw 사용
./pw test headquarters-admin.spec.ts --headed --grep "1.1 총판 목록 조회"

# 모든 playwright 명령어에 사용 가능
./pw test --headed
./pw show-report
./pw codegen http://domaeka.local/adm
```

#### 방법 2: run-test.sh 스크립트 사용
```bash
./run-test.sh
```

#### 방법 3: 환경 변수 직접 설정
```bash
export LIBGL_ALWAYS_SOFTWARE=1
export PLAYWRIGHT_CHROMIUM_ARGS="--no-sandbox --disable-gpu --disable-software-rasterizer"
npx playwright test --headed
```

#### 방법 4: npm 스크립트 사용
```bash
# WSL 전용 스크립트
npm run test:headquarters:wsl

# Firefox 사용 (더 안정적)
npm run test:headquarters:firefox
```

## 테스트 실행 명령어

### 기본 테스트 실행
```bash
# 헤드리스 모드 (백그라운드)
npm run test:headquarters

# 헤드풀 모드 (브라우저 표시) - WSL에서는 ./pw 사용 권장
./pw test headquarters-admin.spec.ts --headed
```

### 특정 테스트만 실행
```bash
# grep으로 특정 테스트 선택
./pw test headquarters-admin.spec.ts --headed --grep "1.1 총판 목록 조회"

# 디버그 모드
./pw test --debug
```

### 테스트 결과 확인
```bash
# HTML 리포트 보기
./pw show-report

# 트레이스 확인
npm run test:headquarters:trace
./pw show-trace trace.zip
```

### 테스트 데이터 정리
```bash
# 시뮬레이션 (실제 삭제 안함)
npm run test:cleanup:dry

# 실제 정리
npm run test:cleanup
```

## 개발 팁

1. **WSL에서는 Firefox가 더 안정적**
   ```bash
   npm run test:headquarters:firefox
   ```

2. **코드 생성기 사용**
   ```bash
   ./pw codegen http://domaeka.local/adm
   ```

3. **UI 모드 사용**
   ```bash
   ./pw test --ui
   ```

4. **병렬 실행 제어**
   ```bash
   # 순차 실행
   ./pw test --workers=1
   
   # 병렬 실행
   ./pw test --workers=4
   ```