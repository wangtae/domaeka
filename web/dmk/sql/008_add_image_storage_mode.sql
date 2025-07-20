-- kb_schedule 테이블에 이미지 저장 방식 필드 추가
-- 2025-01-20

-- 이미지 저장 방식 필드 추가
ALTER TABLE kb_schedule 
ADD COLUMN image_storage_mode ENUM('file', 'base64') NOT NULL DEFAULT 'file' 
COMMENT '이미지 저장 방식' 
AFTER message_thumbnails_2;

-- 기존 데이터 업데이트
-- message_images_1 또는 message_images_2에 base64 데이터가 있는 경우 base64로 설정
UPDATE kb_schedule 
SET image_storage_mode = 'base64' 
WHERE (message_images_1 LIKE '%"base64":%' OR message_images_2 LIKE '%"base64":%');