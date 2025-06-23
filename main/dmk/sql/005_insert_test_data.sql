-- Domaeka 프로젝트 - 테스트 데이터 생성 스크립트
-- 작성일: 2024-01-20
-- 목적: 본사-대리점-지점 계층 구조 테스트를 위한 샘플 데이터 생성

-- ============================================================================
-- 중요: 이 스크립트 실행 후 반드시 004_update_admin_passwords.php를 실행하세요!
-- PASSWORD() 함수는 MySQL 방식이므로 그누보드5 표준 방식으로 변경이 필요합니다.
-- ============================================================================

-- 1. 대리점 정보 데이터
INSERT INTO dmk_agency (
    ag_id, ag_name, ag_ceo_name, ag_phone, ag_address, ag_mb_id, ag_datetime, ag_status
) VALUES 
('AG001', '서울중앙대리점', '김대표', '02-1234-5678', '서울특별시 강남구 테헤란로 123', 'agency_admin1', NOW(), 1),
('AG002', '부산남부대리점', '이대표', '051-9876-5432', '부산광역시 해운대구 센텀로 456', 'agency_admin2', NOW(), 1);

-- 2. 지점 정보 데이터  
INSERT INTO dmk_branch (
    br_id, ag_id, br_name, br_ceo_name, br_phone, br_address, br_mb_id, br_datetime, br_status
) VALUES 
('BR001', 'AG001', '강남1호점', '박점장', '02-2345-6789', '서울특별시 강남구 강남대로 789', 'branch_admin1', NOW(), 1),
('BR002', 'AG001', '강남2호점', '최점장', '02-3456-7890', '서울특별시 강남구 논현로 321', 'branch_admin2', NOW(), 1),
('BR003', 'AG002', '해운대점', '정점장', '051-8765-4321', '부산광역시 해운대구 해운대해변로 654', 'branch_admin3', NOW(), 1);

-- 3. 테스트용 관리자 계정 생성
-- 주의: PASSWORD() 함수는 MySQL 방식이므로, 생성 후 004_update_admin_passwords.php 스크립트로 
--       그누보드5 표준 방식(get_encrypt_string)으로 변경해야 합니다.
INSERT INTO g5_member (
    mb_id, mb_password, mb_name, mb_nick, mb_email, mb_level, mb_datetime, mb_ip,
    dmk_mb_type, dmk_ag_id, dmk_br_id, mb_email_certify
) VALUES 
-- 대리점 관리자들
('agency_admin1', PASSWORD('1234'), '김대표', '김대표', 'agency1@test.com', 10, NOW(), '127.0.0.1', 2, 'AG001', '', NOW()),
('agency_admin2', PASSWORD('1234'), '이대표', '이대표', 'agency2@test.com', 10, NOW(), '127.0.0.1', 2, 'AG002', '', NOW()),
-- 지점 관리자들  
('branch_admin1', PASSWORD('1234'), '박점장', '박점장', 'branch1@test.com', 10, NOW(), '127.0.0.1', 3, 'AG001', 'BR001', NOW()),
('branch_admin2', PASSWORD('1234'), '최점장', '최점장', 'branch2@test.com', 10, NOW(), '127.0.0.1', 3, 'AG001', 'BR002', NOW()),
('branch_admin3', PASSWORD('1234'), '정점장', '정점장', 'branch3@test.com', 10, NOW(), '127.0.0.1', 3, 'AG002', 'BR003', NOW());

-- 4. 테스트용 상품 데이터 (상품 소유권 테스트용)
INSERT INTO g5_shop_item (
    it_id, it_name, it_cust_price, it_price, it_basic, it_maker, it_origin, it_brand, 
    it_model, it_weight, it_img1, it_img2, it_img3, it_time, it_update_time, it_sum_qty, 
    it_use, it_sell_use, it_stock_qty, it_point, it_point_type, it_supply_point, 
    dmk_it_owner_type, dmk_it_owner_id, ca_id, ca_id2, ca_id3
) VALUES 
-- 본사 소유 상품
('ITEM001', '본사 전용상품A', 10000, 8000, 1, '본사제조', '한국', '도매까', 'DMK-001', '1.0', '', '', '', NOW(), NOW(), 100, 1, 1, 100, 0, 1, 0, 'HQ', '', '', '', ''),
-- 대리점 소유 상품들
('ITEM002', '서울중앙대리점 상품B', 15000, 12000, 1, '서울제조', '한국', '서울브랜드', 'SEL-002', '1.5', '', '', '', NOW(), NOW(), 50, 1, 1, 50, 0, 1, 0, 'AGENCY', 'AG001', '', '', ''),
('ITEM003', '부산남부대리점 상품C', 20000, 16000, 1, '부산제조', '한국', '부산브랜드', 'BSN-003', '2.0', '', '', '', NOW(), NOW(), 30, 1, 1, 30, 0, 1, 0, 'AGENCY', 'AG002', '', '', ''),
-- 지점 소유 상품들
('ITEM004', '강남1호점 상품D', 12000, 9600, 1, '강남제조', '한국', '강남브랜드', 'GN1-004', '1.2', '', '', '', NOW(), NOW(), 20, 1, 1, 20, 0, 1, 0, 'BRANCH', 'BR001', '', '', ''),
('ITEM005', '해운대점 상품E', 18000, 14400, 1, '해운대제조', '한국', '해운대브랜드', 'HND-005', '1.8', '', '', '', NOW(), NOW(), 15, 1, 1, 15, 0, 1, 0, 'BRANCH', 'BR003', '', '', '');

-- 5. 완료 메시지
SELECT '테스트 데이터 생성 완료!' as message;
SELECT '다음 단계: 004_update_admin_passwords.php 스크립트를 실행하여 패스워드를 그누보드5 방식으로 변경하세요.' as next_step; 