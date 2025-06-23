-- Domaeka 프로젝트 - 롤백 스크립트
-- 작성일: 2024-01-20
-- 목적: 도매까 관련 데이터베이스 변경사항을 롤백하기 위한 스크립트

-- 주의: 이 스크립트를 실행하면 도매까 관련 모든 데이터가 삭제됩니다.
-- 운영 환경에서는 반드시 백업 후 실행하세요.

-- 1. g5_shop_order 테이블에서 도매까 컬럼 제거
ALTER TABLE `g5_shop_order`
DROP INDEX `idx_dmk_od_br_id`,
DROP COLUMN `dmk_od_br_id`;

-- 2. g5_shop_item 테이블에서 도매까 컬럼 제거
ALTER TABLE `g5_shop_item`
DROP INDEX `idx_dmk_it_owner`,
DROP INDEX `idx_dmk_it_owner_type`,
DROP COLUMN `dmk_it_owner_id`,
DROP COLUMN `dmk_it_owner_type`;

-- 3. g5_member 테이블에서 도매까 컬럼 제거
ALTER TABLE `g5_member`
DROP INDEX `idx_dmk_mb_type`,
DROP INDEX `idx_dmk_ag_id`,
DROP INDEX `idx_dmk_br_id`,
DROP COLUMN `dmk_br_id`,
DROP COLUMN `dmk_ag_id`,
DROP COLUMN `dmk_mb_type`;

-- 4. 도매까 테이블 삭제 (외래키 제약조건 때문에 순서 중요)
DROP TABLE IF EXISTS `dmk_branch`;
DROP TABLE IF EXISTS `dmk_agency`; 