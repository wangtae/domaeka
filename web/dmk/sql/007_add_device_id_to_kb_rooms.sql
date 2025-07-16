-- 007_add_device_id_to_kb_rooms.sql
-- kb_rooms 테이블에 device_id 필드 추가

-- 1. device_id 필드 추가
ALTER TABLE kb_rooms 
ADD COLUMN IF NOT EXISTS `device_id` VARCHAR(255) NULL AFTER `bot_name` COMMENT '봇 디바이스 ID';

-- 2. device_id 인덱스 추가 (검색 성능 향상)
ALTER TABLE kb_rooms 
ADD INDEX IF NOT EXISTS `idx_device_id` (`device_id`);

-- 3. bot_name과 device_id 복합 인덱스 추가
ALTER TABLE kb_rooms 
ADD INDEX IF NOT EXISTS `idx_bot_device` (`bot_name`, `device_id`);