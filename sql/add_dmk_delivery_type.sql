-- 상품 카테고리에 픽업/배송 상품 설정 필드 추가
-- 기본값은 'delivery'이며, 'pickup', 'delivery' 또는 둘 다 선택 가능

ALTER TABLE g5_shop_category 
ADD COLUMN dmk_delivery_type SET('pickup', 'delivery') NOT NULL DEFAULT 'delivery' 
COMMENT '배송 타입 (pickup: 픽업, delivery: 배송)' 
AFTER ca_nocoupon; 