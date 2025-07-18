# `g5` 파일 수정 내역 기록

이 문서는 Domaeka 프로젝트 개발 과정에서 기존 그누보드 5 (Gnuboard 5) 및 영카트 5 (Youngcart 5) 프레임워크 파일을 수정한 내역을 기록합니다. 모든 수정은 이 문서에 명시되어야 합니다.

## 수정 내역 목록

### 2024-01-20 main/adm/admin.head.php 수정
-   **수정 파일 경로**: `main/adm/admin.head.php`
-   **수정 일자**: `2024-01-20T12:00:00Z (UTC ISO 8601)`
-   **수정자**: `Domaeka Development Team`
-   **수정 내용 요약**: `도매까 권한 라이브러리 포함 코드 추가`
-   **상세 변경 내역**:
    ```php
    // 기존 코드
    if (!defined('_GNUBOARD_')) {
        exit;
    }
    
    // 추가된 코드
    // 도매까 권한 라이브러리 포함
    if (file_exists(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php')) {
        include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');
    }
    
    $g5_debug['php']['begin_time'] = $begin_time = get_microtime();
    ```
-   **관련 기능**: 관리자 페이지에서 도매까 권한 관리 기능 활성화

### 2024-01-20 main/adm/admin.menu250.dmk.php 신규 생성
-   **수정 파일 경로**: `main/adm/admin.menu250.dmk.php`
-   **수정 일자**: `2024-01-20T12:00:00Z (UTC ISO 8601)`
-   **수정자**: `Domaeka Development Team`
-   **수정 내용 요약**: `도매까 관리 메뉴 정의 파일 신규 생성`
-   **상세 변경 내역**:
    ```php
    // 도매까 관리 메뉴 정의
    $menu['menu250'] = array(
        array('250000', '도매까관리', G5_PATH . '/dmk/adm/agency_admin/agency_list.php', 'dmk'),
        array('250100', '대리점관리', G5_PATH . '/dmk/adm/agency_admin/agency_list.php', 'dmk_agency'),
        array('250200', '지점관리', G5_PATH . '/dmk/adm/branch_admin/branch_list.php', 'dmk_branch'),
        array('250300', '상품관리', G5_ADMIN_URL . '/shop_admin/itemlist.php', 'dmk_item'),
        array('250400', '주문관리', G5_ADMIN_URL . '/shop_admin/orderlist.php', 'dmk_order'),
        array('250500', '재고관리', G5_ADMIN_URL . '/shop_admin/itemstock.php', 'dmk_stock')
    );
    // 권한별 메뉴 필터링 로직 포함
    ```
-   **관련 기능**: 계층별 관리자 메뉴 접근 권한 제어 (`dmk_mb_type` 기반)

## 패스워드 암호화 방식 이슈 및 해결

### 2024-01-20 패스워드 암호화 방식 불일치 문제 발견
-   **문제 설명**: 테스트 데이터 생성 시 MySQL `PASSWORD()` 함수 사용으로 인한 그누보드5 표준 암호화 방식과 불일치
-   **발견된 현상**:
    ```
    기존 admin 계정: sha256:12000:BBz+Yx7... (그누보드5 표준)
    새 관리자 계정들: *A4B6157319038724E35... (MySQL PASSWORD() 방식)
    ```
-   **해결 방법**: 
    1. `004_update_admin_passwords.php` 스크립트 생성
    2. `get_encrypt_string()` 함수를 사용하여 그누보드5 표준 방식으로 변경
    3. `005_insert_test_data.sql`에 주의사항 명시

### 2024-01-20 패스워드 변경 스크립트 생성
-   **생성 파일 경로**: `main/dmk/sql/004_update_admin_passwords.php`
-   **생성 일자**: `2024-01-20T13:00:00Z (UTC ISO 8601)`
-   **생성자**: `Domaeka Development Team`
-   **파일 목적**: MySQL PASSWORD() 방식으로 생성된 패스워드를 그누보드5 표준 방식으로 변경
-   **주요 기능**:
    - 관리자 권한 확인
    - `get_encrypt_string()` 함수를 사용한 올바른 암호화
    - 업데이트 전후 패스워드 형태 비교 표시
    - 시각적 결과 확인 (색상 코딩)

### 2024-01-20 테스트 데이터 생성 스크립트 분리
-   **생성 파일 경로**: `main/dmk/sql/005_insert_test_data.sql`
-   **생성 일자**: `2024-01-20T13:00:00Z (UTC ISO 8601)`
-   **생성자**: `Domaeka Development Team`
-   **파일 목적**: 도매까 프로젝트 테스트를 위한 샘플 데이터 생성
-   **포함 데이터**:
    - 대리점 정보 (AG001, AG002)
    - 지점 정보 (BR001, BR002, BR003)
    - 관리자 계정 (agency_admin1~2, branch_admin1~3)
    - 테스트 상품 (ITEM001~005, 소유권별 분류)
-   **주의사항**: 실행 후 반드시 `004_update_admin_passwords.php` 실행 필요

### 2025-01-18 g5_shop_order 테이블 수정
-   **수정 파일 경로**: 테이블 스키마 (g5_shop_order)
-   **수정 일자**: `2025-01-18T12:00:00Z (UTC ISO 8601)`
-   **수정자**: `Domaeka Development Team`
-   **수정 내용 요약**: `주문별 통계를 위한 계층 ID 필드 추가`
-   **상세 변경 내역**:
    - dmk_od_ag_id 필드 추가 (대리점 ID)
    - dmk_od_dt_id 필드 추가 (총판 ID)
    - 계층별 인덱스 추가
-   **SQL 파일**: `main/dmk/sql/007_add_hierarchy_to_order.sql`
-   **관련 기능**: 총판별, 대리점별 주문 통계 집계 기능

--- 