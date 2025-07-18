-- Domaeka 프로젝트 - 주문 테이블에 계층 ID 추가
-- 작성일: 2025-01-18
-- 목적: 주문별 통계를 위한 총판/대리점 ID 필드 추가

-- g5_shop_order 테이블에 계층 구조 ID 추가
ALTER TABLE `g5_shop_order`
ADD COLUMN `dmk_od_ag_id` varchar(20) DEFAULT NULL COMMENT '대리점 ID' AFTER `dmk_od_br_id`,
ADD COLUMN `dmk_od_dt_id` varchar(20) DEFAULT NULL COMMENT '총판 ID' AFTER `dmk_od_ag_id`;

-- 인덱스 추가 (통계 조회 성능 향상)
ALTER TABLE `g5_shop_order`
ADD INDEX `idx_dmk_hierarchy` (`dmk_od_dt_id`, `dmk_od_ag_id`, `dmk_od_br_id`);

-- 기존 데이터 업데이트 (기존 주문에 대한 계층 정보 보정)
UPDATE g5_shop_order o
JOIN dmk_branch b ON o.dmk_od_br_id = b.br_id
JOIN dmk_agency a ON b.ag_id = a.ag_id
JOIN dmk_distributor d ON a.dt_id = d.dt_id
SET 
    o.dmk_od_ag_id = a.ag_id,
    o.dmk_od_dt_id = d.dt_id
WHERE o.dmk_od_br_id IS NOT NULL AND o.dmk_od_br_id != '';