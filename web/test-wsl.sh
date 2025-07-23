#!/bin/bash

# WSL 환경에서 Playwright 테스트 실행을 위한 스크립트
# 그래픽 렌더링 문제 해결을 위한 환경 변수 설정

echo "WSL 환경에서 Playwright 테스트 실행 준비..."

# X11 디스플레이 설정
export DISPLAY=:0

# 하드웨어 가속 비활성화 (Cursor AI와 동일한 설정)
export LIBGL_ALWAYS_SOFTWARE=1

# Chromium 관련 환경 변수 (Cursor AI와 동일한 설정)
export PLAYWRIGHT_CHROMIUM_ARGS="--no-sandbox --disable-gpu --disable-software-rasterizer"

# 메시지 출력
echo "환경 변수 설정 완료:"
echo "  DISPLAY=$DISPLAY"
echo "  LIBGL_ALWAYS_SOFTWARE=$LIBGL_ALWAYS_SOFTWARE"
echo ""

# 테스트 실행 옵션 선택
echo "테스트 실행 방법을 선택하세요:"
echo "1) 헤드풀 모드 (브라우저 표시)"
echo "2) 헤드리스 모드 (백그라운드)"
echo "3) 디버그 모드"
echo "4) Firefox로 실행"

read -p "선택 (1-4): " choice

case $choice in
  1)
    echo "헤드풀 모드로 실행합니다..."
    npm run test:headquarters:headed
    ;;
  2)
    echo "헤드리스 모드로 실행합니다..."
    npm run test:headquarters
    ;;
  3)
    echo "디버그 모드로 실행합니다..."
    npm run test:debug
    ;;
  4)
    echo "Firefox로 실행합니다..."
    npx playwright test headquarters-admin.spec.ts --project=firefox --headed
    ;;
  *)
    echo "잘못된 선택입니다."
    exit 1
    ;;
esac