-- Domaeka 프로젝트 - g5_shop_category 테이블 변경 스크립트
-- 작성일: 2024-06-25
-- 목적: g5_shop_category 테이블에 도매까 카테고리 소유권 관리를 위한 컬럼 추가

ALTER TABLE `g5_shop_category`
ADD COLUMN `dmk_ca_owner_type` varchar(10) NOT NULL DEFAULT 'DISTRIBUTOR' COMMENT '카테고리 소유 계층 (DISTRIBUTOR: 총판, AGENCY: 대리점, BRANCH: 지점)' AFTER `ca_10`,
ADD COLUMN `dmk_ca_owner_id` varchar(20) DEFAULT NULL COMMENT '카테고리 소유 계층 ID (총판인 경우 NULL)' AFTER `dmk_ca_owner_type`;

-- g5_shop_category 테이블 인덱스 추가
ALTER TABLE `g5_shop_category`
ADD INDEX `idx_dmk_ca_owner` (`dmk_ca_owner_type`, `dmk_ca_owner_id`),
ADD INDEX `idx_dmk_ca_owner_type` (`dmk_ca_owner_type`); 