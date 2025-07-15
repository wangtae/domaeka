-- kb_rooms 테이블 컬럼 수정 및 추가

-- 1. descryption을 description으로 컬럼명 변경
ALTER TABLE `kb_rooms` 
CHANGE COLUMN `descryption` `description` text DEFAULT NULL COMMENT '설명/메모';

-- 2. 누락된 컬럼 추가
ALTER TABLE `kb_rooms` 
ADD COLUMN `approved_at` datetime DEFAULT NULL COMMENT '승인 일시' AFTER `status`,
ADD COLUMN `rejection_reason` text DEFAULT NULL COMMENT '거부/차단 사유' AFTER `approved_at`;

-- 3. room_owners와 log_settings 컬럼 타입을 JSON으로 변경 (MySQL 5.7+)
ALTER TABLE `kb_rooms` 
MODIFY COLUMN `room_owners` JSON DEFAULT NULL COMMENT '방장 정보 (JSON 배열)',
MODIFY COLUMN `log_settings` JSON DEFAULT NULL COMMENT '로그 설정 (JSON)';

-- 4. status 컬럼에 누락된 값 확인
-- 이미 올바른 enum 값을 가지고 있으므로 수정 불필요