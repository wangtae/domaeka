-- 008_add_stock_message_templates.sql
-- dmk_branch 테이블에 품절 관련 메시지 템플릿 필드 추가

-- 품절 임박 메시지 템플릿 필드 추가
ALTER TABLE `dmk_branch` 
ADD COLUMN `br_stock_warning_msg_template` TEXT DEFAULT NULL COMMENT '품절 임박 메시지 템플릿' AFTER `br_order_msg_template`;

-- 품절 메시지 템플릿 필드 추가
ALTER TABLE `dmk_branch`
ADD COLUMN `br_stock_out_msg_template` TEXT DEFAULT NULL COMMENT '품절 메시지 템플릿' AFTER `br_stock_warning_msg_template`;

-- 품절 임박 기준 수량 설정 (각 지점별로 다르게 설정 가능)
ALTER TABLE `dmk_branch`
ADD COLUMN `br_stock_warning_qty` INT(11) DEFAULT 10 COMMENT '품절 임박 기준 수량' AFTER `br_stock_out_msg_template`;

-- 메시지 발송 활성화 여부 필드들 추가
ALTER TABLE `dmk_branch`
ADD COLUMN `br_order_msg_enabled` TINYINT(1) DEFAULT 1 COMMENT '주문 완료 메시지 사용 여부' AFTER `br_stock_warning_qty`,
ADD COLUMN `br_stock_warning_msg_enabled` TINYINT(1) DEFAULT 1 COMMENT '품절 임박 메시지 사용 여부' AFTER `br_order_msg_enabled`,
ADD COLUMN `br_stock_out_msg_enabled` TINYINT(1) DEFAULT 1 COMMENT '품절 메시지 사용 여부' AFTER `br_stock_warning_msg_enabled`;

-- 기본 템플릿 설정 (예시)
UPDATE `dmk_branch` 
SET `br_stock_warning_msg_template` = '[품절임박] {상품명}

현재 재고: {현재재고}개
품절 임박 상품입니다.

빠른 주문 부탁드립니다.'
WHERE `br_stock_warning_msg_template` IS NULL;

UPDATE `dmk_branch`
SET `br_stock_out_msg_template` = '[품절] {상품명}

품절되었습니다.
입고 예정일: {입고예정일}

다른 상품으로 대체 가능합니다.'
WHERE `br_stock_out_msg_template` IS NULL;