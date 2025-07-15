-- 006_fix_kb_rooms_table.sql
-- kb_rooms 테이블에 누락된 필드 추가

-- 1. 누락된 필드 추가
ALTER TABLE kb_rooms 
ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `description`,
ADD COLUMN IF NOT EXISTS `approved_at` DATETIME NULL AFTER `updated_at`;

-- 2. owner_type 필드는 더 이상 사용하지 않음 (owner_id에 지점 ID만 저장)
-- owner_type 필드는 호환성을 위해 유지하지만, 실제로는 사용하지 않음
-- 모든 owner_id는 지점(branch) ID로 간주됨