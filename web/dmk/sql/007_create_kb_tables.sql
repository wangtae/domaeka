-- 카카오봇 관련 테이블 생성

-- 1. kb_bot_devices 테이블 (봇 디바이스 관리)
CREATE TABLE IF NOT EXISTS `kb_bot_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_name` varchar(30) NOT NULL COMMENT '봇 이름',
  `device_id` varchar(100) NOT NULL COMMENT '디바이스 고유 ID',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP 주소',
  `client_type` varchar(50) DEFAULT NULL COMMENT '클라이언트 타입 (MessengerBotR, AutoReply 등)',
  `client_version` varchar(20) DEFAULT NULL COMMENT '클라이언트 버전',
  `status` enum('pending','approved','denied','revoked','blocked') NOT NULL DEFAULT 'pending' COMMENT '상태',
  `approved_at` datetime DEFAULT NULL COMMENT '승인 일시',
  `rejection_reason` text COMMENT '거부/차단 사유',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device_id` (`device_id`),
  KEY `idx_bot_name` (`bot_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 디바이스 관리';

-- 2. kb_rooms 테이블 (채팅방 관리)
CREATE TABLE IF NOT EXISTS `kb_rooms` (
  `room_id` varchar(100) NOT NULL COMMENT '채팅방 고유 ID (channelId)',
  `room_name` varchar(255) NOT NULL COMMENT '채팅방 이름',
  `bot_name` varchar(30) NOT NULL COMMENT '봇 이름',
  `room_concurrency` int(11) DEFAULT 1 COMMENT '동시 실행 수',
  `room_owners` json DEFAULT NULL COMMENT '방장 정보 (JSON 배열)',
  `owner_type` enum('distributor','agency','branch') DEFAULT NULL COMMENT '소유자 타입',
  `owner_id` varchar(50) DEFAULT NULL COMMENT '소유자 ID',
  `status` enum('pending','approved','denied','revoked','blocked') NOT NULL DEFAULT 'pending' COMMENT '상태',
  `approved_at` datetime DEFAULT NULL COMMENT '승인 일시',
  `rejection_reason` text COMMENT '거부/차단 사유',
  `description` text COMMENT '설명/메모',
  `log_settings` json DEFAULT NULL COMMENT '로그 설정 (JSON)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  KEY `idx_bot_name` (`bot_name`),
  KEY `idx_status` (`status`),
  KEY `idx_owner` (`owner_type`,`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 채팅방 관리';

-- 3. kb_servers 테이블 (서버 관리)
CREATE TABLE IF NOT EXISTS `kb_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_name` varchar(100) NOT NULL COMMENT '서버 이름',
  `server_host` varchar(255) NOT NULL COMMENT '서버 호스트 (IP 또는 도메인)',
  `server_port` int(11) NOT NULL COMMENT '서버 포트',
  `description` text COMMENT '서버 설명',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '활성화 여부',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_server_name` (`server_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 서버 관리';

-- 4. kb_server_processes 테이블 (서버 프로세스 관리)
CREATE TABLE IF NOT EXISTS `kb_server_processes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_name` varchar(100) NOT NULL COMMENT '프로세스 이름',
  `server_id` int(11) NOT NULL COMMENT '서버 ID',
  `port` int(11) NOT NULL COMMENT '포트 번호',
  `mode` enum('test','prod') NOT NULL DEFAULT 'test' COMMENT '실행 모드',
  `status` enum('stopped','running','error') NOT NULL DEFAULT 'stopped' COMMENT '프로세스 상태',
  `pid` int(11) DEFAULT NULL COMMENT '프로세스 ID',
  `last_start_at` datetime DEFAULT NULL COMMENT '마지막 시작 시간',
  `last_stop_at` datetime DEFAULT NULL COMMENT '마지막 중지 시간',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_process_name` (`process_name`),
  KEY `idx_server_id` (`server_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_server_processes_server` FOREIGN KEY (`server_id`) REFERENCES `kb_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 서버 프로세스 관리';

-- 5. kb_chat_logs 테이블 (채팅 로그)
CREATE TABLE IF NOT EXISTS `kb_chat_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(100) NOT NULL COMMENT '채팅방 ID',
  `sender` varchar(255) NOT NULL COMMENT '발신자',
  `message` text NOT NULL COMMENT '메시지 내용',
  `is_group_chat` tinyint(1) DEFAULT 0 COMMENT '단체 채팅 여부',
  `package_name` varchar(255) DEFAULT NULL COMMENT '패키지명',
  `bot_name` varchar(30) DEFAULT NULL COMMENT '봇 이름',
  `response` text COMMENT '봇 응답',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_bot_name` (`bot_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 채팅 로그';

-- 6. kb_ping_monitor 테이블 (ping 모니터링)
CREATE TABLE IF NOT EXISTS `kb_ping_monitor` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `bot_name` varchar(30) NOT NULL COMMENT '봇 이름',
  `device_id` varchar(100) NOT NULL COMMENT '디바이스 ID',
  `ping_time` datetime NOT NULL COMMENT 'ping 시간',
  `response_time_ms` int(11) DEFAULT NULL COMMENT '응답 시간(ms)',
  `status` enum('success','timeout','error') NOT NULL DEFAULT 'success' COMMENT '상태',
  `error_message` text COMMENT '에러 메시지',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bot_device` (`bot_name`,`device_id`),
  KEY `idx_ping_time` (`ping_time`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 ping 모니터링';

-- 7. kb_schedule 테이블 (스케줄링)
CREATE TABLE IF NOT EXISTS `kb_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '스케줄 제목',
  `description` text COMMENT '스케줄 설명',
  `created_by_type` enum('distributor','agency','branch') NOT NULL COMMENT '등록자 타입',
  `created_by_id` varchar(50) NOT NULL COMMENT '등록자 ID',
  `created_by_mb_id` varchar(50) NOT NULL COMMENT '등록한 관리자 회원 ID',
  `target_bot_name` varchar(30) NOT NULL COMMENT '대상 봇',
  `target_room_id` varchar(100) NOT NULL COMMENT '대상 톡방 ID',
  `message_text` text COMMENT '텍스트 메시지',
  `message_images_1` json COMMENT '이미지 파일 정보 배열 1',
  `message_images_2` json COMMENT '이미지 파일 정보 배열 2',
  `send_interval_seconds` int(5) DEFAULT 1 COMMENT '메시지간 발송 간격(초)',
  `media_wait_time_1` int(5) DEFAULT 0 COMMENT 'message_images_1 대기 시간(ms)',
  `media_wait_time_2` int(5) DEFAULT 0 COMMENT 'message_images_2 대기 시간(ms)',
  `schedule_type` enum('once','weekly','daily') NOT NULL COMMENT '스케줄 타입',
  `schedule_date` date DEFAULT NULL COMMENT '1회성 발송 날짜',
  `schedule_time` time NOT NULL COMMENT '발송 시간',
  `schedule_times` json DEFAULT NULL COMMENT '복수 발송 시간 (JSON 배열)',
  `schedule_weekdays` set('monday','tuesday','wednesday','thursday','friday','saturday','sunday') DEFAULT NULL COMMENT '반복 발송 요일',
  `valid_from` datetime NOT NULL COMMENT '유효기간 시작',
  `valid_until` datetime NOT NULL COMMENT '유효기간 종료',
  `status` enum('active','inactive','completed','error') DEFAULT 'active' COMMENT '스케줄 상태',
  `last_sent_at` datetime DEFAULT NULL COMMENT '마지막 발송 시간',
  `next_send_at` datetime DEFAULT NULL COMMENT '다음 발송 예정 시간',
  `send_count` int(11) DEFAULT 0 COMMENT '총 발송 횟수',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_schedule_polling` (`status`,`next_send_at`,`valid_from`,`valid_until`),
  KEY `idx_created_by` (`created_by_type`,`created_by_id`),
  KEY `idx_target_room` (`target_room_id`,`target_bot_name`),
  KEY `idx_target_bot` (`target_bot_name`,`target_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 스케줄링 발송 설정';

-- 8. kb_schedule_logs 테이블 (스케줄 로그)
CREATE TABLE IF NOT EXISTS `kb_schedule_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL COMMENT '스케줄 ID',
  `target_room_id` varchar(100) NOT NULL COMMENT '발송 대상 톡방 ID',
  `sent_message_text` text COMMENT '발송된 텍스트',
  `sent_images_1` json COMMENT '발송된 이미지 그룹 1',
  `sent_images_2` json COMMENT '발송된 이미지 그룹 2',
  `send_components` varchar(50) COMMENT '실제 발송된 구성 요소',
  `status` enum('success','failed','partial') NOT NULL COMMENT '발송 상태',
  `error_message` text COMMENT '오류 메시지',
  `api_response` json COMMENT 'API 응답 데이터',
  `scheduled_at` datetime NOT NULL COMMENT '예정 발송 시간',
  `started_at` datetime NOT NULL COMMENT '발송 시작 시간',
  `completed_at` datetime DEFAULT NULL COMMENT '발송 완료 시간',
  `duration_ms` int(11) COMMENT '발송 소요 시간(밀리초)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_schedule_logs_schedule` (`schedule_id`),
  KEY `idx_target_room_logs` (`target_room_id`),
  KEY `idx_status_time` (`status`,`scheduled_at`),
  CONSTRAINT `fk_schedule_logs_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `kb_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카카오봇 스케줄링 발송 로그';