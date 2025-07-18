-- 지점 주문 페이지 스킨 관련 필드 추가
-- 2025-01-18

-- dmk_branch 테이블에 주문페이지 스킨 필드 추가
ALTER TABLE `dmk_branch` 
ADD COLUMN `br_order_page_skin` VARCHAR(50) DEFAULT 'basic' COMMENT '주문페이지 스킨' AFTER `br_message_device_id`,
ADD COLUMN `br_order_page_skin_options` TEXT COMMENT '주문페이지 스킨 옵션 (JSON)' AFTER `br_order_page_skin`;

-- 기존 br_order_page_layout 필드가 있다면 마이그레이션
-- UPDATE `dmk_branch` 
-- SET `br_order_page_skin_options` = CONCAT('{"layout":"', br_order_page_layout, '"}')
-- WHERE `br_order_page_layout` IS NOT NULL;