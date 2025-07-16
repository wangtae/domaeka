/**
 * UTF-8 인코딩 설정 확인 및 수정
 * 이모티콘 등 특수문자 저장을 위한 설정
 */

-- kb_schedule 테이블의 message_text 컬럼 문자셋 확인 및 수정
ALTER TABLE kb_schedule 
MODIFY COLUMN message_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '텍스트 메시지';

-- 테이블 전체 문자셋 확인
ALTER TABLE kb_schedule 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 인덱스 재생성 (필요시)
-- ALTER TABLE kb_schedule DROP INDEX idx_schedule_polling;
-- ALTER TABLE kb_schedule ADD INDEX idx_schedule_polling (status, next_send_at, valid_from, valid_until);