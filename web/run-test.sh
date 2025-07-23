#!/bin/bash

# Cursor AI와 동일한 환경 변수 설정
export LIBGL_ALWAYS_SOFTWARE=1
export PLAYWRIGHT_CHROMIUM_ARGS="--no-sandbox --disable-gpu --disable-software-rasterizer"

# Display 환경 변수 확인 및 설정
if [ -z "$DISPLAY" ]; then
    export DISPLAY=:0
fi

echo "=========================================="
echo "Playwright 테스트 실행 환경"
echo "=========================================="
echo "DISPLAY: $DISPLAY"
echo "LIBGL_ALWAYS_SOFTWARE: $LIBGL_ALWAYS_SOFTWARE"
echo "PLAYWRIGHT_CHROMIUM_ARGS: $PLAYWRIGHT_CHROMIUM_ARGS"
echo "=========================================="

# 테스트 실행
echo "테스트를 실행합니다..."
npx playwright test headquarters-admin.spec.ts --headed "$@"