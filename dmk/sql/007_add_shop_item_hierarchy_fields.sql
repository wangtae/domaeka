-- 도매까 프로젝트 - 상품 테이블 계층 필드 추가
-- 작성일: 2025-01-02  
-- 목적: g5_shop_item 테이블에 새로운 계층 필드 추가

-- g5_shop_item 테이블에 새로운 계층 필드 추가
ALTER TABLE `g5_shop_item`
ADD COLUMN `dmk_dt_id` varchar(20) DEFAULT NULL COMMENT '소속 총판 ID' AFTER `dmk_it_owner_id`,
ADD COLUMN `dmk_ag_id` varchar(20) DEFAULT NULL COMMENT '소속 대리점 ID' AFTER `dmk_dt_id`,
ADD COLUMN `dmk_br_id` varchar(20) DEFAULT NULL COMMENT '소속 지점 ID' AFTER `dmk_ag_id`;

-- 새로운 필드에 대한 인덱스 추가
ALTER TABLE `g5_shop_item`
ADD INDEX `idx_dmk_dt_id` (`dmk_dt_id`),
ADD INDEX `idx_dmk_ag_id` (`dmk_ag_id`),
ADD INDEX `idx_dmk_br_id` (`dmk_br_id`),
ADD INDEX `idx_dmk_hierarchy` (`dmk_dt_id`, `dmk_ag_id`, `dmk_br_id`);

-- 기존 dmk_it_owner_type, dmk_it_owner_id 데이터를 새로운 필드로 마이그레이션
-- 주의: 이 스크립트는 기존 데이터가 있는 경우에만 실행

-- 총판 상품 마이그레이션
UPDATE `g5_shop_item` 
SET `dmk_dt_id` = `dmk_it_owner_id`
WHERE `dmk_it_owner_type` = 'distributor' AND `dmk_it_owner_id` IS NOT NULL;

-- 대리점 상품 마이그레이션  
UPDATE `g5_shop_item` 
SET `dmk_ag_id` = `dmk_it_owner_id`
WHERE `dmk_it_owner_type` = 'agency' AND `dmk_it_owner_id` IS NOT NULL;

-- 지점 상품 마이그레이션
UPDATE `g5_shop_item`
SET `dmk_br_id` = `dmk_it_owner_id` 
WHERE `dmk_it_owner_type` = 'branch' AND `dmk_it_owner_id` IS NOT NULL;

-- 계층별 상품에서 상위 계층 정보도 채워넣기
-- 지점 상품의 경우 대리점, 총판 정보도 채워넣기
UPDATE `g5_shop_item` si
JOIN `g5_member` m ON si.dmk_br_id = m.mb_id
SET si.dmk_ag_id = m.dmk_ag_id, si.dmk_dt_id = m.dmk_dt_id
WHERE si.dmk_br_id IS NOT NULL AND m.dmk_ag_id IS NOT NULL;

-- 대리점 상품의 경우 총판 정보도 채워넣기  
UPDATE `g5_shop_item` si
JOIN `g5_member` m ON si.dmk_ag_id = m.mb_id
SET si.dmk_dt_id = m.dmk_dt_id
WHERE si.dmk_ag_id IS NOT NULL AND si.dmk_dt_id IS NULL AND m.dmk_dt_id IS NOT NULL;