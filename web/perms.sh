#!/bin/bash

# kcp 본인확인 실행 파일
chmod 755 plugin/kcpcert/bin/ct_cli
chmod 755 plugin/kcpcert/bin/ct_cli_x64

# okname 본인확인 실행 파일
chmod 755 plugin/okname/bin/okname
chmod 755 plugin/okname/bin/okname_x64

# kcp 전자결제 실행 파일
if [ -d "shop" ]; then
  chmod 755 shop/kcp/bin/pp_cli
  chmod 755 shop/kcp/bin/pp_cli_x64
fi

# 업로드 디렉토리 권한 설정
if [ ! -d "data/schedule" ]; then
  mkdir -p data/schedule
fi
chmod 777 data/schedule

echo "Complete change permissions."
