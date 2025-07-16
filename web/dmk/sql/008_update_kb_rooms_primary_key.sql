-- 008_update_kb_rooms_primary_key.sql
-- kb_rooms 테이블의 PRIMARY KEY를 변경하여 봇별 채팅방 구분 가능하도록 수정

-- 1. 기존 PRIMARY KEY 삭제
ALTER TABLE kb_rooms DROP PRIMARY KEY;

-- 2. room_id를 단순 VARCHAR로 변경하고 복합 PRIMARY KEY 생성
-- room_id = channel_id (기존 room_id에서 봇 이름 부분 제거 필요)
-- PRIMARY KEY = (room_id, bot_name, device_id)
ALTER TABLE kb_rooms 
ADD PRIMARY KEY (room_id, bot_name, device_id);

-- 3. 기존 room_id 데이터 마이그레이션 (bot_name 부분 제거)
-- 예: "18445682959392711_LOA.i" -> "18445682959392711"
UPDATE kb_rooms 
SET room_id = SUBSTRING_INDEX(room_id, '_', 1)
WHERE room_id LIKE '%_%';

-- 4. room_id 컬럼 설명 업데이트
ALTER TABLE kb_rooms 
MODIFY COLUMN room_id VARCHAR(100) NOT NULL COMMENT '채팅방 채널 ID';

-- 5. 추가 인덱스 생성 (조회 성능 향상)
ALTER TABLE kb_rooms 
ADD INDEX IF NOT EXISTS idx_room_bot (room_id, bot_name),
ADD INDEX IF NOT EXISTS idx_room_device (room_id, device_id);