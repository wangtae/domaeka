/**
 * 카카오봇 스케줄링 시스템 테이블 생성
 * 
 * 이 스크립트는 다음 테이블들을 생성합니다:
 * - kb_schedule: 스케줄링 발송 설정
 * - kb_schedule_logs: 스케줄링 발송 로그
 * - kb_bot_server_mapping: 봇-서버 연결 매핑
 */

-- 1. kb_schedule 테이블 생성
CREATE TABLE IF NOT EXISTS `kb_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '스케줄 제목',
  `description` text COMMENT '스케줄 설명',
  
  -- 발송 주체 정보
  `created_by_type` enum('distributor','agency','branch') NOT NULL COMMENT '등록자 타입(총판, 대리점, 지점)',
  `created_by_id` varchar(50) NOT NULL COMMENT '등록자 ID (dt_id, ag_id, br_id)',
  `created_by_mb_id` varchar(50) NOT NULL COMMENT '등록한 관리자 회원 ID',
  
  -- 발송 대상 정보
  `target_bot_name` varchar(30) NOT NULL COMMENT '대상 봇',
  `target_room_id` varchar(100) NOT NULL COMMENT '대상 톡방 ID(channelId)',
  
  -- 메시지 내용
  `message_text` text COMMENT '텍스트 메시지',
  `message_images_1` json COMMENT '이미지 파일 정보 배열 1',
  `message_images_2` json COMMENT '이미지 파일 정보 배열 2',
  `send_interval_seconds` int(5) DEFAULT 1 COMMENT '메시지간 발송 간격(초)',
  `media_wait_time_1` int(5) DEFAULT 1 COMMENT 'message_images_1 이미지 대기 시간(ms)',
  `media_wait_time_2` int(5) DEFAULT 1 COMMENT 'message_images_2 이미지 대기 시간(ms)',
  
  -- 스케줄링 설정
  `schedule_type` enum('once','weekly','daily') NOT NULL COMMENT '스케줄 타입',
  `schedule_date` date NULL COMMENT '1회성 발송 날짜',
  `schedule_time` time NOT NULL COMMENT '발송 시간',
  `schedule_weekdays` set('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NULL COMMENT '반복 발송 요일',
  
  -- 유효기간 설정
  `valid_from` datetime NOT NULL COMMENT '유효기간 시작',
  `valid_until` datetime NOT NULL COMMENT '유효기간 종료',
  
  -- 상태 관리
  `status` enum('active','inactive','completed','error') DEFAULT 'active' COMMENT '스케줄 상태',
  `last_sent_at` datetime NULL COMMENT '마지막 발송 시간',
  `next_send_at` datetime NULL COMMENT '다음 발송 예정 시간',
  `send_count` int(11) DEFAULT 0 COMMENT '총 발송 횟수',
  
  -- 시스템 필드
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_schedule_polling` (`status`, `next_send_at`, `valid_from`, `valid_until`),
  KEY `idx_created_by` (`created_by_type`, `created_by_id`),
  KEY `idx_target_room` (`target_room_id`, `target_bot_name`),
  KEY `idx_target_bot` (`target_bot_name`, `target_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='카카오봇 스케줄링 발송 설정';

-- 2. kb_schedule_logs 테이블 생성
CREATE TABLE IF NOT EXISTS `kb_schedule_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL COMMENT '스케줄 ID',
  `target_room_id` varchar(100) NOT NULL COMMENT '발송 대상 톡방 ID',
  
  -- 발송 내용
  `sent_message_text` text COMMENT '발송된 텍스트',
  `sent_images_1` json COMMENT '발송된 이미지 그룹 1',
  `sent_images_2` json COMMENT '발송된 이미지 그룹 2',
  `send_components` varchar(50) COMMENT '실제 발송된 구성 요소',
  
  -- 발송 결과
  `status` enum('success','failed','partial') NOT NULL COMMENT '발송 상태',
  `error_message` text COMMENT '오류 메시지',
  `api_response` json COMMENT 'API 응답 데이터',
  
  -- 발송 시간 정보
  `scheduled_at` datetime NOT NULL COMMENT '예정 발송 시간',
  `started_at` datetime NOT NULL COMMENT '발송 시작 시간',
  `completed_at` datetime NULL COMMENT '발송 완료 시간',
  `duration_ms` int(11) COMMENT '발송 소요 시간(밀리초)',
  
  -- 시스템 필드
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `fk_schedule_logs_schedule` (`schedule_id`),
  KEY `idx_target_room_logs` (`target_room_id`),
  KEY `idx_status_time` (`status`, `scheduled_at`),
  CONSTRAINT `fk_schedule_logs_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `kb_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='카카오봇 스케줄링 발송 로그';

-- 3. kb_bot_server_mapping 테이블 생성 (다중 서버 환경 지원)
CREATE TABLE IF NOT EXISTS `kb_bot_server_mapping` (
  `bot_name` varchar(30) NOT NULL,
  `server_id` varchar(50) NOT NULL COMMENT '서버 식별자 (host:port)',
  `connected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_ping_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`bot_name`),
  KEY `idx_server_active` (`server_id`, `is_active`),
  KEY `idx_last_ping` (`last_ping_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='봇-서버 연결 매핑 정보';

-- 4. 기존 kb_servers 테이블에 필요한 컬럼 추가 (이미 테이블이 있다면)
ALTER TABLE `kb_servers` 
  ADD COLUMN IF NOT EXISTS `last_heartbeat` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 하트비트 시간',
  ADD COLUMN IF NOT EXISTS `status` enum('active','inactive','maintenance') DEFAULT 'active' COMMENT '서버 상태',
  ADD KEY IF NOT EXISTS `idx_heartbeat` (`last_heartbeat`, `status`);

-- 5. 초기 데이터 설정
-- 기본 유효기간 설정 (현재 시간부터 1년간)
-- INSERT INTO kb_schedule 예시는 실제 사용 시 참고용

/**
 * 사용 예시:
 * 
 * -- 매일 오전 9시 발송 스케줄
 * INSERT INTO kb_schedule (
 *   title, created_by_type, created_by_id, created_by_mb_id,
 *   target_bot_name, target_room_id, message_text,
 *   schedule_type, schedule_time, valid_from, valid_until, next_send_at
 * ) VALUES (
 *   '매일 아침 인사', 'branch', 'BR001', 'admin',
 *   'LOA.i', 'channel_123', '좋은 아침입니다!',
 *   'daily', '09:00:00', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR),
 *   DATE_ADD(DATE(NOW()), INTERVAL 1 DAY) + INTERVAL 9 HOUR
 * );
 */