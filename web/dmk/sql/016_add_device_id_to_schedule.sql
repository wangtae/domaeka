-- kb_schedule 테이블에 device_id 필드 추가
-- bot_name과 device_id를 함께 사용하여 정확한 봇 클라이언트 식별

-- 1. target_device_id 컬럼 추가
ALTER TABLE kb_schedule 
ADD COLUMN target_device_id VARCHAR(100) DEFAULT NULL COMMENT '대상 디바이스 ID' AFTER target_bot_name;

-- 2. 인덱스 추가 (봇명과 디바이스ID 조합으로 빠른 검색)
ALTER TABLE kb_schedule 
ADD INDEX idx_bot_device (target_bot_name, target_device_id);

-- 3. 기존 데이터 업데이트 (필요한 경우)
-- 각 봇의 첫 번째 승인된 디바이스를 기본값으로 설정
UPDATE kb_schedule s
LEFT JOIN (
    SELECT bot_name, device_id
    FROM kb_bot_devices
    WHERE status = 'approved'
    GROUP BY bot_name
    HAVING MIN(id)
) d ON s.target_bot_name = d.bot_name
SET s.target_device_id = d.device_id
WHERE s.target_device_id IS NULL;

-- 4. 스케줄 로그 테이블에도 device_id 추가
ALTER TABLE kb_schedule_logs
ADD COLUMN target_device_id VARCHAR(100) DEFAULT NULL COMMENT '대상 디바이스 ID' AFTER target_room_id;