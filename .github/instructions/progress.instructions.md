---
applyTo: '**'
---
Codi# dmk_distributor, dmk_agency, dmk_branch 테이블 재정의 및 g5_member 필드 활용 규칙

## 1. 테이블 스키마 변경 사항 요약

`dmk_distributor`, `dmk_agency`, `dmk_branch` 테이블의 스키마가 재정의되었습니다. 이에 따라 기존에 이들 테이블에 직접 포함되어 있던 일부 개인 및 연락처 관련 필드들이 삭제되었으며, 관련 정보는 이제 `g5_member` 테이블을 통해 관리됩니다. 이는 데이터 중복을 최소화하고 관리자 계정 정보를 중앙 집중화하기 위함입니다.

## 2. 기존 필드 대체 규칙

재정의된 테이블에서 삭제된 다음 필드들의 정보는 `g5_member` 테이블의 해당 필드로 대체되어 관리됩니다. 기존 구현 코드에서 이러한 필드를 참조하는 경우, `g5_member`의 상응하는 필드로 변경하여 사용해야 합니다.

*   **`dmk_distributor` (총판)**
    *   `dt_name` (총판명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick`
    *   `dt_ceo_name` (총판 대표자명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick` (상황에 따라)
    *   `dt_datetime` -> `g5_member.mb_datetime` 
    *   `dt_phone` (총판 전화번호) -> `g5_member.mb_tel`
    *   `dt_address` (총판 주소) -> `g5_member.mb_zip`, `g5_member.mb_addr1`, `g5_member.mb_addr2`, `g5_member.mb_addr3`, `g5_member.mb_addr_jibeon`

*   **`dmk_agency` (대리점)**
    *   `ag_name` (대리점명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick`
    *   `ag_ceo_name` (대표자명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick` (상황에 따라)
    *   `ag_datetime` -> `g5_member.mb_datetime` 
    *   `ag_phone` (대표 전화번호) -> `g5_member.mb_hp` 또는 `g5_member.mb_tel`
    *   `ag_address` (주소) -> `g5_member.mb_zip`, `g5_member.mb_addr1`, `g5_member.mb_addr2`, `g5_member.mb_addr3`, `g5_member.mb_addr_jibeon`

*   **`dmk_branch` (지점)**
    *   `br_name` (지점명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick`
    *   `br_ceo_name` (지점 대표자명) -> `g5_member.mb_name` 또는 `g5_member.mb_nick` (상황에 따라)
    *   `br_datetime` -> `g5_member.mb_datetime` 
    *   `br_phone` (지점 대표 전화번호) -> `g5_member.mb_hp` 또는 `g5_member.mb_tel`
    *   `br_address` (지점 주소) -> `g5_member.mb_zip`, `g5_member.mb_addr1`, `g5_member.mb_addr2`, `g5_member.mb_addr3`, `g5_member.mb_addr_jibeon`

## 3. `g5_member` 테이블 활용 필드 (계층 관리자 정보)

총판, 대리점, 지점과 같은 계층 관리자 계정을 `g5_member` 테이블에 등록/수정할 때 사용되는 주요 필드는 다음과 같습니다.

*   **계층정보**: 선택박스로 계층정보 표시
*   **아이디**: `mb_id`
*   **비밀번호**: `mb_password` (관리페이지란 특성이 있으므로 최소 8자 이상을 생성하도록 합니다.)
*   **계층이름**: `mb_name` (관리자 계정의 실제 이름)
*   **회사명(닉네임)**: `mb_nick` (총판명, 대리점명, 지점명 등으로 활용)
*   **이메일**: `mb_email`
*   **전화번호**: `mb_tel`
*   **핸드폰번호**: `mb_hp`
*   **주소**: `mb_zip`, `mb_addr1`, `mb_addr2`, `mb_addr3`, `mb_addr_jibeon` (adm/member_form.php의 주속 입력 ui를 사용할 것)
*   **상태**: 각 `dmk_*` 테이블의 `*_status` 필드(`dt_status`, `ag_status`, `br_status`)는 해당 조직의 활성화 상태를 나타내며, `g5_member` 테이블의 `mb_level` 및 `dmk_mb_type` 필드도 사용자 역할 및 접근 권한 상태를 정의하는 데 활용됩니다.


## 4. 메뉴별 진행상황을 별도 문서에 작성

@프로젝트진행상황보고서.md 에 각 메뉴별 구현 상황을 기록해야 합니다.

# 메뉴별 개발 체크리스트 - 상세

이 문서는 각 메뉴 기능 개발 시 수행해야 할 핵심 체크리스트를 상세히 정의합니다.

## 1. 목록 페이지 (`_list.php`) 개발 체크리스트

### 1.1. 계층별 권한 체크 (필수)

-   [ ] `dmk_can_access_menu($sub_menu)` 함수를 사용하여 현재 로그인한 관리자의 메뉴 접근 권한을 확인합니다.
    -   최고관리자는 모든 목록에 접근 가능해야 합니다.
    -   총판/대리점/지점 관리자는 자신의 계층과 관련된 목록만 접근 가능해야 합니다.
-   [ ] `dmk_get_admin_auth()`를 통해 얻은 `$auth` 정보를 사용하여, 최고 관리자가 아닌 경우 해당 계층 관리자가 자신의 하위 데이터만 조회할 수 있도록 SQL 쿼리 조건을 필터링합니다.
    -   예: 총판 관리자는 자신에게 속한 대리점 및 지점 목록만 조회.

### 1.2. 목록 계층별 데이터 필터링 (필수)

-   [ ] SQL 쿼리에서 `dmk_get_member_where_condition()`, `dmk_get_item_where_condition()`, `dmk_get_order_where_condition()` 등 계층별 데이터 필터링 함수를 활용하여 데이터 접근 범위를 제한합니다.
-   [ ] `dmk_mb_type` (회원 유형) 필드를 활용하여 정확한 계층별 데이터를 조회하는지 확인합니다.

### 1.3. 목록 UI 체크 (필수)

-   [ ] 페이지 제목(`$g5['title']`)이 명확하고 사용자에게 혼란을 주지 않는지 확인합니다.
-   [ ] 검색 필드(`stx`)가 올바르게 동작하고, 검색 결과가 정확히 필터링되는지 확인합니다.
-   [ ] 페이지네이션(`get_paging`)이 올바르게 동작하고, 총 건수가 정확히 표시되는지 확인합니다.
-   [ ] 목록 테이블의 컬럼들이 데이터를 명확하게 보여주는지 확인합니다.
-   [ ] 데이터가 없을 때 "자료가 없습니다." 메시지가 올바르게 표시되는지 확인합니다.

## 2. 폼 페이지 (`_form.php`) 개발 체크리스트

### 2.1. 폼 계층별 권한 체크 (필수)

-   [ ] `dmk_authenticate_form_access($menu_code, $w, $target_id)` 함수를 사용하여 폼 페이지 접근 권한을 통합적으로 관리합니다.
    -   등록 모드(`$w == ''` 또는 `$w == 'c'`)일 경우: `dmk_can_access_menu()`를 통해 일반 메뉴 접근 권한을 확인합니다.
    -   수정 모드(`$w == 'u'`)일 경우: `dmk_can_modify_` + 엔티티명(`distributor`, `agency`, `branch` 등) 함수를 동적으로 호출하여 해당 엔티티 수정 권한을 확인합니다.
-   [ ] 최고관리자는 모든 폼 페이지에 접근 가능해야 합니다.
-   [ ] 총판/대리점/지점 관리자는 자신 또는 자신의 하위 계층에 해당하는 엔티티의 폼 페이지에만 접근 가능해야 합니다.
-   [ ] 특히, 총판/대리점/지점 관리자가 자신의 정보를 수정할 경우, 민감한 필드(예: 상태 필드)가 숨김 처리되거나 비활성화되는지 확인합니다. (이전 총판 상태 숨김 처리 로직 참고)

### 2.2. 폼 필수 필드 체크 (필수)

-   [ ] 모든 필수 입력 필드(`required` 속성)가 HTML 수준에서 올바르게 적용되어 있는지 확인합니다.
-   [ ] 자바스크립트(`fdistributorform_submit` 등)를 사용하여 클라이언트 측 유효성 검사(비밀번호 길이, 일치 여부 등)가 수행되는지 확인합니다.
-   [ ] 서버 측에서 모든 필수 데이터가 누락되지 않았는지 검증하는 로직을 포함합니다.

### 2.3. 폼 UI/UX 체크 (필수)

-   [ ] 등록(`$is_add = true`) 및 수정(`$is_add = false`) 모드에 따라 폼의 제목, 버튼 텍스트 등이 올바르게 변경되는지 확인합니다.
-   [ ] 주소 검색(`win_zip`) 등 연동되는 외부 기능이 올바르게 동작하는지 확인합니다.
-   [ ] 사용자에게 혼란을 줄 수 있는 정보(예: 비밀번호 수정 시 공란 안내)가 명확하게 표시되는지 확인합니다.

## 3. 등록/수정 처리 페이지 (`_form_update.php`) 개발 체크리스트

### 3.1. 등록/수정 계층별 권한 체크 (필수)

-   [ ] `dmk_authenticate_form_access($menu_code, $w, $target_id)` 함수를 사용하여 폼 제출 시에도 권한을 다시 한번 확인합니다. (수정 모드일 경우 `target_id` 필수 전달)
-   [ ] 최고관리자는 모든 등록/수정을 할 수 있어야 합니다.
-   [ ] 총판/대리점/지점 관리자는 자신 또는 자신의 하위 계층에 해당하는 엔티티만 등록/수정할 수 있는지 확인합니다.

### 3.2. 등록/수정 데이터 유효성 및 보안 체크 (필수)

-   [ ] 모든 POST 데이터에 대해 `sql_escape_string()` 등 적절한 보안 처리가 적용되었는지 확인합니다.
-   [ ] 비밀번호 등 민감 정보는 `get_encrypt_password()`와 같은 암호화 함수를 사용하여 저장하는지 확인합니다.
-   [ ] 서버 측에서 모든 필수 필드 데이터의 유효성(빈 값, 형식, 길이 등)을 다시 한번 검증합니다.
-   [ ] ID 중복 확인(`$w == ''` 등록 모드) 로직이 올바르게 구현되어 있는지 확인합니다.

### 3.3. 데이터베이스 트랜잭션 및 무결성 (필수)

-   [ ] 여러 테이블에 걸친 데이터 변경(예: `g5_member`와 `dmk_distributor`) 시 데이터 일관성을 보장하기 위해 트랜잭션 처리를 고려합니다.
-   [ ] 데이터베이스 스키마 변경이 필요한 경우, @데이터베이스 마이그레이션 및 스키마 변경 원칙을 철저히 준수합니다.

### 3.4. 관리자 액션 로깅 (필수)

-   [ ] `dmk_log_admin_action()` 함수를 사용하여 모든 등록 및 수정 작업을 상세히 기록합니다.
    -   액션 타입(`insert`, `edit`, `delete`)
    -   액션 설명 (예: "총판 등록", "총판 정보 수정")
    -   대상 정보 (예: "총판ID: [ID]")
    -   변경 전/후 데이터(`json_encode($_POST)`, `null` 또는 이전 데이터)
    -   메뉴 코드
    -   영향받은 테이블명

### 3.5. 후처리 및 리다이렉트 (필수)

-   [ ] 작업 성공 또는 실패 시 사용자에게 명확한 메시지를 제공하고(`alert()`), 적절한 페이지로 리다이렉트(`goto_url()`)하는지 확인합니다.
-   [ ] 에러 발생 시 사용자에게 친화적인 메시지를 표시하고, 내부적으로는 상세 로그를 남기는지 확인합니다. ng standards, domain knowledge, and preferences that AI should follow.