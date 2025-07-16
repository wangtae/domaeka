/**
 * 이모티콘 표시 문제 해결
 * 이미 '?'로 저장된 데이터는 원본 데이터가 손실되어 복구 불가능
 * 향후 입력되는 데이터는 정상적으로 저장됩니다.
 */

-- 예시: 테스트용 이모티콘 데이터 입력
-- INSERT INTO kb_schedule (title, message_text, created_by_type, created_by_id, created_by_mb_id, target_bot_name, target_room_id, schedule_type, schedule_time, valid_from, valid_until)
-- VALUES ('이모티콘 테스트', '🎉 이모티콘 테스트 🎊 😊 💕', 'branch', 'BR001', 'admin', 'LOA.i', 'test_room', 'once', '10:00:00', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH));

-- 데이터베이스 연결 문자셋 확인
-- SET NAMES utf8mb4;
-- SET CHARACTER SET utf8mb4;
-- SET character_set_connection=utf8mb4;
-- SET collation_connection=utf8mb4_unicode_ci;