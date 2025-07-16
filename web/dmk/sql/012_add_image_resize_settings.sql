/**
 * 봇 디바이스에 이미지 리사이징 설정 추가
 */

-- kb_bot_devices 테이블에 이미지 리사이징 설정 컬럼 추가
ALTER TABLE kb_bot_devices 
ADD COLUMN `image_resize_width` INT(11) DEFAULT 900 COMMENT '이미지 리사이징 가로 크기 (픽셀)' AFTER `max_message_size`,
ADD COLUMN `image_resize_enabled` TINYINT(1) DEFAULT 1 COMMENT '이미지 리사이징 활성화 여부' AFTER `image_resize_width`;

-- 기존 봇들의 기본값 설정
UPDATE kb_bot_devices SET image_resize_width = 900, image_resize_enabled = 1 WHERE image_resize_width IS NULL;