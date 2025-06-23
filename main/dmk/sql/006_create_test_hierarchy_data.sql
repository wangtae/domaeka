-- Domaeka 프로젝트 - 계층 구조 테스트 데이터 생성 스크립트
-- 작성일: 2024-01-20
-- 목적: 영카트 최고관리자 > 총판 > 대리점 > 지점 계층 구조 테스트 데이터 생성

-- ============================================================================
-- 주의사항
-- 1. 이 스크립트는 관리자가 직접 SQL을 실행하여 사용하거나
-- 2. PHP 스크립트를 통해 그누보드5 표준 암호화 방식으로 실행해야 합니다.
-- 3. PASSWORD() 함수는 MySQL 방식이므로 사용하지 않습니다.
-- ============================================================================

-- 1. 대리점 정보 데이터
INSERT INTO dmk_agency (
    ag_id, ag_name, ag_ceo_name, ag_phone, ag_address, ag_mb_id, ag_datetime, ag_status
) VALUES 
('AG001', '서울중앙대리점', '김대표', '02-1234-5678', '서울특별시 강남구 테헤란로 123', 'distributor1', NOW(), 1),
('AG002', '부산남부대리점', '이대표', '051-9876-5432', '부산광역시 해운대구 센텀로 456', 'distributor2', NOW(), 1);

-- 2. 지점 정보 데이터  
INSERT INTO dmk_branch (
    br_id, ag_id, br_name, br_ceo_name, br_phone, br_address, br_mb_id, br_datetime, br_status
) VALUES 
('BR001', 'AG001', '강남1호점', '박점장', '02-2345-6789', '서울특별시 강남구 강남대로 789', 'agency1', NOW(), 1),
('BR002', 'AG001', '강남2호점', '최점장', '02-3456-7890', '서울특별시 강남구 논현로 321', 'agency2', NOW(), 1),
('BR003', 'AG002', '해운대점', '정점장', '051-8765-4321', '부산광역시 해운대구 해운대해변로 654', 'branch1', NOW(), 1);

-- 3. 총판 관리자 계정 생성 (dmk_mb_type = 1)
-- 주의: 비밀번호는 별도로 그누보드5 관리자 페이지에서 설정해야 합니다.
INSERT INTO g5_member (
    mb_id, mb_password, mb_name, mb_nick, mb_email, mb_level, mb_datetime, mb_ip,
    dmk_mb_type, dmk_ag_id, dmk_br_id, mb_email_certify, mb_mailling, mb_open
) VALUES 
('distributor1', '!', '총판1', '총판1', 'distributor1@domaeka.com', 9, NOW(), '127.0.0.1', 1, NULL, NULL, NOW(), 1, 1),
('distributor2', '!', '총판2', '총판2', 'distributor2@domaeka.com', 9, NOW(), '127.0.0.1', 1, NULL, NULL, NOW(), 1, 1);

-- 4. 대리점 관리자 계정 생성 (dmk_mb_type = 2)
INSERT INTO g5_member (
    mb_id, mb_password, mb_name, mb_nick, mb_email, mb_level, mb_datetime, mb_ip,
    dmk_mb_type, dmk_ag_id, dmk_br_id, mb_email_certify, mb_mailling, mb_open
) VALUES 
('agency1', '!', '대리점1', '대리점1', 'agency1@domaeka.com', 8, NOW(), '127.0.0.1', 2, 'AG001', NULL, NOW(), 1, 1),
('agency2', '!', '대리점2', '대리점2', 'agency2@domaeka.com', 8, NOW(), '127.0.0.1', 2, 'AG002', NULL, NOW(), 1, 1);

-- 5. 지점 관리자 계정 생성 (dmk_mb_type = 3)
INSERT INTO g5_member (
    mb_id, mb_password, mb_name, mb_nick, mb_email, mb_level, mb_datetime, mb_ip,
    dmk_mb_type, dmk_ag_id, dmk_br_id, mb_email_certify, mb_mailling, mb_open
) VALUES 
('branch1', '!', '지점1', '지점1', 'branch1@domaeka.com', 7, NOW(), '127.0.0.1', 3, 'AG001', 'BR001', NOW(), 1, 1),
('branch2', '!', '지점2', '지점2', 'branch2@domaeka.com', 7, NOW(), '127.0.0.1', 3, 'AG001', 'BR002', NOW(), 1, 1),
('branch3', '!', '지점3', '지점3', 'branch3@domaeka.com', 7, NOW(), '127.0.0.1', 3, 'AG002', 'BR003', NOW(), 1, 1);

-- 6. 대리점 정보 업데이트 (올바른 관리자 연결)
UPDATE dmk_agency SET ag_mb_id = 'agency1' WHERE ag_id = 'AG001';
UPDATE dmk_agency SET ag_mb_id = 'agency2' WHERE ag_id = 'AG002';

-- 7. 지점 정보 업데이트 (올바른 관리자 연결)
UPDATE dmk_branch SET br_mb_id = 'branch1' WHERE br_id = 'BR001';
UPDATE dmk_branch SET br_mb_id = 'branch2' WHERE br_id = 'BR002';
UPDATE dmk_branch SET br_mb_id = 'branch3' WHERE br_id = 'BR003';

-- ============================================================================
-- 실행 후 확인 사항
-- 1. 총판관리에서 총판 목록이 보이는지 확인
-- 2. 대리점관리에서 대리점 목록이 보이는지 확인  
-- 3. 지점관리에서 지점 목록이 보이는지 확인
-- 4. 각 계층별 관리자 계정의 비밀번호를 그누보드5 관리자 페이지에서 설정
-- ============================================================================ 