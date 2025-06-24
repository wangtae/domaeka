-- Domaeka 프로젝트 - 기존 테이블 변경 스크립트
-- 작성일: 2024-01-20
-- 목적: 기존 g5 테이블에 도매까 계층 구조 관리를 위한 컬럼 추가

-- 1. g5_member 테이블에 도매까 관련 컬럼 추가
ALTER TABLE `g5_member` 
ADD COLUMN `dmk_mb_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '회원 유형 (0: 일반, 1: 본사, 2: 대리점, 3: 지점)' AFTER `mb_level`,
ADD COLUMN `dmk_ag_id` varchar(20) DEFAULT NULL COMMENT '소속 대리점 ID (대리점 관리자인 경우)' AFTER `dmk_mb_type`,
ADD COLUMN `dmk_br_id` varchar(20) DEFAULT NULL COMMENT '소속 지점 ID (지점 관리자인 경우)' AFTER `dmk_ag_id`;

-- g5_member 테이블 인덱스 추가
ALTER TABLE `g5_member`
ADD INDEX `idx_dmk_mb_type` (`dmk_mb_type`),
ADD INDEX `idx_dmk_ag_id` (`dmk_ag_id`),
ADD INDEX `idx_dmk_br_id` (`dmk_br_id`);

-- 2. g5_shop_item 테이블에 도매까 상품 소유권 관련 컬럼 추가
ALTER TABLE `g5_shop_item`
ADD COLUMN `dmk_it_owner_type` varchar(10) NOT NULL DEFAULT 'HQ' COMMENT '상품 소유 계층 (HQ: 본사, AGENCY: 대리점, BRANCH: 지점)' AFTER `it_tel_inq`,
ADD COLUMN `dmk_it_owner_id` varchar(20) DEFAULT NULL COMMENT '상품 소유 계층 ID (HQ인 경우 NULL)' AFTER `dmk_it_owner_type`;

-- g5_shop_item 테이블 인덱스 추가
ALTER TABLE `g5_shop_item`
ADD INDEX `idx_dmk_it_owner` (`dmk_it_owner_type`, `dmk_it_owner_id`),
ADD INDEX `idx_dmk_it_owner_type` (`dmk_it_owner_type`);

-- 3. g5_shop_order 테이블에 도매까 지점 관련 컬럼 추가
ALTER TABLE `g5_shop_order`
ADD COLUMN `dmk_od_br_id` varchar(20) NOT NULL COMMENT '주문 처리 지점 ID' AFTER `od_test`;

-- g5_shop_order 테이블 인덱스 추가
ALTER TABLE `g5_shop_order`
ADD INDEX `idx_dmk_od_br_id` (`dmk_od_br_id`);

-- Domaeka 프로젝트 - 기존 테이블 구조 변경 스크립트
-- 작성일: 2024-01-20
-- 목적: 대리점/지점 ID와 관리자 ID 통합, 지점 단축 코드 필드 추가

-- 1. 대리점 테이블에서 별도 관리자 ID 필드 제거
-- 주의: 데이터 손실 방지를 위해 실행 전 백업 필수
ALTER TABLE `dmk_agency` 
DROP FOREIGN KEY IF EXISTS `fk_dmk_agency_mb_id`,
DROP INDEX IF EXISTS `uk_dmk_agency_mb_id`,
DROP COLUMN IF EXISTS `ag_mb_id`;

-- 2. 지점 테이블에서 별도 관리자 ID 필드 제거 및 단축 코드 필드 추가
-- 주의: 데이터 손실 방지를 위해 실행 전 백업 필수
ALTER TABLE `dmk_branch` 
DROP FOREIGN KEY IF EXISTS `fk_dmk_branch_mb_id`,
DROP INDEX IF EXISTS `uk_dmk_branch_mb_id`,
DROP COLUMN IF EXISTS `br_mb_id`,
ADD COLUMN `br_shortcut_code` varchar(20) DEFAULT NULL COMMENT 'URL 단축 코드 (8-12자)' AFTER `br_address`,
ADD UNIQUE KEY `uk_dmk_branch_shortcut_code` (`br_shortcut_code`); 