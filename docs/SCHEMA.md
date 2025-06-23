dd# Domaeka 프로젝트 데이터베이스 스키마 문서

이 문서는 Domaeka 프로젝트에서 사용하는 MySQL 데이터베이스(`domaeka`)의 스키마를 설명합니다. Domaeka 프로젝트는 **그누보드 5 (Gnuboard 5) 및 영카트 5 (Youngcart 5) 프레임워크를 기반**으로 구축되어 있으며, 모든 테이블 이름은 `g5_` 접두사를 사용합니다.

## 데이터베이스 개요

`domaeka` 데이터베이스는 웹사이트의 사용자 관리, 게시판, 쇼핑몰 기능, 설정 및 기타 핵심 데이터를 저장합니다. 아래는 주요 테이블에 대한 설명입니다.

## 핵심 테이블 설명

### `g5_member` (회원 정보 테이블)
- **목적**: 웹사이트의 모든 회원 정보를 저장합니다. 사용자 ID, 비밀번호, 이름, 이메일, 등급 등의 기본 회원 정보와 추가적인 개인 정보가 포함됩니다.
- **주요 컬럼**:
    - `mb_id` (VARCHAR): 회원 고유 ID (기본키)
    - `mb_password` (VARCHAR): 비밀번호 (암호화됨)
    - `mb_name` (VARCHAR): 회원 이름
    - `mb_email` (VARCHAR): 이메일 주소
    - `mb_level` (TINYINT): 회원 등급
    - `mb_datetime` (DATETIME): 가입일
    - `mb_hp` (VARCHAR): 휴대폰 번호
    - `mb_zip1`, `mb_zip2`, `mb_addr1`, `mb_addr2`, `mb_addr3`, `mb_addr_jibeon` (VARCHAR): 주소 정보
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 사용자별 고유 식별자로 사용됩니다.

### `g5_shop_item` (상품 정보 테이블)
- **목적**: 쇼핑몰의 모든 상품 정보를 저장합니다. 상품명, 가격, 재고, 설명, 이미지 경로 등이 포함됩니다.
- **주요 컬럼**:
    - `it_id` (VARCHAR): 상품 고유 ID (기본키)
    - `it_name` (VARCHAR): 상품명
    - `it_price` (INT): 판매 가격
    - `it_stock_qty` (INT): 재고 수량
    - `it_content` (TEXT): 상품 상세 설명
    - `it_time` (DATETIME): 상품 등록일
    - `it_img1`, `it_img2`, ... (VARCHAR): 상품 이미지 경로
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 상품별 고유 식별자로 사용됩니다.

### `g5_shop_order` (주문 정보 테이블)
- **목적**: 사용자의 주문 정보를 저장합니다. 주문 번호, 주문자 정보, 총 결제 금액, 주문 상태 등이 포함됩니다.
- **주요 컬럼**:
    - `od_id` (VARCHAR): 주문 고유 ID (기본키)
    - `mb_id` (VARCHAR): 주문한 회원 ID (비회원 주문 시 NULL)
    - `od_name` (VARCHAR): 주문자 이름
    - `od_hp` (VARCHAR): 주문자 휴대폰 번호
    - `od_zip1`, `od_zip2`, `od_addr1`, `od_addr2`, `od_addr3`, `od_addr_jibeon` (VARCHAR): 주문자 주소 정보
    - `od_b_name` (VARCHAR): 수령인 이름
    - `od_b_hp` (VARCHAR): 수령인 휴대폰 번호
    - `od_b_zip1`, `od_b_zip2`, `od_b_addr1`, `od_b_addr2`, `od_b_addr3`, `od_b_addr_jibeon` (VARCHAR): 수령인 주소 정보
    - `od_cart_price` (INT): 상품 총액
    - `od_send_cost` (INT): 배송비
    - `od_settle_price` (INT): 총 결제 금액
    - `od_status` (VARCHAR): 주문 상태 (예: '주문', '입금', '배송', '완료' 등)
    - `od_time` (DATETIME): 주문 일시
    - `od_ip` (VARCHAR): 주문자 IP 주소
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 주문별 고유 식별자로 사용됩니다.

### `g5_auth` (관리자 권한 테이블)
- **목적**: 관리자 계정의 접근 권한을 관리합니다. 특정 모듈이나 기능에 대한 권한 부여에 사용됩니다.
- **주요 컬럼**:
    - `mb_id` (VARCHAR): 회원 고유 ID (기본키의 일부)
    - `au_menu` (VARCHAR): 권한이 부여된 메뉴 (기본키의 일부)
    - `au_auth` (VARCHAR): 부여된 권한 (예: 'r' for read, 'w' for write, 'd' for delete 등)
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 권한 항목별 고유 식별자로 사용됩니다.

### `g5_config` (사이트 설정 테이블)
- **목적**: 웹사이트 전반의 설정 정보를 저장합니다. 사이트명, 관리자 이메일, 약관 등 다양한 설정이 포함됩니다.
- **주요 컬럼**:
    - `cf_id` (INT): 설정 고유 ID (기본키)
    - `cf_title` (VARCHAR): 사이트 제목
    - `cf_admin` (VARCHAR): 최고 관리자 ID
    - `cf_use_email_certify` (TINYINT): 이메일 인증 사용 여부
    - `cf_new_skin` (VARCHAR): 최신글 스킨
    - `cf_memo_send_point` (INT): 쪽지 발송 포인트
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 설정 항목별 고유 식별자로 사용됩니다.

### `g5_board` (게시판 설정 테이블)
- **목적**: 개별 게시판의 설정 정보를 저장합니다. 게시판 ID, 게시판명, 스킨, 접근 권한 등이 포함됩니다.
- **주요 컬럼**:
    - `bo_table` (VARCHAR): 게시판 테이블명 (고유 ID, 기본키)
    - `bo_subject` (VARCHAR): 게시판 제목
    - `bo_skin` (VARCHAR): 게시판 스킨
    - `bo_read_level` (TINYINT): 읽기 권한 레벨
    - `bo_write_level` (TINYINT): 쓰기 권한 레벨
    - `bo_comment_level` (TINYINT): 댓글 쓰기 권한 레벨
    - `id` (BIGINT, AUTO_INCREMENT): 이 테이블의 자동 증가(auto_increment) ID 컬럼입니다. 게시판별 고유 식별자로 사용됩니다.

---
**[Cursor AI 지침]**
- **명명 규칙**: 데이터베이스 테이블의 자동 증가(auto_increment) ID 컬럼은 반드시 `id`로 명명합니다. 이는 해당 테이블의 고유 식별자로서의 `id` 컬럼 명칭이 다른 의미의 고유값 컬럼과 혼동되는 것을 방지하고 일관성을 유지하기 위함입니다.
- **시간 형식**: 시간 관련 데이터는 항상 UTC를 기준으로 ISO 8601 형식('YYYY-MM-DDTHH:mm:ssZ')으로 저장하며, 클라이언트에서는 사용자 로컬 시간대 및 지역화된 형식으로 표시합니다.
- **SQL 문서화**: 모든 SQL 쿼리는 별도의 SQL 문서 파일로 유지하고 관리합니다. 이 문서는 목적별로 구분하고 테이블 및 스키마 정보를 주석으로 포함합니다. 