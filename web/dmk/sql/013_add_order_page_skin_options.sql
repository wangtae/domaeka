-- 지점 주문 페이지 스킨 옵션 필드 추가
-- 2025-01-18

-- dmk_branch 테이블에 주문페이지 스킨 옵션 필드 추가
ALTER TABLE `dmk_branch` 
ADD COLUMN `br_order_page_skin_options` TEXT COMMENT '주문페이지 스킨 옵션 (JSON)' AFTER `br_order_page_skin`;