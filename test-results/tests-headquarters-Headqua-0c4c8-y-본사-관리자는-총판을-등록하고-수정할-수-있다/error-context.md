# Page snapshot

```yaml
- text: 최고관리자 최고관리자님 로그인 중
- link "로그아웃":
  - /url: http://localhost:8001/bbs/logout.php
- link "본문 바로가기":
  - /url: "#container"
- banner:
  - heading "도매까" [level=1]
  - button "메뉴"
  - link "도매까 관리자":
    - /url: http://localhost:8001/adm/
    - img "도매까 관리자"
  - list:
    - listitem:
      - button " 본사 메뉴열기"
  - navigation:
    - heading "관리자 주메뉴" [level=2]
    - list:
      - listitem:
        - button "환경설정"
      - listitem:
        - button "프랜차이즈 관리"
        - heading "프랜차이즈 관리" [level=3]
        - list:
          - listitem:
            - link "총판관리 ":
              - /url: http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php
          - listitem:
            - link "대리점관리 ":
              - /url: http://localhost:8001/dmk/adm/agency_admin/agency_list.php
          - listitem:
            - link "지점관리 ":
              - /url: http://localhost:8001/dmk/adm/branch_admin/branch_list.php
          - listitem:
            - link "통계분석 ":
              - /url: http://localhost:8001/dmk/adm/statistics/statistics_dashboard.php
          - listitem:
            - link "서브관리자관리 ":
              - /url: http://localhost:8001/dmk/adm/admin_manager/admin_list.php
          - listitem:
            - link "서브관리자권한설정 ":
              - /url: http://localhost:8001/dmk/adm/admin_manager/dmk_auth_list.php
          - listitem:
            - link "계층별메뉴권한설정 ":
              - /url: http://localhost:8001/dmk/adm/admin_manager/menu_config.php
      - listitem:
        - button "회원관리"
      - listitem:
        - button "게시판관리"
      - listitem:
        - button "쇼핑몰관리"
      - listitem:
        - button "쇼핑몰현황/기타"
      - listitem:
        - button "SMS 관리"
- heading "총판 관리" [level=1]
- link "전체목록":
  - /url: /dmk/adm/distributor_admin/distributor_list.php
- text: 전체 4건 검색어
- strong: 필수
- textbox "검색어 필수"
- button "검색"
- link "총판 등록":
  - /url: ./distributor_form.php
- paragraph:
  - strong: 총판 관리
  - text: "• 계층 구조: HEAD(본사) → DISTRUBUTOR(총판) → AGENCY(대리점) → BRANCH(지점) • 총판은 본사 하위의 관리자로서 여러 대리점을 관리합니다. • 각 총판별 관리 대리점 수와 산하 지점 수를 확인할 수 있습니다."
- table "총판 관리 목록":
  - caption: 총판 관리 목록
  - rowgroup:
    - row "총판ID 총판이름 회사명명 이메일 전화번호 관리 대리점 수 관리 지점 수 생성일 상태 관리":
      - columnheader "총판ID":
        - link "총판ID":
          - /url: http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php?sst=m.mb_id&sod=asc&sfl=&stx=&sca=&page=1
      - columnheader "총판이름":
        - link "총판이름":
          - /url: http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php?sst=m.mb_name&sod=asc&sfl=&stx=&sca=&page=1
      - columnheader "회사명명"
      - columnheader "이메일"
      - columnheader "전화번호"
      - columnheader "관리 대리점 수"
      - columnheader "관리 지점 수"
      - columnheader "생성일":
        - link "생성일":
          - /url: http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php?sst=m.mb_datetime&sod=asc&sfl=&stx=&sca=&page=1
      - columnheader "상태":
        - link "상태":
          - /url: http://localhost:8001/dmk/adm/distributor_admin/distributor_list.php?sst=d.dt_status&sod=asc&sfl=&stx=&sca=&page=1
      - columnheader "관리"
  - rowgroup:
    - row "DT_TEST_175129982987 테스트총판_1751299829878 테스트회사_1751299829878 0개 0개 2025-07-01 활성 수정 대리점관리":
      - cell "DT_TEST_175129982987":
        - link "DT_TEST_175129982987":
          - /url: http://localhost:8001/adm/member_form.php?w=u&mb_id=DT_TEST_175129982987
      - cell "테스트총판_1751299829878"
      - cell "테스트회사_1751299829878"
      - cell
      - cell
      - cell "0개":
        - link "0개":
          - /url: ../agency_admin/agency_list.php?distributor_id=DT_TEST_175129982987
      - cell "0개":
        - link "0개":
          - /url: ../branch_admin/branch_list.php?distributor_id=DT_TEST_175129982987
      - cell "2025-07-01"
      - cell "활성"
      - cell "수정 대리점관리":
        - link "수정":
          - /url: ./distributor_form.php?w=u&mb_id=DT_TEST_175129982987
        - link "대리점관리":
          - /url: ../agency_admin/agency_list.php?distributor_id=DT_TEST_175129982987
    - row "d_distributor_02 d_distributor_02 d_distributor_02d 0개 0개 2025-06-28 활성 수정 대리점관리":
      - cell "d_distributor_02":
        - link "d_distributor_02":
          - /url: http://localhost:8001/adm/member_form.php?w=u&mb_id=d_distributor_02
      - cell "d_distributor_02"
      - cell "d_distributor_02d"
      - cell
      - cell
      - cell "0개":
        - link "0개":
          - /url: ../agency_admin/agency_list.php?distributor_id=d_distributor_02
      - cell "0개":
        - link "0개":
          - /url: ../branch_admin/branch_list.php?distributor_id=d_distributor_02
      - cell "2025-06-28"
      - cell "활성"
      - cell "수정 대리점관리":
        - link "수정":
          - /url: ./distributor_form.php?w=u&mb_id=d_distributor_02
        - link "대리점관리":
          - /url: ../agency_admin/agency_list.php?distributor_id=d_distributor_02
    - row "d_distributor_01 d_distributor_01 d_distributor_01 2개 3개 2025-06-28 활성 수정 대리점관리":
      - cell "d_distributor_01":
        - link "d_distributor_01":
          - /url: http://localhost:8001/adm/member_form.php?w=u&mb_id=d_distributor_01
      - cell "d_distributor_01"
      - cell "d_distributor_01"
      - cell
      - cell
      - cell "2개":
        - link "2개":
          - /url: ../agency_admin/agency_list.php?distributor_id=d_distributor_01
      - cell "3개":
        - link "3개":
          - /url: ../branch_admin/branch_list.php?distributor_id=d_distributor_01
      - cell "2025-06-28"
      - cell "활성"
      - cell "수정 대리점관리":
        - link "수정":
          - /url: ./distributor_form.php?w=u&mb_id=d_distributor_01
        - link "대리점관리":
          - /url: ../agency_admin/agency_list.php?distributor_id=d_distributor_01
    - row "domaeka 도매까 본사 도매까본사 help@domaeka.com 010-1234-4322 4개 3개 2025-06-24 활성 수정 대리점관리":
      - cell "domaeka":
        - link "domaeka":
          - /url: http://localhost:8001/adm/member_form.php?w=u&mb_id=domaeka
      - cell "도매까 본사"
      - cell "도매까본사"
      - cell "help@domaeka.com"
      - cell "010-1234-4322"
      - cell "4개":
        - link "4개":
          - /url: ../agency_admin/agency_list.php?distributor_id=domaeka
      - cell "3개":
        - link "3개":
          - /url: ../branch_admin/branch_list.php?distributor_id=domaeka
      - cell "2025-06-24"
      - cell "활성"
      - cell "수정 대리점관리":
        - link "수정":
          - /url: ./distributor_form.php?w=u&mb_id=domaeka
        - link "대리점관리":
          - /url: ../agency_admin/agency_list.php?distributor_id=domaeka
- contentinfo:
  - paragraph:
    - text: Copyright © localhost:8001. All rights reserved. YoungCart Version 5.4.5.5.1
    - button "TOP"
```