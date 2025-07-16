/**
 * kb_rooms 테이블 collation 수정
 * kb_schedule과의 JOIN 시 collation 충돌 해결
 */

-- kb_rooms 테이블의 문자셋과 collation을 utf8mb4_unicode_ci로 통일
ALTER TABLE kb_rooms 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 특정 컬럼들의 collation도 명시적으로 변경
ALTER TABLE kb_rooms 
MODIFY COLUMN room_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '카카오톡 방/channelId',
MODIFY COLUMN bot_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '봇 그룹/구분용(최상위 키, 필수)',
MODIFY COLUMN room_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '방 이름',
MODIFY COLUMN owner_type ENUM('distributor','agency','branch') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '소유자 타입',
MODIFY COLUMN owner_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '소유자 ID',
MODIFY COLUMN status ENUM('pending','approved','denied','revoked','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
MODIFY COLUMN description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;