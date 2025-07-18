-- 지점별 메시지 발송 봇 지정 필드 추가
-- 2025-01-18

-- dmk_branch 테이블에 메시지 발송 봇 정보 필드 추가
ALTER TABLE `dmk_branch` 
ADD COLUMN `br_message_bot_name` VARCHAR(50) DEFAULT NULL COMMENT '메시지 발송 봇 이름' AFTER `br_stock_out_msg_delay`,
ADD COLUMN `br_message_device_id` VARCHAR(100) DEFAULT NULL COMMENT '메시지 발송 봇 디바이스 ID' AFTER `br_message_bot_name`;

-- 인덱스 추가 (봇 검색 성능 향상)
ALTER TABLE `dmk_branch` 
ADD INDEX `idx_message_bot` (`br_message_bot_name`, `br_message_device_id`);