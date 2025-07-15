-- 카카오봇 테스트 데이터 삽입

-- 1. 봇 디바이스 테스트 데이터
INSERT INTO `kb_bot_devices` (`bot_name`, `device_id`, `ip_address`, `client_type`, `client_version`, `status`, `created_at`, `updated_at`) VALUES
('LOA.i', 'test_device_001', '192.168.1.100', 'MessengerBotR', '3.1.4', 'approved', NOW(), NOW()),
('LOA.i', 'test_device_002', '192.168.1.101', 'MessengerBotR', '3.1.4', 'pending', NOW(), NOW()),
('TestBot', 'test_device_003', '192.168.1.102', 'AutoReply', '2.0.1', 'approved', NOW(), NOW()),
('TestBot2', 'test_device_004', '192.168.1.103', 'MessengerBotR', '3.1.3', 'denied', NOW(), NOW());

-- 2. 채팅방 테스트 데이터
INSERT INTO `kb_rooms` (`room_id`, `room_name`, `bot_name`, `room_concurrency`, `status`, `owner_type`, `owner_id`, `created_at`, `updated_at`) VALUES
-- 지점에 배정된 채팅방
('test_room_001_branch', '테스트 지점 채팅방 1', 'LOA.i', 1, 'approved', 'branch', 'BR001', NOW(), NOW()),
('test_room_002_branch', '테스트 지점 채팅방 2', 'LOA.i', 1, 'pending', 'branch', 'BR002', NOW(), NOW()),

-- 대리점에 배정된 채팅방
('test_room_003_agency', '테스트 대리점 채팅방 1', 'TestBot', 1, 'approved', 'agency', 'AG001', NOW(), NOW()),
('test_room_004_agency', '테스트 대리점 채팅방 2', 'TestBot', 1, 'approved', 'agency', 'AG001', NOW(), NOW()),

-- 총판에 배정된 채팅방
('test_room_005_dist', '테스트 총판 채팅방', 'LOA.i', 1, 'approved', 'distributor', 'DT001', NOW(), NOW()),

-- 미배정 채팅방
('test_room_006_none', '미배정 채팅방 1', 'TestBot2', 1, 'pending', NULL, NULL, NOW(), NOW()),
('test_room_007_none', '미배정 채팅방 2', 'LOA.i', 1, 'denied', NULL, NULL, NOW(), NOW());

-- 3. 로그 설정이 있는 채팅방 업데이트
UPDATE `kb_rooms` 
SET `log_settings` = '{"enabled":true,"retention_days":30}' 
WHERE `room_id` IN ('test_room_001_branch', 'test_room_003_agency');

UPDATE `kb_rooms` 
SET `log_settings` = '{"enabled":false,"retention_days":7}' 
WHERE `room_id` = 'test_room_002_branch';

-- 4. 방장 정보 업데이트
UPDATE `kb_rooms` 
SET `room_owners` = '["홍길동","김철수","이영희"]' 
WHERE `room_id` = 'test_room_001_branch';

UPDATE `kb_rooms` 
SET `room_owners` = '["박민수","최지원"]' 
WHERE `room_id` = 'test_room_003_agency';

-- 5. 설명 추가
UPDATE `kb_rooms` 
SET `description` = '지점 운영팀 전용 채팅방' 
WHERE `room_id` = 'test_room_001_branch';

UPDATE `kb_rooms` 
SET `description` = '대리점 영업팀 채팅방' 
WHERE `room_id` = 'test_room_003_agency';

UPDATE `kb_rooms` 
SET `rejection_reason` = '테스트용 거부 사유' 
WHERE `room_id` = 'test_room_007_none';