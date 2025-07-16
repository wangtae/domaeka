-- 테스트용 스케줄 데이터 삽입
-- 실행 전에 kb_schedule 테이블이 생성되어 있어야 함

-- 1분 후 실행되는 1회성 테스트 스케줄
INSERT INTO kb_schedule (
    title, description, created_by_type, created_by_id, created_by_mb_id,
    target_bot_name, target_device_id, target_room_id, message_text, message_images_1, message_images_2,
    send_interval_seconds, media_wait_time_1, media_wait_time_2,
    schedule_type, schedule_date, schedule_time,
    valid_from, valid_until, status, next_send_at
) VALUES (
    '테스트 스케줄 - 1분 후 발송',
    '1분 후 한 번만 발송되는 테스트 스케줄',
    'branch',
    'BR001',
    'admin',
    'LOA',  -- 실제 봇 이름으로 변경 필요
    'device_001',  -- 실제 디바이스 ID로 변경 필요
    '12345',  -- 실제 채널 ID로 변경 필요
    '🔔 스케줄 테스트 메시지입니다.\n\n현재 시간: {{NOW}}\n이 메시지는 자동으로 발송되었습니다.',
    '[]',
    '[]',
    1,
    0,
    0,
    'once',
    DATE(NOW()),
    TIME(DATE_ADD(NOW(), INTERVAL 1 MINUTE)),
    NOW(),
    DATE_ADD(NOW(), INTERVAL 1 DAY),
    'active',
    DATE_ADD(NOW(), INTERVAL 1 MINUTE)
);

-- 매일 오전 9시에 발송되는 반복 스케줄
INSERT INTO kb_schedule (
    title, description, created_by_type, created_by_id, created_by_mb_id,
    target_bot_name, target_device_id, target_room_id, message_text, message_images_1, message_images_2,
    send_interval_seconds, media_wait_time_1, media_wait_time_2,
    schedule_type, schedule_time, valid_from, valid_until,
    status, next_send_at
) VALUES (
    '일일 공지사항',
    '매일 오전 9시 정기 공지',
    'branch',
    'BR001',
    'admin',
    'LOA',
    'device_001',  -- 실제 디바이스 ID로 변경 필요
    '12345',
    '☀️ 좋은 아침입니다!\n\n오늘도 좋은 하루 되세요.',
    '[]',
    '[]',
    1,
    0,
    0,
    'daily',
    '09:00:00',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 1 YEAR),
    'active',
    CASE 
        WHEN TIME(NOW()) < '09:00:00' THEN DATE_FORMAT(NOW(), '%Y-%m-%d 09:00:00')
        ELSE DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 DAY), '%Y-%m-%d 09:00:00')
    END
);

-- 매주 월요일 오전 10시에 발송되는 주간 스케줄
INSERT INTO kb_schedule (
    title, description, created_by_type, created_by_id, created_by_mb_id,
    target_bot_name, target_device_id, target_room_id, message_text, message_images_1, message_images_2,
    send_interval_seconds, media_wait_time_1, media_wait_time_2,
    schedule_type, schedule_time, schedule_weekdays,
    valid_from, valid_until, status, next_send_at
) VALUES (
    '주간 리포트',
    '매주 월요일 주간 리포트 발송',
    'branch',
    'BR001',
    'admin',
    'LOA',
    'device_001',  -- 실제 디바이스 ID로 변경 필요
    '12345',
    '📊 주간 리포트\n\n이번 주도 열심히 일합시다!',
    '[]',
    '[]',
    1,
    0,
    0,
    'weekly',
    '10:00:00',
    'monday',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 1 YEAR),
    'active',
    -- 다음 월요일 10시 계산
    DATE_FORMAT(
        DATE_ADD(
            DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY),
            INTERVAL IF(WEEKDAY(NOW()) = 0 AND TIME(NOW()) < '10:00:00', 0, 7) DAY
        ),
        '%Y-%m-%d 10:00:00'
    )
);

-- 텍스트와 이미지가 포함된 스케줄 (5분 후 발송)
INSERT INTO kb_schedule (
    title, description, created_by_type, created_by_id, created_by_mb_id,
    target_bot_name, target_device_id, target_room_id, message_text, 
    message_images_1, message_images_2,
    send_interval_seconds, media_wait_time_1, media_wait_time_2,
    schedule_type, schedule_date, schedule_time,
    valid_from, valid_until, status, next_send_at
) VALUES (
    '이미지 포함 테스트',
    '텍스트와 이미지를 함께 발송하는 테스트',
    'branch',
    'BR001',
    'admin',
    'LOA',
    'device_001',  -- 실제 디바이스 ID로 변경 필요
    '12345',
    '📸 이미지 테스트 메시지\n\n아래 이미지를 확인해주세요.',
    '[{"name":"test1.jpg","path":"data/schedule/test1.jpg","size":102400}]',
    '[]',
    2,  -- 텍스트와 이미지 사이 2초 대기
    1000,  -- 이미지 발송 후 1초 대기
    0,
    'once',
    DATE(NOW()),
    TIME(DATE_ADD(NOW(), INTERVAL 5 MINUTE)),
    NOW(),
    DATE_ADD(NOW(), INTERVAL 1 DAY),
    'active',
    DATE_ADD(NOW(), INTERVAL 5 MINUTE)
);

-- 스케줄 확인
SELECT 
    id, title, target_bot_name, target_room_id,
    schedule_type, status, next_send_at,
    CASE 
        WHEN next_send_at <= NOW() THEN '발송 대기'
        ELSE CONCAT(TIMESTAMPDIFF(MINUTE, NOW(), next_send_at), '분 후 발송')
    END as send_status
FROM kb_schedule
WHERE status = 'active'
ORDER BY next_send_at;